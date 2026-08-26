<?php

declare(strict_types=1);

final class AtsBoardSource
{
    /** @return list<array{type:string,slug:string,label:string,url?:string}> */
    public static function boards(): array
    {
        $uid = Auth::id();
        if ($uid > 0) {
            $fromDb = CareerCompanies::enabledBoards($uid);
            if ($fromDb !== []) {
                return $fromDb;
            }
        }
        // Fallback before seed / CLI
        return [
            ['type' => 'greenhouse', 'slug' => 'n26', 'label' => 'N26', 'url' => ''],
            ['type' => 'greenhouse', 'slug' => 'celonis', 'label' => 'Celonis', 'url' => ''],
            ['type' => 'personio', 'slug' => 'personio', 'label' => 'Personio', 'url' => ''],
            ['type' => 'site', 'slug' => 'jobs.mercedes-benz.com', 'label' => 'Mercedes-Benz', 'url' => 'https://jobs.mercedes-benz.com/'],
            ['type' => 'site', 'slug' => 'www.bmwgroup.jobs', 'label' => 'BMW Group', 'url' => 'https://www.bmwgroup.jobs/de/de.html'],
        ];
    }

    /**
     * @return array{listings: list<JobListing>, notice: ?string}
     */
    public static function search(JobQuery $query, bool $studentBias = false): array
    {
        $boards = self::boards();
        $apiBoards = [];
        $siteBoards = [];
        foreach ($boards as $board) {
            if (($board['type'] ?? '') === 'site') {
                $siteBoards[] = $board;
            } else {
                $apiBoards[] = $board;
            }
        }

        $listings = [];
        $ok = 0;
        $notices = [];

        $requests = [];
        foreach ($apiBoards as $board) {
            $key = $board['type'] . ':' . $board['slug'];
            if ($board['type'] === 'greenhouse') {
                $requests[$key] = [
                    'url' => 'https://boards-api.greenhouse.io/v1/boards/' . rawurlencode($board['slug']) . '/jobs?content=true',
                ];
            } elseif ($board['type'] === 'personio') {
                $requests[$key] = [
                    'url' => 'https://' . rawurlencode($board['slug']) . '.jobs.personio.de/xml?language=en',
                ];
            } elseif ($board['type'] === 'smartrecruiters') {
                $requests[$key] = [
                    'url' => 'https://api.smartrecruiters.com/v1/companies/' . rawurlencode($board['slug']) . '/postings?limit=100',
                ];
            }
        }
        $bodies = $requests !== [] ? JobHttp::multiGet($requests, 12) : [];
        foreach ($apiBoards as $board) {
            $key = $board['type'] . ':' . $board['slug'];
            $body = $bodies[$key] ?? null;
            if ($body === null) {
                continue;
            }
            $ok++;
            if ($board['type'] === 'greenhouse') {
                $listings = array_merge($listings, self::parseGreenhouse($body, $board['slug'], $board['label']));
            } elseif ($board['type'] === 'personio') {
                $listings = array_merge($listings, self::parsePersonio($body, $board['slug'], $board['label']));
            } elseif ($board['type'] === 'smartrecruiters') {
                $listings = array_merge($listings, self::parseSmartRecruiters($body, $board['slug'], $board['label']));
            }
        }

        $siteResult = self::searchSiteBoards($siteBoards, $query);
        $listings = array_merge($listings, $siteResult['listings']);
        $ok += $siteResult['ok'];
        if ($siteResult['notice']) {
            $notices[] = $siteResult['notice'];
        }

        $needle = mb_strtolower(trim($query->searchWas() . ($studentBias ? ' werkstudent praktikum hiwi' : '')));
        if ($needle !== '') {
            $tokens = preg_split('/\s+/u', $needle) ?: [];
            $listings = array_values(array_filter(
                $listings,
                static function (JobListing $job) use ($tokens): bool {
                    $hay = mb_strtolower($job->title . ' ' . $job->company . ' ' . $job->city . ' ' . $job->description);
                    foreach ($tokens as $tok) {
                        if ($tok !== '' && mb_strpos($hay, $tok) !== false) {
                            return true;
                        }
                    }
                    return $tokens === [];
                }
            ));
        }

        if ($query->city !== '') {
            $city = mb_strtolower($query->city);
            $listings = array_values(array_filter(
                $listings,
                static fn(JobListing $job): bool => $job->city === '' || mb_stripos($job->city, $city) !== false
            ));
        }

        // Greenhouse/Personio boards are global (N26 Madrid, Celonis Spain, …). Keep Germany only.
        $listings = array_values(array_filter(
            $listings,
            static function (JobListing $job): bool {
                if (JobText::looksLikeGermany($job->city, $job->bundesland, $job->country, $job->title . ' ' . $job->description)) {
                    return true;
                }
                // Remote / blank location: keep only if text clearly ties to Germany.
                $hay = mb_strtolower($job->city . ' ' . $job->country . ' ' . $job->title . ' ' . mb_substr($job->description, 0, 800));
                if ($job->city === '' || preg_match('/\b(remote|home.?office|anywhere|emea|europe|eu\b)\b/u', $hay)) {
                    return JobText::looksLikeGermany('', '', '', $hay);
                }
                return false;
            }
        ));

        if ($ok === 0 && $apiBoards === [] && $siteBoards !== [] && !SerpBoardSource::configured()) {
            $notices[] = 'Company career sites need BRIGHT_DATA_API_TOKEN for Mercedes/BMW-style boards. Greenhouse/Personio still work without it.';
        } elseif ($ok === 0) {
            $notices[] = 'Career-page boards did not respond.';
        } elseif ($siteBoards !== [] && !SerpBoardSource::configured()) {
            $notices[] = 'Site boards (Mercedes/BMW/…) skipped without BRIGHT_DATA_API_TOKEN. API boards still listed (Germany only).';
        }

        return ['listings' => $listings, 'notice' => $notices !== [] ? implode(' ', $notices) : null];
    }

