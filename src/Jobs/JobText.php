<?php

declare(strict_types=1);

final class JobText
{
    public static function haystack(string ...$parts): string
    {
        return mb_strtolower(trim(implode(' ', $parts)));
    }

    public static function workMode(string $text, string $employmentHint = ''): string
    {
        $t = self::haystack($text, $employmentHint);
        $remote = (bool) preg_match('/\b(remote|homeoffice|home.?office|telearbeit|teleheimarbeit|vollständig remote|100\s*%\s*remote)\b/u', $t);
        $hybrid = (bool) preg_match('/\bhybrid\b/u', $t);
        $onsite = (bool) preg_match('/\b(vor ort|on-?site|präsenz|dienstort)\b/u', $t);
        if ($remote && $hybrid) {
            return 'hybrid';
        }
        if ($remote) {
            return 'remote';
        }
        if ($hybrid) {
            return 'hybrid';
        }
        if ($onsite) {
            return 'onsite';
        }
        return 'unknown';
    }

    public static function employment(string $text, string $hint = ''): string
    {
        $t = self::haystack($text, $hint);
        if (preg_match('/\b(minijob|mini-job|geringfügig)\b/u', $t)) {
            return 'mini';
        }
        if (preg_match('/\b(teilzeit|part[- ]?time|tz\b)\b/u', $t) || $hint === 'tz' || $hint === 'TEILZEIT') {
            return 'parttime';
        }
        if (preg_match('/\b(vollzeit|full[- ]?time|vz\b)\b/u', $t) || $hint === 'vz' || $hint === 'VOLLZEIT') {
            return 'fulltime';
        }
        return 'unknown';
    }

    public static function offerType(string $text, string $hint = ''): string
    {
        $t = self::haystack($text, $hint);
        $hintU = strtoupper($hint);
        if ($hint === '34' || $hintU === 'PRAKTIKUM' || $hintU === 'TRAINEE' || preg_match('/\b(praktikum|internship|intern\b|trainee)\b/u', $t)) {
            return 'internship';
        }
        if ($hint === '4' || $hintU === 'AUSBILDUNG' || preg_match('/\b(ausbildung|duales studium|azubi)\b/u', $t)) {
            return 'training';
        }
        return 'job';
    }

    /** @return list<string> */
    public static function seniorityTags(string $text): array
    {
        $t = self::haystack($text);
        $tags = [];
        if (preg_match('/\b(werkstudent|working student|studentische|hiwi|werkstudentin|studierende)\b/u', $t)) {
            $tags[] = 'student';
        }
        if (preg_match('/\b(junior|einsteiger|berufseinsteiger|entry[- ]level)\b/u', $t)) {
            $tags[] = 'junior';
        }
        if (preg_match('/\b(absolvent|graduate|hochschulabsolvent)\b/u', $t)) {
            $tags[] = 'graduate';
        }
        if (preg_match('/\b(keine berufserfahrung|ohne berufserfahrung|no experience|ohne vorkenntnisse)\b/u', $t)) {
            $tags[] = 'no_experience';
        }
        return $tags;
    }

    /** @return list<string> */
    public static function languages(string $text): array
    {
        $t = self::haystack($text);
        $out = [];
        if (preg_match('/\b(english|englisch|englischsprachig)\b/u', $t)) {
            $out[] = 'en';
        }
        if (preg_match('/\b(deutsch|german|deutschkenntnisse)\b/u', $t)) {
            $out[] = 'de';
        }
        foreach (['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $lvl) {
            if (preg_match('/\b' . $lvl . '\b/i', $text)) {
                $out[] = $lvl;
            }
        }
        return array_values(array_unique($out));
    }

    public static function looksLikeSalary(string $text): bool
    {
        return (bool) preg_match('/(€|eur\b|gehalt|vergütung|tv-?l|tvöd|entgeltgruppe|\d[\d.]*\s*(k|brutto))/iu', $text);
    }

    public static function looksLikeHtml(string $text): bool
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return (bool) preg_match('/<\/?[a-z][a-z0-9]*\b[^>]*>/i', $decoded);
    }

    public static function looksLikeMarkdown(string $text): bool
    {
        return (bool) preg_match(
            '/(^|\n)\s{0,3}(#{1,4}\s+|[-*+]\s+\S|\d+\.\s+\S)|\*\*[^*\n]+\*\*|https?:\/\/\S+/u',
            $text
        );
    }

