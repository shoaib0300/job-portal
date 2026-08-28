<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

use PDO;

/**
 * Persisted ingest run history for Super Admin.
 */
final class JobIngestLog
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        \Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS job_ingest_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NOT NULL,
                trigger_via VARCHAR(32) NOT NULL DEFAULT \'cron\',
                seeds INT UNSIGNED NOT NULL DEFAULT 0,
                fetched INT UNSIGNED NOT NULL DEFAULT 0,
                inserted_count INT UNSIGNED NOT NULL DEFAULT 0,
                updated_count INT UNSIGNED NOT NULL DEFAULT 0,
                purged INT UNSIGNED NOT NULL DEFAULT 0,
                total_after INT UNSIGNED NOT NULL DEFAULT 0,
                duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
                by_source MEDIUMTEXT NULL,
                errors MEDIUMTEXT NULL,
                notes MEDIUMTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ingest_finished (finished_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        self::$ready = true;
    }

    /**
     * @param array{
     *   started_at: string,
     *   finished_at: string,
     *   trigger: string,
     *   seeds: int,
     *   fetched: int,
     *   inserted: int,
     *   updated: int,
     *   purged: int,
     *   total_after: int,
     *   duration_sec: int,
     *   by_source: array<string, array{inserted?:int, updated?:int, upserted?:int}>,
     *   errors: list<string>,
     *   notes?: string
     * } $row
     */
    public static function record(array $row): int
    {
        self::ensureSchema();
        $stmt = \Db::pdo()->prepare(
            'INSERT INTO job_ingest_logs (
                started_at, finished_at, trigger_via, seeds, fetched,
                inserted_count, updated_count, purged, total_after, duration_sec,
                by_source, errors, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $row['started_at'],
            $row['finished_at'],
            mb_substr($row['trigger'], 0, 32),
            max(0, (int) $row['seeds']),
            max(0, (int) $row['fetched']),
            max(0, (int) $row['inserted']),
            max(0, (int) $row['updated']),
            max(0, (int) $row['purged']),
            max(0, (int) $row['total_after']),
            max(0, (int) $row['duration_sec']),
            json_encode($row['by_source'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            json_encode($row['errors'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            $row['notes'] ?? null,
        ]);
        return (int) \Db::pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 30): array
    {
        self::ensureSchema();
        $limit = max(1, min(100, $limit));
        $stmt = \Db::pdo()->query(
            'SELECT * FROM job_ingest_logs ORDER BY id DESC LIMIT ' . $limit
        );
        if ($stmt === false) {
            return [];
        }
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $bySource = json_decode((string) ($row['by_source'] ?? ''), true);
            $errors = json_decode((string) ($row['errors'] ?? ''), true);
            $row['by_source'] = is_array($bySource) ? $bySource : [];
            $row['errors'] = is_array($errors) ? $errors : [];
            $out[] = $row;
        }
        return $out;
    }

    public static function clearOlderThanDays(int $days = 90): int
    {
        self::ensureSchema();
        $days = max(7, min(3650, $days));
        $stmt = \Db::pdo()->prepare(
            'DELETE FROM job_ingest_logs WHERE finished_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
