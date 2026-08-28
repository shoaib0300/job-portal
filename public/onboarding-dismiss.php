<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/onboarding.php';

$section = trim((string) ($_GET['reopen'] ?? $_POST['section'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $section !== '') {
    onboarding_clear_seen($section);
    App::flash('Guide restored.');
    $back = match ($section) {
        'resume' => '/editor',
        'jobs' => '/jobs',
        'cover' => '/cover',
        'hero' => Site::portalHomePath(),
        default => Site::portalHomePath(),
    };
    App::redirect($back);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    App::redirect(Site::portalHomePath());
}

onboarding_mark_seen($section);

$back = (string) ($_SERVER['HTTP_REFERER'] ?? '');
if ($back !== '') {
    $parsed = parse_url($back);
    $path = ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
    if ($path !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//')) {
        App::redirect($path);
    }
}

App::redirect(Site::portalHomePath());
