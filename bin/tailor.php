<?php

declare(strict_types=1);

/**
 * Copy Main resume + cover for a JD and log Applications.
 *
 * Usage (inside DDEV):
 *   ddev exec php bin/tailor.php --user muqaddas <<'JSON'
 *   {"company":"Acme","role":"QA","location":"Hamburg, Germany","jd":"..."}
 *   JSON
 *
 * Optional keys: user, link, status, profile_title, summary, skills, cover_body, notes,
 * experiences (array of {company_contains, position_contains?, bullets})
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

$userFlag = 'muqaddas';
$args = array_slice($argv ?? [], 1);
foreach ($args as $i => $arg) {
    if ($arg === '--user' && isset($args[$i + 1])) {
        $userFlag = (string) $args[$i + 1];
        break;
    }
    if (str_starts_with($arg, '--user=')) {
        $userFlag = substr($arg, 7);
        break;
    }
}

$raw = stream_get_contents(STDIN);
if ($raw === false || trim($raw) === '') {
    fwrite(STDERR, "Pass JSON on stdin with company, role, location, and optional jd.\n");
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "Invalid JSON.\n");
    exit(1);
}

if (!empty($data['user'])) {
    $userFlag = (string) $data['user'];
}

if (!Auth::loginAs($userFlag)) {
    fwrite(STDERR, "Unknown user: {$userFlag}\n");
    exit(1);
}

try {
    $result = App::tailorFromJd(
        (string) ($data['company'] ?? ''),
        (string) ($data['role'] ?? ''),
        (string) ($data['location'] ?? ''),
        (string) ($data['jd'] ?? $data['jd_snippet'] ?? ''),
        (string) ($data['link'] ?? ''),
        (string) ($data['status'] ?? 'applied'),
        isset($data['profile_title']) ? (string) $data['profile_title'] : null,
        isset($data['summary']) ? (string) $data['summary'] : null,
        isset($data['skills']) ? (string) $data['skills'] : null,
        isset($data['cover_body']) ? (string) $data['cover_body'] : null,
        (string) ($data['notes'] ?? ''),
        isset($data['job_source']) ? (string) $data['job_source'] : null,
        isset($data['job_external_id']) ? (string) $data['job_external_id'] : null,
        is_array($data['experiences'] ?? null) ? $data['experiences'] : null
    );
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
