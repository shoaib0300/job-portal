<?php

declare(strict_types=1);

/** Build search / fit terms from the logged-in user's active resume. */
final class ResumeJobMatch
{
    private const STOP = [
        'and', 'or', 'the', 'with', 'for', 'from', 'into', 'your', 'our', 'you', 'und', 'der', 'die', 'das',
        'ein', 'eine', 'einer', 'einem', 'einen', 'oder', 'mit', 'bei', 'nach', 'über', 'von', 'zum', 'zur',
        'in', 'on', 'at', 'to', 'of', 'a', 'an', 'as', 'by', 'is', 'are', 'be', 'we', 'i', 'my', 'me',
        'working', 'student', 'engineer', 'developer', 'manager', 'specialist', 'assistant', 'role',
        'germany', 'deutschland', 'munich', 'berlin', 'hamburg', 'remote', 'hybrid', 'onsite',
    ];

    /** Short role phrases for board search (OR / free-text). */
    public static function searchKeywords(): array
    {
        $payload = self::payload();
        $title = trim((string) ($payload['profile']['title'] ?? ''));
        $phrases = [];
        foreach (preg_split('/[|\/·•]+/u', $title) ?: [] as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? $part);
            $part = trim($part, " \t&,");
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
        $out = [];
        $seen = [];
        foreach ($phrases as $p) {
            $key = mb_strtolower($p);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $p;
            if (count($out) >= 4) {
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
        $chunks = [];
        $chunks[] = (string) ($payload['profile']['title'] ?? '');
        foreach ($payload['sections'] as $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = mb_strtolower((string) ($section['section_key'] ?? ''));
            $title = mb_strtolower((string) ($section['title'] ?? ''));
            if ($key === 'skills' || str_contains($title, 'skill') || str_contains($title, 'kenntnis')) {
                $chunks[] = (string) ($section['body'] ?? '');
            }
            if ($key === 'summary' || str_contains($title, 'summary') || str_contains($title, 'profil')) {
                $chunks[] = (string) ($section['body'] ?? '');
            }
        }
        foreach (self::recentPositions($payload) as $pos) {
            $chunks[] = $pos;
        }
        return self::tokenize(implode("\n", $chunks), 40);
    }

    public static function activeTitle(): string
    {
        $payload = self::payload();
        return trim((string) ($payload['profile']['title'] ?? ''));
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
        foreach ($terms as $term) {
            $t = mb_strtolower($term);
            if ($t === '' || mb_strlen($t) < 3) {
                continue;
            }
            if (mb_strpos($title, $t) !== false) {
                $score += mb_strlen($t) >= 8 ? 10 : 7;
                continue;
            }
            if (mb_strpos($blob, $t) !== false) {
                $score += mb_strlen($t) >= 8 ? 3 : 2;
            }
        }
        return $score;
    }

    public static function fitLabel(int $score): string
    {
        if ($score >= 24) {
            return 'Strong fit';
        }
        if ($score >= 12) {
            return 'Good fit';
        }
        if ($score >= 5) {
            return 'Fair fit';
        }
        return 'Low fit';
    }

    /**
     * @return array{profile: array<string, mixed>, sections: list<array<string, mixed>>, experiences: list<array<string, mixed>>}
     */
    private static function payload(): array
    {
        try {
            $active = Versions::activeResumeVersion();
            $id = $active ? (int) $active['id'] : 0;
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
