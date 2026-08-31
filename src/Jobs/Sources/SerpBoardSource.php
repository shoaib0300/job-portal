<?php

declare(strict_types=1);

namespace KaamFit\Jobs\Sources;

use App;
use KaamFit\Jobs\JobCache;
use KaamFit\Jobs\JobHttp;
use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobText;


final class SerpBoardSource
{
    /** Google SERP fallback — native VPS sources handle these boards by default. */
    public const BOARDS = [
        'indeed' => ['label' => 'Indeed', 'site' => 'site:indeed.de OR site:de.indeed.com'],
        'stepstone' => ['label' => 'StepStone', 'site' => 'site:stepstone.de'],
        'xing' => ['label' => 'XING', 'site' => 'site:xing.com/jobs'],
        'glassdoor' => ['label' => 'Glassdoor', 'site' => 'site:glassdoor.de/Job OR site:www.glassdoor.com/job-listing'],
    ];

    /** Boards with built-in VPS scrapers (JobSpy or PHP). */
    private const NATIVE_BOARDS = ['indeed', 'glassdoor', 'stepstone', 'xing'];
    // Jobware uses JobwareSource (HTML), not Google SERP.

    public static function token(): string
    {
        return trim((string) (getenv('BRIGHT_DATA_API_TOKEN') ?: ''));
    }

    public static function configured(): bool
    {
        return self::token() !== '';
    }

    private static function serpFallbackEnabled(): bool
    {
        $flag = strtolower(trim((string) (getenv('JOBS_SERP_FALLBACK') ?: '')));

        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        $wanted = array_values(array_intersect(array_keys(self::BOARDS), $query->sources));
        if ($wanted === []) {
            return ['listings' => [], 'notices' => []];
        }
        if (!self::serpFallbackEnabled()) {
            $wanted = array_values(array_diff($wanted, self::NATIVE_BOARDS));
        }
        if ($wanted === []) {
            return ['listings' => [], 'notices' => []];
        }
        if (!self::configured()) {
            if (!App::isDev()) {
                return ['listings' => [], 'notices' => []];
            }
            $labels = array_map(static fn(string $id): string => self::BOARDS[$id]['label'], $wanted);
            return [
                'listings' => [],
                'notices' => [
                    implode(', ', $labels) . ' need BRIGHT_DATA_API_TOKEN in .env (Google site search). Arbeitsagentur still works.',
                ],
            ];
        }

        $was = $query->serpWas();
        $where = $query->whereText();
        // Google time filter: past day or past 2 weeks.
        if ($query->effectivePostedDays() === 1) {
            $tbs = 'qdr:d';
        } else {
            $min = date('m/d/Y', time() - JobQuery::MAX_POSTED_DAYS * 86400);
            $max = date('m/d/Y');
            $tbs = 'cdr:1,cd_min:' . $min . ',cd_max:' . $max;
        }
        $requests = [];
        foreach ($wanted as $id) {
            $q = trim(self::BOARDS[$id]['site'] . ' ' . $was . ' ' . $where . ' Germany jobs');
            $google = 'https://www.google.com/search?q=' . rawurlencode($q)
                . '&num=10&hl=de&gl=de&tbs=' . rawurlencode($tbs) . '&brd_json=1';
            $requests[$id] = $google;
        }

        $listings = [];
        $notices = [];
        $zone = trim((string) (getenv('BRIGHT_DATA_ZONE') ?: 'web_unlocker1'));
        $postReqs = [];
        foreach ($requests as $id => $googleUrl) {
            $postReqs[$id] = [
                'url' => 'https://api.brightdata.com/request',
                'payload' => [
                    'zone' => $zone,
                    'url' => $googleUrl,
                    'format' => 'json',
                ],
                'headers' => ['Authorization: Bearer ' . self::token()],
            ];
        }
        $responses = JobHttp::multiPostJson($postReqs, 12);
        foreach ($responses as $id => $data) {
            if ($data === null) {
                $notices[] = self::BOARDS[$id]['label'] . ' search failed.';
                continue;
            }
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
            foreach ($organic as $i => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $title = (string) ($row['title'] ?? $row['name'] ?? '');
                $link = (string) ($row['link'] ?? $row['url'] ?? '');
                $snip = (string) ($row['description'] ?? $row['snippet'] ?? '');
                if ($title === '' || $link === '') {
                    continue;
                }
                $company = '';
                if (preg_match('/\s[-–|]\s(.+)$/u', $title, $m)) {
                    $company = trim($m[1]);
                    $title = trim((string) preg_replace('/\s[-–|]\s.+$/u', '', $title));
                }
                $posted = JobText::parsePostedDate($snip);
                if ($posted === null && $title !== '') {
                    $posted = JobText::parsePostedDate($title);
                }
                $job = new JobListing(
                    $id,
                    hash('sha256', $link),
                    $title,
                    $company !== '' ? $company : self::BOARDS[$id]['label'],
                    $query->city,
                    $query->bundesland,
                    'Germany',
                    'unknown',
                    'unknown',
                    'job',
                    [],
                    [],
                    '',
                    $posted,
                    $link,
                    $snip,
                );
                $listings[] = JobText::enrich($job);
            }
        }

        return ['listings' => $listings, 'notices' => $notices];
    }

    public static function details(string $source, string $externalId): ?JobListing
    {
        return JobCache::getListing($source, $externalId);
    }
}
