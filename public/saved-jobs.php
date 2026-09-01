<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Http\View;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\SavedJobs;

SavedJobs::ensureSchema();

$uid = App::userId();
$items = SavedJobs::listForUser($uid);

View::renderToLayout('Saved jobs', 'saved-jobs/index', [
    'items' => $items,
    'sourceLabels' => JobQuery::SOURCES,
]);
