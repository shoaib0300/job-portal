<?php

declare(strict_types=1);

namespace KaamFit\Interview;

use Auth;
use Db;

final class InterviewProgress
{
    /** @return array<string, mixed>|null */
    public static function forUserQuestion(int $userId, int $questionId): ?array
    {
        InterviewSchema::ensureSchema();
        if ($userId < 1 || $questionId < 1) {
            return null;
        }
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM interview_user_progress WHERE user_id = ? AND question_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $questionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function upsert(int $userId, int $questionId, array $fields): void
    {
        InterviewSchema::ensureSchema();
        if ($userId < 1 || $questionId < 1) {
            return;
        }
        $existing = self::forUserQuestion($userId, $questionId);
        $viewed = !empty($fields['viewed']) ? 1 : (int) ($existing['viewed'] ?? 0);
        $practiced = array_key_exists('practiced', $fields)
            ? (!empty($fields['practiced']) ? 1 : 0)
            : (int) ($existing['practiced'] ?? 0);
        $confident = array_key_exists('confident', $fields)
            ? (!empty($fields['confident']) ? 1 : 0)
            : (int) ($existing['confident'] ?? 0);
        $favorite = array_key_exists('favorite', $fields)
            ? (!empty($fields['favorite']) ? 1 : 0)
            : (int) ($existing['favorite'] ?? 0);
        $confidence = array_key_exists('confidence', $fields)
            ? max(0, min(100, (int) $fields['confidence']))
            : (int) ($existing['confidence'] ?? 0);
        $attempts = (int) ($existing['attempts'] ?? 0);
        if (!empty($fields['practiced']) || !empty($fields['bump_attempt'])) {
            $attempts++;
        }
        $notes = array_key_exists('notes', $fields)
            ? (string) $fields['notes']
            : ($existing['notes'] ?? null);
        $personal = array_key_exists('personal_answer', $fields)
            ? (string) $fields['personal_answer']
            : ($existing['personal_answer'] ?? null);
        $last = (!empty($fields['practiced']) || !empty($fields['bump_attempt']))
            ? date('Y-m-d H:i:s')
            : ($existing['last_practiced_at'] ?? null);

        Db::pdo()->prepare(
            'INSERT INTO interview_user_progress
              (user_id, question_id, viewed, practiced, confident, favorite, confidence, attempts, notes, personal_answer, last_practiced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
              viewed = VALUES(viewed),
              practiced = VALUES(practiced),
              confident = VALUES(confident),
              favorite = VALUES(favorite),
              confidence = VALUES(confidence),
              attempts = VALUES(attempts),
              notes = VALUES(notes),
              personal_answer = VALUES(personal_answer),
              last_practiced_at = VALUES(last_practiced_at)'
        )->execute([
            $userId, $questionId, $viewed, $practiced, $confident, $favorite,
            $confidence, $attempts, $notes, $personal, $last,
        ]);
    }

    /** @return array{practiced:int,favorites:int,need_practice:int,viewed:int,total_published:int,percent:int,focus:list<string>} */
    public static function dashboard(int $userId): array
    {
        InterviewSchema::ensureSchema();
        $total = (int) Db::pdo()->query(
            "SELECT COUNT(*) FROM interview_questions WHERE status = 'published' AND visibility = 'universal'"
        )->fetchColumn();

        $practiced = 0;
        $favorites = 0;
        $viewed = 0;
        $need = 0;
        if ($userId > 0) {
            $stmt = Db::pdo()->prepare(
                'SELECT
                   SUM(practiced = 1) AS practiced,
                   SUM(favorite = 1) AS favorites,
                   SUM(viewed = 1) AS viewed,
                   SUM(practiced = 1 AND confident = 0) AS need_practice
                 FROM interview_user_progress WHERE user_id = ?'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $practiced = (int) ($row['practiced'] ?? 0);
            $favorites = (int) ($row['favorites'] ?? 0);
            $viewed = (int) ($row['viewed'] ?? 0);
            $need = (int) ($row['need_practice'] ?? 0);
        }

        $focus = [];
        if ($userId > 0) {
            $fst = Db::pdo()->prepare(
                'SELECT s.name_en, COUNT(*) AS c
                 FROM interview_user_progress p
                 INNER JOIN interview_question_skills qs ON qs.question_id = p.question_id
                 INNER JOIN interview_skills s ON s.id = qs.skill_id
                 WHERE p.user_id = ? AND (p.practiced = 1 OR p.favorite = 1)
                 GROUP BY s.id
                 ORDER BY c DESC
                 LIMIT 5'
            );
            $fst->execute([$userId]);
            foreach ($fst->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                $focus[] = (string) $r['name_en'];
            }
        }

        $percent = $total > 0 ? (int) round(($practiced / $total) * 100) : 0;

        return [
            'practiced' => $practiced,
            'favorites' => $favorites,
            'need_practice' => $need,
            'viewed' => $viewed,
            'total_published' => $total,
            'percent' => min(100, $percent),
            'focus' => $focus,
        ];
    }

    public static function markViewed(int $questionId): void
    {
        $uid = Auth::id();
        if ($uid > 0) {
            self::upsert($uid, $questionId, ['viewed' => 1]);
        }
    }
}
