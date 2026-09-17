<?php

declare(strict_types=1);

namespace KaamFit\Interview;

use Db;

final class InterviewTaxonomy
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    public static function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            '&' => ' and ',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;
        return trim($value, '-') ?: 'item';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function importTaxonomyArray(array $data): array
    {
        InterviewSchema::ensureSchema();
        $pdo = Db::pdo();
        $stats = ['industries' => 0, 'occupations' => 0, 'specializations' => 0, 'skills' => 0, 'technologies' => 0];

        $insInd = $pdo->prepare(
            'INSERT INTO interview_industries (slug, name_en, name_de, sort_order, enabled)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_de = VALUES(name_de), sort_order = VALUES(sort_order), enabled = 1'
        );
        $insOcc = $pdo->prepare(
            'INSERT INTO interview_occupations (industry_id, slug, name_en, name_de, sort_order, enabled)
             VALUES (?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE industry_id = VALUES(industry_id), name_en = VALUES(name_en), name_de = VALUES(name_de), sort_order = VALUES(sort_order), enabled = 1'
        );
        $insSpec = $pdo->prepare(
            'INSERT INTO interview_specializations (occupation_id, slug, name_en, name_de, sort_order, enabled)
             VALUES (?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE occupation_id = VALUES(occupation_id), name_en = VALUES(name_en), name_de = VALUES(name_de), enabled = 1'
        );
        $insSkill = $pdo->prepare(
            'INSERT INTO interview_skills (slug, name_en, name_de, kind, enabled)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_de = VALUES(name_de), kind = VALUES(kind), enabled = 1'
        );
        $insTech = $pdo->prepare(
            'INSERT INTO interview_technologies (slug, name_en, name_de, enabled)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_de = VALUES(name_de), enabled = 1'
        );

        $sort = 0;
        foreach (($data['industries'] ?? []) as $ind) {
            if (!is_array($ind)) {
                continue;
            }
            $slug = (string) ($ind['slug'] ?? self::slugify((string) ($ind['name_en'] ?? '')));
            if ($slug === '') {
                continue;
            }
            $insInd->execute([
                $slug,
                (string) ($ind['name_en'] ?? $slug),
                (string) ($ind['name_de'] ?? ''),
                (int) ($ind['sort_order'] ?? $sort),
            ]);
            $stats['industries']++;
            $sort += 10;
            $indId = self::idBySlug('interview_industries', $slug);
            $oSort = 0;
            foreach (($ind['occupations'] ?? []) as $occ) {
                if (!is_array($occ)) {
                    continue;
                }
                $oSlug = (string) ($occ['slug'] ?? self::slugify((string) ($occ['name_en'] ?? '')));
                if ($oSlug === '' || $indId < 1) {
                    continue;
                }
                $insOcc->execute([
                    $indId,
                    $oSlug,
                    (string) ($occ['name_en'] ?? $oSlug),
                    (string) ($occ['name_de'] ?? ''),
                    (int) ($occ['sort_order'] ?? $oSort),
                ]);
                $stats['occupations']++;
                $oSort += 10;
                $occId = self::idBySlug('interview_occupations', $oSlug);
                $sSort = 0;
                foreach (($occ['specializations'] ?? []) as $spec) {
                    if (!is_array($spec)) {
                        continue;
                    }
                    $sSlug = (string) ($spec['slug'] ?? self::slugify((string) ($spec['name_en'] ?? '')));
                    if ($sSlug === '' || $occId < 1) {
                        continue;
                    }
                    $insSpec->execute([
                        $occId,
                        $sSlug,
                        (string) ($spec['name_en'] ?? $sSlug),
                        (string) ($spec['name_de'] ?? ''),
                        (int) ($spec['sort_order'] ?? $sSort),
                    ]);
                    $stats['specializations']++;
                    $sSort += 10;
                }
            }
        }

        foreach (($data['skills'] ?? []) as $skill) {
            if (!is_array($skill)) {
                continue;
            }
            $slug = (string) ($skill['slug'] ?? self::slugify((string) ($skill['name_en'] ?? '')));
            if ($slug === '') {
                continue;
            }
            $insSkill->execute([
                $slug,
                (string) ($skill['name_en'] ?? $slug),
                (string) ($skill['name_de'] ?? ''),
                (string) ($skill['kind'] ?? 'universal'),
            ]);
            $stats['skills']++;
        }

        foreach (($data['technologies'] ?? []) as $tech) {
            if (!is_array($tech)) {
                continue;
            }
            $slug = (string) ($tech['slug'] ?? self::slugify((string) ($tech['name_en'] ?? '')));
            if ($slug === '') {
                continue;
            }
            $insTech->execute([
                $slug,
                (string) ($tech['name_en'] ?? $slug),
                (string) ($tech['name_de'] ?? ''),
            ]);
            $stats['technologies']++;
        }

        self::clearCache();
        return $stats;
    }

    public static function idBySlug(string $table, string $slug): int
    {
        $allowed = [
            'interview_industries', 'interview_occupations', 'interview_specializations',
            'interview_skills', 'interview_technologies', 'interview_stages',
        ];
        if (!in_array($table, $allowed, true)) {
            return 0;
        }
        $stmt = Db::pdo()->prepare("SELECT id FROM {$table} WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @return list<array<string, mixed>> */
    public static function industries(bool $enabledOnly = true): array
    {
        $sql = 'SELECT * FROM interview_industries';
        if ($enabledOnly) {
            $sql .= ' WHERE enabled = 1';
        }
        $sql .= ' ORDER BY sort_order, name_en';
        return Db::pdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function occupations(?int $industryId = null, bool $enabledOnly = true): array
    {
        $sql = 'SELECT * FROM interview_occupations WHERE 1=1';
        $params = [];
        if ($industryId !== null && $industryId > 0) {
            $sql .= ' AND industry_id = ?';
            $params[] = $industryId;
        }
        if ($enabledOnly) {
            $sql .= ' AND enabled = 1';
        }
        $sql .= ' ORDER BY sort_order, name_en';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function specializations(?int $occupationId = null, bool $enabledOnly = true): array
    {
        $sql = 'SELECT * FROM interview_specializations WHERE 1=1';
        $params = [];
        if ($occupationId !== null && $occupationId > 0) {
            $sql .= ' AND occupation_id = ?';
            $params[] = $occupationId;
        }
        if ($enabledOnly) {
            $sql .= ' AND enabled = 1';
        }
        $sql .= ' ORDER BY sort_order, name_en';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function skills(bool $enabledOnly = true): array
    {
        $sql = 'SELECT * FROM interview_skills';
        if ($enabledOnly) {
            $sql .= ' WHERE enabled = 1';
        }
        $sql .= ' ORDER BY kind, name_en';
        return Db::pdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function technologies(bool $enabledOnly = true): array
    {
        $sql = 'SELECT * FROM interview_technologies';
        if ($enabledOnly) {
            $sql .= ' WHERE enabled = 1';
        }
        $sql .= ' ORDER BY name_en';
        return Db::pdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Skills used on questions tagged with the given occupation (or all if none).
     * @return list<array<string, mixed>>
     */
    public static function skillsForOccupation(?int $occupationId = null, ?int $specializationId = null): array
    {
        InterviewSchema::ensureSchema();
        if ($occupationId === null && $specializationId === null) {
            return self::skills();
        }
        $joins = [];
        $where = ['s.enabled = 1'];
        $params = [];
        if ($occupationId !== null && $occupationId > 0) {
            $joins[] = 'INNER JOIN interview_question_occupations qo ON qo.question_id = qs.question_id';
            $where[] = 'qo.occupation_id = ?';
            $params[] = $occupationId;
        }
        if ($specializationId !== null && $specializationId > 0) {
            $joins[] = 'INNER JOIN interview_question_specializations qsp ON qsp.question_id = qs.question_id';
            $where[] = 'qsp.specialization_id = ?';
            $params[] = $specializationId;
        }
        $sql = 'SELECT DISTINCT s.* FROM interview_skills s
                INNER JOIN interview_question_skills qs ON qs.skill_id = s.id
                ' . implode("\n", $joins) . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY s.name_en LIMIT 200';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        return $rows !== [] ? $rows : self::skills();
    }

    /**
     * Technologies used on questions for occupation (or all).
     * @return list<array<string, mixed>>
     */
    public static function technologiesForOccupation(?int $occupationId = null): array
    {
        InterviewSchema::ensureSchema();
        if ($occupationId === null || $occupationId < 1) {
            return self::technologies();
        }
        $stmt = Db::pdo()->prepare(
            'SELECT DISTINCT t.* FROM interview_technologies t
             INNER JOIN interview_question_technologies qt ON qt.technology_id = t.id
             INNER JOIN interview_question_occupations qo ON qo.question_id = qt.question_id
             WHERE t.enabled = 1 AND qo.occupation_id = ?
             ORDER BY t.name_en LIMIT 200'
        );
        $stmt->execute([$occupationId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        return $rows !== [] ? $rows : self::technologies();
    }

    /** Compact payload for cascading filter AJAX. */
    public static function cascadePayload(?string $industrySlug, ?string $occupationSlug, ?string $specializationSlug): array
    {
        InterviewSchema::ensureSchema();
        $indId = $industrySlug ? self::idBySlug('interview_industries', $industrySlug) : 0;
        if ($industrySlug && $indId < 1) {
            $industrySlug = null;
        }
        $occId = $occupationSlug ? self::idBySlug('interview_occupations', $occupationSlug) : 0;
        if ($occupationSlug && $occId < 1) {
            $occupationSlug = null;
            $occId = 0;
        }
        $specId = $specializationSlug ? self::idBySlug('interview_specializations', $specializationSlug) : 0;
        if ($specializationSlug && $specId < 1) {
            $specializationSlug = null;
            $specId = 0;
        }

        $occupations = $indId > 0 ? self::occupations($indId) : self::occupations();
        // If occupation not under selected industry, clear it
        if ($occId > 0 && $indId > 0) {
            $ok = false;
            foreach ($occupations as $o) {
                if ((int) $o['id'] === $occId) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $occId = 0;
                $occupationSlug = null;
                $specId = 0;
                $specializationSlug = null;
            }
        }
        $specializations = $occId > 0 ? self::specializations($occId) : [];
        if ($specId > 0 && $occId > 0) {
            $ok = false;
            foreach ($specializations as $s) {
                if ((int) $s['id'] === $specId) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $specId = 0;
                $specializationSlug = null;
            }
        }

        $map = static fn(array $rows): array => array_map(
            static fn(array $r): array => [
                'slug' => (string) $r['slug'],
                'name_en' => (string) $r['name_en'],
                'name_de' => (string) ($r['name_de'] ?? ''),
            ],
            $rows
        );

        return [
            'industry' => $industrySlug,
            'occupation' => $occupationSlug,
            'specialization' => $specializationSlug,
            'occupations' => $map($occupations),
            'specializations' => $map($specializations),
            'skills' => $map(self::skillsForOccupation($occId > 0 ? $occId : null, $specId > 0 ? $specId : null)),
            'technologies' => $map(self::technologiesForOccupation($occId > 0 ? $occId : null)),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function stages(bool $enabledOnly = true): array
    {
        $sql = 'SELECT * FROM interview_stages';
        if ($enabledOnly) {
            $sql .= ' WHERE enabled = 1';
        }
        $sql .= ' ORDER BY sort_order, name_en';
        return Db::pdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function upsertIndustry(string $slug, string $nameEn, string $nameDe = '', int $sort = 0, bool $enabled = true): int
    {
        $slug = self::slugify($slug !== '' ? $slug : $nameEn);
        Db::pdo()->prepare(
            'INSERT INTO interview_industries (slug, name_en, name_de, sort_order, enabled)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_de = VALUES(name_de), sort_order = VALUES(sort_order), enabled = VALUES(enabled)'
        )->execute([$slug, $nameEn, $nameDe, $sort, $enabled ? 1 : 0]);
        self::clearCache();
        return self::idBySlug('interview_industries', $slug);
    }

    public static function upsertOccupation(int $industryId, string $slug, string $nameEn, string $nameDe = '', int $sort = 0, bool $enabled = true): int
    {
        $slug = self::slugify($slug !== '' ? $slug : $nameEn);
        Db::pdo()->prepare(
            'INSERT INTO interview_occupations (industry_id, slug, name_en, name_de, sort_order, enabled)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE industry_id = VALUES(industry_id), name_en = VALUES(name_en), name_de = VALUES(name_de), sort_order = VALUES(sort_order), enabled = VALUES(enabled)'
        )->execute([$industryId, $slug, $nameEn, $nameDe, $sort, $enabled ? 1 : 0]);
        self::clearCache();
        return self::idBySlug('interview_occupations', $slug);
    }

    public static function upsertSkill(string $slug, string $nameEn, string $nameDe = '', string $kind = 'universal', bool $enabled = true): int
    {
        $slug = self::slugify($slug !== '' ? $slug : $nameEn);
        Db::pdo()->prepare(
            'INSERT INTO interview_skills (slug, name_en, name_de, kind, enabled)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_de = VALUES(name_de), kind = VALUES(kind), enabled = VALUES(enabled)'
        )->execute([$slug, $nameEn, $nameDe, $kind, $enabled ? 1 : 0]);
        self::clearCache();
        return self::idBySlug('interview_skills', $slug);
    }

    public static function setEnabled(string $table, int $id, bool $enabled): void
    {
        $allowed = [
            'interview_industries', 'interview_occupations', 'interview_specializations',
            'interview_skills', 'interview_technologies', 'interview_stages',
        ];
        if (!in_array($table, $allowed, true) || $id < 1) {
            return;
        }
        Db::pdo()->prepare("UPDATE {$table} SET enabled = ? WHERE id = ?")->execute([$enabled ? 1 : 0, $id]);
        self::clearCache();
    }

    /**
     * Map free-text terms to skill/occupation/industry slugs.
     *
     * @param list<string> $terms
     * @return array{skill_ids: list<int>, occupation_ids: list<int>, industry_ids: list<int>}
     */
    public static function resolveTerms(array $terms): array
    {
        InterviewSchema::ensureSchema();
        $skills = self::skills();
        $occupations = self::occupations();
        $industries = self::industries();
        $skillIds = [];
        $occIds = [];
        $indIds = [];

        $foldedTerms = [];
        foreach ($terms as $t) {
            $f = \KaamFit\Jobs\JobText::foldMatch((string) $t);
            if ($f !== '') {
                $foldedTerms[] = $f;
            }
        }

        foreach ($skills as $row) {
            $hay = \KaamFit\Jobs\JobText::foldMatch((string) $row['slug'] . ' ' . $row['name_en'] . ' ' . $row['name_de']);
            foreach ($foldedTerms as $t) {
                if ($t !== '' && (str_contains($hay, $t) || str_contains($t, $hay))) {
                    $skillIds[] = (int) $row['id'];
                    break;
                }
            }
        }
        foreach ($occupations as $row) {
            $hay = \KaamFit\Jobs\JobText::foldMatch((string) $row['slug'] . ' ' . $row['name_en'] . ' ' . $row['name_de']);
            foreach ($foldedTerms as $t) {
                if ($t !== '' && (str_contains($hay, $t) || str_contains($t, $hay))) {
                    $occIds[] = (int) $row['id'];
                    break;
                }
            }
        }
        foreach ($industries as $row) {
            $hay = \KaamFit\Jobs\JobText::foldMatch((string) $row['slug'] . ' ' . $row['name_en'] . ' ' . $row['name_de']);
            foreach ($foldedTerms as $t) {
                if ($t !== '' && (str_contains($hay, $t) || str_contains($t, $hay))) {
                    $indIds[] = (int) $row['id'];
                    break;
                }
            }
        }

        return [
            'skill_ids' => array_values(array_unique($skillIds)),
            'occupation_ids' => array_values(array_unique($occIds)),
            'industry_ids' => array_values(array_unique($indIds)),
        ];
    }
}
