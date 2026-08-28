<?php

declare(strict_types=1);

/**
 * LibreTranslate client with MySQL cache.
 * Env: LIBRETRANSLATE_URL (default https://libretranslate.com), LIBRETRANSLATE_API_KEY
 */
final class LibreTranslate
{
    private const CHUNK = 1400;

    public static function ensureSchema(): void
    {
        $pdo = Db::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS translation_cache (
              cache_key VARCHAR(64) NOT NULL PRIMARY KEY,
              translated MEDIUMTEXT NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS translation_usage (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED NOT NULL DEFAULT 0,
              engine VARCHAR(16) NOT NULL,
              billed TINYINT(1) NOT NULL DEFAULT 0,
              chars_in INT UNSIGNED NOT NULL DEFAULT 0,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_usage_user_created (user_id, created_at),
              KEY idx_usage_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public static function endpoint(): string
    {
        $url = trim((string) (getenv('LIBRETRANSLATE_URL') ?: ''));
        if ($url === '') {
            $url = getenv('DDEV_PROJECT') ? 'http://libretranslate:5000' : 'https://libretranslate.com';
        }
        return rtrim($url, '/');
    }

    public static function apiKey(): string
    {
        $key = trim((string) (getenv('LIBRETRANSLATE_API_KEY') ?: ''));
        $key = trim($key, " \t\"'");
        if ($key === '' || preg_match('/^(your_key_here|xxxxxx|changeme|api_key)$/i', $key)) {
            return '';
        }
        return $key;
    }

    public static function normalizeLang(?string $lang): string
    {
        $lang = strtolower(trim((string) $lang));
        return in_array($lang, ['en', 'de'], true) ? $lang : 'en';
    }

    /**
     * Translate text to target language. Empty input returns empty.
     * Throws RuntimeException on hard API failure when translation is required.
     *
     * $prefer: auto (DeepL if configured), deepl, or lt (local only, no DeepL quota).
     */
    public static function translate(string $text, string $target, string $source = 'auto', string $prefer = 'auto'): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $useFullLang = $prefer === 'deepl';
        $target = $useFullLang ? TranslateLanguages::normalize($target) : self::normalizeLang($target);
        $sourceNorm = $source === 'de' || $source === 'en' ? $source : 'auto';

        if (!$useFullLang && $sourceNorm === $target) {
            return $text;
        }

        $uid = Auth::id();
        if ($uid > 0 && class_exists('SuperAdmin') && !SuperAdmin::userCanTranslate($uid)) {
            throw new RuntimeException('PDF translation is disabled for this account. Ask the site admin to enable it.');
        }

        if (!$useFullLang && self::looksLikeTarget($text, $target)) {
            return $text;
        }
        $glossSource = $sourceNorm === 'en' || $sourceNorm === 'de' ? $sourceNorm : 'auto';
        $glossed = TranslationGlossary::lookup($text, $glossSource, $target);
        if ($glossed !== null) {
            self::ensureSchema();
            self::logUsage('glossary', 0, mb_strlen($text));
            return $glossed;
        }

        $prefer = $prefer === 'deepl' || $prefer === 'lt' ? $prefer : 'auto';
        $useDeepL = DeepL::configured() && $prefer !== 'lt';
        $cacheEngine = $useDeepL ? 'deepl' : 'lt';
        $key = hash('sha256', $cacheEngine . '|' . $sourceNorm . '|' . $target . '|' . $text);
        self::ensureSchema();
        $chars = mb_strlen($text);
        $cached = self::cacheGet($key);
        if ($cached !== null) {
            self::logUsage($cacheEngine, 0, $chars);
            return $cached;
        }

        $translated = '';
        $engine = 'lt';
        $billed = 0;
        if ($useDeepL) {
            try {
                $translated = self::viaDeepL($text, $target, $sourceNorm);
                $engine = 'deepl';
                $billed = 1;
            } catch (RuntimeException $deeplError) {
                if ($useFullLang) {
                    throw $deeplError;
                }
                try {
                    $translated = self::viaLibre($text, $target, $sourceNorm);
                    $engine = 'lt';
                } catch (RuntimeException) {
                    throw $deeplError;
                }
            }
        } else {
            $translated = self::viaLibre($text, $target, $sourceNorm);
        }
        self::cachePut($key, $translated);
        self::logUsage($engine, $billed, $chars);
        return $translated;
    }

    /** @param list<string> $texts @return list<string> */
    public static function translateMany(array $texts, string $target, string $source = 'auto'): array
    {
        $result = [];
        foreach ($texts as $t) {
            $result[] = self::translate((string) $t, $target, $source);
        }
        return $result;
    }

    /**
     * Translate multiline text line-by-line so glossary/cache apply per line.
     */
    public static function translateMultiline(string $text, string $target, string $source = 'auto', string $prefer = 'auto'): string
    {
        if ($text === '') {
            return '';
        }
        if (!str_contains($text, "\n")) {
            return self::translate($text, $target, $source, $prefer);
        }
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $out[] = trim($line) === '' ? '' : self::translate($line, $target, $source, $prefer);
        }
        return implode("\n", $out);
    }

    private static function looksLikeTarget(string $text, string $target): bool
    {
        if ($target === 'de') {
            return (bool) preg_match('/\b(und|der|die|das|für|mit|eine|einer|oder|bei|nach|über)\b/iu', $text)
                && !preg_match('/\b(the|and|with|for|experience|testing)\b/iu', $text);
        }
        // English target: treat as already EN if common EN words and few DE articles.
        return (bool) preg_match('/\b(the|and|with|for|experience|testing|skills)\b/iu', $text);
    }

    /** @return list<string> */
    private static function chunk(string $text): array
    {
        if (mb_strlen($text) <= self::CHUNK) {
            return [$text];
        }
        $parts = [];
        $remaining = $text;
        while ($remaining !== '') {
            if (mb_strlen($remaining) <= self::CHUNK) {
                $parts[] = $remaining;
                break;
            }
            $slice = mb_substr($remaining, 0, self::CHUNK);
            $break = max(
                mb_strrpos($slice, "\n") ?: 0,
                mb_strrpos($slice, '. ') ?: 0,
                mb_strrpos($slice, ' ') ?: 0
            );
            if ($break < 200) {
                $break = self::CHUNK;
            }
            $parts[] = mb_substr($remaining, 0, $break);
            $remaining = mb_substr($remaining, $break);
        }
        return $parts;
    }

    private static function viaDeepL(string $text, string $target, string $source): string
    {
        $parts = self::chunk($text);
        $out = [];
        foreach ($parts as $part) {
            $out[] = DeepL::translate($part, $target, $source);
        }
        return implode('', $out);
    }

    private static function viaLibre(string $text, string $target, string $source): string
    {
        $parts = self::chunk($text);
        $out = [];
        foreach ($parts as $part) {
            $out[] = self::request($part, $target, $source);
        }
        return implode('', $out);
    }

    private static function request(string $q, string $target, string $source): string
    {
        // Official body: q, source, target, api_key (key only on keyed hosts).
        // https://docs.libretranslate.com/guides/api_usage/
        $payload = [
            'q' => $q,
            'source' => $source === 'auto' ? 'auto' : $source,
            'target' => $target,
        ];
        $key = self::apiKey();
        $host = mb_strtolower(self::endpoint());
        $needsKey = str_contains($host, 'libretranslate.com');
        if ($needsKey && $key === '') {
            throw new RuntimeException(
                'libretranslate.com needs a paid API key. For local PDF DE, run `ddev restart` so the LibreTranslate container starts, set LIBRETRANSLATE_URL=http://libretranslate:5000, and leave LIBRETRANSLATE_API_KEY empty.'
            );
        }
        if ($key !== '') {
            $payload['api_key'] = $key;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Could not encode translation request.');
        }

        $url = self::endpoint() . '/translate';
        if (!function_exists('curl_init')) {
            throw new RuntimeException('curl required for LibreTranslate.');
        }
        $body = false;
        $code = 0;
        $err = '';
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Could not start translation request.');
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 90,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if (is_string($body) && $code >= 200 && $code < 300) {
                break;
            }
            if ($attempt < 4 && ($body === false || $code === 0 || $code >= 500)) {
                sleep($attempt * 2);
                continue;
            }
            break;
        }

        if (!is_string($body) || $code < 200 || $code >= 300) {
            $hint = '';
            if ($code === 403 || str_contains((string) $body, 'Invalid API key')) {
                $hint = ' Put a valid key from https://portal.libretranslate.com/ in LIBRETRANSLATE_API_KEY (not a placeholder). Self-hosting: set LIBRETRANSLATE_URL and leave the key empty.';
            } elseif ($key === '') {
                $hint = ' Set LIBRETRANSLATE_API_KEY or LIBRETRANSLATE_URL for a self-hosted instance.';
            }
            $detail = is_string($body) && $body !== '' ? ' ' . mb_substr(trim(strip_tags($body)), 0, 180) : ($err !== '' ? ' ' . $err : '');
            throw new RuntimeException('LibreTranslate failed (HTTP ' . $code . ').' . $hint . $detail);
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['translatedText'])) {
            throw new RuntimeException('LibreTranslate returned an unexpected response.');
        }
        return (string) $data['translatedText'];
    }

    private static function logUsage(string $engine, int $billed, int $chars): void
    {
        try {
            $uid = Auth::id();
            $stmt = Db::pdo()->prepare(
                'INSERT INTO translation_usage (user_id, engine, billed, chars_in) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$uid, $engine, $billed ? 1 : 0, max(0, $chars)]);
        } catch (Throwable) {
            // Usage stats must not break PDF DE.
        }
    }

    /** Internal rate: 1,000 billed characters = €0.05 (5 cents). */
    public const EURO_PER_1000_CHARS = 0.05;

    public static function euroCost(int $billedChars): float
    {
        return round(($billedChars / 1000) * self::EURO_PER_1000_CHARS, 2);
    }

    public static function formatEuro(int $billedChars): string
    {
        return '€' . number_format(self::euroCost($billedChars), 2, '.', '');
    }

    /**
     * Billed characters and euro cost by user for this month, last month, and this year.
     *
     * @return list<array{
     *   user_id: int,
     *   name: string,
     *   username: string,
     *   billed_chars: int,
     *   cached_chars: int,
     *   requests: int,
     *   billed_last_month: int,
     *   cached_last_month: int,
     *   requests_last_month: int,
     *   billed_year: int,
     *   cached_year: int,
     *   requests_year: int
     * }>
     */
    public static function usageByUserThisMonth(): array
    {
        self::ensureSchema();
        $sql = 'SELECT u.user_id,
                       COALESCE(usr.name, \'\') AS name,
                       COALESCE(usr.username, \'\') AS username,
                       SUM(CASE WHEN u.billed = 1 AND u.created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01\') THEN u.chars_in ELSE 0 END) AS billed_chars,
                       SUM(CASE WHEN u.billed = 0 AND u.created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01\') THEN u.chars_in ELSE 0 END) AS cached_chars,
                       SUM(CASE WHEN u.created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01\') THEN 1 ELSE 0 END) AS requests,
                       SUM(CASE WHEN u.billed = 1
                                 AND u.created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m-01\')
                                 AND u.created_at < DATE_FORMAT(NOW(), \'%Y-%m-01\')
                                THEN u.chars_in ELSE 0 END) AS billed_last_month,
                       SUM(CASE WHEN u.billed = 0
                                 AND u.created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m-01\')
                                 AND u.created_at < DATE_FORMAT(NOW(), \'%Y-%m-01\')
                                THEN u.chars_in ELSE 0 END) AS cached_last_month,
                       SUM(CASE WHEN u.created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m-01\')
                                 AND u.created_at < DATE_FORMAT(NOW(), \'%Y-%m-01\')
                                THEN 1 ELSE 0 END) AS requests_last_month,
                       SUM(CASE WHEN u.billed = 1 AND u.created_at >= DATE_FORMAT(NOW(), \'%Y-01-01\') THEN u.chars_in ELSE 0 END) AS billed_year,
                       SUM(CASE WHEN u.billed = 0 AND u.created_at >= DATE_FORMAT(NOW(), \'%Y-01-01\') THEN u.chars_in ELSE 0 END) AS cached_year,
                       SUM(CASE WHEN u.created_at >= DATE_FORMAT(NOW(), \'%Y-01-01\') THEN 1 ELSE 0 END) AS requests_year
                FROM translation_usage u
                LEFT JOIN users usr ON usr.id = u.user_id
                WHERE u.created_at >= LEAST(
                    DATE_FORMAT(NOW(), \'%Y-01-01\'),
                    DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m-01\')
                )
                GROUP BY u.user_id, usr.name, usr.username
                ORDER BY billed_chars DESC, billed_year DESC, requests DESC';
        $rows = Db::pdo()->query($sql)->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'user_id' => (int) $row['user_id'],
                'name' => (string) $row['name'],
                'username' => (string) $row['username'],
                'billed_chars' => (int) $row['billed_chars'],
                'cached_chars' => (int) $row['cached_chars'],
                'requests' => (int) $row['requests'],
                'billed_last_month' => (int) $row['billed_last_month'],
                'cached_last_month' => (int) $row['cached_last_month'],
                'requests_last_month' => (int) $row['requests_last_month'],
                'billed_year' => (int) $row['billed_year'],
                'cached_year' => (int) $row['cached_year'],
                'requests_year' => (int) $row['requests_year'],
            ];
        }
        return $out;
    }

    /**
     * @return array{
     *   billed_chars: int,
     *   cached_chars: int,
     *   requests: int,
     *   billed_last_month: int,
     *   cached_last_month: int,
     *   requests_last_month: int,
     *   billed_year: int,
     *   cached_year: int,
     *   requests_year: int
     * }
     */
    public static function usageForUserThisMonth(int $userId): array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT SUM(CASE WHEN billed = 1 AND created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01\') THEN chars_in ELSE 0 END) AS billed_chars,
                    SUM(CASE WHEN billed = 0 AND created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01\') THEN chars_in ELSE 0 END) AS cached_chars,
                    SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01\') THEN 1 ELSE 0 END) AS requests,
                    SUM(CASE WHEN billed = 1
                              AND created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m-01\')
                              AND created_at < DATE_FORMAT(NOW(), \'%Y-%m-01\')
                             THEN chars_in ELSE 0 END) AS billed_last_month,
                    SUM(CASE WHEN billed = 0
                              AND created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m-01\')
                              AND created_at < DATE_FORMAT(NOW(), \'%Y-%m-01\')
                             THEN chars_in ELSE 0 END) AS cached_last_month,
                    SUM(CASE WHEN created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m-01\')
                              AND created_at < DATE_FORMAT(NOW(), \'%Y-%m-01\')
                             THEN 1 ELSE 0 END) AS requests_last_month,
                    SUM(CASE WHEN billed = 1 AND created_at >= DATE_FORMAT(NOW(), \'%Y-01-01\') THEN chars_in ELSE 0 END) AS billed_year,
                    SUM(CASE WHEN billed = 0 AND created_at >= DATE_FORMAT(NOW(), \'%Y-01-01\') THEN chars_in ELSE 0 END) AS cached_year,
                    SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), \'%Y-01-01\') THEN 1 ELSE 0 END) AS requests_year
             FROM translation_usage
             WHERE user_id = ?
               AND created_at >= LEAST(
                   DATE_FORMAT(NOW(), \'%Y-01-01\'),
                   DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m-01\')
               )'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch() ?: [];
        return [
            'billed_chars' => (int) ($row['billed_chars'] ?? 0),
            'cached_chars' => (int) ($row['cached_chars'] ?? 0),
            'requests' => (int) ($row['requests'] ?? 0),
            'billed_last_month' => (int) ($row['billed_last_month'] ?? 0),
            'cached_last_month' => (int) ($row['cached_last_month'] ?? 0),
            'requests_last_month' => (int) ($row['requests_last_month'] ?? 0),
            'billed_year' => (int) ($row['billed_year'] ?? 0),
            'cached_year' => (int) ($row['cached_year'] ?? 0),
            'requests_year' => (int) ($row['requests_year'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{billed_chars: int, cached_chars: int, requests: int}
     */
    public static function usageForPeriod(array $row, string $period): array
    {
        if ($period === 'last') {
            return [
                'billed_chars' => (int) ($row['billed_last_month'] ?? 0),
                'cached_chars' => (int) ($row['cached_last_month'] ?? 0),
                'requests' => (int) ($row['requests_last_month'] ?? 0),
            ];
        }
        if ($period === 'year') {
            return [
                'billed_chars' => (int) ($row['billed_year'] ?? 0),
                'cached_chars' => (int) ($row['cached_year'] ?? 0),
                'requests' => (int) ($row['requests_year'] ?? 0),
            ];
        }
        return [
            'billed_chars' => (int) ($row['billed_chars'] ?? 0),
            'cached_chars' => (int) ($row['cached_chars'] ?? 0),
            'requests' => (int) ($row['requests'] ?? 0),
        ];
    }

    private static function cacheGet(string $key): ?string
    {
        $stmt = Db::pdo()->prepare('SELECT translated FROM translation_cache WHERE cache_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row === false ? null : (string) $row['translated'];
    }

    private static function cachePut(string $key, string $translated): void
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO translation_cache (cache_key, translated) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE translated = VALUES(translated), created_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$key, $translated]);
    }
}
