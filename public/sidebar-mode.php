<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (Auth::id() <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in']);
    exit;
}

$raw = file_get_contents('php://input');
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
$mode = is_array($data) ? (string) ($data['sidebar_mode'] ?? '') : (string) ($_POST['sidebar_mode'] ?? '');
$mode = App::resolveSidebar($mode);

App::setSetting('sidebar_mode', $mode);

echo json_encode([
    'ok' => true,
    'sidebar_mode' => $mode,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
