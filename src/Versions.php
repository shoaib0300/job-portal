<?php

declare(strict_types=1);

/**
 * Resume version snapshots + cover-letter helpers.
 *
 * Live tables (resume_sections / experience_entries / profile) are the working copy.
 * Named versions store JSON snapshots so Main stays stable while you tailor copies.
 */
final class Versions
{
    public static function ensureSchema(): void
    {
        $pdo = Db::pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS resume_versions (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              title VARCHAR(200) NOT NULL,
              company VARCHAR(160) NOT NULL DEFAULT \'\',
              is_base TINYINT(1) NOT NULL DEFAULT 0,
              is_active TINYINT(1) NOT NULL DEFAULT 0,
              profile_title VARCHAR(200) NOT NULL DEFAULT \'\',
              snapshot MEDIUMTEXT NOT NULL,
              note TEXT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY idx_base (is_base),
              KEY idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $cols = $pdo->query('SHOW COLUMNS FROM cover_letters LIKE \'is_base\'')->fetch();
        if ($cols === false) {
            $pdo->exec(
                'ALTER TABLE cover_letters ADD COLUMN is_base TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active'
            );
        }
    }

    private static function uid(): int
    {
        $id = Auth::id();
        if ($id <= 0) {
            throw new RuntimeException('Not signed in.');
        }
        return $id;
    }

    public static function captureSnapshot(?string $profileTitle = null): array
    {
        $profile = App::profile();
        $sections = [];
        foreach (App::sections(false) as $section) {
            $sections[] = [
                'section_key' => (string) ($section['section_key'] ?? ''),
                'title' => (string) ($section['title'] ?? ''),
                'body' => (string) ($section['body'] ?? ''),
                'sort_order' => (int) ($section['sort_order'] ?? 0),
                'visible' => (int) ($section['visible'] ?? 1),
            ];
        }
        $experiences = [];
        foreach (App::experiences(false) as $job) {
            $experiences[] = [
                'company' => (string) ($job['company'] ?? ''),
                'position' => (string) ($job['position'] ?? ''),
                'location' => (string) ($job['location'] ?? ''),
                'start_date' => (string) ($job['start_date'] ?? ''),
                'end_date' => (string) ($job['end_date'] ?? ''),
                'bullets' => (string) ($job['bullets'] ?? ''),
                'sort_order' => (int) ($job['sort_order'] ?? 0),
                'visible' => (int) ($job['visible'] ?? 1),
            ];
        }

        return [
            'profile_title' => $profileTitle !== null
                ? $profileTitle
                : (string) ($profile['title'] ?? ''),
            'location' => (string) ($profile['location'] ?? ''),
            'sections' => $sections,
            'experiences' => $experiences,
        ];
    }

    public static function encodeSnapshot(array $snapshot): string
    {
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Could not encode resume snapshot');
        }
        return $json;
    }

