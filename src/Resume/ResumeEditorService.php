<?php

declare(strict_types=1);

namespace KaamFit\Resume;

use App;
use Auth;
use Db;
use InvalidArgumentException;
use RuntimeException;
use Versions;

/**
 * JSON API business logic for the resume studio editor.
 */
final class ResumeEditorService
{
    /** @return array<string, mixed> */
    public static function getState(): array
    {
        $active = Versions::activeResumeVersion();
        $base = Versions::baseResumeVersion();
        $profile = App::profile();
        $sections = App::sections(false);
        $experiences = App::experiences(false);
        $meta = ResumeLayout::mergeMeta(
            is_array($active) ? (Versions::decodeSnapshot((string) ($active['snapshot'] ?? ''))['meta'] ?? []) : []
        );

        // Prefer live section order; sync meta.section_order from DB.
        $order = [];
        foreach ($sections as $section) {
            $order[] = (string) ($section['section_key'] ?? '');
        }
        if ($order !== []) {
            $meta['section_order'] = $order;
        }

        $enriched = [];
        foreach ($sections as $section) {
            $body = (string) ($section['body'] ?? '');
            $decoded = SectionBody::decode($body);
            $enriched[] = [
                'id' => (int) ($section['id'] ?? 0),
                'section_key' => (string) ($section['section_key'] ?? ''),
                'title' => (string) ($section['title'] ?? ''),
                'body' => $body,
                'sort_order' => (int) ($section['sort_order'] ?? 0),
                'visible' => (int) ($section['visible'] ?? 1) === 1,
                'body_format' => $decoded !== null ? 'structured' : 'plain',
                'structured' => $decoded,
            ];
        }

        return [
            'ok' => true,
            'active_version' => $active ? self::versionSummary($active) : null,
            'main_version' => $base ? self::versionSummary($base) : null,
            'profile' => $profile,
            'sections' => $enriched,
            'experiences' => array_map(static function (array $e): array {
                return [
                    'id' => (int) ($e['id'] ?? 0),
                    'company' => (string) ($e['company'] ?? ''),
                    'position' => (string) ($e['position'] ?? ''),
                    'location' => (string) ($e['location'] ?? ''),
                    'start_date' => (string) ($e['start_date'] ?? ''),
                    'end_date' => (string) ($e['end_date'] ?? ''),
                    'bullets' => (string) ($e['bullets'] ?? ''),
                    'sort_order' => (int) ($e['sort_order'] ?? 0),
                    'visible' => (int) ($e['visible'] ?? 1) === 1,
                ];
            }, $experiences),
            'meta' => $meta,
            'catalog' => ResumeLayout::sectionCatalog(),
            'addable' => ResumeLayout::addableSectionKeys(),
            'templates' => ResumeLayout::TEMPLATES,
            'fonts' => App::fonts(),
            'pdf' => [
                'normal' => \PdfExport::downloadHrefOriginal('resume', self::versionQuery($active)),
                'ats' => \PdfExport::downloadHrefAts('resume', self::versionQuery($active)),
            ],
        ];
    }

