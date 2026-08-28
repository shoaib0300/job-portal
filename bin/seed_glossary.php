<?php

declare(strict_types=1);

/**
 * Load EN↔DE glossary seed into MySQL.
 *
 * Usage: ddev exec php bin/seed_glossary.php
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

TranslationGlossary::ensureSchema();
$before = TranslationGlossary::countRows();
$upserted = TranslationGlossary::loadFromSeed();
$after = TranslationGlossary::countRows();

echo json_encode([
    'seed_entries' => count(TranslationGlossary::seedEntries()),
    'rows_upserted' => $upserted,
    'rows_before' => $before,
    'rows_after' => $after,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
