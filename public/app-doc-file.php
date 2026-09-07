<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\UserDocuments;

Auth::requireLogin();
UserDocuments::ensureSchema();

$uid = Auth::id();
$versionId = isset($_GET['version']) ? (int) $_GET['version'] : 0;
$documentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$inline = isset($_GET['inline']) && (string) $_GET['inline'] === '1';

try {
    if ($versionId > 0) {
        $ver = UserDocuments::getVersion($versionId);
        if ($ver === null) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        $path = UserDocuments::absolutePath((string) $ver['storage_path']);
        $mime = (string) ($ver['mime_type'] ?? 'application/octet-stream');
        $name = (string) ($ver['original_filename'] ?? 'document.pdf');
        $doc = UserDocuments::get((int) $ver['document_id']);
        if ($doc !== null && trim((string) $doc['name']) !== '') {
            $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'pdf';
            $name = preg_replace('/[^\w.\-]+/u', '_', (string) $doc['name']) . '.' . $ext;
        }
    } elseif ($documentId > 0) {
        $doc = UserDocuments::get($documentId);
        if ($doc === null || empty($doc['storage_path'])) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        $path = UserDocuments::absolutePath((string) $doc['storage_path']);
        $mime = (string) ($doc['mime_type'] ?? 'application/octet-stream');
        $ext = pathinfo((string) ($doc['original_filename'] ?? 'pdf'), PATHINFO_EXTENSION) ?: 'pdf';
        $name = preg_replace('/[^\w.\-]+/u', '_', (string) $doc['name']) . '.' . $ext;
    } else {
        http_response_code(400);
        echo 'Missing id';
        exit;
    }
} catch (Throwable $e) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
$disp = $inline ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disp . '; filename="' . str_replace('"', '', $name) . '"');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
exit;
