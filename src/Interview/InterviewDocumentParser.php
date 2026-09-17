<?php

declare(strict_types=1);

namespace KaamFit\Interview;

/**
 * Extract plain text from PDF / DOCX / TXT / Markdown / paste.
 * No third-party scraping — only user-provided files or pasted text.
 */
final class InterviewDocumentParser
{
    /**
     * @return array{text:string,format:string,filename:string}
     */
    public static function parseUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Upload failed.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \InvalidArgumentException('Invalid upload.');
        }
        $name = (string) ($file['name'] ?? 'upload');
        $size = (int) ($file['size'] ?? 0);
        if ($size > 12 * 1024 * 1024) {
            throw new \InvalidArgumentException('File too large (max 12MB).');
        }
        return self::parsePath($tmp, $name);
    }

    /**
     * @return array{text:string,format:string,filename:string}
     */
    public static function parsePath(string $path, string $filename = ''): array
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException('File not readable.');
        }
        $filename = $filename !== '' ? $filename : basename($path);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $text = match ($ext) {
            'pdf' => self::fromPdf($path),
            'docx' => self::fromDocx($path),
            'md', 'markdown' => self::fromPlain($path),
            'txt', 'text' => self::fromPlain($path),
            'json' => self::fromPlain($path),
            default => self::guess($path, $ext),
        };
        $text = self::normalizeWhitespace($text);
        if (trim($text) === '') {
            throw new \InvalidArgumentException('No text could be extracted from the document.');
        }
        return [
            'text' => $text,
            'format' => $ext !== '' ? $ext : 'txt',
            'filename' => $filename,
        ];
    }

    /** @return array{text:string,format:string,filename:string} */
    public static function fromPaste(string $paste): array
    {
        $text = self::normalizeWhitespace($paste);
        if (trim($text) === '') {
            throw new \InvalidArgumentException('Paste is empty.');
        }
        return ['text' => $text, 'format' => 'paste', 'filename' => 'paste.txt'];
    }

    private static function guess(string $path, string $ext): string
    {
        $head = (string) file_get_contents($path, false, null, 0, 8);
        if (str_starts_with($head, '%PDF')) {
            return self::fromPdf($path);
        }
        if (str_starts_with($head, 'PK')) {
            return self::fromDocx($path);
        }
        return self::fromPlain($path);
    }

    private static function fromPlain(string $path): string
    {
        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            return '';
        }
        // Strip UTF-8 BOM
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        return $raw;
    }

    private static function fromPdf(string $path): string
    {
        $bin = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($bin !== '') {
            $cmd = escapeshellarg($bin) . ' -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null';
            $out = shell_exec($cmd);
            if (is_string($out) && trim($out) !== '') {
                return $out;
            }
        }
        // Minimal fallback: extract readable strings from PDF streams
        $raw = (string) file_get_contents($path);
        if (preg_match_all('/\((\\\\.|[^\\\\)]){3,}\)/s', $raw, $m)) {
            $parts = [];
            foreach ($m[0] as $chunk) {
                $s = substr($chunk, 1, -1);
                $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\r", "\t", '(', ')'], $s);
                if (preg_match('/[A-Za-zÄÖÜäöüß]{3,}/u', $s)) {
                    $parts[] = $s;
                }
            }
            return implode("\n", $parts);
        }
        throw new \InvalidArgumentException('Could not extract text from PDF (install pdftotext for best results).');
    }

    private static function fromDocx(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \InvalidArgumentException('ZipArchive required for DOCX.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('Invalid DOCX file.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml) || $xml === '') {
            throw new \InvalidArgumentException('DOCX has no document.xml.');
        }
        $xml = preg_replace('/<w:tab[^\/]*\/>/', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<w:br[^\/]*\/>/', "\n", $xml) ?? $xml;
        $text = strip_tags($xml);
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }
}
