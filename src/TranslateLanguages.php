<?php

declare(strict_types=1);

/** DeepL target languages for PDF translation. */
final class TranslateLanguages
{
    /** @var array<string, string>|null */
    private static ?array $resolvedTargets = null;

    /** @return array<string, string> code => English label */
    public static function targets(): array
    {
        if (self::$resolvedTargets !== null) {
            return self::$resolvedTargets;
        }
        $cached = self::readCache();
        if ($cached !== null) {
            self::$resolvedTargets = $cached;
            return $cached;
        }
        $live = DeepL::configured() ? DeepL::fetchTargetLanguages() : null;
        if ($live !== null && $live !== []) {
            self::writeCache($live);
            self::$resolvedTargets = $live;
            return $live;
        }
        self::$resolvedTargets = self::staticFallback();
        return self::$resolvedTargets;
    }

    public static function normalize(?string $code): string
    {
        $code = strtolower(str_replace('_', '-', trim((string) $code)));
        $aliases = [
            'en' => 'en-gb',
            'pt' => 'pt-pt',
            'zh-cn' => 'zh-hans',
            'zh-tw' => 'zh-hant',
        ];
        if (isset($aliases[$code])) {
            $code = $aliases[$code];
        }
        $targets = self::targets();
        if (isset($targets[$code])) {
            return $code;
        }
        foreach ($targets as $key => $_label) {
            if (str_replace('-', '', $key) === str_replace('-', '', $code)) {
                return $key;
            }
        }
        return isset(self::staticFallback()['de']) ? 'de' : array_key_first(self::staticFallback()) ?? 'de';
    }

    public static function label(string $code): string
    {
        $code = self::normalize($code);
        return self::targets()[$code] ?? $code;
    }

    /** DeepL API target_lang value. */
    public static function toDeepLTarget(string $code): string
    {
        $code = self::normalize($code);
        $parts = explode('-', $code);
        $parts = array_map(static fn(string $p): string => strtoupper($p), $parts);
        return implode('-', $parts);
    }

    /** DeepL API source_lang value (empty = auto-detect). */
    public static function toDeepLSource(string $code): string
    {
        $code = strtolower(str_replace('_', '-', trim($code)));
        if ($code === '' || $code === 'auto') {
            return '';
        }
        if ($code === 'en' || str_starts_with($code, 'en-')) {
            return 'EN';
        }
        if ($code === 'de' || str_starts_with($code, 'de-')) {
            return 'DE';
        }
        if ($code === 'pt' || str_starts_with($code, 'pt-')) {
            return 'PT';
        }
        if ($code === 'zh' || str_starts_with($code, 'zh-')) {
            return 'ZH';
        }
        if ($code === 'fr' || str_starts_with($code, 'fr-')) {
            return 'FR';
        }
        if (isset(self::targets()[$code])) {
            return strtoupper(explode('-', $code)[0]);
        }
        return '';
    }

