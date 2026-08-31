<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

use App;

/**
 * Cron ingest: fetch live jobs for seed queries, upsert into job_listings, purge stale.
 */
final class JobsIngest
{
    public const SETTING_AUTO_DELETE_DAYS = 'jobs_auto_delete_days';
    public const SETTING_SEEDS = 'jobs_ingest_seeds';
    public const SETTING_LAST_RUN = 'jobs_ingest_last_run';
    public const SETTING_LAST_STATS = 'jobs_ingest_last_stats';
    public const SETTING_PROGRESS = 'jobs_ingest_progress';

    public const DEFAULT_AUTO_DELETE_DAYS = 14;

    /**
     * Built-in searches for cron / “Fetch all jobs”.
     * Broad by design: all fields + levels (student, junior, full-time, experienced), not QA-only.
     *
     * @return list<array{q:string,city:string,sources?:list<string>,companies?:list<string>}>
     */
    public static function defaultSeeds(): array
    {
        $boards = [
            'arbeitsagentur',
            'linkedin',
            'indeed',
            'glassdoor',
            'stepstone',
            'xing',
            'jobexport',
            'jobware',
            'adzuna',
            'career',
            'university',
            'public_sector',
        ];

        // Field / industry buckets (DE first — BA & Jobexport return far more).
        $fields = [
            // IT & digital
            'Informatik',
            'Softwareentwickler',
            'IT',
            'Systemadministrator',
            'Datenbank',
            'DevOps',
            'SAP',
            'Webentwickler',
            // QA / quality (still included, not exclusive)
            'Qualitätssicherung',
            'Softwaretester',
            'QA Engineer',
            // Engineering & tech
            'Ingenieur',
            'Elektroniker',
            'Mechatroniker',
            'Techniker',
            'Maschinenbau',
            // Business & office
            'Kaufmann',
            'Sachbearbeiter',
            'Bürokauffrau',
            'Projektmanagement',
            'Controlling',
            'Buchhaltung',
            'Personal',
            'Marketing',
            'Vertrieb',
            'Customer Service',
            // Ops / logistics / care
            'Logistik',
            'Lagerist',
            'Produktion',
            'Pflege',
            'Erzieher',
        ];

        // Level / contract types — so beginners, students, and full-time all land in the index.
        $levels = [
            'Werkstudent',
            'Working Student',
            'Praktikum',
            'Internship',
            'Trainee',
            'Junior',
            'Berufseinsteiger',
            'Absolvent',
            'Vollzeit',
            'Teilzeit',
            'Minijob',
            'Fachkraft',
            'Quereinsteiger',
        ];

        $seeds = [];
        foreach (array_merge($fields, $levels) as $q) {
            $seeds[] = [
                'q' => $q,
                'city' => '',
                'sources' => $boards,
            ];
        }

        // Newest-first board crawls (no keyword = all fields / levels in the last 14 days).
        $seeds[] = [
            'q' => '',
            'city' => '',
            'sources' => ['arbeitsagentur'],
        ];
        // Dedicated Minijob crawl (BA arbeitszeit=mj) — keyword-only seeds miss most of these.
        $seeds[] = [
            'q' => '',
            'city' => '',
            'sources' => ['arbeitsagentur'],
            'employment' => 'mini',
        ];
        $seeds[] = [
            'q' => 'Minijob',
            'city' => '',
            'sources' => ['jobexport', 'jobware', 'linkedin', 'adzuna'],
        ];
        $seeds[] = [
            'q' => '',
            'city' => '',
            'sources' => ['jobexport'],
        ];
        $seeds[] = [
            'q' => '',
            'city' => '',
            'sources' => ['jobware'],
        ];
        $seeds[] = [
            'q' => 'IT',
            'city' => '',
            'sources' => ['jobware'],
        ];
        $seeds[] = [
            'q' => '',
            'city' => '',
            'sources' => ['adzuna'],
        ];
        // VPS-native boards (Indeed / Glassdoor via JobSpy; StepStone / XING via PHP scrapers).
        foreach (['', 'Software', 'IT', 'Mitarbeiter'] as $boardQ) {
            $seeds[] = [
                'q' => $boardQ,
                'city' => '',
                'sources' => ['indeed', 'glassdoor', 'stepstone', 'xing'],
            ];
        }
        foreach (['Berlin', 'München', 'Hamburg'] as $boardCity) {
            $seeds[] = [
                'q' => 'Software',
                'city' => $boardCity,
                'sources' => ['indeed', 'glassdoor', 'stepstone', 'xing'],
            ];
        }
        $seeds[] = [
            'q' => '',
            'city' => '',
            'sources' => ['career'],
            'companies' => ['successfactors:jobs.nordex-online.com'],
        ];

        return $seeds;
    }

