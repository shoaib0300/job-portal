<?php

declare(strict_types=1);

session_start();

$root = dirname(__DIR__);

$envFile = $root . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

require_once $root . '/src/Db.php';
require_once $root . '/src/App.php';
require_once $root . '/src/Versions.php';
require_once $root . '/src/Auth.php';

try {
    Versions::ensureSchema();
    App::ensureDashboardSchema();
    Auth::ensureSchema();
} catch (Throwable $e) {
    // Pages that need the DB will surface the error; CLI without DATABASE_URL still loads classes.
}

if (PHP_SAPI !== 'cli') {
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $public = ['login.php', 'register.php', 'logout.php'];
    if (!in_array($script, $public, true)) {
        Auth::requireLogin();
    }
}
