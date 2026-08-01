<?php

declare(strict_types=1);

final class App
{
    public static function setting(string $key, ?string $default = null): ?string
    {
        $stmt = Db::pdo()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $default;
        }
        return (string) $row['value'];
    }

    public static function setSetting(string $key, string $value): void
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([$key, $value]);
    }

    public static function profile(): array
    {
        $row = Db::pdo()->query('SELECT * FROM resume_profile ORDER BY id ASC LIMIT 1')->fetch();
        if ($row === false) {
            return [
                'id' => 0,
                'full_name' => 'Your Name',
                'title' => '',
                'email' => '',
                'phone' => '',
                'location' => '',
                'links' => [],
            ];
        }
        $links = $row['links'];
        if (is_string($links)) {
            $decoded = json_decode($links, true);
            $row['links'] = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($links)) {
            $row['links'] = [];
        }
        return $row;
    }

    public static function sections(bool $visibleOnly = false): array
    {
        $sql = 'SELECT * FROM resume_sections';
        if ($visibleOnly) {
            $sql .= ' WHERE visible = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return Db::pdo()->query($sql)->fetchAll();
    }

    public static function activeCoverLetter(): ?array
    {
        $row = Db::pdo()->query(
            'SELECT * FROM cover_letters WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1'
        )->fetch();
        return $row === false ? null : $row;
    }

    public static function coverLetters(): array
    {
        return Db::pdo()->query(
            'SELECT * FROM cover_letters ORDER BY is_active DESC, updated_at DESC'
        )->fetchAll();
    }

    public static function applications(?string $status = null): array
    {
        if ($status !== null && $status !== '' && $status !== 'all') {
            $stmt = Db::pdo()->prepare(
                'SELECT * FROM applications WHERE status = ? ORDER BY applied_date DESC, id DESC'
            );
            $stmt->execute([$status]);
            return $stmt->fetchAll();
        }
        return Db::pdo()->query(
            'SELECT * FROM applications ORDER BY applied_date DESC, id DESC'
        )->fetchAll();
    }

    public static function searchHistory(int $limit = 50): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM search_history ORDER BY created_at DESC, id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
                'blurb' => 'Centered header with strong horizontal rules.',
            ],
            'company' => [
                'label' => 'Company tint',
                'blurb' => 'Soft brand wash using your accent color.',
            ],
        ];
    }

    public static function themeKeys(): array
    {
        return array_keys(self::themes());
    }

    public static function themeLabel(string $key): string
    {
        return self::themes()[$key]['label'] ?? ucfirst($key);
    }

    public static function resolveTheme(?string $theme): string
    {
        $theme = $theme ?: (self::setting('theme', 'classic') ?: 'classic');
        return in_array($theme, self::themeKeys(), true) ? $theme : 'classic';
    }

    public static function resolveAccent(?string $accent): string
    {
        $accent = $accent ?: (self::setting('accent_color', '#1a5f4a') ?: '#1a5f4a');
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $accent) ? $accent : '#1a5f4a';
    }

    public static function colorPresets(): array
    {
        return [
            '#1a5f4a' => 'Forest',
            '#1e3a5f' => 'Navy',
            '#6b2d3c' => 'Wine',
            '#3d4f2f' => 'Olive',
            '#0f4c5c' => 'Teal',
            '#4a3728' => 'Espresso',
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
        header('Location: ' . $path);
        exit;
    }
}
