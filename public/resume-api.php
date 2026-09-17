<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\Resume\ResumeEditorService;

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

    // GET get_state is read-only; still require login (above). CSRF for mutating methods.
    $mutating = !in_array($action, ['get_state', 'tailor_preview'], true)
        || $_SERVER['REQUEST_METHOD'] === 'POST';
    if ($mutating && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        $csrf = $json['_csrf'] ?? Csrf::requestToken();
        Csrf::requireValid(is_string($csrf) ? $csrf : null);
    }

    $payload = $json !== [] ? $json : $_POST;

    $result = match ($action) {
        'get_state' => ResumeEditorService::getState(),
        'patch_profile' => ResumeEditorService::patchProfile(
            is_array($payload['fields'] ?? null) ? $payload['fields'] : $payload
        ),
        'patch_section' => ResumeEditorService::patchSection(
            (int) ($payload['id'] ?? 0),
            is_array($payload['fields'] ?? null) ? $payload['fields'] : $payload
        ),
        'reorder_sections' => ResumeEditorService::reorderSections(
            is_array($payload['ids'] ?? null) ? $payload['ids'] : []
        ),
        'add_section' => ResumeEditorService::addSection(
            (string) ($payload['type'] ?? $payload['section_key'] ?? ''),
            (string) ($payload['title'] ?? '')
        ),
        'remove_section' => ResumeEditorService::removeSection((int) ($payload['id'] ?? 0)),
        'patch_experience' => ResumeEditorService::patchExperience(
            (int) ($payload['id'] ?? 0),
            is_array($payload['fields'] ?? null) ? $payload['fields'] : $payload
        ),
        'add_experience' => ResumeEditorService::addExperience(
            is_array($payload['fields'] ?? null) ? $payload['fields'] : $payload
        ),
        'duplicate_experience' => ResumeEditorService::duplicateExperience((int) ($payload['id'] ?? 0)),
        'delete_experience' => ResumeEditorService::deleteExperience((int) ($payload['id'] ?? 0)),
        'reorder_experiences' => ResumeEditorService::reorderExperiences(
            is_array($payload['ids'] ?? null) ? $payload['ids'] : []
        ),
        'patch_design' => ResumeEditorService::patchDesign(
            is_array($payload['fields'] ?? null) ? $payload['fields'] : $payload
        ),
        'save_version' => ResumeEditorService::saveVersion(
            isset($payload['title']) ? (string) $payload['title'] : null
        ),
        'duplicate_resume' => ResumeEditorService::duplicateResume(
            (int) ($payload['id'] ?? 0),
            (string) ($payload['title'] ?? ''),
            [
                'copy_content' => ($payload['copy_content'] ?? true) !== false && ($payload['copy_content'] ?? '1') !== '0',
                'copy_section_order' => ($payload['copy_section_order'] ?? true) !== false && ($payload['copy_section_order'] ?? '1') !== '0',
                'copy_design' => ($payload['copy_design'] ?? true) !== false && ($payload['copy_design'] ?? '1') !== '0',
            ]
        ),
        'rename_version' => ResumeEditorService::renameVersion(
            (int) ($payload['id'] ?? 0),
            (string) ($payload['title'] ?? '')
        ),
        'tailor_preview' => ResumeEditorService::tailorPreview(
            (string) ($payload['company'] ?? ''),
            (string) ($payload['role'] ?? ''),
            (string) ($payload['jd'] ?? $payload['job_description'] ?? '')
        ),
        'tailor_apply' => ResumeEditorService::tailorApply(
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
