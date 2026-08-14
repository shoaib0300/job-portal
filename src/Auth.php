<?php

declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;

    public static function ensureSchema(): void
    {
        $pdo = Db::pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(160) NOT NULL DEFAULT \'\',
              username VARCHAR(80) NOT NULL,
              email VARCHAR(160) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_username (username),
              UNIQUE KEY uq_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_settings (
              user_id INT UNSIGNED NOT NULL,
              `key` VARCHAR(64) NOT NULL,
              `value` TEXT NOT NULL,
              PRIMARY KEY (user_id, `key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $tables = [
            'resume_profile',
            'resume_sections',
            'experience_entries',
            'resume_versions',
            'cover_letters',
            'applications',
            'search_history',
        ];
        foreach ($tables as $table) {
            $exists = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE \'user_id\'')->fetch();
            if ($exists === false) {
                $pdo->exec(
                    'ALTER TABLE `' . $table . '` ADD COLUMN user_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id'
                );
            }
        }

        $hasSectionKey = false;
        $hasUserSection = false;
        foreach ($pdo->query('SHOW INDEX FROM resume_sections')->fetchAll() as $idx) {
            $name = (string) ($idx['Key_name'] ?? '');
            if ($name === 'uq_section_key') {
                $hasSectionKey = true;
            }
            if ($name === 'uq_user_section') {
                $hasUserSection = true;
            }
        }
        if ($hasSectionKey) {
            $pdo->exec('ALTER TABLE resume_sections DROP INDEX uq_section_key');
        }
        if (!$hasUserSection) {
            $pdo->exec(
                'ALTER TABLE resume_sections ADD UNIQUE KEY uq_user_section (user_id, section_key)'
            );
        }

        self::seedOwner($pdo);
    }

    private static function seedOwner(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute(['muqaddas']);
        $row = $stmt->fetch();

        $profile = $pdo->query('SELECT full_name, email FROM resume_profile ORDER BY id ASC LIMIT 1')->fetch();
        $name = is_array($profile) && trim((string) ($profile['full_name'] ?? '')) !== ''
            ? trim((string) $profile['full_name'])
            : 'Muqaddas Khan';
        $email = is_array($profile) && filter_var((string) ($profile['email'] ?? ''), FILTER_VALIDATE_EMAIL)
            ? strtolower(trim((string) $profile['email']))
            : 'muqaddas@mnk.local';

        if ($row === false) {
            $hash = password_hash('muqaddas123', PASSWORD_DEFAULT);
            $pdo->prepare(
                'INSERT INTO users (name, username, email, password_hash) VALUES (?, ?, ?, ?)'
            )->execute([$name, 'muqaddas', $email, $hash]);
            $userId = (int) $pdo->lastInsertId();
        } else {
            $userId = (int) $row['id'];
        }

        foreach (
            [
                'resume_profile',
                'resume_sections',
                'experience_entries',
                'resume_versions',
                'cover_letters',
                'applications',
                'search_history',
            ] as $table
        ) {
            $pdo->prepare('UPDATE `' . $table . '` SET user_id = ? WHERE user_id = 0')->execute([$userId]);
        }

        $copied = $pdo->prepare('SELECT COUNT(*) FROM user_settings WHERE user_id = ?');
        $copied->execute([$userId]);
        if ((int) $copied->fetchColumn() === 0) {
            $settings = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll();
            $ins = $pdo->prepare(
                'INSERT INTO user_settings (user_id, `key`, `value`) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
            );
            foreach ($settings as $setting) {
                $ins->execute([$userId, (string) $setting['key'], (string) $setting['value']]);
            }
        }
    }

    public static function id(): int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : 0;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $stmt = Db::pdo()->prepare('SELECT id, name, username, email, created_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            unset($_SESSION['user_id']);
            return null;
        }
        self::$user = $row;
        return self::$user;
    }

    public static function impersonate(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        self::$user = null;
    }

    public static function loginAs(string $usernameOrEmail): bool
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $login = strtolower(trim($usernameOrEmail));
        $stmt->execute([$login, $login]);
        $row = $stmt->fetch();
        if ($row === false) {
            return false;
        }
        self::impersonate((int) $row['id']);
        return true;
    }

    public static function login(string $login, string $password): bool
    {
        $login = strtolower(trim($login));
        if ($login === '' || $password === '') {
            return false;
        }
        $stmt = Db::pdo()->prepare(
            'SELECT id, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $row = $stmt->fetch();
        if ($row === false || !password_verify($password, (string) $row['password_hash'])) {
            return false;
        }
        self::impersonate((int) $row['id']);
        return true;
    }

    public static function register(string $name, string $email, string $password): int
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a name and a valid email.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        $pdo = Db::pdo();
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            throw new InvalidArgumentException('That email is already registered.');
        }

        $username = self::uniqueUsername($email, $name);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare(
            'INSERT INTO users (name, username, email, password_hash) VALUES (?, ?, ?, ?)'
        )->execute([$name, $username, $email, $hash]);
        $userId = (int) $pdo->lastInsertId();
        self::provisionWorkspace($userId, $name, $email);
        self::impersonate($userId);
        return $userId;
    }

    public static function uniqueUsername(string $email, string $name): string
    {
        $base = strtolower((string) (explode('@', $email)[0] ?? ''));
        $base = preg_replace('/[^a-z0-9_]+/', '', $base) ?: preg_replace('/[^a-z0-9_]+/', '', strtolower($name));
        $base = $base !== '' ? substr((string) $base, 0, 24) : 'user';
        $pdo = Db::pdo();
        $candidate = $base;
        $n = 1;
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        while (true) {
            $check->execute([$candidate]);
            if ($check->fetch() === false) {
                return $candidate;
            }
            $n++;
            $candidate = $base . $n;
        }
    }

    public static function provisionWorkspace(int $userId, string $name, string $email): void
    {
        $pdo = Db::pdo();

        $defaults = [
            'accent_color' => '#4E6351',
            'theme' => 'sage',
            'font_family' => 'candara',
            'pdf_mode' => '0',
            'active_company' => '',
            'ui_density' => 'comfortable',
            'sidebar_mode' => 'expanded',
            'ui_mode' => 'warm',
            'name_size' => 'md',
            'section_spacing' => 'md',
        ];
        $insSet = $pdo->prepare(
            'INSERT INTO user_settings (user_id, `key`, `value`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        foreach ($defaults as $key => $value) {
            $insSet->execute([$userId, $key, $value]);
        }

        $pdo->prepare(
            'INSERT INTO resume_profile (user_id, full_name, title, email, phone, location, links)
             VALUES (?, ?, ?, ?, \'\', \'\', ?)'
        )->execute([
            $userId,
            $name,
            '',
            $email,
            json_encode([], JSON_UNESCAPED_SLASHES),
        ]);

        $sections = [
            ['summary', 'Summary', 'Write a short professional summary.', 10],
            ['experience', 'Experience', '', 20],
            ['skills', 'Skills', "Tools\n\nLanguages\nEnglish — Professional Working Proficiency", 30],
            ['education', 'Education', '', 40],
        ];
        $insSec = $pdo->prepare(
            'INSERT INTO resume_sections (user_id, section_key, title, body, sort_order, visible)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        foreach ($sections as $section) {
            $insSec->execute([$userId, $section[0], $section[1], $section[2], $section[3]]);
        }

        $pdo->prepare(
            'INSERT INTO cover_letters (user_id, title, body, company, is_active, is_base)
             VALUES (?, ?, ?, \'\', 1, 1)'
        )->execute([
            $userId,
            'Main cover letter',
            "Dear Hiring Manager,\n\nI am writing to express my interest in the open role on your team.\n\nSincerely,\n" . $name,
        ]);

        $previous = $_SESSION['user_id'] ?? null;
        self::impersonate($userId);
        $snapshot = Versions::captureSnapshot();
        Versions::saveResumeVersion(
            'Main resume',
            $snapshot,
            '',
            'Stable base resume.',
            true,
            null,
            true
        );
        if (is_int($previous) || (is_string($previous) && $previous !== '')) {
            $_SESSION['user_id'] = (int) $previous;
            self::$user = null;
        }
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        self::$user = null;
    }

    public static function requireLogin(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (self::id() > 0) {
            return;
        }
        $next = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?next=' . rawurlencode($next));
        exit;
    }

    public static function updateEmail(int $userId, string $email): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email.');
        }
        $stmt = Db::pdo()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('That email is already in use.');
        }
        Db::pdo()->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $userId]);
        self::$user = null;
    }
}
