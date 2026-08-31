<?php

declare(strict_types=1);

namespace KaamFit\Jobs\Sources;

use Freeworld\PhpJobspy\DTO\JobPostDTO;
use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobText;

/**
 * Shared python-jobspy runner + JobPostDTO → JobListing mapping.
 */
final class JobspySupport
{
    public const RESULTS_WANTED = 50;

    /** @var array<string, array{label: string, default_company: string, id_patterns: list<string>}> */
    public const BOARDS = [
        'linkedin' => [
            'label' => 'LinkedIn',
            'default_company' => 'LinkedIn',
            'id_patterns' => ['#/jobs/view/(?:[\w-]+-)?(\d+)#'],
        ],
        'indeed' => [
            'label' => 'Indeed',
            'default_company' => 'Indeed',
            'id_patterns' => ['#[?&]jk=([a-zA-Z0-9]+)#', '#/viewjob\?jk=([a-zA-Z0-9]+)#'],
        ],
        'glassdoor' => [
            'label' => 'Glassdoor',
            'default_company' => 'Glassdoor',
            'id_patterns' => ['#/job-listing/[^/]*-(\d+)#i', '#/Job/[^/]*-(\d+)#i', '#/job/(\d+)#i'],
        ],
    ];

    public static function searchKeywords(JobQuery $query): string
    {
        $keywords = trim($query->searchWas());
        if ($keywords === '') {
            return 'Mitarbeiter';
        }

        return $keywords;
    }

    public static function searchLocation(JobQuery $query): string
    {
        if ($query->city !== '') {
            return $query->city . ', Germany';
        }
        if ($query->bundesland !== '') {
            return $query->bundesland . ', Germany';
        }

        return 'Germany';
    }

    /**
     * @param list<string> $siteNames
     * @return array{hours_old: int, linkedin_fetch_description?: bool}
     */
    public static function scrapeArgs(JobQuery $query, array $siteNames): array
    {
        $args = [
            'site_name' => $siteNames,
            'search_term' => self::searchKeywords($query),
            'location' => self::searchLocation($query),
            'results_wanted' => self::RESULTS_WANTED,
            'hours_old' => $query->effectivePostedDays() === 1 ? 24 : (JobQuery::MAX_POSTED_DAYS * 24),
            'country_indeed' => 'Germany',
            'description_format' => 'markdown',
        ];
        if ($query->workMode === 'remote') {
            $args['is_remote'] = true;
        }
        if (in_array('linkedin', $siteNames, true)) {
            $args['linkedin_fetch_description'] = true;
        }

        return $args;
    }

    public static function pythonBinary(): ?string
    {
        $configured = trim((string) (getenv('JOBSPY_PYTHON') ?: ''));
        $candidates = array_values(array_filter([
            $configured !== '' ? $configured : null,
            dirname(__DIR__, 3) . '/.venv-jobspy/bin/python',
            'python3',
            'python',
        ]));
        foreach ($candidates as $bin) {
            if (str_contains($bin, '/') && !is_executable($bin)) {
                continue;
            }
            if (!str_contains($bin, '/')) {
                $which = trim((string) shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null'));
                if ($which === '' || !is_executable($which)) {
                    continue;
                }
                $bin = $which;
            }
            $code = 0;
            exec(escapeshellarg($bin) . ' -c ' . escapeshellarg('import jobspy') . ' 2>/dev/null', $out, $code);
            if ($code === 0) {
                return $bin;
            }
        }

        return null;
    }

    public static function scriptPath(): ?string
    {
        $root = dirname(__DIR__, 3);
        $local = $root . '/bin/jobspy_scrape.py';
        if (is_readable($local)) {
            return $local;
        }
        $pkg = $root . '/packages/php-jobspy/src/python/scrape.py';

        return is_readable($pkg) ? $pkg : null;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, error: ?string}
     */
    public static function runJobspy(string $payload): array
    {
        $python = self::pythonBinary();
        $script = self::scriptPath();
        if ($python === null || $script === null) {
            return ['rows' => [], 'error' => 'python-jobspy not installed'];
        }

        $cmd = escapeshellarg($python) . ' ' . escapeshellarg($script);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, dirname(__DIR__, 3));
        if (!is_resource($proc)) {
            return ['rows' => [], 'error' => 'Could not start Python JobSpy process.'];
        }
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code !== 0) {
            $err = trim($stderr);
            if ($err === '') {
                $err = trim($stdout);
            }
            if ($err !== '' && str_starts_with(ltrim($err), '{')) {
                $decoded = json_decode($err, true);
                if (is_array($decoded) && isset($decoded['error'])) {
                    $err = (string) $decoded['error'];
                }
            }

            return ['rows' => [], 'error' => $err !== '' ? $err : ('exit ' . $code)];
        }

