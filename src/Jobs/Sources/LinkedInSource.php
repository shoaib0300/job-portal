<?php

declare(strict_types=1);

namespace KaamFit\Jobs\Sources;

use App;
use KaamFit\Jobs\JobCache;
use KaamFit\Jobs\JobHttp;
use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobText;

/**
 * LinkedIn via php-jobspy / python-jobspy (https://github.com/alexseif/php-jobspy).
 */
final class LinkedInSource
{
    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        if (JobspySupport::pythonBinary() === null || JobspySupport::scriptPath() === null) {
            return [
                'listings' => [],
                'notices' => [
                    'LinkedIn needs python-jobspy. Run `ddev restart` after the web-build Dockerfile installs Python, or: pip install python-jobspy',
                ],
            ];
        }

        $args = JobspySupport::scrapeArgs($query, ['linkedin']);
        $payload = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return ['listings' => [], 'notices' => ['LinkedIn JobSpy could not encode the search.']];
        }

        $result = JobspySupport::runJobspy($payload);
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

        $listings = JobspySupport::listingsFromRows($result['rows'], $query, ['linkedin']);
        if ($listings === [] && $result['rows'] !== []) {
            return [
                'listings' => [],
                'notices' => [
                    'LinkedIn JobSpy returned posts, but none passed Germany / '
                    . JobQuery::MAX_POSTED_DAYS . '-day filters.',
                ],
            ];
        }
        if ($listings === []) {
            return [
                'listings' => [],
                'notices' => ['LinkedIn JobSpy returned no jobs for this search.'],
            ];
        }

        return ['listings' => $listings, 'notices' => []];
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
