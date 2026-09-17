<?php

declare(strict_types=1);

namespace KaamFit\Interview;

use Db;

final class InterviewQuestionRepo
{
    /**
     * @param array{
     *   q?:string, language?:string, industry?:string, occupation?:string,
     *   specialization?:string, type?:string, difficulty?:string, level?:string,
     *   skill?:string, technology?:string, stage?:string, status?:string, universal?:bool,
     *   visibility?:string, review_status?:string, source_type?:string,
     *   has_answer?:string, owner_user_id?:int, user_library?:int,
     *   for_user?:int, favorite?:bool|int|string, practiced?:bool|int|string,
     *   ids?:list<int>, page?:int, per_page?:int,
     *   category?:string, admin?:bool
     * } $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function search(array $filters = []): array
    {
        InterviewSchema::ensureSchema();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 24)));
        $offset = ($page - 1) * $perPage;
        $admin = !empty($filters['admin']);

        $where = ['1=1'];
        $params = [];
        $joins = [];

        $status = (string) ($filters['status'] ?? ($admin ? 'any' : 'published'));
        $forUser = (int) ($filters['for_user'] ?? 0);
        // When scoping to a user library+universal, don't force status=published alone
        if (!$admin && $forUser > 0 && !isset($filters['status'])) {
            $status = 'any';
        }
        if ($status !== '' && $status !== 'any') {
            $where[] = 'q.status = ?';
            $params[] = $status;
        }

        $visibility = trim((string) ($filters['visibility'] ?? ''));
        if ($visibility !== '' && $visibility !== 'any') {
            $where[] = 'q.visibility = ?';
            $params[] = $visibility;
        }

        // Normal users: published universal OR own personal / library
        if (!$admin && $forUser > 0) {
            $joins['uiq'] = 'LEFT JOIN user_interview_questions uiq ON uiq.question_id = q.id AND uiq.user_id = ' . $forUser;
            $where[] = "(
                (q.visibility = 'universal' AND q.status = 'published')
                OR (q.owner_user_id = ? AND q.status IN ('review','published','rejected'))
                OR uiq.id IS NOT NULL
            )";
            $params[] = $forUser;
        } elseif (!$admin) {
            $where[] = "q.visibility = 'universal'";
            if ($status === '' || $status === 'any') {
                $where[] = "q.status = 'published'";
            }
        }

        $lang = trim((string) ($filters['language'] ?? ''));
        if ($lang !== '') {
            $where[] = 'q.language = ?';
            $params[] = $lang;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'q.question_type = ?';
            $params[] = $type;
        }

        $difficulty = trim((string) ($filters['difficulty'] ?? ''));
        if ($difficulty !== '') {
            $where[] = 'q.difficulty = ?';
            $params[] = $difficulty;
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where[] = 'q.category = ?';
            $params[] = $category;
        }

        $reviewStatus = trim((string) ($filters['review_status'] ?? ''));
        if ($reviewStatus !== '' && $reviewStatus !== 'any') {
            $where[] = 'q.review_status = ?';
            $params[] = $reviewStatus;
        }

        $sourceType = trim((string) ($filters['source_type'] ?? ''));
        if ($sourceType !== '') {
            $where[] = 'q.source_type = ?';
            $params[] = $sourceType;
        }

        $hasAnswer = trim((string) ($filters['has_answer'] ?? ''));
        if ($hasAnswer === '1' || $hasAnswer === 'yes') {
            $where[] = "(q.example_answer IS NOT NULL AND q.example_answer != '' AND q.example_answer != 'null')";
        } elseif ($hasAnswer === '0' || $hasAnswer === 'no') {
            $where[] = "(q.example_answer IS NULL OR q.example_answer = '' OR q.example_answer = 'null')";
        }

        $owner = (int) ($filters['owner_user_id'] ?? 0);
        if ($owner > 0) {
            $where[] = 'q.owner_user_id = ?';
            $params[] = $owner;
        }

        $level = trim((string) ($filters['level'] ?? ''));
        if ($level !== '') {
            $joins['ql'] = 'LEFT JOIN interview_question_levels ql ON ql.question_id = q.id';
            $where[] = '(q.career_level = ? OR ql.career_level = ? OR q.career_level IS NULL OR q.career_level = \'\')';
            $params[] = $level;
            $params[] = $level;
        }

        if (!empty($filters['universal'])) {
            $where[] = "q.visibility = 'universal'";
            $where[] = 'q.is_universal = 1';
        }

        $industry = trim((string) ($filters['industry'] ?? ''));
        if ($industry !== '') {
            $joins['qi'] = 'INNER JOIN interview_question_industries qi ON qi.question_id = q.id
                INNER JOIN interview_industries ind ON ind.id = qi.industry_id';
            $where[] = 'ind.slug = ?';
            $params[] = $industry;
        }

        $occupation = trim((string) ($filters['occupation'] ?? ''));
        if ($occupation !== '') {
            $joins['qo'] = 'INNER JOIN interview_question_occupations qo ON qo.question_id = q.id
                INNER JOIN interview_occupations occ ON occ.id = qo.occupation_id';
            $where[] = 'occ.slug = ?';
            $params[] = $occupation;
        }

        $spec = trim((string) ($filters['specialization'] ?? ''));
        if ($spec !== '') {
            $joins['qs'] = 'INNER JOIN interview_question_specializations qs ON qs.question_id = q.id
                INNER JOIN interview_specializations sp ON sp.id = qs.specialization_id';
            $where[] = 'sp.slug = ?';
            $params[] = $spec;
        }

        $skill = trim((string) ($filters['skill'] ?? ''));
        if ($skill !== '') {
            $joins['qsk'] = 'INNER JOIN interview_question_skills qsk ON qsk.question_id = q.id
                INNER JOIN interview_skills sk ON sk.id = qsk.skill_id';
            $where[] = 'sk.slug = ?';
            $params[] = $skill;
        }

        $technology = trim((string) ($filters['technology'] ?? ''));
        if ($technology !== '') {
            $joins['qtech'] = 'INNER JOIN interview_question_technologies qtech ON qtech.question_id = q.id
                INNER JOIN interview_technologies tech ON tech.id = qtech.technology_id';
            $where[] = 'tech.slug = ?';
            $params[] = $technology;
        }

        $stage = trim((string) ($filters['stage'] ?? ''));
        if ($stage !== '') {
            $joins['qst'] = 'INNER JOIN interview_question_stages qst ON qst.question_id = q.id
                INNER JOIN interview_stages st ON st.id = qst.stage_id';
            $where[] = 'st.slug = ?';
            $params[] = $stage;
        }

        $libraryUser = (int) ($filters['user_library'] ?? 0);
        if ($libraryUser > 0) {
            $joins['uiq2'] = 'INNER JOIN user_interview_questions uiq2 ON uiq2.question_id = q.id';
            $where[] = 'uiq2.user_id = ?';
            $params[] = $libraryUser;
        }

        $progressUser = $forUser > 0 ? $forUser : (int) ($filters['progress_user'] ?? 0);
        $wantFavorite = self::truthyFilter($filters['favorite'] ?? null);
        $wantPracticed = self::truthyFilter($filters['practiced'] ?? null);
        if (($wantFavorite || $wantPracticed) && $progressUser > 0) {
            $joins['prog'] = 'INNER JOIN interview_user_progress prog ON prog.question_id = q.id AND prog.user_id = ' . $progressUser;
            if ($wantFavorite) {
                $where[] = 'prog.favorite = 1';
            }
            if ($wantPracticed) {
                $where[] = 'prog.practiced = 1';
            }
        }

        $ids = $filters['ids'] ?? null;
        if (is_array($ids) && $ids !== []) {
            $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $i): bool => $i > 0));
            if ($ids !== []) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $where[] = "q.id IN ({$ph})";
                foreach ($ids as $id) {
                    $params[] = $id;
                }
            }
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(MATCH(q.question_text) AGAINST (? IN BOOLEAN MODE)
                OR q.question_text LIKE ?
                OR q.why_asked LIKE ?
                OR q.example_answer LIKE ?
                OR q.short_answer LIKE ?
                OR q.detailed_answer LIKE ?
                OR q.category LIKE ?
                OR q.source_name LIKE ?
                OR q.related_concepts LIKE ?
                OR q.strong_answer_covers LIKE ?)';
            $words = preg_split('/\s+/u', $q) ?: [];
            $bool = '+' . implode('* +', array_filter($words)) . '*';
            $params[] = $bool;
            for ($i = 0; $i < 9; $i++) {
                $params[] = $like;
            }
        }

        $joinSql = $joins !== [] ? implode("\n", $joins) : '';
        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(DISTINCT q.id) FROM interview_questions q {$joinSql} WHERE {$whereSql}";
        $cstmt = Db::pdo()->prepare($countSql);
        $cstmt->execute($params);
        $total = (int) $cstmt->fetchColumn();

        $order = $admin ? 'q.updated_at DESC, q.id DESC' : 'q.is_universal DESC, q.id ASC';
        $sql = "SELECT DISTINCT q.* FROM interview_questions q {$joinSql}
                WHERE {$whereSql}
                ORDER BY {$order}
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'items' => array_map([self::class, 'decodeRow'], $items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        InterviewSchema::ensureSchema();
        if ($id < 1) {
            return null;
        }
        $stmt = Db::pdo()->prepare('SELECT * FROM interview_questions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $out = self::decodeRow($row);
        $out['tags'] = self::tagsForQuestion($id);
        return $out;
    }

    /** Whether a normal user may view this question. */
    public static function userCanView(int $userId, array $question): bool
    {
        if (($question['visibility'] ?? '') === 'universal' && ($question['status'] ?? '') === 'published') {
            return true;
        }
        if ($userId > 0 && (int) ($question['owner_user_id'] ?? 0) === $userId) {
            return true;
        }
        if ($userId > 0 && InterviewLibrary::isLinked($userId, (int) ($question['id'] ?? 0))) {
            return true;
        }
        return false;
    }

