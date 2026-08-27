<?php

declare(strict_types=1);

namespace KaamMilo\Jobs\Sources;

use App;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use KaamMilo\Jobs\JobCache;
use KaamMilo\Jobs\JobHttp;
use KaamMilo\Jobs\JobListing;
use KaamMilo\Jobs\JobQuery;
use KaamMilo\Jobs\JobStore;
use KaamMilo\Jobs\JobText;

/**
 * Public Stellenbörse at jobexport.de — a distributor feed (BA, StepStone, Indeed, …).
 * Newest listings appear on /stellenboerse with an empty suchbegriff (~40k catalogue).
 * Keyword search is relevance-ranked and often returns older ads — we always also crawl
 * the empty-search newest pages so fresh jobs (e.g. dated today) are not missed.
 */
final class JobexportSource
{
    private const BASE = 'https://www.jobexport.de';
    /** Keyword search pages (relevance order — often older). */
    private const KEYWORD_PAGES = 6;
    /** Empty-search newest-first pages (~10 jobs each). */
    private const RECENT_PAGES = 30;

    /**
     * @return array{listings: list<JobListing>, notice: ?string}
     */
    public static function search(JobQuery $query): array
    {
        $terms = $query->keywords !== [] ? $query->keywords : [trim($query->searchWas())];
        if ($terms === [''] || $terms === []) {
            $terms = [''];
        }
        $terms = array_slice($terms, 0, 4);

        $requests = [];

        // Newest-first browse (matches https://www.jobexport.de/stellenboerse with no keyword).
        for ($page = 1; $page <= self::RECENT_PAGES; $page++) {
            $params = [
                'suchbegriff' => '',
                'ort' => $query->city,
                'umkreis' => $query->city !== '' ? '50' : '0',
                'page' => (string) $page,
            ];
            $requests['recent:' . $page] = [
                'url' => self::BASE . '/stellenboerse?' . http_build_query($params),
                'headers' => self::htmlHeaders(),
            ];
        }

        foreach ($terms as $i => $term) {
            $was = trim($term . ' ' . $query->extraKeywords());
            if ($was === '') {
                continue; // already covered by recent crawl
            }
            for ($page = 1; $page <= self::KEYWORD_PAGES; $page++) {
                $params = [
                    'suchbegriff' => $was,
                    'ort' => $query->city,
                    'umkreis' => $query->city !== '' ? '50' : '0',
                    'page' => (string) $page,
                ];
                $requests[$i . ':' . $page] = [
                    'url' => self::BASE . '/stellenboerse?' . http_build_query($params),
                    'headers' => self::htmlHeaders(),
                ];
            }
        }

        $bodies = JobHttp::multiGet($requests, 14);
        $listings = [];
        $ok = 0;
        foreach ($bodies as $html) {
            if (!is_string($html) || $html === '') {
                continue;
            }
            $ok++;
            foreach (self::parseList($html) as $job) {
                $listings[] = $job;
            }
        }

        $seen = [];
        $unique = [];
        foreach ($listings as $job) {
            if (isset($seen[$job->externalId])) {
                continue;
            }
            $seen[$job->externalId] = true;
            $unique[] = $job;
        }

        if ($ok === 0) {
            return [
                'listings' => [],
                'notice' => 'Jobexport Stellenbörse did not respond.',
            ];
        }

        return ['listings' => $unique, 'notice' => null];
    }

    public static function details(string $externalId): ?JobListing
    {
        $cached = JobCache::getListing('jobexport', $externalId);
        if ($cached === null) {
            $cached = JobStore::get('jobexport', $externalId);
        }
        if ($cached !== null && mb_strlen(trim(strip_tags($cached->description))) >= 120) {
            return $cached;
        }
        $urls = [];
        if ($cached !== null && trim($cached->url) !== '') {
            $urls[] = trim($cached->url);
        }
        if ($cached !== null && preg_match('~/detail/' . preg_quote($externalId, '~') . '/[^?]+~', (string) $cached->url, $m)) {
            $urls[] = self::BASE . $m[0];
        }
        // Slug-less /detail/{id} returns empty — only try when we have no better URL.
        if ($urls === []) {
            $urls[] = self::BASE . '/detail/' . rawurlencode($externalId);
        }
        $urls = array_values(array_unique($urls));

        $html = null;
        foreach ($urls as $url) {
            $html = JobHttp::get($url, self::htmlHeaders(), 18);
            if (is_string($html) && $html !== '' && self::looksLikeDetail($html)) {
                break;
            }
            $html = null;
        }
        if ($html === null) {
            return $cached;
        }
        $fresh = self::parseDetail($html, $externalId, $cached);
        if ($fresh !== null) {
            JobCache::putListing($fresh);
            // Persist description into the dashboard index so the next open is instant.
            JobStore::upsertMany([$fresh]);
        }
        return $fresh ?? $cached;
    }

