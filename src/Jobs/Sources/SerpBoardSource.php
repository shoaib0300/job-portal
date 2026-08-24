<?php

declare(strict_types=1);

final class SerpBoardSource
{
    public const BOARDS = [
        'linkedin' => ['label' => 'LinkedIn', 'site' => 'site:linkedin.com/jobs'],
        'indeed' => ['label' => 'Indeed', 'site' => 'site:indeed.de OR site:de.indeed.com'],
        'stepstone' => ['label' => 'StepStone', 'site' => 'site:stepstone.de'],
        'xing' => ['label' => 'XING', 'site' => 'site:xing.com/jobs'],
        'jobware' => ['label' => 'Jobware', 'site' => 'site:jobware.de'],
        'glassdoor' => ['label' => 'Glassdoor', 'site' => 'site:glassdoor.de/Job OR site:www.glassdoor.com/job-listing'],
    ];

    public static function token(): string
    {
        return trim((string) (getenv('BRIGHT_DATA_API_TOKEN') ?: ''));
    }

    public static function configured(): bool
    {
        return self::token() !== '';
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
        if (!self::configured()) {
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
        $requests = [];
        foreach ($wanted as $id) {
            $q = trim(self::BOARDS[$id]['site'] . ' ' . $was . ' ' . $where . ' Germany jobs');
            $google = 'https://www.google.com/search?q=' . rawurlencode($q) . '&num=10&hl=de&gl=de&brd_json=1';
            $requests[$id] = $google;
        }

        $listings = [];
        $notices = [];
        $zone = trim((string) (getenv('BRIGHT_DATA_ZONE') ?: 'web_unlocker1'));
        foreach ($requests as $id => $googleUrl) {
            $data = JobHttp::postJson(
                'https://api.brightdata.com/request',
                [
                    'zone' => $zone,
                    'url' => $googleUrl,
                    'format' => 'json',
                ],
                ['Authorization: Bearer ' . self::token()],
                18
            );
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
                    null,
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
