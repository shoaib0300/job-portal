<?php

declare(strict_types=1);

namespace KaamFit\Resume;

/**
 * German Lebenslauf section catalog, labels, ordering, and empty-section filtering.
 */
final class ResumeLayout
{
    public const TEMPLATES = [
        'modern_de' => [
            'label' => 'Modern German',
            'blurb' => 'Clean professional Lebenslauf with subtle accent and passport photo.',
        ],
        'ats' => [
            'label' => 'ATS',
            'blurb' => 'Single-column, parser-friendly layout for Workday, SAP, Personio.',
        ],
        'classic' => [
            'label' => 'Classic',
            'blurb' => 'Traditional German Bewerbung style with clear tabular structure.',
        ],
        'technical' => [
            'label' => 'Technical',
            'blurb' => 'Optimized for software / IT — skills and projects emphasized.',
        ],
        'minimal' => [
            'label' => 'Minimal',
            'blurb' => 'Quiet typography and whitespace; highly readable in print.',
        ],
    ];

    /** Legacy theme id → new template. */
    public const LEGACY_THEME_MAP = [
        'midnight' => 'modern_de',
        'sage' => 'modern_de',
        'modern' => 'modern_de',
        'compact' => 'modern_de',
        'sidebar' => 'modern_de',
        'executive' => 'classic',
        'company' => 'modern_de',
        'banner' => 'modern_de',
        'split' => 'modern_de',
        'slate' => 'modern_de',
        'serif' => 'classic',
        'cards' => 'modern_de',
        'timeline' => 'modern_de',
        'ivory' => 'ats',
        'classic' => 'classic',
        'minimal' => 'minimal',
        'modern_de' => 'modern_de',
        'ats' => 'ats',
        'technical' => 'technical',
    ];

    public const DENSITIES = ['normal', 'tight', 'compact'];

    public const MODES = ['professional', 'student'];

    /**
     * @return array<string, array{en: string, de: string, default_visible: bool}>
     */
    public static function sectionCatalog(): array
    {
        return [
            'summary' => ['en' => 'Profile', 'de' => 'Kurzprofil', 'default_visible' => true],
            'experience' => ['en' => 'Work Experience', 'de' => 'Berufserfahrung', 'default_visible' => true],
            'education' => ['en' => 'Education', 'de' => 'Ausbildung / Studium', 'default_visible' => true],
            'skills' => ['en' => 'Skills', 'de' => 'Kenntnisse & Fähigkeiten', 'default_visible' => true],
            'languages' => ['en' => 'Languages', 'de' => 'Sprachen', 'default_visible' => true],
            'certificates' => ['en' => 'Certificates & Training', 'de' => 'Zertifikate & Weiterbildungen', 'default_visible' => false],
            'projects' => ['en' => 'Projects', 'de' => 'Projekte', 'default_visible' => false],
            'internships' => ['en' => 'Internships', 'de' => 'Praktika', 'default_visible' => false],
            'interests' => ['en' => 'Interests', 'de' => 'Interessen', 'default_visible' => false],
            'additional' => ['en' => 'Additional Information', 'de' => 'Weitere Informationen', 'default_visible' => false],
            'signature' => ['en' => 'Place / Date', 'de' => 'Ort / Datum', 'default_visible' => false],
        ];
    }

    /** @return list<string> */
    public static function defaultOrder(string $mode = 'professional'): array
    {
        if ($mode === 'student') {
            return [
                'summary',
                'education',
                'projects',
                'internships',
                'experience',
                'skills',
                'languages',
                'certificates',
                'interests',
                'additional',
                'signature',
            ];
        }

        return [
            'summary',
            'experience',
            'education',
            'skills',
            'languages',
            'certificates',
            'projects',
            'internships',
            'interests',
            'additional',
            'signature',
        ];
    }

    public static function sectionLabel(string $key, string $lang = 'en'): string
    {
        $catalog = self::sectionCatalog();
        if (!isset($catalog[$key])) {
            return ucfirst(str_replace('_', ' ', $key));
        }
        $lang = str_starts_with(strtolower($lang), 'de') ? 'de' : 'en';

        return $catalog[$key][$lang];
    }

