<?php

declare(strict_types=1);

namespace KaamFit\Interview;

use Db;

/**
 * Canonical duplicate detection for interview questions.
 * Scores are for admin use only — never expose to normal users.
 */
final class InterviewDuplicateDetector
{
    public static function normalize(string $text): string
    {
        $t = mb_strtolower(trim($text));
        $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        // Strip common interview lead-ins / politeness
        $t = preg_replace(
            '/^(can you |could you |please |kindly |tell me |explain |describe |what is |what are |how do you |how would you |why do you )/u',
            '',
            $t
        ) ?? $t;
        $t = preg_replace('/\b(please|kindly)\b/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        return trim(mb_substr($t, 0, 500));
    }

    public static function contentHash(string $language, string $questionText): string
    {
        return sha1(strtolower(trim($language)) . '|' . mb_strtolower(trim($questionText)));
    }

    /**
     * Find best canonical (universal published) match.
     *
     * @return array{match: ?array<string,mixed>, score: float, confidence: 'exact'|'near'|'none'}
     */
    public static function findCanonicalMatch(string $questionText, string $language = ''): array
    {
        InterviewSchema::ensureSchema();
        $norm = self::normalize($questionText);
        $hash = self::contentHash($language !== '' ? $language : 'en', $questionText);
        $pdo = Db::pdo();

        // Exact hash among universal published
        $st = $pdo->prepare(
            "SELECT * FROM interview_questions
             WHERE content_hash = ? AND visibility = 'universal' AND status = 'published'
             LIMIT 1"
        );
        $st->execute([$hash]);
        $exact = $st->fetch(\PDO::FETCH_ASSOC);
        if (is_array($exact)) {
            return ['match' => $exact, 'score' => 100.0, 'confidence' => 'exact'];
        }

        if ($norm === '') {
            return ['match' => null, 'score' => 0.0, 'confidence' => 'none'];
        }

        // Exact normalized_text
        $st2 = $pdo->prepare(
            "SELECT * FROM interview_questions
             WHERE normalized_text = ? AND visibility = 'universal' AND status = 'published'
             LIMIT 1"
        );
        $st2->execute([$norm]);
        $byNorm = $st2->fetch(\PDO::FETCH_ASSOC);
        if (is_array($byNorm)) {
            return ['match' => $byNorm, 'score' => 98.0, 'confidence' => 'exact'];
        }

        // Near-duplicate via LIKE on significant tokens + similarity
        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $norm) ?: [],
            static fn(string $w): bool => mb_strlen($w) >= 4
        ));
        if ($tokens === []) {
            return ['match' => null, 'score' => 0.0, 'confidence' => 'none'];
        }
        $tokens = array_slice($tokens, 0, 6);
        $likes = [];
        $params = [];
        foreach ($tokens as $tok) {
            $likes[] = 'normalized_text LIKE ?';
            $params[] = '%' . $tok . '%';
        }
        $langClause = '';
        if ($language !== '') {
            $langClause = ' AND language = ?';
            $params[] = $language;
        }
        $sql = "SELECT * FROM interview_questions
                WHERE visibility = 'universal' AND status = 'published'
                  AND (" . implode(' OR ', $likes) . "){$langClause}
                LIMIT 40";
        $st3 = $pdo->prepare($sql);
        $st3->execute($params);
        $candidates = $st3->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $best = null;
        $bestScore = 0.0;
        foreach ($candidates as $cand) {
            $score = self::similarity($norm, (string) ($cand['normalized_text'] ?: self::normalize((string) $cand['question_text'])));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $cand;
            }
        }

        if ($best !== null && $bestScore >= 90.0) {
            return ['match' => $best, 'score' => $bestScore, 'confidence' => 'exact'];
        }
        if ($best !== null && $bestScore >= 72.0) {
            return ['match' => $best, 'score' => $bestScore, 'confidence' => 'near'];
        }

        return ['match' => null, 'score' => $bestScore, 'confidence' => 'none'];
    }

    public static function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 100.0;
        }
        similar_text($a, $b, $pct);
        return (float) $pct;
    }
}