    /**
     * @return list<array{q:string,city:string,sources?:list<string>}>
     */
    public static function seeds(): array
    {
        $raw = App::setting(self::SETTING_SEEDS, '');
        if (!is_string($raw) || trim($raw) === '') {
            return self::defaultSeeds();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === []) {
            return self::defaultSeeds();
        }
        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = trim((string) ($row['q'] ?? ''));
            $city = trim((string) ($row['city'] ?? ''));
            $sources = $row['sources'] ?? null;
            // Empty q is allowed for newest-first boards (Jobexport / Jobware sitemap).
            if ($q === '' && (!is_array($sources) || $sources === [])) {
                continue;
            }
            $item = ['q' => $q, 'city' => $city];
            if (is_array($sources) && $sources !== []) {
                $item['sources'] = array_values(array_map('strval', $sources));
            }
            $out[] = $item;
        }
        return $out !== [] ? $out : self::defaultSeeds();
    }

    public static function autoDeleteDays(): int
    {
        $n = (int) (App::setting(self::SETTING_AUTO_DELETE_DAYS, (string) self::DEFAULT_AUTO_DELETE_DAYS) ?? self::DEFAULT_AUTO_DELETE_DAYS);
        return max(1, min(365, $n > 0 ? $n : self::DEFAULT_AUTO_DELETE_DAYS));
    }

    /**
     * Start ingest in the background (admin button / HTTP trigger).
     *
     * @return array{ok:bool,pid:?string,log:string,already_running:bool}
     */
    public static function startBackground(?int $maxSeeds = null, string $trigger = 'admin'): array
    {
        $current = self::progress();
        if (($current['status'] ?? '') === 'running' && self::isPidAlive((string) ($current['pid'] ?? ''))) {
            return [
                'ok' => true,
                'pid' => isset($current['pid']) ? (string) $current['pid'] : null,
                'log' => 'storage/logs/jobs_ingest.log',
                'already_running' => true,
            ];
        }

        $root = dirname(__DIR__, 2);
        $logDir = $root . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . '/jobs_ingest.log';
        $php = self::phpCliBinary();
        $script = $root . '/bin/jobs_ingest.php';
        $extra = $maxSeeds !== null && $maxSeeds > 0 ? ' --max-seeds=' . $maxSeeds : '';
        $trigger = preg_replace('/[^a-z0-9_-]/i', '', $trigger) ?: 'admin';
        $seedTotal = count(self::seeds());
        if ($maxSeeds !== null && $maxSeeds > 0) {
            $seedTotal = min($seedTotal, $maxSeeds);
        }

        self::writeProgress([
            'status' => 'starting',
            'pid' => null,
            'trigger' => $trigger,
            'started_at' => date('c'),
            'updated_at' => date('c'),
            'finished_at' => null,
            'seed_index' => 0,
            'seed_total' => $seedTotal,
            'seed_label' => 'Starting…',
            'percent' => 0,
            'fetched' => 0,
            'inserted' => 0,
            'updated' => 0,
            'message' => 'Launching job fetch…',
            'log_id' => null,
            'duration_sec' => 0,
            'errors' => 0,
        ]);

        $cmd = sprintf(
            'JOBS_INGEST_TRIGGER=%s %s %s%s >> %s 2>&1 & echo $!',
            escapeshellarg($trigger),
            escapeshellarg($php),
            escapeshellarg($script),
            $extra,
            escapeshellarg($logFile)
        );
        $pid = trim((string) shell_exec($cmd));
        self::writeProgress([
            'status' => 'running',
            'pid' => $pid !== '' ? $pid : null,
            'message' => $pid !== '' ? ('Running (pid ' . $pid . ')') : 'Running…',
            'updated_at' => date('c'),
        ]);

        return [
            'ok' => true,
            'pid' => $pid !== '' ? $pid : null,
            'log' => 'storage/logs/jobs_ingest.log',
            'already_running' => false,
        ];
    }

    /**
     * Live progress for Super Admin UI (poll JSON).
     *
     * @return array<string, mixed>
     */
    public static function progress(): array
    {
        $raw = self::globalSetting(self::SETTING_PROGRESS) ?? '';
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data) || $data === []) {
            return [
                'status' => 'idle',
                'percent' => 0,
                'message' => 'No fetch running.',
                'seed_index' => 0,
                'seed_total' => 0,
                'alive' => false,
            ];
        }

        $status = (string) ($data['status'] ?? 'idle');
        $pid = isset($data['pid']) ? (string) $data['pid'] : '';
        $alive = $pid !== '' && self::isPidAlive($pid);
        $data['alive'] = $alive;

        // Live elapsed time while a seed is blocked on network I/O.
        if (in_array($status, ['running', 'starting'], true)) {
            $started = (string) ($data['started_at'] ?? '');
            if ($started !== '') {
                $ts = strtotime($started);
                if ($ts !== false) {
                    $data['duration_sec'] = max(0, time() - $ts);
                }
            }
        }

        // Stale: marked running but process is gone and no finish yet.
        if (in_array($status, ['running', 'starting'], true) && $pid !== '' && !$alive) {
            $updated = (string) ($data['updated_at'] ?? '');
            $age = $updated !== '' ? (time() - (int) strtotime($updated)) : 9999;
            if ($age > 90) {
                $data['status'] = 'error';
                $data['message'] = 'Fetch process stopped unexpectedly. Check storage/logs/jobs_ingest.log.';
                $data['finished_at'] = $data['finished_at'] ?? date('c');
                $data['percent'] = (int) ($data['percent'] ?? 0);
                self::writeProgress($data);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $patch */
    public static function writeProgress(array $patch): void
    {
        $cur = [];
        $raw = self::globalSetting(self::SETTING_PROGRESS);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $cur = $decoded;
            }
        }
        $next = array_merge($cur, $patch);
        $next['updated_at'] = date('c');
        self::setGlobalSetting(
            self::SETTING_PROGRESS,
            json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
        );
    }

    /** Progress must be global — background CLI has no logged-in Auth user. */
    private static function globalSetting(string $key): ?string
    {
        $stmt = \Db::pdo()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return (string) $row['value'];
    }

    private static function setGlobalSetting(string $key, string $value): void
    {
        $stmt = \Db::pdo()->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([$key, $value]);
    }

    public static function isPidAlive(string $pid): bool
    {
        $pid = trim($pid);
        if ($pid === '' || !ctype_digit($pid)) {
            return false;
        }
        $n = (int) $pid;
        if ($n <= 1) {
            return false;
        }
        if (function_exists('posix_kill')) {
            return @posix_kill($n, 0);
        }
        return is_dir('/proc/' . $n);
    }

    /** Prefer PHP CLI — PHP_BINARY under php-fpm is not usable for background scripts. */
    private static function phpCliBinary(): string
    {
        $bin = PHP_BINARY;
        if (is_string($bin) && $bin !== '' && is_executable($bin) && !str_contains(mb_strtolower(basename($bin)), 'fpm')) {
            return $bin;
        }
        foreach (['/usr/bin/php', '/usr/local/bin/php', 'php'] as $candidate) {
            if ($candidate === 'php') {
                return 'php';
            }
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        return 'php';
    }

    /**
     * @param callable|null $log fn(string): void
     * @return array{
     *   seeds:int,
     *   fetched:int,
     *   upserted:int,
     *   inserted:int,
     *   updated:int,
     *   purged:int,
     *   by_source: array<string, array{inserted:int, updated:int, upserted:int}>,
     *   errors:list<string>,
     *   log_id:int
     * }
     */
    public static function run(?callable $log = null, ?int $maxSeeds = null, string $trigger = 'cron'): array
    {
        JobStore::ensureSchema();
        JobIngestLog::ensureSchema();
        $log = $log ?? static function (string $msg): void {
            fwrite(STDOUT, $msg . "\n");
        };

        $startedAt = date('Y-m-d H:i:s');
        $t0 = microtime(true);
        $envTrigger = trim((string) (getenv('JOBS_INGEST_TRIGGER') ?: ''));
        if ($envTrigger !== '') {
            $trigger = $envTrigger;
        }

        $seeds = self::seeds();
        if ($maxSeeds !== null && $maxSeeds > 0) {
            $seeds = array_slice($seeds, 0, $maxSeeds);
        }

        $seedTotal = count($seeds);
        self::writeProgress([
            'status' => 'running',
            'trigger' => $trigger,
            'started_at' => date('c'),
            'finished_at' => null,
            'seed_index' => 0,
            'seed_total' => $seedTotal,
            'seed_label' => 'Preparing…',
            'percent' => 0,
            'fetched' => 0,
            'inserted' => 0,
            'updated' => 0,
            'message' => 'Fetch started (' . $seedTotal . ' searches)…',
            'log_id' => null,
            'duration_sec' => 0,
            'errors' => 0,
            'pid' => (string) getmypid(),
        ]);

        $fetched = 0;
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $upserted = 0;
        /** @var array<string, array{inserted:int, updated:int, upserted:int, skipped:int}> $bySource */
        $bySource = [];
        $errors = [];
        $i = 0;
        foreach ($seeds as $seed) {
            $i++;
            $q = (string) ($seed['q'] ?? '');
            $city = (string) ($seed['city'] ?? '');
            $sources = $seed['sources'] ?? array_keys(JobQuery::SOURCES);
            if (!is_array($sources) || $sources === []) {
                $sources = array_keys(JobQuery::SOURCES);
            }
            $companies = $seed['companies'] ?? [];
            if (!is_array($companies)) {
                $companies = [];
            }
            $label = $q !== '' ? $q : ('(' . implode(',', array_map('strval', $sources)) . ')');
            $empSeed = trim((string) ($seed['employment'] ?? ''));
            if ($empSeed === 'mini') {
                $label = 'Minijob · ' . $label;
            }
            $percent = (int) max(1, floor((($i - 1) / max(1, $seedTotal)) * 100));
            if ($seedTotal === 1) {
                $percent = 5;
            }
            self::writeProgress([
                'status' => 'running',
                'seed_index' => $i,
                'seed_total' => $seedTotal,
                'seed_label' => $label,
                'percent' => min(99, $percent),
                'fetched' => $fetched,
                'inserted' => $inserted,
                'updated' => $updated,
                'message' => sprintf('[%d/%d] %s', $i, $seedTotal, $label),
                'duration_sec' => (int) round(microtime(true) - $t0),
                'errors' => count($errors),
                'pid' => (string) getmypid(),
            ]);
            $log(sprintf(
                '[%d/%d] ingest q=%s city=%s companies=%s',
                $i,
                count($seeds),
                $q !== '' ? $q : '(any)',
                $city !== '' ? $city : '(any)',
                $companies !== [] ? implode(',', $companies) : '(all)'
            ));
            try {
                $req = [
                    'q' => $q,
                    'city' => $city,
                    'sources' => $sources,
                    'posted' => (string) JobQuery::MAX_POSTED_DAYS,
                    'page' => '1',
                    'size' => '50',
                ];
                if ($companies !== []) {
                    $req['companies'] = array_values(array_map('strval', $companies));
                }
                $employment = trim((string) ($seed['employment'] ?? ''));
                if ($employment !== '') {
                    $req['employment'] = $employment;
                }
                $query = JobQuery::fromRequest($req);
                $result = JobAggregator::searchLive($query);
                $fetched += count($result['listings']);
                $up = JobStore::upsertMany($result['listings']);
                $inserted += $up['inserted'];
                $updated += $up['updated'];
                $skipped += (int) ($up['skipped'] ?? 0);
                $upserted += $up['upserted'];
                foreach ($up['by_source'] as $src => $counts) {
                    if (!isset($bySource[$src])) {
                        $bySource[$src] = ['inserted' => 0, 'updated' => 0, 'upserted' => 0, 'skipped' => 0];
                    }
                    $bySource[$src]['inserted'] += (int) ($counts['inserted'] ?? 0);
                    $bySource[$src]['updated'] += (int) ($counts['updated'] ?? 0);
                    $bySource[$src]['upserted'] += (int) ($counts['upserted'] ?? 0);
                    $bySource[$src]['skipped'] += (int) ($counts['skipped'] ?? 0);
                }
                self::writeProgress([
                    'fetched' => $fetched,
                    'inserted' => $inserted,
                    'updated' => $updated,
                    'percent' => min(99, (int) floor(($i / max(1, $seedTotal)) * 100)),
                    'message' => sprintf('[%d/%d] done · +%d new this search', $i, $seedTotal, $up['inserted']),
                    'duration_sec' => (int) round(microtime(true) - $t0),
                ]);
                $log(sprintf(
                    '  → %d fetched | +%d new | ~%d updated | skip %d (dup/old) | notices: %d',
                    count($result['listings']),
                    $up['inserted'],
                    $up['updated'],
                    (int) ($up['skipped'] ?? 0),
                    count($result['notices'])
                ));
                foreach ($result['notices'] as $notice) {
                    $log('  notice: ' . $notice);
                }
            } catch (\Throwable $e) {
                $msg = sprintf('seed failed (%s / %s): %s', $q, $city, $e->getMessage());
                $errors[] = $msg;
                $log('  ERROR: ' . $msg);
                self::writeProgress([
                    'errors' => count($errors),
                    'message' => sprintf('[%d/%d] error: %s', $i, $seedTotal, $e->getMessage()),
                ]);
            }
        }

        $days = self::autoDeleteDays();
        self::writeProgress([
            'percent' => 99,
            'message' => 'Purging jobs older than ' . $days . ' days…',
            'seed_label' => 'Cleanup',
        ]);
        $purged = JobStore::purgeOlderThanDays($days);
        $totalAfter = JobStore::count();
        $finishedAt = date('Y-m-d H:i:s');
        $duration = (int) round(microtime(true) - $t0);
        $log(sprintf(
            'Done: +%d new, ~%d updated, skipped %d, purged %d (>%d days). Store total: %d (%ds)',
            $inserted,
            $updated,
            $skipped,
            $purged,
            $days,
            $totalAfter,
            $duration
        ));

        ksort($bySource);
        $stats = [
            'seeds' => count($seeds),
            'fetched' => $fetched,
            'upserted' => $upserted,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'purged' => $purged,
            'errors' => $errors,
            'by_source' => $bySource,
            'total' => $totalAfter,
            'at' => date('c'),
            'trigger' => $trigger,
            'duration_sec' => $duration,
        ];
        App::setSetting(self::SETTING_LAST_RUN, date('c'));
        App::setSetting(self::SETTING_LAST_STATS, json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        $logId = JobIngestLog::record([
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'trigger' => $trigger,
            'seeds' => count($seeds),
            'fetched' => $fetched,
            'inserted' => $inserted,
            'updated' => $updated,
            'purged' => $purged,
            'total_after' => $totalAfter,
            'duration_sec' => $duration,
            'by_source' => $bySource,
            'errors' => $errors,
            'notes' => $skipped > 0 ? ("skipped_dups_or_old=" . $skipped) : null,
        ]);
        JobIngestLog::clearOlderThanDays(90);

        self::writeProgress([
            'status' => count($errors) > 0 && $upserted === 0 ? 'error' : 'done',
            'percent' => 100,
            'seed_index' => $seedTotal,
            'seed_total' => $seedTotal,
            'seed_label' => 'Finished',
            'fetched' => $fetched,
            'inserted' => $inserted,
            'updated' => $updated,
            'message' => sprintf(
                'Completed · +%d new · ~%d updated · total %d · %ds',
                $inserted,
                $updated,
                $totalAfter,
                $duration
            ),
            'finished_at' => date('c'),
            'log_id' => $logId,
            'duration_sec' => $duration,
            'errors' => count($errors),
            'alive' => false,
        ]);

        return [
            'seeds' => count($seeds),
            'fetched' => $fetched,
            'upserted' => $upserted,
            'inserted' => $inserted,
            'updated' => $updated,
            'purged' => $purged,
            'by_source' => $bySource,
            'errors' => $errors,
            'log_id' => $logId,
        ];
    }
}