        $data = json_decode($stdout, true);
        if (!is_array($data)) {
            return ['rows' => [], 'error' => 'JobSpy returned invalid JSON.'];
        }
        if (isset($data['error'])) {
            return ['rows' => [], 'error' => (string) $data['error']];
        }
        if ($data !== [] && !array_is_list($data)) {
            return ['rows' => [], 'error' => 'JobSpy JSON was not a list of jobs.'];
        }
        /** @var list<array<string, mixed>> $data */

        return ['rows' => $data, 'error' => null];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $wantedSources
     * @return list<JobListing>
     */
    public static function listingsFromRows(array $rows, JobQuery $query, array $wantedSources): array
    {
        $maxAge = JobQuery::MAX_POSTED_DAYS * 86400;
        $wanted = array_flip($wantedSources);
        $listings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $post = self::dtoFromRow($row);
            $sourceId = self::normalizeSiteId($post->site);
            if ($sourceId === null || !isset($wanted[$sourceId])) {
                continue;
            }
            $job = self::rowToListing($post, $query, $sourceId);
            if ($job === null) {
                continue;
            }
            if ($job->postedAt !== null) {
                $ts = strtotime($job->postedAt);
                if ($ts !== false && $ts < (time() - $maxAge)) {
                    continue;
                }
            }
            $listings[] = JobText::enrich($job);
        }

