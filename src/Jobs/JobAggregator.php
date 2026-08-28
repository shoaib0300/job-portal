<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

use App;
use KaamFit\Jobs\Sources\AdzunaSource;
use KaamFit\Jobs\Sources\ArbeitsagenturSource;
use KaamFit\Jobs\Sources\AtsBoardSource;
use KaamFit\Jobs\Sources\InteramtSource;
use KaamFit\Jobs\Sources\JobexportSource;
use KaamFit\Jobs\Sources\JobwareSource;
use KaamFit\Jobs\Sources\LinkedInSource;
use KaamFit\Jobs\Sources\SerpBoardSource;


final class JobAggregator
{
    private const SOURCE_RANK = [
        'arbeitsagentur' => 0,
        'public_sector' => 1,
        'career' => 2,
        'university' => 3,
        'stepstone' => 4,
        'indeed' => 5,
        'xing' => 6,
        'jobware' => 7,
        'glassdoor' => 8,
        'linkedin' => 9,
        'jobexport' => 10,
        'adzuna' => 11,
    ];

    public static function ensureSchema(): void
    {
        JobCache::ensureSchema();
        JobStore::ensureSchema();
        try {
            JobIngestLog::ensureSchema();
        } catch (Throwable) {
            // ignore
        }
        try {
            CareerCompanies::ensureSchema();
        } catch (Throwable) {
            // Auth/DB may not be ready in some CLI contexts.
        }
    }

    /**
     * User-facing search: read from job_listings only (filled by cron ingest).
     *
     * @return array{listings: list<JobListing>, total: int, notices: list<string>, page: int, pages: int}
     */
    public static function search(JobQuery $query, bool $useCache = true): array
    {
        self::ensureSchema();
        // Short-lived ranked result cache (DB is source of truth; this only speeds repeat filters).
        if ($useCache) {
            $cached = JobCache::get($query->cacheKey(), JobCache::SEARCH_TTL);
            if (is_array($cached) && isset($cached['listings']) && is_array($cached['listings'])) {
                $all = [];
                foreach ($cached['listings'] as $row) {
                    if (is_array($row)) {
                        $all[] = JobListing::fromArray($row);
                    }
                }
                return self::paginate($all, $query, is_array($cached['notices'] ?? null) ? $cached['notices'] : []);
            }
        }

        $notices = [];
        $listings = JobStore::search($query);
        if ($listings === [] && JobStore::count() === 0) {
            $notices[] = 'Job index is empty. Jobs are refreshed every 2 hours in the background.';
        }

        // Filter first, then dedupe — otherwise a preferred source (e.g. BA) can replace
        // a Jobware/Jobexport twin and then fail filters, shrinking the result set.
        $listings = self::postFilter($listings, $query);
        $listings = self::dedupe($listings);
        if ($query->matchResume) {
            $listings = self::filterByResumeFit($listings);
        }
        $listings = self::rank($listings, $query);

        JobCache::put($query->cacheKey(), [
            'listings' => array_map(static fn(JobListing $j): array => $j->toArray(), $listings),
            'notices' => $notices,
        ]);

        return self::paginate($listings, $query, $notices);
    }

