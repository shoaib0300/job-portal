<?php

declare(strict_types=1);

namespace KaamFit\Jobs\Sources;

use App;
use DOMDocument;
use DOMElement;
use DOMXPath;
use KaamFit\Jobs\JobCache;
use KaamFit\Jobs\JobHttp;
use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobStore;
use KaamFit\Jobs\JobText;

/**
 * Adzuna Germany (https://www.adzuna.de/search).
 *
 * Primary: public jobs API (ADZUNA_APP_ID + ADZUNA_APP_KEY).
 * Fallback: Bright Data Web Unlocker on adzuna.de/search (CloudFront blocks datacenter IPs).
 */
final class AdzunaSource
{
    private const API = 'https://api.adzuna.com/v1/api/jobs/de/search/';
    private const BASE = 'https://www.adzuna.de';
    private const API_PAGES = 4;
    private const HTML_PAGES = 3;
    private const PER_PAGE = 50;

    /** Adzuna area labels (EN) → Bundesland. */
    private const REGION_MAP = [
        'baden-wuerttemberg' => 'Baden-Württemberg',
        'baden-württemberg' => 'Baden-Württemberg',
        'bavaria' => 'Bayern',
        'bayern' => 'Bayern',
        'berlin' => 'Berlin',
        'brandenburg' => 'Brandenburg',
        'bremen' => 'Bremen',
        'hamburg' => 'Hamburg',
        'hesse' => 'Hessen',
        'hessen' => 'Hessen',
        'mecklenburg-western pomerania' => 'Mecklenburg-Vorpommern',
        'mecklenburg-vorpommern' => 'Mecklenburg-Vorpommern',
        'lower saxony' => 'Niedersachsen',
        'niedersachsen' => 'Niedersachsen',
        'north rhine-westphalia' => 'Nordrhein-Westfalen',
        'nordrhein-westfalen' => 'Nordrhein-Westfalen',
        'nrw' => 'Nordrhein-Westfalen',
        'rhineland-palatinate' => 'Rheinland-Pfalz',
        'rheinland-pfalz' => 'Rheinland-Pfalz',
        'saarland' => 'Saarland',
        'saxony' => 'Sachsen',
        'sachsen' => 'Sachsen',
        'saxony-anhalt' => 'Sachsen-Anhalt',
        'sachsen-anhalt' => 'Sachsen-Anhalt',
        'schleswig-holstein' => 'Schleswig-Holstein',
        'thuringia' => 'Thüringen',
        'thüringen' => 'Thüringen',
    ];

    public static function apiConfigured(): bool
    {
        return self::appId() !== '' && self::appKey() !== '';
    }

    public static function appId(): string
    {
        return trim((string) (getenv('ADZUNA_APP_ID') ?: ''));
    }

