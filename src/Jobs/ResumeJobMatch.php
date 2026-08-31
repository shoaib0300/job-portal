<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

use App;
use Auth;
use Versions;


/** Build search / fit terms from the logged-in user's Master CV. */
final class ResumeJobMatch
{
    private const STOP = [
        'and', 'or', 'the', 'with', 'for', 'from', 'into', 'your', 'our', 'you', 'und', 'der', 'die', 'das',
        'ein', 'eine', 'einer', 'einem', 'einen', 'oder', 'mit', 'bei', 'nach', 'über', 'von', 'zum', 'zur',
        'in', 'on', 'at', 'to', 'of', 'a', 'an', 'as', 'by', 'is', 'are', 'be', 'we', 'i', 'my', 'me',
        'role', 'team', 'using', 'used', 'also', 'plus', 'etc', 'via', 'per', 'all', 'any',
        'germany', 'deutschland', 'munich', 'berlin', 'hamburg', 'remote', 'hybrid', 'onsite',
        // Summary fluff — these match almost every JD and must not drive fit.
        'background', 'currently', 'studying', 'experienced', 'structured', 'documentation',
        'stakeholder', 'communication', 'tooling', 'motivated', 'careful', 'process', 'reliable',
        'coordination', 'across', 'suppliers', 'internal', 'stakeholders', 'requirements',
        'support', 'analysis', 'quality', 'office', 'work', 'data', 'strong', 'hands', 'open',
        'based', 'skills', 'knowledge', 'experience', 'responsible', 'including', 'various',
    ];

    /** Short role phrases for board / SQL search (OR). */
    public static function searchKeywords(): array
    {
        $payload = self::payload();
        $title = trim((string) ($payload['profile']['title'] ?? ''));
        $phrases = [];
        foreach (preg_split('/[|\/·•,]+/u', $title) ?: [] as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? $part);
            $part = trim($part, " \t&");
            if (mb_strlen($part) < 3) {
                continue;
            }
            $phrases[] = $part;
        }
        if ($phrases === []) {
            foreach (self::recentPositions($payload) as $pos) {
                $phrases[] = $pos;
            }
        }
        // Add distinctive skill phrases so SQL recall is not only the title.
        foreach (self::skillPhrases($payload, 6) as $skill) {
            $phrases[] = $skill;
        }