    public static function resolveTemplate(?string $theme): string
    {
        $raw = trim((string) ($theme ?? ''));
        if ($raw === '') {
            $raw = (string) (\App::setting('resume_template') ?: \App::setting('theme', 'modern_de') ?: 'modern_de');
        }
        $mapped = self::LEGACY_THEME_MAP[$raw] ?? $raw;
        if (!isset(self::TEMPLATES[$mapped])) {
            return 'modern_de';
        }

        return $mapped;
    }

    public static function resolveMode(?string $mode = null): string
    {
        $mode = $mode ?? (\App::setting('resume_mode', 'professional') ?: 'professional');

        return in_array($mode, self::MODES, true) ? $mode : 'professional';
    }

    public static function resolveDensity(?string $density = null): string
    {
        $density = $density ?? (\App::setting('resume_density', 'normal') ?: 'normal');

        return in_array($density, self::DENSITIES, true) ? $density : 'normal';
    }

    /**
     * Default meta for snapshots / live settings.
     *
     * @return array{template: string, mode: string, photo_mode: string, density: string, show_personal_extras: bool, show_signature: bool, section_order: list<string>}
     */
    public static function defaultMeta(): array
    {
        $mode = self::resolveMode();

        return [
            'template' => self::resolveTemplate(null),
            'mode' => $mode,
            'photo_mode' => ResumePhotoMode::resolve(null),
            'density' => self::resolveDensity(null),
            'show_personal_extras' => (\App::setting('show_personal_extras', '0') ?: '0') === '1',
            'show_signature' => (\App::setting('show_signature', '0') ?: '0') === '1',
            // Empty = respect resume_sections.sort_order (user drag-reorder).
            'section_order' => [],
        ];
    }

    /**
     * Merge snapshot meta with live settings (snapshot wins when set).
     *
     * @param array<string, mixed> $snapshotMeta
     * @return array{template: string, mode: string, photo_mode: string, density: string, show_personal_extras: bool, show_signature: bool, section_order: list<string>}
     */
    public static function mergeMeta(array $snapshotMeta = []): array
    {
        $base = self::defaultMeta();
        if ($snapshotMeta === []) {
            return $base;
        }
        if (!empty($snapshotMeta['template'])) {
            $base['template'] = self::resolveTemplate((string) $snapshotMeta['template']);
        }
        if (!empty($snapshotMeta['mode'])) {
            $base['mode'] = self::resolveMode((string) $snapshotMeta['mode']);
        }
        if (!empty($snapshotMeta['photo_mode'])) {
            $base['photo_mode'] = ResumePhotoMode::resolve((string) $snapshotMeta['photo_mode']);
        }
        if (!empty($snapshotMeta['density'])) {
            $base['density'] = self::resolveDensity((string) $snapshotMeta['density']);
        }
        if (array_key_exists('show_personal_extras', $snapshotMeta)) {
            $base['show_personal_extras'] = (bool) $snapshotMeta['show_personal_extras'];
        }
        if (array_key_exists('show_signature', $snapshotMeta)) {
            $base['show_signature'] = (bool) $snapshotMeta['show_signature'];
        }
        if (!empty($snapshotMeta['section_order']) && is_array($snapshotMeta['section_order'])) {
            $order = [];
            foreach ($snapshotMeta['section_order'] as $key) {
                $key = trim((string) $key);
                if ($key !== '') {
                    $order[] = $key;
                }
            }
            if ($order !== []) {
                $base['section_order'] = $order;
            }
        }

        return $base;
    }

