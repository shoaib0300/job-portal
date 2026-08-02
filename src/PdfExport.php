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
        $qs = http_build_query($params);

        if ($forHost) {
            $base = getenv('MNK_PUBLIC_URL') ?: 'https://mnk.ddev.site';
        } else {
            $base = getenv('MNK_PDF_BASE_URL') ?: 'http://127.0.0.1';
        }

        return rtrim($base, '/') . $path . '?' . $qs;
    }

    public static function safeFilename(string $doc, string $name): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($name)) ?: 'document';
        $base = trim($base, '-');
        $suffix = $doc === 'cover' ? 'Cover-Letter' : 'Resume';
        return $base . '-' . $suffix . '.pdf';
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
