<?php

declare(strict_types=1);

namespace KaamFit\Resume;

/**
 * Heuristic resume quality checks (content, German conventions, ATS, length).
 */
final class ResumeCheck
{
    /**
     * @param array{profile: array, sections: list<array>, experiences: list<array>} $payload
     * @param array{template?: string, photo_mode?: string, density?: string} $meta
     * @return array{
     *   ok: bool,
     *   issues: list<array{level: string, code: string, message: string}>,
     *   pages_estimate: float|null,
     *   summary: array{errors: int, warnings: int, info: int}
     * }
     */
    public static function analyze(array $payload, array $meta = [], ?float $pagesEstimate = null): array
    {
        $issues = [];
        $profile = $payload['profile'] ?? [];
        $sections = $payload['sections'] ?? [];
        $experiences = $payload['experiences'] ?? [];
        $byKey = [];
        foreach ($sections as $section) {
            if (is_array($section)) {
                $byKey[(string) ($section['section_key'] ?? '')] = $section;
            }
        }

        if (!\App::filled($profile['full_name'] ?? null)) {
            $issues[] = self::issue('error', 'missing_name', 'Full name is missing.');
        }
        if (!\App::filled($profile['email'] ?? null) && !\App::filled($profile['phone'] ?? null)) {
            $issues[] = self::issue('error', 'missing_contact', 'Add at least an email or phone number.');
        }
        if (!\App::filled($profile['title'] ?? null)) {
            $issues[] = self::issue('warning', 'missing_title', 'Professional title is missing.');
        }
        if (!\App::filled($profile['location'] ?? null)) {
            $issues[] = self::issue('warning', 'missing_location', 'City / location is missing.');
        }

        $summary = trim((string) ($byKey['summary']['body'] ?? ''));
        if ($summary === '') {
            $issues[] = self::issue('warning', 'missing_profile', 'Kurzprofil / Profile section is empty.');
        } elseif (mb_strlen($summary) < 80) {
            $issues[] = self::issue('info', 'weak_profile', 'Profile is very short — aim for 3–5 lines.');
        } elseif (preg_match('/\b(I am|I\'m|Ich bin|mein|meine)\b/ui', $summary)) {
            $issues[] = self::issue('info', 'first_person', 'Prefer third-person or impersonal style in the profile (German applications).');
        }

        if ($experiences === []) {
            $issues[] = self::issue('warning', 'no_experience', 'No work experience entries yet.');
        } else {
            foreach ($experiences as $i => $job) {
                if (!is_array($job)) {
                    continue;
                }
                $start = trim((string) ($job['start_date'] ?? ''));
                $end = trim((string) ($job['end_date'] ?? ''));
                if ($start === '' && $end === '') {
                    $issues[] = self::issue('warning', 'missing_dates', 'Experience entry #' . ($i + 1) . ' has no dates.');
                }
                $bullets = trim((string) ($job['bullets'] ?? ''));
                foreach (preg_split('/\R+/u', $bullets) ?: [] as $line) {
                    $line = trim(preg_replace('/^[•\-\*]+\s*/u', '', $line) ?? $line);
                    if (mb_strlen($line) > 220) {
                        $issues[] = self::issue('info', 'long_bullet', 'A bullet is very long — consider shortening for scannability.');
                        break;
                    }
                }
            }
        }

        $skills = trim((string) ($byKey['skills']['body'] ?? ''));
        if ($skills !== '') {
            $tokens = preg_split('/[,·\|;\n]+/u', $skills) ?: [];
            $tokens = array_values(array_filter(array_map('trim', $tokens)));
            if (count($tokens) > 60) {
                $issues[] = self::issue('warning', 'too_many_skills', 'Skills list is very long — keep the most relevant.');
            }
            if (preg_match('/★|⭐|\[\s*x\s*\]|skill\s*bar/ui', $skills)) {
                $issues[] = self::issue('warning', 'skill_graphics', 'Avoid star ratings or skill bars — they hurt ATS parsing.');
            }
        }

        $template = ResumeLayout::resolveTemplate($meta['template'] ?? null);
        $photoMode = ResumePhotoMode::effective($meta['photo_mode'] ?? null, $template === 'ats');
        if ($template === 'ats' && $photoMode === ResumePhotoMode::WITH_PHOTO) {
            $issues[] = self::issue('info', 'ats_photo', 'ATS mode hides the photo automatically on export.');
        }
        if ($photoMode === ResumePhotoMode::WITH_PHOTO && !\App::shouldShowPhoto($profile)) {
            $issues[] = self::issue('info', 'photo_missing', 'Photo mode is on, but no photo is uploaded (or show photo is off).');
        }

        if ($pagesEstimate !== null && $pagesEstimate > 2.05) {
            $issues[] = self::issue(
                'warning',
                'too_long',
                'Resume is too long (~' . number_format($pagesEstimate, 1) . ' pages) — shorten low-relevance content (max ~2 A4 pages).'
            );
        } elseif ($pagesEstimate !== null && $pagesEstimate > 1.85) {
            $issues[] = self::issue('info', 'near_limit', 'Approaching 2 A4 pages — consider tighter density or shorter bullets.');
        }

        $errors = 0;
        $warnings = 0;
        $info = 0;
        foreach ($issues as $issue) {
            match ($issue['level']) {
                'error' => $errors++,
                'warning' => $warnings++,
                default => $info++,
            };
        }

        return [
            'ok' => $errors === 0,
            'issues' => $issues,
            'pages_estimate' => $pagesEstimate,
            'summary' => [
                'errors' => $errors,
                'warnings' => $warnings,
                'info' => $info,
            ],
        ];
    }

