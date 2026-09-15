<?php

declare(strict_types=1);

/**
 * Strip unnecessary PDF Info/XMP metadata from exported application documents.
 * Does not alter visible page content.
 */
final class PdfSanitize
{
    /** Patterns that must never appear in PDF metadata (case-insensitive). */
    public const FORBIDDEN_META_NEEDLES = [
        'kaamfit',
        'kaam-fit',
        'kaam_fit',
        'mnk-pdf',
        'ddev.site',
        'puppeteer',
        'headlesschrome',
        'skia/pdf',
        'ghostscript',
        '/var/www',
        'storage/pdfs',
        'pdf_token',
        'host.docker.internal',
    ];

    /**
     * Rewrite Info/XMP metadata to generic, candidate-oriented values.
     * Returns absolute path (updates file in place when possible).
     *
     * @param array{title?:string,author?:string,subject?:string} $meta
     */
    public static function clean(string $infile, array $meta = []): string
    {
        if (!is_file($infile) || filesize($infile) < 100) {
            return $infile;
        }

        $title = self::safeMetaString($meta['title'] ?? 'Application Documents');
        $author = self::safeMetaString($meta['author'] ?? 'Candidate');
        $subject = self::safeMetaString($meta['subject'] ?? 'Application Documents');
        $creator = 'PDF';
        $producer = 'PDF';

        // Ghostscript pass: PDF 1.4 + DOCINFO via pdfmark (full Title/Author/Subject).
        // GS may still inject Producer; binary scrub removes that afterwards.
        $gs = trim((string) shell_exec('command -v gs 2>/dev/null'));
        $docInfoSet = false;
        if ($gs !== '') {
            $dir = dirname($infile);
            $tmp = $dir . '/sanitized-' . bin2hex(random_bytes(6)) . '.pdf';
            $mark = $dir . '/pdfmark-' . bin2hex(random_bytes(4)) . '.ps';
            $markBody = sprintf(
                "[ /Title (%s)\n  /Author (%s)\n  /Subject (%s)\n  /Creator (%s)\n  /Keywords ()\n  /DOCINFO pdfmark\n",
                self::pdfLiteralEscape($title),
                self::pdfLiteralEscape($author),
                self::pdfLiteralEscape($subject),
                self::pdfLiteralEscape($creator)
            );
            file_put_contents($mark, $markBody);
            $cmd = sprintf(
                '%s -dBATCH -dNOPAUSE -dSAFER -dQUIET -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 '
                . '-dPDFSETTINGS=/prepress -dDetectDuplicateImages=true '
                . '-sOutputFile=%s %s %s 2>&1',
                escapeshellcmd($gs),
                escapeshellarg($tmp),
                escapeshellarg($infile),
                escapeshellarg($mark)
            );
            exec($cmd, $out, $code);
            @unlink($mark);
            if ($code === 0 && is_file($tmp) && filesize($tmp) > 100) {
                if (!@rename($tmp, $infile)) {
                    @unlink($infile);
                    @rename($tmp, $infile);
                }
                $docInfoSet = true;
            } else {
                @unlink($tmp);
            }
        }

        $bin = (string) file_get_contents($infile);
        if ($bin === '' || !str_starts_with($bin, '%PDF')) {
            return $infile;
        }

        $bin = self::scrubBinaryMetadata(
            $bin,
            $title,
            $author,
            $subject,
            $creator,
            $producer,
            !$docInfoSet
        );
        file_put_contents($infile, $bin);

        return $infile;
    }