    /** @param array<string, mixed> $fields */
    public static function patchProfile(array $fields): array
    {
        $current = App::profile();
        $id = (int) ($current['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Profile not found');
        }

        $allowed = [
            'full_name', 'title', 'email', 'phone', 'location', 'gender',
            'country', 'nationality', 'date_of_birth', 'show_photo',
        ];
        $updates = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }
            if ($key === 'show_photo') {
                $updates[] = 'show_photo = ?';
                $params[] = !empty($fields[$key]) ? 1 : 0;
                continue;
            }
            if ($key === 'date_of_birth') {
                $raw = trim((string) $fields[$key]);
                $updates[] = 'date_of_birth = ?';
                $params[] = $raw !== '' ? $raw : null;
                continue;
            }
            $updates[] = $key . ' = ?';
            $params[] = trim((string) $fields[$key]);
        }

        if (array_key_exists('links', $fields) && is_array($fields['links'])) {
            $links = [];
            foreach ($fields['links'] as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));
                if ($label === '' && $url === '') {
                    continue;
                }
                $links[] = ['label' => $label !== '' ? $label : $url, 'url' => $url];
            }
            $updates[] = 'links = ?';
            $params[] = json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($updates === []) {
            return ['ok' => true, 'profile' => App::profile()];
        }

        $params[] = $id;
        $params[] = Auth::id();
        $sql = 'UPDATE resume_profile SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?';
        Db::pdo()->prepare($sql)->execute($params);

        return ['ok' => true, 'profile' => App::profile()];
    }

    /** @param array<string, mixed> $fields */
    public static function patchSection(int $id, array $fields): array
    {
        $row = self::ownedSection($id);
        $updates = [];
        $params = [];

        if (array_key_exists('title', $fields)) {
            $updates[] = 'title = ?';
            $params[] = trim((string) $fields['title']) ?: (string) $row['title'];
        }
        if (array_key_exists('visible', $fields)) {
            $updates[] = 'visible = ?';
            $params[] = !empty($fields['visible']) ? 1 : 0;
        }
        if (array_key_exists('body', $fields)) {
            $body = $fields['body'];
            if (is_array($body)) {
                $body = SectionBody::encode($body);
            } else {
                $body = (string) $body;
            }
            $updates[] = 'body = ?';
            $params[] = $body;
        }
        if (array_key_exists('structured', $fields) && is_array($fields['structured'])) {
            $updates[] = 'body = ?';
            $params[] = SectionBody::encode($fields['structured']);
        }

        if ($updates === []) {
            return ['ok' => true, 'section' => self::sectionPayload($row)];
        }

        $params[] = $id;
        $params[] = Auth::id();
        Db::pdo()->prepare(
            'UPDATE resume_sections SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?'
        )->execute($params);

        return ['ok' => true, 'section' => self::sectionPayload(self::ownedSection($id))];
    }

    /** @param list<int|string> $orderedIds */
    public static function reorderSections(array $orderedIds): array
    {
        $ids = [];
        foreach ($orderedIds as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            throw new InvalidArgumentException('No section ids provided.');
        }

        $pdo = Db::pdo();
        $owned = [];
        $stmt = $pdo->prepare('SELECT id, section_key FROM resume_sections WHERE user_id = ?');
        $stmt->execute([Auth::id()]);
        foreach ($stmt->fetchAll() as $row) {
            $owned[(int) $row['id']] = (string) $row['section_key'];
        }

        foreach ($ids as $id) {
            if (!isset($owned[$id])) {
                throw new InvalidArgumentException('Invalid section id.');
            }
        }

        $upd = $pdo->prepare('UPDATE resume_sections SET sort_order = ? WHERE id = ? AND user_id = ?');
        $order = 10;
        $keys = [];
        foreach ($ids as $id) {
            $upd->execute([$order, $id, Auth::id()]);
            $keys[] = $owned[$id];
            $order += 10;
        }

        // Append any owned sections not in the list at the end.
        foreach ($owned as $id => $key) {
            if (in_array($id, $ids, true)) {
                continue;
            }
            $upd->execute([$order, $id, Auth::id()]);
            $keys[] = $key;
            $order += 10;
        }

        self::touchActiveSnapshotMeta(['section_order' => $keys]);

        return ['ok' => true, 'section_order' => $keys];
    }

    public static function addSection(string $type, string $customTitle = ''): array
    {
        $type = strtolower(trim($type));
        $catalog = ResumeLayout::sectionCatalog();
        $addable = ResumeLayout::addableSectionKeys();

        if ($type === 'custom' || str_starts_with($type, 'custom_')) {
            $key = 'custom_' . substr(bin2hex(random_bytes(4)), 0, 8);
            $title = trim($customTitle) !== '' ? trim($customTitle) : 'Custom Section';
            $body = SectionBody::encode(['text' => '']);
        } elseif (in_array($type, $addable, true) && isset($catalog[$type])) {
            // Avoid duplicate unique keys when already present.
            $existing = Db::pdo()->prepare(
                'SELECT id FROM resume_sections WHERE user_id = ? AND section_key = ? LIMIT 1'
            );
            $existing->execute([Auth::id(), $type]);
            if ($existing->fetch()) {
                throw new InvalidArgumentException('That section already exists. Enable it or edit the existing one.');
            }
            $key = $type;
            $lang = App::resolveDocumentLang();
            $title = ResumeLayout::sectionLabel($type, $lang);
            $body = match ($type) {
                'skills' => SectionBody::encode(['categories' => [['name' => '', 'skills' => []]]]),
                'languages' => SectionBody::encode(['entries' => [['name' => '', 'level' => '']]]),
                'summary', 'hobbies', 'interests', 'additional' => SectionBody::encode(['text' => '']),
                default => SectionBody::encode(['entries' => []]),
            };
        } else {
            throw new InvalidArgumentException('Unknown section type.');
        }

        $pdo = Db::pdo();
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM resume_sections WHERE user_id = ?');
        $maxStmt->execute([Auth::id()]);
        $max = (int) $maxStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO resume_sections (user_id, section_key, title, body, sort_order, visible) VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([Auth::id(), $key, $title, $body, $max + 10]);
        $id = (int) $pdo->lastInsertId();

        return ['ok' => true, 'section' => self::sectionPayload(self::ownedSection($id))];
    }

    public static function removeSection(int $id): array
    {
        $row = self::ownedSection($id);
        $key = (string) ($row['section_key'] ?? '');
        $protected = ['summary', 'experience'];
        if (in_array($key, $protected, true)) {
            // Soft-hide instead of delete for core sections.
            Db::pdo()->prepare(
                'UPDATE resume_sections SET visible = 0 WHERE id = ? AND user_id = ?'
            )->execute([$id, Auth::id()]);

            return ['ok' => true, 'hidden' => true, 'section_id' => $id];
        }

        Db::pdo()->prepare('DELETE FROM resume_sections WHERE id = ? AND user_id = ?')
            ->execute([$id, Auth::id()]);

        return ['ok' => true, 'deleted' => true, 'section_id' => $id];
    }

    /** @param array<string, mixed> $fields */
    public static function patchExperience(int $id, array $fields): array
    {
        self::ownedExperience($id);
        $map = [
            'company' => 'company',
            'position' => 'position',
            'location' => 'location',
            'start_date' => 'start_date',
            'end_date' => 'end_date',
            'bullets' => 'bullets',
        ];
        $updates = [];
        $params = [];
        foreach ($map as $in => $col) {
            if (!array_key_exists($in, $fields)) {
                continue;
            }
            $updates[] = $col . ' = ?';
            $params[] = $in === 'bullets' ? (string) $fields[$in] : trim((string) $fields[$in]);
        }
        if (array_key_exists('visible', $fields)) {
            $updates[] = 'visible = ?';
            $params[] = !empty($fields['visible']) ? 1 : 0;
        }
        if ($updates === []) {
            return ['ok' => true];
        }
        $params[] = $id;
        $params[] = Auth::id();
        Db::pdo()->prepare(
            'UPDATE experience_entries SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?'
        )->execute($params);

        return ['ok' => true, 'experience' => self::experiencePayload(self::ownedExperience($id))];
    }

    public static function addExperience(array $fields = []): array
    {
        $pdo = Db::pdo();
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM experience_entries WHERE user_id = ?');
        $maxStmt->execute([Auth::id()]);
        $max = (int) $maxStmt->fetchColumn();
        $stmt = $pdo->prepare(
            'INSERT INTO experience_entries (user_id, company, position, location, start_date, end_date, bullets, sort_order, visible)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            Auth::id(),
            trim((string) ($fields['company'] ?? '')),
            trim((string) ($fields['position'] ?? '')),
            trim((string) ($fields['location'] ?? '')),
            trim((string) ($fields['start_date'] ?? '')),
            trim((string) ($fields['end_date'] ?? '')),
            (string) ($fields['bullets'] ?? ''),
            $max + 10,
        ]);
        $id = (int) $pdo->lastInsertId();

        return ['ok' => true, 'experience' => self::experiencePayload(self::ownedExperience($id))];
    }

    public static function duplicateExperience(int $id): array
    {
        $src = self::ownedExperience($id);
        return self::addExperience([
            'company' => (string) ($src['company'] ?? ''),
            'position' => (string) ($src['position'] ?? ''),
            'location' => (string) ($src['location'] ?? ''),
            'start_date' => (string) ($src['start_date'] ?? ''),
            'end_date' => (string) ($src['end_date'] ?? ''),
            'bullets' => (string) ($src['bullets'] ?? ''),
        ]);
    }

    public static function deleteExperience(int $id): array
    {
        self::ownedExperience($id);
        Db::pdo()->prepare('DELETE FROM experience_entries WHERE id = ? AND user_id = ?')
            ->execute([$id, Auth::id()]);

        return ['ok' => true, 'deleted' => true, 'experience_id' => $id];
    }

    /** @param list<int|string> $orderedIds */
    public static function reorderExperiences(array $orderedIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $orderedIds), static fn(int $i): bool => $i > 0));
        $pdo = Db::pdo();
        $owned = [];
        $stmt = $pdo->prepare('SELECT id FROM experience_entries WHERE user_id = ?');
        $stmt->execute([Auth::id()]);
        foreach ($stmt->fetchAll() as $row) {
            $owned[(int) $row['id']] = true;
        }
        foreach ($ids as $id) {
            if (!isset($owned[$id])) {
                throw new InvalidArgumentException('Invalid experience id.');
            }
        }
        $upd = $pdo->prepare('UPDATE experience_entries SET sort_order = ? WHERE id = ? AND user_id = ?');
        $order = 10;
        foreach ($ids as $id) {
            $upd->execute([$order, $id, Auth::id()]);
            $order += 10;
        }

        return ['ok' => true];
    }

    /** @param array<string, mixed> $fields */
    public static function patchDesign(array $fields): array
    {
        $metaPatch = [];
        if (isset($fields['template'])) {
            $template = ResumeLayout::resolveTemplate((string) $fields['template']);
            App::setSetting('resume_template', $template);
            App::setSetting('theme', $template);
            $metaPatch['template'] = $template;
            if ($template === 'german_qa' && empty($fields['accent_color'])) {
                App::setSetting('accent_color', '#17365D');
                $metaPatch['accent_color'] = '#17365D';
            }
            if ($template === 'ats') {
                App::setSetting('photo_mode', ResumePhotoMode::WITHOUT_PHOTO);
                $metaPatch['photo_mode'] = ResumePhotoMode::WITHOUT_PHOTO;
            }
        }
        if (isset($fields['font_family'])) {
            $font = App::resolveFont((string) $fields['font_family']);
            App::setSetting('font_family', $font);
            $metaPatch['font_family'] = $font;
        }
        if (isset($fields['accent_color'])) {
            $accent = App::resolveAccent((string) $fields['accent_color']);
            App::setSetting('accent_color', $accent);
            $metaPatch['accent_color'] = $accent;
        }
        if (isset($fields['density'])) {
            $density = ResumeLayout::resolveDensity((string) $fields['density']);
            App::setSetting('resume_density', $density);
            $metaPatch['density'] = $density;
        }
        if (isset($fields['mode'])) {
            $mode = ResumeLayout::resolveMode((string) $fields['mode']);
            App::setSetting('resume_mode', $mode);
            $metaPatch['mode'] = $mode;
        }
        if (isset($fields['photo_mode'])) {
            $photo = ResumePhotoMode::resolve((string) $fields['photo_mode']);
            App::setSetting('photo_mode', $photo);
            $metaPatch['photo_mode'] = $photo;
        }
        if (isset($fields['font_size'])) {
            $raw = (string) $fields['font_size'];
            if (in_array($raw, ['sm', 'md', 'lg'], true)) {
                $size = $raw;
            } else {
                $n = (int) $raw;
                $size = $n <= 90 ? 'sm' : ($n >= 110 ? 'lg' : 'md');
            }
            App::setSetting('font_size', $size);
            $metaPatch['font_size'] = $size;
        }
        if (isset($fields['page_format'])) {
            $format = ResumeLayout::resolvePageFormat((string) $fields['page_format']);
            App::setSetting('page_format', $format);
            $metaPatch['page_format'] = $format;
        }
        if (isset($fields['margin'])) {
            $margin = ResumeLayout::resolveMargin((string) $fields['margin']);
            App::setSetting('page_margin', $margin);
            $metaPatch['margin'] = $margin;
        }
        if (isset($fields['date_format'])) {
            $df = ResumeLayout::resolveDateFormat((string) $fields['date_format']);
            App::setSetting('date_format', $df);
            $metaPatch['date_format'] = $df;
        }
        if (array_key_exists('show_personal_extras', $fields)) {
            $v = !empty($fields['show_personal_extras']) ? '1' : '0';
            App::setSetting('show_personal_extras', $v);
            $metaPatch['show_personal_extras'] = $v === '1';
        }
        if (array_key_exists('show_signature', $fields)) {
            $v = !empty($fields['show_signature']) ? '1' : '0';
            App::setSetting('show_signature', $v);
            $metaPatch['show_signature'] = $v === '1';
        }

        if ($metaPatch !== []) {
            self::touchActiveSnapshotMeta($metaPatch);
        }

        $active = Versions::activeResumeVersion();
        $meta = ResumeLayout::mergeMeta(
            $active ? (Versions::decodeSnapshot((string) ($active['snapshot'] ?? ''))['meta'] ?? []) : []
        );

        return ['ok' => true, 'meta' => $meta];
    }

    public static function saveVersion(?string $title = null): array
    {
        $active = Versions::activeResumeVersion();
        $base = Versions::baseResumeVersion();
        $target = $active ?: $base;
        $snapshot = Versions::captureSnapshot();
        if ($target) {
            $isBase = Versions::isMasterResume($target);
            $saveTitle = $title !== null && trim($title) !== ''
                ? trim($title)
                : (string) ($target['title'] ?? Versions::MASTER_CV_LABEL);
            $id = Versions::saveResumeVersion(
                $saveTitle,
                $snapshot,
                (string) ($target['company'] ?? ''),
                (string) ($target['note'] ?? ''),
                $isBase,
                (int) $target['id'],
                true
            );
        } else {
            $id = Versions::updateBaseFromLive();
        }

        $row = Versions::resumeVersion($id);

        return [
            'ok' => true,
            'version' => $row ? self::versionSummary($row) : null,
            'saved_at' => date('c'),
        ];
    }

    /**
     * @param array{copy_content?: bool, copy_section_order?: bool, copy_design?: bool} $opts
     */
    public static function duplicateResume(int $id, string $title, array $opts = []): array
    {
        $newId = Versions::duplicateResume($id, $title, $opts + ['make_active' => true]);
        Versions::loadResumeVersion($newId);
        $row = Versions::resumeVersion($newId);

        return ['ok' => true, 'version' => $row ? self::versionSummary($row) : null, 'resume_id' => $newId];
    }

    /**
     * Preview tailor suggestions without applying (Main untouched).
     *
     * @return array<string, mixed>
     */
    public static function tailorPreview(string $company, string $role, string $jd): array
    {
        $base = Versions::baseResumeVersion();
        if ($base === null) {
            throw new RuntimeException('Main Resume not found.');
        }
        $snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
        $summary = '';
        $skills = '';
        foreach ($snapshot['sections'] ?? [] as $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key === 'summary') {
                $summary = self::bodyAsPlain((string) ($section['body'] ?? ''));
            }
            if ($key === 'skills') {
                $skills = self::bodyAsPlain((string) ($section['body'] ?? ''));
            }
        }

        $keywords = self::extractKeywords($jd);
        $suggestedSkills = $skills;
        if ($keywords !== []) {
            $suggestedSkills = App::reorderSkillsForJd($skills, $jd);
        }

        return [
            'ok' => true,
            'company' => $company,
            'role' => $role,
            'keywords' => $keywords,
            'suggestions' => [
                'summary' => [
                    'current' => $summary,
                    'note' => 'Review and edit the profile so it reflects the role without inventing experience.',
                ],
                'skills' => [
                    'current' => $skills,
                    'suggested' => $suggestedSkills,
                ],
                'experience' => [
                    'note' => 'Reframe existing bullets to match JD language. Do not invent employers or dates.',
                ],
            ],
        ];
    }

    /**
     * Apply tailor via App::tailorFromJd (creates Job CV; Main unchanged).
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function tailorApply(
        string $company,
        string $role,
        string $location,
        string $jd,
        string $link = '',
        array $overrides = []
    ): array {
        $result = App::tailorFromJd(
            $company,
            $role,
            $location,
            $jd,
            $link,
            (string) ($overrides['status'] ?? 'applied'),
            isset($overrides['profile_title']) ? (string) $overrides['profile_title'] : null,
            isset($overrides['summary']) ? (string) $overrides['summary'] : null,
            isset($overrides['skills']) ? (string) $overrides['skills'] : null,
            isset($overrides['cover_body']) ? (string) $overrides['cover_body'] : null,
            '',
            null,
            null,
            isset($overrides['experiences']) && is_array($overrides['experiences'])
                ? $overrides['experiences']
                : null
        );

        return ['ok' => true] + $result;
    }

    public static function renameVersion(int $id, string $title): array
    {
        $row = Versions::resumeVersion($id);
        if ($row === null) {
            throw new RuntimeException('Resume not found');
        }
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Title required');
        }
        if (Versions::isMasterResume($row)) {
            // Keep is_base; allow display title change carefully — prefer fixed Main label in UI.
            $title = Versions::MASTER_CV_LABEL;
        }
        Db::pdo()->prepare(
            'UPDATE resume_versions SET title = ? WHERE id = ? AND user_id = ?'
        )->execute([$title, $id, Auth::id()]);

        $updated = Versions::resumeVersion($id);

        return ['ok' => true, 'version' => $updated ? self::versionSummary($updated) : null];
    }

    /** @param array<string, mixed> $row */
    private static function versionSummary(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'label' => Versions::resumeUiLabel($row),
            'company' => (string) ($row['company'] ?? ''),
            'is_main' => Versions::isMasterResume($row),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed>|null $active */
    private static function versionQuery(?array $active): array
    {
        if ($active && (int) ($active['id'] ?? 0) > 0) {
            return ['version' => (int) $active['id']];
        }

        return [];
    }

    /** @return array<string, mixed> */
    private static function ownedSection(int $id): array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM resume_sections WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, Auth::id()]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('Section not found.');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private static function ownedExperience(int $id): array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM experience_entries WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, Auth::id()]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('Experience not found.');
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private static function sectionPayload(array $row): array
    {
        $body = (string) ($row['body'] ?? '');
        $decoded = SectionBody::decode($body);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'section_key' => (string) ($row['section_key'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'body' => $body,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'visible' => (int) ($row['visible'] ?? 1) === 1,
            'body_format' => $decoded !== null ? 'structured' : 'plain',
            'structured' => $decoded,
        ];
    }

    /** @param array<string, mixed> $row */
    private static function experiencePayload(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'company' => (string) ($row['company'] ?? ''),
            'position' => (string) ($row['position'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'start_date' => (string) ($row['start_date'] ?? ''),
            'end_date' => (string) ($row['end_date'] ?? ''),
            'bullets' => (string) ($row['bullets'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'visible' => (int) ($row['visible'] ?? 1) === 1,
        ];
    }

    /** @param array<string, mixed> $patch */
    private static function touchActiveSnapshotMeta(array $patch): void
    {
        $active = Versions::activeResumeVersion();
        if ($active === null) {
            return;
        }
        $snapshot = Versions::decodeSnapshot((string) ($active['snapshot'] ?? ''));
        $meta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
        foreach ($patch as $k => $v) {
            $meta[$k] = $v;
        }
        $snapshot['meta'] = $meta;
        // Also refresh content from live so meta save does not stale the snapshot.
        $live = Versions::captureSnapshot();
        $live['meta'] = $meta;
        Versions::saveResumeVersion(
            (string) ($active['title'] ?? 'Resume'),
            $live,
            (string) ($active['company'] ?? ''),
            (string) ($active['note'] ?? ''),
            Versions::isMasterResume($active),
            (int) $active['id'],
            true
        );
    }

    private static function bodyAsPlain(string $body): string
    {
        $data = SectionBody::decode($body);
        if ($data === null) {
            return $body;
        }
        if (($data['kind'] ?? '') === 'text') {
            return (string) ($data['text'] ?? '');
        }
        if (($data['kind'] ?? '') === 'skills') {
            $blocks = [];
            foreach ($data['categories'] ?? [] as $cat) {
                $name = trim((string) ($cat['name'] ?? ''));
                $skills = [];
                foreach ($cat['skills'] ?? [] as $skill) {
                    $n = trim((string) ($skill['name'] ?? ''));
                    if ($n !== '') {
                        $skills[] = $n;
                    }
                }
                if ($name !== '' || $skills !== []) {
                    $blocks[] = $name . "\n" . implode(' · ', $skills);
                }
            }

            return implode("\n\n", $blocks);
        }

        return $body;
    }

    /** @return list<string> */
    private static function extractKeywords(string $jd): array
    {
        $jd = strtolower($jd);
        $candidates = [
            'php', 'javascript', 'typescript', 'python', 'java', 'mysql', 'sql', 'git',
            'manual testing', 'regression', 'api testing', 'selenium', 'playwright',
            'jira', 'agile', 'scrum', 'qa', 'quality assurance', 'istqb', 'postman',
            'docker', 'kubernetes', 'aws', 'azure', 'ci/cd', 'jenkins',
        ];
        $found = [];
        foreach ($candidates as $kw) {
            if (str_contains($jd, $kw)) {
                $found[] = $kw;
            }
        }

        return array_slice($found, 0, 20);
    }
}
