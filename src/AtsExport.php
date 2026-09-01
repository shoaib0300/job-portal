<?php

declare(strict_types=1);

/**
 * Employer ATS / portal uploads (SAP SuccessFactors, Workday, etc.).
 * Strips content and PDF features that commonly trigger upload blocks.
 */
final class AtsExport
{
    public static function isEnabled(array $query): bool
    {
        return isset($query['ats']) && (string) $query['ats'] === '1';
    }

    public static function sanitizeText(string $text, ?string $employer = null): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(
            ["\u{2014}", "\u{2013}", "\u{00B7}", "\u{2019}", "\u{2018}", '&'],
            ['-', '-', ', ', "'", "'", 'and'],
            $text
        );
        if ($employer !== null && trim($employer) !== '') {
            $text = self::stripEmployer($text, $employer);
        }

        return trim(preg_replace('/\s{2,}/', ' ', $text) ?? $text);
    }

    public static function stripEmployer(string $text, string $employer): string
    {
        $employer = trim($employer);
        if ($employer === '') {
            return $text;
        }

        $names = [$employer];
        foreach (preg_split('/\s*&\s*|\s*,\s*/', $employer) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part !== '' && !in_array($part, $names, true)) {
                $names[] = $part;
            }
        }

        foreach ($names as $name) {
            $quoted = preg_quote($name, '/');
            $text = preg_replace('/\b' . $quoted . '\b[\x{2019}\']?s?/iu', '', $text) ?? $text;
            $text = preg_replace('/\bat\s+' . $quoted . '\b/iu', '', $text) ?? $text;
        }

        $text = preg_replace(
            '/\b(eager|excited|motivated)\s+to\s+join\b[^.]*\./iu',
            '',
            $text
        ) ?? $text;

        return trim(preg_replace('/\s{2,}/', ' ', $text) ?? $text);
    }

    /**
     * @param array{profile: array, sections: list<array>, experiences: list<array>, version: ?array, company: string} $payload
     * @return array{profile: array, sections: list<array>, experiences: list<array>, version: ?array, company: string}
     */
    public static function sanitizeResumePayload(array $payload): array
    {
        $employer = (string) ($payload['company'] ?? '');
        if ($employer === '' && is_array($payload['version'] ?? null)) {
            $employer = (string) ($payload['version']['company'] ?? '');
        }

        if (($payload['profile']['title'] ?? '') !== '') {
            $payload['profile']['title'] = self::sanitizeText((string) $payload['profile']['title'], $employer);
        }

        foreach ($payload['sections'] as &$section) {
            if (!is_array($section) || !isset($section['body'])) {
                continue;
            }
            $section['body'] = self::sanitizeText((string) $section['body'], $employer);
        }
        unset($section);

        foreach ($payload['experiences'] as &$job) {
            if (!is_array($job) || !isset($job['bullets'])) {
                continue;
            }
            $job['bullets'] = self::sanitizeText((string) $job['bullets'], $employer);
        }
        unset($job);

        return $payload;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public static function sanitizeSnapshot(array $snapshot, string $employer): array
    {
        if (isset($snapshot['profile_title'])) {
            $snapshot['profile_title'] = self::sanitizeText((string) $snapshot['profile_title'], $employer);
        }

        foreach ($snapshot['sections'] ?? [] as &$section) {
            if (!is_array($section) || !isset($section['body'])) {
                continue;
            }
            $section['body'] = self::sanitizeText((string) $section['body'], $employer);
        }
        unset($section);

        foreach ($snapshot['experiences'] ?? [] as &$job) {
            if (!is_array($job) || !isset($job['bullets'])) {
                continue;
            }
            $job['bullets'] = self::sanitizeText((string) $job['bullets'], $employer);
        }
        unset($job);

        return $snapshot;
    }
}
