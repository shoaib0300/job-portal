<?php

declare(strict_types=1);

namespace KaamFit\Interview;

/**
 * Escape-first Markdown subset for interview answers.
 * Allows: headings, bold/italic, lists, code fences, tables, links.
 */
final class InterviewMarkdown
{
    public static function render(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        // Extract fenced code blocks
        $blocks = [];
        $text = preg_replace_callback('/```([a-zA-Z0-9_-]*)\n(.*?)```/s', static function (array $m) use (&$blocks): string {
            $idx = count($blocks);
            $code = htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $lang = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $blocks[$idx] = '<pre class="interview-code"><code' . ($lang !== '' ? ' class="language-' . $lang . '"' : '') . '>' . $code . '</code></pre>';
            return "\n%%CODEBLOCK{$idx}%%\n";
        }, $text) ?? $text;

        $lines = explode("\n", $text);
        $html = [];
        $inUl = false;
        $inOl = false;
        $inTable = false;
        $para = [];

        $flushPara = static function () use (&$html, &$para): void {
            if ($para === []) {
                return;
            }
            $html[] = '<p>' . self::inline(implode(' ', $para)) . '</p>';
            $para = [];
        };
        $closeLists = static function () use (&$html, &$inUl, &$inOl): void {
            if ($inUl) {
                $html[] = '</ul>';
                $inUl = false;
            }
            if ($inOl) {
                $html[] = '</ol>';
                $inOl = false;
            }
        };
        $closeTable = static function () use (&$html, &$inTable): void {
            if ($inTable) {
                $html[] = '</tbody></table>';
                $inTable = false;
            }
        };

        foreach ($lines as $line) {
            $trim = rtrim($line);
            if (preg_match('/^%%CODEBLOCK(\d+)%%$/', trim($trim), $m)) {
                $flushPara();
                $closeLists();
                $closeTable();
                $html[] = $blocks[(int) $m[1]] ?? '';
                continue;
            }
            if ($trim === '') {
                $flushPara();
                $closeLists();
                $closeTable();
                continue;
            }
            if (preg_match('/^(#{1,3})\s+(.+)$/', $trim, $m)) {
                $flushPara();
                $closeLists();
                $closeTable();
                $level = strlen($m[1]) + 2; // h3–h5
                $html[] = '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . '>';
                continue;
            }
            if (preg_match('/^\|(.+)\|$/', $trim, $m)) {
                $flushPara();
                $closeLists();
                $cells = array_map('trim', explode('|', trim($m[1], '|')));
                if (preg_match('/^[\s|:\-]+$/', $trim)) {
                    continue; // separator
                }
                if (!$inTable) {
                    $html[] = '<table class="table table-sm"><thead><tr>';
                    foreach ($cells as $c) {
                        $html[] = '<th>' . self::inline($c) . '</th>';
                    }
                    $html[] = '</tr></thead><tbody>';
                    $inTable = true;
                } else {
                    $html[] = '<tr>';
                    foreach ($cells as $c) {
                        $html[] = '<td>' . self::inline($c) . '</td>';
                    }
                    $html[] = '</tr>';
                }
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/', $trim, $m)) {
                $flushPara();
                $closeTable();
                if ($inOl) {
                    $html[] = '</ol>';
                    $inOl = false;
                }
                if (!$inUl) {
                    $html[] = '<ul>';
                    $inUl = true;
                }
                $html[] = '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }
            if (preg_match('/^\d+\.\s+(.+)$/', $trim, $m)) {
                $flushPara();
                $closeTable();
                if ($inUl) {
                    $html[] = '</ul>';
                    $inUl = false;
                }
                if (!$inOl) {
                    $html[] = '<ol>';
                    $inOl = true;
                }
                $html[] = '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }
            $closeLists();
            $closeTable();
            $para[] = $trim;
        }
        $flushPara();
        $closeLists();
        $closeTable();

        return implode("\n", $html);
    }

    /** True if text looks like a useless placeholder filler. */
    public static function isWeakFiller(?string $text): bool
    {
        if ($text === null) {
            return true;
        }
        $t = trim($text);
        if ($t === '' || mb_strlen($t) < 40) {
            return true;
        }
        $weak = [
            'provide a relevant',
            'share a concise',
            'antworten sie',
            'give a clear example',
            'describe your approach briefly',
            'role-relevant example with a clear outcome',
        ];
        $lower = mb_strtolower($t);
        foreach ($weak as $w) {
            if (str_contains($lower, $w)) {
                return true;
            }
        }
        return false;
    }

    private static function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // code spans
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
        // bold / italic
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped) ?? $escaped;
        // links [text](url) — http(s) only
        $escaped = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/',
            static function (array $m): string {
                return '<a href="' . $m[2] . '" rel="noopener noreferrer" target="_blank">' . $m[1] . '</a>';
            },
            $escaped
        ) ?? $escaped;
        return $escaped;
    }
}
