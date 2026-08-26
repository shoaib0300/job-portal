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
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
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
require_once $root . '/src/SuperAdmin.php';
require_once $root . '/src/PdfExport.php';
require_once $root . '/src/DeepL.php';
require_once $root . '/src/LibreTranslate.php';
require_once $root . '/src/DocTranslate.php';
require_once $root . '/src/Jobs/load.php';

try {
    Versions::ensureSchema();
    App::ensureDashboardSchema();
    Auth::ensureSchema();
    SuperAdmin::ensureSchema();
    JobAggregator::ensureSchema();
    LibreTranslate::ensureSchema();
} catch (Throwable $e) {
    // Pages that need the DB will surface the error; CLI without DATABASE_URL still loads classes.
}

if (PHP_SAPI !== 'cli') {
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $isSuperAdmin = str_contains($scriptPath, '/super-admin');
    if (in_array($script, ['resume.php', 'cover-letter.php'], true)) {
        PdfExport::acceptExportToken();
    }
    $public = ['login.php', 'register.php', 'logout.php'];
    if ($isSuperAdmin) {
        // Super-admin pages handle their own auth.
    } elseif (!in_array($script, $public, true)) {
        Auth::requireLogin();
    }
}
