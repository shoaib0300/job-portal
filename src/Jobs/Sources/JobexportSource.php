<?php

declare(strict_types=1);

/**
 * Public Stellenbörse at jobexport.de — a distributor feed (BA, StepStone, Indeed, …).
 * HTML search only; we never pull the full ~40k catalogue.
 */
final class JobexportSource
{
    private const BASE = 'https://www.jobexport.de';
    private const PAGES = 3;

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
        foreach ($terms as $i => $term) {
            $was = trim($term . ' ' . $query->extraKeywords());
            for ($page = 1; $page <= self::PAGES; $page++) {
                $params = [
                    'suchbegriff' => $was,
                    'ort' => $query->city,
                    'umkreis' => $query->city !== '' ? '50' : '0',
                    'page' => (string) $page,
                ];
                $requests[$i . ':' . $page] = [
                    'url' => self::BASE . '/stellenboerse?' . http_build_query($params),
                ];
            }
        }

        $bodies = JobHttp::multiGet($requests, 12);
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
        $path = $cached !== null && preg_match('~/detail/' . preg_quote($externalId, '~') . '/[^?]+~', $cached->url, $m)
            ? $m[0]
            : '/detail/' . rawurlencode($externalId);
        $html = JobHttp::get(self::BASE . $path, ['Accept: text/html'], 12);
        if ($html === null && $cached !== null && $cached->url !== '') {
            $html = JobHttp::get($cached->url, ['Accept: text/html'], 12);
        }
        if ($html === null) {
            return $cached;
        }
        $fresh = self::parseDetail($html, $externalId, $cached);
        if ($fresh !== null) {
            JobCache::putListing($fresh);
        }
        return $fresh ?? $cached;
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
            $ldDesc = trim(JobText::stripHtml((string) ($ld['description'] ?? '')));
            if ($ldDesc !== '') {
                $desc = (string) $ld['description'];
            }
        }

        if ($dom !== null) {
            $xp = new DOMXPath($dom);
            if ($title === '') {
                $title = self::firstText($xp, $dom, '//h1');
            }
            foreach ($xp->query('//a[contains(@class,"btn")]') as $a) {
                if (!$a instanceof DOMElement) {
                    continue;
                }
                $label = mb_strtolower(trim($a->textContent));
                if (!str_contains($label, 'bewerb')) {
                    continue;
                }
                $href = App::normalizeHttpUrl(trim(html_entity_decode($a->getAttribute('href'), ENT_QUOTES, 'UTF-8')));
                if ($href !== '' && !str_contains(mb_strtolower($href), 'jobexport.de')) {
                    $apply = $href;
                    break;
                }
            }
            $fromBox = self::descriptionFromDom($xp, $dom);
            if ($fromBox !== '') {
                $desc = $fromBox;
            }
        }

        if ($title === '') {
            return $cached;
        }

        $blob = $title . ' ' . JobText::stripHtml($desc);
        $job = new JobListing(
            'jobexport',
            $id,
            $title,
            $company !== '' ? $company : 'Employer',
            $city,
            $bundesland,
            'Germany',
            JobText::workMode($blob),
            JobText::employment($blob, ''),
            JobText::offerType($blob),
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

    /** @return array<string, mixed>|null */
    private static function jobPostingLd(string $html): ?array
    {
        if (!preg_match_all('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches)) {
            return null;
        }
        foreach ($matches[1] as $raw) {
            $data = json_decode(html_entity_decode(trim($raw), ENT_QUOTES, 'UTF-8'), true);
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

    private static function descriptionFromDom(DOMXPath $xp, DOMDocument $dom): string
    {
        $best = '';
        $bestLen = 0;
        foreach ($xp->query('//div[contains(@class,"whitebox")]') as $box) {
            if (!$box instanceof DOMElement) {
                continue;
            }
            $heading = '';
            foreach ($box->childNodes as $child) {
                if ($child instanceof DOMElement && preg_match('/^h[1-6]$/i', $child->tagName)) {
                    $heading = trim($child->textContent);
                    break;
                }
            }
            if (preg_match('/^(details|kontakt)$/iu', $heading)) {
                continue;
            }
            $html = '';
            foreach ($box->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'h3'
                    && preg_match('/stellenbeschreibung/iu', trim($child->textContent))) {
                    continue;
                }
                $html .= $dom->saveHTML($child);
            }
            $html = trim($html);
            $len = mb_strlen(trim(strip_tags($html)));
            if ($len > $bestLen && $len >= 80) {
                $best = $html;
                $bestLen = $len;
            }
        }
        return $best;
    }

    private static function isDistributorName(string $name): bool
    {
        return (bool) preg_match('/^(joblica|jobexport|jobbox)$/iu', trim($name));
    }

    private static function cityFromLocation(string $location): string
    {
        $location = trim(preg_replace('/\s+/u', ' ', $location) ?? $location);
        $location = preg_replace('/^\d{5}\s+/u', '', $location) ?? $location;
        return trim($location);
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
