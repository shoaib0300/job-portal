<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use KaamMilo\Jobs\JobsIngest;

SuperAdmin::requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$progress = JobsIngest::progress();
$total = null;
try {
    $total = \KaamMilo\Jobs\JobStore::count();
} catch (Throwable) {
    $total = null;
}
$progress['jobs_stored'] = $total;
$progress['last_run'] = App::setting(JobsIngest::SETTING_LAST_RUN, '') ?? '';

echo json_encode($progress, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
