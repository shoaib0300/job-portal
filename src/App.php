<?php

declare(strict_types=1);

final class App
{
    public static function isDev(): bool
    {
        $env = strtolower((string) (getenv('APP_ENV') ?: 'prod'));
        return in_array($env, ['dev', 'local', 'development'], true);
    }

    public static function userId(): int
    {
        return Auth::id();
    }

    public static function setting(string $key, ?string $default = null): ?string
    {
        $pdo = Db::pdo();
        $uid = self::userId();
        if ($uid > 0) {
            $stmt = $pdo->prepare(
                'SELECT `value` FROM user_settings WHERE user_id = ? AND `key` = ? LIMIT 1'
            );
            $stmt->execute([$uid, $key]);
            $row = $stmt->fetch();
            if ($row !== false) {
                return (string) $row['value'];
            }
        }
        $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $default;
        }
        return (string) $row['value'];
    }

    public static function setSetting(string $key, string $value): void
    {
        $uid = self::userId();
        if ($uid > 0) {
            $stmt = Db::pdo()->prepare(
                'INSERT INTO user_settings (user_id, `key`, `value`) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
            );
            $stmt->execute([$uid, $key, $value]);
            return;
        }
        $stmt = Db::pdo()->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([$key, $value]);
    }

    public static function profile(): array
    {
        $empty = [
            'id' => 0,
            'user_id' => 0,
            'full_name' => 'Your Name',
            'title' => '',
            'email' => '',
            'phone' => '',
            'location' => '',
            'gender' => '',
            'date_of_birth' => null,
            'country' => '',
            'nationality' => '',
            'photo_path' => '',
            'show_photo' => 1,
            'links' => [],
        ];
        $uid = self::userId();
        if ($uid <= 0) {
            return $empty;
        }
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM resume_profile WHERE user_id = ? ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $empty;
        }
        $links = $row['links'];
        if (is_string($links)) {
            $decoded = json_decode($links, true);
            $row['links'] = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($links)) {
            $row['links'] = [];
        }
        $row['show_photo'] = (int) ($row['show_photo'] ?? 1);
        $row['photo_path'] = (string) ($row['photo_path'] ?? '');
        return $row;
    }

    public static function photoUrl(array $profile): string
    {
        $path = (string) ($profile['photo_path'] ?? '');
        if ($path === '' || !preg_match('#^uploads/photos/[A-Za-z0-9._-]+$#', $path)) {
            return '';
        }
        $full = dirname(__DIR__) . '/public/' . $path;
        if (!is_file($full)) {
            return '';
        }
        return '/' . $path;
    }

    public static function shouldShowPhoto(array $profile): bool
    {
        return (int) ($profile['show_photo'] ?? 0) === 1 && self::photoUrl($profile) !== '';
    }

    public static function sections(bool $visibleOnly = false): array
    {
        $uid = self::userId();
        $sql = 'SELECT * FROM resume_sections WHERE user_id = ?';
        if ($visibleOnly) {
            $sql .= ' AND visible = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([$uid]);
        return $stmt->fetchAll();
    }

    public static function experiences(bool $visibleOnly = false): array
    {
        $uid = self::userId();
        $sql = 'SELECT * FROM experience_entries WHERE user_id = ?';
        if ($visibleOnly) {
            $sql .= ' AND visible = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([$uid]);
        return $stmt->fetchAll();
    }

    public static function experienceDateRange(array $entry): string
    {
        $start = trim((string) ($entry['start_date'] ?? ''));
        $end = trim((string) ($entry['end_date'] ?? ''));
        if ($start !== '' && $end !== '') {
            return $start . ' – ' . $end;
        }
        return $start !== '' ? $start : $end;
    }

    public static function activeCoverLetter(): ?array
    {
        Versions::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM cover_letters WHERE user_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1'
        );
        $stmt->execute([self::userId()]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function coverLetters(): array
    {
        Versions::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM cover_letters WHERE user_id = ? ORDER BY is_base DESC, is_active DESC, updated_at DESC'
        );
        $stmt->execute([self::userId()]);
        return $stmt->fetchAll();
    }

    public static function applications(?string $status = null, string $q = ''): array
    {
        $sql = 'SELECT * FROM applications WHERE user_id = ?';
        $params = [self::userId()];
        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $q = trim($q);
        if ($q !== '') {
            $sql .= ' AND (company LIKE ? OR role LIKE ? OR location LIKE ? OR notes LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY applied_date DESC, id DESC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function applicationCounts(): array
    {
        $counts = [
            'all' => 0,
            'applied' => 0,
            'rejected' => 0,
            'interview' => 0,
            'offer' => 0,
            'custom' => 0,
        ];
        $stmt = Db::pdo()->prepare(
            'SELECT status, COUNT(*) AS n FROM applications WHERE user_id = ? GROUP BY status'
        );
        $stmt->execute([self::userId()]);
        foreach ($stmt->fetchAll() as $row) {
            $key = (string) ($row['status'] ?? '');
            $n = (int) ($row['n'] ?? 0);
            if (isset($counts[$key])) {
                $counts[$key] = $n;
            }
            $counts['all'] += $n;
        }
        return $counts;
    }

    public static function ensureDashboardSchema(): void
    {
        $pdo = Db::pdo();
        $alters = [
            'location' => "ALTER TABLE applications ADD COLUMN location VARCHAR(160) NOT NULL DEFAULT '' AFTER role",
            'resume_version_id' => 'ALTER TABLE applications ADD COLUMN resume_version_id INT UNSIGNED NULL AFTER link',
            'cover_letter_id' => 'ALTER TABLE applications ADD COLUMN cover_letter_id INT UNSIGNED NULL AFTER resume_version_id',
        ];
        foreach ($alters as $col => $sql) {
            $exists = $pdo->query('SHOW COLUMNS FROM applications LIKE ' . $pdo->quote($col))->fetch();
            if ($exists === false) {
                $pdo->exec($sql);
            }
        }

        $defaults = [
            'ui_density' => 'comfortable',
            'sidebar_mode' => 'expanded',
            'ui_mode' => 'warm',
            'dashboard_palette' => 'light',
            'name_size' => 'md',
            'font_size' => 'md',
            'section_spacing' => 'md',
        ];
        foreach ($defaults as $key => $value) {
            if (self::setting($key) === null) {
                self::setSetting($key, $value);
            }
        }
    }

    /**
     * Copy Main resume + Main cover for a JD and log Applications.
     * Never overwrites Main. Prefer this over writing data/tailor_*.php files.
     *
     * @return array{resume_id:int,cover_id:int,application_id:int,location:string,status:string}
     */
    public static function tailorFromJd(
        string $company,
        string $role,
        string $location,
        string $jdSnippet = '',
        string $link = '',
        string $status = 'applied',
        ?string $profileTitle = null,
        ?string $summary = null,
        ?string $skills = null,
        ?string $coverBody = null,
        string $notes = ''
    ): array {
        self::ensureDashboardSchema();
        Versions::ensureSchema();

        $company = trim($company);
        $role = trim($role);
        $location = trim($location);
        if ($company === '' || $role === '') {
            throw new InvalidArgumentException('Company and role are required');
        }
        if ($location === '') {
            throw new InvalidArgumentException('Location is required');
        }

        $base = Versions::baseResumeVersion();
        if ($base === null) {
            throw new RuntimeException('No Master CV. Save a Master CV in the Editor first.');
        }

        $snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
        $snapshot['location'] = $location;
        if ($profileTitle !== null && trim($profileTitle) !== '') {
            $snapshot['profile_title'] = trim($profileTitle);
        }

        foreach ($snapshot['sections'] as &$section) {
            if (!is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key === 'summary' && $summary !== null) {
                $section['body'] = $summary;
            }
            if ($key === 'skills' && $skills !== null) {
                $section['body'] = $skills;
            }
        }
        unset($section);

        $resumeTitle = $role . ' — ' . $company;
        $resumeId = Versions::saveResumeVersion(
            $resumeTitle,
            $snapshot,
            $company,
            'Copy of Main for ' . $company . ' · ' . $location . '.',
            false,
            null,
            true
        );
        Versions::loadResumeVersion($resumeId);

        $baseCover = Versions::baseCoverLetter();
        if ($baseCover === null) {
            throw new RuntimeException('No Master cover letter. Save a Master cover letter first.');
        }

        $coverId = Versions::duplicateCover((int) $baseCover['id'], $resumeTitle);
        $coverCompany = $company . ' · ' . $location;
        $body = $coverBody !== null && trim($coverBody) !== ''
            ? $coverBody
            : (string) ($baseCover['body'] ?? '');
        Db::pdo()->prepare(
            'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ? AND user_id = ?'
        )->execute([$body, $coverCompany, $coverId, self::userId()]);
        Versions::activateCover($coverId);

        $note = trim($notes);
        if ($note === '') {
            $note = "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}.";
        }

        $appId = self::logJdApplication(
            $company,
            $role,
            $jdSnippet,
            $status,
            $note,
            $link,
            null,
            $location,
            $resumeId,
            $coverId
        );

        return [
            'resume_id' => $resumeId,
            'cover_id' => $coverId,
            'application_id' => $appId,
            'location' => $location,
            'status' => $status,
        ];
    }

    /**
     * Log / update an application when a JD is tailored.
     * Default status is "applied". Pass another allowed status if needed.
     *
     * @return int application id
     */
    public static function logJdApplication(
        string $company,
        string $role,
        string $jdSnippet = '',
        string $status = 'applied',
        string $notes = '',
        string $link = '',
        ?string $appliedDate = null,
        string $location = '',
        ?int $resumeVersionId = null,
        ?int $coverLetterId = null
    ): int {
        self::ensureDashboardSchema();
        $allowed = ['applied', 'rejected', 'interview', 'offer', 'custom'];
        if (!in_array($status, $allowed, true)) {
            $status = 'applied';
        }
        $company = trim($company);
        $role = trim($role);
        if ($company === '' || $role === '') {
            throw new InvalidArgumentException('Company and role are required');
        }
        $date = $appliedDate !== null && trim($appliedDate) !== ''
            ? trim($appliedDate)
            : date('Y-m-d');

        $pdo = Db::pdo();
        $uid = self::userId();
        $existing = $pdo->prepare(
            'SELECT id, resume_version_id, cover_letter_id, link FROM applications WHERE user_id = ? AND company = ? AND role = ? ORDER BY id DESC LIMIT 1'
        );
        $existing->execute([$uid, $company, $role]);
        $row = $existing->fetch();

        $location = trim($location);
        $resumeVersionId = $resumeVersionId !== null && $resumeVersionId > 0 ? $resumeVersionId : null;
        $coverLetterId = $coverLetterId !== null && $coverLetterId > 0 ? $coverLetterId : null;

        if ($row) {
            $id = (int) $row['id'];
            if ($resumeVersionId === null && (int) ($row['resume_version_id'] ?? 0) > 0) {
                $resumeVersionId = (int) $row['resume_version_id'];
            }
            if ($coverLetterId === null && (int) ($row['cover_letter_id'] ?? 0) > 0) {
                $coverLetterId = (int) $row['cover_letter_id'];
            }
            if ($link === '' && trim((string) ($row['link'] ?? '')) !== '') {
                $link = (string) $row['link'];
            }
            $pdo->prepare(
                'UPDATE applications
                 SET status = ?, applied_date = ?, notes = ?, jd_snippet = ?, link = ?,
                     location = ?, resume_version_id = ?, cover_letter_id = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND user_id = ?'
            )->execute([
                $status,
                $date,
                $notes !== '' ? $notes : null,
                $jdSnippet !== '' ? $jdSnippet : null,
                $link !== '' ? $link : null,
                $location,
                $resumeVersionId,
                $coverLetterId,
                $id,
                $uid,
            ]);
            return $id;
        }

        $pdo->prepare(
            'INSERT INTO applications
                (user_id, company, role, location, status, applied_date, notes, jd_snippet, link, resume_version_id, cover_letter_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uid,
            $company,
            $role,
            $location,
            $status,
            $date,
            $notes !== '' ? $notes : null,
            $jdSnippet !== '' ? $jdSnippet : null,
            $link !== '' ? $link : null,
            $resumeVersionId,
            $coverLetterId,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function searchHistory(int $limit = 50): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM search_history WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ?'
        );
        $stmt->bindValue(1, self::userId(), PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Empty is allowed. Bare domains get https:// so they still open. */
    public static function normalizeHttpUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }

    public static function nl2p(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
        $html = '';
        foreach ($paragraphs as $p) {
            $lines = preg_split("/\n/", $p) ?: [];
            $escaped = array_map(static fn(string $line): string => self::e($line), $lines);
            $html .= '<p>' . implode('<br>', $escaped) . '</p>';
        }
        return $html;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'applied' => 'Applied',
            'rejected' => 'Rejected',
            'interview' => 'Interview',
            'offer' => 'Offer',
            'custom' => 'Custom',
            default => ucfirst($status),
        };
    }

    public static function themes(): array
    {
        return [
            'midnight' => [
                'label' => 'Midnight',
                'blurb' => 'Word-style dark letter — large caps name, Aptos/Arial, pipe contacts.',
            ],
            'sage' => [
                'label' => 'Sage',
                'blurb' => 'Word-style sage letter — centered name, Candara, contact between thin rules.',
            ],
            'classic' => [
                'label' => 'Classic',
                'blurb' => 'Serif name, accent underline — clean and traditional.',
            ],
            'modern' => [
                'label' => 'Modern',
                'blurb' => 'Bold left accent bar and open spacing.',
            ],
            'compact' => [
                'label' => 'Compact',
                'blurb' => 'Tighter type for one-page applications.',
            ],
            'sidebar' => [
                'label' => 'Sidebar',
                'blurb' => 'Colored side column for contact and name.',
            ],
            'executive' => [
                'label' => 'Executive',
                'blurb' => 'Clean left-aligned layout with refined rules — formal and sharp.',
            ],
            'company' => [
                'label' => 'Company tint',
                'blurb' => 'Soft brand wash using your accent color.',
            ],
            'banner' => [
                'label' => 'Banner',
                'blurb' => 'Full-width accent header band with white name.',
            ],
            'split' => [
                'label' => 'Split',
                'blurb' => 'Two-tone header with name left and contacts right.',
            ],
            'minimal' => [
                'label' => 'Minimal',
                'blurb' => 'Quiet typography, almost no decoration.',
            ],
            'slate' => [
                'label' => 'Slate',
                'blurb' => 'Dark slate header strip for strong contrast.',
            ],
            'serif' => [
                'label' => 'Editorial',
                'blurb' => 'Large serif headlines with editorial section titles.',
            ],
            'cards' => [
                'label' => 'Cards',
                'blurb' => 'Each section sits in a soft bordered card.',
            ],
            'timeline' => [
                'label' => 'Timeline',
                'blurb' => 'Centered header, vertical rail with icons, dates on the left — burgundy accents.',
                'accent' => '#8B1A1A',
            ],
        ];
    }

    public static function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    public static function formatDate(?string $date): string
    {
        if ($date === null || trim($date) === '' || $date === '0000-00-00') {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        return date('d.m.Y', $ts);
    }

    public static function themeKeys(): array
    {
        return array_keys(self::themes());
    }

    public static function themeLabel(string $key): string
    {
        return self::themes()[$key]['label'] ?? ucfirst($key);
    }

    public static function fonts(): array
    {
        return [
            'arial' => [
                'label' => 'Arial',
                'stack' => 'Arial, Helvetica, sans-serif',
                'google' => null,
            ],
            'aptos' => [
                'label' => 'Aptos',
                'stack' => 'Aptos, "Segoe UI", Arial, Helvetica, sans-serif',
                'google' => null,
            ],
            'candara' => [
                'label' => 'Candara',
                'stack' => 'Candara, Calibri, "Segoe UI", sans-serif',
                'google' => null,
            ],
            'helvetica' => [
                'label' => 'Helvetica',
                'stack' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
                'google' => null,
            ],
            'georgia' => [
                'label' => 'Georgia',
                'stack' => 'Georgia, "Times New Roman", Times, serif',
                'google' => null,
            ],
            'times' => [
                'label' => 'Times New Roman',
                'stack' => '"Times New Roman", Times, serif',
                'google' => null,
            ],
            'garamond' => [
                'label' => 'Garamond',
                'stack' => '"EB Garamond", Garamond, "Times New Roman", serif',
                'google' => 'EB+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400',
            ],
            'palatino' => [
                'label' => 'Palatino',
                'stack' => 'Palatino, "Palatino Linotype", "Book Antiqua", serif',
                'google' => null,
            ],
            'playfair' => [
                'label' => 'Playfair Display',
                'stack' => '"Playfair Display", Georgia, serif',
                'google' => 'Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400',
            ],
            'lora' => [
                'label' => 'Lora',
                'stack' => 'Lora, Georgia, serif',
                'google' => 'Lora:ital,wght@0,400;0,500;0,600;0,700;1,400',
            ],
            'cormorant' => [
                'label' => 'Cormorant',
                'stack' => '"Cormorant Garamond", Garamond, serif',
                'google' => 'Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400',
            ],
            'baskerville' => [
                'label' => 'Baskerville',
                'stack' => '"Libre Baskerville", Baskerville, Georgia, serif',
                'google' => 'Libre+Baskerville:ital,wght@0,400;0,700;1,400',
            ],
            'source_serif' => [
                'label' => 'Source Serif',
                'stack' => '"Source Serif 4", Georgia, serif',
                'google' => 'Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400',
            ],
            'montserrat' => [
                'label' => 'Montserrat',
                'stack' => 'Montserrat, Arial, sans-serif',
                'google' => 'Montserrat:ital,wght@0,400;0,500;0,600;0,700;1,400',
            ],
            'calibri' => [
                'label' => 'Calibri',
                'stack' => 'Calibri, Carlito, Candara, sans-serif',
                'google' => 'Carlito:ital,wght@0,400;0,700;1,400',
            ],
            'cosmo' => [
                'label' => 'Cosmo',
                'stack' => 'Outfit, "Segoe UI", Arial, sans-serif',
                'google' => 'Outfit:wght@400;500;600;700',
            ],
            'didot' => [
                'label' => 'Didot',
                'stack' => '"Bodoni Moda", Didot, "Times New Roman", serif',
                'google' => 'Bodoni+Moda:ital,opsz,wght@0,6..96,400;0,6..96,600;0,6..96,700;1,6..96,400',
            ],
            'verdana' => [
                'label' => 'Verdana',
                'stack' => 'Verdana, Geneva, sans-serif',
                'google' => null,
            ],
        ];
    }

    public static function fontKeys(): array
    {
        return array_keys(self::fonts());
    }

    public static function fontLabel(string $key): string
    {
        return self::fonts()[$key]['label'] ?? ucfirst($key);
    }

    public static function resolveFont(?string $font): string
    {
        $font = $font ?: (self::setting('font_family', 'candara') ?: 'candara');
        return in_array($font, self::fontKeys(), true) ? $font : 'candara';
    }

    public static function fontStack(string $key): string
    {
        return self::fonts()[$key]['stack'] ?? 'Georgia, serif';
    }

    public static function googleFontsHref(?string $selected = null): string
    {
        $families = [
            'Inter:wght@400;500;600;700',
            'DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400',
            'Instrument+Serif:ital@0;1',
        ];
        foreach (self::fonts() as $key => $meta) {
            if (!empty($meta['google'])) {
                // Always load selectable Google fonts so the picker and preview work immediately.
                $families[] = $meta['google'];
            }
        }
        $families = array_values(array_unique($families));
        return 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families) . '&display=swap';
    }

    public static function resolveTheme(?string $theme): string
    {
        $theme = $theme ?: (self::setting('theme', 'sage') ?: 'sage');
        return in_array($theme, self::themeKeys(), true) ? $theme : 'sage';
    }

    public static function resolveAccent(?string $accent): string
    {
        $accent = $accent ?: (self::setting('accent_color', '#5B4CDB') ?: '#5B4CDB');
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $accent) ? $accent : '#5B4CDB';
    }

    /** Dashboard / portal chrome — not the resume PDF accent. */
    public static function uiAccent(): string
    {
        return '#0d7377';
    }

    public static function resolveUiMode(?string $mode = null): string
    {
        $palette = self::resolveDashboardPalette();
        return $palette === 'dark' ? 'warm-dark' : 'warm';
    }

    /** @return array<string, array{label: string, is_dark: bool, tokens: array<string, string>}> */
    public static function dashboardPalettes(): array
    {
        return kaammilo_dashboard_palettes();
    }

    public static function resolveDashboardPalette(?string $palette = null): string
    {
        if ($palette !== null && $palette !== '') {
            $palette = strtolower(trim($palette));
            if (isset(self::dashboardPalettes()[$palette])) {
                return $palette;
            }
        }
        $stored = self::setting('dashboard_palette');
        if (is_string($stored) && $stored !== '' && isset(self::dashboardPalettes()[$stored])) {
            return $stored;
        }
        $legacy = self::setting('ui_mode', 'warm') ?: 'warm';
        return $legacy === 'warm-dark' ? 'dark' : 'light';
    }

    public static function dashboardPaletteIsDark(?string $palette = null): bool
    {
        return kaammilo_palette_is_dark(self::resolveDashboardPalette($palette));
    }

    public static function resolveDensity(?string $density = null): string
    {
        $density = $density ?: (self::setting('ui_density', 'comfortable') ?: 'comfortable');
        return in_array($density, ['comfortable', 'compact'], true) ? $density : 'comfortable';
    }

    public static function resolveSidebar(?string $mode = null): string
    {
        $mode = $mode ?: (self::setting('sidebar_mode', 'expanded') ?: 'expanded');
        return in_array($mode, ['expanded', 'compact'], true) ? $mode : 'expanded';
    }

    public static function resolveNameSize(?string $size = null): string
    {
        $size = $size ?: (self::setting('name_size', 'md') ?: 'md');
        return in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    }

    public static function resolveSectionSpacing(?string $spacing = null): string
    {
        $spacing = $spacing ?: (self::setting('section_spacing', 'md') ?: 'md');
        return in_array($spacing, ['tight', 'md', 'loose'], true) ? $spacing : 'md';
    }

    public static function resolveFontSize(?string $size = null): string
    {
        $size = $size ?: (self::setting('font_size', 'md') ?: 'md');
        return in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    }

    public static function nameSizeScale(string $size): string
    {
        return match ($size) {
            'sm' => '0.86',
            'lg' => '1.18',
            default => '1',
        };
    }

    public static function fontSizeScale(string $size): string
    {
        return match ($size) {
            'sm' => '0.9',
            'lg' => '1.12',
            default => '1',
        };
    }

    public static function sectionSpacingValue(string $spacing): string
    {
        return match ($spacing) {
            'tight' => '0.85rem',
            'loose' => '2.15rem',
            default => '1.5rem',
        };
    }

    public static function currentNavKey(): string
    {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_contains($scriptPath, '/dashboard/')) {
            return 'dashboard';
        }
        if ($script === 'index.php' && Site::isPortalHost()) {
            return 'dashboard';
        }
        return match ($script) {
            'index.php', '' => '',
            'tailor.php' => 'apply',
            'jobs.php', 'job.php' => 'jobs',
            'companies.php' => 'companies',
            'applications.php', 'history.php' => 'applications',
            'documents.php', 'editor.php', 'resume-edit.php', 'design.php' => 'resume',
            'cover.php', 'cover-edit.php', 'cover-design.php' => 'cover',
            'settings.php' => 'account',
            default => '',
        };
    }

    public static function colorPresets(): array
    {
        return [
            '#5B4CDB' => 'Indigo',
            '#4E6351' => 'Sage',
            '#313E32' => 'Forest ink',
            '#8B1A1A' => 'Burgundy',
            '#1a1a1a' => 'Midnight',
            '#B85A22' => 'Terracotta',
            '#DD8047' => 'Copper',
            '#1e3a5f' => 'Navy',
            '#6b2d3c' => 'Wine',
            '#0f4c5c' => 'Teal',
        ];
    }

    public static function flash(?string $message = null, string $type = 'ok'): ?array
    {
        if ($message !== null) {
            $_SESSION['flash'] = ['message' => $message, 'type' => $type];
            return null;
        }
        if (!isset($_SESSION['flash'])) {
            return null;
        }
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . self::url($path));
        exit;
    }

    /**
     * Pretty path for links/redirects: /cover.php → /cover (keeps ?query #hash).
     */
    public static function url(string $path): string
    {
        if ($path === '' || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:')) {
            return $path;
        }
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }
        $hash = '';
        $query = '';
        if (str_contains($path, '#')) {
            [$path, $frag] = explode('#', $path, 2);
            $hash = '#' . $frag;
        }
        if (str_contains($path, '?')) {
            [$path, $q] = explode('?', $path, 2);
            $query = '?' . $q;
        }
        if (str_ends_with($path, '.php')) {
            $path = substr($path, 0, -4);
        }
        if ($path === '/index' || $path === 'index') {
            $path = '/';
        }
        return $path . $query . $hash;
    }
}