    /**
     * Live internet fetch used only by cron ingest (and admin “Run ingest now”).
     *
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function searchLive(JobQuery $query): array
    {
        self::ensureSchema();
        $listings = [];
        $notices = [];

        $fanout = [];
        if ($query->wantsSource('arbeitsagentur')) {
            // Up to 5 pages × 50 = 250 jobs per keyword (age via veroeffentlichtseit).
            for ($page = 1; $page <= 5; $page++) {
                $fanout['aa' . $page] = ArbeitsagenturSource::httpSearchRequest($query, $page);
            }
        }
        $bodies = $fanout !== [] ? JobHttp::multiGet($fanout, 14) : [];

        if ($query->wantsSource('arbeitsagentur')) {
            $got = false;
            for ($page = 1; $page <= 5; $page++) {
                $raw = $bodies['aa' . $page] ?? null;
                if (!is_string($raw) || $raw === '') {
                    continue;
                }
                $data = json_decode($raw, true);
                if (!is_array($data)) {
                    continue;
                }
                $got = true;
                $chunk = ArbeitsagenturSource::listingsFromData($data, $query);
                if ($chunk === []) {
                    break;
                }
                $listings = array_merge($listings, $chunk);
            }
            if (!$got) {
                $notices[] = 'Arbeitsagentur did not respond. Try again in a moment.';
            }
        }

        if ($query->wantsSource('linkedin')) {
            $li = LinkedInSource::search($query);
            $listings = array_merge($listings, $li['listings']);
            $notices = array_merge($notices, $li['notices']);
        }

        $serp = SerpBoardSource::search($query);
        $listings = array_merge($listings, $serp['listings']);
        $notices = array_merge($notices, $serp['notices']);

        if ($query->wantsSource('career') || $query->wantsSource('university')) {
            $ats = AtsBoardSource::search($query, $query->wantsSource('university'));
            foreach ($ats['listings'] as $job) {
                if ($query->wantsSource('university') && !$query->wantsSource('career')) {
                    $job->source = 'university';
                }
                $listings[] = $job;
            }
            if ($ats['notice']) {
                $notices[] = $ats['notice'];
            }
        }

        if ($query->wantsSource('public_sector')) {
            $ia = InteramtSource::search($query);
            $listings = array_merge($listings, $ia['listings']);
            if ($ia['notice']) {
                $notices[] = $ia['notice'];
            }
        }

        if ($query->wantsSource('jobexport')) {
            $je = JobexportSource::search($query);
            $listings = array_merge($listings, $je['listings']);
            if ($je['notice']) {
                $notices[] = $je['notice'];
            }
        }

        if ($query->wantsSource('jobware')) {
            $jw = JobwareSource::search($query);
            $listings = array_merge($listings, $jw['listings']);
            $notices = array_merge($notices, $jw['notices']);
        }

        if ($query->wantsSource('adzuna')) {
            $az = AdzunaSource::search($query);
            $listings = array_merge($listings, $az['listings']);
            $notices = array_merge($notices, $az['notices']);
        }

        // Keep every board’s results for the store. Cross-platform duplicates are
        // skipped in JobStore by company+title+posted date (content_key).
        // Do not fingerprint-dedupe here — that preferred AA and wiped Jobexport.
        $listings = self::ingestKeepFilter($listings, $query);

        foreach ($listings as $job) {
            JobCache::putListing($job);
        }

        return ['listings' => $listings, 'notices' => $notices];
    }

    /**
     * Light filter for cron ingest only (age + Germany). Full postFilter is for dashboard DB search.
     *
     * @param list<JobListing> $listings
     * @return list<JobListing>
     */
    private static function ingestKeepFilter(array $listings, JobQuery $query): array
    {
        $days = $query->effectivePostedDays();
        return array_values(array_filter($listings, static function (JobListing $job) use ($days): bool {
            if (!self::isWithinPostedWindow($job, $days)) {
                return false;
            }
            if (JobText::isForeignPrimaryLocation($job->city, $job->country, $job->title)) {
                return false;
            }
            return $job->title !== '';
        }));
    }

    public static function details(string $source, string $externalId): ?JobListing
    {
        self::ensureSchema();
        $stored = JobStore::get($source, $externalId);
        $cached = $stored ?? JobCache::getListing($source, $externalId);
        if ($source === 'arbeitsagentur') {
            $fresh = (new ArbeitsagenturSource())->details($externalId);
            return $fresh ?? $cached;
        }
        if ($source === 'linkedin') {
            return LinkedInSource::details($externalId) ?? $cached;
        }
        if ($source === 'jobware') {
            return JobwareSource::details($externalId) ?? $cached;
        }
        if (isset(SerpBoardSource::BOARDS[$source])) {
            return SerpBoardSource::details($source, $externalId) ?? $cached;
        }
        if ($source === 'career' || $source === 'university') {
            return AtsBoardSource::details($externalId) ?? $cached;
        }
        if ($source === 'public_sector') {
            return InteramtSource::details($externalId) ?? $cached;
        }
        if ($source === 'jobexport') {
            return JobexportSource::details($externalId) ?? $cached;
        }
        if ($source === 'adzuna') {
            return AdzunaSource::details($externalId) ?? $cached;
        }
        return $cached;
    }

    /**
     * @param list<JobListing> $listings
     * @return list<JobListing>
     */
    public static function dedupe(array $listings): array
    {
        $best = [];
        foreach ($listings as $job) {
            $fp = $job->fingerprint;
            if ($fp === '|' || $fp === '||') {
                $best[] = $job;
                continue;
            }
            if (!isset($best[$fp])) {
                $best[$fp] = $job;
                continue;
            }
            $cur = $best[$fp];
            $curRank = self::SOURCE_RANK[$cur->source] ?? 10;
            $newRank = self::SOURCE_RANK[$job->source] ?? 10;
            if ($newRank < $curRank) {
                if ($job->description === '' && $cur->description !== '') {
                    $job->description = $cur->description;
                }
                $best[$fp] = $job;
            } elseif ($cur->description === '' && $job->description !== '') {
                $cur->description = $job->description;
            }
        }
        return array_values($best);
    }