    /** Plain text for Applications / tailor. Decodes entities, then strips tags. */
    public static function stripHtml(string $html): string
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $text) ?? $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|h[1-6]|li|tr|blockquote)>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<(li|h[1-6])\b[^>]*>#i', "\n• ", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        return trim($text);
    }

    /** Safe HTML for the job page: keep lists and emphasis, drop scripts. */
    public static function safeHtml(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><a><h2><h3><h4><div><span>');
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\s(style|class|id)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html) ?? $html;
        $html = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', 'href="#"', $html) ?? $html;
        // Ensure external links open safely.
        $html = preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function (array $m): string {
                $attrs = $m[1];
                if (!preg_match('/\btarget\s*=/i', $attrs)) {
                    $attrs .= ' target="_blank"';
                }
                if (!preg_match('/\brel\s*=/i', $attrs)) {
                    $attrs .= ' rel="noopener noreferrer"';
                }
                return '<a' . $attrs . '>';
            },
            $html
        ) ?? $html;
        return $html;
    }

    /** Lightweight Markdown → HTML (headings, lists, bold, links). Escapes first. */
    public static function markdownToHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $lines = explode("\n", $text);
        $out = [];
        $listType = null; // 'ul' | 'ol' | null

        $closeList = static function () use (&$out, &$listType): void {
            if ($listType !== null) {
                $out[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            $trimmed = rtrim($line);

            if (preg_match('/^\s*[-*+]\s+(.+)$/u', $trimmed, $m)) {
                if ($listType !== 'ul') {
                    $closeList();
                    $out[] = '<ul>';
                    $listType = 'ul';
                }
                $out[] = '<li>' . self::inlineMarkdown($m[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\s*\d+\.\s+(.+)$/u', $trimmed, $m)) {
                if ($listType !== 'ol') {
                    $closeList();
                    $out[] = '<ol>';
                    $listType = 'ol';
                }
                $out[] = '<li>' . self::inlineMarkdown($m[1]) . '</li>';
                continue;
            }

            $closeList();

            if (trim($trimmed) === '') {
                continue;
            }

            if (preg_match('/^\s{0,3}(#{1,4})\s+(.+)$/u', $trimmed, $m)) {
                $level = min(4, max(2, strlen($m[1]) + 1)); // # → h2, ## → h3, ### → h4
                $out[] = '<h' . $level . '>' . self::inlineMarkdown(trim($m[2])) . '</h' . $level . '>';
                continue;
            }

            $out[] = '<p>' . self::inlineMarkdown($trimmed) . '</p>';
        }

        $closeList();
        return implode("\n", $out);
    }

    private static function inlineMarkdown(string $text): string
    {
        $s = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = preg_replace('/\*\*(.+?)\*\*/us', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/__(.+?)__/us', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/us', '<em>$1</em>', $s) ?? $s;
        // Markdown links [label](url)
        $s = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/iu',
            '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
            $s
        ) ?? $s;
        // Autolink bare URLs (skip ones already inside href=")
        $s = preg_replace_callback(
            '/(?<!href="|href=\')(https?:\/\/[^\s<]+)/iu',
            static function (array $m): string {
                $url = rtrim($m[1], '.,);]');
                $trail = substr($m[1], strlen($url));
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $trail;
            },
            $s
        ) ?? $s;
        return $s;
    }

    public static function displayHtml(string $text): string
    {
        if (self::looksLikeHtml($text)) {
            return self::safeHtml($text);
        }
        if (self::looksLikeMarkdown($text)) {
            return self::safeHtml(self::markdownToHtml($text));
        }
        return App::nl2p($text);
    }

    public static function enrich(JobListing $job): JobListing
    {
        $blob = self::haystack($job->title, $job->company, $job->description, $job->salaryText);
        if ($job->workMode === 'unknown') {
            $job->workMode = self::workMode($blob);
        }
        if ($job->employment === 'unknown') {
            $job->employment = self::employment($blob);
        }
        if ($job->offerType === 'unknown') {
            $job->offerType = self::offerType($blob);
        }
        if ($job->seniorityTags === []) {
            $job->seniorityTags = self::seniorityTags($blob);
        }
        if ($job->languages === []) {
            $job->languages = self::languages($job->title . "\n" . $job->description);
        }
        if ($job->salaryText === '' && self::looksLikeSalary($job->description)) {
            if (preg_match('/([€]|EUR).{0,40}/u', $job->description, $m)) {
                $job->salaryText = trim($m[0]);
            }
        }
        return $job;
    }
}
