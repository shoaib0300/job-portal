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
 * XING Jobs — public search HTML (VPS, no Bright Data).
 */
final class XingSource
{
    private const BASE = 'https://www.xing.com';

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        if (!$query->wantsSource('xing')) {
            return ['listings' => [], 'notices' => []];
        }

        $params = [
            'keywords' => JobspySupport::searchKeywords($query),
            'location' => $query->city !== ''
                ? $query->city
                : ($query->bundesland !== '' ? $query->bundesland : 'Deutschland'),
            'radius' => '30',
        ];
        $url = self::BASE . '/jobs/search?' . http_build_query($params);
        $html = JobHttp::get($url, JobspySupport::browserHeaders(), 18);
        if (!is_string($html) || $html === '') {
            return [
                'listings' => [],
                'notices' => [
                    App::isDev()
                        ? 'XING Jobs search did not respond (xing.com).'
                        : 'XING Jobs search failed. The site may block this server IP.',
                ],
            ];
        }

        $listings = self::parseList($html, $query);
        if ($listings === []) {
            return [
                'listings' => [],
                'notices' => ['XING returned no matching jobs for this search.'],
            ];
        }

        return ['listings' => $listings, 'notices' => []];
    }

    public static function details(string $externalId): ?JobListing
    {
        $cached = JobCache::getListing('xing', $externalId)
            ?? JobStore::get('xing', $externalId);
        if ($cached !== null && mb_strlen(trim(strip_tags($cached->description))) >= 120) {
            return $cached;
        }

        $url = $cached !== null && trim($cached->url) !== ''
            ? trim($cached->url)
            : null;
        if ($url === null) {
            return $cached;
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

    /** @return list<JobListing> */
    private static function parseList(string $html, JobQuery $query): array
    {
        $dom = self::dom($html);
        if ($dom === null) {
            return [];
        }
        $xp = new DOMXPath($dom);
        $out = [];
        $seen = [];
        foreach ($xp->query('//*[@data-testid="job-search-result"]') as $item) {
            if (!$item instanceof DOMElement) {
                continue;
            }
            $job = self::parseJobItem($xp, $item, $query);
            if ($job === null || isset($seen[$job->externalId])) {
                continue;
            }
            $seen[$job->externalId] = true;
            $out[] = JobText::enrich($job);
        }

        return $out;
    }

    private static function parseJobItem(DOMXPath $xp, DOMElement $item, JobQuery $query): ?JobListing
    {
        $href = '';
        foreach ($xp->query('.//a[contains(@href,"/jobs/")]', $item) as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $candidate = trim($a->getAttribute('href'));
            if ($candidate === '' || str_contains($candidate, '/jobs/search')) {
                continue;
            }
            if (preg_match('#-(\d{6,})$#', $candidate)) {
                $href = $candidate;
                break;
            }
        }
        if ($href === '' || !preg_match('#-(\d{6,})$#', $href, $m)) {
            return null;
        }
        $externalId = $m[1];

        $titleNode = $xp->query('.//*[@data-testid="job-teaser-list-title"]', $item)->item(0);
        $title = $titleNode instanceof DOMElement ? trim($titleNode->textContent) : '';
        if ($title === '') {
            $title = self::titleFromSlug($href);
        }

        $url = str_starts_with($href, 'http') ? $href : self::BASE . $href;
        $city = $query->city;
        if ($city === '' && preg_match('#^/jobs/([^-]+)-#', $href, $cityM)) {
            $city = ucfirst(str_replace('-', ' ', $cityM[1]));
        }

        $job = new JobListing(
            'xing',
            $externalId,
            $title !== '' ? $title : 'XING job',
            'XING',
            $city,
            $query->bundesland,
            'Germany',
            JobText::workMode($title),
            JobText::employment($title),
            JobText::offerType($title),
            [],
            [],
            '',
            null,
            $url,
            '',
        );
        $job->applyUrl = $url;

        if (JobText::isForeignPrimaryLocation($job->city, $job->country, $job->title)) {
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
        $company = trim((string) ($ld['hiringOrganization']['name'] ?? ($cached->company ?? 'XING')));
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

        $city = $cached->city ?? '';
        if (isset($ld['jobLocation']) && is_array($ld['jobLocation'])) {
            $addr = $ld['jobLocation']['address'] ?? $ld['jobLocation'];
            if (is_array($addr)) {
                $loc = trim((string) ($addr['addressLocality'] ?? ''));
                if ($loc !== '') {
                    $city = $loc;
                }
            }
        }

        $job = new JobListing(
            'xing',
            $externalId,
            $title !== '' ? $title : ($cached->title ?? 'XING job'),
            $company !== '' ? $company : ($cached->company ?? 'XING'),
            $city,
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

    private static function titleFromSlug(string $href): string
    {
        if (!preg_match('#/jobs/(.+)-(\d{6,})$#', $href, $m)) {
            return '';
        }
        $slug = preg_replace('/-\d{6,}$/', '', $m[1]) ?? $m[1];
        $slug = preg_replace('/^[^-]+-/', '', $slug) ?? $slug;

        return ucwords(str_replace('-', ' ', $slug));
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
