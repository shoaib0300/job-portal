<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\Jobs\SavedJobs;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$uid = App::userId();
if ($uid <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Login required']);
    exit;
}

$action = strtolower(trim((string) ($_POST['action'] ?? 'toggle')));
$source = trim((string) ($_POST['source'] ?? ''));
$externalId = trim((string) ($_POST['external_id'] ?? ''));

if ($source === '' || $externalId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing job reference']);
    exit;
}

$snapshot = [
    'title' => (string) ($_POST['title'] ?? ''),
    'company' => (string) ($_POST['company'] ?? ''),
    'location' => (string) ($_POST['location'] ?? ''),
    'apply_url' => (string) ($_POST['apply_url'] ?? ''),
];

try {
    $saved = false;
    if ($action === 'save') {
        SavedJobs::save($uid, $source, $externalId, $snapshot);
        $saved = true;
    } elseif ($action === 'unsave') {
        SavedJobs::remove($uid, $source, $externalId);
        $saved = false;
    } else {
        $saved = SavedJobs::toggle($uid, $source, $externalId, $snapshot);
    }

    echo json_encode([
        'ok' => true,
        'saved' => $saved,
        'count' => SavedJobs::countForUser($uid),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
