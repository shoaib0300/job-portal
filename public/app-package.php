<?php

declare(strict_types=1);

/**
 * Application package: attach/detach (legacy) + Generate PDF from selection.
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\UserDocuments;

Auth::requireLogin();
UserDocuments::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'generate') {
        try {
            $order = $_POST['order'] ?? [];
            if (!is_array($order)) {
                $order = [];
            }
            $order = array_values(array_filter(array_map('strval', $order), static fn(string $k): bool => $k !== ''));
            if ($order === []) {
                throw new InvalidArgumentException('Select at least one document.');
            }

            $appId = (int) ($_POST['application_id'] ?? 0);
            $resumeId = (int) ($_POST['resume_version_id'] ?? 0);
            $coverId = (int) ($_POST['cover_letter_id'] ?? 0);
            $company = trim((string) ($_POST['company'] ?? ''));
            $app = ['company' => $company];

            if ($appId > 0) {
                $stmt = Db::pdo()->prepare('SELECT * FROM applications WHERE id = ? AND user_id = ? LIMIT 1');
                $stmt->execute([$appId, Auth::id()]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row === false) {
                    throw new InvalidArgumentException('Application not found.');
                }
                $app = $row;
                if ($resumeId <= 0) {
                    $resumeId = (int) ($row['resume_version_id'] ?? 0);
                }
                if ($coverId <= 0) {
                    $coverId = (int) ($row['cover_letter_id'] ?? 0);
                }
            }

            $path = UserDocuments::exportSelection($order, [
                'resume_version_id' => $resumeId,
                'cover_letter_id' => $coverId,
                'application_id' => $appId,
                'sync_attachments' => $appId > 0,
            ]);

            $profile = App::profile();
            $filename = UserDocuments::packageFilename($app, (string) ($profile['full_name'] ?? 'Application'));
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
            header('Content-Length: ' . (string) filesize($path));
            header('Cache-Control: private, no-store');
            readfile($path);
            @unlink($path);
            exit;
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            $back = (int) ($_POST['application_id'] ?? 0);
            App::redirect($back > 0 ? '/applications?action=edit&id=' . $back . '#package' : '/app-docs');
        }
    }

    $appId = (int) ($_POST['application_id'] ?? 0);
    if ($appId <= 0) {
        http_response_code(400);
        echo 'Missing application';
        exit;
    }
    $stmt = Db::pdo()->prepare('SELECT * FROM applications WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$appId, Auth::id()]);
    if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    try {
        if ($action === 'attach') {
            $ids = $_POST['document_ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            foreach ($ids as $id) {
                UserDocuments::attach($appId, (int) $id);
            }
            App::flash('Documents attached.');
        }
        if ($action === 'detach') {
            UserDocuments::detach($appId, (int) ($_POST['document_id'] ?? 0));
            App::flash('Document detached.');
        }
        if ($action === 'reorder') {
            $order = $_POST['order'] ?? [];
            if (is_array($order)) {
                UserDocuments::setOrder($appId, array_map('intval', $order));
            }
            App::flash('Order saved.');
        }
        if ($action === 'attach_always') {
            UserDocuments::attachAlwaysInclude($appId);
            App::flash('Always-include documents attached.');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/applications?action=edit&id=' . $appId . '#package');
}

// GET = export package from saved attachments
$appId = isset($_GET['application']) ? (int) $_GET['application'] : 0;
if ($appId <= 0) {
    http_response_code(400);
    echo 'Missing application';
    exit;
}

$stmt = Db::pdo()->prepare('SELECT * FROM applications WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$appId, Auth::id()]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
if ($app === false) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

try {
    $path = UserDocuments::exportPackage($appId);
    $profile = App::profile();
    $filename = UserDocuments::packageFilename($app, (string) ($profile['full_name'] ?? 'Application'));
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit;
} catch (Throwable $e) {
    App::flash($e->getMessage(), 'error');
    App::redirect('/applications?action=edit&id=' . $appId . '#package');
}
