<?php

declare(strict_types=1);

namespace KaamFit\Interview;

/**
 * Detect Q&A pairs from normalized interview document text.
 * Does not fabricate answers — missing answers stay empty.
 */
final class InterviewQuestionExtractor
{
    /**
     * @return list<array{question:string,answer:string}>
     */
    public static function extract(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // Prefer numbered / Q: patterns
        $pairs = self::extractLabeled($text);
        if ($pairs !== []) {
            return self::dedupePairs($pairs);
        }

        $pairs = self::extractByQuestionMarks($text);
        return self::dedupePairs($pairs);
    }

    /**
     * @return list<array{question:string,answer:string}>
     */
    private static function extractLabeled(string $text): array
    {
        $lines = preg_split('/\n/u', $text) ?: [];
        $blocks = [];
        $currentQ = null;
        $currentA = [];
        $flush = static function () use (&$blocks, &$currentQ, &$currentA): void {
            if ($currentQ === null) {
                return;
            }
            $answer = trim(implode("\n", $currentA));
            $blocks[] = ['question' => $currentQ, 'answer' => $answer];
            $currentQ = null;
            $currentA = [];
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                if ($currentQ !== null && $currentA !== []) {
                    $currentA[] = '';
                }
                continue;
            }
            if (preg_match('/^(?:Q(?:uestion)?\s*[:.)]\s*|Frage\s*[:.)]\s*|\d{1,3}[.)]\s+)(.+)$/iu', $trim, $m)) {
                $flush();
                $currentQ = trim($m[1]);
                continue;
            }
            if (preg_match('/^(?:A(?:nswer)?\s*[:.)]\s*|Antwort\s*[:.)]\s+)(.+)$/iu', $trim, $m)) {
                if ($currentQ !== null) {
                    $currentA[] = trim($m[1]);
                }
                continue;
            }
            // Heuristic: line ending with ? starts a new question
            if (str_ends_with($trim, '?') && mb_strlen($trim) >= 12 && mb_strlen($trim) <= 400) {
                $flush();
                $currentQ = $trim;
                continue;
            }
            if ($currentQ !== null) {
                $currentA[] = $trim;
            }
        }
        $flush();

        return array_values(array_filter(
            $blocks,
            static fn(array $p): bool => mb_strlen($p['question']) >= 8
        ));
    }

    /**
     * @return list<array{question:string,answer:string}>
     */
    private static function extractByQuestionMarks(string $text): array
    {
        $parts = preg_split('/(?<=\?)\s+/u', $text) ?: [];
        $out = [];
        foreach ($parts as $i => $part) {
            $part = trim($part);
            if ($part === '' || !str_contains($part, '?')) {
                continue;
            }
            $qEnd = mb_strpos($part, '?');
            if ($qEnd === false) {
                continue;
            }
            $question = trim(mb_substr($part, 0, $qEnd + 1));
            $answer = trim(mb_substr($part, $qEnd + 1));
            // Cap answer: stop at next obvious question if embedded
            if (mb_strlen($question) < 8 || mb_strlen($question) > 500) {
                continue;
            }
            if (mb_strlen($answer) > 4000) {
                $answer = mb_substr($answer, 0, 4000);
            }
            $out[] = ['question' => $question, 'answer' => $answer];
        }
        return $out;
    }

    /**
     * @param list<array{question:string,answer:string}> $pairs
     * @return list<array{question:string,answer:string}>
     */
    private static function dedupePairs(array $pairs): array
    {
        $seen = [];
        $out = [];
        foreach ($pairs as $p) {
            $key = InterviewDuplicateDetector::normalize($p['question']);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $p;
        }
        return $out;
    }
}
