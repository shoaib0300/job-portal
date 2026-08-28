<?php

declare(strict_types=1);

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

require_once $root . '/src/Site.php';

if (PHP_SAPI !== 'cli') {
    $cookie = [
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    $domain = Site::sessionCookieDomain();
    if ($domain !== null) {
        $cookie['domain'] = $domain;
    }
    session_set_cookie_params($cookie);
}

session_start();

require_once $root . '/src/Db.php';
require_once $root . '/src/App.php';
require_once $root . '/src/Versions.php';
require_once $root . '/src/Auth.php';
require_once $root . '/src/SuperAdmin.php';
require_once $root . '/src/PdfExport.php';
require_once $root . '/src/DeepL.php';
require_once $root . '/src/TranslateLanguages.php';
require_once $root . '/src/LibreTranslate.php';
require_once $root . '/src/DocTranslate.php';

$autoload = $root . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Run: composer install (or ddev composer install)\n");
        throw new RuntimeException('vendor/autoload.php missing — run composer install');
    }
    http_response_code(500);
    echo 'Application autoloader missing. Run composer install.';
    exit;
}
require_once $autoload;

try {
    Versions::ensureSchema();
    App::ensureDashboardSchema();
    Auth::ensureSchema();
    SuperAdmin::ensureSchema();
    KaamMilo\Jobs\JobAggregator::ensureSchema();
    LibreTranslate::ensureSchema();
} catch (Throwable $e) {
    // Pages that need the DB will surface the error; CLI without DATABASE_URL still loads classes.
}

if (PHP_SAPI !== 'cli') {
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $isSuperAdmin = str_contains($scriptPath, '/super-admin');
    $isDashboardIndex = str_contains($scriptPath, '/dashboard/');
    if (in_array($script, ['resume.php', 'cover-letter.php'], true)) {
        PdfExport::acceptExportToken();
    }
    $public = ['about.php', 'demo.php', 'features.php', 'guide.php', 'login.php', 'register.php', 'logout.php'];
    // Marketing home only — not /dashboard/index.php
    if ($script === 'index.php' && !$isDashboardIndex) {
        $public[] = 'index.php';
    }
    // Portal subdomain root is the app home (auth required unless login/register).
    if (Site::isPortalHost() && $script === 'index.php' && !$isDashboardIndex) {
        // Handled in public/index.php (redirect/login). Allow through without Auth::requireLogin here.
        $public[] = 'index.php';
    }
    // Cron HTTP triggers use their own key auth (see public/cron/*).
    $isCron = str_contains($scriptPath, '/cron/');
    if ($isSuperAdmin || $isCron) {
        // Super-admin / cron pages handle their own auth.
    } elseif (!in_array($script, $public, true)) {
        Auth::requireLogin();
    }
}
