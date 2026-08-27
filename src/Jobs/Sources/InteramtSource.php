<?php

declare(strict_types=1);

namespace KaamMilo\Jobs\Sources;

use App;
use KaamMilo\Jobs\JobCache;
use KaamMilo\Jobs\JobHttp;
use KaamMilo\Jobs\JobListing;
use KaamMilo\Jobs\JobQuery;
use KaamMilo\Jobs\JobText;


final class InteramtSource
{
    /**
     * @return array{listings: list<JobListing>, notice: ?string}
     */
    public static function search(JobQuery $query): array
    {
        $requests = [
            'praktikum' => [
                'url' => 'https://gate.interamt.de/interamtApi/v1/api/Export/Praktikum',
                'headers' => ['Accept: application/json'],
            ],
            'stellen' => [
                'url' => 'https://gate.interamt.de/interamtApi/v1/api/Stellenangebote',
                'headers' => ['Accept: application/json'],
            ],
        ];
        $bodies = JobHttp::multiGet($requests, 8);
        $listings = [];
        foreach (['praktikum', 'stellen'] as $key) {
            $raw = $bodies[$key] ?? null;
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                continue;
            }
            $rows = $data;
            if (isset($data['Stellenangebote']) && is_array($data['Stellenangebote'])) {
                $rows = $data['Stellenangebote'];
            } elseif (isset($data['items']) && is_array($data['items'])) {
                $rows = $data['items'];
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $job = self::fromRow($row);
                if ($job !== null) {
                    $listings[] = JobText::enrich($job);
                }
            }
            if ($listings !== []) {
                break;
            }
        }

        if ($listings === []) {
            return [
                'listings' => [],
                'notice' => 'Interamt public list is not open without an employer contract. Public-sector roles still appear via Arbeitsagentur.',
            ];
        }

        $needle = mb_strtolower($query->searchWas() . ' ' . $query->whereText());
        if (trim($needle) !== '') {
            $tokens = preg_split('/\s+/u', trim($needle)) ?: [];
            $listings = array_values(array_filter(
                $listings,
                static function (JobListing $job) use ($tokens): bool {
                    $hay = mb_strtolower($job->title . ' ' . $job->company . ' ' . $job->city);
                    foreach ($tokens as $tok) {
                        if ($tok !== '' && mb_strpos($hay, $tok) !== false) {
                            return true;
                        }
                    }
                    return false;
                }
            ));
        }

        return ['listings' => $listings, 'notice' => null];
    }

    public static function details(string $externalId): ?JobListing
    {
        return JobCache::getListing('public_sector', $externalId);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): ?JobListing
    {
        $id = (string) ($row['StellenangebotId'] ?? $row['Id'] ?? $row['id'] ?? '');
        $title = (string) ($row['Stellenbezeichnung'] ?? $row['Titel'] ?? $row['title'] ?? '');
        if ($id === '' || $title === '') {
            return null;
        }
        $company = (string) ($row['Behoerde'] ?? $row['BehoerdeName'] ?? $row['Arbeitgeber'] ?? 'Öffentlicher Dienst');
        $city = (string) ($row['Ort'] ?? $row['Dienstort'] ?? $row['Stadt'] ?? '');
        $url = (string) ($row['Url'] ?? $row['Link'] ?? '');
        if ($url === '') {
            $url = 'https://www.interamt.de/koop/app/stelle?id=' . rawurlencode($id);
        }
        $posted = (string) ($row['Ausschreibungsdatum'] ?? $row['Datum'] ?? '');
        $desc = JobText::stripHtml((string) ($row['Beschreibung'] ?? $row['Stellenbeschreibung'] ?? ''));

        return new JobListing(
            'public_sector',
            $id,
            $title,
            $company,
            $city,
            (string) ($row['Bundesland'] ?? ''),
            'Germany',
            'unknown',
            'unknown',
            JobText::offerType($title . ' ' . $desc),
            [],
            [],
            (string) ($row['Verguetung'] ?? ''),
            $posted !== '' ? substr($posted, 0, 10) : null,
            $url,
            $desc,
        );
    }
}
