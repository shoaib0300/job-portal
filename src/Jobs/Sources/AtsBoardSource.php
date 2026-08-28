<?php

declare(strict_types=1);

namespace KaamFit\Jobs\Sources;

use App;
use Auth;
use KaamFit\Jobs\JobCache;
use KaamFit\Jobs\JobHttp;
use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobText;
use KaamFit\Jobs\CareerCompanies;


final class AtsBoardSource
{
    /** @return list<array{type:string,slug:string,label:string,url?:string}> */
    public static function boards(?JobQuery $query = null): array
    {
        $uid = Auth::id();
        $filter = $query !== null ? $query->companies : [];
        $fromDb = CareerCompanies::enabledBoards($uid, $filter);
        if ($fromDb !== []) {
            return $fromDb;
        }
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
        $boards = self::boards($query);
        $apiBoards = [];
        $siteBoards = [];
        $sitemapBoards = [];
        $sfBoards = [];
        foreach ($boards as $board) {
            $t = (string) ($board['type'] ?? '');
            if ($t === 'site') {
                $siteBoards[] = $board;
            } elseif ($t === 'sitemap') {
                $sitemapBoards[] = $board;
            } elseif ($t === 'successfactors') {
                $sfBoards[] = $board;
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

        $sitemapResult = self::searchSitemapBoards($sitemapBoards, $query);
        $listings = array_merge($listings, $sitemapResult['listings']);
        $ok += $sitemapResult['ok'];
        if ($sitemapResult['notice']) {
            $notices[] = $sitemapResult['notice'];
        }

        $sfResult = self::searchSuccessFactorsBoards($sfBoards, $query);
        $listings = array_merge($listings, $sfResult['listings']);
        $ok += $sfResult['ok'];
        if ($sfResult['notice']) {
            $notices[] = $sfResult['notice'];
        }

        $siteResult = self::searchSiteBoards($siteBoards, $query);
        $listings = array_merge($listings, $siteResult['listings']);
        $ok += $siteResult['ok'];
        if ($siteResult['notice']) {
            $notices[] = $siteResult['notice'];
        }

        $needle = mb_strtolower(trim($query->searchWas() . ($studentBias ? ' werkstudent praktikum hiwi' : '')));
        if ($query->hasKeywords() || $needle !== '') {
            $keywords = $query->keywords;
            if ($keywords === [] && $needle !== '') {
                $keywords = preg_split('/\s+/u', $needle) ?: [];
            }
            $listings = array_values(array_filter(
                $listings,
                static function (JobListing $job) use ($keywords): bool {
                    $hay = $job->title . ' ' . $job->company . ' ' . $job->city . ' ' . $job->description;
                    return JobText::matchesAnyKeyword($hay, $keywords);
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
        // Primary city/country/title wins — Berlin HQ mentions in the JD must not keep Madrid roles.
        $listings = array_values(array_filter(
            $listings,
            static function (JobListing $job): bool {
                if (JobText::isForeignPrimaryLocation($job->city, $job->country, $job->title)) {
                    return false;
                }
                if (JobText::looksLikeGermany($job->city, $job->bundesland, $job->country, $job->title)) {
                    return true;
                }
                // Remote / blank location: keep only if text clearly ties to Germany (and not a foreign market title).
                $hay = mb_strtolower($job->city . ' ' . $job->country . ' ' . $job->title . ' ' . mb_substr($job->description, 0, 800));
                if ($job->city === '' || preg_match('/\b(remote|home.?office|anywhere|emea|europe|eu\b)\b/u', $hay)) {
                    if (JobText::isForeignPrimaryLocation('', '', $job->title)) {
                        return false;
                    }
                    return JobText::looksLikeGermany('', '', '', $hay);
                }
                return false;
            }
        ));

        if ($ok === 0 && $apiBoards === [] && $siteBoards !== [] && !SerpBoardSource::configured()) {
            if (App::isDev()) {
                $notices[] = 'Company career sites need BRIGHT_DATA_API_TOKEN for Mercedes/BMW-style boards. Greenhouse/Personio still work without it.';
            }
        } elseif ($ok === 0) {
            $notices[] = 'Career-page boards did not respond.';
        } elseif ($siteBoards !== [] && !SerpBoardSource::configured() && App::isDev()) {
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
                'notice' => App::isDev()
                    ? (count($siteBoards) . ' company career sites skipped (set BRIGHT_DATA_API_TOKEN for site boards like Mercedes/BMW).')
                    : null,
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
     * SAP SuccessFactors career sites (e.g. jobs.nordex-online.com) — HTML list + startrow pages.
     *
     * @param list<array{type:string,slug:string,label:string,url?:string}> $boards
     * @return array{listings: list<JobListing>, ok: int, notice: ?string}
     */
    private static function searchSuccessFactorsBoards(array $boards, JobQuery $query): array
    {
        if ($boards === []) {
            return ['listings' => [], 'ok' => 0, 'notice' => null];
        }

        $listings = [];
        $ok = 0;
        $notices = [];
        $focused = $query->companies !== [];

        foreach ($boards as $board) {
            $host = (string) ($board['slug'] ?? '');
            $base = rtrim((string) ($board['url'] ?? ''), '/');
            if ($base === '') {
                $base = 'https://' . $host . '/search';
            }
            if (!str_contains(mb_strtolower($base), '/search')) {
                $base = preg_replace('#/$#', '', $base) . '/search';
            }
            $origin = preg_replace('#^(https?://[^/]+).*$#i', '$1', $base) ?: ('https://' . $host);
            // Focused company filter → crawl deeper; otherwise keep ingest bounded.
            $maxPages = $focused ? 24 : 8;
            $pageListings = [];
            $boardOk = false;
            for ($page = 0; $page < $maxPages; $page++) {
                $start = $page * 25;
                $url = $base . (str_contains($base, '?') ? '&' : '?') . 'startrow=' . $start;
                $html = JobHttp::get($url, [
                    'Accept: text/html,application/xhtml+xml',
                    'Accept-Language: en-US,en;q=0.9,de;q=0.8',
                    'User-Agent: Mozilla/5.0 (compatible; MNK-Jobs/1.1; +https://mnk.ddev.site/)',
                ], 18);
                if (!is_string($html) || $html === '' || !str_contains($html, 'data-row')) {
                    break;
                }
                $boardOk = true;
                $chunk = self::parseSuccessFactorsList($html, $origin, (string) ($board['label'] ?? $host));
                if ($chunk === []) {
                    break;
                }
                foreach ($chunk as $job) {
                    $pageListings[$job->externalId] = $job;
                }
                if (count($chunk) < 20) {
                    break;
                }
            }
            if ($boardOk) {
                $ok++;
                foreach ($pageListings as $job) {
                    $listings[] = $job;
                }
            } elseif (App::isDev()) {
                $notices[] = ($board['label'] ?? $host) . ' SuccessFactors board did not respond.';
            }
        }

        return [
            'listings' => $listings,
            'ok' => $ok,
            'notice' => $notices !== [] ? implode(' ', $notices) : null,
        ];
    }

    /** @return list<JobListing> */
    private static function parseSuccessFactorsList(string $html, string $origin, string $company): array
    {
        $dom = new \DOMDocument();
        $ok = @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$ok) {
            return [];
        }
        $xp = new \DOMXPath($dom);
        $out = [];
        foreach ($xp->query('//tr[contains(@class,"data-row")]') as $tr) {
            if (!$tr instanceof \DOMElement) {
                continue;
            }
            $a = null;
            foreach ($xp->query('.//a[contains(@class,"jobTitle-link")]', $tr) as $link) {
                if ($link instanceof \DOMElement) {
                    $a = $link;
                    break;
                }
            }
            if ($a === null) {
                continue;
            }
            $href = html_entity_decode(trim($a->getAttribute('href')), ENT_QUOTES | ENT_HTML5);
            $title = trim(preg_replace('/\s+/u', ' ', $a->textContent ?? '') ?? '');
            if ($title === '' || $href === '') {
                continue;
            }
            if (!preg_match('#/(\d+)/?\s*$#', $href, $im)) {
                continue;
            }
            $id = $im[1];
            $url = str_starts_with($href, 'http') ? $href : ($origin . (str_starts_with($href, '/') ? $href : '/' . $href));
            $loc = '';
            $locNode = $xp->query('.//span[contains(@class,"jobLocation")]', $tr);
            if ($locNode !== false && $locNode->length > 0) {
                $loc = trim(preg_replace('/\s+/u', ' ', $locNode->item(0)?->textContent ?? '') ?? '');
                $loc = preg_replace('/\+\d+\s*more…?/iu', '', $loc) ?? $loc;
                $loc = trim($loc);
            }
            $posted = null;
            $dateNode = $xp->query('.//span[contains(@class,"jobDate")]', $tr);
            if ($dateNode !== false && $dateNode->length > 0) {
                $posted = self::parseSfDate(trim($dateNode->item(0)?->textContent ?? ''));
            }
            [$city, $country] = self::parseSfLocation($loc);
            $job = new JobListing(
                'career',
                'sf:' . $id,
                $title,
                $company,
                $city,
                '',
                $country !== '' ? $country : 'Germany',
                'unknown',
                'unknown',
                'job',
                [],
                [],
                '',
                $posted,
                $url,
                '',
            );
            $job->applyUrl = $url;
            $out[] = JobText::enrich($job);
        }
        return $out;
    }

    /** @return array{0:string,1:string} city, country */
    private static function parseSfLocation(string $loc): array
    {
        $loc = trim($loc);
        if ($loc === '') {
            return ['', ''];
        }
        $ccMap = [
            'DE' => 'Germany',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'US' => 'United States',
            'ES' => 'Spain',
            'FR' => 'France',
            'CA' => 'Canada',
            'IN' => 'India',
            'PT' => 'Portugal',
            'BR' => 'Brazil',
            'TR' => 'Turkey',
            'MX' => 'Mexico',
            'GB' => 'United Kingdom',
            'UK' => 'United Kingdom',
            'NL' => 'Netherlands',
            'PL' => 'Poland',
            'IT' => 'Italy',
            'SE' => 'Sweden',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'NO' => 'Norway',
            'IE' => 'Ireland',
            'BE' => 'Belgium',
            'CZ' => 'Czech Republic',
            'AU' => 'Australia',
            'CN' => 'China',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'ZA' => 'South Africa',
        ];
        // Bare country code: "US", "DE"
        if (preg_match('/^[A-Z]{2}$/', $loc)) {
            return ['', $ccMap[$loc] ?? $loc];
        }
        // "... DE, 22419" or "... CA, H3B 2E3" — country near the end.
        if (preg_match('/,\s*([A-Z]{2})\s*(?:,\s*[^,]+)?\s*$/u', $loc, $m)) {
            $cc = strtoupper($m[1]);
            $country = $ccMap[$cc] ?? $cc;
            $city = trim(explode(',', $loc)[0]);
            if (preg_match('/^(united states|canada|spain|india|france|germany|mexico|brazil)$/iu', $city)) {
                return ['', $country];
            }
            return [$city, $country];
        }
        // "Hamburg, DE, 22419"
        if (preg_match('/^([^,]+),\s*([A-Z]{2})\b/u', $loc, $m)) {
            $cc = strtoupper($m[2]);
            return [trim($m[1]), $ccMap[$cc] ?? $cc];
        }
        return [$loc, ''];
    }

    private static function parseSfDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    /**
     * Career boards that expose job URLs via sitemap.xml (DIS AG, Rossmann, …).
     *
     * @param list<array{type:string,slug:string,label:string,url?:string}> $boards
     * @return array{listings: list<JobListing>, ok: int, notice: ?string}
     */
    private static function searchSitemapBoards(array $boards, JobQuery $query): array
    {
        if ($boards === []) {
            return ['listings' => [], 'ok' => 0, 'notice' => null];
        }

        $keywords = $query->keywords;
        $limit = $keywords === [] ? 40 : 80;
        $maxSitemapPages = $keywords === [] ? 2 : 8;

        $listings = [];
        $ok = 0;
        $notices = [];

        foreach ($boards as $board) {
            $host = (string) ($board['slug'] ?? '');
            $base = rtrim((string) ($board['url'] ?? ''), '/');
            if ($base === '') {
                $base = 'https://' . $host;
            }
            $pages = self::discoverJobSitemapPages($base);
            if ($pages === []) {
                if (App::isDev()) {
                    $notices[] = ($board['label'] ?? $host) . ': no job sitemap found.';
                }
                continue;
            }
            $pages = array_slice($pages, 0, $maxSitemapPages);
            $reqs = [];
            foreach ($pages as $i => $pageUrl) {
                $reqs['p' . $i] = ['url' => $pageUrl];
            }
            $bodies = JobHttp::multiGet($reqs, 18);
            $boardOk = false;
            $matched = 0;
            foreach ($pages as $i => $pageUrl) {
                $xml = $bodies['p' . $i] ?? null;
                if (!is_string($xml) || $xml === '') {
                    continue;
                }
                $boardOk = true;
                foreach (self::parseSitemapJobUrls($xml, $host) as $link) {
                    $title = self::titleFromJobUrl($link);
                    if ($keywords !== [] && !JobText::matchesAnyKeyword($title . ' ' . $link, $keywords)) {
                        continue;
                    }
                    $city = '';
                    if (preg_match('/(?:am[-_]?standort|standort|in|location)[-_]?([a-z0-9äöüß\-]+)/ui', $link, $m)) {
                        $city = self::titleFromJobUrl($m[1]);
                    }
                    $job = new JobListing(
                        'career',
                        'sitemap:' . hash('sha256', $link),
                        $title,
                        (string) ($board['label'] ?? $host),
                        $city,
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
                        '',
                    );
                    $job->applyUrl = $link;
                    $listings[] = JobText::enrich($job);
                    $matched++;
                    if ($matched >= $limit) {
                        break 2;
                    }
                }
            }
            if ($boardOk) {
                $ok++;
            }
        }

        $notice = $notices !== [] ? implode(' ', $notices) : null;
        if ($keywords === [] && $listings !== [] && App::isDev()) {
            $extra = 'Sitemap boards return a sample without keywords — add role keywords to search deeper.';
            $notice = $notice !== null ? ($notice . ' ' . $extra) : $extra;
        }

        return ['listings' => $listings, 'ok' => $ok, 'notice' => $notice];
    }

    /** @return list<string> */
    private static function discoverJobSitemapPages(string $baseUrl): array
    {
        $origin = preg_replace('#^(https?://[^/]+).*$#i', '$1', $baseUrl) ?: $baseUrl;
        $indexXml = JobHttp::get($origin . '/sitemap.xml', [], 14);
        if ($indexXml === null || $indexXml === '') {
            return [];
        }
        $locs = [];
        if (preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $indexXml, $m)) {
            foreach ($m[1] as $loc) {
                $loc = html_entity_decode(trim($loc), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($loc === '') {
                    continue;
                }
                $locs[] = $loc;
            }
        }
        if ($locs === []) {
            return [];
        }
        // Sitemap index → prefer job/vacancy children; else treat as a single urlset.
        $jobPages = [];
        foreach ($locs as $loc) {
            $pathQuery = (string) (parse_url($loc, PHP_URL_PATH) ?? '') . '?' . (string) (parse_url($loc, PHP_URL_QUERY) ?? '');
            $low = mb_strtolower($pathQuery);
            if (str_contains($low, 'job') || str_contains($low, 'vacanc') || str_contains($low, 'stelle')) {
                $jobPages[] = $loc;
            }
        }
        if ($jobPages !== []) {
            return array_values(array_unique($jobPages));
        }
        // Single urlset (locs are job pages) — return the sitemap itself.
        if (str_contains(mb_strtolower($indexXml), '<urlset')) {
            return [$origin . '/sitemap.xml'];
        }
        return array_slice($locs, 0, 5);
    }

    /** @return list<string> */
    private static function parseSitemapJobUrls(string $xml, string $host): array
    {
        $out = [];
        if (!preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $xml, $m)) {
            return [];
        }
        foreach ($m[1] as $loc) {
            $loc = html_entity_decode(trim($loc), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($loc === '' || !str_contains(mb_strtolower($loc), mb_strtolower($host))) {
                continue;
            }
            $path = (string) (parse_url($loc, PHP_URL_PATH) ?? '');
            // Skip non-job / content pages
            if (preg_match('#/(static|fileadmin|typo3|impressum|datenschutz|cookie|arbeitgeber|einstieg|bewerben\.html|/bewerben/)#i', $path)) {
                continue;
            }
            $isJob = (bool) preg_match('#/stellenanzeige/#i', $path)
                || (bool) preg_match('#^/[a-z0-9äöüß][a-z0-9äöüß\-]{8,}/?$#iu', $path);
            if (!$isJob) {
                continue;
            }
            if (str_ends_with($path, '/bewerben/') || str_ends_with($path, '/bewerben')) {
                $loc = preg_replace('#/bewerben/?$#', '/', $loc) ?: $loc;
            }
            $out[] = $loc;
        }
        return array_values(array_unique($out));
    }

    private static function titleFromJobUrl(string $urlOrSlug): string
    {
        $path = $urlOrSlug;
        if (str_contains($urlOrSlug, '://')) {
            $path = (string) (parse_url($urlOrSlug, PHP_URL_PATH) ?? '');
        }
        $path = trim($path, '/');
        $parts = explode('/', $path);
        $slug = (string) end($parts);
        $slug = preg_replace('/\.html?$/i', '', $slug) ?? $slug;
        $slug = preg_replace('/-\d{4,}$/', '', $slug) ?? $slug; // rossmann trailing id
        $slug = str_replace(['-', '_'], ' ', $slug);
        $slug = preg_replace('/\s+/u', ' ', $slug) ?? $slug;
        $title = mb_convert_case(trim($slug), MB_CASE_TITLE, 'UTF-8');
        $title = preg_replace('/\bM W D\b/iu', '(m/w/d)', $title) ?? $title;
        $title = preg_replace('/\bW M D\b/iu', '(w/m/d)', $title) ?? $title;
        return $title !== '' ? $title : 'Job opening';
    }

    private static function foldDe(string $s): string
    {
        $s = mb_strtolower($s);
        return strtr($s, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'é' => 'e', 'è' => 'e',
        ]);
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
        $cached = JobCache::getListing('career', $externalId)
            ?? JobCache::getListing('university', $externalId);
        if ($cached === null) {
            return null;
        }
        // Meta/LD blurbs are often short — re-fetch until we have a full JD.
        if (trim($cached->description) !== '' && !self::isThinDescription($cached->description)) {
            return $cached;
        }
        $url = trim($cached->applyUrl !== '' ? $cached->applyUrl : $cached->url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return $cached;
        }
        $html = self::fetchJobPageHtml($url);
        if ($html === null || $html === '') {
            return $cached;
        }
        $fresh = self::hydrateFromJobHtml($cached, $html);
        if ($fresh !== null && trim($fresh->description) !== ''
            && (!self::isThinDescription($fresh->description)
                || mb_strlen($fresh->description) > mb_strlen($cached->description))) {
            JobCache::putListing($fresh);
            return $fresh;
        }
        return $cached;
    }

    /** True when text looks like a meta/og blurb, not the full posting. */
    private static function isThinDescription(string $desc): bool
    {
        $desc = trim(JobText::stripHtml($desc));
        $len = mb_strlen($desc);
        if ($len < 400) {
            return true;
        }
        if (preg_match('/\b(Aufgaben|Profil|Einleitung|Perspektiven|Your tasks|Responsibilities|Requirements|What you.?ll do|Das erwarten wir|Das bieten wir|Über uns)\b/u', $desc)) {
            return false;
        }
        return $len < 1200;
    }

    private static function fetchJobPageHtml(string $url): ?string
    {
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        ];
        $html = JobHttp::get($url, $headers, 18);
        if (is_string($html) && $html !== '' && !self::isBlockedHtml($html)) {
            return $html;
        }
        if (!SerpBoardSource::configured()) {
            return null;
        }
        return JobHttp::unlockHtml($url, 22);
    }

    private static function isBlockedHtml(string $html): bool
    {
        $snip = mb_strtolower(mb_substr($html, 0, 2000));
        return str_contains($snip, 'request rejected')
            || str_contains($snip, 'access denied')
            || str_contains($snip, 'just a moment')
            || str_contains($snip, 'cf-browser-verification')
            || str_contains($snip, 'attention required')
            || (str_contains($snip, 'captcha') && mb_strlen($html) < 8000);
    }

    private static function hydrateFromJobHtml(JobListing $cached, string $html): ?JobListing
    {
        $desc = '';
        $title = $cached->title;
        $city = $cached->city;
        $posted = $cached->postedAt;
        $company = $cached->company;

        if (preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $blocks)) {
            foreach ($blocks[1] as $raw) {
                $data = json_decode(html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
                if (!is_array($data)) {
                    continue;
                }
                $nodes = isset($data[0]) ? $data : [$data];
                foreach ($nodes as $node) {
                    if (!is_array($node)) {
                        continue;
                    }
                    $type = $node['@type'] ?? '';
                    $types = is_array($type) ? $type : [$type];
                    $isJob = false;
                    foreach ($types as $t) {
                        if (is_string($t) && stripos($t, 'JobPosting') !== false) {
                            $isJob = true;
                            break;
                        }
                    }
                    if (!$isJob) {
                        continue;
                    }
                    $t = trim((string) ($node['title'] ?? ''));
                    if ($t !== '') {
                        $title = $t;
                    }
                    $d = trim((string) ($node['description'] ?? ''));
                    if ($d !== '') {
                        // Keep HTML if present — strip later only when comparing length.
                        $desc = $d;
                    }
                    $postedLd = trim((string) ($node['datePosted'] ?? ''));
                    if ($postedLd !== '') {
                        $posted = substr($postedLd, 0, 10);
                    }
                    $org = $node['hiringOrganization'] ?? null;
                    if (is_array($org)) {
                        $orgName = trim((string) ($org['name'] ?? ''));
                        if ($orgName !== '') {
                            $company = $orgName;
                        }
                    }
                    $loc = $node['jobLocation'] ?? null;
                    if (is_array($loc)) {
                        $locs = isset($loc[0]) ? $loc : [$loc];
                        foreach ($locs as $one) {
                            if (!is_array($one)) {
                                continue;
                            }
                            $addr = $one['address'] ?? null;
                            if (is_array($addr)) {
                                $locCity = trim((string) ($addr['addressLocality'] ?? ''));
                                if ($locCity !== '') {
                                    $city = $locCity;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Always try the visible page body — LD/og is often only a short teaser (DIS AG, etc.).
        $domDesc = self::extractMainTextFromHtml($html);
        $descPlain = trim(JobText::stripHtml($desc));
        $domPlain = trim(JobText::stripHtml($domDesc));
        if ($domPlain !== '' && (self::isThinDescription($descPlain) || mb_strlen($domPlain) > mb_strlen($descPlain) + 120)) {
            $desc = $domDesc;
        } elseif ($desc === '' && $domDesc !== '') {
            $desc = $domDesc;
        } elseif ($desc === '') {
            if (preg_match('#<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $m)
                || preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:description["\']#i', $html, $m)
                || preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $m)) {
                $desc = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        if ($desc === '') {
            return null;
        }

        $job = new JobListing(
            $cached->source,
            $cached->externalId,
            $title !== '' ? $title : $cached->title,
            $company !== '' ? $company : $cached->company,
            $city,
            $cached->bundesland,
            $cached->country !== '' ? $cached->country : 'Germany',
            $cached->workMode,
            $cached->employment,
            $cached->offerType,
            $cached->seniorityTags,
            $cached->languages,
            $cached->salaryText,
            $posted,
            $cached->url !== '' ? $cached->url : $cached->applyUrl,
            $desc,
        );
        $job->applyUrl = $cached->applyUrl !== '' ? $cached->applyUrl : $cached->url;
        $job->fingerprint = $cached->fingerprint;
        return JobText::enrich($job);
    }

    private static function extractMainTextFromHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|svg)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;

        // Prefer structured German career sections (DIS AG / similar ATS skins).
        $sectioned = self::extractLabeledJobSections($html);
        if ($sectioned !== '') {
            return $sectioned;
        }

        $html = preg_replace('#<(nav|footer|header)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $candidates = [];
        foreach ([
            '#<(?:div|section|article)[^>]*(?:class|id)=["\'][^"\']*(?:job[-_]?description|description|stellenbeschreibung|job[-_]?detail|job[-_]?content|vacancy|advertisement|jobad)[^"\']*["\'][^>]*>(.*?)</(?:div|section|article)>#is',
            '#<main[^>]*>(.*?)</main>#is',
            '#<article[^>]*>(.*?)</article>#is',
        ] as $re) {
            if (preg_match_all($re, $html, $m)) {
                foreach ($m[1] as $chunk) {
                    $text = trim(JobText::stripHtml($chunk));
                    if (mb_strlen($text) >= 120) {
                        $candidates[] = self::normalizeJobBodyHtml($chunk);
                    }
                }
            }
        }
        if ($candidates === []) {
            // Fallback: whole body text if it contains clear JD markers.
            $plain = trim(JobText::stripHtml($html));
            if (preg_match('/\b(Aufgaben|Einleitung|Profil)\b/u', $plain) && mb_strlen($plain) > 400) {
                return mb_substr($plain, 0, 25000);
            }
            return '';
        }
        usort($candidates, static function (string $a, string $b): int {
            return mb_strlen(JobText::stripHtml($b)) <=> mb_strlen(JobText::stripHtml($a));
        });
        return mb_substr($candidates[0], 0, 25000);
    }

    /**
     * Pull Einleitung / Aufgaben / Perspektiven / Profil (and EN equivalents) as one body.
     */
    private static function extractLabeledJobSections(string $html): string
    {
        $labels = 'Einleitung|Aufgaben|Perspektiven|Profil|Interessiert\?|Kontakt|Your tasks|Responsibilities|Requirements|About (?:us|the role)|What (?:we offer|you.?ll do)|Das bieten wir|Das erwarten wir|Über uns';
        // Headings or strong labels followed by content until the next label / apply CTA.
        if (!preg_match_all(
            '#<(?:h[1-6]|p|div|strong|span)[^>]*>\s*(' . $labels . ')\s*:?\s*</(?:h[1-6]|p|div|strong|span)>(.*?)(?=<(?:h[1-6]|p|div|strong|span)[^>]*>\s*(?:' . $labels . '|Für Job bewerben|Job speichern|Teile diesen Job)\b|$)#is',
            $html,
            $m,
            PREG_SET_ORDER
        )) {
            // Plain-text oriented split when headings aren't wrapped cleanly.
            $plain = JobText::stripHtml($html);
            if (!preg_match('/\bEinleitung\b/u', $plain) || !preg_match('/\bAufgaben\b/u', $plain)) {
                return '';
            }
            if (!preg_match(
                '/(Einleitung\b.*?)(?:Ref\b|Für Job bewerben|Job speichern|$)/is',
                $plain,
                $pm
            )) {
                return '';
            }
            $chunk = trim($pm[1]);
            return mb_strlen($chunk) >= 400 ? mb_substr($chunk, 0, 25000) : '';
        }

        $parts = [];
        foreach ($m as $row) {
            $label = trim($row[1]);
            $body = trim(JobText::stripHtml($row[2]));
            // Skip share / apply chrome trapped in a section.
            if ($body === '' || preg_match('/^(Auf LinkedIn teilen|Link kopieren)/u', $body)) {
                continue;
            }
            if (preg_match('/^(Interessiert\?|Kontakt)$/iu', $label) && mb_strlen($body) < 40) {
                continue;
            }
            $parts[] = $label . "\n" . $body;
        }
        $joined = trim(implode("\n\n", $parts));
        return mb_strlen($joined) >= 300 ? mb_substr($joined, 0, 25000) : '';
    }

    private static function normalizeJobBodyHtml(string $chunk): string
    {
        $chunk = preg_replace('#<(script|style|nav|footer|button)[^>]*>.*?</\1>#is', ' ', $chunk) ?? $chunk;
        // Drop share widgets.
        $chunk = preg_replace('#<(?:div|ul)[^>]*(?:share|social|teilen)[^>]*>.*?</(?:div|ul)>#is', ' ', $chunk) ?? $chunk;
        $text = trim(JobText::stripHtml($chunk));
        // Prefer plain text with preserved paragraphs for our display pipeline.
        return $text;
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
            $country = self::countryFromLocation($office);
            if ($country === '' && JobText::looksLikeGermany($office, '', '', '')) {
                $country = 'Germany';
            }
            $job = new JobListing(
                'career',
                'pe:' . $slug . ':' . $id,
                $title,
                $company,
                self::cityFromLocation($office) !== '' ? self::cityFromLocation($office) : $office,
                '',
                $country,
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
            $city = self::cityFromLocation($loc);
            $country = self::countryFromLocation($loc);
            if ($country === '' && !JobText::isForeignPrimaryLocation($city, '', '')) {
                if (JobText::looksLikeGermany($city, '', '', '')) {
                    $country = 'Germany';
                }
            }
            $job = new JobListing(
                'career',
                'sr:' . $slug . ':' . $id,
                $title,
                $company,
                $city,
                '',
                $country,
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
        if (JobText::isForeignPrimaryLocation(self::cityFromLocation($loc), '', '')
            || preg_match('/\b(spain|españa)\b/iu', $loc)) {
            if (preg_match('/\b(spain|españa)\b/iu', $loc)) {
                return 'Spain';
            }
            // Known foreign city without country word — still mark non-Germany
            $city = self::cityFromLocation($loc);
            if (preg_match('/\b(madrid|barcelona|valencia|seville|sevilla|malaga)\b/iu', $city)) {
                return 'Spain';
            }
            if (preg_match('/\b(paris|lyon|marseille)\b/iu', $city)) {
                return 'France';
            }
            if (preg_match('/\b(london|manchester)\b/iu', $city)) {
                return 'United Kingdom';
            }
            if (preg_match('/\b(amsterdam|rotterdam)\b/iu', $city)) {
                return 'Netherlands';
            }
            if (preg_match('/\b(vienna|wien)\b/iu', $city)) {
                return 'Austria';
            }
            if (preg_match('/\b(zurich|zürich|geneva)\b/iu', $city)) {
                return 'Switzerland';
            }
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
