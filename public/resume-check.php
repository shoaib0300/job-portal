<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\Resume\ResumeCheck;
use KaamFit\Resume\ResumeLayout;

header('Content-Type: application/json; charset=utf-8');

if (Auth::id() <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

Versions::ensureSchema();
$versionId = isset($_GET['version']) ? (int) $_GET['version'] : 0;
$pages = isset($_GET['pages']) ? (float) $_GET['pages'] : null;
$jd = trim((string) ($_GET['jd'] ?? $_POST['jd'] ?? ''));

$payload = Versions::resumePayloadForView($versionId > 0 ? $versionId : null);
$meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : ResumeLayout::defaultMeta();
$check = ResumeCheck::analyze($payload, $meta, $pages);
$match = $jd !== '' ? ResumeCheck::jdMatchEstimate($payload, $jd) : null;

echo json_encode([
    'ok' => true,
    'check' => $check,
    'match' => $match,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