    /**
     * Simple JD match breakdown from overlapping tokens (estimate only).
     *
     * @param array{profile: array, sections: list<array>, experiences: list<array>} $payload
     * @return array{overall: int, skills: int, experience: int, education: int, keywords: int, languages: int}|null
     */
    public static function jdMatchEstimate(array $payload, string $jd): ?array
    {
        $jd = trim($jd);
        if ($jd === '') {
            return null;
        }
        $jdLower = mb_strtolower($jd);
        $skillsBody = '';
        $eduBody = '';
        $langBody = '';
        $summary = '';
        foreach ($payload['sections'] ?? [] as $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            $body = (string) ($section['body'] ?? '');
            if ($key === 'skills') {
                $skillsBody = $body;
            } elseif ($key === 'education') {
                $eduBody = $body;
            } elseif ($key === 'languages') {
                $langBody = $body;
            } elseif ($key === 'summary') {
                $summary = $body;
            }
        }
        $expText = '';
        foreach ($payload['experiences'] ?? [] as $job) {
            if (!is_array($job)) {
                continue;
            }
            $expText .= ' ' . ($job['position'] ?? '') . ' ' . ($job['bullets'] ?? '');
        }
        $title = (string) ($payload['profile']['title'] ?? '');

        $skillScore = self::overlapScore($skillsBody . ' ' . $title, $jdLower);
        $expScore = self::overlapScore($expText . ' ' . $summary, $jdLower);
        $eduScore = self::overlapScore($eduBody, $jdLower, true);
        $langScore = self::languageScore($langBody, $jdLower);
        $kwScore = self::overlapScore($skillsBody . ' ' . $expText . ' ' . $title, $jdLower);
        $overall = (int) round(($skillScore * 0.3) + ($expScore * 0.3) + ($kwScore * 0.25) + ($eduScore * 0.1) + ($langScore * 0.05));

        return [
            'overall' => min(100, max(0, $overall)),
            'skills' => $skillScore,
            'experience' => $expScore,
            'education' => $eduScore,
            'keywords' => $kwScore,
            'languages' => $langScore,
        ];
    }

    /** @return array{level: string, code: string, message: string} */
    private static function issue(string $level, string $code, string $message): array
    {
        return ['level' => $level, 'code' => $code, 'message' => $message];
    }

    private static function overlapScore(string $haystack, string $jdLower, bool $lenientEmpty = false): int
    {
        $haystack = mb_strtolower($haystack);
        if (trim($haystack) === '') {
            return $lenientEmpty ? 100 : 40;
        }
        $tokens = preg_split('/[^a-z0-9+#.\-]+/ui', $jdLower) ?: [];
        $wanted = [];
        foreach ($tokens as $t) {
            $t = trim((string) $t);
            if (mb_strlen($t) < 3) {
                continue;
            }
            if (in_array($t, ['and', 'the', 'with', 'for', 'und', 'der', 'die', 'das', 'mit', 'bei'], true)) {
                continue;
            }
            $wanted[$t] = true;
        }
        if ($wanted === []) {
            return 50;
        }
        $hit = 0;
        foreach (array_keys($wanted) as $t) {
            $t = (string) $t;
            if ($t !== '' && str_contains($haystack, $t)) {
                $hit++;
            }
        }
        $ratio = $hit / max(1, count($wanted));

        return (int) round(min(100, $ratio * 140));
    }

    private static function languageScore(string $langBody, string $jdLower): int
    {
        $needDe = str_contains($jdLower, 'deutsch') || str_contains($jdLower, 'german');
        $needEn = str_contains($jdLower, 'englisch') || str_contains($jdLower, 'english');
        if (!$needDe && !$needEn) {
            return 100;
        }
        $body = mb_strtolower($langBody);
        $score = 50;
        if ($needDe && (str_contains($body, 'deutsch') || str_contains($body, 'german'))) {
            $score += 25;
        }
        if ($needEn && (str_contains($body, 'englisch') || str_contains($body, 'english'))) {
            $score += 25;
        }

        return min(100, $score);
    }
}