    public static function decodeSnapshot(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return ['profile_title' => '', 'location' => '', 'sections' => [], 'experiences' => []];
        }
        return [
            'profile_title' => (string) ($decoded['profile_title'] ?? ''),
            'location' => (string) ($decoded['location'] ?? ''),
            'sections' => is_array($decoded['sections'] ?? null) ? $decoded['sections'] : [],
            'experiences' => is_array($decoded['experiences'] ?? null) ? $decoded['experiences'] : [],
        ];
    }

    public static function applySnapshot(array $snapshot, bool $updateProfileTitle = true): void
    {
        $pdo = Db::pdo();
        $uid = self::uid();
        $pdo->beginTransaction();
        try {
            if ($updateProfileTitle) {
                $fields = [];
                $values = [];
                if (isset($snapshot['profile_title'])) {
                    $fields[] = 'title = ?';
                    $values[] = (string) $snapshot['profile_title'];
                }
                if (array_key_exists('location', $snapshot) && (string) $snapshot['location'] !== '') {
                    $fields[] = 'location = ?';
                    $values[] = (string) $snapshot['location'];
                }
                if ($fields) {
                    $values[] = $uid;
                    $pdo->prepare('UPDATE resume_profile SET ' . implode(', ', $fields) . ' WHERE user_id = ?')
                        ->execute($values);
                }
            }

            $pdo->prepare('DELETE FROM resume_sections WHERE user_id = ?')->execute([$uid]);
            $insSection = $pdo->prepare(
                'INSERT INTO resume_sections (user_id, section_key, title, body, sort_order, visible) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($snapshot['sections'] as $section) {
                if (!is_array($section)) {
                    continue;
                }
                $key = trim((string) ($section['section_key'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $insSection->execute([
                    $uid,
                    $key,
                    (string) ($section['title'] ?? $key),
                    (string) ($section['body'] ?? ''),
                    (int) ($section['sort_order'] ?? 0),
                    (int) ($section['visible'] ?? 1),
                ]);
            }

            $pdo->prepare('DELETE FROM experience_entries WHERE user_id = ?')->execute([$uid]);
            $insExp = $pdo->prepare(
                'INSERT INTO experience_entries (user_id, company, position, location, start_date, end_date, bullets, sort_order, visible)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($snapshot['experiences'] as $job) {
                if (!is_array($job)) {
                    continue;
                }
                $insExp->execute([
                    $uid,
                    (string) ($job['company'] ?? ''),
                    (string) ($job['position'] ?? ''),
                    (string) ($job['location'] ?? ''),
                    (string) ($job['start_date'] ?? ''),
                    (string) ($job['end_date'] ?? ''),
                    (string) ($job['bullets'] ?? ''),
                    (int) ($job['sort_order'] ?? 0),
                    (int) ($job['visible'] ?? 1),
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function resumeVersions(): array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT id, title, company, is_base, is_active, profile_title, note, created_at, updated_at
             FROM resume_versions
             WHERE user_id = ?
             ORDER BY is_base DESC, is_active DESC, updated_at DESC, id DESC'
        );
        $stmt->execute([self::uid()]);
        return $stmt->fetchAll();
    }

    public static function resumeVersion(int $id): ?array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare('SELECT * FROM resume_versions WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, self::uid()]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function baseResumeVersion(): ?array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM resume_versions WHERE user_id = ? AND is_base = 1 ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([self::uid()]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function activeResumeVersion(): ?array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM resume_versions WHERE user_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1'
        );
        $stmt->execute([self::uid()]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function saveResumeVersion(
        string $title,
        array $snapshot,
        string $company = '',
        string $note = '',
        bool $asBase = false,
        ?int $id = null,
        bool $makeActive = true
    ): int {
        self::ensureSchema();
        $pdo = Db::pdo();
        $uid = self::uid();
        $title = trim($title) !== '' ? trim($title) : 'Resume version';
        $encoded = self::encodeSnapshot($snapshot);
        $profileTitle = (string) ($snapshot['profile_title'] ?? '');

        if ($asBase) {
            $pdo->prepare('UPDATE resume_versions SET is_base = 0 WHERE user_id = ?')->execute([$uid]);
        }
        if ($makeActive) {
            $pdo->prepare('UPDATE resume_versions SET is_active = 0 WHERE user_id = ?')->execute([$uid]);
        }

        if ($id !== null && $id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE resume_versions
                 SET title = ?, company = ?, is_base = ?, is_active = ?, profile_title = ?, snapshot = ?, note = ?
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([
                $title,
                $company,
                $asBase ? 1 : 0,
                $makeActive ? 1 : 0,
                $profileTitle,
                $encoded,
                $note !== '' ? $note : null,
                $id,
                $uid,
            ]);
            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO resume_versions (user_id, title, company, is_base, is_active, profile_title, snapshot, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $uid,
            $title,
            $company,
            $asBase ? 1 : 0,
            $makeActive ? 1 : 0,
            $profileTitle,
            $encoded,
            $note !== '' ? $note : null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function updateBaseFromLive(string $title = 'Main resume'): int
    {
        $snapshot = self::captureSnapshot();
        $base = self::baseResumeVersion();
        return self::saveResumeVersion(
            $title,
            $snapshot,
            '',
            'Stable base resume — restore this before / after JD tailoring.',
            true,
            $base ? (int) $base['id'] : null,
            true
        );
    }

    public static function loadResumeVersion(int $id): void
    {
        $row = self::resumeVersion($id);
        if ($row === null) {
            throw new RuntimeException('Resume version not found');
        }
        $snapshot = self::decodeSnapshot((string) $row['snapshot']);
        self::applySnapshot($snapshot, true);
        $pdo = Db::pdo();
        $uid = self::uid();
        $pdo->prepare('UPDATE resume_versions SET is_active = 0 WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('UPDATE resume_versions SET is_active = 1 WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    }

    public static function deleteResumeVersion(int $id): void
    {
        $row = self::resumeVersion($id);
        if ($row === null) {
            return;
        }
        if ((int) ($row['is_base'] ?? 0) === 1) {
            throw new RuntimeException('Cannot delete the Main resume. Save a new Main first if you need to replace it.');
        }
        Db::pdo()->prepare('DELETE FROM resume_versions WHERE id = ? AND user_id = ?')->execute([$id, self::uid()]);
    }

    public static function resumePayloadForView(?int $versionId): array
    {
        $profile = App::profile();
        if ($versionId === null || $versionId <= 0) {
            return [
                'profile' => $profile,
                'sections' => App::sections(true),
                'experiences' => App::experiences(true),
                'version' => null,
                'company' => App::setting('active_company', '') ?: '',
            ];
        }

        $row = self::resumeVersion($versionId);
        if ($row === null) {
            return [
                'profile' => $profile,
                'sections' => App::sections(true),
                'experiences' => App::experiences(true),
                'version' => null,
                'company' => App::setting('active_company', '') ?: '',
            ];
        }

        $snapshot = self::decodeSnapshot((string) $row['snapshot']);
        if (($snapshot['profile_title'] ?? '') !== '') {
            $profile['title'] = $snapshot['profile_title'];
        }
        if (($snapshot['location'] ?? '') !== '') {
            $profile['location'] = $snapshot['location'];
        }

        $sections = array_values(array_filter(
            $snapshot['sections'],
            static fn($s): bool => is_array($s) && (int) ($s['visible'] ?? 1) === 1
        ));
        usort($sections, static fn($a, $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        $experiences = array_values(array_filter(
            $snapshot['experiences'],
            static fn($e): bool => is_array($e) && (int) ($e['visible'] ?? 1) === 1
        ));
        usort($experiences, static fn($a, $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        return [
            'profile' => $profile,
            'sections' => $sections,
            'experiences' => $experiences,
            'version' => $row,
            'company' => (string) ($row['company'] ?? '') ?: (App::setting('active_company', '') ?: ''),
        ];
    }

    public static function resumeExportOptions(): array
    {
        $options = [];
        foreach (self::resumeVersions() as $row) {
            $label = (int) $row['is_base'] === 1
                ? 'Main resume'
                : (string) $row['title'];
            $options[] = [
                'id' => (int) $row['id'],
                'label' => $label,
                'company' => (string) ($row['company'] ?? ''),
                'base' => (int) $row['is_base'] === 1,
                'active' => (int) $row['is_active'] === 1,
            ];
        }
        return $options;
    }

    public static function coverExportOptions(): array
    {
        self::ensureSchema();
        $options = [];
        foreach (App::coverLetters() as $row) {
            $label = (int) ($row['is_base'] ?? 0) === 1
                ? 'Main cover letter'
                : (string) $row['title'];
            $options[] = [
                'id' => (int) $row['id'],
                'label' => $label,
                'company' => (string) ($row['company'] ?? ''),
                'base' => (int) ($row['is_base'] ?? 0) === 1,
                'active' => (int) ($row['is_active'] ?? 0) === 1,
            ];
        }
        return $options;
    }

    public static function coverLetterById(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM cover_letters WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, self::uid()]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function baseCoverLetter(): ?array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM cover_letters WHERE user_id = ? AND is_base = 1 ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([self::uid()]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function markCoverBase(int $id): void
    {
        self::ensureSchema();
        $pdo = Db::pdo();
        $uid = self::uid();
        $pdo->prepare('UPDATE cover_letters SET is_base = 0 WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('UPDATE cover_letters SET is_base = 1 WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    }

    public static function activateCover(int $id): void
    {
        $pdo = Db::pdo();
        $uid = self::uid();
        $pdo->prepare('UPDATE cover_letters SET is_active = 0 WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('UPDATE cover_letters SET is_active = 1 WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    }

    public static function duplicateCover(int $id, string $title = ''): int
    {
        $src = self::coverLetterById($id);
        if ($src === null) {
            throw new RuntimeException('Cover letter not found');
        }
        $pdo = Db::pdo();
        $uid = self::uid();
        $pdo->prepare('UPDATE cover_letters SET is_active = 0 WHERE user_id = ?')->execute([$uid]);
        $newTitle = trim($title) !== '' ? trim($title) : ((string) $src['title'] . ' (copy)');
        $stmt = $pdo->prepare(
            'INSERT INTO cover_letters (user_id, title, body, company, is_active, is_base) VALUES (?, ?, ?, ?, 1, 0)'
        );
        $stmt->execute([
            $uid,
            $newTitle,
            (string) $src['body'],
            (string) ($src['company'] ?? ''),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function deleteCover(int $id): void
    {
        $row = self::coverLetterById($id);
        if ($row === null) {
            return;
        }
        if ((int) ($row['is_base'] ?? 0) === 1) {
            throw new RuntimeException('Cannot delete the Main cover letter. Mark another as Main first.');
        }
        $uid = self::uid();
        Db::pdo()->prepare('DELETE FROM cover_letters WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        $active = App::activeCoverLetter();
        if ($active === null) {
            $stmt = Db::pdo()->prepare(
                'SELECT id FROM cover_letters WHERE user_id = ? ORDER BY id ASC LIMIT 1'
            );
            $stmt->execute([$uid]);
            $first = $stmt->fetch();
            if ($first) {
                self::activateCover((int) $first['id']);
            }
        }
    }
}
