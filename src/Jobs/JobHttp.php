<?php

declare(strict_types=1);

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
}
