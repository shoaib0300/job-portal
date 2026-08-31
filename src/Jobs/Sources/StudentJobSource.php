<?php

declare(strict_types=1);

namespace KaamFit\Jobs\Sources;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use KaamFit\Jobs\JobCache;
use KaamFit\Jobs\JobHttp;
use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobStore;
use KaamFit\Jobs\JobText;

/**
 * StudentJob.de — student / Werkstudent / Praktikum listings.
 *
 * Discovery via public sitemap (~7k jobs in last 14 days); detail pages hydrated on open.
 */
final class StudentJobSource
{
    private const BASE = 'https://www.studentjob.de';
    private const SITEMAP = 'https://www.studentjob.de/sitemap/job_openings.xml';
    private const MAX_PER_SEARCH = 7500;
    private const CITY_HTML_PAGES = 12;

    /** @var list<array{url:string,slug:string,id:string,lastmod:string,lastmod_ts:int}>|null */
    private static ?array $sitemapCache = null;

    /** @var array<string, string> display city → URL slug */
    private const CITY_SLUGS = [
        'berlin' => 'berlin',
        'münchen' => 'munchen',
        'munich' => 'munchen',
        'hamburg' => 'hamburg',
        'köln' => 'koln',
        'koln' => 'koln',
        'cologne' => 'koln',
        'frankfurt' => 'frankfurt-am-main',
        'frankfurt am main' => 'frankfurt-am-main',
        'stuttgart' => 'stuttgart',
        'dortmund' => 'dortmund',
        'nürnberg' => 'nurnberg',
        'nurnberg' => 'nurnberg',
        'düsseldorf' => 'dusseldorf',
        'dusseldorf' => 'dusseldorf',
        'hannover' => 'hannover',
        'leipzig' => 'leipzig',
        'bremen' => 'bremen',
        'dresden' => 'dresden',
    ];

    /**
     * @return array{listings: list<JobListing>, notice: ?string}
     */
    public static function search(JobQuery $query): array
    {
        $entries = self::fetchSitemapEntries();
        if ($entries === []) {
            return [
                'listings' => [],
                'notice' => 'StudentJob.de sitemap did not respond (public URL: /sitemap/job_openings.xml).',
            ];
        }

        $days = $query->effectivePostedDays();
        $minTs = time() - ($days * 86400);
        $keywords = $query->keywords;
        $city = trim($query->city);
        $citySlug = self::citySlug($city);

        $out = [];
        foreach ($entries as $entry) {
            if ($entry['lastmod_ts'] > 0 && $entry['lastmod_ts'] < $minTs) {
                continue;
            }
            if ($keywords !== [] && !self::slugMatchesKeywords($entry['slug'], $keywords)) {
                continue;
            }
            if ($citySlug !== '' && !self::slugMatchesCity($entry['slug'], $city, $citySlug)) {
                continue;
            }
            $job = self::listingFromEntry($entry);
            if ($job !== null) {
                $out[] = $job;
            }
            if (count($out) >= self::MAX_PER_SEARCH) {
                break;
            }
        }

        if ($citySlug !== '' && count($out) < 30) {
            $out = self::dedupeListings(array_merge($out, self::searchHtmlCity($citySlug)));
        }

        if ($out === []) {
            return [
                'listings' => [],
                'notice' => 'StudentJob.de had no matches in the last ' . $days . ' days for this query.',
            ];
        }

        return ['listings' => $out, 'notice' => null];
    }

