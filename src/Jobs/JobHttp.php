<?php

declare(strict_types=1);

namespace KaamMilo\Jobs;

use App;


final class JobHttp
{
    /**
     * @param list<string> $headers
     */
    public static function get(string $url, array $headers = [], int $timeout = 12): ?string
    {
        $headers = array_merge(['Accept: application/json, text/xml, */*', 'User-Agent: MNK-Jobs/1.0'], $headers);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($body) || $code < 200 || $code >= 300) {
                return null;
            }
            return $body;
        }

        $headerStr = implode("\r\n", $headers);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headerStr,
                'timeout' => $timeout,
                'ignore_errors' => true,
                'follow_location' => 1,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : null;
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>|null
     */
    public static function getJson(string $url, array $headers = [], int $timeout = 12): ?array
    {
        $raw = self::get($url, $headers, $timeout);
        if ($raw === null) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>|null
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 15): ?array
    {
        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: MNK-Jobs/1.0',
        ], $headers);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || !function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $code < 200 || $code >= 300) {
            return null;
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /**
     * POST JSON and return decoded body plus HTTP status (for Bright Data scrape/trigger).
     *
     * @param list<string> $headers
     * @return array{code:int, data:mixed, raw:string}|null
     */
    public static function postJsonResult(string $url, array|string $payload, array $headers = [], int $timeout = 45): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $json = is_string($payload)
            ? $payload
            : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return null;
        }
        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: MNK-Jobs/1.0',
        ], $headers);
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body)) {
            return null;
        }
        $data = json_decode($body, true);

        return ['code' => $code, 'data' => $data, 'raw' => $body];
    }

    /**
     * @param list<string> $headers
     * @return array{code:int, data:mixed, raw:string}|null
     */
    public static function getJsonResult(string $url, array $headers = [], int $timeout = 20): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $headers = array_merge([
            'Accept: application/json',
            'User-Agent: MNK-Jobs/1.0',
        ], $headers);
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body)) {
            return null;
        }
        $data = json_decode($body, true);

        return ['code' => $code, 'data' => $data, 'raw' => $body];
    }

    /**
     * @param array<string, array{url:string,headers?:list<string>}> $requests
     * @return array<string, string|null>
     */
    public static function multiGet(array $requests, int $timeout = 10): array
    {
        $out = [];
        foreach (array_keys($requests) as $key) {
            $out[$key] = null;
        }
        if ($requests === [] || !function_exists('curl_multi_init')) {
            foreach ($requests as $key => $req) {
                $out[$key] = self::get($req['url'], $req['headers'] ?? [], $timeout);
            }
            return $out;
        }

        $mh = curl_multi_init();
        $handles = [];
        foreach ($requests as $key => $req) {
            $ch = curl_init($req['url']);
            if ($ch === false) {
                continue;
            }
            $headers = array_merge(['Accept: application/json, text/xml, */*', 'User-Agent: MNK-Jobs/1.0'], $req['headers'] ?? []);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $key => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $out[$key] = (is_string($body) && $code >= 200 && $code < 300) ? $body : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $out;
    }

    /**
     * Parallel JSON POSTs (e.g. Bright Data Unlocker for several SERP boards).
     *
     * @param array<string, array{url:string,payload:array<string,mixed>,headers?:list<string>}> $requests
     * @return array<string, array<string, mixed>|null>
     */
    public static function multiPostJson(array $requests, int $timeout = 12): array
    {
        $out = [];
        foreach (array_keys($requests) as $key) {
            $out[$key] = null;
        }
        if ($requests === [] || !function_exists('curl_multi_init')) {
            foreach ($requests as $key => $req) {
                $out[$key] = self::postJson(
                    $req['url'],
                    $req['payload'],
                    $req['headers'] ?? [],
                    $timeout
                );
            }
            return $out;
        }

        $mh = curl_multi_init();
        $handles = [];
        foreach ($requests as $key => $req) {
            $json = json_encode($req['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json)) {
                continue;
            }
            $ch = curl_init($req['url']);
            if ($ch === false) {
                continue;
            }
            $headers = array_merge([
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: MNK-Jobs/1.0',
            ], $req['headers'] ?? []);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $key => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            if (!is_string($body) || $code < 200 || $code >= 300) {
                continue;
            }
            $data = json_decode($body, true);
            $out[$key] = is_array($data) ? $data : null;
        }
        curl_multi_close($mh);
        return $out;
    }

    /**
     * Fetch HTML via Bright Data Web Unlocker (for bot-blocked career pages like DIS AG).
     */
    public static function unlockHtml(string $url, int $timeout = 22): ?string
    {
        $token = trim((string) (getenv('BRIGHT_DATA_API_TOKEN') ?: ''));
        if ($token === '' || !function_exists('curl_init')) {
            return null;
        }
        $zone = trim((string) (getenv('BRIGHT_DATA_ZONE') ?: 'web_unlocker1'));
        $payload = json_encode(
            ['zone' => $zone, 'url' => $url, 'format' => 'raw'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if (!is_string($payload)) {
            return null;
        }
        $ch = curl_init('https://api.brightdata.com/request');
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: */*',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $body === '' || $code < 200 || $code >= 300) {
            return null;
        }
        $trim = ltrim($body);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $data = json_decode($body, true);
            if (is_array($data)) {
                if (isset($data['body']) && is_string($data['body']) && $data['body'] !== '') {
                    return $data['body'];
                }
                if (isset($data['html']) && is_string($data['html']) && $data['html'] !== '') {
                    return $data['html'];
                }
            }
        }
        if (str_contains(mb_strtolower(mb_substr($body, 0, 500)), 'request rejected')) {
            return null;
        }
        return $body;
    }
}
