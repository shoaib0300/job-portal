<?php

declare(strict_types=1);

final class SuperAdmin
{
    private static ?array $admin = null;

    public static function ensureSchema(): void
    {
        $pdo = Db::pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS super_admins (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              email VARCHAR(160) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY uq_super_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS super_admin_emails (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              email VARCHAR(160) NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_recovery_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS super_admin_reset_tokens (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              token_hash VARCHAR(64) NOT NULL,
              expires_at DATETIME NOT NULL,
              used_at DATETIME NULL DEFAULT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_token_hash (token_hash),
              KEY idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        self::ensureUserColumns($pdo);
        self::seed($pdo);
    }

    private static function ensureUserColumns(PDO $pdo): void
    {
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll() as $row) {
            $cols[(string) ($row['Field'] ?? '')] = true;
        }
        if (!isset($cols['is_active'])) {
            $pdo->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash');
        }
        if (!isset($cols['can_translate'])) {
            $pdo->exec('ALTER TABLE users ADD COLUMN can_translate TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active');
        }
        if (!isset($cols['last_login_at'])) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER can_translate');
        }
    }

    private static function seed(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM super_admins')->fetchColumn();
        if ($count === 0) {
            $hash = password_hash('SuperAdmin!2026', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO super_admins (email, password_hash) VALUES (?, ?)')
                ->execute(['shoaibsarwar187@gmail.com', $hash]);
        }

        $ins = $pdo->prepare('INSERT IGNORE INTO super_admin_emails (email) VALUES (?)');
        foreach (['shoaibsarwar187@gmail.com', 'shoaibsarwar095@gmail.com'] as $email) {
            $ins->execute([$email]);
        }
    }

    public static function id(): int
    {
        $admin = self::admin();
        return $admin ? (int) $admin['id'] : 0;
    }

    public static function admin(): ?array
    {
        if (self::$admin !== null) {
            return self::$admin;
        }
        $id = (int) ($_SESSION['super_admin_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $stmt = Db::pdo()->prepare('SELECT id, email, created_at, updated_at FROM super_admins WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            unset($_SESSION['super_admin_id']);
            return null;
        }
        self::$admin = $row;
        return self::$admin;
    }

    public static function login(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            return false;
        }
        $stmt = Db::pdo()->prepare('SELECT id, password_hash FROM super_admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row === false || !password_verify($password, (string) $row['password_hash'])) {
            // Also allow login with any recovery email if password matches the super admin hash
            if (!self::isRecoveryEmail($email)) {
                return false;
            }
            $admin = Db::pdo()->query('SELECT id, password_hash FROM super_admins ORDER BY id ASC LIMIT 1')->fetch();
            if ($admin === false || !password_verify($password, (string) $admin['password_hash'])) {
                return false;
            }
            $_SESSION['super_admin_id'] = (int) $admin['id'];
            self::$admin = null;
            return true;
        }
        $_SESSION['super_admin_id'] = (int) $row['id'];
        self::$admin = null;
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['super_admin_id']);
        self::$admin = null;
    }

    public static function requireLogin(): void
    {
        if (self::id() > 0) {
            return;
        }
        header('Location: /super-admin/');
        exit;
    }

    public static function isRecoveryEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        $stmt = Db::pdo()->prepare('SELECT id FROM super_admin_emails WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }

    /** @return list<string> */
    public static function recoveryEmails(): array
    {
        $rows = Db::pdo()->query('SELECT email FROM super_admin_emails ORDER BY email ASC')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = (string) $row['email'];
        }
        return $out;
    }

    public static function addRecoveryEmail(string $email): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email.');
        }
        Db::pdo()->prepare('INSERT IGNORE INTO super_admin_emails (email) VALUES (?)')->execute([$email]);
    }

    public static function removeRecoveryEmail(string $email): void
    {
        $email = strtolower(trim($email));
        $emails = self::recoveryEmails();
        if (count($emails) <= 1) {
            throw new InvalidArgumentException('Keep at least one recovery email.');
        }
        Db::pdo()->prepare('DELETE FROM super_admin_emails WHERE email = ?')->execute([$email]);
    }

    public static function changePassword(string $current, string $new): void
    {
        $admin = self::admin();
        if ($admin === null) {
            throw new RuntimeException('Not signed in.');
        }
        if (strlen($new) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        $stmt = Db::pdo()->prepare('SELECT password_hash FROM super_admins WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $admin['id']]);
        $row = $stmt->fetch();
        if ($row === false || !password_verify($current, (string) $row['password_hash'])) {
            throw new InvalidArgumentException('Current password is incorrect.');
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        Db::pdo()->prepare('UPDATE super_admins SET password_hash = ? WHERE id = ?')
            ->execute([$hash, (int) $admin['id']]);
    }

    public static function setPrimaryEmail(string $email): void
    {
        $admin = self::admin();
        if ($admin === null) {
            throw new RuntimeException('Not signed in.');
        }
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email.');
        }
        Db::pdo()->prepare('UPDATE super_admins SET email = ? WHERE id = ?')
            ->execute([$email, (int) $admin['id']]);
        self::addRecoveryEmail($email);
        self::$admin = null;
    }

    /**
     * @return array{token: string, url: string}
     */
    public static function createResetToken(): array
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
        Db::pdo()->prepare(
            'INSERT INTO super_admin_reset_tokens (token_hash, expires_at) VALUES (?, ?)'
        )->execute([$hash, $expires]);
        $base = Site::marketingBaseUrl();
        return [
            'token' => $token,
            'url' => $base . '/super-admin/reset.php?token=' . rawurlencode($token),
        ];
    }

    public static function resetPasswordWithToken(string $token, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        $hash = hash('sha256', $token);
        $stmt = Db::pdo()->prepare(
            'SELECT id FROM super_admin_reset_tokens
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('Reset link is invalid or expired.');
        }
        $admin = Db::pdo()->query('SELECT id FROM super_admins ORDER BY id ASC LIMIT 1')->fetch();
        if ($admin === false) {
            throw new RuntimeException('No super admin account.');
        }
        $passHash = password_hash($newPassword, PASSWORD_DEFAULT);
        Db::pdo()->prepare('UPDATE super_admins SET password_hash = ? WHERE id = ?')
            ->execute([$passHash, (int) $admin['id']]);
        Db::pdo()->prepare('UPDATE super_admin_reset_tokens SET used_at = NOW() WHERE id = ?')
            ->execute([(int) $row['id']]);
    }

    public static function tryMail(string $to, string $subject, string $body): bool
    {
        $headers = "From: noreply@mnk.local\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        return @mail($to, $subject, $body, $headers);
    }

    public static function isDev(): bool
    {
        return App::isDev();
    }

    /** @return list<array<string, mixed>> */
    public static function listUsers(): array
    {
        return Db::pdo()->query(
            'SELECT id, name, username, email, is_active, can_translate, created_at, last_login_at
             FROM users ORDER BY id ASC'
        )->fetchAll() ?: [];
    }

    public static function getUser(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, username, email, is_active, can_translate, created_at, last_login_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function createUser(string $name, string $username, string $email, string $password): int
    {
        $name = trim($name);
        $username = strtolower(trim($username));
        $email = strtolower(trim($email));
        if ($name === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Name, username, and valid email are required.');
        }
        if (!preg_match('/^[a-z0-9_]{3,80}$/', $username)) {
            throw new InvalidArgumentException('Username: 3–80 chars, letters/numbers/underscore.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        $pdo = Db::pdo();
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            throw new InvalidArgumentException('Username or email already taken.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $pdo->prepare(
                'INSERT INTO users (name, username, email, password_hash, is_active, can_translate)
                 VALUES (?, ?, ?, ?, 1, 1)'
            )->execute([$name, $username, $email, $hash]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new InvalidArgumentException('Username or email already taken.');
            }
            throw $e;
        }
        return (int) $pdo->lastInsertId();
    }

    public static function setUserActive(int $userId, bool $active): void
    {
        Db::pdo()->prepare('UPDATE users SET is_active = ? WHERE id = ?')
            ->execute([$active ? 1 : 0, $userId]);
        if (!$active) {
            return;
        }
        $user = self::getUser($userId);
        if ($user === null) {
            return;
        }
        $email = (string) ($user['email'] ?? '');
        $name = (string) ($user['name'] ?? 'there');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $body = "Hi {$name},\n\n"
            . "Your KaamMilo account has been enabled. You can sign in now.\n\n"
            . "— KaamMilo\n";
        try {
            self::tryMail($email, 'KaamMilo — account enabled', $body);
        } catch (Throwable) {
        }
    }

    public static function countPendingUsers(): int
    {
        return (int) Db::pdo()->query(
            'SELECT COUNT(*) FROM users WHERE is_active = 0'
        )->fetchColumn();
    }

    public static function setUserCanTranslate(int $userId, bool $can): void
    {
        Db::pdo()->prepare('UPDATE users SET can_translate = ? WHERE id = ?')
            ->execute([$can ? 1 : 0, $userId]);
    }

    public static function setUserPassword(int $userId, string $password): void
    {
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        Db::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([$hash, $userId]);
    }

    public static function deleteUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user.');
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach (
                [
                    'user_settings',
                    'resume_profile',
                    'resume_sections',
                    'experience_entries',
                    'resume_versions',
                    'cover_letters',
                    'applications',
                    'search_history',
                    'career_companies',
                ] as $table
            ) {
                try {
                    $pdo->prepare('DELETE FROM `' . $table . '` WHERE user_id = ?')->execute([$userId]);
                } catch (Throwable) {
                    // Table may not exist in older DBs.
                }
            }
            try {
                $pdo->prepare('DELETE FROM translation_usage WHERE user_id = ?')->execute([$userId]);
            } catch (Throwable) {
            }
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function touchLastLogin(int $userId): void
    {
        Db::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);
    }

    public static function userCanTranslate(int $userId): bool
    {
        if ($userId <= 0) {
            return true;
        }
        $stmt = Db::pdo()->prepare('SELECT can_translate FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return true;
        }
        return (int) ($row['can_translate'] ?? 1) === 1;
    }
}