    /**
     * @param list<JobListing> $listings
     * @return list<JobListing>
     */
    public static function postFilter(array $listings, JobQuery $query): array
    {
        return array_values(array_filter($listings, static function (JobListing $job) use ($query): bool {
            if ($query->bundesland !== '' && $job->bundesland !== '') {
                if (mb_stripos($job->bundesland, $query->bundesland) === false
                    && mb_stripos($query->bundesland, $job->bundesland) === false) {
                    return false;
                }
            }
            if ($query->city !== '' && $job->city !== '') {
                if (mb_stripos($job->city, $query->city) === false) {
                    return false;
                }
            }
            if ($query->workMode !== '' && $job->workMode !== 'unknown' && $job->workMode !== $query->workMode) {
                return false;
            }
            if ($query->employment !== '') {
                if ($query->employment === 'mini') {
                    $hay = $job->title . "\n" . $job->description;
                    if ($job->employment !== 'mini' && JobText::employment($hay) !== 'mini') {
                        return false;
                    }
                } elseif ($job->employment !== 'unknown' && $job->employment !== $query->employment) {
                    return false;
                }
            }
            if ($query->hasKeywords() && !$query->matchResume) {
                $blob = $job->title . ' ' . $job->company . ' ' . $job->city . ' ' . $job->description;
                if (!JobText::matchesAnyKeyword($blob, $query->keywords)) {
                    return false;
                }
            }
            // Level / language / salary are ignored in resume-match mode (JD vs resume only).
            if (!$query->matchResume) {
                if ($query->hasLevelFilter() && !self::matchesLevelFilter($job, $query)) {
                    return false;
                }
                if ($query->english) {
                    $hay = $job->title . "\n" . $job->description;
                    if (!in_array('en', $job->languages, true) && !preg_match('/english|englisch/iu', $hay)) {
                        return false;
                    }
                }
                if ($query->germanLevel !== '') {
                    $hay = $job->title . "\n" . $job->description;
                    if (!in_array($query->germanLevel, $job->languages, true)
                        && !preg_match('/\b' . preg_quote($query->germanLevel, '/') . '\b/i', $hay)) {
                        return false;
                    }
                }
                if ($query->hasSalary && $job->salaryText === '' && !JobText::looksLikeSalary($job->description)) {
                    return false;
                }
            }
            // Always drop jobs older than the posted window (max 14 days).
            if (!self::isWithinPostedWindow($job, $query->effectivePostedDays())) {
                return false;
            }
            // Germany-only: foreign primary city/country/title (e.g. N26 Madrid) never shown.
            if (JobText::isForeignPrimaryLocation($job->city, $job->country, $job->title)) {
                return false;
            }
            return true;
        }));
    }

    /** Unix ts for recency sort. Unknown posted date ranks as fresh (not 1970). */
    private static function postedSortTs(JobListing $job): int
    {
        if ($job->postedAt !== null && $job->postedAt !== '') {
            $ts = strtotime($job->postedAt);
            if ($ts !== false) {
                return $ts;
            }
        }
        return time();
    }

    /** True when postedAt is missing/unparseable, or within the last $days days. */
    private static function isWithinPostedWindow(JobListing $job, int $days): bool
    {
        if ($job->postedAt === null || $job->postedAt === '') {
            return true;
        }
        $ts = strtotime($job->postedAt);
        if ($ts === false) {
            return true;
        }
        return $ts >= (time() - ($days * 86400));
    }

