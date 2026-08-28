<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/DemoSample.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

$persona = DemoSample::persona();
$counts = DemoSample::applicationCounts();
$jobs = DemoSample::jobs();
$applications = DemoSample::applications();
$tailor = DemoSample::tailorDefaults();
$preview = DemoSample::tailorPreview();

site_layout_header('Try demo', [
    'body_class' => 'site-demo-page',
    'extra_stylesheets' => [
        '/assets/css/dashboard.css?v=20260828demo',
        '/assets/css/demo.css?v=20260828demo',
    ],
]);
require dirname(__DIR__) . '/src/Views/demo/index.php';
site_layout_footer([
    'extra_scripts' => [
        '/assets/js/demo.js?v=20260828demo',
    ],
]);
