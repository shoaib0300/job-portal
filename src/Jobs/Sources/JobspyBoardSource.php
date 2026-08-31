<?php

declare(strict_types=1);

namespace KaamFit\Jobs\Sources;

use App;
use KaamFit\Jobs\JobCache;
use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobStore;

/**
 * Indeed + Glassdoor via python-jobspy (VPS — no Bright Data).
 */
final class JobspyBoardSource
{
    /** @var list<string> */
    public const BOARD_IDS = ['indeed', 'glassdoor'];

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        $wanted = array_values(array_intersect(self::BOARD_IDS, $query->sources));
        if ($wanted === []) {
            return ['listings' => [], 'notices' => []];
        }

        if (JobspySupport::pythonBinary() === null || JobspySupport::scriptPath() === null) {
            $labels = array_map(
                static fn(string $id): string => JobspySupport::BOARDS[$id]['label'],
                $wanted
            );

            return [
                'listings' => [],
                'notices' => [
                    implode(' / ', $labels) . ' need python-jobspy. Run `ddev exec bash bin/install_jobspy.sh` or set JOBSPY_PYTHON.',
                ],
            ];
        }

        $args = JobspySupport::scrapeArgs($query, $wanted);
        $payload = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return ['listings' => [], 'notices' => ['JobSpy could not encode the search payload.']];
        }

        $result = JobspySupport::runJobspy($payload);
        if ($result['error'] !== null) {
            return [
                'listings' => [],
                'notices' => [
                    App::isDev()
                        ? ('JobSpy: ' . $result['error'])
                        : 'Indeed / Glassdoor JobSpy failed. Check Python / python-jobspy on the server.',
                ],
            ];
        }

        $listings = JobspySupport::listingsFromRows($result['rows'], $query, $wanted);
        if ($listings === [] && $result['rows'] !== []) {
            return [
                'listings' => [],
                'notices' => [
                    'JobSpy returned posts, but none passed Germany / '
                    . JobQuery::MAX_POSTED_DAYS . '-day filters for Indeed / Glassdoor.',
                ],
            ];
        }
        if ($listings === []) {
            return [
                'listings' => [],
                'notices' => ['Indeed / Glassdoor JobSpy returned no jobs for this search.'],
            ];
        }

        return ['listings' => $listings, 'notices' => []];
    }

    public static function details(string $source, string $externalId): ?JobListing
    {
        if (!in_array($source, self::BOARD_IDS, true)) {
            return null;
        }

        $cached = JobCache::getListing($source, $externalId)
            ?? JobStore::get($source, $externalId);
        if ($cached === null) {
            return null;
        }
        if (trim($cached->description) !== '') {
            return $cached;
        }

        return $cached;
    }
}
