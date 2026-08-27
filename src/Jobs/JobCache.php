<?php

declare(strict_types=1);

namespace KaamMilo\Jobs;

use App;
use Db;


final class JobCache
{
    public const SEARCH_TTL = 900;
    public const JOB_TTL = 3600;

    /** Drop cached rows / jobs older than this (matches JobQuery::MAX_POSTED_DAYS). */
    public const MAX_JOB_AGE_DAYS = 14;

    private static bool $purgedThisRequest = false;

    public static function ensureSchema(): void
    {
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS job_search_cache (
              query_hash VARCHAR(80) NOT NULL PRIMARY KEY,
              payload MEDIUMTEXT NOT NULL,
              fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        self::purgeStale();
    }

    /**
     * Remove expired cache rows and job payloads older than MAX_JOB_AGE_DAYS.
     * Runs at most once per request.
     */
    public static function purgeStale(): void
    {
        if (self::$purgedThisRequest) {
            return;
        }
        self::$purgedThisRequest = true;
        $pdo = Db::pdo();
        $days = self::MAX_JOB_AGE_DAYS;

        // Soft-TTL leftovers + anything not touched in a week.
        $pdo->exec(
            'DELETE FROM job_search_cache
             WHERE fetched_at < (NOW() - INTERVAL ' . (int) $days . ' DAY)'
        );

        // Individual job rows with an old posted_at (MySQL 5.7 JSON).
        try {
            $pdo->exec(
                "DELETE FROM job_search_cache
                 WHERE JSON_EXTRACT(payload, '$.posted_at') IS NOT NULL
                   AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.posted_at')) <> ''
                   AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.posted_at')) <
                       DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL {$days} DAY), '%Y-%m-%d')"
            );
        } catch (Throwable) {
            // JSON functions unavailable — fall through to PHP sweep.
        }

        // Search result blobs: drop row if every listing is old / strip old listings.
        self::purgeOldListingsFromSearchRows($days);
    }

    private static function purgeOldListingsFromSearchRows(int $days): void
    {
        $cutoff = time() - ($days * 86400);
        $stmt = Db::pdo()->query(
            "SELECT query_hash, payload FROM job_search_cache
             WHERE payload LIKE '%\"listings\"%'"
        );
        if ($stmt === false) {
            return;
        }
        $upd = Db::pdo()->prepare(
            'UPDATE job_search_cache SET payload = ?, fetched_at = fetched_at WHERE query_hash = ?'
        );
        $del = Db::pdo()->prepare('DELETE FROM job_search_cache WHERE query_hash = ?');
        while ($row = $stmt->fetch()) {
            $data = json_decode((string) $row['payload'], true);
            if (!is_array($data) || !isset($data['listings']) || !is_array($data['listings'])) {
                continue;
            }
            $kept = [];
            foreach ($data['listings'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $posted = (string) ($item['posted_at'] ?? '');
                if ($posted !== '') {
                    $ts = strtotime($posted);
                    if ($ts !== false && $ts < $cutoff) {
                        continue;
                    }
                }
                $kept[] = $item;
            }
            if ($kept === []) {
                $del->execute([(string) $row['query_hash']]);
                continue;
            }
            if (count($kept) !== count($data['listings'])) {
                $data['listings'] = $kept;
                $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($json)) {
                    $upd->execute([$json, (string) $row['query_hash']]);
                }
            }
        }
    }

    /**
     * Keep keys within VARCHAR(80). Career/ATS external IDs are often full URLs.
     */
    private static function storageKey(string $key): string
    {
        if (strlen($key) <= 80) {
            return $key;
        }
        return 'h:' . hash('sha256', $key);
    }

    private static function isPostedTooOld(?string $postedAt): bool
    {
        if ($postedAt === null || $postedAt === '') {
            return false;
        }
        $ts = strtotime($postedAt);
        if ($ts === false) {
            return false;
        }
        return $ts < (time() - (self::MAX_JOB_AGE_DAYS * 86400));
    }

    private static function deleteKey(string $key): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM job_search_cache WHERE query_hash = ?');
        $stmt->execute([self::storageKey($key)]);
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key, int $ttl): ?array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT payload, fetched_at FROM job_search_cache WHERE query_hash = ? LIMIT 1'
        );
        $stmt->execute([self::storageKey($key)]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $age = time() - strtotime((string) $row['fetched_at']);
        if ($age > $ttl) {
            self::deleteKey($key);
            return null;
        }
        $data = json_decode((string) $row['payload'], true);
        if (!is_array($data)) {
            return null;
        }
        // Single job payload
        if (isset($data['posted_at']) && self::isPostedTooOld(is_string($data['posted_at']) ? $data['posted_at'] : null)) {
            self::deleteKey($key);
            return null;
        }
        // Search payload
        if (isset($data['listings']) && is_array($data['listings'])) {
            $before = count($data['listings']);
            $kept = [];
            foreach ($data['listings'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $posted = $item['posted_at'] ?? null;
                if (self::isPostedTooOld(is_string($posted) ? $posted : null)) {
                    continue;
                }
                $kept[] = $item;
            }
            $data['listings'] = $kept;
            if ($data['listings'] === []) {
                self::deleteKey($key);
                return null;
            }
            if (count($data['listings']) !== $before) {
                self::put($key, $data);
            }
        }
        return $data;
    }

    /** @param array<string, mixed> $payload */
    public static function put(string $key, array $payload): void
    {
        self::ensureSchema();
        if (isset($payload['posted_at']) && self::isPostedTooOld(is_string($payload['posted_at']) ? $payload['posted_at'] : null)) {
            self::deleteKey($key);
            return;
        }
        if (isset($payload['listings']) && is_array($payload['listings'])) {
            $kept = [];
            foreach ($payload['listings'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $posted = $item['posted_at'] ?? null;
                if (self::isPostedTooOld(is_string($posted) ? $posted : null)) {
                    continue;
                }
                $kept[] = $item;
            }
            $payload['listings'] = $kept;
            if ($payload['listings'] === []) {
                self::deleteKey($key);
                return;
            }
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }
        $stmt = Db::pdo()->prepare(
            'INSERT INTO job_search_cache (query_hash, payload) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), fetched_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([self::storageKey($key), $json]);
    }

    public static function putListing(JobListing $job): void
    {
        if (self::isPostedTooOld($job->postedAt)) {
            self::deleteKey($job->cacheKey());
            return;
        }
        self::put($job->cacheKey(), $job->toArray());
    }

    public static function getListing(string $source, string $externalId): ?JobListing
    {
        $data = self::get('job:' . $source . ':' . $externalId, self::JOB_TTL);
        if ($data === null) {
            return null;
        }
        $job = JobListing::fromArray($data);
        if ($job->source === '' || $job->externalId === '') {
            return null;
        }
        if (self::isPostedTooOld($job->postedAt)) {
            self::deleteKey('job:' . $source . ':' . $externalId);
            return null;
        }
        return $job;
    }
}