    /**
     * Length-preserving metadata scrub so xref offsets stay valid.
     *
     * @param bool $rewriteDocInfo When false, Title/Author/Subject were already set via pdfmark.
     */
    private static function scrubBinaryMetadata(
        string $bin,
        string $title,
        string $author,
        string $subject,
        string $creator,
        string $producer,
        bool $rewriteDocInfo = true
    ): string {
        // Neutralize known generator fingerprints anywhere in the file (Info + XMP).
        $bin = preg_replace_callback(
            '/HeadlessChrome\/[0-9.]+/',
            static function (array $m): string {
                return str_pad('PDF', strlen($m[0]), ' ', STR_PAD_RIGHT);
            },
            $bin
        ) ?? $bin;
        $bin = preg_replace_callback(
            '/Skia\/PDF m[0-9]+/',
            static function (array $m): string {
                return str_pad('PDF', strlen($m[0]), ' ', STR_PAD_RIGHT);
            },
            $bin
        ) ?? $bin;
        $bin = preg_replace_callback(
            '/GPL Ghostscript [0-9.]+/',
            static function (array $m): string {
                return str_pad('PDF', strlen($m[0]), ' ', STR_PAD_RIGHT);
            },
            $bin
        ) ?? $bin;
        $bin = preg_replace_callback(
            '/Mozilla\/5\.0 \(X11; Linux x86_64\) AppleWebKit\/537\.36 \(KHTML, like Gecko\) [^\n<)]+/',
            static function (array $m): string {
                return str_pad('PDF', strlen($m[0]), ' ', STR_PAD_RIGHT);
            },
            $bin
        ) ?? $bin;

        // Force Creator/Producer to neutral "PDF" (length-padded).
        foreach (['Creator', 'Producer'] as $key) {
            $bin = preg_replace_callback(
                '/\/' . $key . '\s*\(((?:\\\\\)|\\\\.|[^\\\\)])*)\)/s',
                static function (array $m) use ($key): string {
                    $replacement = '/' . $key . ' (PDF)';

                    return str_pad($replacement, strlen($m[0]), ' ', STR_PAD_RIGHT);
                },
                $bin
            ) ?? $bin;
        }

        // Only rewrite Title/Author/Subject when pdfmark did not set them (no Ghostscript).
        if ($rewriteDocInfo) {
            foreach (
                [
                    'Title' => $title,
                    'Author' => $author,
                    'Subject' => $subject,
                    'Keywords' => '',
                ] as $key => $value
            ) {
                $bin = self::replaceInfoField($bin, $key, $value);
            }
        }

        // XMP CreatorTool / Producer
        $bin = preg_replace_callback(
            '/<xmp:CreatorTool>.*?<\/xmp:CreatorTool>/s',
            static function (array $m) use ($creator): string {
                $inner = '<xmp:CreatorTool>' . htmlspecialchars($creator, ENT_XML1) . '</xmp:CreatorTool>';

                return str_pad($inner, strlen($m[0]), ' ', STR_PAD_RIGHT);
            },
            $bin
        ) ?? $bin;
        $bin = preg_replace_callback(
            '/<pdf:Producer>.*?<\/pdf:Producer>/s',
            static function (array $m) use ($producer): string {
                $inner = '<pdf:Producer>' . htmlspecialchars($producer, ENT_XML1) . '</pdf:Producer>';

                return str_pad($inner, strlen($m[0]), ' ', STR_PAD_RIGHT);
            },
            $bin
        ) ?? $bin;

        // Strip KaamFit / internal host leftovers from metadata regions only (not body text streams if possible).
        foreach (['KaamFit', 'kaamfit', 'kaam-fit', 'kaamfit.ddev.site', 'host.docker.internal', 'pdf_token='] as $needle) {
            if ($needle !== '' && str_contains($bin, $needle)) {
                $bin = str_replace($needle, str_repeat(' ', strlen($needle)), $bin);
            }
        }

        return $bin;
    }

    private static function replaceInfoField(string $bin, string $key, string $value): string
    {
        $escaped = self::pdfLiteralEscape($value);
        // Match /Title (....) or /Title(....)
        $pattern = '/\/' . preg_quote($key, '/') . '\s*\(((?:\\\\\)|\\\\.|[^\\\\)])*)\)/s';

        return preg_replace_callback(
            $pattern,
            static function (array $m) use ($key, $escaped): string {
                $original = $m[0];
                $replacement = '/' . $key . ' (' . $escaped . ')';
                if (strlen($replacement) > strlen($original)) {
                    // Truncate value to fit
                    $maxVal = strlen($original) - strlen('/' . $key . ' ()');
                    if ($maxVal < 0) {
                        return $original;
                    }
                    $truncated = substr($escaped, 0, $maxVal);
                    $replacement = '/' . $key . ' (' . $truncated . ')';
                }

                return str_pad($replacement, strlen($original), ' ', STR_PAD_RIGHT);
            },
            $bin,
            1
        ) ?? $bin;
    }

