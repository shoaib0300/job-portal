<?php

declare(strict_types=1);

namespace KaamFit\Interview;

final class InterviewImport
{
    /**
     * @return array{
     *   found:int, valid:int, duplicates:int, errors:int, new:int, updated:int, skipped:int,
     *   messages: list<string>
     * }
     */
    public static function fromFile(string $path, bool $dryRun = false, bool $allowUpdate = false, ?string $forceSource = null): array
    {
        InterviewSchema::ensureSchema();
        if (!is_readable($path)) {
            throw new \InvalidArgumentException('File not readable: ' . $path);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $raw = (string) file_get_contents($path);
        $items = [];
        if ($ext === 'json') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('Invalid JSON');
            }
            if (isset($decoded['questions']) && is_array($decoded['questions'])) {
                $items = $decoded['questions'];
                if (isset($decoded['taxonomy']) && is_array($decoded['taxonomy']) && !$dryRun) {
                    InterviewTaxonomy::importTaxonomyArray($decoded['taxonomy']);
                }
            } elseif (array_is_list($decoded)) {
                $items = $decoded;
            } else {
                throw new \InvalidArgumentException('JSON must be a list or {questions:[...]}');
            }
        } elseif ($ext === 'csv') {
            $items = self::parseCsv($raw);
        } else {
            throw new \InvalidArgumentException('Supported formats: json, csv');
        }

        $stats = [
            'found' => count($items),
            'valid' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'new' => 0,
            'updated' => 0,
            'skipped' => 0,
            'messages' => [],
        ];

        foreach ($items as $i => $row) {
            if (!is_array($row)) {
                $stats['errors']++;
                $stats['messages'][] = "Row {$i}: not an object";
                continue;
            }
            try {
                if ($forceSource !== null && $forceSource !== '') {
                    $row['source_type'] = $forceSource;
                }
                self::validateRow($row);
                $stats['valid']++;
                if ($dryRun) {
                    continue;
                }
                // Pack imports are always canonical universal content
                $row['visibility'] = 'universal';
                $row['is_universal'] = 1;
                $row['review_status'] = $row['review_status'] ?? 'none';
                $row['owner_user_id'] = null;
                if (empty($row['status'])) {
                    $row['status'] = 'published';
                }
                // Map rich-content aliases
                if (!empty($row['key_points']) && empty($row['strong_answer_covers'])) {
                    $row['strong_answer_covers'] = $row['key_points'];
                }
                if (!empty($row['short_answer']) && empty($row['example_answer'])) {
                    // Keep both; short_answer is primary brief answer
                }
                $result = InterviewQuestionRepo::upsert($row, [], $allowUpdate);
                if ($result['action'] === 'created') {
                    $stats['new']++;
                } elseif ($result['action'] === 'updated') {
                    $stats['updated']++;
                } else {
                    $stats['duplicates']++;
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['messages'][] = 'Row ' . $i . ': ' . $e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function validateRow(array $row): void
    {
        $text = trim((string) ($row['question'] ?? $row['question_text'] ?? ''));
        if ($text === '') {
            throw new \InvalidArgumentException('missing question text');
        }
        $source = (string) ($row['source_type'] ?? 'kaamfit_original');
        if (!in_array($source, InterviewSchema::sourceTypes(), true)) {
            throw new \InvalidArgumentException('invalid source_type');
        }
        if ($source !== 'kaamfit_original' && $source !== 'public_domain') {
            $license = trim((string) ($row['license'] ?? ''));
            if ($license === '') {
                throw new \InvalidArgumentException('license required for non-original content');
            }
        }
        $type = (string) ($row['question_type'] ?? 'general');
        if (!in_array($type, InterviewSchema::questionTypes(), true)) {
            throw new \InvalidArgumentException('invalid question_type: ' . $type);
        }
    }

    /** @return list<array<string, mixed>> */
    private static function parseCsv(string $raw): array
    {
        $lines = preg_split('/\R/u', $raw) ?: [];
        if ($lines === []) {
            return [];
        }
        $header = str_getcsv(array_shift($lines) ?? '');
        $header = array_map(static fn($h) => trim((string) $h), $header);
        $out = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $cols[$i] ?? '';
            }
            foreach (['skills', 'industries', 'occupations', 'stages', 'answer_framework'] as $jsonKey) {
                if (!empty($row[$jsonKey]) && is_string($row[$jsonKey])) {
                    $decoded = json_decode($row[$jsonKey], true);
                    if (is_array($decoded)) {
                        $row[$jsonKey] = $decoded;
                    } else {
                        $row[$jsonKey] = array_values(array_filter(array_map('trim', explode('|', $row[$jsonKey]))));
                    }
                }
            }
            $out[] = $row;
        }
        return $out;
    }
}
