<?php

declare(strict_types=1);

namespace KaamFit\Resume;

/**
 * Photo / anonymization modes for German Lebenslauf vs ATS.
 */
final class ResumePhotoMode
{
    public const WITH_PHOTO = 'with_photo';
    public const WITHOUT_PHOTO = 'without_photo';
    public const ANONYMIZED = 'anonymized';

    public const MODES = [self::WITH_PHOTO, self::WITHOUT_PHOTO, self::ANONYMIZED];

    public static function resolve(?string $mode = null): string
    {
        $mode = trim((string) ($mode ?? ''));
        if ($mode === '') {
            $mode = (string) (\App::setting('photo_mode', self::WITH_PHOTO) ?: self::WITH_PHOTO);
        }

        return in_array($mode, self::MODES, true) ? $mode : self::WITH_PHOTO;
    }

    /**
     * Effective mode after ATS override.
     */
    public static function effective(?string $mode, bool $atsMode): string
    {
        if ($atsMode) {
            return self::WITHOUT_PHOTO;
        }

        return self::resolve($mode);
    }

    public static function shouldShowPhoto(array $profile, string $mode, bool $atsMode = false): bool
    {
        $mode = self::effective($mode, $atsMode);
        if ($mode !== self::WITH_PHOTO) {
            return false;
        }
        if (!\App::shouldShowPhoto($profile)) {
            return false;
        }

        return \App::photoUrl($profile) !== '';
    }

    /**
     * Strip sensitive / identifying fields for anonymized or when extras disabled.
     *
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    public static function prepareProfile(array $profile, string $mode, bool $showPersonalExtras, bool $atsMode = false): array
    {
        $mode = self::effective($mode, $atsMode);
        $out = $profile;

        if ($mode === self::ANONYMIZED || $atsMode || !$showPersonalExtras) {
            $out['gender'] = '';
            $out['date_of_birth'] = null;
            $out['nationality'] = '';
            // Keep country/location for job relevance unless anonymized.
            if ($mode === self::ANONYMIZED) {
                $out['country'] = '';
            }
        }

        if ($mode === self::ANONYMIZED) {
            $out['show_photo'] = 0;
        }

        if ($atsMode) {
            $out['show_photo'] = 0;
        }

        return $out;
    }
}
