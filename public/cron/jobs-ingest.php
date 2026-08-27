<?php

declare(strict_types=1);

/**
 * Manual / cron trigger for job index ingest.
 *
 *   https://kaammilo.ddev.site/cron/jobs-ingest?key=YOUR_KEY
 *   https://kaammilo.ddev.site/cron/jobs-ingest?key=YOUR_KEY&max_seeds=2
 *   https://kaammilo.ddev.site/cron/jobs-ingest?key=YOUR_KEY&sync=1
 *   https://kaammilo.ddev.site/cron/jobs-ingest?key=YOUR_KEY&purge_only=1
 *
 * Key: env JOBS_INGEST_KEY, or settings.jobs_ingest_key (set in Super Admin → Jobs).
 * Super-admin session also authorized (no key needed when logged in).
 */

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use KaamMilo\Jobs\JobStore;
use KaamMilo\Jobs\JobsIngest;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$key = trim((string) ($_GET['key'] ?? $_POST['key'] ?? ''));
$expected = trim((string) (getenv('JOBS_INGEST_KEY') ?: ''));
if ($expected === '') {
    $expected = trim((string) (App::setting('jobs_ingest_key', '') ?? ''));
}

$authorized = false;
if ($expected !== '' && $key !== '' && hash_equals($expected, $key)) {
    $authorized = true;
}
if (!$authorized && SuperAdmin::id() > 0) {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden. Pass ?key=… or log in as super-admin.'], JSON_UNESCAPED_SLASHES);
    exit;
}

@set_time_limit(0);
JobStore::ensureSchema();

$purgeOnly = isset($_GET['purge_only']) || isset($_POST['purge_only']);
$sync = isset($_GET['sync']) || isset($_POST['sync']);
$maxSeeds = null;
if (isset($_GET['max_seeds']) || isset($_POST['max_seeds'])) {
    $maxSeeds = max(1, (int) ($_GET['max_seeds'] ?? $_POST['max_seeds'] ?? 1));
}

if ($purgeOnly) {
    $days = JobsIngest::autoDeleteDays();
    $n = JobStore::purgeOlderThanDays($days);
    echo json_encode([
        'ok' => true,
        'action' => 'purge',
        'purged' => $n,
        'days' => $days,
        'total' => JobStore::count(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($sync) {
    $lines = [];
    $result = JobsIngest::run(static function (string $msg) use (&$lines): void {
        $lines[] = $msg;
    }, $maxSeeds, 'http');
    echo json_encode([
        'ok' => true,
        'action' => 'ingest_sync',
        'result' => $result,
        'total' => JobStore::count(),
        'log' => $lines,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$started = JobsIngest::startBackground($maxSeeds, 'http');

echo json_encode([
    'ok' => true,
    'action' => 'ingest_started',
    'pid' => $started['pid'],
    'max_seeds' => $maxSeeds,
    'log' => $started['log'],
    'message' => 'Ingest started in the background. See Super Admin → Jobs for run history.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
