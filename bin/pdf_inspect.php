<?php

declare(strict_types=1);

/**
 * Inspect PDF metadata for privacy issues (dev/debug).
 *
 * Usage:
 *   ddev exec php bin/pdf_inspect.php path/to/file.pdf
 *   ddev exec php bin/pdf_inspect.php --generate-resume
 *   ddev exec php bin/pdf_inspect.php --generate-ats
 *   ddev exec php bin/pdf_inspect.php --generate-package=53
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\UserDocuments;

Auth::loginAs(getenv('MNK_PDF_INSPECT_USER') ?: 'muqaddas');

$arg = $argv[1] ?? '';
$path = '';
$cleanup = false;

if ($arg === '--generate-resume' || $arg === '--generate-ats') {
    $q = ['version' => (int) (Versions::baseResumeVersion()['id'] ?? 1), 'lang' => App::resolveDocumentLang()];
    if ($arg === '--generate-ats') {
        $q['ats'] = '1';
        $q['theme'] = 'ats';
    }
    $path = PdfExport::generate('resume', $q);
    $cleanup = true;
} elseif (str_starts_with($arg, '--generate-package=')) {
    $appId = (int) substr($arg, strlen('--generate-package='));
    $path = UserDocuments::exportPackage($appId);
    $cleanup = true;
} elseif ($arg !== '' && is_file($arg)) {
    $path = $arg;
} elseif ($arg !== '' && is_file(dirname(__DIR__) . '/' . ltrim($arg, '/'))) {
    $path = dirname(__DIR__) . '/' . ltrim($arg, '/');
} else {
    fwrite(STDERR, "Usage: php bin/pdf_inspect.php <file.pdf|--generate-resume|--generate-ats|--generate-package=ID>\n");
    exit(2);
}

$report = PdfSanitize::inspect($path);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

if ($cleanup) {
    @unlink($path);
}

exit(!empty($report['ok']) ? 0 : 1);
