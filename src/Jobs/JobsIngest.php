<?php

declare(strict_types=1);

namespace KaamMilo\Jobs;

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

    public const DEFAULT_AUTO_DELETE_DAYS = 14;

    /**
     * Built-in searches for cron / “Fetch all jobs”.
     * Nationwide only (no city × keyword matrix) — keep this short.
     *
     * @return list<array{q:string,city:string,sources?:list<string>}>
     */
    public static function defaultSeeds(): array
    {
        // Free boards that work without Bright Data SERP. Jobware HTML may need Unlocker fallback.
        $sources = [
            'arbeitsagentur',
            'linkedin',
            'jobexport',
            'jobware',
            'career',
            'university',
            'public_sector',
        ];
        // German first — AA/Jobexport return far more for DE terms than EN-only.
        $keywords = [
            'Qualitätssicherung',
            'Softwaretester',
            'Testingenieur',
            'Testautomatisierung',
            'Qualitätsingenieur',
            'QS-Ingenieur',
            'Prüfingenieur',
            'Testmanager',
            'Tester',
            'QA Engineer',
            'Software Tester',
            'Test Automation',
            'Quality Assurance',
            'Manual Tester',
            'Automation Engineer',
            'QS Engineer',
            'QA Specialist',
        ];
        $seeds = [];
        foreach ($keywords as $q) {
            $seeds[] = [
                'q' => $q,
                'city' => '',
                'sources' => $sources,
            ];
        }
        // Jobexport newest-first crawl (empty keyword = same as stellenboerse home, ~40k pool).
        $seeds[] = [
            'q' => '',
            'city' => '',
            'sources' => ['jobexport'],
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
            if ($q === '') {
                continue;
            }
            $city = trim((string) ($row['city'] ?? ''));
            $sources = $row['sources'] ?? null;
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
     * @return array{ok:bool,pid:?string,log:string}
     */
    public static function startBackground(?int $maxSeeds = null, string $trigger = 'admin'): array
    {
        $root = dirname(__DIR__, 2);
        $logDir = $root . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . '/jobs_ingest.log';
        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $script = $root . '/bin/jobs_ingest.php';
        $extra = $maxSeeds !== null && $maxSeeds > 0 ? ' --max-seeds=' . $maxSeeds : '';
        $trigger = preg_replace('/[^a-z0-9_-]/i', '', $trigger) ?: 'admin';
        $cmd = sprintf(
            'JOBS_INGEST_TRIGGER=%s %s %s%s >> %s 2>&1 & echo $!',
            escapeshellarg($trigger),
            escapeshellarg($php),
            escapeshellarg($script),
            $extra,
            escapeshellarg($logFile)
        );
        $pid = trim((string) shell_exec($cmd));
        return [
            'ok' => true,
            'pid' => $pid !== '' ? $pid : null,
            'log' => 'storage/logs/jobs_ingest.log',
        ];
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
            $log(sprintf('[%d/%d] ingest q=%s city=%s', $i, count($seeds), $q, $city !== '' ? $city : '(any)'));
            try {
                $query = JobQuery::fromRequest([
                    'q' => $q,
                    'city' => $city,
                    'sources' => $sources,
                    'posted' => (string) JobQuery::MAX_POSTED_DAYS,
                    'page' => '1',
                    'size' => '50',
                ]);
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
            }
        }

        $days = self::autoDeleteDays();
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