    /**
     * @param list<array{type:string,slug:string,label:string,url?:string}> $siteBoards
     * @return array{listings: list<JobListing>, ok: int, notice: ?string}
     */
    private static function searchSiteBoards(array $siteBoards, JobQuery $query): array
    {
        if ($siteBoards === []) {
            return ['listings' => [], 'ok' => 0, 'notice' => null];
        }
        if (!SerpBoardSource::configured()) {
            return [
                'listings' => [],
                'ok' => 0,
                'notice' => count($siteBoards) . ' company career sites skipped (set BRIGHT_DATA_API_TOKEN for site boards like Mercedes/BMW).',
            ];
        }

        // Cap Bright Data cost/latency: prefer boards whose name matches role keywords.
        $picked = self::pickSiteBoards($siteBoards, $query, 12);
        $was = $query->serpWas();
        $where = $query->whereText();
        $zone = trim((string) (getenv('BRIGHT_DATA_ZONE') ?: 'web_unlocker1'));
        $token = SerpBoardSource::token();
        $listings = [];
        $ok = 0;

        foreach ($picked as $board) {
            $host = $board['slug'];
            $q = trim('site:' . $host . ' ' . $was . ' ' . $where . ' (jobs OR Karriere OR Stellenangebot)');
            $google = 'https://www.google.com/search?q=' . rawurlencode($q) . '&num=8&hl=de&gl=de&brd_json=1';
            $data = JobHttp::postJson(
                'https://api.brightdata.com/request',
                ['zone' => $zone, 'url' => $google, 'format' => 'json'],
                ['Authorization: Bearer ' . $token],
                16
            );
            if ($data === null) {
                continue;
            }
            $ok++;
            $organic = $data['organic'] ?? $data['organic_results'] ?? $data['results'] ?? [];
            if (!is_array($organic) && isset($data['body'])) {
                $inner = is_string($data['body']) ? json_decode($data['body'], true) : $data['body'];
                if (is_array($inner)) {
                    $organic = $inner['organic'] ?? $inner['organic_results'] ?? [];
                }
            }
            if (!is_array($organic)) {
                continue;
            }
            foreach ($organic as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $title = (string) ($row['title'] ?? $row['name'] ?? '');
                $link = (string) ($row['link'] ?? $row['url'] ?? '');
                $snip = (string) ($row['description'] ?? $row['snippet'] ?? '');
                if ($title === '' || $link === '') {
                    continue;
                }
                $title = trim((string) preg_replace('/\s[-–|]\s.+$/u', '', $title));
                $job = new JobListing(
                    'career',
                    'site:' . hash('sha256', $link),
                    $title,
                    $board['label'],
                    '',
                    '',
                    'Germany',
                    'unknown',
                    'unknown',
                    'job',
                    [],
                    [],
                    '',
                    null,
                    $link,
                    $snip,
                );
                $job->applyUrl = $link;
                $listings[] = JobText::enrich($job);
            }
        }

        return ['listings' => $listings, 'ok' => $ok, 'notice' => null];
    }