    /**
     * @return array{
     *   industries: list<array<string,mixed>>,
     *   occupations: list<array<string,mixed>>,
     *   specializations: list<array<string,mixed>>,
     *   skills: list<array<string,mixed>>,
     *   technologies: list<array<string,mixed>>,
     *   stages: list<array<string,mixed>>,
     *   levels: list<string>
     * }
     */
    public static function tagsForQuestion(int $questionId): array
    {
        $pdo = Db::pdo();
        $fetch = static function (string $sql) use ($pdo, $questionId): array {
            $st = $pdo->prepare($sql);
            $st->execute([$questionId]);
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        };
        $levels = $pdo->prepare('SELECT career_level FROM interview_question_levels WHERE question_id = ?');
        $levels->execute([$questionId]);
        return [
            'industries' => $fetch(
                'SELECT i.* FROM interview_industries i
                 INNER JOIN interview_question_industries j ON j.industry_id = i.id WHERE j.question_id = ?'
            ),
            'occupations' => $fetch(
                'SELECT o.* FROM interview_occupations o
                 INNER JOIN interview_question_occupations j ON j.occupation_id = o.id WHERE j.question_id = ?'
            ),
            'specializations' => $fetch(
                'SELECT s.* FROM interview_specializations s
                 INNER JOIN interview_question_specializations j ON j.specialization_id = s.id WHERE j.question_id = ?'
            ),
            'skills' => $fetch(
                'SELECT s.* FROM interview_skills s
                 INNER JOIN interview_question_skills j ON j.skill_id = s.id WHERE j.question_id = ?'
            ),
            'technologies' => $fetch(
                'SELECT t.* FROM interview_technologies t
                 INNER JOIN interview_question_technologies j ON j.technology_id = t.id WHERE j.question_id = ?'
            ),
            'stages' => $fetch(
                'SELECT s.* FROM interview_stages s
                 INNER JOIN interview_question_stages j ON j.stage_id = s.id WHERE j.question_id = ?'
            ),
            'levels' => array_column($levels->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'career_level'),
        ];
    }

