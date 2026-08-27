<?php

declare(strict_types=1);

namespace KaamMilo\Jobs\Sources;

use DOMDocument;
use DOMElement;
use DOMXPath;
use KaamMilo\Jobs\JobCache;
use KaamMilo\Jobs\JobHttp;
use KaamMilo\Jobs\JobListing;
use KaamMilo\Jobs\JobQuery;
use KaamMilo\Jobs\JobText;

/**
 * Jobware.de HTML search — no Bright Data SERP.
 * Direct fetch often 403 from datacenter IPs; Unlocker used only as fallback when token is set.
 */
final class JobwareSource
{
    private const BASE = 'https://www.jobware.de';
    private const PAGES = 5;

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        $terms = $query->keywords !== [] ? $query->keywords : [trim($query->searchWas())];
        if ($terms === [] || $terms === ['']) {
            $terms = ['Software'];
        }
        $terms = array_slice($terms, 0, 4);
        $listings = [];
        $ok = 0;
        $blocked = 0;

        foreach ($terms as $term) {
            for ($page = 1; $page <= self::PAGES; $page++) {
                $params = [
                    'jw_jobname' => trim($term),
                    'jw_jobort' => $query->city,
                    'jw_ort_distance' => $query->city !== '' ? '50' : '0',
                ];
                if ($page > 1) {
                    $params['page'] = (string) $page;
                }
                $url = self::BASE . '/jobsuche?' . http_build_query($params);
                $html = self::fetchHtml($url);
                if ($html === null || $html === '') {
                    $blocked++;
                    continue;
                }
                if (stripos($html, '403 Forbidden') !== false || strlen($html) < 500) {
                    $blocked++;
                    continue;
                }
                $ok++;
                foreach (self::parseList($html) as $job) {
                    $listings[] = $job;
                }
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

        $notices = [];
        if ($ok === 0) {
            $notices[] = SerpBoardSource::token() !== ''
                ? 'Jobware did not return results.'
                : 'Jobware blocks datacenter IPs. Set BRIGHT_DATA_API_TOKEN for Unlocker fallback, or Jobexport/AA/LinkedIn still fill the index.';
        }

        return ['listings' => $unique, 'notices' => $notices];
    }

    public static function details(string $externalId): ?JobListing
    {
        $cached = JobCache::getListing('jobware', $externalId);
        $url = $cached !== null && $cached->url !== ''
            ? $cached->url
            : self::BASE . '/job/' . rawurlencode($externalId);
        $html = self::fetchHtml($url);
        if ($html === null || $html === '') {
            return $cached;
        }
        $fresh = self::parseDetail($html, $externalId, $cached);
        if ($fresh !== null) {
            JobCache::putListing($fresh);
        }
        return $fresh ?? $cached;
    }

    private static function fetchHtml(string $url): ?string
    {
        $html = JobHttp::get($url, self::headers(), 18);
        if (is_string($html) && $html !== '' && stripos($html, '403 Forbidden') === false && strlen($html) > 800) {
            return $html;
        }
        // Optional Unlocker only when token exists (not SERP — just HTML unlock).
        if (SerpBoardSource::token() !== '') {
            $unlocked = JobHttp::unlockHtml($url, 22);
            if (is_string($unlocked) && $unlocked !== '') {
                return $unlocked;
            }
        }
        return is_string($html) ? $html : null;
    }

    /** @return list<string> */
    private static function headers(): array
    {
        return [
            'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];
    }

    /** @return list<JobListing> */
    private static function parseList(string $html): array
    {
        $out = [];
        if (preg_match_all('~href="((?:https://www\.jobware\.de)?/job/([^"?#]+))"[^>]*>~i', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $path = $row[1];
                $slug = $row[2];
                $id = $slug;
                if (preg_match('/(\d{6,})$/', $slug, $idm)) {
                    $id = $idm[1];
                }
                $url = str_starts_with($path, 'http') ? $path : (self::BASE . $path);
                $title = self::titleFromSlug($slug);
                $job = new JobListing(
                    'jobware',
                    $id,
                    $title !== '' ? $title : 'Jobware listing',
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
                    null,
                    $url,
                    '',
                );
                $out[] = JobText::enrich($job);
            }
        }

        $dom = self::dom($html);
        if ($dom === null) {
            return self::dedupeList($out);
        }
        $xp = new DOMXPath($dom);
        foreach ($xp->query('//a[contains(@href,"/job/")]') as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $href = trim($a->getAttribute('href'));
            if (!preg_match('~/job/([^"?#]+)~', $href, $hm)) {
                continue;
            }
            $slug = $hm[1];
            $id = preg_match('/(\d{6,})$/', $slug, $idm) ? $idm[1] : $slug;
            $title = trim(preg_replace('/\s+/u', ' ', $a->textContent ?? '') ?? '');
            if ($title === '' || mb_strlen($title) < 4) {
                $title = self::titleFromSlug($slug);
            }
            $url = str_starts_with($href, 'http') ? $href : (self::BASE . $href);
            $company = '';
            $parent = $a->parentNode;
            if ($parent !== null) {
                $blob = trim(preg_replace('/\s+/u', ' ', $parent->textContent ?? '') ?? '');
                if (preg_match('/\b(?:bei|at)\s+(.+?)(?:\s+[·|]|\s+\d|$)/iu', $blob, $cm)) {
                    $company = trim($cm[1]);
                }
            }
            $posted = JobText::parsePostedDate($a->textContent . ' ' . ($parent?->textContent ?? ''));
            $job = new JobListing(
                'jobware',
                $id,
                $title,
                $company !== '' ? $company : 'Employer',
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
                $url,
                '',
            );
            $out[] = JobText::enrich($job);
        }
        return self::dedupeList($out);
    }

    private static function parseDetail(string $html, string $id, ?JobListing $cached): ?JobListing
    {
        $title = $cached->title ?? '';
        $company = $cached->company ?? '';
        $city = $cached->city ?? '';
        $desc = $cached->description ?? '';
        $posted = $cached->postedAt ?? null;
        $url = $cached->url ?? (self::BASE . '/job/' . rawurlencode($id));

        if (preg_match('~<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>~is', $html, $lm)) {
            $ld = json_decode(html_entity_decode($lm[1], ENT_QUOTES | ENT_HTML5), true);
            if (is_array($ld)) {
                if (($ld['@type'] ?? '') === 'JobPosting' || isset($ld['title'])) {
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

    private static function titleFromSlug(string $slug): string
    {
        $slug = preg_replace('/-\d{6,}$/', '', $slug) ?? $slug;
        $slug = str_replace(['-', '_'], ' ', $slug);
        return trim(ucwords($slug));
    }

    /** @param list<JobListing> $list @return list<JobListing> */
    private static function dedupeList(array $list): array
    {
        $seen = [];
        $out = [];
        foreach ($list as $job) {
            if (isset($seen[$job->externalId])) {
                continue;
            }
            $seen[$job->externalId] = true;
            $out[] = $job;
        }
        return $out;
    }

    private static function dom(string $html): ?DOMDocument
    {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $ok ? $dom : null;
    }
}
