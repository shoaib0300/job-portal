<?php

declare(strict_types=1);

namespace KaamFit\Interview;

use Db;
use KaamFit\Jobs\JobText;
use KaamFit\Jobs\ResumeJobMatch;
use Versions;

/**
 * Single matching layer for resume + job description → interview questions.
 */
final class InterviewMatcher
{
    /**
     * @return array{
     *   question_ids: list<int>,
     *   buckets: array{resume_skills:int, experience:int, occupation:int, industry:int, general:int, total:int},
     *   terms: list<string>
     * }
     */
    public static function match(
        ?int $resumeVersionId = null,
        ?string $jdText = null,
        ?string $role = null,
        string $language = '',
        int $userId = 0
    ): array {
        InterviewSchema::ensureSchema();

        $terms = [];
        $payload = Versions::resumePayloadForView($resumeVersionId !== null && $resumeVersionId > 0 ? $resumeVersionId : null);
        foreach (ResumeJobMatch::scoreTerms($payload) as $t) {
            $t = trim((string) $t);
            if ($t !== '') {
                $terms[] = $t;
            }
        }
        if ($role !== null && trim($role) !== '') {
            $terms[] = trim($role);
        }
        if ($jdText !== null && trim($jdText) !== '') {
            $jd = JobText::foldMatch($jdText);
            foreach (preg_split('/\s+/u', $jd) ?: [] as $tok) {
                if (mb_strlen($tok) >= 4) {
                    $terms[] = $tok;
                }
            }
        }
        $terms = array_values(array_unique(array_filter($terms)));

        $resolved = InterviewTaxonomy::resolveTerms($terms);
        $skillIds = $resolved['skill_ids'];
        $occIds = $resolved['occupation_ids'];
        $indIds = $resolved['industry_ids'];

        $buckets = [
            'resume_skills' => 0,
            'experience' => 0,
            'occupation' => 0,
            'industry' => 0,
            'general' => 0,
            'total' => 0,
        ];
        $scores = [];

        $pdo = Db::pdo();
        $langSql = $language !== '' ? ' AND q.language = ' . $pdo->quote($language) : '';
        // Universal published, plus this user's personal/library questions
        $scopeSql = "(q.visibility = 'universal' AND q.status = 'published')";
        if ($userId > 0) {
            $scopeSql = "(
                (q.visibility = 'universal' AND q.status = 'published')
                OR q.owner_user_id = {$userId}
                OR EXISTS (SELECT 1 FROM user_interview_questions uiq WHERE uiq.question_id = q.id AND uiq.user_id = {$userId})
            )";
        }

        if ($skillIds !== []) {
            $ph = implode(',', array_map('intval', $skillIds));
            $rows = $pdo->query(
                "SELECT q.id, COUNT(*) AS c FROM interview_questions q
                 INNER JOIN interview_question_skills qs ON qs.question_id = q.id
                 WHERE ({$scopeSql}) AND qs.skill_id IN ({$ph}) {$langSql}
                 GROUP BY q.id"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $id = (int) $r['id'];
                $scores[$id] = ($scores[$id] ?? 0) + ((int) $r['c'] * 3);
                $buckets['resume_skills']++;
            }
        }

        if ($occIds !== []) {
            $ph = implode(',', array_map('intval', $occIds));
            $rows = $pdo->query(
                "SELECT q.id FROM interview_questions q
                 INNER JOIN interview_question_occupations qo ON qo.question_id = q.id
                 WHERE ({$scopeSql}) AND qo.occupation_id IN ({$ph}) {$langSql}"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $id = (int) $r['id'];
                $scores[$id] = ($scores[$id] ?? 0) + 4;
                $buckets['occupation']++;
            }
        }

        if ($indIds !== []) {
            $ph = implode(',', array_map('intval', $indIds));
            $rows = $pdo->query(
                "SELECT q.id FROM interview_questions q
                 INNER JOIN interview_question_industries qi ON qi.question_id = q.id
                 WHERE ({$scopeSql}) AND qi.industry_id IN ({$ph}) {$langSql}"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $id = (int) $r['id'];
                $scores[$id] = ($scores[$id] ?? 0) + 2;
                $buckets['industry']++;
            }
        }

        // Behavioral / general always available
        $gen = $pdo->query(
            "SELECT id FROM interview_questions q
             WHERE ({$scopeSql}) AND (q.is_universal = 1 OR q.question_type IN ('general','behavioral'))
             {$langSql}
             ORDER BY q.is_universal DESC, q.id ASC
             LIMIT 40"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($gen as $r) {
            $id = (int) $r['id'];
            $scores[$id] = ($scores[$id] ?? 0) + 1;
            $buckets['general']++;
        }

        // Experience / portfolio types
        $exp = $pdo->query(
            "SELECT id FROM interview_questions q
             WHERE ({$scopeSql}) AND q.question_type IN ('portfolio','practical','situational')
             {$langSql}
             LIMIT 40"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($exp as $r) {
            $id = (int) $r['id'];
            $scores[$id] = ($scores[$id] ?? 0) + 1;
            $buckets['experience']++;
        }

        arsort($scores);
        $ids = array_map('intval', array_keys($scores));
        $buckets['total'] = count($ids);

        return [
            'question_ids' => $ids,
            'buckets' => $buckets,
            'terms' => array_slice($terms, 0, 40),
        ];
    }
}
