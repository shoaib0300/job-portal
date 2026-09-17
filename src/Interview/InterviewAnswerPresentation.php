<?php

declare(strict_types=1);

namespace KaamFit\Interview;

/**
 * Compose user-facing Interview Prep content as Question + Answer.
 * Taxonomy / coaching metadata stay in the DB but are not shown by default.
 */
final class InterviewAnswerPresentation
{
    /** Coaching / template headings that must not appear as primary answer sections. */
    private const COACHING_HEADINGS = [
        'why asked',
        'why interviewers ask',
        'why interviewers ask this',
        'what interviewers assess',
        'what a strong answer should demonstrate',
        'what a strong answer covers',
        'strong answer should demonstrate',
        'recommended approach',
        'recommended structure',
        'recommended structure (star)',
        'answer framework',
        'use star',
        'star method',
        'skills',
        'occupations',
        'key points',
        'problem framing',
        'assumptions & analysis points',
        'short answer',
        'short recommendation',
    ];

    /**
     * @param array<string, mixed> $question Decoded interview_questions row (+ optional tags)
     * @return array{
     *   answer_markdown: string,
     *   common_mistakes: list<string>,
     *   follow_ups: list<string>,
     *   related_concepts: list<string>,
     *   has_answer: bool
     * }
     */
    public static function forQuestion(array $question): array
    {
        $short = self::cleanField($question['short_answer'] ?? null);
        $detailed = self::cleanField($question['detailed_answer'] ?? null);
        $example = self::cleanField($question['example_answer'] ?? null);

        if ($detailed === '' && $example !== '') {
            $detailed = $example;
            $example = '';
        } elseif ($example !== '' && self::substantiallySame($example, $detailed)) {
            $example = '';
        }

        $parts = [];
        if ($short !== '' && ($detailed === '' || !self::substantiallyContained($short, $detailed))) {
            $parts[] = $short;
        }
        if ($detailed !== '') {
            $parts[] = $detailed;
        } elseif ($example !== '') {
            $parts[] = $example;
        } elseif ($short !== '' && $parts === []) {
            $parts[] = $short;
        } elseif ($example !== '' && !self::substantiallyContained($example, implode("\n\n", $parts))) {
            $parts[] = $example;
        }

        $answer = self::stripCoachingSections(trim(implode("\n\n", $parts)));
        $answer = self::stripLeadingAnswerHeading($answer);

        return [
            'answer_markdown' => $answer,
            'common_mistakes' => self::meaningfulLines($question['common_mistakes'] ?? null),
            'follow_ups' => self::meaningfulLines($question['follow_ups'] ?? null),
            'related_concepts' => self::meaningfulLines($question['related_concepts'] ?? null),
            'has_answer' => $answer !== '' && !InterviewMarkdown::isWeakFiller($answer),
        ];
    }

    /** Normalize imported / generated answer text before save. */
    public static function normalizeAnswer(?string $text): string
    {
        $t = self::cleanField($text);
        if ($t === '') {
            return '';
        }
        return self::stripLeadingAnswerHeading(self::stripCoachingSections($t));
    }

