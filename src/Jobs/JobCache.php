<?php

declare(strict_types=1);

final class JobCache
{
    public const SEARCH_TTL = 900;
    public const JOB_TTL = 3600;

    public static function ensureSchema(): void
    {
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS job_search_cache (
              query_hash VARCHAR(80) NOT NULL PRIMARY KEY,
              payload MEDIUMTEXT NOT NULL,
              fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key, int $ttl): ?array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT payload, fetched_at FROM job_search_cache WHERE query_hash = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $age = time() - strtotime((string) $row['fetched_at']);
        if ($age > $ttl) {
            return null;
        }
        $data = json_decode((string) $row['payload'], true);
        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $payload */
    public static function put(string $key, array $payload): void
    {
        self::ensureSchema();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }
        $stmt = Db::pdo()->prepare(
            'INSERT INTO job_search_cache (query_hash, payload) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), fetched_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$key, $json]);
    }

    public static function putListing(JobListing $job): void
    {
        self::put($job->cacheKey(), $job->toArray());
    }

    public static function getListing(string $source, string $externalId): ?JobListing
    {
        $data = self::get('job:' . $source . ':' . $externalId, self::JOB_TTL);
        if ($data === null) {
            return null;
        }
        $job = JobListing::fromArray($data);
        return $job->source !== '' && $job->externalId !== '' ? $job : null;
    }
}
