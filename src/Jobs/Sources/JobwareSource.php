<?php

declare(strict_types=1);

namespace KaamMilo\Jobs\Sources;

use KaamMilo\Jobs\JobCache;
use KaamMilo\Jobs\JobHttp;
use KaamMilo\Jobs\JobListing;
use KaamMilo\Jobs\JobQuery;
use KaamMilo\Jobs\JobText;

/**
 * Jobware.de — public sitemap + optional detail hydrate.
 *
 * Category pages like /jobs/it are a JS SPA (curl sees an empty shell / intermittent 403).
 * The public sitemap https://www.jobware.de/sitemap-advertisements.xml lists every ad
 * with <lastmod> (~3k in the last 14 days) — no Bright Data required.
 */
final class JobwareSource
{
    private const BASE = 'https://www.jobware.de';
    private const SITEMAP = 'https://www.jobware.de/sitemap-advertisements.xml';
    /** Cap per search so ingest stays bounded (~3k Jobware ads in 14 days). */
    private const MAX_PER_SEARCH = 2000;

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        $entries = self::fetchSitemapEntries();
        if ($entries === []) {
            return [
                'listings' => [],
                'notices' => ['Jobware sitemap did not respond (public URL: /sitemap-advertisements.xml).'],
            ];
        }

        $days = $query->effectivePostedDays();
        $minTs = time() - ($days * 86400);
        $keywords = $query->keywords !== []
            ? $query->keywords
            : array_values(array_filter([trim($query->searchWas())]));

        $out = [];
        foreach ($entries as $entry) {
            if ($entry['lastmod_ts'] > 0 && $entry['lastmod_ts'] < $minTs) {
                continue;
            }
            if ($keywords !== [] && !self::slugMatchesKeywords($entry['slug'], $keywords)) {
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

        // Newest first when no keyword filter matched enough — already sorted by sitemap order;
        // re-sort by lastmod desc.
        usort($out, static function (JobListing $a, JobListing $b): int {
            $ta = $a->postedAt ? (int) strtotime($a->postedAt) : 0;
            $tb = $b->postedAt ? (int) strtotime($b->postedAt) : 0;
            return $tb <=> $ta;
        });

        $notices = [];
        if ($out === []) {
            $notices[] = 'Jobware sitemap had no matches in the last ' . $days . ' days for this query.';
        }

        return ['listings' => $out, 'notices' => $notices];
    }

    public static function details(string $externalId): ?JobListing
    {
        $cached = JobCache::getListing('jobware', $externalId);
        $urls = [];
        if ($cached !== null && $cached->url !== '') {
            $urls[] = $cached->url;
        }
        $urls[] = self::BASE . '/job/' . rawurlencode($externalId);
        $urls = array_values(array_unique($urls));

        foreach ($urls as $url) {
            $html = self::fetchHtml($url);
            if ($html === null || strlen($html) < 1000) {
                continue;
            }
            // SPA shell has no JobPosting — Unlocker/browser may return real HTML.
            if (!str_contains($html, 'JobPosting') && !preg_match('/<h1[\s>]/i', $html)) {
                continue;
            }
            $fresh = self::parseDetailHtml($html, $externalId, $cached, $url);
            if ($fresh !== null) {
                JobCache::putListing($fresh);
                return $fresh;
            }
        }
        return $cached;
    }

    /**
     * @return list<array{url:string,slug:string,id:string,lastmod:string,lastmod_ts:int}>
     */
    private static function fetchSitemapEntries(): array
    {
        $xml = JobHttp::get(self::SITEMAP, self::headers(), 60);
        if (!is_string($xml) || $xml === '' || !str_contains($xml, '<loc>')) {
            // Retry with unlocker if available.
            if (SerpBoardSource::token() !== '') {
                $xml = JobHttp::unlockHtml(self::SITEMAP, 45);
            }
        }
        if (!is_string($xml) || $xml === '') {
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
            if ($url === '' || !str_contains($url, '/job/')) {
                continue;
            }
            $lastmod = trim((string) ($row[2] ?? ''));
            $ts = $lastmod !== '' ? (int) strtotime($lastmod) : 0;
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            $slug = basename($path);
            $slug = preg_replace('/\.html$/i', '', $slug) ?? $slug;
            $id = '';
            if (preg_match('/(\d{6,})$/', $slug, $im)) {
                $id = $im[1];
            } elseif (preg_match('/\.(\d{6,})$/', $slug, $im)) {
                $id = $im[1];
            }
            if ($id === '') {
                $id = hash('crc32b', $url);
            }
            $out[] = [
                'url' => $url,
                'slug' => $slug,
                'id' => $id,
                'lastmod' => $lastmod !== '' ? substr($lastmod, 0, 10) : '',
                'lastmod_ts' => $ts,
            ];
        }

        // Newest first.
        usort($out, static fn(array $a, array $b): int => $b['lastmod_ts'] <=> $a['lastmod_ts']);
        return $out;
    }

    /** @param array{url:string,slug:string,id:string,lastmod:string,lastmod_ts:int} $entry */
    private static function listingFromEntry(array $entry): ?JobListing
    {
        $title = self::titleFromSlug($entry['slug']);
        if ($title === '') {
            return null;
        }
        $posted = $entry['lastmod'] !== '' ? $entry['lastmod'] : null;
        $job = new JobListing(
            'jobware',
            $entry['id'],
            $title,
            'Employer',
            '',
            '',
            'Germany',
            'unknown',
            'unknown',
            'job',
            [],
            [],
            '',
            $posted,
            $entry['url'],
            '',
        );
        return JobText::enrich($job);
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
            // Category-style "IT" → jobware.de/jobs/it-like slug vocabulary (not substring "it").
            if ($kw === 'it' || $kw === 'i.t.') {
                return (bool) preg_match(
                    '/\b(it|informatik|software|entwickl|programmier|devops|cloud|sap|java|python|php|netzwerk|systemadmin|administrator|datenbank|database|frontend|backend|fullstack|webentwick|qa|testautomat|tester|qualitätssicherung|qualitaetssicherung|security|cyber|ai |ki |machine learning|data engineer|data scientist)\b/u',
                    $hay
                );
            }
            // Single-token or all tokens present (e.g. "QA Engineer").
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
            if (preg_match('/\b(qa|test|software|entwickl|qualität|quality|automat)/u', $kw)
                && preg_match('/\b(qa|test|software|entwickl|qualitaet|quality|automat)/u', $hay)) {
                return true;
            }
        }
        return false;
    }

