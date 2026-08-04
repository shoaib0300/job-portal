<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/PdfExport.php';

Versions::ensureSchema();

$doc = isset($_GET['doc']) && (string) $_GET['doc'] === 'cover' ? 'cover' : 'resume';
$theme = App::resolveTheme($_GET['theme'] ?? null);
$font = App::resolveFont($_GET['font'] ?? null);
$accent = App::resolveAccent($_GET['accent'] ?? null);
$version = isset($_GET['version']) ? (int) $_GET['version'] : 0;
$coverId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$profile = App::profile();
$filename = PdfExport::safeFilename($doc, (string) ($profile['full_name'] ?? 'Document'));

if ($doc === 'resume' && $version > 0) {
    $row = Versions::resumeVersion($version);
    if ($row) {
        $suffix = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $row['title']) ?: 'Resume';
        $filename = preg_replace('/\.pdf$/i', '', $filename) . '-' . trim($suffix, '-') . '.pdf';
    }
} elseif ($doc === 'cover' && $coverId > 0) {
    $row = Versions::coverLetterById($coverId);
    if ($row) {
        $suffix = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $row['title']) ?: 'Cover';
        $filename = preg_replace('/\.pdf$/i', '', $filename) . '-' . trim($suffix, '-') . '.pdf';
    }
}

$inline = isset($_GET['inline']) && (string) $_GET['inline'] === '1';

$query = [
    'theme' => $theme,
    'font' => $font,
    'accent' => $accent,
];
if ($doc === 'resume' && $version > 0) {
    $query['version'] = $version;
}
if ($doc === 'cover' && $coverId > 0) {
    $query['id'] = $coverId;
}

try {
    $path = PdfExport::generate($doc, $query);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "PDF export failed.\n\n";
    echo $e->getMessage() . "\n";
    echo "\nTip: ensure Chrome/Chromium is available (CHROME_PATH) and Node can run scripts/export-pdf.mjs.\n";
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: no-store');
readfile($path);
@unlink($path);
exit;
