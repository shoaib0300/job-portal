<?php

declare(strict_types=1);

namespace KaamFit\Interview;

use Db;

/**
 * Interview Prep schema: universal taxonomy + questions + personal library + reviews.
 * Occupations live in MySQL — never hard-coded PHP enums of jobs.
 */
final class InterviewSchema
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        self::$ready = true;

        $pdo = Db::pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_industries (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              slug VARCHAR(80) NOT NULL,
              name_en VARCHAR(160) NOT NULL,
              name_de VARCHAR(160) NOT NULL DEFAULT \'\',
              sort_order INT NOT NULL DEFAULT 0,
              enabled TINYINT(1) NOT NULL DEFAULT 1,
              UNIQUE KEY uq_industry_slug (slug),
              KEY idx_industry_enabled (enabled, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_occupations (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              industry_id INT UNSIGNED NOT NULL,
              slug VARCHAR(100) NOT NULL,
              name_en VARCHAR(160) NOT NULL,
              name_de VARCHAR(160) NOT NULL DEFAULT \'\',
              sort_order INT NOT NULL DEFAULT 0,
              enabled TINYINT(1) NOT NULL DEFAULT 1,
              UNIQUE KEY uq_occ_slug (slug),
              KEY idx_occ_industry (industry_id, enabled, sort_order),
              CONSTRAINT fk_occ_industry FOREIGN KEY (industry_id) REFERENCES interview_industries(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_specializations (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              occupation_id INT UNSIGNED NOT NULL,
              slug VARCHAR(100) NOT NULL,
              name_en VARCHAR(160) NOT NULL,
              name_de VARCHAR(160) NOT NULL DEFAULT \'\',
              sort_order INT NOT NULL DEFAULT 0,
              enabled TINYINT(1) NOT NULL DEFAULT 1,
              UNIQUE KEY uq_spec_slug (slug),
              KEY idx_spec_occ (occupation_id, enabled),
              CONSTRAINT fk_spec_occ FOREIGN KEY (occupation_id) REFERENCES interview_occupations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_skills (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              slug VARCHAR(100) NOT NULL,
              name_en VARCHAR(160) NOT NULL,
              name_de VARCHAR(160) NOT NULL DEFAULT \'\',
              kind VARCHAR(32) NOT NULL DEFAULT \'universal\',
              enabled TINYINT(1) NOT NULL DEFAULT 1,
              UNIQUE KEY uq_skill_slug (slug),
              KEY idx_skill_kind (kind, enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_technologies (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              slug VARCHAR(100) NOT NULL,
              name_en VARCHAR(160) NOT NULL,
              name_de VARCHAR(160) NOT NULL DEFAULT \'\',
              enabled TINYINT(1) NOT NULL DEFAULT 1,
              UNIQUE KEY uq_tech_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_stages (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              slug VARCHAR(80) NOT NULL,
              name_en VARCHAR(120) NOT NULL,
              name_de VARCHAR(120) NOT NULL DEFAULT \'\',
              sort_order INT NOT NULL DEFAULT 0,
              enabled TINYINT(1) NOT NULL DEFAULT 1,
              UNIQUE KEY uq_stage_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_questions (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              slug VARCHAR(160) NOT NULL,
              language VARCHAR(8) NOT NULL DEFAULT \'en\',
              question_text TEXT NOT NULL,
              why_asked TEXT NULL,
              strong_answer_covers TEXT NULL,
              example_answer TEXT NULL,
              short_answer TEXT NULL,
              detailed_answer TEXT NULL,
              examples TEXT NULL,
              answer_framework TEXT NULL,
              common_mistakes TEXT NULL,
              follow_ups TEXT NULL,
              related_concepts TEXT NULL,
              question_type VARCHAR(40) NOT NULL DEFAULT \'general\',
              difficulty VARCHAR(20) NOT NULL DEFAULT \'medium\',
              career_level VARCHAR(40) NULL,
              category VARCHAR(60) NOT NULL DEFAULT \'\',
              is_universal TINYINT(1) NOT NULL DEFAULT 0,
              visibility VARCHAR(20) NOT NULL DEFAULT \'universal\',
              owner_user_id INT UNSIGNED NULL,
              review_status VARCHAR(20) NOT NULL DEFAULT \'none\',
              source_type VARCHAR(40) NOT NULL DEFAULT \'kaamfit_original\',
              source_name VARCHAR(160) NOT NULL DEFAULT \'KaamFit\',
              source_url VARCHAR(500) NOT NULL DEFAULT \'\',
              license VARCHAR(80) NOT NULL DEFAULT \'proprietary\',
              attribution VARCHAR(255) NOT NULL DEFAULT \'\',
              status VARCHAR(20) NOT NULL DEFAULT \'published\',
              diagram_path VARCHAR(255) NOT NULL DEFAULT \'\',
              diagram_alt VARCHAR(255) NOT NULL DEFAULT \'\',
              diagram_caption VARCHAR(255) NOT NULL DEFAULT \'\',
              diagram_license VARCHAR(80) NOT NULL DEFAULT \'\',
              diagram_source_url VARCHAR(500) NOT NULL DEFAULT \'\',
              content_hash CHAR(40) NOT NULL DEFAULT \'\',
              normalized_text VARCHAR(500) NOT NULL DEFAULT \'\',
              created_by INT UNSIGNED NULL,
              updated_by INT UNSIGNED NULL,
              approved_by INT UNSIGNED NULL,
              approved_at TIMESTAMP NULL DEFAULT NULL,
              archived_by INT UNSIGNED NULL,
              archived_at TIMESTAMP NULL DEFAULT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY uq_q_slug_lang (slug, language),
              KEY idx_q_status_lang (status, language),
              KEY idx_q_type (question_type),
              KEY idx_q_diff (difficulty),
              KEY idx_q_level (career_level),
              KEY idx_q_universal (is_universal),
              KEY idx_q_visibility (visibility, status),
              KEY idx_q_owner (owner_user_id),
              KEY idx_q_review (review_status, status),
              KEY idx_q_hash (content_hash),
              KEY idx_q_norm (normalized_text(191)),
              FULLTEXT KEY ft_q_text (question_text, why_asked, example_answer, source_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        self::migrateQuestionColumns($pdo);

        self::createJunction($pdo, 'interview_question_industries', 'industry_id', 'interview_industries');
        self::createJunction($pdo, 'interview_question_occupations', 'occupation_id', 'interview_occupations');
        self::createJunction($pdo, 'interview_question_specializations', 'specialization_id', 'interview_specializations');
        self::createJunction($pdo, 'interview_question_skills', 'skill_id', 'interview_skills');
        self::createJunction($pdo, 'interview_question_technologies', 'technology_id', 'interview_technologies');
        self::createJunction($pdo, 'interview_question_stages', 'stage_id', 'interview_stages');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_question_levels (
              question_id INT UNSIGNED NOT NULL,
              career_level VARCHAR(40) NOT NULL,
              PRIMARY KEY (question_id, career_level),
              CONSTRAINT fk_ql_q FOREIGN KEY (question_id) REFERENCES interview_questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_user_progress (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED NOT NULL,
              question_id INT UNSIGNED NOT NULL,
              viewed TINYINT(1) NOT NULL DEFAULT 0,
              practiced TINYINT(1) NOT NULL DEFAULT 0,
              confident TINYINT(1) NOT NULL DEFAULT 0,
              favorite TINYINT(1) NOT NULL DEFAULT 0,
              confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
              attempts INT UNSIGNED NOT NULL DEFAULT 0,
              notes TEXT NULL,
              personal_answer TEXT NULL,
              last_practiced_at TIMESTAMP NULL DEFAULT NULL,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY uq_user_q (user_id, question_id),
              KEY idx_prog_user (user_id, practiced, favorite),
              CONSTRAINT fk_prog_q FOREIGN KEY (question_id) REFERENCES interview_questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_interview_questions (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED NOT NULL,
              question_id INT UNSIGNED NOT NULL,
              origin VARCHAR(32) NOT NULL DEFAULT \'linked\',
              imported_answer TEXT NULL,
              import_batch_id VARCHAR(40) NOT NULL DEFAULT \'\',
              source_filename VARCHAR(255) NOT NULL DEFAULT \'\',
              source_type VARCHAR(40) NOT NULL DEFAULT \'user_upload\',
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_uiq (user_id, question_id),
              KEY idx_uiq_user (user_id, created_at),
              KEY idx_uiq_batch (import_batch_id),
              CONSTRAINT fk_uiq_q FOREIGN KEY (question_id) REFERENCES interview_questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_content_reviews (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              question_id INT UNSIGNED NOT NULL,
              submitted_by INT UNSIGNED NOT NULL,
              status VARCHAR(20) NOT NULL DEFAULT \'pending\',
              possible_match_id INT UNSIGNED NULL,
              match_score DECIMAL(5,2) NULL,
              admin_notes TEXT NULL,
              reviewed_by INT UNSIGNED NULL,
              reviewed_at TIMESTAMP NULL DEFAULT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY idx_icr_status (status, created_at),
              KEY idx_icr_q (question_id),
              CONSTRAINT fk_icr_q FOREIGN KEY (question_id) REFERENCES interview_questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS interview_question_sources (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              question_id INT UNSIGNED NOT NULL,
              source_type VARCHAR(40) NOT NULL DEFAULT \'\',
              source_name VARCHAR(160) NOT NULL DEFAULT \'\',
              source_url VARCHAR(500) NOT NULL DEFAULT \'\',
              license VARCHAR(80) NOT NULL DEFAULT \'\',
              attribution VARCHAR(255) NOT NULL DEFAULT \'\',
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_iqs_q (question_id),
              CONSTRAINT fk_iqs_q FOREIGN KEY (question_id) REFERENCES interview_questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        self::seedStages($pdo);
        self::seedTaxonomyFromFile($pdo);
        self::backfillNormalized($pdo);
    }

    private static function migrateQuestionColumns(\PDO $pdo): void
    {
        $cols = [
            'visibility' => "VARCHAR(20) NOT NULL DEFAULT 'universal'",
            'owner_user_id' => 'INT UNSIGNED NULL',
            'review_status' => "VARCHAR(20) NOT NULL DEFAULT 'none'",
            'short_answer' => 'TEXT NULL',
            'detailed_answer' => 'TEXT NULL',
            'examples' => 'TEXT NULL',
            'diagram_caption' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'diagram_license' => "VARCHAR(80) NOT NULL DEFAULT ''",
            'diagram_source_url' => "VARCHAR(500) NOT NULL DEFAULT ''",
            'normalized_text' => "VARCHAR(500) NOT NULL DEFAULT ''",
            'created_by' => 'INT UNSIGNED NULL',
            'updated_by' => 'INT UNSIGNED NULL',
            'approved_by' => 'INT UNSIGNED NULL',
            'approved_at' => 'TIMESTAMP NULL DEFAULT NULL',
            'archived_by' => 'INT UNSIGNED NULL',
            'archived_at' => 'TIMESTAMP NULL DEFAULT NULL',
        ];
        foreach ($cols as $name => $def) {
            try {
                $pdo->exec("ALTER TABLE interview_questions ADD COLUMN {$name} {$def}");
            } catch (\Throwable) {
                // already exists
            }
        }
        try {
            $pdo->exec('ALTER TABLE interview_questions ADD KEY idx_q_visibility (visibility, status)');
        } catch (\Throwable) {
        }
        try {
            $pdo->exec('ALTER TABLE interview_questions ADD KEY idx_q_owner (owner_user_id)');
        } catch (\Throwable) {
        }
        try {
            $pdo->exec('ALTER TABLE interview_questions ADD KEY idx_q_review (review_status, status)');
        } catch (\Throwable) {
        }
        try {
            $pdo->exec('ALTER TABLE interview_questions ADD KEY idx_q_norm (normalized_text(191))');
        } catch (\Throwable) {
        }
        // Sync visibility from legacy is_universal for rows still defaulted oddly
        try {
            $pdo->exec(
                "UPDATE interview_questions SET visibility = 'universal', is_universal = 1
                 WHERE (visibility = '' OR visibility IS NULL) AND status = 'published'"
            );
            $pdo->exec(
                "UPDATE interview_questions SET visibility = 'universal'
                 WHERE is_universal = 1 AND (visibility = '' OR visibility = 'personal')"
            );
        } catch (\Throwable) {
        }
    }

    private static function backfillNormalized(\PDO $pdo): void
    {
        try {
            $stmt = $pdo->query(
                "SELECT id, question_text FROM interview_questions
                 WHERE normalized_text = '' OR normalized_text IS NULL LIMIT 500"
            );
            if (!$stmt) {
                return;
            }
            $upd = $pdo->prepare('UPDATE interview_questions SET normalized_text = ? WHERE id = ?');
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $upd->execute([
                    InterviewDuplicateDetector::normalize((string) $row['question_text']),
                    (int) $row['id'],
                ]);
            }
        } catch (\Throwable) {
        }
    }

    private static function createJunction(\PDO $pdo, string $table, string $fkCol, string $refTable): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$table} (
              question_id INT UNSIGNED NOT NULL,
              {$fkCol} INT UNSIGNED NOT NULL,
              PRIMARY KEY (question_id, {$fkCol}),
              KEY idx_{$fkCol} ({$fkCol}),
              CONSTRAINT fk_{$table}_q FOREIGN KEY (question_id) REFERENCES interview_questions(id) ON DELETE CASCADE,
              CONSTRAINT fk_{$table}_t FOREIGN KEY ({$fkCol}) REFERENCES {$refTable}(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private static function seedStages(\PDO $pdo): void
    {
        $stages = [
            ['recruiter_screen', 'Recruiter screen', 'Recruiter-Screening', 5],
            ['hr_screen', 'HR interview', 'HR-Interview', 10],
            ['phone_screen', 'Phone screen', 'Telefoninterview', 20],
            ['technical_screen', 'Technical screen', 'Fachliches Screening', 25],
            ['technical', 'Technical interview', 'Fachinterview', 30],
            ['hiring_manager', 'Hiring manager', 'Fachvorgesetzte', 40],
            ['team_interview', 'Team interview', 'Teaminterview', 45],
            ['case_study', 'Case study', 'Fallstudie', 50],
            ['assessment', 'Assessment', 'Assessment', 55],
            ['coding_interview', 'Coding interview', 'Coding-Interview', 58],
            ['final_interview', 'Final interview', 'Abschlussgespräch', 60],
            ['panel', 'Panel', 'Panel', 70],
        ];
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO interview_stages (slug, name_en, name_de, sort_order, enabled)
             VALUES (?, ?, ?, ?, 1)'
        );
        foreach ($stages as $s) {
            $ins->execute($s);
        }
    }

    private static function seedTaxonomyFromFile(\PDO $pdo): void
    {
        $path = dirname(__DIR__, 2) . '/data/interview/v1/taxonomy.json';
        if (!is_readable($path)) {
            return;
        }
        $count = (int) $pdo->query('SELECT COUNT(*) FROM interview_industries')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return;
        }
        InterviewTaxonomy::importTaxonomyArray($data);
    }

    /** @return list<string> */
    public static function questionTypes(): array
    {
        return [
            'general', 'behavioral', 'situational', 'role_specific', 'industry_specific',
            'technical', 'case_study', 'practical', 'leadership', 'regulatory',
            'safety', 'portfolio',
        ];
    }

    /** @return list<string> */
    public static function difficulties(): array
    {
        return ['easy', 'medium', 'hard'];
    }

    /** @return list<string> */
    public static function careerLevels(): array
    {
        return [
            'internship', 'working_student', 'apprentice', 'graduate', 'entry',
            'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive',
        ];
    }

    /** @return list<string> */
    public static function sourceTypes(): array
    {
        return [
            'kaamfit_original', 'user_upload', 'user_paste', 'user_url',
            'licensed_dataset', 'public_domain', 'approved_user_submission',
            'research_reference', 'ai_assisted',
        ];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return ['published', 'draft', 'review', 'archived', 'rejected'];
    }

    /** @return list<string> */
    public static function visibilities(): array
    {
        return ['universal', 'personal'];
    }

    /** @return list<string> */
    public static function reviewStatuses(): array
    {
        return ['none', 'pending', 'approved', 'rejected', 'merged'];
    }
}
