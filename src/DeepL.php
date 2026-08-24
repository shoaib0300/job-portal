<?php

declare(strict_types=1);

/**
 * DeepL Translate API (Free keys end with :fx → api-free.deepl.com).
 * Env: DEEPL_API_KEY
 */
final class DeepL
{
    public static function apiKey(): string
    {
        $key = trim((string) (getenv('DEEPL_API_KEY') ?: ''));
        $key = trim($key, " \t\"'");
        if ($key === '' || preg_match('/^(your_key_here|xxxxxx|changeme|api_key)$/i', $key)) {
            return '';
        }
        return $key;
    }

    public static function configured(): bool
    {
        return self::apiKey() !== '';
    }

    public static function endpoint(): string
    {
        $url = trim((string) (getenv('DEEPL_API_URL') ?: ''));
        if ($url !== '') {
            return rtrim($url, '/');
        }
        $key = self::apiKey();
        if (str_ends_with(strtolower($key), ':fx')) {
            return 'https://api-free.deepl.com/v2/translate';
        }
        return 'https://api.deepl.com/v2/translate';
    }

    public static function translate(string $text, string $target, string $source = 'en'): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $key = self::apiKey();
        if ($key === '') {
            throw new RuntimeException('DEEPL_API_KEY is not set.');
        }

        $targetLang = strtoupper($target === 'de' ? 'DE' : 'EN');
        $payload = [
            'text' => [$text],
            'target_lang' => $targetLang,
            'preserve_formatting' => true,
        ];
        if ($source === 'de' || $source === 'en') {
            $payload['source_lang'] = strtoupper($source);
        }
        if ($targetLang === 'DE') {
            $payload['formality'] = 'prefer_more';
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Could not encode DeepL request.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('curl required for DeepL.');
        }

        $ch = curl_init(self::endpoint());
        if ($ch === false) {
            throw new RuntimeException('Could not start DeepL request.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: DeepL-Auth-Key ' . $key,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if (!is_string($body) || $code < 200 || $code >= 300) {
            $detail = is_string($body) && $body !== '' ? ' ' . mb_substr(trim(strip_tags($body)), 0, 180) : ($err !== '' ? ' ' . $err : '');
            $hint = '';
            if ($code === 403) {
                $hint = ' DeepL rejected the API key.';
            } elseif ($code === 456) {
                $hint = ' DeepL Free character quota is used up this month.';
            }
            throw new RuntimeException('DeepL failed (HTTP ' . $code . ').' . $hint . $detail);
        }

        $data = json_decode($body, true);
        $out = $data['translations'][0]['text'] ?? null;
        if (!is_string($out)) {
            throw new RuntimeException('DeepL returned an unexpected response.');
        }
        return $out;
    }

    public static function apiHost(): string
    {
        $url = self::endpoint();
        if (preg_match('#^https?://[^/]+#', $url, $m) === 1) {
            return $m[0];
        }
        return str_ends_with(strtolower(self::apiKey()), ':fx')
            ? 'https://api-free.deepl.com'
            : 'https://api.deepl.com';
    }

    /**
     * Whole-key quota from DeepL (not split by app user).
     *
     * @return array{character_count: int, character_limit: int}|null
     */
    public static function accountUsage(): ?array
    {
        $key = self::apiKey();
        if ($key === '' || !function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init(self::apiHost() . '/v2/usage');
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['Authorization: DeepL-Auth-Key ' . $key],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $code < 200 || $code >= 300) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['character_count'], $data['character_limit'])) {
            return null;
        }
        return [
            'character_count' => (int) $data['character_count'],
            'character_limit' => (int) $data['character_limit'],
        ];
    }
}
