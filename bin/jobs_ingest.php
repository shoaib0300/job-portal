<?php

declare(strict_types=1);

/**
 * Cron: fetch jobs from all sources into job_listings, then purge by auto-delete days.
 *
 * Every 2 hours (host crontab example):
 *   0 every-2h * * * cd /path/to/mnk && ddev exec php bin/jobs_ingest.php >> storage/logs/jobs_ingest.log 2>&1
 *
 * Or inside the web container:
 *   php /var/www/html/bin/jobs_ingest.php
 *
 * Options:
 *   --max-seeds=N   Limit how many seed queries to run (useful for smoke tests)
 *   --purge-only    Only run auto-delete purge, skip live fetch
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\Jobs\JobStore;
use KaamFit\Jobs\JobsIngest;

@set_time_limit(0);
ini_set('memory_limit', '512M');

$args = array_slice($argv ?? [], 1);
$maxSeeds = null;
$purgeOnly = false;
foreach ($args as $arg) {
    if ($arg === '--purge-only') {
        $purgeOnly = true;
        continue;
    }
    if (str_starts_with($arg, '--max-seeds=')) {
        $maxSeeds = max(1, (int) substr($arg, 12));
    }
}

fwrite(STDOUT, '[' . date('c') . "] jobs_ingest start\n");

if ($purgeOnly) {
    JobStore::ensureSchema();
    $days = JobsIngest::autoDeleteDays();
    $n = JobStore::purgeOlderThanDays($days);
    fwrite(STDOUT, "Purged {$n} jobs older than {$days} days. Total: " . JobStore::count() . "\n");
    exit(0);
}

$result = JobsIngest::run(null, $maxSeeds, 'cron');
fwrite(STDOUT, sprintf(
    "[%s] done seeds=%d fetched=%d new=%d updated=%d purged=%d errors=%d log_id=%d\n",
    date('c'),
    $result['seeds'],
    $result['fetched'],
    $result['inserted'],
    $result['updated'],
    $result['purged'],
    count($result['errors']),
    $result['log_id']
));
exit(count($result['errors']) > 0 && $result['upserted'] === 0 ? 1 : 0);
