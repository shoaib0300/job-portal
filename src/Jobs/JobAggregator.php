<?php

declare(strict_types=1);

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
    ];

    public static function ensureSchema(): void
    {
        JobCache::ensureSchema();
        try {
            CareerCompanies::ensureSchema();
        } catch (Throwable) {
            // Auth/DB may not be ready in some CLI contexts.
        }
    }

    /**
     * @return array{listings: list<JobListing>, total: int, notices: list<string>, page: int, pages: int}
     */
    public static function search(JobQuery $query, bool $useCache = true): array
    {
        self::ensureSchema();
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

        $listings = [];
        $notices = [];

        if ($query->wantsSource('arbeitsagentur')) {
            $aa = (new ArbeitsagenturSource())->search($query);
            $listings = array_merge($listings, $aa['listings']);
            if ($aa['notice']) {
                $notices[] = $aa['notice'];
            }
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

        $listings = self::dedupe($listings);
        $listings = self::postFilter($listings, $query);
        if ($query->matchResume) {
            $listings = self::filterByResumeFit($listings);
        }
        $listings = self::rank($listings, $query);

        foreach ($listings as $job) {
            JobCache::putListing($job);
        }

        JobCache::put($query->cacheKey(), [
            'listings' => array_map(static fn(JobListing $j): array => $j->toArray(), $listings),
            'notices' => $notices,
        ]);

        return self::paginate($listings, $query, $notices);
    }

    public static function details(string $source, string $externalId): ?JobListing
    {
        self::ensureSchema();
        $cached = JobCache::getListing($source, $externalId);
        if ($source === 'arbeitsagentur') {
            $fresh = (new ArbeitsagenturSource())->details($externalId);
            return $fresh ?? $cached;
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
            if ($query->employment !== '' && $job->employment !== 'unknown' && $job->employment !== $query->employment) {
                return false;
            }
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
            if ($query->postedDays > 0 && $job->postedAt) {
                $ts = strtotime($job->postedAt);
                if ($ts !== false && $ts < (time() - ($query->postedDays * 86400))) {
                    return false;
                }
            }
            return true;
        }));
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
                $pa = $a->postedAt ? (int) strtotime($a->postedAt) : 0;
                $pb = $b->postedAt ? (int) strtotime($b->postedAt) : 0;
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
                $pa = $a->postedAt ? (int) strtotime($a->postedAt) : 0;
                $pb = $b->postedAt ? (int) strtotime($b->postedAt) : 0;
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
            static fn(JobListing $job): bool => ResumeJobMatch::fitScore($job, $terms) >= 5
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
            'notices' => array_values(array_unique($notices)),
            'page' => $page,
            'pages' => $pages,
        ];
    }
}