    private static function titleFromSlug(string $slug): string
    {
        $slug = preg_replace('/\.html$/i', '', $slug) ?? $slug;
        $slug = preg_replace('/(\.|-)\d{6,}$/', '', $slug) ?? $slug;
        $slug = str_replace(['-', '_', '.'], ' ', $slug);
        $slug = preg_replace('/\s+/u', ' ', $slug) ?? $slug;
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }
        return mb_convert_case($slug, MB_CASE_TITLE, 'UTF-8');
    }

    private static function fetchHtml(string $url): ?string
    {
        $html = JobHttp::get($url, self::headers(), 20);
        if (is_string($html) && $html !== '' && stripos($html, '403 Forbidden') === false && strlen($html) > 500) {
            // Prefer Unlocker when the response is clearly the SPA shell (no job body).
            if (str_contains($html, 'JobPosting') || preg_match('/<h1[\s>]/i', $html)) {
                return $html;
            }
        }
        if (SerpBoardSource::token() !== '') {
            $unlocked = JobHttp::unlockHtml($url, 25);
            if (is_string($unlocked) && $unlocked !== '') {
                return $unlocked;
            }
        }
        return is_string($html) ? $html : null;
    }

    private static function parseDetailHtml(string $html, string $id, ?JobListing $cached, string $url): ?JobListing
    {
        $title = $cached->title ?? '';
        $company = $cached->company ?? '';
        $city = $cached->city ?? '';
        $desc = $cached->description ?? '';
        $posted = $cached->postedAt ?? null;

        if (preg_match('~<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>~is', $html, $lm)) {
            $ld = json_decode(html_entity_decode($lm[1], ENT_QUOTES | ENT_HTML5), true);
            if (is_array($ld) && (($ld['@type'] ?? '') === 'JobPosting' || isset($ld['title']))) {
                $t = trim((string) ($ld['title'] ?? ''));
                if ($t !== '') {
                    $title = $t;
                }
                $org = $ld['hiringOrganization']['name'] ?? '';
                if (is_string($org) && trim($org) !== '') {
                    $company = trim($org);
                }
                $loc = $ld['jobLocation']['address']['addressLocality'] ?? '';
                if (is_string($loc) && trim($loc) !== '') {
                    $city = trim($loc);
                }
                $dp = trim((string) ($ld['datePosted'] ?? ''));
                if ($dp !== '') {
                    $posted = substr($dp, 0, 10);
                }
                $d = trim((string) ($ld['description'] ?? ''));
                if ($d !== '') {
                    $desc = strip_tags($d);
                }
            }
        }
        if ($title === '' && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $hm)) {
            $title = trim(strip_tags($hm[1]));
        }
        if ($title === '') {
            return $cached;
        }
        $job = new JobListing(
            'jobware',
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
            $posted,
            $url,
            $desc,
        );
        return JobText::enrich($job);
    }

    /** @return list<string> */
    private static function headers(): array
    {
        return [
            'Accept: application/xml,text/xml,text/html;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Referer: https://www.jobware.de/',
        ];
    }
}