    private static function replaceKeepLength(string $bin, string $old, string $new): string
    {
        if ($old === '' || !str_contains($bin, $old)) {
            return $bin;
        }
        if (strlen($new) > strlen($old)) {
            $new = substr($new, 0, strlen($old));
        }
        $new = str_pad($new, strlen($old), ' ', STR_PAD_RIGHT);

        return str_replace($old, $new, $bin);
    }

    /**
     * @return array{title:string,author:string,subject:string}
     */
    public static function metaForDocument(string $doc, string $personName, string $lang = 'en'): array
    {
        $personName = trim($personName);
        $author = $personName !== '' ? $personName : 'Candidate';
        $isDe = str_starts_with(strtolower($lang), 'de');
        if ($doc === 'cover') {
            $title = $isDe ? 'Anschreiben' : 'Cover Letter';
            $subject = $title;
        } else {
            $title = $isDe ? 'Lebenslauf' : 'CV';
            $subject = $isDe ? 'Lebenslauf' : 'Curriculum Vitae';
        }

        return [
            'title' => $title,
            'author' => $author,
            'subject' => $subject,
        ];
    }

    /** @return array{title:string,author:string,subject:string} */
    public static function metaForPackage(string $personName, string $company = ''): array
    {
        $personName = trim($personName);
        $author = $personName !== '' ? $personName : 'Candidate';

        return [
            'title' => 'Application Documents',
            'author' => $author,
            'subject' => 'Application Documents',
        ];
    }

    /**
     * @return array{
     *   path:string,
     *   size:int,
     *   info:array<string,string>,
     *   forbidden_hits:list<string>,
     *   ok:bool
     * }
     */
    public static function inspect(string $path): array
    {
        $bin = is_file($path) ? (string) file_get_contents($path) : '';
        $info = self::extractInfoDictionary($bin);
        $hits = [];
        $hay = strtolower($bin);
        foreach (self::FORBIDDEN_META_NEEDLES as $needle) {
            if ($needle !== '' && str_contains($hay, strtolower($needle))) {
                $hits[] = $needle;
            }
        }
        foreach (['Creator', 'Producer', 'Title', 'Author', 'Subject', 'Keywords'] as $key) {
            $val = strtolower($info[$key] ?? '');
            foreach (['kaamfit', 'headlesschrome', 'skia/pdf', 'puppeteer', 'ddev.site', 'ghostscript'] as $bad) {
                if ($val !== '' && str_contains($val, $bad)) {
                    $hits[] = $key . ':' . $bad;
                }
            }
        }
        $hits = array_values(array_unique($hits));

        $creatorOk = isset($info['Creator']) && !preg_match('/chrome|skia|ghostscript|kaamfit|puppeteer/i', $info['Creator']);
        $producerOk = isset($info['Producer']) && !preg_match('/chrome|skia|ghostscript|kaamfit|puppeteer/i', $info['Producer']);

        return [
            'path' => $path,
            'size' => is_file($path) ? (int) filesize($path) : 0,
            'info' => $info,
            'forbidden_hits' => $hits,
            'ok' => $hits === [] && $creatorOk && $producerOk,
        ];
    }

    /** @return array<string,string> */
    public static function extractInfoDictionary(string $bin): array
    {
        $out = [];
        if ($bin === '') {
            return $out;
        }
        foreach (['Title', 'Author', 'Subject', 'Creator', 'Producer', 'Keywords', 'CreationDate', 'ModDate'] as $key) {
            if (preg_match('/\/' . $key . '\s*\(((?:\\\\\)|\\\\.|[^\\\\)])*)\)/s', $bin, $m)) {
                $out[$key] = trim(self::pdfUnescape($m[1]));
            }
        }

        return $out;
    }

    private static function pdfUnescape(string $s): string
    {
        return str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'], ['(', ')', '\\', "\n", "\r", "\t"], $s);
    }

    private static function pdfLiteralEscape(string $s): string
    {
        $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? $s;
    }

    private static function safeMetaString(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        if ($s === '') {
            return 'Application Documents';
        }
        $lower = mb_strtolower($s);
        foreach (['kaamfit', 'kaam-fit', 'ddev.site'] as $bad) {
            if (str_contains($lower, $bad)) {
                return 'Application Documents';
            }
        }
        if (mb_strlen($s) > 180) {
            $s = mb_substr($s, 0, 180);
        }

        return $s;
    }
}