    public static function details(string $externalId): ?JobListing
    {
        $cached = JobCache::getListing('studentjob', $externalId);
        if ($cached === null) {
            $cached = JobStore::get('studentjob', $externalId);
        }
        if ($cached !== null && mb_strlen(trim(strip_tags($cached->description))) >= 120) {
            return $cached;
        }

        $url = $cached !== null && trim($cached->url) !== ''
            ? trim($cached->url)
            : self::BASE . '/stellenangebote/' . rawurlencode($externalId);

        $html = JobHttp::get($url, self::htmlHeaders(), 18);
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
     * @return list<array{url:string,slug:string,id:string,lastmod:string,lastmod_ts:int}>
     */
    private static function fetchSitemapEntries(): array
    {
        if (self::$sitemapCache !== null) {
            return self::$sitemapCache;
        }

        $xml = JobHttp::get(self::SITEMAP, self::htmlHeaders(), 60);
        if (!is_string($xml) || $xml === '' || !str_contains($xml, '<loc>')) {
            return [];
        }

        $out = [];
        if (!preg_match_all(
            '~<url>\s*<loc>([^<]+)</loc>\s*(?:<lastmod>([^<]*)</lastmod>)?~i',
            $xml,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        foreach ($matches as $row) {
            $url = trim(html_entity_decode($row[1], ENT_QUOTES | ENT_HTML5));
            if ($url === '' || !str_contains($url, '/stellenangebote/')) {
                continue;
            }
            if (!preg_match('~/stellenangebote/(\d+)-([^/?#]+)~', $url, $m)) {
                continue;
            }
            $lastmod = trim((string) ($row[2] ?? ''));
            $ts = $lastmod !== '' ? (int) strtotime($lastmod) : 0;
            $out[] = [
                'url' => $url,
                'slug' => $m[1] . '-' . $m[2],
                'id' => $m[1],
                'lastmod' => $lastmod !== '' ? substr($lastmod, 0, 10) : '',
                'lastmod_ts' => $ts,
            ];
        }

        usort($out, static fn(array $a, array $b): int => $b['lastmod_ts'] <=> $a['lastmod_ts']);
        self::$sitemapCache = $out;

        return $out;
    }

    /** @param array{url:string,slug:string,id:string,lastmod:string,lastmod_ts:int} $entry */
    private static function listingFromEntry(array $entry): ?JobListing
    {
        $title = self::titleFromSlug($entry['slug']);
        if ($title === '') {
            return null;
        }

        $city = self::cityFromSlug($entry['slug']);
        $posted = $entry['lastmod'] !== '' ? $entry['lastmod'] : null;
        [$employment, $offerType, $tags] = self::employmentFromHint($entry['slug']);
        $low = mb_strtolower($entry['slug']);

        $job = new JobListing(
            'studentjob',
            $entry['id'],
            $title,
            'Employer',
            $city,
            '',
            'Germany',
            str_contains($low, 'home-office') ? 'remote' : 'unknown',
            $employment,
            $offerType,
            $tags,
            [],
            '',
            $posted,
            $entry['url'],
            '',
        );

        return JobText::enrich($job);
    }

    /** @return list<JobListing> */
    private static function searchHtmlCity(string $citySlug): array
    {
        $requests = [];
        for ($page = 1; $page <= self::CITY_HTML_PAGES; $page++) {
            $base = self::BASE . '/stellenangebote-in/' . rawurlencode($citySlug);
            $url = $page > 1 ? $base . '?page=' . $page : $base;
            $requests['p' . $page] = [
                'url' => $url,
                'headers' => self::htmlHeaders(),
            ];
        }

        $bodies = JobHttp::multiGet($requests, 10);
        $listings = [];
        foreach ($bodies as $html) {
            if (!is_string($html) || $html === '') {
                continue;
            }
            foreach (self::parseList($html) as $job) {
                $listings[] = $job;
            }
        }

        return $listings;
    }

    private static function titleFromSlug(string $slug): string
    {
        $slug = preg_replace('/^\d+-/', '', $slug) ?? $slug;
        $slug = str_replace(['-', '_'], ' ', $slug);
        $slug = preg_replace('/\s+/u', ' ', $slug) ?? $slug;
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        return mb_convert_case($slug, MB_CASE_TITLE, 'UTF-8');
    }

    private static function cityFromSlug(string $slug): string
    {
        if (!preg_match('/-in-([a-z0-9-]+?)(?:-deutschland|-germany)?(?:-|$)/i', $slug, $m)) {
            return '';
        }

        $citySlug = $m[1];
        foreach (self::CITY_SLUGS as $display => $mapped) {
            if ($mapped === $citySlug) {
                return mb_convert_case($display, MB_CASE_TITLE, 'UTF-8');
            }
        }

        return mb_convert_case(str_replace('-', ' ', $citySlug), MB_CASE_TITLE, 'UTF-8');
    }

    private static function slugMatchesCity(string $slug, string $city, string $citySlug): bool
    {
        $hay = mb_strtolower($slug);
        if (str_contains($hay, '-in-' . $citySlug)) {
            return true;
        }

        $cityLow = mb_strtolower(trim($city));
        if ($cityLow === '') {
            return false;
        }

        $cityDash = str_replace(' ', '-', $cityLow);

        return str_contains($hay, $cityDash) || str_contains($hay, $cityLow);
    }

    /** @param list<string> $keywords */
    private static function slugMatchesKeywords(string $slug, array $keywords): bool
    {
        $hay = ' ' . mb_strtolower(str_replace(['-', '_', '.'], ' ', $slug)) . ' ';
        foreach ($keywords as $kw) {
            $kw = mb_strtolower(trim($kw));
            if ($kw === '') {
                continue;
            }
            $parts = preg_split('/\s+/u', $kw) ?: [];
            $ok = true;
            foreach ($parts as $p) {
                if ($p === '') {
                    continue;
                }
                $ascii = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p);
                if (!str_contains($hay, $p) && !str_contains($hay, $ascii)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    private static function citySlug(string $city): string
    {
        $city = trim($city);
        if ($city === '') {
            return '';
        }
        $key = mb_strtolower($city);

        return self::CITY_SLUGS[$key] ?? '';
    }

    /** @return list<string> */
    private static function htmlHeaders(): array
    {
        return [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.5',
            'User-Agent: Mozilla/5.0 (compatible; MNK-Jobs/1.1; +https://mnk.ddev.site/)',
        ];
    }

    /** @param list<JobListing> $listings @return list<JobListing> */
    private static function dedupeListings(array $listings): array
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

    /** @return list<JobListing> */
    private static function parseList(string $html): array
    {
        $dom = self::dom($html);
        if ($dom === null) {
            return [];
        }
        $xp = new DOMXPath($dom);
        $out = [];
        foreach ($xp->query('//a[contains(@class,"job-opening__item")]') as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $id = trim($a->getAttribute('data-job-opening-id'));
            if ($id === '') {
                continue;
            }
            $title = trim($a->getAttribute('data-job-opening-title'));
            if ($title === '') {
                $title = self::firstText($xp, $a, './/h3');
            }
            if ($title === '') {
                continue;
            }

            $brand = trim($a->getAttribute('data-job-opening-item-brand'));
            $employmentHint = trim($a->getAttribute('data-job-opening-employment'));
            $category = trim($a->getAttribute('data-job-opening-item-category'));
            $salaryMin = trim($a->getAttribute('data-job-opening-salary-min'));
            $salaryMax = trim($a->getAttribute('data-job-opening-salary-max'));

            $href = trim($a->getAttribute('href'));
            $url = str_starts_with($href, 'http') ? $href : self::BASE . $href;

            $location = self::firstText($xp, $a, './/span[contains(@class,"nyc-icon-location")]/following-sibling::span[1]');
            $city = self::cityFromLocation($location);

            $salaryText = '';
            if ($salaryMin !== '' || $salaryMax !== '') {
                if ($salaryMin !== '' && $salaryMax !== '') {
                    $salaryText = $salaryMin . '–' . $salaryMax;
                } else {
                    $salaryText = $salaryMin !== '' ? $salaryMin : $salaryMax;
                }
            } else {
                $salaryText = self::firstText($xp, $a, './/span[contains(@class,"nyc-icon-euro-circle")]/following-sibling::span[1]');
            }

            $company = $brand;
            if ($company === '') {
                $img = $xp->query('.//img[contains(@class,"job-opening__customer-logo")]', $a);
                if ($img !== false && $img->length > 0 && $img->item(0) instanceof DOMElement) {
                    $company = trim($img->item(0)->getAttribute('title') ?: $img->item(0)->getAttribute('alt'));
                }
            }
            if ($company === '') {
                $company = 'Employer';
            }

            [$employment, $offerType, $tags] = self::employmentFromHint($employmentHint . ' ' . $title . ' ' . $category);

            $job = new JobListing(
                'studentjob',
                $id,
                $title,
                $company,
                $city,
                '',
                'Germany',
                str_contains(mb_strtolower($employmentHint), 'home-office') ? 'remote' : 'unknown',
                $employment,
                $offerType,
                $tags,
                [],
                $salaryText,
                null,
                $url,
                '',
            );
            $out[] = JobText::enrich($job);
        }

        return $out;
    }

    /**
     * @return array{0:string,1:string,2:list<string>}
     */
    private static function employmentFromHint(string $hint): array
    {
        $low = mb_strtolower($hint);
        $tags = ['student'];
        $employment = 'unknown';
        $offerType = 'job';

        if (str_contains($low, 'werkstudent')) {
            $tags[] = 'student';
            $employment = 'parttime';
            $offerType = 'internship';
        }
        if (str_contains($low, 'praktikum') || str_contains($low, 'internship')) {
            $tags[] = 'internship';
            $offerType = 'internship';
        }
        if (str_contains($low, 'minijob')) {
            $tags[] = 'minijob';
            $employment = 'parttime';
        }
        if (str_contains($low, 'teilzeit') || str_contains($low, 'nebenjob') || str_contains($low, 'ferienjob')) {
            $employment = 'parttime';
        }
        if (str_contains($low, 'vollzeit')) {
            $employment = 'fulltime';
        }
        if (str_contains($low, 'trainee')) {
            $tags[] = 'graduate';
        }

        return [$employment, $offerType, array_values(array_unique($tags))];
    }

    private static function parseDetail(string $html, string $id, ?JobListing $cached): ?JobListing
    {
        $ld = self::jobPostingLd($html);
        $title = $cached->title ?? '';
        $company = $cached->company ?? 'Employer';
        $city = $cached->city ?? '';
        $bundesland = $cached->bundesland ?? '';
        $posted = $cached->postedAt ?? null;
        $desc = $cached->description ?? '';
        $apply = $cached->applyUrl ?? '';
        $employment = $cached->employment ?? 'unknown';
        $url = $cached !== null && $cached->url !== ''
            ? $cached->url
            : self::BASE . '/stellenangebote/' . rawurlencode($id);

        if (is_array($ld)) {
            $t = trim((string) ($ld['title'] ?? ''));
            if ($t !== '') {
                $title = $t;
            }
            $postedLd = trim((string) ($ld['datePosted'] ?? ''));
            if ($postedLd !== '') {
                $posted = substr($postedLd, 0, 10);
            }
            $org = $ld['hiringOrganization'] ?? [];
            if (is_array($org)) {
                $orgName = trim((string) ($org['name'] ?? ''));
                if ($orgName !== '' && ($company === '' || $company === 'Employer')) {
                    $company = $orgName;
                }
            }
            $loc = $ld['jobLocation'] ?? [];
            if (is_array($loc)) {
                if (isset($loc['address']) && is_array($loc['address'])) {
                    $addr = $loc['address'];
                    $locCity = trim((string) ($addr['addressLocality'] ?? ''));
                    $region = trim((string) ($addr['addressRegion'] ?? ''));
                    if ($locCity !== '') {
                        $city = $locCity;
                    }
                    if ($region !== '') {
                        $bundesland = $region;
                    }
                } elseif (isset($loc[0]) && is_array($loc[0])) {
                    $addr = $loc[0]['address'] ?? [];
                    if (is_array($addr)) {
                        $locCity = trim((string) ($addr['addressLocality'] ?? ''));
                        if ($locCity !== '') {
                            $city = $locCity;
                        }
                    }
                }
            }
            $d = trim((string) ($ld['description'] ?? ''));
            if ($d !== '' && mb_strlen(strip_tags($d)) > 80) {
                $desc = $d;
            }
        }

        $dom = self::dom($html);
        if ($dom !== null) {
            $xp = new DOMXPath($dom);
            if ($title === '') {
                $title = self::firstText($xp, $dom, '//h1[@itemprop="title"]|//h1');
            }
            if ($desc === '' || mb_strlen(strip_tags($desc)) < 80) {
                $body = self::firstText($xp, $dom, '//*[contains(@class,"job-opening__body")]');
                if ($body !== '' && mb_strlen($body) > mb_strlen(strip_tags($desc))) {
                    $desc = '<p>' . htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
                }
            }
            if ($apply === '') {
                $apply = self::applyHrefFromDom($xp);
            }
            if ($city === '') {
                $locLine = self::firstText($xp, $dom, '//*[contains(@class,"nyc-icon-location")]/following-sibling::span[1]');
                if ($locLine !== '') {
                    $city = self::cityFromLocation($locLine);
                }
            }
        }

        if ($title === '') {
            return $cached;
        }

        $tags = $cached->seniorityTags ?? ['student'];
        if ($tags === []) {
            $tags = ['student'];
        }

        $job = new JobListing(
            'studentjob',
            $id,
            $title,
            $company !== '' ? $company : 'Employer',
            $city,
            $bundesland,
            'Germany',
            $cached->workMode ?? 'unknown',
            $employment,
            $cached->offerType ?? 'job',
            $tags,
            $cached->languages ?? [],
            $cached->salaryText ?? '',
            $posted,
            $url,
            $desc,
            '',
            $apply,
        );

        return JobText::enrich($job);
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

    private static function applyHrefFromDom(DOMXPath $xp): string
    {
        foreach ($xp->query('//a[contains(@class,"btn__apply") or contains(.,"Bewirb dich")]') as $a) {
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

    private static function cityFromLocation(string $location): string
    {
        $location = trim($location);
        if ($location === '' || mb_strtolower($location) === 'bundesweit') {
            return '';
        }
        if (preg_match('/^\d{5}\s+(.+)$/u', $location, $m)) {
            return trim($m[1]);
        }

        return $location;
    }

    private static function firstText(DOMXPath $xp, DOMNode $ctx, string $query): string
    {
        $n = $xp->query($query, $ctx);
        if ($n === false || $n->length === 0) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $n->item(0)?->textContent ?? '') ?? '');
    }

    private static function dom(string $html): ?DOMDocument
    {
        $dom = new DOMDocument();
        $ok = @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return $ok ? $dom : null;
    }
}
