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
                'gender' => '',
                'date_of_birth' => null,
                'country' => '',
                'nationality' => '',
                'photo_path' => '',
                'show_photo' => 1,
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
        return date('j M Y', $ts);
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
        $font = $font ?: (self::setting('font_family', 'georgia') ?: 'georgia');
        return in_array($font, self::fontKeys(), true) ? $font : 'georgia';
    }

    public static function fontStack(string $key): string
    {
        return self::fonts()[$key]['stack'] ?? 'Georgia, serif';
    }

    public static function googleFontsHref(?string $selected = null): string
    {
        $families = [
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
