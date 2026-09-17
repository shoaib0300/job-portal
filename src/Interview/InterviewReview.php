<?php

declare(strict_types=1);

namespace KaamFit\Interview;

use Db;

/** Super-admin review queue: approve / reject / merge. */
final class InterviewReview
{
    public static function queue(int $questionId, int $submittedBy, ?int $possibleMatchId = null, ?float $score = null): int
    {
        InterviewSchema::ensureSchema();
        $pdo = Db::pdo();
        $ex = $pdo->prepare(
            "SELECT id FROM interview_content_reviews WHERE question_id = ? AND status = 'pending' LIMIT 1"
        );
        $ex->execute([$questionId]);
        $id = (int) ($ex->fetchColumn() ?: 0);
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE interview_content_reviews SET possible_match_id = ?, match_score = ? WHERE id = ?'
            )->execute([$possibleMatchId, $score, $id]);
            return $id;
        }
        $pdo->prepare(
            'INSERT INTO interview_content_reviews
              (question_id, submitted_by, status, possible_match_id, match_score)
             VALUES (?, ?, \'pending\', ?, ?)'
        )->execute([$questionId, $submittedBy, $possibleMatchId, $score]);
        return (int) $pdo->lastInsertId();
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int} */
    public static function pending(int $page = 1, int $perPage = 30): array
    {
        return self::listByStatus('pending', $page, $perPage);
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int} */
    public static function listByStatus(string $status, int $page = 1, int $perPage = 30): array
    {
        InterviewSchema::ensureSchema();
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $allowed = ['pending', 'approved', 'rejected', 'merged'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }
        $c = Db::pdo()->prepare('SELECT COUNT(*) FROM interview_content_reviews WHERE status = ?');
        $c->execute([$status]);
        $total = (int) $c->fetchColumn();
        $stmt = Db::pdo()->prepare(
            "SELECT r.*, q.question_text, q.language, q.question_type, q.difficulty, q.status AS q_status,
                    q.visibility, q.example_answer, q.source_type, q.source_name,
                    m.question_text AS match_question_text
             FROM interview_content_reviews r
             INNER JOIN interview_questions q ON q.id = r.question_id
             LEFT JOIN interview_questions m ON m.id = r.possible_match_id
             WHERE r.status = ?
             ORDER BY r.id " . ($status === 'pending' ? 'ASC' : 'DESC') . "
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([$status]);
        return [
            'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function approve(int $reviewId, int $adminId, array $overrides = []): void
    {
        InterviewSchema::ensureSchema();
        $review = self::find($reviewId);
        if ($review === null || $review['status'] !== 'pending') {
            throw new \InvalidArgumentException('Review not found or not pending');
        }
        $qid = (int) $review['question_id'];
        $q = InterviewQuestionRepo::find($qid);
        if ($q === null) {
            throw new \InvalidArgumentException('Question missing');
        }

        $data = array_merge([
            'slug' => (string) $q['slug'],
            'language' => (string) $q['language'],
            'question' => (string) $q['question_text'],
            'why_asked' => $q['why_asked'] ?? '',
            'strong_answer_covers' => $q['strong_answer_covers'] ?? '',
            'example_answer' => $overrides['example_answer'] ?? ($q['example_answer'] ?? ''),
            'short_answer' => $overrides['short_answer'] ?? ($q['short_answer'] ?? ''),
            'detailed_answer' => $overrides['detailed_answer'] ?? ($q['detailed_answer'] ?? ''),
            'answer_framework' => $q['answer_framework'] ?? '',
            'common_mistakes' => $q['common_mistakes'] ?? '',
            'follow_ups' => $q['follow_ups'] ?? '',
            'related_concepts' => $q['related_concepts'] ?? '',
            'question_type' => (string) ($q['question_type'] ?? 'general'),
            'difficulty' => (string) ($q['difficulty'] ?? 'medium'),
            'career_level' => $q['career_level'] ?? null,
            'category' => (string) ($q['category'] ?? ''),
            'is_universal' => 1,
            'visibility' => 'universal',
            'owner_user_id' => null,
            'review_status' => 'approved',
            'status' => 'published',
            'source_type' => 'approved_user_submission',
            'source_name' => (string) ($q['source_name'] ?? 'User submission'),
            'license' => (string) ($q['license'] ?? 'user_provided'),
            'updated_by' => $adminId,
            'approved_by' => $adminId,
        ], array_filter($overrides, static fn($v) => $v !== null && $v !== ''));

        // Promote imported answer into detailed answer if admin left empty and library has one
        if (trim((string) ($data['detailed_answer'] ?? '')) === '' && trim((string) ($data['example_answer'] ?? '')) === '') {
            $lib = Db::pdo()->prepare(
                'SELECT imported_answer FROM user_interview_questions WHERE question_id = ? AND imported_answer IS NOT NULL AND imported_answer != \'\' LIMIT 1'
            );
            $lib->execute([$qid]);
            $imp = $lib->fetchColumn();
            if (is_string($imp) && trim($imp) !== '') {
                $data['detailed_answer'] = $imp;
            }
        }

        $data['short_answer'] = InterviewAnswerPresentation::normalizeAnswer(
            is_string($data['short_answer'] ?? null) ? (string) $data['short_answer'] : null
        );
        $data['detailed_answer'] = InterviewAnswerPresentation::normalizeAnswer(
            is_string($data['detailed_answer'] ?? null) ? (string) $data['detailed_answer'] : null
        );
        $data['example_answer'] = InterviewAnswerPresentation::normalizeAnswer(
            is_string($data['example_answer'] ?? null) ? (string) $data['example_answer'] : null
        );
        // Prefer detailed_answer as canonical body
        if ($data['detailed_answer'] === '' && $data['example_answer'] !== '') {
            $data['detailed_answer'] = $data['example_answer'];
        }

        InterviewQuestionRepo::upsert($data, [
            'industries' => $overrides['industries'] ?? [],
            'occupations' => $overrides['occupations'] ?? [],
            'specializations' => $overrides['specializations'] ?? [],
            'skills' => $overrides['skills'] ?? [],
            'stages' => $overrides['stages'] ?? [],
        ], true);

        Db::pdo()->prepare(
            'UPDATE interview_questions SET approved_by = ?, approved_at = NOW(), review_status = \'approved\',
             visibility = \'universal\', is_universal = 1, status = \'published\', owner_user_id = NULL WHERE id = ?'
        )->execute([$adminId, $qid]);

        self::close($reviewId, 'approved', $adminId, (string) ($overrides['admin_notes'] ?? ''));

        // Clear duplicate library copy once it has been promoted into the universal answer
        if (trim((string) ($data['detailed_answer'] ?? '')) !== '') {
            Db::pdo()->prepare(
                'UPDATE user_interview_questions SET imported_answer = NULL
                 WHERE question_id = ? AND imported_answer IS NOT NULL AND imported_answer != \'\''
            )->execute([$qid]);
        }
    }

    public static function reject(int $reviewId, int $adminId, string $notes = ''): void
    {
        InterviewSchema::ensureSchema();
        $review = self::find($reviewId);
        if ($review === null || $review['status'] !== 'pending') {
            throw new \InvalidArgumentException('Review not found or not pending');
        }
        $qid = (int) $review['question_id'];
        // Keep personal for the owning user; mark rejected for universal
        Db::pdo()->prepare(
            "UPDATE interview_questions SET review_status = 'rejected', status = 'rejected', updated_by = ? WHERE id = ?"
        )->execute([$adminId, $qid]);
        self::close($reviewId, 'rejected', $adminId, $notes);
    }

    /** Merge personal into existing canonical: re-link library, archive personal. */
    public static function merge(int $reviewId, int $canonicalId, int $adminId, string $notes = ''): void
    {
        InterviewSchema::ensureSchema();
        $review = self::find($reviewId);
        if ($review === null || $review['status'] !== 'pending') {
            throw new \InvalidArgumentException('Review not found or not pending');
        }
        $personalId = (int) $review['question_id'];
        if ($personalId === $canonicalId) {
            throw new \InvalidArgumentException('Cannot merge into itself');
        }
        $canonical = InterviewQuestionRepo::find($canonicalId);
        if ($canonical === null || ($canonical['visibility'] ?? '') !== 'universal') {
            throw new \InvalidArgumentException('Canonical question required');
        }

        $pdo = Db::pdo();
        // Move user library links from personal → canonical
        $links = $pdo->prepare('SELECT * FROM user_interview_questions WHERE question_id = ?');
        $links->execute([$personalId]);
        foreach ($links->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $link) {
            InterviewLibrary::link(
                (int) $link['user_id'],
                $canonicalId,
                'merged_canonical',
                is_string($link['imported_answer'] ?? null) ? (string) $link['imported_answer'] : null,
                (string) ($link['import_batch_id'] ?? ''),
                (string) ($link['source_filename'] ?? ''),
                (string) ($link['source_type'] ?? 'user_upload')
            );
            $pdo->prepare('DELETE FROM user_interview_questions WHERE id = ?')->execute([(int) $link['id']]);
        }
        // Move progress
        $prog = $pdo->prepare('SELECT * FROM interview_user_progress WHERE question_id = ?');
        $prog->execute([$personalId]);
        foreach ($prog->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $p) {
            InterviewProgress::upsert((int) $p['user_id'], $canonicalId, [
                'viewed' => (int) $p['viewed'],
                'practiced' => (int) $p['practiced'],
                'confident' => (int) $p['confident'],
                'favorite' => (int) $p['favorite'],
                'confidence' => (int) $p['confidence'],
                'notes' => $p['notes'],
                'personal_answer' => $p['personal_answer'],
            ]);
            $pdo->prepare('DELETE FROM interview_user_progress WHERE id = ?')->execute([(int) $p['id']]);
        }

        InterviewQuestionRepo::archive($personalId, $adminId);
        Db::pdo()->prepare(
            "UPDATE interview_questions SET review_status = 'merged' WHERE id = ?"
        )->execute([$personalId]);
        self::close($reviewId, 'merged', $adminId, $notes !== '' ? $notes : 'Merged into #' . $canonicalId);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        InterviewSchema::ensureSchema();
        $st = Db::pdo()->prepare('SELECT * FROM interview_content_reviews WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function close(int $reviewId, string $status, int $adminId, string $notes): void
    {
        Db::pdo()->prepare(
            'UPDATE interview_content_reviews SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_notes = ? WHERE id = ?'
        )->execute([$status, $adminId, $notes, $reviewId]);
    }
}