    /**
     * Filter empty sections and optionally re-order by meta.section_order.
     *
     * @param list<array<string, mixed>> $sections
     * @param list<array<string, mixed>> $experiences
     * @param array{section_order?: list<string>, show_signature?: bool} $meta
     * @return list<array<string, mixed>>
     */
    public static function prepareSections(array $sections, array $experiences, array $meta = []): array
    {
        $visible = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            if ((int) ($section['visible'] ?? 1) !== 1) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key === 'experience') {
                if ($experiences === []) {
                    continue;
                }
                $visible[] = $section;
                continue;
            }
            if ($key === 'signature' && empty($meta['show_signature'])) {
                // Still allow if body filled and visible — signature content is the body.
            }
            $body = trim((string) ($section['body'] ?? ''));
            if ($body === '' && $key !== 'experience') {
                continue;
            }
            $visible[] = $section;
        }

        $order = $meta['section_order'] ?? [];
        if (!is_array($order) || $order === []) {
            return $visible;
        }

        $rank = [];
        foreach (array_values($order) as $i => $key) {
            $rank[(string) $key] = $i;
        }
        usort($visible, static function (array $a, array $b) use ($rank): int {
            $ka = (string) ($a['section_key'] ?? '');
            $kb = (string) ($b['section_key'] ?? '');
            $ra = $rank[$ka] ?? 1000 + (int) ($a['sort_order'] ?? 0);
            $rb = $rank[$kb] ?? 1000 + (int) ($b['sort_order'] ?? 0);
            if ($ra === $rb) {
                return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
            }

            return $ra <=> $rb;
        });

        return $visible;
    }

    /**
     * Seed definitions for missing optional sections (visible=0).
     *
     * @return list<array{0: string, 1: string, 2: string, 3: int, 4: int}>
     */
    public static function seedSections(string $lang = 'en'): array
    {
        $mode = 'professional';
        $order = self::defaultOrder($mode);
        $out = [];
        $sort = 10;
        foreach ($order as $key) {
            $meta = self::sectionCatalog()[$key] ?? null;
            if ($meta === null) {
                continue;
            }
            $title = self::sectionLabel($key, $lang);
            $body = '';
            if ($key === 'summary') {
                $body = $lang === 'de'
                    ? 'Schreiben Sie ein kurzes berufliches Kurzprofil (3–5 Zeilen).'
                    : 'Write a short professional profile (3–5 lines).';
            }
            if ($key === 'languages') {
                $body = $lang === 'de'
                    ? "Deutsch — B1\nEnglisch — C1"
                    : "German — B1\nEnglish — C1";
            }
            $visible = $meta['default_visible'] ? 1 : 0;
            $out[] = [$key, $title, $body, $sort, $visible];
            $sort += 10;
        }

        return $out;
    }

    /**
     * Add missing catalog sections for a user without wiping existing bodies.
     */
    public static function ensureMissingSections(int $userId, string $lang = 'en'): void
    {
        if ($userId <= 0) {
            return;
        }
        $pdo = \Db::pdo();
        $stmt = $pdo->prepare('SELECT section_key FROM resume_sections WHERE user_id = ?');
        $stmt->execute([$userId]);
        $have = [];
        foreach ($stmt->fetchAll() as $row) {
            $have[(string) $row['section_key']] = true;
        }
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM resume_sections WHERE user_id = ?');
        $maxStmt->execute([$userId]);
        $sort = (int) $maxStmt->fetchColumn();
        $ins = $pdo->prepare(
            'INSERT INTO resume_sections (user_id, section_key, title, body, sort_order, visible)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach (self::seedSections($lang) as $row) {
            [$key, $title, $body, , $visible] = $row;
            if (isset($have[$key])) {
                continue;
            }
            // Do not re-add core sections that were intentionally never created with empty placeholder
            // if user already has content under a different structure — only add truly missing keys.
            $sort += 10;
            $ins->execute([$userId, $key, $title, $body, $sort, $visible]);
        }
    }

    /** Studio chrome strings. */
    public static function ui(string $key, string $lang = 'en'): string
    {
        $isDe = str_starts_with(strtolower($lang), 'de');
        $map = [
            'studio' => ['en' => 'Resume Studio', 'de' => 'Lebenslauf-Studio'],
            'sections' => ['en' => 'Sections', 'de' => 'Abschnitte'],
            'preview' => ['en' => 'Preview', 'de' => 'Vorschau'],
            'editor' => ['en' => 'Editor', 'de' => 'Bearbeiten'],
            'profile' => ['en' => 'Personal Information', 'de' => 'Persönliche Daten'],
            'layout' => ['en' => 'Layout & template', 'de' => 'Layout & Vorlage'],
            'check' => ['en' => 'Resume Check', 'de' => 'Lebenslauf-Check'],
            'download' => ['en' => 'Download PDF', 'de' => 'PDF herunterladen'],
            'standard' => ['en' => 'Standard', 'de' => 'Standard'],
            'ats' => ['en' => 'ATS', 'de' => 'ATS'],
            'german' => ['en' => 'German', 'de' => 'Deutsch'],
            'english' => ['en' => 'English', 'de' => 'Englisch'],
            'match' => ['en' => 'KaamFit Match Estimate', 'de' => 'KaamFit Match-Schätzung'],
        ];
        $row = $map[$key] ?? ['en' => $key, 'de' => $key];

        return $isDe ? $row['de'] : $row['en'];
    }
}
