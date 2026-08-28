<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

use KaamFit\Jobs\Sources\SerpBoardSource;
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
                content_key CHAR(64) NOT NULL DEFAULT \'\',
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
                KEY idx_job_company (company(100)),
                KEY idx_job_content (content_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        // Older installs: add content_key if missing.
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM job_listings')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[(string) ($row['Field'] ?? '')] = true;
        }
        if (!isset($cols['content_key'])) {
            $pdo->exec('ALTER TABLE job_listings ADD COLUMN content_key CHAR(64) NOT NULL DEFAULT \'\' AFTER fingerprint');
            try {
                $pdo->exec('ALTER TABLE job_listings ADD KEY idx_job_content (content_key)');
            } catch (\Throwable) {
                // index may already exist
            }
        }
        self::$ready = true;
    }

    /**
     * @param list<JobListing> $jobs
     * @return array{
     *   upserted: int,
     *   inserted: int,
     *   updated: int,
     *   skipped: int,
     *   by_source: array<string, array{inserted: int, updated: int, upserted: int, skipped: int}>
     * }
     */
    public static function upsertMany(array $jobs): array
    {
        self::ensureSchema();
        $stats = [
            'upserted' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'by_source' => [],
        ];
        if ($jobs === []) {
            return $stats;
        }

        $minPosted = (new \DateTimeImmutable('today'))
            ->modify('-' . JobQuery::MAX_POSTED_DAYS . ' days')
            ->format('Y-m-d');
        $pdo = \Db::pdo();
        $findSame = $pdo->prepare(
            'SELECT id FROM job_listings WHERE source = ? AND external_id = ? LIMIT 1'
        );
        $findContent = $pdo->prepare(
            'SELECT id, source FROM job_listings WHERE content_key = ? AND content_key != \'\' LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT INTO job_listings (
                source, external_id, fingerprint, content_key, title, company, city, bundesland, country,
                work_mode, employment, offer_type, salary_text, posted_at, url, apply_url, description, payload, fetched_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )'
        );
        $update = $pdo->prepare(
            'UPDATE job_listings SET
                fingerprint = ?, content_key = ?, title = ?, company = ?, city = ?, bundesland = ?, country = ?,
                work_mode = ?, employment = ?, offer_type = ?, salary_text = ?, posted_at = ?,
                url = ?, apply_url = ?, description = ?, payload = ?, fetched_at = NOW()
             WHERE source = ? AND external_id = ?'
        );

        foreach ($jobs as $job) {
            if (!$job instanceof JobListing || $job->source === '' || $job->externalId === '') {
                continue;
            }
            $src = $job->source;
            if (!isset($stats['by_source'][$src])) {
                $stats['by_source'][$src] = ['inserted' => 0, 'updated' => 0, 'upserted' => 0, 'skipped' => 0];
            }

            $posted = ($job->postedAt !== null && $job->postedAt !== '') ? substr($job->postedAt, 0, 10) : null;
            if ($posted !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $posted)) {
                $posted = null;
            }
            // Only keep jobs within MAX_POSTED_DAYS when a posted date is known.
            if ($posted !== null && $posted < $minPosted) {
                $stats['skipped']++;
                $stats['by_source'][$src]['skipped']++;
                continue;
            }

            $contentKey = JobListing::contentKey($job->company, $job->title, $posted);
            $fingerprint = mb_substr($job->fingerprint !== '' ? $job->fingerprint : JobListing::makeFingerprint($job->company, $job->title, $job->city), 0, 191);
            $payload = json_encode($job->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $row = [
                $fingerprint,
                $contentKey,
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
                $payload,
            ];

            $findSame->execute([$job->source, $job->externalId]);
            $same = $findSame->fetch(PDO::FETCH_ASSOC);
            if ($same !== false) {
                // Re-crawl list cards have empty description/apply — keep hydrated detail fields.
                $existing = self::get($job->source, $job->externalId);
                if ($existing !== null) {
                    // List recrawls often have a short snippet — keep the hydrated JD.
                    if (mb_strlen(trim(strip_tags($existing->description))) > mb_strlen(trim(strip_tags($job->description)))) {
                        $job->description = $existing->description;
                    }
                    if ($job->applyUrl === '' && $existing->applyUrl !== '') {
                        $job->applyUrl = $existing->applyUrl;
                    }
                    if (str_contains($existing->url, '/details/') && str_contains($job->url, '/land/ad/')) {
                        $job->url = $existing->url;
                    }
                    $payload = json_encode($job->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $row[12] = mb_substr($job->url, 0, 1024);
                    $row[13] = mb_substr($job->applyUrl, 0, 1024);
                    $row[14] = $job->description !== '' ? $job->description : null;
                    $row[15] = $payload;
                }
                $update->execute([...$row, $job->source, $job->externalId]);
                $stats['updated']++;
                $stats['upserted']++;
                $stats['by_source'][$src]['updated']++;
                $stats['by_source'][$src]['upserted']++;
                continue;
            }

            // Same company + title + posted date already stored from another platform → skip.
            if ($contentKey !== '') {
                $findContent->execute([$contentKey]);
                $dup = $findContent->fetch(PDO::FETCH_ASSOC);
                if ($dup !== false) {
                    $stats['skipped']++;
                    $stats['by_source'][$src]['skipped']++;
                    continue;
                }
            }

            $insert->execute([
                $job->source,
                $job->externalId,
                ...$row,
            ]);
            $stats['inserted']++;
            $stats['upserted']++;
            $stats['by_source'][$src]['inserted']++;
            $stats['by_source'][$src]['upserted']++;
        }
        return $stats;
    }

    /** @return list<JobListing> */
    public static function search(JobQuery $query): array
    {
        self::ensureSchema();
        $sources = $query->sources !== []
            ? $query->sources
            : array_merge(['arbeitsagentur', 'linkedin', 'jobexport', 'adzuna', 'interamt'], array_keys(SerpBoardSource::BOARDS));
        $sources = array_values(array_unique(array_map('strval', $sources)));
        if ($sources === []) {
            return [];
        }

        // Each selected source is queried on its own with a high cap so adding BA
        // cannot push Jobware/Jobexport rows out of a shared global LIMIT.
        $perSource = 2500;
        $out = [];
        $seen = [];
        foreach ($sources as $source) {
            foreach (self::searchOneSource($query, $source, $perSource) as $job) {
                $key = $job->source . ':' . $job->externalId;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $job;
            }
        }
        return $out;
    }

    /** @return list<JobListing> */
    private static function searchOneSource(JobQuery $query, string $source, int $limit): array
    {
        $where = ['source = ?'];
        $params = [$source];

        if ($query->city !== '') {
            $where[] = '(city LIKE ? OR bundesland LIKE ? OR country LIKE ? OR title LIKE ? OR company LIKE ?)';
            $like = '%' . $query->city . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if ($query->keywords !== [] && !$query->matchResume) {
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

        $limit = max(50, min(2000, $limit));
        $sql = 'SELECT payload FROM job_listings WHERE ' . implode(' AND ', $where)
            . ' ORDER BY COALESCE(posted_at, DATE(fetched_at)) DESC, fetched_at DESC LIMIT ' . $limit;
        $stmt = \Db::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = json_decode((string) ($row['payload'] ?? ''), true);
            if (!is_array($data)) {
                continue;
            }
            $out[] = JobListing::fromArray($data);
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

    /** Jobs ingested or re-crawled today (fetched_at). */
    public static function countFetchedToday(): int
    {
        self::ensureSchema();
        return (int) \Db::pdo()->query(
            'SELECT COUNT(*) FROM job_listings WHERE fetched_at >= CURDATE()'
        )->fetchColumn();
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
