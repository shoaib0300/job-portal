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
 * StepStone.de — public search HTML (VPS, no Bright Data).
 */
final class StepStoneSource
{
    private const BASE = 'https://www.stepstone.de';
    private const MAX_PAGES = 2;

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        if (!$query->wantsSource('stepstone')) {
            return ['listings' => [], 'notices' => []];
        }

        $requests = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $url = self::searchUrl($query, $page);
            $requests['p' . $page] = [
                'url' => $url,
                'headers' => JobspySupport::browserHeaders(),
            ];
        }

        $bodies = JobHttp::multiGet($requests, 16);
        $listings = [];
        $ok = 0;
        foreach ($bodies as $html) {
            if (!is_string($html) || $html === '') {
                continue;
            }
            $ok++;
            foreach (self::parseList($html, $query) as $job) {
                $listings[] = $job;
            }
        }

        $unique = self::dedupeListings($listings);
        if ($ok === 0) {
            return [
                'listings' => [],
                'notices' => [
                    App::isDev()
                        ? 'StepStone search did not respond (stepstone.de).'
                        : 'StepStone search failed. Try again later.',
                ],
            ];
        }
        if ($unique === []) {
            return [
                'listings' => [],
                'notices' => ['StepStone returned no matching jobs for this search.'],
            ];
        }

        return ['listings' => $unique, 'notices' => []];
    }

    public static function details(string $externalId): ?JobListing
    {
        $cached = JobCache::getListing('stepstone', $externalId)
            ?? JobStore::get('stepstone', $externalId);
        if ($cached !== null && mb_strlen(trim(strip_tags($cached->description))) >= 120) {
            return $cached;
        }

        $path = $cached !== null && trim($cached->url) !== ''
            ? (string) parse_url($cached->url, PHP_URL_PATH)
            : null;
        if ($path === null || $path === '') {
            $path = self::guessPathFromId($externalId);
        }
        if ($path === null) {
            return $cached;
        }

        $url = str_starts_with($path, 'http') ? $path : self::BASE . $path;
        if (!str_contains($url, '-inline.html') && !str_contains($url, '.html')) {
            $url = rtrim($url, '/') . '-inline.html';
        }
        if (!str_contains($url, '-inline.html') && preg_match('/\.html$/', $url)) {
            $url = preg_replace('/\.html$/', '-inline.html', $url) ?? $url;
        }

        $html = JobHttp::get($url, JobspySupport::browserHeaders(), 18);
        if (!is_string($html) || $html === '') {
            return $cached;
        }

        $fresh = self::listingFromDetailHtml($html, $externalId, $cached);
        if ($fresh !== null) {
            JobCache::putListing($fresh);
            JobStore::upsertMany([$fresh]);
        }

        return $fresh ?? $cached;
    }

    private static function searchUrl(JobQuery $query, int $page): string
    {
        $keyword = self::slug(JobspySupport::searchKeywords($query));
        $location = $query->city !== ''
            ? self::slug($query->city)
            : ($query->bundesland !== '' ? self::slug($query->bundesland) : '');

        if ($location !== '') {
            $path = '/jobs/' . rawurlencode($keyword) . '/in-' . rawurlencode($location);
        } else {
            $path = '/jobs/' . rawurlencode($keyword);
        }
        if ($page > 1) {
            $path .= '?page=' . $page . '&action=paging_next';
        }

        return self::BASE . $path;
    }

    private static function slug(string $text): string
    {
        $text = trim(mb_strtolower($text));
        $text = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $text);
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? $text;
        $text = trim($text, '-');

        return $text !== '' ? $text : 'mitarbeiter';
    }

    /** @return list<JobListing> */
    private static function parseList(string $html, JobQuery $query): array
    {
        $dom = self::dom($html);
        if ($dom === null) {
            return [];
        }
        $xp = new DOMXPath($dom);
        $out = [];
        foreach ($xp->query('//*[@data-at="job-item"]') as $item) {
            if (!$item instanceof DOMElement) {
                continue;
            }
            $job = self::parseJobItem($xp, $item, $query);
            if ($job !== null) {
                $out[] = JobText::enrich($job);
            }
        }

        return $out;
    }

    private static function parseJobItem(DOMXPath $xp, DOMElement $item, JobQuery $query): ?JobListing
    {
        $href = '';
        foreach ($xp->query('.//a[contains(@href,"stellenangebote")]', $item) as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $candidate = trim($a->getAttribute('href'));
            if ($candidate !== '') {
                $href = $candidate;
                break;
            }
        }
        if ($href === '' || !preg_match('/--(\d+)(?:-inline)?\.html$/', $href, $m)) {
            return null;
        }
        $externalId = $m[1];

        $titleNode = $xp->query('.//*[@data-at="job-item-title"]', $item)->item(0);
        $title = $titleNode instanceof DOMElement
            ? JobspySupport::stripEmbeddedCss(trim($titleNode->textContent))
            : '';
        if ($title === '' && preg_match('#/stellenangebote--(.+)--\d+#', $href, $slugM)) {
            $title = str_replace('-', ' ', $slugM[1]);
        }

        $companyNode = $xp->query('.//*[@data-at="job-item-company-name"]', $item)->item(0);
        $company = $companyNode instanceof DOMElement
            ? JobspySupport::stripEmbeddedCss(trim($companyNode->textContent))
            : 'StepStone';

        $locNode = $xp->query('.//*[@data-at="job-item-location"]', $item)->item(0);
        $location = $locNode instanceof DOMElement ? trim($locNode->textContent) : '';

        $timeNode = $xp->query('.//*[@data-at="job-item-timeago"]', $item)->item(0);
        $posted = $timeNode instanceof DOMElement
            ? JobText::parsePostedDate(trim($timeNode->textContent))
            : null;

        $url = str_starts_with($href, 'http') ? $href : self::BASE . $href;
        $city = $query->city;
        if ($city === '' && $location !== '') {
            $city = trim(explode(',', $location)[0]);
        }

        $job = new JobListing(
            'stepstone',
            $externalId,
            $title !== '' ? $title : 'StepStone job',
            $company !== '' ? $company : 'StepStone',
            $city,
            $query->bundesland,
            'Germany',
            JobText::workMode($title . ' ' . $location),
            JobText::employment($title),
            JobText::offerType($title),
            [],
            [],
            '',
            $posted,
            $url,
            '',
        );
        $job->applyUrl = $url;

        if (!self::passesGermanyFilter($job)) {
            return null;
        }

        return $job;
    }

    private static function listingFromDetailHtml(string $html, string $externalId, ?JobListing $cached): ?JobListing
    {
        $ld = JobspySupport::jsonLdJobPosting($html);
        if ($ld === null) {
            return $cached;
        }

        $title = trim((string) ($ld['title'] ?? ($cached->title ?? '')));
        $company = trim((string) ($ld['hiringOrganization']['name'] ?? ($cached->company ?? 'StepStone')));
        $url = trim((string) ($ld['url'] ?? ($cached->url ?? '')));
        if ($url !== '' && !str_starts_with($url, 'http')) {
            $url = self::BASE . $url;
        }

        $desc = trim((string) ($ld['description'] ?? ''));
        if ($desc !== '') {
            $desc = JobText::stripHtml($desc);
            $desc = mb_substr($desc, 0, 12000);
        }

        $posted = null;
        $rawPosted = trim((string) ($ld['datePosted'] ?? ''));
        if ($rawPosted !== '') {
            $ts = strtotime($rawPosted);
            $posted = $ts !== false ? date('Y-m-d', $ts) : JobText::parsePostedDate($rawPosted);
        }

        $loc = '';
        if (isset($ld['jobLocation']) && is_array($ld['jobLocation'])) {
            $addr = $ld['jobLocation']['address'] ?? $ld['jobLocation'];
            if (is_array($addr)) {
                $loc = trim((string) ($addr['addressLocality'] ?? ''));
                if ($loc === '') {
                    $loc = trim((string) ($addr['addressRegion'] ?? ''));
                }
            }
        }

        $job = new JobListing(
            'stepstone',
            $externalId,
            $title !== '' ? $title : ($cached->title ?? 'StepStone job'),
            $company !== '' ? $company : ($cached->company ?? 'StepStone'),
            $loc !== '' ? $loc : ($cached->city ?? ''),
            $cached->bundesland ?? '',
            'Germany',
            JobText::workMode($title . ' ' . $desc),
            JobText::employment($title . ' ' . $desc),
            JobText::offerType($title . ' ' . $desc),
            [],
            [],
            '',
            $posted ?? $cached->postedAt,
            $url !== '' ? $url : ($cached->url ?? ''),
            $desc !== '' ? $desc : ($cached->description ?? ''),
        );
        $job->applyUrl = $job->url;

        return $job;
    }

    private static function passesGermanyFilter(JobListing $job): bool
    {
        if (JobText::isForeignPrimaryLocation($job->city, $job->country, $job->title)) {
            return false;
        }

        return JobText::looksLikeGermany($job->city, $job->bundesland, $job->country, $job->title . ' ' . $job->city);
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

    private static function guessPathFromId(string $externalId): ?string
    {
        if (!ctype_digit($externalId)) {
            return null;
        }

        return '/stellenangebote--job--' . $externalId . '-inline.html';
    }

    private static function dom(string $html): ?DOMDocument
    {
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $ok ? $dom : null;
    }
}
