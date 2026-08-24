<?php

declare(strict_types=1);

final class PdfExport
{
    /**
     * Build absolute document URL for headless export.
     * Host PDF service should use the public ddev URL.
     */
    public static function documentUrl(string $doc, array $query = [], bool $forHost = false): string
    {
        $path = $doc === 'cover' ? '/cover-letter.php' : '/resume.php';
        $params = array_merge([
            'embed' => '1',
            'pdf' => '1',
        ], $query);
        $uid = Auth::id();
        if ($uid > 0 && empty($params['pdf_token'])) {
            $params['pdf_token'] = self::issueToken($uid);
        }
        $qs = http_build_query($params);

        if ($forHost) {
            $base = getenv('MNK_PUBLIC_URL') ?: 'https://mnk.ddev.site';
        } else {
            $base = getenv('MNK_PDF_BASE_URL') ?: 'http://127.0.0.1';
        }

        return rtrim($base, '/') . $path . '?' . $qs;
    }

    public static function issueToken(int $userId): string
    {
        $exp = (string) (time() + 180);
        $uid = (string) $userId;
        $sig = hash_hmac('sha256', $uid . '|' . $exp, self::tokenSecret());
        return rtrim(strtr(base64_encode($uid . '.' . $exp . '.' . $sig), '+/', '-_'), '=');
    }

    public static function verifyToken(string $token): ?int
    {
        $b64 = strtr($token, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($b64, true);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $parts = explode('.', $raw, 3);
        if (count($parts) !== 3) {
            return null;
        }
        [$uid, $exp, $sig] = $parts;
        if (!ctype_digit($uid) || !ctype_digit($exp) || (int) $exp < time()) {
            return null;
        }
        $expected = hash_hmac('sha256', $uid . '|' . $exp, self::tokenSecret());
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        return (int) $uid;
    }

    public static function acceptExportToken(): void
    {
        $token = (string) ($_GET['pdf_token'] ?? '');
        if ($token === '') {
            return;
        }
        $embed = (string) ($_GET['embed'] ?? '') === '1' || (string) ($_GET['pdf'] ?? '') === '1';
        if (!$embed) {
            return;
        }
        $uid = self::verifyToken($token);
        if ($uid === null || $uid <= 0) {
            return;
        }
        Auth::impersonate($uid);
    }

    private static function tokenSecret(): string
    {
        $secret = (string) (getenv('MNK_PDF_SECRET') ?: '');
        if ($secret !== '') {
            return $secret;
        }
        return hash('sha256', (string) (getenv('DATABASE_URL') ?: 'mnk-pdf'));
    }

    /** ASCII slug for filenames: "Muqaddas Khan" → muqaddas_khan */
    public static function personSlug(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'document';
        }
        $name = strtr($name, [
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
        ]);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if (!is_string($ascii) || trim($ascii) === '') {
            $ascii = $name;
        }
        $slug = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
        $slug = trim($slug, '_');
        return $slug !== '' ? $slug : 'document';
    }

    /** External download name only — never version titles, Main, or company. */
    public static function safeFilename(string $doc, string $name): string
    {
        $suffix = $doc === 'cover' ? 'cover_letter' : 'resume';
        return self::personSlug($name) . '_' . $suffix . '.pdf';
    }

    /** Browser print / Save as PDF document title (no .pdf). */
    public static function printDocumentTitle(string $doc, string $name): string
    {
        return (string) preg_replace('/\.pdf$/i', '', self::safeFilename($doc, $name));
    }

    /**
     * Generate a PDF file; returns absolute path on success.
     *
     * @throws RuntimeException
     */
    public static function generate(string $doc, array $query = []): string
    {
        $root = dirname(__DIR__);
        $dir = $root . '/storage/pdfs';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create storage/pdfs');
        }

        $token = bin2hex(random_bytes(8));
        $outfile = $dir . '/export-' . $token . '.pdf';

        $errors = [];

        try {
            self::generateViaService($doc, $query, $outfile);
            return $outfile;
        } catch (Throwable $e) {
            $errors[] = 'service: ' . $e->getMessage();
        }

        try {
            self::generateViaNode($doc, $query, $outfile);
            return $outfile;
        } catch (Throwable $e) {
            $errors[] = 'node: ' . $e->getMessage();
        }

        throw new RuntimeException(
            "PDF export failed.\n- " . implode("\n- ", $errors) .
            "\nStart the host PDF helper: ddev pdf-server   (or: node scripts/pdf-server.mjs)"
        );
    }

    private static function generateViaService(string $doc, array $query, string $outfile): void
    {
        $service = getenv('MNK_PDF_SERVICE') ?: 'http://host.docker.internal:18477';
        $url = self::documentUrl($doc, $query, true);
        $payload = json_encode(['url' => $url], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('Could not encode PDF request');
        }

        $ch = curl_init(rtrim($service, '/') . '/pdf');
        if ($ch === false) {
            throw new RuntimeException('curl unavailable');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 90,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status !== 200 || strlen((string) $body) < 100) {
            throw new RuntimeException($err !== '' ? $err : ('HTTP ' . $status . ' ' . substr((string) $body, 0, 200)));
        }

        if (file_put_contents($outfile, $body) === false) {
            throw new RuntimeException('Could not write PDF file');
        }
    }

    private static function generateViaNode(string $doc, array $query, string $outfile): void
    {
        $root = dirname(__DIR__);
        $script = $root . '/scripts/export-pdf.mjs';
        if (!is_file($script)) {
            throw new RuntimeException('Missing scripts/export-pdf.mjs');
        }

        $url = self::documentUrl($doc, $query, false);
        $node = self::findNode();
        $cmd = [$node, $script, $url, $outfile];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = array_merge($_ENV, $_SERVER);
        $chrome = self::findChrome();
        if ($chrome) {
            $env['CHROME_PATH'] = $chrome;
            $env['PUPPETEER_EXECUTABLE_PATH'] = $chrome;
        }

        $proc = proc_open($cmd, $descriptor, $pipes, $root, $env);
        if (!is_resource($proc)) {
            throw new RuntimeException('Failed to start PDF export process');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code !== 0 || !is_file($outfile) || filesize($outfile) < 100) {
            @unlink($outfile);
            $detail = trim($stderr !== '' ? $stderr : $stdout);
            throw new RuntimeException($detail !== '' ? $detail : 'export-pdf.mjs failed');
        }
    }

    private static function findNode(): string
    {
        foreach (['node', '/usr/local/bin/node', '/usr/bin/node'] as $bin) {
            $which = $bin === 'node' ? trim((string) shell_exec('command -v node 2>/dev/null')) : $bin;
            if ($which !== '' && is_executable($which)) {
                return $which;
            }
        }
        throw new RuntimeException('Node.js is required for local PDF export');
    }

    private static function findChrome(): ?string
    {
        $candidates = array_filter([
            getenv('CHROME_PATH') ?: null,
            getenv('PUPPETEER_EXECUTABLE_PATH') ?: null,
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
