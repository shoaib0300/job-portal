<?php

declare(strict_types=1);

final class AtsBoardSource
{
    /** @return list<array{type:string,slug:string,label:string}> */
    public static function boards(): array
    {
        $defaults = [
            ['type' => 'greenhouse', 'slug' => 'n26', 'label' => 'N26'],
            ['type' => 'greenhouse', 'slug' => 'celonis', 'label' => 'Celonis'],
            ['type' => 'greenhouse', 'slug' => 'trade-republic', 'label' => 'Trade Republic'],
            ['type' => 'personio', 'slug' => 'personio', 'label' => 'Personio'],
            ['type' => 'personio', 'slug' => 'getyourguide', 'label' => 'GetYourGuide'],
            ['type' => 'greenhouse', 'slug' => 'contentful', 'label' => 'Contentful'],
        ];
        $extra = trim((string) (App::setting('job_ats_boards', '') ?: ''));
        if ($extra === '') {
            return $defaults;
        }
        foreach (preg_split('/\R/', $extra) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode(':', $line, 3));
            if (count($parts) < 2) {
                continue;
            }
            $type = strtolower($parts[0]);
            if (!in_array($type, ['personio', 'greenhouse'], true)) {
                continue;
            }
            $defaults[] = [
                'type' => $type,
                'slug' => $parts[1],
                'label' => $parts[2] ?? $parts[1],
            ];
        }
        return $defaults;
    }

    /**
     * @return array{listings: list<JobListing>, notice: ?string}
     */
    public static function search(JobQuery $query, bool $studentBias = false): array
    {
        $requests = [];
        foreach (self::boards() as $board) {
            $key = $board['type'] . ':' . $board['slug'];
            if ($board['type'] === 'greenhouse') {
                $requests[$key] = [
                    'url' => 'https://boards-api.greenhouse.io/v1/boards/' . rawurlencode($board['slug']) . '/jobs?content=true',
                ];
            } else {
                $requests[$key] = [
                    'url' => 'https://' . rawurlencode($board['slug']) . '.jobs.personio.de/xml?language=en',
                ];
            }
        }
        $bodies = JobHttp::multiGet($requests, 10);
        $listings = [];
        $ok = 0;
        foreach (self::boards() as $board) {
            $key = $board['type'] . ':' . $board['slug'];
            $body = $bodies[$key] ?? null;
            if ($body === null) {
                continue;
            }
            $ok++;
            if ($board['type'] === 'greenhouse') {
                $listings = array_merge($listings, self::parseGreenhouse($body, $board['slug']));
            } else {
                $listings = array_merge($listings, self::parsePersonio($body, $board['slug']));
            }
        }

        $needle = mb_strtolower(trim($query->searchWas() . ($studentBias ? ' werkstudent praktikum hiwi' : '')));
        if ($needle !== '') {
            $tokens = preg_split('/\s+/u', $needle) ?: [];
            $listings = array_values(array_filter(
                $listings,
                static function (JobListing $job) use ($tokens): bool {
                    $hay = mb_strtolower($job->title . ' ' . $job->company . ' ' . $job->city . ' ' . $job->description);
                    foreach ($tokens as $tok) {
                        if ($tok !== '' && mb_strpos($hay, $tok) !== false) {
                            return true;
                        }
                    }
                    return $tokens === [];
                }
            ));
        }

        if ($query->city !== '') {
            $city = mb_strtolower($query->city);
            $listings = array_values(array_filter(
                $listings,
                static fn(JobListing $job): bool => $job->city === '' || mb_stripos($job->city, $city) !== false
            ));
        }

        $notice = $ok === 0 ? 'Career-page boards did not respond.' : null;
        return ['listings' => $listings, 'notice' => $notice];
    }

    public static function details(string $externalId): ?JobListing
    {
        return JobCache::getListing('career', $externalId)
            ?? JobCache::getListing('university', $externalId);
    }

    /** @return list<JobListing> */
    private static function parseGreenhouse(string $body, string $slug): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [];
        }
        $jobs = $data['jobs'] ?? [];
        if (!is_array($jobs)) {
            return [];
        }
        $out = [];
        foreach ($jobs as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $url = (string) ($row['absolute_url'] ?? '');
            if ($id === '' || $title === '') {
                continue;
            }
            $loc = '';
            if (isset($row['location']) && is_array($row['location'])) {
                $loc = (string) ($row['location']['name'] ?? '');
            }
            $desc = JobText::stripHtml((string) ($row['content'] ?? ''));
            $company = (string) ($row['company_name'] ?? $slug);
            $job = new JobListing(
                'career',
                'gh:' . $slug . ':' . $id,
                $title,
                $company !== '' ? $company : $slug,
                self::cityFromLocation($loc),
                '',
                self::countryFromLocation($loc),
                'unknown',
                'unknown',
                'job',
                [],
                [],
                '',
                isset($row['updated_at']) ? substr((string) $row['updated_at'], 0, 10) : null,
                $url,
                $desc,
            );
            $out[] = JobText::enrich($job);
        }
        return $out;
    }

    /** @return list<JobListing> */
    private static function parsePersonio(string $body, string $slug): array
    {
        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            return [];
        }
        $out = [];
        foreach ($xml->position as $pos) {
            $id = trim((string) ($pos->id ?? ''));
            $title = trim((string) ($pos->name ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }
            $office = trim((string) ($pos->office ?? ''));
            $descParts = [];
            if (isset($pos->jobDescriptions->jobDescription)) {
                foreach ($pos->jobDescriptions->jobDescription as $block) {
                    $descParts[] = JobText::stripHtml((string) ($block->value ?? ''));
                }
            }
            $schedule = trim((string) ($pos->schedule ?? ''));
            $emp = trim((string) ($pos->employmentType ?? ''));
            $created = trim((string) ($pos->createdAt ?? ''));
            $url = 'https://' . $slug . '.jobs.personio.de/job/' . rawurlencode($id);
            $job = new JobListing(
                'career',
                'pn:' . $slug . ':' . $id,
                $title,
                $slug,
                $office,
                '',
                'Germany',
                JobText::workMode($title . ' ' . $schedule),
                JobText::employment($title . ' ' . $emp . ' ' . $schedule, $schedule),
                JobText::offerType($title . ' ' . $emp),
                [],
                [],
                '',
                $created !== '' ? substr($created, 0, 10) : null,
                $url,
                implode("\n\n", $descParts),
            );
            $out[] = JobText::enrich($job);
        }
        return $out;
    }

    private static function cityFromLocation(string $loc): string
    {
        $loc = trim($loc);
        if ($loc === '') {
            return '';
        }
        $parts = array_map('trim', explode(',', $loc));
        return $parts[0] ?? '';
    }

    private static function countryFromLocation(string $loc): string
    {
        if (stripos($loc, 'Germany') !== false || stripos($loc, 'Deutschland') !== false) {
            return 'Germany';
        }
        $parts = array_map('trim', explode(',', $loc));
        return $parts !== [] ? (string) end($parts) : 'Germany';
    }
}