    /**
     * @param list<array{type:string,slug:string,label:string,url?:string}> $siteBoards
     * @return list<array{type:string,slug:string,label:string,url?:string}>
     */
    private static function pickSiteBoards(array $siteBoards, JobQuery $query, int $limit): array
    {
        if (count($siteBoards) <= $limit) {
            return $siteBoards;
        }
        $needles = array_map('mb_strtolower', $query->keywords);
        $scored = [];
        foreach ($siteBoards as $i => $board) {
            $score = 0;
            $label = mb_strtolower($board['label'] . ' ' . $board['slug']);
            foreach ($needles as $n) {
                if ($n !== '' && mb_strpos($label, $n) !== false) {
                    $score += 10;
                }
            }
            // Always keep Mercedes / BMW near the front of large catalogs.
            if (str_contains($label, 'mercedes') || str_contains($label, 'bmw')) {
                $score += 5;
            }
            $scored[] = [$score, $i, $board];
        }
        usort($scored, static fn(array $a, array $b): int => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as $row) {
            $out[] = $row[2];
        }
        return $out;
    }

    public static function details(string $externalId): ?JobListing
    {
        return JobCache::getListing('career', $externalId)
            ?? JobCache::getListing('university', $externalId);
    }

    /** @return list<JobListing> */
    private static function parseGreenhouse(string $body, string $slug, string $label = ''): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [];
        }
        $jobs = $data['jobs'] ?? [];
        if (!is_array($jobs)) {
            return [];
        }
        $out = [];
        foreach ($jobs as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $url = (string) ($row['absolute_url'] ?? '');
            if ($id === '' || $title === '') {
                continue;
            }
            $loc = '';
            if (isset($row['location']) && is_array($row['location'])) {
                $loc = (string) ($row['location']['name'] ?? '');
            }
            $desc = JobText::stripHtml((string) ($row['content'] ?? ''));
            $company = (string) ($row['company_name'] ?? '');
            if ($company === '') {
                $company = $label !== '' ? $label : $slug;
            }
            $job = new JobListing(
                'career',
                'gh:' . $slug . ':' . $id,
                $title,
                $company,
                self::cityFromLocation($loc),
                '',
                self::countryFromLocation($loc),
                'unknown',
                'unknown',
                'job',
                [],
                [],
                '',
                isset($row['updated_at']) ? substr((string) $row['updated_at'], 0, 10) : null,
                $url,
                $desc,
            );
            $job->applyUrl = $url;
            $out[] = JobText::enrich($job);
        }
        return $out;
    }

    /** @return list<JobListing> */
    private static function parsePersonio(string $body, string $slug, string $label = ''): array
    {
        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            return [];
        }
        $out = [];
        $company = $label !== '' ? $label : $slug;
        foreach ($xml->position as $pos) {
            $id = trim((string) ($pos->id ?? ''));
            $title = trim((string) ($pos->name ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }
            $office = trim((string) ($pos->office ?? ''));
            $descParts = [];
            if (isset($pos->jobDescriptions->jobDescription)) {
                foreach ($pos->jobDescriptions->jobDescription as $block) {
                    $descParts[] = JobText::stripHtml((string) ($block->value ?? ''));
                }
            }
            $schedule = trim((string) ($pos->schedule ?? ''));
            $emp = trim((string) ($pos->employmentType ?? ''));
            $created = trim((string) ($pos->createdAt ?? ''));
            $url = 'https://' . $slug . '.jobs.personio.de/job/' . rawurlencode($id);
            $job = new JobListing(
                'career',
                'pe:' . $slug . ':' . $id,
                $title,
                $company,
                $office,
                '',
                'Germany',
                JobText::workMode($schedule . ' ' . implode(' ', $descParts)),
                JobText::employment($emp . ' ' . $schedule),
                'job',
                [],
                [],
                '',
                $created !== '' ? substr($created, 0, 10) : null,
                $url,
                implode("\n\n", $descParts),
            );
            $job->applyUrl = $url . '/apply';
            $out[] = JobText::enrich($job);
        }
        return $out;
    }

    /** @return list<JobListing> */
    private static function parseSmartRecruiters(string $body, string $slug, string $label = ''): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [];
        }
        $jobs = $data['content'] ?? $data['postings'] ?? [];
        if (!is_array($jobs)) {
            return [];
        }
        $company = $label !== '' ? $label : $slug;
        $out = [];
        foreach ($jobs as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? $row['uuid'] ?? '');
            $title = (string) ($row['name'] ?? $row['title'] ?? '');
            $url = (string) ($row['applyUrl'] ?? $row['ref'] ?? '');
            if ($url === '' && $id !== '') {
                $url = 'https://jobs.smartrecruiters.com/' . rawurlencode($slug) . '/' . rawurlencode($id);
            }
            if ($id === '' || $title === '') {
                continue;
            }
            $loc = '';
            if (isset($row['location']) && is_array($row['location'])) {
                $city = (string) ($row['location']['city'] ?? '');
                $region = (string) ($row['location']['region'] ?? '');
                $loc = trim($city . ($city && $region ? ', ' : '') . $region);
            }
            $desc = JobText::stripHtml((string) ($row['jobAd']['sections']['jobDescription']['text'] ?? $row['description'] ?? ''));
            $job = new JobListing(
                'career',
                'sr:' . $slug . ':' . $id,
                $title,
                $company,
                self::cityFromLocation($loc),
                '',
                self::countryFromLocation($loc) ?: 'Germany',
                'unknown',
                'unknown',
                'job',
                [],
                [],
                '',
                isset($row['releasedDate']) ? substr((string) $row['releasedDate'], 0, 10) : null,
                $url,
                $desc,
            );
            $job->applyUrl = $url;
            $out[] = JobText::enrich($job);
        }
        return $out;
    }

    private static function cityFromLocation(string $loc): string
    {
        $loc = trim($loc);
        if ($loc === '') {
            return '';
        }
        $parts = array_map('trim', explode(',', $loc));
        return $parts[0] ?? '';
    }

    private static function countryFromLocation(string $loc): string
    {
        $loc = trim($loc);
        if ($loc === '') {
            return '';
        }
        if (JobText::looksLikeGermany('', '', '', $loc)) {
            return 'Germany';
        }
        if (preg_match('/\b(spain|españa)\b/iu', $loc)) {
            return 'Spain';
        }
        if (preg_match('/\b(france)\b/iu', $loc)) {
            return 'France';
        }
        $parts = array_map('trim', explode(',', $loc));
        $last = $parts[count($parts) - 1] ?? '';
        // Avoid treating "Berlin, Berlin" as country Berlin
        if ($last !== '' && isset($parts[0]) && mb_strtolower($last) === mb_strtolower($parts[0])) {
            return JobText::looksLikeGermany($parts[0], '', '', '') ? 'Germany' : '';
        }
        return $last !== '' ? $last : '';
    }
}
