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

    public static function stripHtml(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        return trim($text);
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
