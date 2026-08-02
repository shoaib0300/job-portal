<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/PdfExport.php';

$doc = isset($_GET['doc']) && (string) $_GET['doc'] === 'cover' ? 'cover' : 'resume';
$theme = App::resolveTheme($_GET['theme'] ?? null);
$font = App::resolveFont($_GET['font'] ?? null);
$accent = App::resolveAccent($_GET['accent'] ?? null);

$profile = App::profile();
$filename = PdfExport::safeFilename($doc, (string) ($profile['full_name'] ?? 'Document'));

$inline = isset($_GET['inline']) && (string) $_GET['inline'] === '1';

try {
    $path = PdfExport::generate($doc, [
        'theme' => $theme,
        'font' => $font,
        'accent' => $accent,
    ]);
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