    /** @return array{linked_users:int,practiced:int,favorites:int} */
    public static function usageStats(int $questionId): array
    {
        InterviewSchema::ensureSchema();
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT COUNT(*) FROM user_interview_questions WHERE question_id = ?');
        $st->execute([$questionId]);
        $linked = (int) $st->fetchColumn();
        $st2 = $pdo->prepare(
            'SELECT SUM(practiced = 1) AS practiced, SUM(favorite = 1) AS favorites
             FROM interview_user_progress WHERE question_id = ?'
        );
        $st2->execute([$questionId]);
        $row = $st2->fetch(\PDO::FETCH_ASSOC) ?: [];
        return [
            'linked_users' => $linked,
            'practiced' => (int) ($row['practiced'] ?? 0),
            'favorites' => (int) ($row['favorites'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array{
     *   industries?:list<string>, occupations?:list<string>, specializations?:list<string>,
     *   skills?:list<string>, technologies?:list<string>, stages?:list<string>, levels?:list<string>
     * } $tags
     */
    public static function upsert(array $data, array $tags = [], bool $allowUpdate = true): array
    {
        InterviewSchema::ensureSchema();
        $slug = InterviewTaxonomy::slugify((string) ($data['slug'] ?? substr((string) ($data['question'] ?? $data['question_text'] ?? ''), 0, 80)));
        $lang = strtolower(trim((string) ($data['language'] ?? 'en')));
        if ($lang === '') {
            $lang = 'en';
        }
        $text = trim((string) ($data['question'] ?? $data['question_text'] ?? ''));
        if ($text === '' || $slug === '') {
            throw new \InvalidArgumentException('question_text and slug are required');
        }

        $hash = InterviewDuplicateDetector::contentHash($lang, $text);
        $norm = InterviewDuplicateDetector::normalize($text);
        $existing = Db::pdo()->prepare('SELECT id FROM interview_questions WHERE slug = ? AND language = ? LIMIT 1');
        $existing->execute([$slug, $lang]);
        $id = (int) ($existing->fetchColumn() ?: 0);
        if (!empty($data['id'])) {
            $id = (int) $data['id'];
        }

        $encode = static function (mixed $v): ?string {
            if ($v === null || $v === '') {
                return null;
            }
            if (is_array($v)) {
                return json_encode(array_values($v), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
            }
            return (string) $v;
        };

        $visibility = (string) ($data['visibility'] ?? (!empty($data['is_universal']) ? 'universal' : 'personal'));
        if (!in_array($visibility, InterviewSchema::visibilities(), true)) {
            $visibility = 'universal';
        }
        $isUniversal = $visibility === 'universal' ? 1 : (!empty($data['is_universal']) ? 1 : 0);
        if ($visibility === 'universal') {
            $isUniversal = 1;
        }

        $fields = [
            'slug' => $slug,
            'language' => $lang,
            'question_text' => $text,
            'why_asked' => $encode($data['why_asked'] ?? null),
            'strong_answer_covers' => $encode($data['strong_answer_covers'] ?? $data['what_strong_answer_covers'] ?? null),
            'example_answer' => $encode($data['example_answer'] ?? null),
            'short_answer' => $encode($data['short_answer'] ?? null),
            'detailed_answer' => $encode($data['detailed_answer'] ?? null),
            'examples' => $encode($data['examples'] ?? null),
            'answer_framework' => $encode($data['answer_framework'] ?? null),
            'common_mistakes' => $encode($data['common_mistakes'] ?? null),
            'follow_ups' => $encode($data['follow_ups'] ?? null),
            'related_concepts' => $encode($data['related_concepts'] ?? null),
            'question_type' => (string) ($data['question_type'] ?? 'general'),
            'difficulty' => (string) ($data['difficulty'] ?? 'medium'),
            'career_level' => ($data['level'] ?? $data['career_level'] ?? null) !== null && (string) ($data['level'] ?? $data['career_level']) !== ''
                ? (string) ($data['level'] ?? $data['career_level'])
                : null,
            'category' => (string) ($data['category'] ?? ''),
            'is_universal' => $isUniversal,
            'visibility' => $visibility,
            'owner_user_id' => isset($data['owner_user_id']) && $data['owner_user_id'] !== null && (int) $data['owner_user_id'] > 0
                ? (int) $data['owner_user_id']
                : null,
            'review_status' => (string) ($data['review_status'] ?? 'none'),
            'source_type' => (string) ($data['source_type'] ?? 'kaamfit_original'),
            'source_name' => (string) ($data['source_name'] ?? 'KaamFit'),
            'source_url' => (string) ($data['source_url'] ?? ''),
            'license' => (string) ($data['license'] ?? 'proprietary'),
            'attribution' => (string) ($data['attribution'] ?? ''),
            'status' => (string) ($data['status'] ?? 'published'),
            'diagram_path' => (string) ($data['diagram_path'] ?? ''),
            'diagram_alt' => (string) ($data['diagram_alt'] ?? ''),
            'diagram_caption' => (string) ($data['diagram_caption'] ?? ''),
            'diagram_license' => (string) ($data['diagram_license'] ?? ''),
            'diagram_source_url' => (string) ($data['diagram_source_url'] ?? ''),
            'content_hash' => $hash,
            'normalized_text' => $norm,
        ];

        if (!empty($data['created_by'])) {
            $fields['created_by'] = (int) $data['created_by'];
        }
        if (!empty($data['updated_by'])) {
            $fields['updated_by'] = (int) $data['updated_by'];
        }
        if (!empty($data['approved_by'])) {
            $fields['approved_by'] = (int) $data['approved_by'];
        }

        if ($id > 0 && !$allowUpdate) {
            return ['id' => $id, 'action' => 'skipped_duplicate'];
        }

        if ($id > 0) {
            $sets = [];
            $params = [];
            foreach ($fields as $k => $v) {
                if ($k === 'slug' || $k === 'language' || $k === 'created_by') {
                    continue;
                }
                $sets[] = "`{$k}` = ?";
                $params[] = $v;
            }
            $params[] = $id;
            Db::pdo()->prepare('UPDATE interview_questions SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
            $action = 'updated';
        } else {
            $cols = array_keys($fields);
            $ph = implode(',', array_fill(0, count($cols), '?'));
            Db::pdo()->prepare(
                'INSERT INTO interview_questions (`' . implode('`,`', $cols) . '`) VALUES (' . $ph . ')'
            )->execute(array_values($fields));
            $id = (int) Db::pdo()->lastInsertId();
            $action = 'created';
        }

        self::syncTags($id, array_merge([
            'industries' => $data['industries'] ?? ($tags['industries'] ?? []),
            'occupations' => $data['occupations'] ?? ($tags['occupations'] ?? []),
            'specializations' => $data['specializations'] ?? ($tags['specializations'] ?? []),
            'skills' => $data['skills'] ?? ($tags['skills'] ?? []),
            'technologies' => $data['technologies'] ?? ($tags['technologies'] ?? []),
            'stages' => $data['interview_stages'] ?? ($data['stages'] ?? ($tags['stages'] ?? [])),
            'levels' => $data['levels'] ?? ($tags['levels'] ?? []),
        ], $tags));

        return ['id' => $id, 'action' => $action];
    }

    public static function archive(int $id, int $adminId): void
    {
        InterviewSchema::ensureSchema();
        Db::pdo()->prepare(
            "UPDATE interview_questions SET status = 'archived', archived_by = ?, archived_at = NOW(), updated_by = ? WHERE id = ?"
        )->execute([$adminId, $adminId, $id]);
    }

    public static function restore(int $id, int $adminId): void
    {
        InterviewSchema::ensureSchema();
        Db::pdo()->prepare(
            "UPDATE interview_questions SET status = 'published', archived_by = NULL, archived_at = NULL, updated_by = ? WHERE id = ?"
        )->execute([$adminId, $id]);
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $fields
     */
    public static function bulkUpdate(array $ids, array $fields, int $adminId): int
    {
        InterviewSchema::ensureSchema();
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $i): bool => $i > 0));
        if ($ids === []) {
            return 0;
        }
        $allowed = ['status', 'visibility', 'difficulty', 'language', 'question_type', 'review_status'];
        $sets = ['updated_by = ?'];
        $params = [$adminId];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields) || $fields[$col] === '' || $fields[$col] === null) {
                continue;
            }
            $sets[] = "`{$col}` = ?";
            $params[] = $fields[$col];
            if ($col === 'visibility' && $fields[$col] === 'universal') {
                $sets[] = 'is_universal = 1';
            }
            if ($col === 'review_status' && $fields[$col] === 'approved') {
                $sets[] = 'approved_by = ?';
                $params[] = $adminId;
                $sets[] = 'approved_at = NOW()';
            }
            if ($col === 'status' && $fields[$col] === 'archived') {
                $sets[] = 'archived_by = ?';
                $params[] = $adminId;
                $sets[] = 'archived_at = NOW()';
            }
        }
        if (count($sets) <= 1) {
            return 0;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        foreach ($ids as $id) {
            $params[] = $id;
        }
        $sql = 'UPDATE interview_questions SET ' . implode(', ', $sets) . " WHERE id IN ({$ph})";
        $st = Db::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /**
     * @param array{
     *   industries?:list<string>, occupations?:list<string>, specializations?:list<string>,
     *   skills?:list<string>, technologies?:list<string>, stages?:list<string>, levels?:list<string>
     * } $tags
     */
    public static function syncTags(int $questionId, array $tags): void
    {
        if ($questionId < 1) {
            return;
        }
        $pdo = Db::pdo();
        $map = [
            'industries' => ['interview_question_industries', 'industry_id', 'interview_industries'],
            'occupations' => ['interview_question_occupations', 'occupation_id', 'interview_occupations'],
            'specializations' => ['interview_question_specializations', 'specialization_id', 'interview_specializations'],
            'skills' => ['interview_question_skills', 'skill_id', 'interview_skills'],
            'technologies' => ['interview_question_technologies', 'technology_id', 'interview_technologies'],
            'stages' => ['interview_question_stages', 'stage_id', 'interview_stages'],
        ];
        foreach ($map as $key => [$jTable, $fk, $ref]) {
            $pdo->prepare("DELETE FROM {$jTable} WHERE question_id = ?")->execute([$questionId]);
            $slugs = $tags[$key] ?? [];
            if (!is_array($slugs)) {
                continue;
            }
            $ins = $pdo->prepare("INSERT IGNORE INTO {$jTable} (question_id, {$fk}) VALUES (?, ?)");
            foreach ($slugs as $slug) {
                $slug = is_string($slug) ? $slug : (string) ($slug['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                if (str_contains($slug, '/')) {
                    $slug = basename(str_replace('\\', '/', $slug));
                }
                $tid = InterviewTaxonomy::idBySlug($ref, InterviewTaxonomy::slugify($slug));
                if ($tid < 1 && $key === 'skills') {
                    $tid = InterviewTaxonomy::upsertSkill($slug, str_replace('-', ' ', $slug));
                }
                if ($tid > 0) {
                    $ins->execute([$questionId, $tid]);
                }
            }
        }

        $pdo->prepare('DELETE FROM interview_question_levels WHERE question_id = ?')->execute([$questionId]);
        $levels = $tags['levels'] ?? [];
        if (is_array($levels)) {
            $insL = $pdo->prepare('INSERT IGNORE INTO interview_question_levels (question_id, career_level) VALUES (?, ?)');
            foreach ($levels as $level) {
                $level = trim((string) $level);
                if ($level !== '') {
                    $insL->execute([$questionId, $level]);
                }
            }
        }
    }

    /** Bulk attach industry/occupation slugs to many questions. */
    public static function bulkSetTaxonomy(array $ids, array $industrySlugs, array $occupationSlugs): void
    {
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $tags = self::tagsForQuestion($id);
            $ind = $industrySlugs !== [] ? $industrySlugs : array_column($tags['industries'], 'slug');
            $occ = $occupationSlugs !== [] ? $occupationSlugs : array_column($tags['occupations'], 'slug');
            self::syncTags($id, [
                'industries' => $ind,
                'occupations' => $occ,
                'specializations' => array_column($tags['specializations'], 'slug'),
                'skills' => array_column($tags['skills'], 'slug'),
                'technologies' => array_column($tags['technologies'], 'slug'),
                'stages' => array_column($tags['stages'], 'slug'),
                'levels' => $tags['levels'],
            ]);
        }
    }

    private static function truthyFilter(mixed $v): bool
    {
        if ($v === true || $v === 1 || $v === '1' || $v === 'yes' || $v === 'true') {
            return true;
        }
        return false;
    }

    /** @param array<string, mixed> $row */
    private static function decodeRow(array $row): array
    {
        foreach (['answer_framework', 'follow_ups', 'related_concepts', 'strong_answer_covers', 'common_mistakes', 'examples'] as $k) {
            if (!isset($row[$k]) || !is_string($row[$k]) || $row[$k] === '') {
                continue;
            }
            $decoded = json_decode($row[$k], true);
            if (is_array($decoded)) {
                $row[$k] = $decoded;
            }
        }
        return $row;
    }
}