    public static function appKey(): string
    {
        return trim((string) (getenv('ADZUNA_APP_KEY') ?: ''));
    }

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        if (self::apiConfigured()) {
            return self::searchApi($query);
        }
        if (SerpBoardSource::configured()) {
            return self::searchHtml($query);
        }
        if (!App::isDev()) {
            return ['listings' => [], 'notices' => []];
        }
        return [
            'listings' => [],
            'notices' => [
                'Adzuna needs ADZUNA_APP_ID and ADZUNA_APP_KEY in .env (free at developer.adzuna.com). Bright Data can scrape adzuna.de as a fallback.',
            ],
        ];
    }

    public static function details(string $externalId): ?JobListing
    {
        $cached = JobCache::getListing('adzuna', $externalId);
        if ($cached === null) {
            $cached = JobStore::get('adzuna', $externalId);
        }
        if ($cached !== null) {
            $oldUrl = $cached->url;
            $cached = self::withCanonicalUrl($cached);
            if ($cached->url !== $oldUrl) {
                JobStore::upsertMany([$cached]);
            }
        }
        if ($cached !== null && !self::isThinDescription($cached->description)) {
            return $cached;
        }

        $html = null;
        foreach (self::detailFetchUrls($externalId, $cached) as $url) {
            $html = JobHttp::unlockHtml($url, 22);
            if (!self::looksLikeDetail($html)) {
                $html = JobHttp::get($url, self::htmlHeaders(), 16);
            }
            if (self::looksLikeDetail($html)) {
                break;
            }
            $html = null;
        }
        if (!is_string($html) || $html === '') {
            return $cached;
        }
        $fresh = self::parseDetail($html, $externalId, $cached);
        if ($fresh !== null) {
            JobCache::putListing($fresh);
            JobStore::upsertMany([$fresh]);
        }
        return $fresh ?? $cached;
    }

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    private static function searchApi(JobQuery $query): array
    {
        $terms = $query->keywords !== [] ? $query->keywords : [trim($query->searchWas())];
        if ($terms === [] || $terms === ['']) {
            $terms = [''];
        }
        $terms = array_slice($terms, 0, 4);

        $requests = [];
        foreach ($terms as $i => $term) {
            $what = trim($term . ' ' . $query->extraKeywords());
            for ($page = 1; $page <= self::API_PAGES; $page++) {
                $requests[$i . ':' . $page] = [
                    'url' => self::apiUrl($query, $what, $page),
                    'headers' => ['Accept: application/json'],
                ];
            }
        }

        $bodies = JobHttp::multiGet($requests, 16);
        $listings = [];
        $ok = 0;
        foreach ($bodies as $raw) {
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
                continue;
            }
            $ok++;
            foreach ($data['results'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $job = self::fromApiRow($row);
                if ($job !== null) {
                    $listings[] = $job;
                }
            }
        }

        if ($ok === 0) {
            return [
                'listings' => [],
                'notices' => ['Adzuna API did not respond.'],
            ];
        }

        return ['listings' => self::uniqueById($listings), 'notices' => []];
    }

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    private static function searchHtml(JobQuery $query): array
    {
        $what = trim($query->searchWas());
        $where = $query->whereText();
        $listings = [];
        $ok = 0;
        for ($page = 1; $page <= self::HTML_PAGES; $page++) {
            $params = ['q' => $what];
            if ($where !== '') {
                $params['w'] = $where;
            }
            if ($page > 1) {
                $params['p'] = (string) $page;
            }
            $url = self::BASE . '/search?' . http_build_query($params);
            $html = JobHttp::unlockHtml($url, 28);
            if (!is_string($html) || $html === '' || str_contains($html, '403 ERROR')) {
                continue;
            }
            $ok++;
            foreach (self::parseList($html) as $job) {
                $listings[] = $job;
            }
        }

        if ($ok === 0) {
            return [
                'listings' => [],
                'notices' => ['Adzuna search pages did not respond (CloudFront). Set ADZUNA_APP_ID / ADZUNA_APP_KEY.'],
            ];
        }

        return ['listings' => self::uniqueById($listings), 'notices' => []];
    }

    private static function apiUrl(JobQuery $query, string $what, int $page): string
    {
        $params = [
            'app_id' => self::appId(),
            'app_key' => self::appKey(),
            'results_per_page' => self::PER_PAGE,
            'max_days_old' => $query->effectivePostedDays(),
            'sort_by' => 'date',
            'content-type' => 'application/json',
        ];
        if ($what !== '') {
            $params['what'] = $what;
        }
        $where = $query->whereText();
        if ($where !== '') {
            $params['where'] = $where;
        }
        if ($query->employment === 'fulltime') {
            $params['full_time'] = 1;
        } elseif ($query->employment === 'parttime' || $query->employment === 'mini') {
            $params['part_time'] = 1;
        }
        if ($query->hasSalary) {
            $params['salary_include_unknown'] = 0;
        }

        return self::API . max(1, $page) . '?' . http_build_query($params);
    }

    /** @param array<string, mixed> $row */
    private static function fromApiRow(array $row): ?JobListing
    {
        $id = trim((string) ($row['id'] ?? ''));
        $title = self::plain((string) ($row['title'] ?? ''));
        if ($id === '' || $title === '') {
            return null;
        }
        $company = '';
        if (isset($row['company']) && is_array($row['company'])) {
            $company = self::plain((string) ($row['company']['display_name'] ?? ''));
        }
        $loc = self::parseLocation(is_array($row['location'] ?? null) ? $row['location'] : []);
        $desc = self::plain((string) ($row['description'] ?? ''));
        $posted = self::postedDate((string) ($row['created'] ?? ''));
        if ($posted === null) {
            $posted = date('Y-m-d');
        }
        $redirect = trim((string) ($row['redirect_url'] ?? ''));
        $url = self::BASE . '/details/' . rawurlencode($id);
        $employment = self::employmentFromApi($row);
        $salary = self::salaryText($row);

        $job = new JobListing(
            'adzuna',
            $id,
            $title,
            $company !== '' ? $company : 'Employer',
            self::cleanCity($loc['city']),
            $loc['bundesland'],
            $loc['country'] !== '' ? $loc['country'] : 'Germany',
            'unknown',
            $employment,
            'job',
            [],
            [],
            $salary,
            $posted,
            $url,
            $desc,
            '',
            $redirect,
        );
        return JobText::enrich($job);
    }

    /** @return list<JobListing> */
    private static function parseList(string $html): array
    {
        $dom = self::dom($html);
        if ($dom === null) {
            return [];
        }
        $xp = new DOMXPath($dom);
        $out = [];
        $seen = [];

        $cards = $xp->query('//article[@data-aid]');
        if ($cards !== false && $cards->length > 0) {
            foreach ($cards as $card) {
                if (!$card instanceof DOMElement) {
                    continue;
                }
                $id = trim($card->getAttribute('data-aid'));
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $title = self::nearbyText($xp, $card, './/h2');
                if ($title === '') {
                    $title = self::nearbyText($xp, $card, './/a[contains(@href,"/land/ad/") or contains(@href,"/details/")]');
                }
                if ($title === '' || mb_strlen($title) < 8) {
                    continue;
                }
                $company = '';
                $companyNodes = $xp->query('.//*[@data-company-name]', $card);
                if ($companyNodes !== false && $companyNodes->length > 0 && $companyNodes->item(0) instanceof DOMElement) {
                    $company = trim($companyNodes->item(0)->getAttribute('data-company-name'));
                }
                if ($company === '') {
                    $company = self::firstClassText($xp, $card, 'ui-company');
                }
                $location = self::firstClassText($xp, $card, 'ui-location');
                $snippet = self::firstClassText($xp, $card, 'max-snippet-height');
                if ($snippet === '') {
                    $snippet = self::firstClassText($xp, $card, 'ui-description');
                }
                $posted = JobText::parsePostedDate($snippet);
                if ($posted === null) {
                    $posted = date('Y-m-d');
                }
                $cityParts = self::splitLocation($location);
                $job = new JobListing(
                    'adzuna',
                    $id,
                    self::plain($title),
                    $company !== '' ? self::plain($company) : 'Employer',
                    $cityParts['city'],
                    $cityParts['bundesland'],
                    'Germany',
                    'unknown',
                    'unknown',
                    'job',
                    [],
                    [],
                    '',
                    $posted,
                    self::detailUrl($id),
                    self::plain($snippet),
                );
                $seen[$id] = true;
                $out[] = JobText::enrich($job);
            }
            return $out;
        }

        foreach ($xp->query('//a[contains(@href,"/details/") or contains(@href,"/land/ad/")]') as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $href = trim($a->getAttribute('href'));
            $id = self::idFromHref($href);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $title = trim(preg_replace('/\s+/u', ' ', $a->textContent ?? '') ?? '');
            if ($title === '' || mb_strlen($title) < 8) {
                $title = self::nearbyText($xp, $a, './/h2|.//h3');
            }
            if ($title === '' || mb_strlen($title) < 8) {
                continue;
            }
            if (preg_match('/^(deutschland|germany|[\d\s,]+)$/iu', $title)) {
                continue;
            }
            $parent = $a->parentNode;
            $company = '';
            $location = '';
            $snippet = '';
            if ($parent instanceof DOMElement) {
                $company = self::firstClassText($xp, $parent, 'company');
                $location = self::firstClassText($xp, $parent, 'location');
                $snippet = self::firstClassText($xp, $parent, 'description');
            }
            $cityParts = self::splitLocation($location);
            $posted = JobText::parsePostedDate($snippet);
            if ($posted === null) {
                $posted = date('Y-m-d');
            }
            $url = self::detailUrl($id);
            $job = new JobListing(
                'adzuna',
                $id,
                self::plain($title),
                $company !== '' ? self::plain($company) : 'Employer',
                $cityParts['city'],
                $cityParts['bundesland'],
                'Germany',
                'unknown',
                'unknown',
                'job',
                [],
                [],
                '',
                $posted,
                $url,
                self::plain($snippet),
            );
            $seen[$id] = true;
            $out[] = JobText::enrich($job);
        }
        return $out;
    }

    private static function titleCasePlace(string $city): string
    {
        $city = trim($city);
        if ($city === '') {
            return '';
        }
        if ($city === mb_strtoupper($city) && preg_match('/[A-ZÄÖÜ]/u', $city)) {
            return mb_convert_case(mb_strtolower($city), MB_CASE_TITLE, 'UTF-8');
        }
        return $city;
    }

    private static function parseDetail(string $html, string $id, ?JobListing $cached): ?JobListing
    {
        $title = $cached->title ?? '';
        $company = $cached->company ?? '';
        $city = $cached->city ?? '';
        $bundesland = $cached->bundesland ?? '';
        $posted = $cached->postedAt ?? null;
        $desc = $cached->description ?? '';
        $apply = $cached->applyUrl ?? '';
        $employment = $cached->employment ?? 'unknown';
        $salary = $cached->salaryText ?? '';
        $url = self::detailUrl($id);

        $ld = self::jobPostingLd($html);
        if (is_array($ld)) {
            $t = self::plain((string) ($ld['title'] ?? ''));
            if ($t !== '') {
                $title = $t;
            }
            $postedLd = trim((string) ($ld['datePosted'] ?? ''));
            if ($postedLd !== '') {
                $posted = substr($postedLd, 0, 10);
            }
            $org = $ld['hiringOrganization'] ?? [];
            if (is_array($org)) {
                $orgName = self::plain((string) ($org['name'] ?? ''));
                if ($orgName !== '' && ($company === '' || $company === 'Employer')) {
                    $company = $orgName;
                }
            }
            $loc = $ld['jobLocation'] ?? [];
            if (is_array($loc) && isset($loc['address']) && is_array($loc['address'])) {
                $addr = $loc['address'];
                $locCity = self::plain((string) ($addr['addressLocality'] ?? ''));
                $region = self::mapRegion((string) ($addr['addressRegion'] ?? ''));
                if ($locCity !== '') {
                    $city = $locCity;
                }
                if ($region !== '') {
                    $bundesland = $region;
                }
            }
            $d = (string) ($ld['description'] ?? '');
            if ($d !== '' && mb_strlen(strip_tags($d)) > mb_strlen(strip_tags($desc))) {
                $desc = $d;
            }
        }

        $dom = self::dom($html);
        if ($dom !== null) {
            $xp = new DOMXPath($dom);
            if ($title === '') {
                $h1 = $xp->query('//h1');
                if ($h1 !== false && $h1->length > 0) {
                    $title = trim(preg_replace('/\s+/u', ' ', $h1->item(0)?->textContent ?? '') ?? '');
                }
            }
            $fromPageCompany = '';
            $companyNodes = $xp->query('//*[@data-company-name]');
            if ($companyNodes !== false && $companyNodes->length > 0 && $companyNodes->item(0) instanceof DOMElement) {
                $fromPageCompany = trim($companyNodes->item(0)->getAttribute('data-company-name'));
            }
            if ($fromPageCompany === '' && $dom->documentElement instanceof DOMElement) {
                $fromPageCompany = self::firstClassText($xp, $dom->documentElement, 'ui-company');
            }
            if ($fromPageCompany !== '' && ($company === '' || $company === 'Employer'
                || ($company === mb_strtoupper($company) && preg_match('/[A-ZÄÖÜ]/u', $company)))) {
                $company = $fromPageCompany;
            }
            $bodyHtml = self::firstClassHtml($dom, $xp, 'adp-body');
            if ($bodyHtml === '') {
                $bodyHtml = self::firstClassHtml($dom, $xp, 'ui-adp-content');
            }
            if ($bodyHtml !== '' && mb_strlen(strip_tags($bodyHtml)) > mb_strlen(strip_tags($desc))) {
                $desc = $bodyHtml;
            }
            $applyFromPage = self::applyHrefFromDom($xp);
            if ($applyFromPage !== '' && !str_contains($applyFromPage, 'adzuna.de')) {
                $apply = $applyFromPage;
            }
            if ($posted === null || $posted === '') {
                $timeNodes = $xp->query('//time[@datetime]');
                if ($timeNodes !== false && $timeNodes->item(0) instanceof DOMElement) {
                    $dt = trim($timeNodes->item(0)->getAttribute('datetime'));
                    if ($dt !== '') {
                        $posted = self::postedDate($dt);
                    }
                }
            }
            if (($posted === null || $posted === '') && preg_match('/\b(heute|gestern|vor\s+\d+\s+Tag)/iu', $html)) {
                $posted = JobText::parsePostedDate($html) ?? $posted;
            }
        }

        $hay = mb_strtolower(strip_tags($desc));
        if ($employment === 'unknown') {
            if (str_contains($hay, 'vollzeit') || str_contains($hay, 'full-time') || str_contains($hay, 'full time')) {
                $employment = 'fulltime';
            } elseif (str_contains($hay, 'teilzeit') || str_contains($hay, 'part-time') || str_contains($hay, 'part time')) {
                $employment = 'parttime';
            }
        }

        if ($title === '') {
            return $cached;
        }
        $job = new JobListing(
            'adzuna',
            $id,
            $title,
            $company !== '' ? $company : 'Employer',
            $city,
            $bundesland,
            'Germany',
            'unknown',
            $employment,
            'job',
            [],
            [],
            $salary,
            $posted,
            $url,
            $desc,
            '',
            $apply,
        );
        return JobText::enrich($job);
    }

    /**
     * @param array<string, mixed> $location
     * @return array{city:string,bundesland:string,country:string}
     */
    private static function parseLocation(array $location): array
    {
        $display = self::plain((string) ($location['display_name'] ?? ''));
        $city = '';
        $bundesland = '';
        $country = 'Germany';
        if ($display !== '') {
            $parts = array_map('trim', explode(',', $display));
            $city = $parts[0] ?? '';
            if (isset($parts[1])) {
                $bundesland = self::mapRegion($parts[1]);
            }
        }
        $area = $location['area'] ?? [];
        if (is_array($area)) {
            $labels = array_values(array_filter(array_map(
                static fn($v): string => self::plain((string) $v),
                $area
            )));
            foreach ($labels as $label) {
                $low = mb_strtolower($label);
                if (in_array($low, ['germany', 'deutschland', 'de', 'federal republic of germany'], true)) {
                    $country = 'Germany';
                    continue;
                }
                $mapped = self::mapRegion($label);
                if ($mapped !== '' && $bundesland === '') {
                    $bundesland = $mapped;
                    continue;
                }
                if ($city === '' && $mapped === '') {
                    $city = $label;
                }
            }
        }
        return [
            'city' => self::cleanCity($city),
            'bundesland' => $bundesland,
            'country' => $country,
        ];
    }

    /** @return array{city:string,bundesland:string} */
    private static function splitLocation(string $location): array
    {
        $location = trim(preg_replace('/\+\s*\d+.*$/u', '', $location) ?? $location);
        $bundesland = '';
        $city = $location;
        if (str_contains($location, ',')) {
            [$city, $rest] = array_map('trim', explode(',', $location, 2));
            $bundesland = self::mapRegion($rest);
        }
        return ['city' => self::cleanCity($city), 'bundesland' => $bundesland];
    }

    private static function cleanCity(string $city): string
    {
        $city = self::plain($city);
        $city = preg_replace('/,\s*(germany|deutschland)\s*$/iu', '', $city) ?? $city;
        $city = preg_replace('/,?\s*\d{4,5}\s*$/u', '', $city) ?? $city;
        $city = trim($city, " \t,");
        return self::titleCasePlace($city);
    }

    private static function mapRegion(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $key = mb_strtolower(strtr($raw, ['ü' => 'ü', 'ä' => 'ä', 'ö' => 'ö']));
        if (isset(self::REGION_MAP[$key])) {
            return self::REGION_MAP[$key];
        }
        foreach (JobQuery::BUNDESLAENDER as $bl) {
            if (mb_stripos($bl, $raw) !== false || mb_stripos($raw, $bl) !== false) {
                return $bl;
            }
        }
        return '';
    }

    /** @param array<string, mixed> $row */
    private static function employmentFromApi(array $row): string
    {
        $time = mb_strtolower(trim((string) ($row['contract_time'] ?? '')));
        if ($time === 'full_time' || $time === 'full-time') {
            return 'fulltime';
        }
        if ($time === 'part_time' || $time === 'part-time') {
            return 'parttime';
        }
        return 'unknown';
    }

    /** @param array<string, mixed> $row */
    private static function salaryText(array $row): string
    {
        $min = isset($row['salary_min']) ? (float) $row['salary_min'] : 0.0;
        $max = isset($row['salary_max']) ? (float) $row['salary_max'] : 0.0;
        if ($min <= 0 && $max <= 0) {
            return '';
        }
        $fmt = static fn(float $n): string => number_format($n, 0, ',', '.');
        if ($min > 0 && $max > 0 && abs($max - $min) > 1) {
            return '€' . $fmt($min) . '–' . $fmt($max);
        }
        $n = $max > 0 ? $max : $min;
        return '€' . $fmt($n);
    }

    private static function postedDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return JobText::parsePostedDate($raw);
        }
        return date('Y-m-d', $ts);
    }

    private static function idFromHref(string $href): string
    {
        if (preg_match('~/details/(\d+)~', $href, $m) || preg_match('~/land/ad/(\d+)~', $href, $m)) {
            return $m[1];
        }
        return '';
    }

    /** @param list<JobListing> $listings @return list<JobListing> */
    private static function uniqueById(array $listings): array
    {
        $seen = [];
        $out = [];
        foreach ($listings as $job) {
            if (isset($seen[$job->externalId])) {
                continue;
            }
            $seen[$job->externalId] = true;
            $out[] = $job;
        }
        return $out;
    }

    private static function plain(string $s): string
    {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace("\xc2\xa0", ' ', $s);
        return trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? $s);
    }

    private static function nearbyText(DOMXPath $xp, DOMElement $ctx, string $query): string
    {
        $n = $xp->query($query, $ctx);
        if ($n === false || $n->length === 0) {
            return '';
        }
        return trim(preg_replace('/\s+/u', ' ', $n->item(0)?->textContent ?? '') ?? '');
    }

    private static function firstClassText(DOMXPath $xp, DOMElement $ctx, string $class): string
    {
        $n = $xp->query('.//*[contains(@class,"' . $class . '")]', $ctx);
        if ($n === false || $n->length === 0) {
            return '';
        }
        return trim(preg_replace('/\s+/u', ' ', $n->item(0)?->textContent ?? '') ?? '');
    }

    /** @return array<string, mixed>|null */
    private static function jobPostingLd(string $html): ?array
    {
        if (!preg_match_all('~<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>~is', $html, $all)) {
            return null;
        }
        foreach ($all[1] as $raw) {
            $data = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5), true);
            if (!is_array($data)) {
                continue;
            }
            if (($data['@type'] ?? '') === 'JobPosting') {
                return $data;
            }
            if (isset($data['@graph']) && is_array($data['@graph'])) {
                foreach ($data['@graph'] as $node) {
                    if (is_array($node) && ($node['@type'] ?? '') === 'JobPosting') {
                        return $node;
                    }
                }
            }
        }
        return null;
    }

    private static function dom(string $html): ?DOMDocument
    {
        $dom = new DOMDocument();
        $ok = @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return $ok ? $dom : null;
    }

    private static function detailUrl(string $id): string
    {
        return self::BASE . '/details/' . rawurlencode($id);
    }

    private static function withCanonicalUrl(JobListing $job): JobListing
    {
        $canonical = self::detailUrl($job->externalId);
        if (trim($job->url) === $canonical) {
            return $job;
        }
        $job->url = $canonical;
        return $job;
    }

    /**
     * @return list<string>
     */
    private static function detailFetchUrls(string $id, ?JobListing $cached): array
    {
        $urls = [self::detailUrl($id)];
        $cachedUrl = trim((string) ($cached->url ?? ''));
        if ($cachedUrl !== ''
            && !in_array($cachedUrl, $urls, true)
            && !preg_match('~/land/ad/\d+/?$~', $cachedUrl)) {
            $urls[] = $cachedUrl;
        }
        return $urls;
    }

    private static function looksLikeDetail(?string $html): bool
    {
        if (!is_string($html) || $html === '' || str_contains($html, '403 ERROR')) {
            return false;
        }
        if (str_contains($html, 'Seite nicht gefunden') || str_contains($html, 'Page not found')) {
            return false;
        }
        return str_contains($html, 'adp-body')
            || str_contains($html, 'ui-adp-content')
            || str_contains($html, '"@type":"JobPosting"')
            || str_contains($html, '"@type" : "JobPosting"');
    }

    /** Search snippets are truncated; full JD lives on /details/{id}. */
    private static function isThinDescription(string $desc): bool
    {
        $plain = trim(JobText::stripHtml($desc));
        $len = mb_strlen($plain);
        if ($len < 400) {
            return true;
        }
        if (preg_match('/(\.{3}|…)\s*$/u', $plain)) {
            return true;
        }
        if (preg_match('/\b(Aufgaben|Profil|Wir bieten|Your tasks|Responsibilities|Requirements)\b/u', $plain)) {
            return false;
        }
        return $len < 800;
    }

    private static function firstClassHtml(DOMDocument $dom, DOMXPath $xp, string $class): string
    {
        $nodes = $xp->query('//*[contains(@class,"' . $class . '")]');
        if ($nodes === false || $nodes->length === 0 || !$nodes->item(0) instanceof DOMElement) {
            return '';
        }
        $inner = '';
        foreach ($nodes->item(0)->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }
        $inner = preg_replace('/\sdata-cursor-ref="[^"]*"/', '', $inner) ?? $inner;
        $inner = trim($inner);
        return mb_strlen(strip_tags($inner)) >= 80 ? $inner : '';
    }

    private static function applyHrefFromDom(DOMXPath $xp): string
    {
        foreach ($xp->query('//a[contains(translate(., "BEWERBENAPPLY", "bewerbenapply"), "bewerben") or contains(translate(., "APPLY", "apply"), "apply")]') as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $href = trim($a->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }
            if (str_starts_with($href, '/')) {
                $href = self::BASE . $href;
            }
            if (preg_match('~^https?://~i', $href)) {
                return $href;
            }
        }
        return '';
    }

    /** @return list<string> */
    private static function htmlHeaders(): array
    {
        return [
            'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ];
    }
}
