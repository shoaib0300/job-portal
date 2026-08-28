<?php

declare(strict_types=1);

namespace KaamFit\Jobs\Sources;

use App;
use Freeworld\PhpJobspy\DTO\JobPostDTO;
use KaamFit\Jobs\JobCache;
use KaamFit\Jobs\JobHttp;
use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobText;

/**
 * LinkedIn via php-jobspy / python-jobspy (https://github.com/alexseif/php-jobspy).
 * Uses JobPostDTO from that package and bin/jobspy_scrape.py (JobSpy).
 */
final class LinkedInSource
{
    private const RESULTS_WANTED = 50;

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        $python = self::pythonBinary();
        $script = self::scriptPath();
        if ($python === null || $script === null) {
            return [
                'listings' => [],
                'notices' => [
                    'LinkedIn needs python-jobspy. Run `ddev restart` after the web-build Dockerfile installs Python, or: pip install python-jobspy',
                ],
            ];
        }

        $keywords = trim($query->searchWas());
        if ($keywords === '') {
            // Broad DE term — empty ingest must not collapse to “Software” only.
            $keywords = 'Mitarbeiter';
        }
        $location = $query->city !== ''
            ? $query->city . ', Germany'
            : ($query->bundesland !== '' ? $query->bundesland . ', Germany' : 'Germany');

        $args = [
            'site_name' => ['linkedin'],
            'search_term' => $keywords,
            'location' => $location,
            'results_wanted' => self::RESULTS_WANTED,
            'hours_old' => $query->effectivePostedDays() === 1 ? 24 : (JobQuery::MAX_POSTED_DAYS * 24),
            'country_indeed' => 'Germany',
            'linkedin_fetch_description' => true,
            'description_format' => 'markdown',
        ];
        if ($query->workMode === 'remote') {
            $args['is_remote'] = true;
        }

        $payload = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return ['listings' => [], 'notices' => ['LinkedIn JobSpy could not encode the search.']];
        }

        $result = self::runJobspy($python, $script, $payload);
        if ($result['error'] !== null) {
            return [
                'listings' => [],
                'notices' => [
                    App::isDev()
                        ? ('LinkedIn JobSpy: ' . $result['error'])
                        : 'LinkedIn JobSpy failed. Try again or check Python / python-jobspy install.',
                ],
            ];
        }

        $rows = $result['rows'];
        if ($rows === []) {
            return [
                'listings' => [],
                'notices' => ['LinkedIn JobSpy returned no jobs for this search.'],
            ];
        }

        $maxAge = JobQuery::MAX_POSTED_DAYS * 86400;
        $listings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $post = self::dtoFromRow($row);
            $job = self::fromDto($post, $query);
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

        return [
            'listings' => $listings,
            'notices' => $listings === []
                ? ['LinkedIn JobSpy returned posts, but none passed Germany / ' . JobQuery::MAX_POSTED_DAYS . '-day filters.']
                : [],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, error: ?string}
     */
    private static function runJobspy(string $python, string $script, string $payload): array
    {
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
        // list of job dicts
        if ($data !== [] && !array_is_list($data)) {
            return ['rows' => [], 'error' => 'JobSpy JSON was not a list of jobs.'];
        }
        /** @var list<array<string, mixed>> $data */
        return ['rows' => $data, 'error' => null];
    }

    /** @param array<string, mixed> $item */
    private static function dtoFromRow(array $item): JobPostDTO
    {
        $posted = $item['date_posted'] ?? null;
        if ($posted !== null && !is_string($posted)) {
            $posted = (string) $posted;
        }
        if (is_string($posted) && (strcasecmp($posted, 'nan') === 0 || strcasecmp($posted, 'null') === 0)) {
            $posted = null;
        }

        return new JobPostDTO(
            site: (string) ($item['site'] ?? 'linkedin'),
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

    private static function pythonBinary(): ?string
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

    private static function scriptPath(): ?string
    {
        $root = dirname(__DIR__, 3);
        $local = $root . '/bin/jobspy_scrape.py';
        if (is_readable($local)) {
            return $local;
        }
        $pkg = $root . '/packages/php-jobspy/src/python/scrape.py';
        return is_readable($pkg) ? $pkg : null;
    }

    private static function fromDto(JobPostDTO $post, JobQuery $query): ?JobListing
    {
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

        $externalId = '';
        if (preg_match('#/jobs/view/(?:[\w-]+-)?(\d+)#', $url, $m)) {
            $externalId = $m[1];
        }
        if ($externalId === '') {
            $externalId = hash('sha256', $url);
        }

        $job = new JobListing(
            'linkedin',
            $externalId,
            $title,
            trim($post->company) !== '' ? trim($post->company) : 'LinkedIn',
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

    public static function details(string $externalId): ?JobListing
    {
        $cached = JobCache::getListing('linkedin', $externalId);
        if ($cached === null) {
            return null;
        }
        if (trim($cached->description) !== '' && $cached->postedAt !== null && $cached->postedAt !== '') {
            return $cached;
        }

        $numericId = $externalId;
        if (preg_match('/(\d{8,})/', $externalId, $m)) {
            $numericId = $m[1];
        } elseif (preg_match('#/jobs/view/(?:[\w-]+-)?(\d+)#', $cached->url, $m)) {
            $numericId = $m[1];
        }

        $desc = self::fetchGuestDescription($numericId);
        if ($desc !== '') {
            $cached->description = $desc;
        }
        if (($cached->postedAt === null || $cached->postedAt === '') && $desc !== '') {
            $parsed = JobText::parsePostedDate($desc);
            if ($parsed !== null) {
                $cached->postedAt = $parsed;
            }
        }
        if (trim($cached->description) !== '' || ($cached->postedAt !== null && $cached->postedAt !== '')) {
            JobCache::putListing($cached);
        }
        return $cached;
    }

    private static function fetchGuestDescription(string $jobId): string
    {
        if (!ctype_digit($jobId)) {
            return '';
        }
        $url = 'https://www.linkedin.com/jobs-guest/jobs/api/jobPosting/' . $jobId;
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ];
        $html = JobHttp::get($url, $headers, 12);
        if ($html === null || strlen($html) < 200) {
            $html = JobHttp::unlockHtml($url, 28);
        }
        if ($html === null || strlen($html) < 200) {
            return '';
        }
        if (preg_match(
            '#class="[^"]*(?:description__text|show-more-less-html__markup|jobs-description)[^"]*"[^>]*>(.*?)</div>#is',
            $html,
            $m
        )) {
            $text = JobText::stripHtml($m[1]);
            if (mb_strlen($text) > 80) {
                return mb_substr($text, 0, 12000);
            }
        }
        $plain = JobText::stripHtml($html);
        if (mb_strlen($plain) > 120) {
            return mb_substr($plain, 0, 12000);
        }
        return '';
    }
}