        $out = [];
        $seen = [];
        foreach ($phrases as $p) {
            $key = mb_strtolower($p);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $p;
            if (count($out) >= 8) {
                break;
            }
        }
        return $out;
    }

    /**
     * Broader tokens used to rank / filter jobs against the resume.
     *
     * @return list<string>
     */
    public static function scoreTerms(): array
    {
        $payload = self::payload();
        $terms = [];
        // Keep title phrases intact (high signal).
        foreach (self::titlePhrases($payload) as $p) {
            $terms[] = $p;
        }
        foreach (self::skillPhrases($payload, 24) as $p) {
            $terms[] = $p;
        }
        foreach (self::recentPositions($payload) as $pos) {
            $terms[] = $pos;
        }
        // Tokenize skills only (not the whole summary essay).
        $skillsBody = self::skillsBody($payload);
        foreach (self::tokenize($skillsBody, 24) as $tok) {
            $terms[] = $tok;
        }

        $out = [];
        $seen = [];
        foreach ($terms as $t) {
            $t = trim($t);
            if ($t === '') {
                continue;
            }
            $key = mb_strtolower($t);
            if (isset($seen[$key]) || in_array($key, self::STOP, true)) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $t;
            if (count($out) >= 36) {
                break;
            }
        }
        return $out;
    }

    public static function masterTitle(): string
    {
        $base = Versions::baseResumeVersion();
        if ($base !== null) {
            return Versions::resumeDisplayLabel($base);
        }
        $payload = self::payload();
        $title = trim((string) ($payload['profile']['title'] ?? ''));
        return $title !== '' ? $title : 'your profile';
    }

    /** @deprecated Use masterTitle() */
    public static function activeTitle(): string
    {
        return self::masterTitle();
    }

    /**
     * Minimum fit score to keep a job when “Match my resume” is on.
     * Tuned so generic words alone are not enough — need real title/skill overlap.
     */
    public static function minFitScore(): int
    {
        return 12;
    }

    public static function fitScore(JobListing $job, ?array $terms = null): int
    {
        $terms = $terms ?? self::scoreTerms();
        if ($terms === []) {
            return 0;
        }
        $title = mb_strtolower($job->title);
        $blob = mb_strtolower($job->title . "\n" . $job->description . "\n" . $job->company);
        $score = 0;
        $titleHits = 0;
        foreach ($terms as $term) {
            $t = mb_strtolower(trim($term));
            if ($t === '' || mb_strlen($t) < 3) {
                continue;
            }
            $isPhrase = str_contains($t, ' ') || mb_strlen($t) >= 10;
            if (mb_strpos($title, $t) !== false) {
                $score += $isPhrase ? 14 : 8;
                $titleHits++;
                continue;
            }
            if (mb_strpos($blob, $t) !== false) {
                $score += $isPhrase ? 5 : 2;
            }
        }
        // Require at least one title hit for mid scores, or a strong overall score.
        if ($titleHits === 0 && $score < 18) {
            return (int) floor($score * 0.4);
        }
        return $score;
    }

    public static function fitLabel(int $score): string
    {
        if ($score >= 28) {
            return 'Strong fit';
        }
        if ($score >= 18) {
            return 'Good fit';
        }
        if ($score >= self::minFitScore()) {
            return 'Fair fit';
        }
        return 'Low fit';
    }

    /** True when any resume term appears in the job title or description. */
    public static function matchesAnyTerm(JobListing $job, ?array $terms = null): bool
    {
        $terms = $terms ?? self::scoreTerms();
        if ($terms === []) {
            return false;
        }
        $blob = mb_strtolower($job->title . "\n" . $job->description);
        foreach ($terms as $term) {
            $t = mb_strtolower(trim($term));
            if ($t === '' || mb_strlen($t) < 3) {
                continue;
            }
            if (mb_strpos($blob, $t) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{profile: array<string, mixed>, sections: list<array<string, mixed>>, experiences: list<array<string, mixed>>}
     */
    private static function payload(): array
    {
        try {
            $base = Versions::baseResumeVersion();
            $id = $base ? (int) $base['id'] : 0;
            $payload = Versions::resumePayloadForView($id > 0 ? $id : null);
            return [
                'profile' => is_array($payload['profile'] ?? null) ? $payload['profile'] : [],
                'sections' => is_array($payload['sections'] ?? null) ? $payload['sections'] : [],
                'experiences' => is_array($payload['experiences'] ?? null) ? $payload['experiences'] : [],
            ];
        } catch (Throwable) {
            return ['profile' => [], 'sections' => [], 'experiences' => []];
        }
    }

    /** @param array<string, mixed> $payload @return list<string> */
    private static function titlePhrases(array $payload): array
    {
        $title = trim((string) ($payload['profile']['title'] ?? ''));
        $out = [];
        foreach (preg_split('/[|\/·•,]+/u', $title) ?: [] as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? $part);
            if (mb_strlen($part) >= 3) {
                $out[] = $part;
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $payload */
    private static function skillsBody(array $payload): string
    {
        foreach ($payload['sections'] as $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = mb_strtolower((string) ($section['section_key'] ?? ''));
            $title = mb_strtolower((string) ($section['title'] ?? ''));
            if ($key === 'skills' || str_contains($title, 'skill') || str_contains($title, 'kenntnis')) {
                return (string) ($section['body'] ?? '');
            }
        }
        return '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private static function skillPhrases(array $payload, int $limit): array
    {
        $body = self::skillsBody($payload);
        if ($body === '') {
            return [];
        }
        $parts = preg_split('/[\n,;·•|]+/u', $body) ?: [];
        $out = [];
        $seen = [];
        foreach ($parts as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? $part);
            if (mb_strlen($part) < 3 || mb_strlen($part) > 48) {
                continue;
            }
            $key = mb_strtolower($part);
            if (isset($seen[$key]) || in_array($key, self::STOP, true)) {
                continue;
            }
            // Prefer concrete skills, not sentence fragments.
            if (str_word_count($part) > 6) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $part;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $payload @return list<string> */
    private static function recentPositions(array $payload): array
    {
        $out = [];
        foreach (array_slice($payload['experiences'], 0, 3) as $job) {
            if (!is_array($job)) {
                continue;
            }
            $pos = trim((string) ($job['position'] ?? ''));
            if ($pos !== '') {
                $out[] = $pos;
            }
        }
        return $out;
    }

    /** @return list<string> */
    private static function tokenize(string $text, int $limit): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\+\#\.\-\s\/]/u', ' ', $text) ?? $text;
        $parts = preg_split('/[\s\/,;|·•]+/u', $text) ?: [];
        $out = [];
        $seen = [];
        foreach ($parts as $part) {
            $part = trim($part, ".-");
            if (mb_strlen($part) < 3 || isset($seen[$part]) || in_array($part, self::STOP, true)) {
                continue;
            }
            if (preg_match('/^\d+$/u', $part)) {
                continue;
            }
            $seen[$part] = true;
            $out[] = $part;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }
}