    /**
     * Selected Level checkboxes are OR: keep the job if it matches any checked level.
     */
    private static function matchesLevelFilter(JobListing $job, JobQuery $query): bool
    {
        $hay = mb_strtolower($job->title . "\n" . $job->description);
        $tags = $job->seniorityTags;

        if ($query->student) {
            if (in_array('student', $tags, true)
                || $job->offerType === 'internship'
                || preg_match('/werkstudent|working student|studierend|studentische|hiwi|werkstudentin/u', $hay)) {
                return true;
            }
        }
        if ($query->junior) {
            if (in_array('junior', $tags, true)
                || preg_match('/\bjunior\b|einsteiger|berufseinsteiger|entry[-\s]?level/u', $hay)) {
                return true;
            }
        }
        if ($query->graduate) {
            if (in_array('graduate', $tags, true)
                || preg_match('/absolvent|graduate|hochschulabsolvent|career starter|berufseinstieg/u', $hay)) {
                return true;
            }
        }
        if ($query->internship) {
            if ($job->offerType === 'internship' || $job->offerType === 'training'
                || preg_match('/\bpraktikum\b|\binternship\b|\bintern\b|\btrainee\b|\bazubi\b|\bausbildung\b/u', $hay)) {
                return true;
            }
        }
        if ($query->noExperience) {
            if (in_array('no_experience', $tags, true)
                || preg_match('/keine berufserfahrung|ohne berufserfahrung|no experience|ohne vorkenntnisse|berufseinsteiger|kein[e]?\s+erfahrung/u', $hay)) {
                return true;
            }
        }
        if ($query->minijob) {
            if ($job->employment === 'mini'
                || in_array('minijob', $tags, true)
                || preg_match('/\bminijob\b|\bmini[- ]?job\b|geringfügig|geringfuegig|450\s*€|450\s*eur|520\s*eur|556\s*eur|midi[- ]?job/u', $hay)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<JobListing> $listings
     * @return list<JobListing>
     */
    public static function rank(array $listings, JobQuery $query): array
    {
        $keywords = array_map(static fn(string $k): string => mb_strtolower($k), $query->keywords);
        $resumeTerms = $query->matchResume ? ResumeJobMatch::scoreTerms() : [];
        usort($listings, static function (JobListing $a, JobListing $b) use ($keywords, $resumeTerms, $query): int {
            if ($query->sort === 'recent') {
                $pa = self::postedSortTs($a);
                $pb = self::postedSortTs($b);
                if ($pa !== $pb) {
                    return $pb <=> $pa;
                }
            }
            if ($query->matchResume) {
                $ra = ResumeJobMatch::fitScore($a, $resumeTerms);
                $rb = ResumeJobMatch::fitScore($b, $resumeTerms);
                if ($ra !== $rb) {
                    return $rb <=> $ra;
                }
            }
            $sa = self::score($a, $keywords);
            $sb = self::score($b, $keywords);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            if ($query->sort !== 'recent') {
                $pa = self::postedSortTs($a);
                $pb = self::postedSortTs($b);
                if ($pa !== $pb) {
                    return $pb <=> $pa;
                }
            }
            $ra = self::SOURCE_RANK[$a->source] ?? 10;
            $rb = self::SOURCE_RANK[$b->source] ?? 10;
            return $ra <=> $rb;
        });
        return $listings;
    }

    /**
     * @param list<JobListing> $listings
     * @return list<JobListing>
     */
    private static function filterByResumeFit(array $listings): array
    {
        $terms = ResumeJobMatch::scoreTerms();
        if ($terms === []) {
            return $listings;
        }
        return array_values(array_filter(
            $listings,
            static fn(JobListing $job): bool => ResumeJobMatch::fitScore($job, $terms) >= ResumeJobMatch::minFitScore()
        ));
    }

    /** @param list<string> $keywords */
    private static function score(JobListing $job, array $keywords): int
    {
        $score = 0;
        $title = mb_strtolower($job->title);
        $company = mb_strtolower($job->company);
        foreach ($keywords as $q) {
            if ($q === '') {
                continue;
            }
            if (mb_strpos($title, $q) !== false) {
                $score += 8;
            }
            if (mb_strpos($company, $q) !== false) {
                $score += 3;
            }
        }
        if ($job->postedAt && strtotime($job->postedAt) > time() - 86400) {
            $score += 2;
        }
        $score += 10 - (self::SOURCE_RANK[$job->source] ?? 10);
        return $score;
    }

    /**
     * @param list<JobListing> $listings
     * @param list<string> $notices
     * @return array{listings: list<JobListing>, total: int, notices: list<string>, page: int, pages: int}
     */
    private static function paginate(array $listings, JobQuery $query, array $notices): array
    {
        $total = count($listings);
        $pages = max(1, (int) ceil($total / $query->size));
        $page = min($query->page, $pages);
        $slice = array_slice($listings, ($page - 1) * $query->size, $query->size);
        return [
            'listings' => $slice,
            'total' => $total,
            'notices' => self::visibleNotices($notices),
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * Config / token / sample-board warnings stay in cache for developers, but are
     * hidden from the jobs UI unless APP_ENV is dev/local/development.
     *
     * @param list<string> $notices
     * @return list<string>
     */
    private static function visibleNotices(array $notices): array
    {
        $unique = array_values(array_unique($notices));
        if (App::isDev()) {
            return $unique;
        }

        return array_values(array_filter(
            $unique,
            static fn(string $notice): bool => !self::isOperationalNotice($notice)
        ));
    }

    private static function isOperationalNotice(string $notice): bool
    {
        $hay = mb_strtolower($notice);
        return str_contains($hay, 'bright_data')
            || str_contains($hay, 'sitemap boards return a sample')
            || str_contains($hay, 'company career sites skipped')
            || str_contains($hay, 'site boards')
            || str_contains($hay, 'no job sitemap found')
            || str_contains($hay, 'adzuna_app');
    }
}