    /** @return list<string> */
    private static function htmlHeaders(): array
    {
        return [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'User-Agent: Mozilla/5.0 (compatible; MNK-Jobs/1.1; +https://mnk.ddev.site/)',
        ];
    }

    private static function looksLikeDetail(string $html): bool
    {
        return str_contains($html, 'jobTplContainer')
            || str_contains($html, 'id="jobdetail"')
            || str_contains($html, 'whitebox')
            || str_contains($html, 'Stellenbeschreibung')
            || str_contains($html, 'application/ld+json')
            || str_contains($html, 'col-md-7 main')
            || str_contains($html, 'Jetzt bewerben');
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
        foreach ($xp->query('//a[contains(@class,"job")]') as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $href = trim($a->getAttribute('href'));
            if (!preg_match('~/detail/(\d+)/([^?]+)~', $href, $m)) {
                continue;
            }
            $id = $m[1];
            $title = self::firstText($xp, $a, './/h3');
            $company = self::firstText($xp, $a, './/span[contains(@class,"company")]');
            $location = self::firstText($xp, $a, './/span[contains(@class,"location")]');
            $created = self::firstText($xp, $a, './/span[contains(@class,"created")]');
            if ($title === '') {
                continue;
            }
            $city = self::cityFromLocation($location);
            $url = self::BASE . '/detail/' . $id . '/' . $m[2];
            $job = new JobListing(
                'jobexport',
                $id,
                $title,
                $company !== '' ? $company : 'Employer',
                $city,
                '',
                'Germany',
                'unknown',
                'unknown',
                'job',
                [],
                [],
                '',
                self::parseDeDate($created),
                $url,
                '',
            );
            $out[] = JobText::enrich($job);
        }
        return $out;
    }