    /** @return list<array{code: string, label: string}> */
    public static function optionsForJs(): array
    {
        $out = [];
        foreach (self::targets() as $code => $label) {
            $out[] = ['code' => $code, 'label' => $label];
        }
        usort($out, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));
        return $out;
    }

    public static function filenameSuffix(string $code): string
    {
        $code = self::normalize($code);
        return preg_replace('/[^a-z0-9]+/', '_', $code) ?? 'lang';
    }

    public static function count(): int
    {
        return count(self::targets());
    }

    /** @return array<string, string>|null */
    private static function readCache(): ?array
    {
        $path = self::cachePath();
        if (!is_readable($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['expires'] ?? 0) < time()) {
            return null;
        }
        $langs = $data['languages'] ?? null;
        return is_array($langs) && $langs !== [] ? $langs : null;
    }

    /** @param array<string, string> $languages */
    private static function writeCache(array $languages): void
    {
        $path = self::cachePath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $payload = json_encode([
            'expires' => time() + 86400,
            'languages' => $languages,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($payload)) {
            file_put_contents($path, $payload);
        }
    }

    private static function cachePath(): string
    {
        return dirname(__DIR__) . '/storage/cache/deepl-target-languages.json';
    }

    /** Official DeepL list (125 languages) — used when API is unavailable. */
    /** @return array<string, string> */
    public static function staticFallback(): array
    {
        return [
            'ace' => 'Acehnese',
            'af' => 'Afrikaans',
            'sq' => 'Albanian',
            'ar' => 'Arabic',
            'an' => 'Aragonese',
            'hy' => 'Armenian',
            'as' => 'Assamese',
            'ay' => 'Aymara',
            'az' => 'Azerbaijani',
            'ba' => 'Bashkir',
            'eu' => 'Basque',
            'be' => 'Belarusian',
            'bn' => 'Bengali',
            'bho' => 'Bhojpuri',
            'bs' => 'Bosnian',
            'br' => 'Breton',
            'bg' => 'Bulgarian',
            'my' => 'Burmese',
            'yue' => 'Cantonese',
            'ca' => 'Catalan',
            'ceb' => 'Cebuano',
            'zh-hans' => 'Chinese (simplified)',
            'zh-hant' => 'Chinese (traditional)',
            'zh' => 'Chinese',
            'hr' => 'Croatian',
            'cs' => 'Czech',
            'da' => 'Danish',
            'prs' => 'Dari',
            'nl' => 'Dutch',
            'en' => 'English',
            'en-us' => 'English (US)',
            'en-gb' => 'English (UK)',
            'eo' => 'Esperanto',
            'et' => 'Estonian',
            'fi' => 'Finnish',
            'fr' => 'French',
            'fr-ca' => 'French (Canadian)',
            'fr-fr' => 'French (France)',
            'gl' => 'Galician',
            'ka' => 'Georgian',
            'de' => 'German',
            'de-de' => 'German (Germany)',
            'de-ch' => 'German (Swiss)',
            'el' => 'Greek',
            'gn' => 'Guarani',
            'gu' => 'Gujarati',
            'ht' => 'Haitian Creole',
            'ha' => 'Hausa',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'hu' => 'Hungarian',
            'is' => 'Icelandic',
            'ig' => 'Igbo',
            'id' => 'Indonesian',
            'ga' => 'Irish',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'jv' => 'Javanese',
            'pam' => 'Kapampangan',
            'kk' => 'Kazakh',
            'gom' => 'Konkani',
            'ko' => 'Korean',
            'kmr' => 'Kurdish (Kurmanji)',
            'ckb' => 'Kurdish (Sorani)',
            'ky' => 'Kyrgyz',
            'la' => 'Latin',
            'lv' => 'Latvian',
            'ln' => 'Lingala',
            'lt' => 'Lithuanian',
            'lmo' => 'Lombard',
            'lb' => 'Luxembourgish',
            'mk' => 'Macedonian',
            'mai' => 'Maithili',
            'mg' => 'Malagasy',
            'ms' => 'Malay',
            'ml' => 'Malayalam',
            'mt' => 'Maltese',
            'mi' => 'Maori',
            'mr' => 'Marathi',
            'mn' => 'Mongolian',
            'ne' => 'Nepali',
            'nb' => 'Norwegian (bokmål)',
            'oc' => 'Occitan',
            'om' => 'Oromo',
            'pag' => 'Pangasinan',
            'ps' => 'Pashto',
            'fa' => 'Persian',
            'pl' => 'Polish',
            'pt-br' => 'Portuguese (Brazil)',
            'pt-pt' => 'Portuguese (Europe)',
            'pt' => 'Portuguese',
            'pa' => 'Punjabi',
            'qu' => 'Quechua',
            'ro' => 'Romanian',
            'ru' => 'Russian',
            'sa' => 'Sanskrit',
            'sr' => 'Serbian',
            'st' => 'Sesotho',
            'scn' => 'Sicilian',
            'sk' => 'Slovak',
            'sl' => 'Slovenian',
            'es' => 'Spanish',
            'es-419' => 'Spanish (Latin America)',
            'su' => 'Sundanese',
            'sw' => 'Swahili',
            'sv' => 'Swedish',
            'tl' => 'Tagalog',
            'tg' => 'Tajik',
            'ta' => 'Tamil',
            'tt' => 'Tatar',
            'te' => 'Telugu',
            'th' => 'Thai',
            'ts' => 'Tsonga',
            'tn' => 'Tswana',
            'tr' => 'Turkish',
            'tk' => 'Turkmen',
            'uk' => 'Ukrainian',
            'ur' => 'Urdu',
            'uz' => 'Uzbek',
            'vi' => 'Vietnamese',
            'cy' => 'Welsh',
            'wo' => 'Wolof',
            'xh' => 'Xhosa',
            'yi' => 'Yiddish',
            'zu' => 'Zulu',
        ];
    }
}
