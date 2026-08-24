<?php

declare(strict_types=1);

/** Translate resume / cover content for EN or DE PDF downloads (DeepL, else local LibreTranslate). */
final class DocTranslate
{
    /**
     * @param array{profile: array<string, mixed>, sections: list<array<string, mixed>>, experiences: list<array<string, mixed>>} $payload
     * @return array{profile: array<string, mixed>, sections: list<array<string, mixed>>, experiences: list<array<string, mixed>>}
     */
    public static function resume(array $payload, string $lang): array
    {
        $lang = LibreTranslate::normalizeLang($lang);
        if ($lang === 'en') {
            return $payload;
        }

        $source = 'en';
        $profile = $payload['profile'];
        if (!empty($profile['title'])) {
            $profile['title'] = LibreTranslate::translate((string) $profile['title'], $lang, $source);
        }
        if (!empty($profile['location'])) {
            $profile['location'] = LibreTranslate::translate((string) $profile['location'], $lang, $source);
        }

        $sections = [];
        foreach ($payload['sections'] as $section) {
            if (!is_array($section)) {
                continue;
            }
            if (!empty($section['title'])) {
                $section['title'] = LibreTranslate::translate((string) $section['title'], $lang, $source);
            }
            if (!empty($section['body'])) {
                $section['body'] = LibreTranslate::translate((string) $section['body'], $lang, $source);
            }
            $sections[] = $section;
        }

        $experiences = [];
        foreach ($payload['experiences'] as $job) {
            if (!is_array($job)) {
                continue;
            }
            if (!empty($job['position'])) {
                $job['position'] = LibreTranslate::translate((string) $job['position'], $lang, $source);
            }
            if (!empty($job['location'])) {
                $job['location'] = LibreTranslate::translate((string) $job['location'], $lang, $source);
            }
            if (!empty($job['bullets'])) {
                $job['bullets'] = LibreTranslate::translate((string) $job['bullets'], $lang, $source);
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
    public static function cover(?array $letter, array $profile, string $lang): array
    {
        $lang = LibreTranslate::normalizeLang($lang);
        if ($lang === 'en') {
            return ['letter' => $letter, 'profile' => $profile];
        }

        $source = 'en';
        if (!empty($profile['title'])) {
            $profile['title'] = LibreTranslate::translate((string) $profile['title'], $lang, $source);
        }
        if (!empty($profile['location'])) {
            $profile['location'] = LibreTranslate::translate((string) $profile['location'], $lang, $source);
        }
        if ($letter !== null) {
            if (!empty($letter['company'])) {
                $letter['company'] = LibreTranslate::translate((string) $letter['company'], $lang, $source);
            }
            if (!empty($letter['body'])) {
                $letter['body'] = LibreTranslate::translate((string) $letter['body'], $lang, $source);
            }
        }
        return ['letter' => $letter, 'profile' => $profile];
    }
}
