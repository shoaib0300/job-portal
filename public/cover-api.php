<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\Cover\CoverEditorService;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

Auth::requireLogin();

try {
    $raw = file_get_contents('php://input') ?: '';
    $json = [];
    if ($raw !== '' && str_starts_with(ltrim($raw), '{')) {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        } catch (Throwable) {
            $json = [];
        }
    }

    $action = (string) ($json['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '');
    if ($action === '') {
        throw new InvalidArgumentException('Missing action.');
    }

    $mutating = !in_array($action, ['get_state', 'tailor_preview'], true)
        || $_SERVER['REQUEST_METHOD'] === 'POST';
    if ($mutating && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        $csrf = $json['_csrf'] ?? Csrf::requestToken();
        Csrf::requireValid(is_string($csrf) ? $csrf : null);
    }

    $payload = $json !== [] ? $json : $_POST;

    $result = match ($action) {
        'get_state' => CoverEditorService::getState(
            isset($payload['id']) ? (int) $payload['id'] : null
        ),
        'patch_letter' => CoverEditorService::patchLetter(
            (int) ($payload['id'] ?? 0),
            is_array($payload['fields'] ?? null) ? $payload['fields'] : $payload
        ),
        'upgrade_structured' => CoverEditorService::upgradeToStructured((int) ($payload['id'] ?? 0)),
        'patch_design' => CoverEditorService::patchDesign(
            is_array($payload['fields'] ?? null) ? $payload['fields'] : $payload
        ),
        'duplicate' => CoverEditorService::duplicate(
            (int) ($payload['id'] ?? 0),
            (string) ($payload['title'] ?? ''),
            [
                'copy_content' => ($payload['copy_content'] ?? true) !== false && ($payload['copy_content'] ?? '1') !== '0',
                'make_active' => ($payload['make_active'] ?? true) !== false,
            ]
        ),
        'rename' => CoverEditorService::rename(
            (int) ($payload['id'] ?? 0),
            (string) ($payload['title'] ?? '')
        ),
        'delete' => CoverEditorService::delete((int) ($payload['id'] ?? 0)),
        'activate' => CoverEditorService::activate((int) ($payload['id'] ?? 0)),
        'tailor_preview' => CoverEditorService::tailorPreview(
            (string) ($payload['company'] ?? ''),
            (string) ($payload['role'] ?? ''),
            (string) ($payload['jd'] ?? $payload['job_description'] ?? '')
        ),
        'tailor_apply' => CoverEditorService::tailorApply(
            (string) ($payload['company'] ?? ''),
            (string) ($payload['role'] ?? ''),
            (string) ($payload['location'] ?? ''),
            (string) ($payload['jd'] ?? $payload['job_description'] ?? ''),
            (string) ($payload['link'] ?? ''),
            is_array($payload['overrides'] ?? null) ? $payload['overrides'] : []
        ),
        default => throw new InvalidArgumentException('Unknown action.'),
    };

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
