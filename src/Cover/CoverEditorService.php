<?php

declare(strict_types=1);

namespace KaamFit\Cover;

use App;
use Auth;
use Db;
use InvalidArgumentException;
use RuntimeException;
use Versions;

/**
 * JSON API business logic for the cover letter studio.
 */
final class CoverEditorService
{
    /** @return array<string, mixed> */
    public static function getState(?int $letterId = null): array
    {
        if ($letterId !== null && $letterId > 0) {
            $letter = Versions::coverLetterById($letterId);
            if ($letter === null) {
                throw new InvalidArgumentException('Cover letter not found.');
            }
            Versions::activateCover($letterId);
        } else {
            $letter = App::activeCoverLetter();
        }

        if ($letter === null || empty($letter['id'])) {
            throw new RuntimeException('No cover letter selected.');
        }

        $body = (string) ($letter['body'] ?? '');
        $structured = CoverBody::decode($body);
        $bodyFormat = $structured !== null ? 'structured' : 'plain';

        $profile = App::profile();
        $meta = self::designMeta();

        return [
            'ok' => true,
            'letter' => self::letterSummary($letter),
            'body' => $body,
            'body_format' => $bodyFormat,
            'structured' => $structured,
            'profile' => $profile,
            'header_visibility' => App::coverHeaderVisibility(),
            'meta' => $meta,
            'templates' => self::templates(),
            'fonts' => App::fonts(),
            'pdf' => [
                'normal' => \PdfExport::downloadHrefOriginal('cover', ['id' => (int) $letter['id']]),
                'ats' => \PdfExport::downloadHrefAts('cover', ['id' => (int) $letter['id']]),
            ],
            'letters' => array_map(
                static fn(array $row): array => self::letterSummary($row),
                App::coverLetters()
            ),
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function patchLetter(int $id, array $fields): array
    {
        $row = self::ownedLetter($id);
        $updates = [];
        $params = [];

        if (array_key_exists('title', $fields)) {
            $title = trim((string) $fields['title']);
            if (Versions::isMasterCover($row)) {
                $title = $title !== '' ? $title : Versions::MASTER_COVER_LABEL;
            } elseif ($title === '') {
                $title = (string) ($row['title'] ?? 'Cover letter');
            }
            $updates[] = 'title = ?';
            $params[] = $title;
        }
        if (array_key_exists('company', $fields)) {
            $updates[] = 'company = ?';
            $params[] = trim((string) $fields['company']);
        }
        if (array_key_exists('body', $fields)) {
            $body = $fields['body'];
            if (is_array($body)) {
                $body = CoverBody::encode($body);
            } else {
                $body = (string) $body;
            }
            $updates[] = 'body = ?';
            $params[] = $body;
        }
        if (array_key_exists('structured', $fields) && is_array($fields['structured'])) {
            $updates[] = 'body = ?';
            $params[] = CoverBody::encode($fields['structured']);
        }

        if ($updates === []) {
            return ['ok' => true, 'letter' => self::letterPayload(self::ownedLetter($id))];
        }

        $params[] = $id;
        $params[] = Auth::id();
        Db::pdo()->prepare(
            'UPDATE cover_letters SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?'
        )->execute($params);

        return ['ok' => true, 'letter' => self::letterPayload(self::ownedLetter($id))];
    }

    /**
     * Upgrade plain body to structured on explicit user action (does not invent text).
     *
     * @return array<string, mixed>
     */
    public static function upgradeToStructured(int $id): array
    {
        $row = self::ownedLetter($id);
        $body = (string) ($row['body'] ?? '');
        if (CoverBody::decode($body) !== null) {
            return ['ok' => true, 'letter' => self::letterPayload($row), 'already' => true];
        }
        $structured = CoverBody::fromPlain($body);
        Db::pdo()->prepare(
            'UPDATE cover_letters SET body = ? WHERE id = ? AND user_id = ?'
        )->execute([CoverBody::encode($structured), $id, Auth::id()]);

        return ['ok' => true, 'letter' => self::letterPayload(self::ownedLetter($id))];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function patchDesign(array $fields): array
    {
        if (isset($fields['template']) || isset($fields['theme'])) {
            $theme = \KaamFit\Resume\ResumeLayout::resolveTemplate(
                (string) ($fields['template'] ?? $fields['theme'] ?? '')
            );
            App::setSetting('theme', $theme);
            App::setSetting('resume_template', $theme);
            if ($theme === 'ats') {
                // Cover ATS: keep content simple via export flags; theme still ats.
            }
        }
        if (isset($fields['font_family'])) {
            App::setSetting('font_family', App::resolveFont((string) $fields['font_family']));
        }
        if (isset($fields['accent_color'])) {
            App::setSetting('accent_color', App::resolveAccent((string) $fields['accent_color']));
        }
        if (isset($fields['font_size'])) {
            $raw = (string) $fields['font_size'];
            $size = in_array($raw, ['sm', 'md', 'lg'], true)
                ? $raw
                : (((int) $raw) <= 90 ? 'sm' : (((int) $raw) >= 110 ? 'lg' : 'md'));
            App::setSetting('font_size', $size);
        }
        if (isset($fields['section_spacing'])) {
            $spacing = (string) $fields['section_spacing'];
            if (in_array($spacing, ['sm', 'md', 'lg'], true)) {
                App::setSetting('section_spacing', $spacing);
            }
        }
        if (isset($fields['density'])) {
            App::setSetting('resume_density', \KaamFit\Resume\ResumeLayout::resolveDensity((string) $fields['density']));
        }
        if (isset($fields['page_format'])) {
            App::setSetting('page_format', \KaamFit\Resume\ResumeLayout::resolvePageFormat((string) $fields['page_format']));
        }
        if (isset($fields['margin'])) {
            App::setSetting('page_margin', \KaamFit\Resume\ResumeLayout::resolveMargin((string) $fields['margin']));
        }

        $headerKeys = [
            'cover_show_title' => 'title',
            'cover_show_location' => 'location',
            'cover_show_phone' => 'phone',
            'cover_show_email' => 'email',
            'cover_show_links' => 'links',
            'cover_show_personal_extras' => 'personal_extras',
        ];
        $touchedHeader = false;
        foreach ($headerKeys as $settingKey => $_) {
            if (array_key_exists($settingKey, $fields)) {
                $touchedHeader = true;
                break;
            }
        }
        if ($touchedHeader) {
            $vis = App::coverHeaderVisibility();
            $post = [];
            foreach ($headerKeys as $settingKey => $visKey) {
                $on = array_key_exists($settingKey, $fields)
                    ? !empty($fields[$settingKey])
                    : !empty($vis[$visKey]);
                if ($on) {
                    $post[$settingKey] = '1';
                }
            }
            App::saveCoverHeaderVisibility($post);
        }

        return [
            'ok' => true,
            'meta' => self::designMeta(),
            'header_visibility' => App::coverHeaderVisibility(),
        ];
    }

    /**
     * @param array{copy_content?: bool, copy_design?: bool, make_active?: bool} $opts
     * @return array<string, mixed>
     */
    public static function duplicate(int $id, string $title = '', array $opts = []): array
    {
        $src = self::ownedLetter($id);
        $makeActive = ($opts['make_active'] ?? true) !== false;
        $copyContent = ($opts['copy_content'] ?? true) !== false;

        $newTitle = trim($title);
        if ($newTitle === '') {
            $newTitle = Versions::coverUiLabel($src) . ' (copy)';
        }

        $newId = Versions::duplicateCover($id, $newTitle);
        if (!$copyContent) {
            Db::pdo()->prepare(
                'UPDATE cover_letters SET body = ? WHERE id = ? AND user_id = ?'
            )->execute([CoverBody::encode(CoverBody::emptyStructure()), $newId, Auth::id()]);
        }
        if (!$makeActive) {
            // duplicateCover activates the new one — optionally restore previous active
            $prev = (int) ($src['id'] ?? 0);
            if ($prev > 0 && (int) ($src['is_active'] ?? 0) === 1) {
                Versions::activateCover($prev);
            }
        }

        return [
            'ok' => true,
            'cover_id' => $newId,
            'letter' => self::letterSummary(Versions::coverLetterById($newId) ?? []),
        ];
    }

    public static function rename(int $id, string $title): array
    {
        $row = self::ownedLetter($id);
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Title required');
        }
        if (Versions::isMasterCover($row)) {
            throw new InvalidArgumentException('Main Cover Letter title is fixed.');
        }
        Db::pdo()->prepare(
            'UPDATE cover_letters SET title = ? WHERE id = ? AND user_id = ?'
        )->execute([$title, $id, Auth::id()]);

        return ['ok' => true, 'letter' => self::letterSummary(self::ownedLetter($id))];
    }

    public static function delete(int $id): array
    {
        Versions::deleteCover($id);

        return ['ok' => true, 'deleted' => true, 'cover_id' => $id];
    }

    public static function activate(int $id): array
    {
        self::ownedLetter($id);
        Versions::activateCover($id);

        return ['ok' => true, 'letter' => self::letterSummary(self::ownedLetter($id))];
    }

    /**
     * Preview tailor suggestions from Main cover (no writes).
     *
     * @return array<string, mixed>
     */
    public static function tailorPreview(string $company, string $role, string $jd): array
    {
        $base = Versions::baseCoverLetter();
        if ($base === null) {
            throw new RuntimeException('Main Cover Letter not found.');
        }
        $plain = CoverBody::toPlain((string) ($base['body'] ?? ''));
        $keywords = self::extractKeywords($jd);

        return [
            'ok' => true,
            'company' => $company,
            'role' => $role,
            'keywords' => $keywords,
            'current_body' => $plain,
            'suggestions' => [
                'note' => 'Review the letter against the job. Do not invent employers, degrees, or experience.',
                'body' => $plain,
            ],
        ];
    }

    /**
     * Apply via App::tailorFromJd — creates Job CV + cover; Main cover unchanged.
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
        $coverBody = isset($overrides['cover_body']) ? (string) $overrides['cover_body'] : null;
        if ($coverBody !== null && $coverBody !== '' && CoverBody::decode($coverBody) === null) {
            // Keep plain; tailor stores as text in cover_letters.body
        }

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
            $coverBody,
            '',
            null,
            null,
            isset($overrides['experiences']) && is_array($overrides['experiences'])
                ? $overrides['experiences']
                : null
        );

        return ['ok' => true] + $result;
    }

    /** @return array<string, array{label: string, blurb: string}> */
    public static function templates(): array
    {
        // Reuse resume template ids so PDF/theme CSS stay aligned.
        return \KaamFit\Resume\ResumeLayout::TEMPLATES;
    }

    /** @return array<string, mixed> */
    private static function designMeta(): array
    {
        return [
            'template' => \KaamFit\Resume\ResumeLayout::resolveTemplate(null),
            'font_family' => App::resolveFont(null),
            'accent_color' => App::resolveAccent(null),
            'font_size' => App::resolveFontSize(null),
            'section_spacing' => App::setting('section_spacing', 'md') ?: 'md',
            'density' => \KaamFit\Resume\ResumeLayout::resolveDensity(null),
            'page_format' => \KaamFit\Resume\ResumeLayout::resolvePageFormat(null),
            'margin' => \KaamFit\Resume\ResumeLayout::resolveMargin(null),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function letterSummary(array $row): array
    {
        if ($row === [] || empty($row['id'])) {
            return [];
        }

        return [
            'id' => (int) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'label' => Versions::coverUiLabel($row),
            'company' => (string) ($row['company'] ?? ''),
            'is_main' => Versions::isMasterCover($row),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'body_format' => CoverBody::detect((string) ($row['body'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function letterPayload(array $row): array
    {
        $body = (string) ($row['body'] ?? '');
        $structured = CoverBody::decode($body);

        return self::letterSummary($row) + [
            'body' => $body,
            'body_format' => $structured !== null ? 'structured' : 'plain',
            'structured' => $structured,
        ];
    }

    /** @return array<string, mixed> */
    private static function ownedLetter(int $id): array
    {
        $row = Versions::coverLetterById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Cover letter not found.');
        }

        return $row;
    }

    /** @return list<string> */
    private static function extractKeywords(string $jd): array
    {
        $jd = strtolower($jd);
        $candidates = [
            'php', 'javascript', 'python', 'java', 'mysql', 'git',
            'manual testing', 'regression', 'api testing', 'qa', 'agile', 'scrum',
            'jira', 'istqb', 'communication', 'teamwork', 'german', 'english',
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
