<?php

declare(strict_types=1);

namespace KaamMilo\Jobs;

use KaamMilo\Jobs\Sources\SerpBoardSource;
use PDO;

/**
 * Persistent job index filled by cron ingest. User searches read from here only.
 */
final class JobStore
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        $pdo = \Db::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS job_listings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                source VARCHAR(32) NOT NULL,
                external_id VARCHAR(191) NOT NULL,
                fingerprint VARCHAR(191) NOT NULL DEFAULT \'\',
                title VARCHAR(512) NOT NULL,
                company VARCHAR(255) NOT NULL DEFAULT \'\',
                city VARCHAR(128) NOT NULL DEFAULT \'\',
                bundesland VARCHAR(128) NOT NULL DEFAULT \'\',
                country VARCHAR(64) NOT NULL DEFAULT \'Germany\',
                work_mode VARCHAR(32) NOT NULL DEFAULT \'unknown\',
                employment VARCHAR(32) NOT NULL DEFAULT \'unknown\',
                offer_type VARCHAR(32) NOT NULL DEFAULT \'unknown\',
                salary_text VARCHAR(255) NOT NULL DEFAULT \'\',
                posted_at DATE NULL,
                url VARCHAR(1024) NOT NULL DEFAULT \'\',
                apply_url VARCHAR(1024) NOT NULL DEFAULT \'\',
                description MEDIUMTEXT NULL,
                payload MEDIUMTEXT NOT NULL,
                fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_job_source_ext (source, external_id(120)),
                KEY idx_job_posted (posted_at),
                KEY idx_job_fetched (fetched_at),
                KEY idx_job_city (city),
                KEY idx_job_source (source),
                KEY idx_job_company (company(100))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        self::$ready = true;
    }

    /**
     * @param list<JobListing> $jobs
     * @return array{
     *   upserted: int,
     *   inserted: int,
     *   updated: int,
     *   by_source: array<string, array{inserted: int, updated: int, upserted: int}>
     * }
     */
    public static function upsertMany(array $jobs): array
    {
        self::ensureSchema();
        $stats = [
            'upserted' => 0,
            'inserted' => 0,
            'updated' => 0,
            'by_source' => [],
        ];
        if ($jobs === []) {
            return $stats;
        }
        $sql = 'INSERT INTO job_listings (
            source, external_id, fingerprint, title, company, city, bundesland, country,
            work_mode, employment, offer_type, salary_text, posted_at, url, apply_url, description, payload, fetched_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
        ) ON DUPLICATE KEY UPDATE
            fingerprint = VALUES(fingerprint),
            title = VALUES(title),
            company = VALUES(company),
            city = VALUES(city),
            bundesland = VALUES(bundesland),
            country = VALUES(country),
            work_mode = VALUES(work_mode),
            employment = VALUES(employment),
            offer_type = VALUES(offer_type),
            salary_text = VALUES(salary_text),
            posted_at = VALUES(posted_at),
            url = VALUES(url),
            apply_url = VALUES(apply_url),
            description = VALUES(description),
            payload = VALUES(payload),
            fetched_at = NOW()';
        $stmt = \Db::pdo()->prepare($sql);
        foreach ($jobs as $job) {
            if (!$job instanceof JobListing || $job->source === '' || $job->externalId === '') {
                continue;
            }
            $posted = ($job->postedAt !== null && $job->postedAt !== '') ? substr($job->postedAt, 0, 10) : null;
            if ($posted !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $posted)) {
                $posted = null;
            }
            $stmt->execute([
                $job->source,
                $job->externalId,
                $job->fingerprint,
                mb_substr($job->title, 0, 512),
                mb_substr($job->company, 0, 255),
                mb_substr($job->city, 0, 128),
                mb_substr($job->bundesland, 0, 128),
                mb_substr($job->country !== '' ? $job->country : 'Germany', 0, 64),
                $job->workMode,
                $job->employment,
                $job->offerType,
                mb_substr($job->salaryText, 0, 255),
                $posted,
                mb_substr($job->url, 0, 1024),
                mb_substr($job->applyUrl, 0, 1024),
                $job->description !== '' ? $job->description : null,
                json_encode($job->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            // MySQL: 1 = insert, 2 = update on duplicate.
            $affected = $stmt->rowCount();
            $isInsert = $affected === 1;
            $src = $job->source;
            if (!isset($stats['by_source'][$src])) {
                $stats['by_source'][$src] = ['inserted' => 0, 'updated' => 0, 'upserted' => 0];
            }
            if ($isInsert) {
                $stats['inserted']++;
                $stats['by_source'][$src]['inserted']++;
            } else {
                $stats['updated']++;
                $stats['by_source'][$src]['updated']++;
            }
            $stats['upserted']++;
            $stats['by_source'][$src]['upserted']++;
        }
        return $stats;
    }

    /** @return list<JobListing> */
    public static function search(JobQuery $query): array
    {
        self::ensureSchema();
        $where = ['1=1'];
        $params = [];

        $sources = $query->sources !== []
            ? $query->sources
            : array_merge(['arbeitsagentur', 'linkedin', 'jobexport', 'interamt'], array_keys(SerpBoardSource::BOARDS));
        $placeholders = implode(',', array_fill(0, count($sources), '?'));
        $where[] = "source IN ($placeholders)";
        foreach ($sources as $s) {
            $params[] = $s;
        }

        if ($query->city !== '') {
            $where[] = '(city LIKE ? OR bundesland LIKE ? OR country LIKE ? OR title LIKE ? OR company LIKE ?)';
            $like = '%' . $query->city . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if ($query->keywords !== []) {
            $kwParts = [];
            foreach ($query->keywords as $kw) {
                $kw = trim((string) $kw);
                if ($kw === '') {
                    continue;
                }
                $kwParts[] = '(title LIKE ? OR company LIKE ? OR description LIKE ? OR city LIKE ?)';
                $like = '%' . $kw . '%';
                array_push($params, $like, $like, $like, $like);
            }
            if ($kwParts !== []) {
                $where[] = '(' . implode(' OR ', $kwParts) . ')';
            }
        }

        if ($query->postedDays > 0) {
            $where[] = '(posted_at IS NULL OR posted_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                OR (posted_at IS NULL AND fetched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)))';
            $params[] = $query->postedDays;
            $params[] = $query->postedDays;
        }

        if ($query->workMode !== '') {
            $where[] = 'work_mode = ?';
            $params[] = $query->workMode;
        }
        if ($query->employment !== '') {
            $where[] = 'employment = ?';
            $params[] = $query->employment;
        }

        $sql = 'SELECT payload FROM job_listings WHERE ' . implode(' AND ', $where)
            . ' ORDER BY COALESCE(posted_at, DATE(fetched_at)) DESC, fetched_at DESC LIMIT 800';
        $stmt = \Db::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = json_decode((string) ($row['payload'] ?? ''), true);
            if (!is_array($data)) {
                continue;
            }
            $job = JobListing::fromArray($data);
            $out[] = $job;
        }
        return $out;
    }

    public static function get(string $source, string $externalId): ?JobListing
    {
        self::ensureSchema();
        $stmt = \Db::pdo()->prepare(
            'SELECT payload FROM job_listings WHERE source = ? AND external_id = ? LIMIT 1'
        );
        $stmt->execute([$source, $externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $data = json_decode((string) ($row['payload'] ?? ''), true);
        return is_array($data) ? JobListing::fromArray($data) : null;
    }

    public static function count(?string $source = null): int
    {
        self::ensureSchema();
        if ($source !== null && $source !== '') {
            $stmt = \Db::pdo()->prepare('SELECT COUNT(*) FROM job_listings WHERE source = ?');
            $stmt->execute([$source]);
            return (int) $stmt->fetchColumn();
        }
        return (int) \Db::pdo()->query('SELECT COUNT(*) FROM job_listings')->fetchColumn();
    }

    /** @return list<array{source:string,cnt:int}> */
    public static function countsBySource(): array
    {
        self::ensureSchema();
        $rows = \Db::pdo()->query(
            'SELECT source, COUNT(*) AS cnt FROM job_listings GROUP BY source ORDER BY cnt DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['source' => (string) $row['source'], 'cnt' => (int) $row['cnt']];
        }
        return $out;
    }

    /**
     * Delete rows older than N days by fetched_at (and posted_at when set).
     *
     * @return int deleted rows
     */
    public static function purgeOlderThanDays(int $days): int
    {
        self::ensureSchema();
        $days = max(1, min(3650, $days));
        $stmt = \Db::pdo()->prepare(
            'DELETE FROM job_listings
             WHERE fetched_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                OR (posted_at IS NOT NULL AND posted_at < DATE_SUB(CURDATE(), INTERVAL ? DAY))'
        );
        $stmt->execute([$days, $days]);
        return $stmt->rowCount();
    }

    public static function purgeAll(): int
    {
        self::ensureSchema();
        return (int) \Db::pdo()->exec('DELETE FROM job_listings');
    }

    public static function oldestFetchedAt(): ?string
    {
        self::ensureSchema();
        $v = \Db::pdo()->query('SELECT MIN(fetched_at) FROM job_listings')->fetchColumn();
        return $v !== false && $v !== null ? (string) $v : null;
    }

    public static function newestFetchedAt(): ?string
    {
        self::ensureSchema();
        $v = \Db::pdo()->query('SELECT MAX(fetched_at) FROM job_listings')->fetchColumn();
        return $v !== false && $v !== null ? (string) $v : null;
    }
}