    public static function cleanField(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            $value = implode("\n", array_map(static fn($x) => is_string($x) ? $x : (string) json_encode($x), $value));
        }
        $t = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        if ($t === '') {
            return '';
        }
        // Strip coaching sections first so a real answer with a leftover template heading survives
        $t = self::stripLeadingAnswerHeading(self::stripCoachingSections($t));
        if ($t === '' || InterviewMarkdown::isWeakFiller($t) || self::isGenericCoachingBlob($t)) {
            return '';
        }
        return $t;
    }

    /** Remove template coaching sections from markdown bodies. */
    public static function stripCoachingSections(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $text);
        $out = [];
        $skip = false;
        foreach ($lines as $line) {
            if (preg_match('/^(#{1,4})\s+(.+)$/', trim($line), $m)) {
                $title = mb_strtolower(trim($m[2]));
                $title = rtrim($title, ':');
                $skip = self::isCoachingHeading($title);
                if ($skip) {
                    continue;
                }
            }
            if ($skip) {
                continue;
            }
            $out[] = $line;
        }
        $joined = trim(implode("\n", $out));
        // Collapse excess blank lines
        $joined = preg_replace("/\n{3,}/", "\n\n", $joined) ?? $joined;
        return trim($joined);
    }

    /** @return list<string> */
    public static function meaningfulLines(mixed $value): array
    {
        $items = self::normalizeList($value);
        $out = [];
        foreach ($items as $item) {
            $t = trim($item);
            if ($t === '' || self::isGenericListItem($t)) {
                continue;
            }
            $out[] = $t;
        }
        return array_values(array_unique($out));
    }

    public static function isGenericCoachingBlob(string $text): bool
    {
        $lower = mb_strtolower(trim($text));
        if ($lower === '') {
            return true;
        }
        $patterns = [
            'interviewers use this to understand',
            'interviewers ask this to understand',
            'use star',
            'use the star',
            'connect your answer to the role',
            'connect it to the role',
            'provide a relevant example',
            'share a concise, role-relevant',
            'give a relevant example from your experience',
            'explain how you handled the situation',
            'quantify the result where possible',
            'talk about a project you are proud of',
            'being vague, blaming others',
            'clear structure',
        ];
        $hits = 0;
        foreach ($patterns as $p) {
            if (str_contains($lower, $p)) {
                $hits++;
            }
        }
        // Short blobs that are mostly coaching
        if ($hits >= 1 && mb_strlen($text) < 220) {
            return true;
        }
        if ($hits >= 2) {
            return true;
        }
        return false;
    }

    public static function isGenericListItem(string $text): bool
    {
        $t = mb_strtolower(trim($text));
        if ($t === '' || mb_strlen($t) < 3) {
            return true;
        }
        $generic = [
            'situation',
            'task',
            'action',
            'result',
            'context',
            'approach',
            'trade-offs',
            'tradeoffs',
            'outcome',
            'clear structure',
            'concrete example or method',
            'outcome or learning',
            'relevance to the role',
            'communication',
            'problem solving',
            'teamwork',
            'being vague',
            'being vague, blaming others, or ignoring the role context.',
            'be vague',
            'use star',
            'provide a relevant example',
            'connect your answer to the role',
        ];
        foreach ($generic as $g) {
            if ($t === $g || str_starts_with($t, $g)) {
                return true;
            }
        }
        return false;
    }

    private static function isCoachingHeading(string $title): bool
    {
        foreach (self::COACHING_HEADINGS as $h) {
            if ($title === $h || str_starts_with($title, $h)) {
                return true;
            }
        }
        if (str_contains($title, 'star') && (str_contains($title, 'recommend') || str_contains($title, 'structure') || str_contains($title, 'method'))) {
            return true;
        }
        return false;
    }

    private static function stripLeadingAnswerHeading(string $markdown): string
    {
        $t = ltrim($markdown);
        // Avoid redundant "## Answer" when the page already labels Answer
        if (preg_match('/^#{1,3}\s+answer\s*\n+/i', $t)) {
            $t = preg_replace('/^#{1,3}\s+answer\s*\n+/i', '', $t) ?? $t;
        }
        return trim($t);
    }

    /** @return list<string> */
    private static function normalizeList(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $parts = preg_split('/\r\n|\n|\r/', $value) ?: [];
                return array_values(array_filter(array_map('trim', $parts), static fn(string $s): bool => $s !== ''));
            }
        }
        if (!is_array($value)) {
            return [trim((string) $value)];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = trim($item);
            } elseif (is_scalar($item)) {
                $out[] = trim((string) $item);
            }
        }
        return $out;
    }

    private static function substantiallySame(string $a, string $b): bool
    {
        return self::normCompare($a) === self::normCompare($b);
    }

    private static function substantiallyContained(string $needle, string $haystack): bool
    {
        $n = self::normCompare($needle);
        $h = self::normCompare($haystack);
        if ($n === '' || $h === '') {
            return false;
        }
        return str_contains($h, $n);
    }

    private static function normCompare(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return $s;
    }
}
