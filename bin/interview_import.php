#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Import interview content.
 *
 * Pack JSON/CSV (canonical seeds):
 *   php bin/interview_import.php --file=data/interview/v1/universal/questions.json --dry-run
 *   php bin/interview_import.php --dir=data/interview/v1 --update
 *
 * User-style document import (PDF/DOCX/TXT/MD) — same pipeline as web UI:
 *   php bin/interview_import.php --content=path.pdf --user=muqaddas --dry-run
 *   php bin/interview_import.php --content=notes.md --user=1 --language=en
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\Interview\InterviewContentImport;
use KaamFit\Interview\InterviewImport;
use KaamFit\Interview\InterviewSchema;
use KaamFit\Interview\InterviewTaxonomy;

InterviewSchema::ensureSchema();

$file = null;
$dir = null;
$content = null;
$userArg = null;
$dryRun = false;
$update = false;
$source = null;
$taxonomyOnly = false;
$language = 'en';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--update') {
        $update = true;
    } elseif ($arg === '--taxonomy') {
        $taxonomyOnly = true;
    } elseif (str_starts_with($arg, '--file=')) {
        $file = substr($arg, 7);
    } elseif (str_starts_with($arg, '--dir=')) {
        $dir = substr($arg, 6);
    } elseif (str_starts_with($arg, '--content=')) {
        $content = substr($arg, 10);
    } elseif (str_starts_with($arg, '--user=')) {
        $userArg = substr($arg, 7);
    } elseif (str_starts_with($arg, '--source=')) {
        $source = substr($arg, 9);
    } elseif (str_starts_with($arg, '--language=')) {
        $language = substr($arg, 11);
    }
}

$root = dirname(__DIR__);
$resolve = static function (string $path) use ($root): string {
    if ($path !== '' && $path[0] === '/') {
        return $path;
    }
    return $root . '/' . ltrim($path, '/');
};

try {
    if ($content !== null) {
        if ($userArg === null || $userArg === '') {
            fwrite(STDERR, "--content requires --user=id|username\n");
            exit(1);
        }
        if (ctype_digit($userArg)) {
            $userId = (int) $userArg;
        } else {
            $st = Db::pdo()->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $st->execute([$userArg, $userArg]);
            $userId = (int) ($st->fetchColumn() ?: 0);
        }
        if ($userId < 1) {
            fwrite(STDERR, "User not found: {$userArg}\n");
            exit(1);
        }
        $path = $resolve($content);
        $result = InterviewContentImport::importForUser($userId, [
            'path' => $path,
            'filename' => basename($path),
            'language' => $language,
            'source_type' => 'user_upload',
            'dry_run' => $dryRun,
        ]);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    }

    if ($taxonomyOnly || $file === null && $dir === null) {
        $taxPath = $resolve('data/interview/v1/taxonomy.json');
        if (is_readable($taxPath)) {
            $raw = json_decode((string) file_get_contents($taxPath), true);
            if (is_array($raw)) {
                $t = InterviewTaxonomy::importTaxonomyArray($raw);
                echo json_encode(['taxonomy' => $t], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        if ($taxonomyOnly) {
            exit(0);
        }
    }

    $files = [];
    if ($file !== null) {
        $files[] = $resolve($file);
    }
    if ($dir !== null) {
        $base = $resolve($dir);
        foreach (glob($base . '/*/questions.json') ?: [] as $f) {
            $files[] = $f;
        }
        if (is_readable($base . '/questions.json')) {
            $files[] = $base . '/questions.json';
        }
    }

    if ($files === []) {
        fwrite(STDERR, "Usage: php bin/interview_import.php --file=path.json [--dry-run] [--update]\n");
        fwrite(STDERR, "   or: php bin/interview_import.php --dir=data/interview/v1 [--dry-run] [--update]\n");
        fwrite(STDERR, "   or: php bin/interview_import.php --content=file.pdf --user=username [--dry-run]\n");
        exit(1);
    }

    $totals = [
        'found' => 0, 'valid' => 0, 'duplicates' => 0, 'errors' => 0,
        'new' => 0, 'updated' => 0, 'skipped' => 0, 'messages' => [],
    ];
    foreach ($files as $f) {
        echo "Importing {$f}" . ($dryRun ? ' (dry-run)' : '') . "\n";
        $stats = InterviewImport::fromFile($f, $dryRun, $update, $source);
        foreach (['found', 'valid', 'duplicates', 'errors', 'new', 'updated', 'skipped'] as $k) {
            $totals[$k] += $stats[$k];
        }
        foreach ($stats['messages'] as $m) {
            $totals['messages'][] = basename(dirname($f)) . ': ' . $m;
        }
        echo sprintf(
            "  found=%d valid=%d new=%d updated=%d duplicates=%d errors=%d\n",
            $stats['found'], $stats['valid'], $stats['new'], $stats['updated'], $stats['duplicates'], $stats['errors']
        );
    }

    echo "\nQuestions found: {$totals['found']}\n";
    echo "Valid: {$totals['valid']}\n";
    echo "Duplicates: {$totals['duplicates']}\n";
    echo "Errors: {$totals['errors']}\n\n";
    echo "New: {$totals['new']}\n";
    echo "Updated: {$totals['updated']}\n";
    if ($totals['messages'] !== []) {
        echo "\nMessages:\n";
        foreach (array_slice($totals['messages'], 0, 30) as $m) {
            echo "  - {$m}\n";
        }
    }
    exit($totals['errors'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
