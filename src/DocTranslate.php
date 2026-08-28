<?php

declare(strict_types=1);

/** Translate resume / cover content for paid PDF export (DeepL). */
final class DocTranslate
{
    /**
     * @param array{profile: array<string, mixed>, sections: list<array<string, mixed>>, experiences: list<array<string, mixed>>} $payload
     * @return array{profile: array<string, mixed>, sections: list<array<string, mixed>>, experiences: list<array<string, mixed>>}
     */
    public static function resume(array $payload, string $targetLang, string $sourceLang = 'en'): array
    {
        $targetLang = TranslateLanguages::normalize($targetLang);
        $sourceLang = LibreTranslate::normalizeLang($sourceLang);
        if ($targetLang === $sourceLang) {
            return $payload;
        }

        $engine = 'deepl';
        $profile = $payload['profile'];
        if (!empty($profile['title'])) {
            $profile['title'] = LibreTranslate::translate((string) $profile['title'], $targetLang, $sourceLang, $engine);
        }
        if (!empty($profile['location'])) {
            $profile['location'] = LibreTranslate::translate((string) $profile['location'], $targetLang, $sourceLang, $engine);
        }

        $sections = [];
        foreach ($payload['sections'] as $section) {
            if (!is_array($section)) {
                continue;
            }
            if (!empty($section['title'])) {
                $section['title'] = LibreTranslate::translate((string) $section['title'], $targetLang, $sourceLang, $engine);
            }
            if (!empty($section['body'])) {
                $section['body'] = LibreTranslate::translateMultiline((string) $section['body'], $targetLang, $sourceLang, $engine);
            }
            $sections[] = $section;
        }

        $experiences = [];
        foreach ($payload['experiences'] as $job) {
            if (!is_array($job)) {
                continue;
            }
            if (!empty($job['position'])) {
                $job['position'] = LibreTranslate::translate((string) $job['position'], $targetLang, $sourceLang, $engine);
            }
            if (!empty($job['location'])) {
                $job['location'] = LibreTranslate::translate((string) $job['location'], $targetLang, $sourceLang, $engine);
            }
            if (!empty($job['bullets'])) {
                $job['bullets'] = LibreTranslate::translateMultiline((string) $job['bullets'], $targetLang, $sourceLang, $engine);
            }
            $experiences[] = $job;
        }

        $payload['profile'] = $profile;
        $payload['sections'] = $sections;
        $payload['experiences'] = $experiences;
        return $payload;
    }

    /**
     * @param array<string, mixed>|null $letter
     * @param array<string, mixed> $profile
     * @return array{letter: ?array<string, mixed>, profile: array<string, mixed>}
     */
    public static function cover(?array $letter, array $profile, string $targetLang, string $sourceLang = 'en'): array
    {
        $targetLang = TranslateLanguages::normalize($targetLang);
        $sourceLang = LibreTranslate::normalizeLang($sourceLang);
        if ($targetLang === $sourceLang) {
            return ['letter' => $letter, 'profile' => $profile];
        }

        if (!empty($profile['title'])) {
            $profile['title'] = LibreTranslate::translate((string) $profile['title'], $targetLang, $sourceLang, 'deepl');
        }
        if (!empty($profile['location'])) {
            $profile['location'] = LibreTranslate::translate((string) $profile['location'], $targetLang, $sourceLang, 'deepl');
        }
        if ($letter !== null) {
            if (!empty($letter['body'])) {
                $letter['body'] = LibreTranslate::translateMultiline((string) $letter['body'], $targetLang, $sourceLang, 'deepl');
            }
        }
        return ['letter' => $letter, 'profile' => $profile];
    }
}