    private static function parseDetail(string $html, string $id, ?JobListing $cached): ?JobListing
    {
        $ld = self::jobPostingLd($html);
        $dom = self::dom($html);
        $title = $cached->title ?? '';
        $company = $cached->company ?? '';
        $city = $cached->city ?? '';
        $bundesland = $cached->bundesland ?? '';
        $posted = $cached->postedAt ?? null;
        $desc = $cached->description ?? '';
        $apply = $cached->applyUrl ?? '';
        $employment = $cached->employment ?? 'unknown';
        $url = $cached !== null && $cached->url !== ''
            ? $cached->url
            : self::BASE . '/detail/' . rawurlencode($id);

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
                if ($orgName !== '' && !self::isDistributorName($orgName) && ($company === '' || $company === 'Employer')) {
                    $company = $orgName;
                }
            }
            $loc = $ld['jobLocation'] ?? [];
            if (is_array($loc) && isset($loc['address']) && is_array($loc['address'])) {
                $addr = $loc['address'];
                $locCity = trim((string) ($addr['addressLocality'] ?? ''));
                $region = trim((string) ($addr['addressRegion'] ?? ''));
                if ($locCity !== '') {
                    $city = $locCity;
                }
                if ($region !== '') {
                    $bundesland = $region;
                }
            }
            // Jobexport JobPosting JSON often has an empty description — body HTML is the source of truth.
            $d = trim((string) ($ld['description'] ?? ''));
            if ($d !== '' && mb_strlen(strip_tags($d)) > 80) {
                $desc = $d;
            }
        }

        if ($dom !== null) {
            $xp = new DOMXPath($dom);
            if ($title === '') {
                $title = self::firstText($xp, $dom, '//*[@id="jobdetail"]//h1');
            }
            // Main ad body: first col-md-12 whitebox (Über die Rolle / Aufgaben / Profil / Angebot).
            $bodyHtml = self::firstWhiteboxHtml($dom, $xp);
            if ($bodyHtml !== '' && mb_strlen(strip_tags($bodyHtml)) > mb_strlen(strip_tags($desc))) {
                $desc = $bodyHtml;
            }
            if ($apply === '') {
                $apply = self::applyHrefFromDom($xp);
            }
            // Meta table: Arbeitgeber, Arbeitszeitmodell, Veröffentlicht.
            foreach ($xp->query('//*[@id="jobdetail"]//table//tr|//*[contains(@class,"whitebox")]//table//tr') as $tr) {
                if (!$tr instanceof DOMElement) {
                    continue;
                }
                $line = trim(preg_replace('/\s+/u', ' ', $tr->textContent ?? '') ?? '');
                if (preg_match('/^Arbeitgeber:\s*(.+)$/iu', $line, $m) && ($company === '' || $company === 'Employer')) {
                    $company = trim($m[1]);
                }
                if (preg_match('/^Arbeitszeitmodell:\s*(.+)$/iu', $line, $m)) {
                    $model = mb_strtolower(trim($m[1]));
                    if (str_contains($model, 'teilzeit') || str_contains($model, 'werkstudent')) {
                        $employment = 'parttime';
                    } elseif (str_contains($model, 'vollzeit')) {
                        $employment = 'fulltime';
                    }
                }
            }
            if ($city === '') {
                $locLine = self::firstText($xp, $dom, '//*[@id="jobdetail"]//*[contains(@class,"location") or contains(@class,"ort")]');
                if ($locLine === '' && preg_match('/(\d{5})\s+([A-Za-zÄÖÜäöüß\- ]+)/u', strip_tags($html), $lm)) {
                    $city = trim($lm[2]);
                } elseif ($locLine !== '') {
                    $city = self::cityFromLocation($locLine);
                }
            }
            if ($posted === null && preg_match('/Veröffentlicht:\s*(\d{1,2}\.\d{1,2}\.\d{4})/u', $html, $pm)) {
                $posted = self::parseDeDate($pm[1]);
            }
        }

        if ($title === '') {
            return $cached;
        }
        $job = new JobListing(
            'jobexport',
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
            '',
            $posted,
            $url,
            $desc,
            '',
            $apply,
        );
        return JobText::enrich($job);
    }

    private static function firstWhiteboxHtml(DOMDocument $dom, DOMXPath $xp): string
    {
        $nodes = $xp->query('//*[contains(@class,"whitebox") and contains(@class,"col-md-12")]');
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xp->query('//*[contains(@class,"whitebox")]');
        }
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }
        $el = $nodes->item(0);
        if (!$el instanceof DOMElement) {
            return '';
        }
        $inner = '';
        foreach ($el->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }
        // Drop trailing apply buttons / empty noise; keep headings + lists.
        $inner = preg_replace('~<a\b[^>]*>\s*(Weitere Details und Bewerbung|Jetzt bewerben)\s*</a>~iu', '', $inner) ?? $inner;
        $inner = trim($inner);
        return mb_strlen(strip_tags($inner)) >= 80 ? $inner : '';
    }

    private static function applyHrefFromDom(DOMXPath $xp): string
    {
        foreach ($xp->query('//a[contains(.,"Jetzt bewerben") or contains(.,"Weitere Details und Bewerbung") or contains(.,"Bewerbung")]') as $a) {
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

    private static function cityFromLocation(string $location): string
    {
        $location = trim($location);
        if ($location === '') {
            return '';
        }
        if (preg_match('/^\d{5}\s+(.+)$/u', $location, $m)) {
            return trim($m[1]);
        }
        return $location;
    }

    private static function isDistributorName(string $name): bool
    {
        return (bool) preg_match('/^(joblica|jobexport|jobbox|vonq)$/iu', trim($name));
    }

    private static function parseDeDate(string $raw): ?string
    {
        if (!preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', trim($raw), $m)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
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
