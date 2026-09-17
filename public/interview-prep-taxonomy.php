<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\Interview\InterviewSchema;
use KaamFit\Interview\InterviewTaxonomy;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

InterviewSchema::ensureSchema();

$payload = InterviewTaxonomy::cascadePayload(
    trim((string) ($_GET['industry'] ?? '')) ?: null,
    trim((string) ($_GET['occupation'] ?? '')) ?: null,
    trim((string) ($_GET['specialization'] ?? '')) ?: null
);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
