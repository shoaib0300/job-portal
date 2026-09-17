<?php

declare(strict_types=1);

namespace KaamFit\Interview;

use Db;

/**
 * Shared import pipeline for CLI + web UI.
 * Never creates duplicate universal questions; never exposes match internals to users.
 */
final class InterviewContentImport
{
    /**
     * @param array{text?:string,file?:array,path?:string,filename?:string,language?:string,source_type?:string,dry_run?:bool} $input
     * @return array{
     *   batch_id:string,
     *   user_summary:array{added:int,answers:int,need_review:int},
     *   admin_stats:array<string,int>,
     *   dry_run:bool
     * }
     */
    public static function importForUser(int $userId, array $input): array
    {
        InterviewSchema::ensureSchema();
        if ($userId < 1) {
            throw new \InvalidArgumentException('User required');
        }

        $dryRun = !empty($input['dry_run']);
        $language = strtolower(trim((string) ($input['language'] ?? 'en')));
        if ($language === '') {
            $language = 'en';
        }
        $sourceType = (string) ($input['source_type'] ?? 'user_upload');
        if (!in_array($sourceType, InterviewSchema::sourceTypes(), true)) {
            $sourceType = 'user_upload';
        }

        if (!empty($input['text'])) {
            $doc = InterviewDocumentParser::fromPaste((string) $input['text']);
            if ($sourceType === 'user_upload') {
                $sourceType = 'user_paste';
            }
        } elseif (!empty($input['file']) && is_array($input['file'])) {
            $doc = InterviewDocumentParser::parseUpload($input['file']);
        } elseif (!empty($input['path'])) {
            $doc = InterviewDocumentParser::parsePath(
                (string) $input['path'],
                (string) ($input['filename'] ?? '')
            );
        } else {
            throw new \InvalidArgumentException('Provide text, file, or path');
        }

        $pairs = InterviewQuestionExtractor::extract($doc['text']);
        $batchId = bin2hex(random_bytes(8));

        $stats = [
            'detected' => count($pairs),
            'canonical_matches' => 0,
            'near_matches' => 0,
            'new_personal' => 0,
            'answers_detected' => 0,
            'missing_answers' => 0,
            'already_linked' => 0,
            'review_queued' => 0,
            'linked' => 0,
        ];
        $added = 0;
        $answers = 0;
        $needReview = 0;

        foreach ($pairs as $pair) {
            $qText = trim($pair['question']);
            $answer = InterviewAnswerPresentation::normalizeAnswer($pair['answer'] ?? '');
            if ($answer !== '') {
                $stats['answers_detected']++;
                $answers++;
            } else {
                $stats['missing_answers']++;
            }

            $match = InterviewDuplicateDetector::findCanonicalMatch($qText, $language);

            if ($dryRun) {
                if ($match['confidence'] === 'exact' && is_array($match['match'])) {
                    $stats['canonical_matches']++;
                    $stats['linked']++;
                    $added++;
                } elseif ($match['confidence'] === 'near') {
                    $stats['near_matches']++;
                    $stats['new_personal']++;
                    $stats['review_queued']++;
                    $added++;
                    $needReview++;
                } else {
                    $stats['new_personal']++;
                    $stats['review_queued']++;
                    $added++;
                    $needReview++;
                }
                continue;
            }

            if ($match['confidence'] === 'exact' && is_array($match['match'])) {
                $qid = (int) $match['match']['id'];
                $link = InterviewLibrary::link(
                    $userId,
                    $qid,
                    'linked_canonical',
                    $answer !== '' ? $answer : null,
                    $batchId,
                    $doc['filename'],
                    $sourceType
                );
                $stats['canonical_matches']++;
                if ($link['action'] === 'already_linked') {
                    $stats['already_linked']++;
                } else {
                    $stats['linked']++;
                    $added++;
                }
                continue;
            }

            // New personal question (near-dup or none) — never create second universal
            $slug = 'u' . $userId . '-' . substr(sha1($qText), 0, 12);
            $data = [
                'slug' => $slug,
                'language' => $language,
                'question' => $qText,
                'example_answer' => '', // never put imported answer into canonical field until approved
                'question_type' => self::guessType($qText),
                'difficulty' => 'medium',
                'category' => 'imported',
                'is_universal' => 0,
                'visibility' => 'personal',
                'owner_user_id' => $userId,
                'review_status' => 'pending',
                'status' => 'review',
                'source_type' => $sourceType,
                'source_name' => $doc['filename'],
                'license' => 'user_provided',
                'created_by' => $userId,
            ];
            $result = InterviewQuestionRepo::upsert($data, [], false);
            $qid = (int) $result['id'];
            // If slug collided with another personal, still link
            if ($result['action'] === 'skipped_duplicate') {
                // ensure still personal row
            }

            $link = InterviewLibrary::link(
                $userId,
                $qid,
                $match['confidence'] === 'near' ? 'imported_near' : 'imported_personal',
                $answer !== '' ? $answer : null,
                $batchId,
                $doc['filename'],
                $sourceType
            );

            $possibleMatchId = ($match['confidence'] === 'near' && is_array($match['match']))
                ? (int) $match['match']['id']
                : null;
            InterviewReview::queue(
                $qid,
                $userId,
                $possibleMatchId,
                $match['score'] > 0 ? $match['score'] : null
            );

            if ($match['confidence'] === 'near') {
                $stats['near_matches']++;
            }
            $stats['new_personal']++;
            $stats['review_queued']++;
            $needReview++;
            if ($link['action'] === 'already_linked') {
                $stats['already_linked']++;
            } else {
                $stats['linked']++;
                $added++;
            }
        }

        return [
            'batch_id' => $batchId,
            'user_summary' => [
                'added' => $added,
                'answers' => $answers,
                'need_review' => $needReview,
            ],
            'admin_stats' => $stats,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Super Admin import: same parser/extractor/dedupe as user import,
     * but new questions become universal + approved immediately (no review queue).
     * Authorization must be enforced by the caller (SuperAdmin::requireLogin).
     *
     * @param array{
     *   text?:string,file?:array,path?:string,filename?:string,language?:string,
     *   source_type?:string,dry_run?:bool,
     *   industry?:string,occupation?:string,specialization?:string,
     *   skill?:string,technology?:string,question_type?:string,difficulty?:string,
     *   career_level?:string,stage?:string,category?:string
     * } $input
     * @return array{
     *   batch_id:string,
     *   summary:array{found:int,imported:int,already_existing:int,skipped:int,errors:int,answers:int},
     *   admin_stats:array<string,int>,
     *   question_ids:list<int>,
     *   dry_run:bool
     * }
     */
    public static function importForSuperAdmin(int $adminId, array $input): array
    {
        InterviewSchema::ensureSchema();
        if ($adminId < 1) {
            throw new \InvalidArgumentException('Super Admin required');
        }

        $industry = InterviewTaxonomy::slugify(trim((string) ($input['industry'] ?? '')));
        $occupation = InterviewTaxonomy::slugify(trim((string) ($input['occupation'] ?? '')));
        if ($industry === '' || $occupation === '') {
            throw new \InvalidArgumentException('Field (industry) and Department (occupation) are required.');
        }
        $indId = InterviewTaxonomy::idBySlug('interview_industries', $industry);
        $occId = InterviewTaxonomy::idBySlug('interview_occupations', $occupation);
        if ($indId < 1 || $occId < 1) {
            throw new \InvalidArgumentException('Invalid Field or Department.');
        }
        $occRows = InterviewTaxonomy::occupations($indId, false);
        $occOk = false;
        foreach ($occRows as $o) {
            if ((int) $o['id'] === $occId) {
                $occOk = true;
                break;
            }
        }
        if (!$occOk) {
            throw new \InvalidArgumentException('Department does not belong to the selected Field.');
        }

        $specialization = InterviewTaxonomy::slugify(trim((string) ($input['specialization'] ?? '')));
        $skill = InterviewTaxonomy::slugify(trim((string) ($input['skill'] ?? '')));
        $technology = InterviewTaxonomy::slugify(trim((string) ($input['technology'] ?? '')));
        $stage = InterviewTaxonomy::slugify(trim((string) ($input['stage'] ?? '')));
        $questionType = trim((string) ($input['question_type'] ?? ''));
        $difficulty = trim((string) ($input['difficulty'] ?? 'medium')) ?: 'medium';
        $careerLevel = trim((string) ($input['career_level'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));

        $dryRun = !empty($input['dry_run']);
        $language = strtolower(trim((string) ($input['language'] ?? 'en'))) ?: 'en';
        $sourceType = (string) ($input['source_type'] ?? 'user_upload');
        if (!in_array($sourceType, InterviewSchema::sourceTypes(), true)) {
            $sourceType = 'user_upload';
        }

        $doc = self::resolveDocument($input, $sourceType);
        $sourceType = $doc['source_type'];
        $pairs = InterviewQuestionExtractor::extract($doc['text']);
        $batchId = bin2hex(random_bytes(8));

        $stats = [
            'detected' => count($pairs),
            'canonical_matches' => 0,
            'near_matches' => 0,
            'new_universal' => 0,
            'answers_detected' => 0,
            'missing_answers' => 0,
            'skipped_weak' => 0,
            'errors' => 0,
            'taxonomy_attached' => 0,
        ];
        $imported = 0;
        $already = 0;
        $skipped = 0;
        $errors = 0;
        $answers = 0;
        $questionIds = [];

        $baseTags = [
            'industries' => [$industry],
            'occupations' => [$occupation],
            'specializations' => $specialization !== '' ? [$specialization] : [],
            'skills' => $skill !== '' ? [$skill] : [],
            'technologies' => $technology !== '' ? [$technology] : [],
            'stages' => $stage !== '' ? [$stage] : [],
            'levels' => $careerLevel !== '' ? [$careerLevel] : [],
        ];

        foreach ($pairs as $pair) {
            try {
                $qText = trim($pair['question']);
                $answer = InterviewAnswerPresentation::normalizeAnswer($pair['answer'] ?? '');
                if ($qText === '') {
                    $skipped++;
                    continue;
                }
                if ($answer !== '' && !InterviewMarkdown::isWeakFiller($answer)) {
                    $stats['answers_detected']++;
                    $answers++;
                } elseif ($answer !== '') {
                    $stats['skipped_weak']++;
                    $answer = ''; // do not publish weak filler as canonical answer
                } else {
                    $stats['missing_answers']++;
                }

                $match = InterviewDuplicateDetector::findCanonicalMatch($qText, $language);
                $isExisting = ($match['confidence'] === 'exact' || $match['confidence'] === 'near')
                    && is_array($match['match']);

                if ($dryRun) {
                    if ($isExisting) {
                        $stats['canonical_matches']++;
                        if ($match['confidence'] === 'near') {
                            $stats['near_matches']++;
                        }
                        $already++;
                        $questionIds[] = (int) $match['match']['id'];
                    } else {
                        $stats['new_universal']++;
                        $imported++;
                    }
                    continue;
                }

                if ($isExisting) {
                    $qid = (int) $match['match']['id'];
                    self::attachTaxonomyIfMissing($qid, $baseTags);
                    $stats['canonical_matches']++;
                    $stats['taxonomy_attached']++;
                    if ($match['confidence'] === 'near') {
                        $stats['near_matches']++;
                    }
                    $already++;
                    $questionIds[] = $qid;
                    continue;
                }

                $slug = 'sa' . $adminId . '-' . substr(sha1($language . '|' . $qText), 0, 14);
                $type = $questionType !== '' && in_array($questionType, InterviewSchema::questionTypes(), true)
                    ? $questionType
                    : self::guessType($qText);
                $data = [
                    'slug' => $slug,
                    'language' => $language,
                    'question' => $qText,
                    'detailed_answer' => $answer,
                    'example_answer' => $answer,
                    'question_type' => $type,
                    'difficulty' => in_array($difficulty, InterviewSchema::difficulties(), true) ? $difficulty : 'medium',
                    'career_level' => $careerLevel !== '' ? $careerLevel : null,
                    'category' => $category !== '' ? $category : 'imported',
                    'is_universal' => 1,
                    'visibility' => 'universal',
                    'owner_user_id' => null,
                    'review_status' => 'approved',
                    'status' => 'published',
                    'source_type' => $sourceType,
                    'source_name' => $doc['filename'],
                    'license' => $sourceType === 'kaamfit_original' ? 'proprietary' : 'user_provided',
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                    'approved_by' => $adminId,
                    'industries' => $baseTags['industries'],
                    'occupations' => $baseTags['occupations'],
                    'specializations' => $baseTags['specializations'],
                    'skills' => $baseTags['skills'],
                    'technologies' => $baseTags['technologies'],
                    'stages' => $baseTags['stages'],
                    'levels' => $baseTags['levels'],
                ];
                $result = InterviewQuestionRepo::upsert($data, $baseTags, true);
                $qid = (int) $result['id'];
                Db::pdo()->prepare(
                    'UPDATE interview_questions SET approved_by = ?, approved_at = NOW(), review_status = \'approved\',
                     visibility = \'universal\', is_universal = 1, status = \'published\' WHERE id = ?'
                )->execute([$adminId, $qid]);
                $stats['new_universal']++;
                $imported++;
                $questionIds[] = $qid;
            } catch (\Throwable $e) {
                $errors++;
                $stats['errors']++;
            }
        }

        return [
            'batch_id' => $batchId,
            'summary' => [
                'found' => count($pairs),
                'imported' => $imported,
                'already_existing' => $already,
                'skipped' => $skipped + (int) $stats['skipped_weak'],
                'errors' => $errors,
                'answers' => $answers,
            ],
            'admin_stats' => $stats,
            'question_ids' => array_values(array_unique($questionIds)),
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @param array{text?:string,file?:array,path?:string,filename?:string,source_type?:string} $input
     * @return array{text:string,format:string,filename:string,source_type:string}
     */
    private static function resolveDocument(array $input, string $sourceType): array
    {
        if (!empty($input['text'])) {
            $doc = InterviewDocumentParser::fromPaste((string) $input['text']);
            if ($sourceType === 'user_upload') {
                $sourceType = 'user_paste';
            }
            $doc['source_type'] = $sourceType;
            return $doc;
        }
        if (!empty($input['file']) && is_array($input['file'])) {
            $doc = InterviewDocumentParser::parseUpload($input['file']);
            $doc['source_type'] = $sourceType;
            return $doc;
        }
        if (!empty($input['path'])) {
            $doc = InterviewDocumentParser::parsePath(
                (string) $input['path'],
                (string) ($input['filename'] ?? '')
            );
            $doc['source_type'] = $sourceType;
            return $doc;
        }
        throw new \InvalidArgumentException('Provide text, file, or path');
    }

    /**
     * Merge taxonomy tags onto an existing question without removing current tags.
     * @param array{industries:list<string>,occupations:list<string>,specializations:list<string>,skills:list<string>,technologies:list<string>,stages:list<string>,levels:list<string>} $tags
     */
    private static function attachTaxonomyIfMissing(int $questionId, array $tags): void
    {
        if ($questionId < 1) {
            return;
        }
        $existing = InterviewQuestionRepo::tagsForQuestion($questionId);
        $merge = static function (array $have, array $add): array {
            $slugs = array_column($have, 'slug');
            foreach ($add as $s) {
                $s = trim((string) $s);
                if ($s !== '' && !in_array($s, $slugs, true)) {
                    $slugs[] = $s;
                }
            }
            return $slugs;
        };
        $levelHave = $existing['levels'] ?? [];
        InterviewQuestionRepo::syncTags($questionId, [
            'industries' => $merge($existing['industries'] ?? [], $tags['industries'] ?? []),
            'occupations' => $merge($existing['occupations'] ?? [], $tags['occupations'] ?? []),
            'specializations' => $merge($existing['specializations'] ?? [], $tags['specializations'] ?? []),
            'skills' => $merge($existing['skills'] ?? [], $tags['skills'] ?? []),
            'technologies' => $merge($existing['technologies'] ?? [], $tags['technologies'] ?? []),
            'stages' => $merge($existing['stages'] ?? [], $tags['stages'] ?? []),
            'levels' => array_values(array_unique(array_filter(array_merge(
                array_map('strval', $levelHave),
                $tags['levels'] ?? []
            )))),
        ]);
    }

    private static function guessType(string $q): string
    {
        $l = mb_strtolower($q);
        if (preg_match('/\b(tell me about a time|situation|challenge|conflict|example of)\b/u', $l)) {
            return 'behavioral';
        }
        if (preg_match('/\b(how would you|what would you do|imagine)\b/u', $l)) {
            return 'situational';
        }
        if (preg_match('/\b(lead|team|manage|delegate|stakeholder)\b/u', $l)) {
            return 'leadership';
        }
        if (preg_match('/\b(test|code|api|database|algorithm|regression|sql)\b/u', $l)) {
            return 'technical';
        }
        if (str_starts_with($l, 'what is') || str_starts_with($l, 'what are') || str_starts_with($l, 'define')) {
            return 'technical';
        }
        return 'general';
    }
}
