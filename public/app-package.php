<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\UserDocuments;

Auth::requireLogin();
UserDocuments::ensureSchema();

$appId = isset($_GET['application']) ? (int) $_GET['application'] : (int) ($_POST['application_id'] ?? 0);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
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

// GET = export package
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
