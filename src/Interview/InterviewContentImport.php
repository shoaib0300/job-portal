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
            $answer = trim($pair['answer']);
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
