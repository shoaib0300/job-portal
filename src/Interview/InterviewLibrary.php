<?php

declare(strict_types=1);

namespace KaamFit\Interview;

use Db;

/** User ↔ question library (linked universal or personal import). */
final class InterviewLibrary
{
    /**
     * @return array{id:int,action:string}
     */
    public static function link(
        int $userId,
        int $questionId,
        string $origin = 'linked',
        ?string $importedAnswer = null,
        string $batchId = '',
        string $sourceFilename = '',
        string $sourceType = 'user_upload'
    ): array {
        InterviewSchema::ensureSchema();
        if ($userId < 1 || $questionId < 1) {
            throw new \InvalidArgumentException('Invalid user or question');
        }
        $pdo = Db::pdo();
        $existing = $pdo->prepare(
            'SELECT id FROM user_interview_questions WHERE user_id = ? AND question_id = ? LIMIT 1'
        );
        $existing->execute([$userId, $questionId]);
        $id = (int) ($existing->fetchColumn() ?: 0);
        if ($id > 0) {
            if ($importedAnswer !== null && $importedAnswer !== '') {
                $pdo->prepare(
                    'UPDATE user_interview_questions SET imported_answer = COALESCE(NULLIF(imported_answer, \'\'), ?) WHERE id = ?'
                )->execute([$importedAnswer, $id]);
            }
            return ['id' => $id, 'action' => 'already_linked'];
        }
        $pdo->prepare(
            'INSERT INTO user_interview_questions
              (user_id, question_id, origin, imported_answer, import_batch_id, source_filename, source_type)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            $questionId,
            $origin,
            $importedAnswer,
            $batchId,
            $sourceFilename,
            $sourceType,
        ]);
        return ['id' => (int) $pdo->lastInsertId(), 'action' => 'linked'];
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int} */
    public static function forUser(int $userId, array $filters = []): array
    {
        InterviewSchema::ensureSchema();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 24)));
        $offset = ($page - 1) * $perPage;
        $where = ['uiq.user_id = ?'];
        $params = [$userId];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = 'q.question_text LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $whereSql = implode(' AND ', $where);
        $c = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM user_interview_questions uiq
             INNER JOIN interview_questions q ON q.id = uiq.question_id
             WHERE {$whereSql}"
        );
        $c->execute($params);
        $total = (int) $c->fetchColumn();
        $stmt = Db::pdo()->prepare(
            "SELECT q.*, uiq.origin, uiq.imported_answer, uiq.source_filename, uiq.created_at AS linked_at
             FROM user_interview_questions uiq
             INNER JOIN interview_questions q ON q.id = uiq.question_id
             WHERE {$whereSql}
             ORDER BY uiq.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return [
            'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function isLinked(int $userId, int $questionId): bool
    {
        if ($userId < 1 || $questionId < 1) {
            return false;
        }
        InterviewSchema::ensureSchema();
        $st = Db::pdo()->prepare(
            'SELECT 1 FROM user_interview_questions WHERE user_id = ? AND question_id = ? LIMIT 1'
        );
        $st->execute([$userId, $questionId]);
        return (bool) $st->fetchColumn();
    }

    /** @return array<string,mixed>|null */
    public static function entry(int $userId, int $questionId): ?array
    {
        InterviewSchema::ensureSchema();
        $st = Db::pdo()->prepare(
            'SELECT * FROM user_interview_questions WHERE user_id = ? AND question_id = ? LIMIT 1'
        );
        $st->execute([$userId, $questionId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
