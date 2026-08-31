<?php

declare(strict_types=1);

/** Pre-seeded EN↔DE phrase pairs to skip DeepL for exact matches. */
final class TranslationGlossary
{
    /** @var array<string, string>|null */
    private static ?array $memory = null;
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        self::$ready = true;

        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS translation_glossary (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              source_lang CHAR(2) NOT NULL,
              target_lang CHAR(2) NOT NULL,
              source_phrase VARCHAR(512) NOT NULL,
              target_phrase VARCHAR(512) NOT NULL,
              category VARCHAR(32) NOT NULL DEFAULT \'\',
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_glossary_pair (source_lang, target_lang, source_phrase(191)),
              KEY idx_glossary_langs (source_lang, target_lang)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public static function resolveLang(string $lang): ?string
    {
        $lang = strtolower(trim($lang));
        if ($lang === 'de') {
            return 'de';
        }
        if ($lang === 'en' || str_starts_with($lang, 'en-')) {
            return 'en';
        }
        return null;
    }

    public static function normalizeKey(string $text): string
    {
        return strtolower(preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text));
    }

    public static function lookup(string $text, string $source, string $target): ?string
    {
        $targetLang = self::resolveLang($target);
        if ($targetLang === null) {
            return null;
        }

        $sources = [];
        $sourceLang = self::resolveLang($source);
        if ($sourceLang !== null && $sourceLang !== $targetLang) {
            $sources[] = $sourceLang;
        } elseif ($source === 'auto' || $sourceLang === null) {
            foreach (['en', 'de'] as $candidate) {
                if ($candidate !== $targetLang) {
                    $sources[] = $candidate;
                }
            }
        }

        foreach ($sources as $src) {
            $hit = self::lookupPair($text, $src, $targetLang);
            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    public static function lookupPair(string $text, string $sourceLang, string $targetLang): ?string
    {
        if ($sourceLang === $targetLang) {
            return null;
        }
        self::warmMemory();
        $key = $sourceLang . '|' . $targetLang . '|' . self::normalizeKey($text);
        return self::$memory[$key] ?? null;
    }

    /** @return list<array{en: string, de: string, category?: string}> */
    public static function seedEntries(): array
    {
        $file = __DIR__ . '/glossary/en_de.php';
        if (!is_readable($file)) {
            return [];
        }
        $entries = require $file;
        return is_array($entries) ? $entries : [];
    }

    public static function loadFromSeed(): int
    {
        self::ensureSchema();
        $entries = self::seedEntries();
        if ($entries === []) {
            return 0;
        }

        $stmt = Db::pdo()->prepare(
            'INSERT INTO translation_glossary (source_lang, target_lang, source_phrase, target_phrase, category)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE target_phrase = VALUES(target_phrase), category = VALUES(category)'
        );

        $count = 0;
        foreach ($entries as $row) {
            if (!is_array($row)) {
                continue;
            }
            $en = trim((string) ($row['en'] ?? ''));
            $de = trim((string) ($row['de'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            if ($en === '' || $de === '') {
                continue;
            }

            foreach ([['en', 'de', $en, $de], ['de', 'en', $de, $en]] as [$src, $tgt, $sourcePhrase, $targetPhrase]) {
                $stmt->execute([$src, $tgt, $sourcePhrase, $targetPhrase, $category]);
                $count++;
            }
        }

        self::$memory = null;
        return $count;
    }

    public static function countRows(): int
    {
        self::ensureSchema();
        return (int) Db::pdo()->query('SELECT COUNT(*) FROM translation_glossary')->fetchColumn();
    }

    private static function warmMemory(): void
    {
        if (self::$memory !== null) {
            return;
        }
        self::ensureSchema();
        self::$memory = [];
        $rows = Db::pdo()->query(
            'SELECT source_lang, target_lang, source_phrase, target_phrase FROM translation_glossary'
        )->fetchAll();
        foreach ($rows as $row) {
            $src = (string) ($row['source_lang'] ?? '');
            $tgt = (string) ($row['target_lang'] ?? '');
            $phrase = (string) ($row['source_phrase'] ?? '');
            if ($src === '' || $tgt === '' || $phrase === '') {
                continue;
            }
            $key = $src . '|' . $tgt . '|' . self::normalizeKey($phrase);
            self::$memory[$key] = (string) ($row['target_phrase'] ?? '');
        }
    }
}
