<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

App::ensureDashboardSchema();

$action = (string) ($_POST['action'] ?? '');
$applicationId = (int) ($_POST['application_id'] ?? 0);

try {
    if ($action === 'confirm_applied') {
        if ($applicationId <= 0) {
            throw new InvalidArgumentException('Missing application.');
        }
        App::confirmApplicationApplied($applicationId);
        echo json_encode(['ok' => true, 'status' => 'applied'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'discard_preparing') {
        if ($applicationId <= 0) {
            throw new InvalidArgumentException('Missing application.');
        }
        App::discardPreparingApplication($applicationId);
        echo json_encode(['ok' => true, 'discarded' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'minimize') {
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new InvalidArgumentException('Unknown action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
