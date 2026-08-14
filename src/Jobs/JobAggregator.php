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
        'linkedin' => 8,
    ];

    public static function ensureSchema(): void
    {
        JobCache::ensureSchema();
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

        $listings = self::dedupe($listings);
        $listings = self::postFilter($listings, $query);
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
            $curRank = self::SOURCE_RANK[$cur->source] ?? 9;
            $newRank = self::SOURCE_RANK[$job->source] ?? 9;
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
            if ($query->internship && $job->offerType !== 'unknown' && $job->offerType !== 'internship') {
                return false;
            }
            if ($query->student && $job->seniorityTags !== [] && !in_array('student', $job->seniorityTags, true)
                && $job->offerType !== 'internship') {
                $hay = mb_strtolower($job->title . ' ' . $job->description);
                if (!preg_match('/werkstudent|working student|studierend|hiwi|praktikum/u', $hay)) {
                    return false;
                }
            }
            if ($query->junior && $job->seniorityTags !== [] && !in_array('junior', $job->seniorityTags, true)) {
                if (!preg_match('/junior|einsteiger/iu', $job->title . ' ' . $job->description)) {
                    return false;
                }
            }
            if ($query->graduate && $job->seniorityTags !== [] && !in_array('graduate', $job->seniorityTags, true)) {
                if (!preg_match('/absolvent|graduate/iu', $job->title . ' ' . $job->description)) {
                    return false;
                }
            }
            if ($query->noExperience && $job->seniorityTags !== [] && !in_array('no_experience', $job->seniorityTags, true)) {
                if (!preg_match('/keine berufserfahrung|ohne berufserfahrung|no experience|einsteiger/iu', $job->title . ' ' . $job->description)) {
                    return false;
                }
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
     * @param list<JobListing> $listings
     * @return list<JobListing>
     */
    public static function rank(array $listings, JobQuery $query): array
    {
        $q = mb_strtolower($query->q);
        usort($listings, static function (JobListing $a, JobListing $b) use ($q): int {
            $sa = self::score($a, $q);
            $sb = self::score($b, $q);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            $pa = $a->postedAt ? strtotime($a->postedAt) : 0;
            $pb = $b->postedAt ? strtotime($b->postedAt) : 0;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            $ra = self::SOURCE_RANK[$a->source] ?? 9;
            $rb = self::SOURCE_RANK[$b->source] ?? 9;
            return $ra <=> $rb;
        });
        return $listings;
    }

    private static function score(JobListing $job, string $q): int
    {
        $score = 0;
        if ($q !== '' && mb_stripos($job->title, $q) !== false) {
            $score += 8;
        }
        if ($q !== '' && mb_stripos($job->company, $q) !== false) {
            $score += 3;
        }
        if ($job->postedAt && strtotime($job->postedAt) > time() - 86400) {
            $score += 2;
        }
        $score += 9 - (self::SOURCE_RANK[$job->source] ?? 9);
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
