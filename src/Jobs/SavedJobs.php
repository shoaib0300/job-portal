<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

use PDO;

/**
 * Per-user saved/bookmarked jobs (apply later).
 */
final class SavedJobs
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        $pdo = \Db::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS saved_jobs (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                job_source VARCHAR(32) NOT NULL,
                job_external_id VARCHAR(191) NOT NULL,
                title VARCHAR(512) NOT NULL DEFAULT \'\',
                company VARCHAR(255) NOT NULL DEFAULT \'\',
                location VARCHAR(255) NOT NULL DEFAULT \'\',
                apply_url VARCHAR(1024) NOT NULL DEFAULT \'\',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_user_job (user_id, job_source, job_external_id(120)),
                KEY idx_user_created (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        self::migrateSchema($pdo);
        self::$ready = true;
    }

    private static function migrateSchema(PDO $pdo): void
    {
        if (self::schemaFlag('schema_saved_jobs_v1')) {
            return;
        }

        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM saved_jobs')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[(string) ($row['Field'] ?? '')] = true;
        }
        if ($cols === []) {
            self::setSchemaFlag('schema_saved_jobs_v1');
            return;
        }

        if (isset($cols['saved_at']) && !isset($cols['created_at'])) {
            $pdo->exec(
                'ALTER TABLE saved_jobs CHANGE saved_at created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            );
            $cols['created_at'] = true;
            unset($cols['saved_at']);
        }
        if (!isset($cols['created_at'])) {
            $pdo->exec(
                'ALTER TABLE saved_jobs ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            );
        }

        $addString = static function (string $column, string $definition) use ($pdo, &$cols): void {
            if (!isset($cols[$column])) {
                $pdo->exec('ALTER TABLE saved_jobs ADD COLUMN ' . $column . ' ' . $definition);
                $cols[$column] = true;
            }
        };

        $addString('title', "VARCHAR(512) NOT NULL DEFAULT ''");
        $addString('company', "VARCHAR(255) NOT NULL DEFAULT ''");
        $addString('location', "VARCHAR(255) NOT NULL DEFAULT ''");
        $addString('apply_url', "VARCHAR(1024) NOT NULL DEFAULT ''");

        self::setSchemaFlag('schema_saved_jobs_v1');
    }

    private static function schemaFlag(string $key): bool
    {
        try {
            $stmt = \Db::pdo()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
            $stmt->execute([$key]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function setSchemaFlag(string $key): void
    {
        $stmt = \Db::pdo()->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([$key, '1']);
    }

    public static function jobKey(string $source, string $externalId): string
    {
        return $source . ':' . $externalId;
    }

    /**
     * @param list<JobListing> $jobs
     * @return array<string, true>
     */
    public static function mapForJobs(array $jobs): array
    {
        if ($jobs === []) {
            return [];
        }
        self::ensureSchema();
        $uid = \App::userId();
        if ($uid <= 0) {
            return [];
        }

        $pairs = [];
        foreach ($jobs as $job) {
            if ($job->source !== '' && $job->externalId !== '') {
                $pairs[] = [$job->source, $job->externalId];
            }
        }
        if ($pairs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pairs), '(?,?)'));
        $params = [$uid];
        foreach ($pairs as $pair) {
            array_push($params, $pair[0], $pair[1]);
        }

        $stmt = \Db::pdo()->prepare(
            "SELECT job_source, job_external_id FROM saved_jobs
             WHERE user_id = ? AND (job_source, job_external_id) IN ({$placeholders})"
        );
        $stmt->execute($params);

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $key = self::jobKey((string) ($row['job_source'] ?? ''), (string) ($row['job_external_id'] ?? ''));
            if ($key !== ':') {
                $map[$key] = true;
            }
        }

        return $map;
    }

    public static function isSaved(int $userId, string $source, string $externalId): bool
    {
        if ($userId <= 0 || $source === '' || $externalId === '') {
            return false;
        }
        self::ensureSchema();
        $stmt = \Db::pdo()->prepare(
            'SELECT 1 FROM saved_jobs WHERE user_id = ? AND job_source = ? AND job_external_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $source, $externalId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array{title?:string,company?:string,location?:string,apply_url?:string} $snapshot
     */
    public static function save(int $userId, string $source, string $externalId, array $snapshot = []): void
    {
        if ($userId <= 0 || $source === '' || $externalId === '') {
            throw new \InvalidArgumentException('Invalid job reference.');
        }
        self::ensureSchema();
        $stmt = \Db::pdo()->prepare(
            'INSERT INTO saved_jobs (user_id, job_source, job_external_id, title, company, location, apply_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               title = VALUES(title),
               company = VALUES(company),
               location = VALUES(location),
               apply_url = VALUES(apply_url)'
        );
        $stmt->execute([
            $userId,
            $source,
            $externalId,
            mb_substr(trim((string) ($snapshot['title'] ?? '')), 0, 512),
            mb_substr(trim((string) ($snapshot['company'] ?? '')), 0, 255),
            mb_substr(trim((string) ($snapshot['location'] ?? '')), 0, 255),
            mb_substr(trim((string) ($snapshot['apply_url'] ?? '')), 0, 1024),
        ]);
    }

    public static function remove(int $userId, string $source, string $externalId): void
    {
        if ($userId <= 0 || $source === '' || $externalId === '') {
            return;
        }
        self::ensureSchema();
        \Db::pdo()->prepare(
            'DELETE FROM saved_jobs WHERE user_id = ? AND job_source = ? AND job_external_id = ?'
        )->execute([$userId, $source, $externalId]);
    }

    /**
     * @param array{title?:string,company?:string,location?:string,apply_url?:string} $snapshot
     */
    public static function toggle(int $userId, string $source, string $externalId, array $snapshot = []): bool
    {
        if (self::isSaved($userId, $source, $externalId)) {
            self::remove($userId, $source, $externalId);

            return false;
        }
        self::save($userId, $source, $externalId, $snapshot);

        return true;
    }

    public static function countForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        self::ensureSchema();
        $stmt = \Db::pdo()->prepare('SELECT COUNT(*) FROM saved_jobs WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{
     *   id:int,
     *   job_source:string,
     *   job_external_id:string,
     *   title:string,
     *   company:string,
     *   location:string,
     *   apply_url:string,
     *   created_at:string,
     *   listing:?JobListing
     * }>
     */
    public static function listForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        self::ensureSchema();
        $stmt = \Db::pdo()->prepare(
            'SELECT id, job_source, job_external_id, title, company, location, apply_url, created_at
             FROM saved_jobs WHERE user_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$userId]);

        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $source = (string) ($row['job_source'] ?? '');
            $externalId = (string) ($row['job_external_id'] ?? '');
            $listing = $source !== '' && $externalId !== ''
                ? JobStore::get($source, $externalId)
                : null;

            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'job_source' => $source,
                'job_external_id' => $externalId,
                'title' => (string) ($row['title'] ?? ''),
                'company' => (string) ($row['company'] ?? ''),
                'location' => (string) ($row['location'] ?? ''),
                'apply_url' => (string) ($row['apply_url'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'listing' => $listing,
            ];
        }

        return $out;
    }
}