        return $listings;
    }

    public static function normalizeSiteId(string $site): ?string
    {
        $key = strtolower(trim(str_replace([' ', '-'], '_', $site)));

        return isset(self::BOARDS[$key]) ? $key : null;
    }

    /** @param array<string, mixed> $item */
    public static function dtoFromRow(array $item): JobPostDTO
    {
        $posted = $item['date_posted'] ?? null;
        if ($posted !== null && !is_string($posted)) {
            $posted = (string) $posted;
        }
        if (is_string($posted) && (strcasecmp($posted, 'nan') === 0 || strcasecmp($posted, 'null') === 0)) {
            $posted = null;
        }

        return new JobPostDTO(
            site: (string) ($item['site'] ?? ''),
            title: (string) ($item['title'] ?? ''),
            company: (string) ($item['company'] ?? ''),
            company_url: (string) ($item['company_url'] ?? ''),
            job_url: (string) ($item['job_url'] ?? $item['job_url_direct'] ?? ''),
            location: (string) ($item['location'] ?? ''),
            is_remote: (bool) ($item['is_remote'] ?? false),
            description: (string) ($item['description'] ?? ''),
            job_type: isset($item['job_type']) ? (string) $item['job_type'] : null,
            interval: isset($item['interval']) ? (string) $item['interval'] : null,
            min_amount: isset($item['min_amount']) && is_numeric($item['min_amount']) ? $item['min_amount'] + 0 : null,
            max_amount: isset($item['max_amount']) && is_numeric($item['max_amount']) ? $item['max_amount'] + 0 : null,
            currency: isset($item['currency']) ? (string) $item['currency'] : null,
            date_posted: $posted,
        );
    }

    public static function rowToListing(JobPostDTO $post, JobQuery $query, string $sourceId): ?JobListing
    {
        if (!isset(self::BOARDS[$sourceId])) {
            return null;
        }
        $board = self::BOARDS[$sourceId];

        $title = trim($post->title);
        $url = trim($post->job_url);
        if ($title === '' || $url === '') {
            return null;
        }

        $loc = trim($post->location);
        $city = '';
        $bundesland = '';
        $country = '';
        if ($loc !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $loc)), static fn(string $p): bool => $p !== ''));
            $city = $parts[0] ?? '';
            if (count($parts) >= 3) {
                $bundesland = $parts[1];
                $country = $parts[count($parts) - 1];
            } elseif (count($parts) === 2) {
                if (JobText::looksLikeGermany('', '', $parts[1], '')) {
                    $country = $parts[1];
                } else {
                    $bundesland = $parts[1];
                }
            }
        }
        if ($query->city !== '' && $city === '') {
            $city = $query->city;
        }
        if ($query->bundesland !== '' && $bundesland === '') {
            $bundesland = $query->bundesland;
        }
        if ($country === '' && JobText::looksLikeGermany($city, $bundesland, '', $loc . ' Germany')) {
            $country = 'Germany';
        }

        if (JobText::isForeignPrimaryLocation($city, $country !== '' ? $country : $loc, $title)) {
            return null;
        }
        if ($country !== '' && !JobText::looksLikeGermany($city, $bundesland, $country, $loc)
            && !preg_match('/\b(germany|deutschland|de)\b/iu', $country)) {
            return null;
        }
        if ($country === '' && $loc !== ''
            && !JobText::looksLikeGermany($city, $bundesland, '', $loc)
            && !preg_match('/\b(germany|deutschland|berlin|münchen|munich|hamburg|köln|cologne)\b/iu', $loc)) {
            return null;
        }
        if ($country === '') {
            $country = 'Germany';
        }

        $posted = null;
        $rawPosted = trim((string) ($post->date_posted ?? ''));
        if ($rawPosted !== '') {
            $ts = strtotime($rawPosted);
            if ($ts !== false) {
                $posted = date('Y-m-d', $ts);
            } else {
                $posted = JobText::parsePostedDate($rawPosted);
            }
        }

        $desc = trim($post->description);
        if (strcasecmp($desc, 'nan') === 0 || strcasecmp($desc, 'null') === 0) {
            $desc = '';
        }
        $blob = $title . ' ' . $loc . ' ' . $desc . ' ' . (string) ($post->job_type ?? '');
        $workMode = $post->is_remote ? 'remote' : JobText::workMode($blob);
        $employment = JobText::employment($blob, (string) ($post->job_type ?? ''));

        $salary = '';
        if ($post->min_amount !== null || $post->max_amount !== null) {
            $cur = trim((string) ($post->currency ?? ''));
            $min = $post->min_amount !== null ? (string) $post->min_amount : '';
            $max = $post->max_amount !== null ? (string) $post->max_amount : '';
            $salary = trim($cur . ' ' . ($min !== '' && $max !== '' ? "{$min}–{$max}" : ($min !== '' ? $min : $max)));
        }

        $externalId = self::externalIdFromUrl($url, $sourceId);

        $job = new JobListing(
            $sourceId,
            $externalId,
            $title,
            trim($post->company) !== '' ? trim($post->company) : $board['default_company'],
            $city,
            $bundesland,
            $country,
            $workMode,
            $employment,
            JobText::offerType($blob),
            [],
            [],
            $salary,
            $posted,
            $url,
            $desc,
        );
        $job->applyUrl = $url;

        return $job;
    }

    public static function externalIdFromUrl(string $url, string $sourceId): string
    {
        $patterns = self::BOARDS[$sourceId]['id_patterns'] ?? [];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                for ($i = 1; $i < count($m); $i++) {
                    if (($m[$i] ?? '') !== '') {
                        return $m[$i];
                    }
                }
            }
        }

        return hash('sha256', $url);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function jsonLdJobPosting(string $html): ?array
    {
        if (!preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $html, $blocks)) {
            return null;
        }
        foreach ($blocks[1] as $raw) {
            $data = json_decode(html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (!is_array($data)) {
                continue;
            }
            $type = $data['@type'] ?? '';
            if ($type === 'JobPosting' || (is_array($type) && in_array('JobPosting', $type, true))) {
                return $data;
            }
            if (isset($data['@graph']) && is_array($data['@graph'])) {
                foreach ($data['@graph'] as $node) {
                    if (!is_array($node)) {
                        continue;
                    }
                    $nodeType = $node['@type'] ?? '';
                    if ($nodeType === 'JobPosting' || (is_array($nodeType) && in_array('JobPosting', $nodeType, true))) {
                        return $node;
                    }
                }
            }
        }

        return null;
    }

    public static function stripEmbeddedCss(string $text): string
    {
        $text = preg_replace('/\.[a-z0-9_-]+\{[^}]*\}/iu', '', $text) ?? $text;
        $text = preg_replace('/@media[^{]+\{[^}]*\}/iu', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** @return list<string> */
    public static function browserHeaders(): array
    {
        return [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ];
    }
}
