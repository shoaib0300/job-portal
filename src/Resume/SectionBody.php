<?php

declare(strict_types=1);

namespace KaamFit\Resume;

/**
 * Versioned structured JSON for resume_sections.body with plain-text fallback.
 *
 * Schema v1 shapes:
 * - entry lists: { schema_version, entries: [...] }
 * - skills:      { schema_version, categories: [...] }
 * - text blocks: { schema_version, text: "..." }
 */
final class SectionBody
{
    public const SCHEMA_VERSION = 1;

    /** Section keys that use entry-list schema. */
    public const ENTRY_TYPES = [
        'education',
        'certificates',
        'projects',
        'internships',
        'awards',
        'achievements',
        'volunteering',
        'publications',
        'references',
    ];

    /** @return 'structured'|'plain' */
    public static function detect(string $body): string
    {
        return self::decode($body) !== null ? 'structured' : 'plain';
    }

    /**
     * Decode structured JSON body. Returns null for plain text or invalid JSON.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(string $body): ?array
    {
        $trimmed = trim($body);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return null;
        }

        try {
            $data = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $version = (int) ($data['schema_version'] ?? 0);
        if ($version < 1) {
            return null;
        }

        if (isset($data['categories']) && is_array($data['categories'])) {
            return [
                'schema_version' => $version,
                'kind' => 'skills',
                'categories' => self::normalizeCategories($data['categories']),
            ];
        }

        if (array_key_exists('text', $data) && is_string($data['text'])) {
            return [
                'schema_version' => $version,
                'kind' => 'text',
                'text' => $data['text'],
            ];
        }

        if (isset($data['entries']) && is_array($data['entries'])) {
            return [
                'schema_version' => $version,
                'kind' => 'entries',
                'entries' => self::normalizeEntries($data['entries']),
            ];
        }

        return null;
    }

    /**
     * Encode structured data. Always writes schema_version = 1.
     *
     * @param array<string, mixed> $data
     */
    public static function encode(array $data): string
    {
        $out = ['schema_version' => self::SCHEMA_VERSION];

        if (isset($data['categories']) && is_array($data['categories'])) {
            $out['categories'] = self::normalizeCategories($data['categories']);
        } elseif (array_key_exists('text', $data)) {
            $out['text'] = (string) $data['text'];
        } elseif (isset($data['entries']) && is_array($data['entries'])) {
            $out['entries'] = self::normalizeEntries($data['entries']);
        } else {
            $out['text'] = '';
        }

        return (string) json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Whether a structured body has meaningful content (for empty-section filtering).
     */
    public static function hasContent(string $body): bool
    {
        $data = self::decode($body);
        if ($data === null) {
            return trim($body) !== '';
        }

        return match ($data['kind'] ?? '') {
            'skills' => self::categoriesHaveContent($data['categories'] ?? []),
            'text' => trim((string) ($data['text'] ?? '')) !== '',
            'entries' => self::entriesHaveContent($data['entries'] ?? []),
            default => false,
        };
    }

    /**
     * Map string fields in a section body through a callback (ATS / clean).
     * Preserves structured shape when input is structured; otherwise maps whole string.
     *
     * @param callable(string): string $mapper
     */
    public static function mapStrings(string $body, callable $mapper): string
    {
        $data = self::decode($body);
        if ($data === null) {
            return $mapper($body);
        }

        if (($data['kind'] ?? '') === 'skills') {
            $categories = [];
            foreach ($data['categories'] ?? [] as $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $skills = [];
                foreach ($cat['skills'] ?? [] as $skill) {
                    if (!is_array($skill)) {
                        continue;
                    }
                    $skills[] = [
                        'name' => $mapper((string) ($skill['name'] ?? '')),
                        'level' => $mapper((string) ($skill['level'] ?? '')),
                    ];
                }
                $categories[] = [
                    'name' => $mapper((string) ($cat['name'] ?? '')),
                    'skills' => $skills,
                ];
            }

            return self::encode(['categories' => $categories]);
        }

        if (($data['kind'] ?? '') === 'text') {
            return self::encode(['text' => $mapper((string) ($data['text'] ?? ''))]);
        }

        $entries = [];
        foreach ($data['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $mapped = $entry;
            foreach (['id', 'title', 'institution', 'issuer', 'org', 'location', 'start', 'end', 'description', 'name', 'level', 'field', 'url'] as $field) {
                if (isset($mapped[$field]) && is_string($mapped[$field])) {
                    $mapped[$field] = $mapper($mapped[$field]);
                }
            }
            $entries[] = $mapped;
        }

        return self::encode(['entries' => $entries]);
    }

    /**
     * @param list<mixed> $categories
     * @return list<array{name: string, skills: list<array{name: string, level: string}>}>
     */
    private static function normalizeCategories(array $categories): array
    {
        $out = [];
        foreach ($categories as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $skills = [];
            $rawSkills = $cat['skills'] ?? [];
            if (is_string($rawSkills)) {
                foreach (preg_split('/\s*[·|,;]\s*/u', $rawSkills) ?: [] as $part) {
                    $part = trim((string) $part);
                    if ($part !== '') {
                        $skills[] = ['name' => $part, 'level' => ''];
                    }
                }
            } elseif (is_array($rawSkills)) {
                foreach ($rawSkills as $skill) {
                    if (is_string($skill)) {
                        $name = trim($skill);
                        if ($name !== '') {
                            $skills[] = ['name' => $name, 'level' => ''];
                        }
                        continue;
                    }
                    if (!is_array($skill)) {
                        continue;
                    }
                    $name = trim((string) ($skill['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $skills[] = [
                        'name' => $name,
                        'level' => trim((string) ($skill['level'] ?? '')),
                    ];
                }
            }
            $out[] = [
                'name' => trim((string) ($cat['name'] ?? '')),
                'skills' => $skills,
            ];
        }

        return $out;
    }

    /**
     * @param list<mixed> $entries
     * @return list<array<string, string>>
     */
    private static function normalizeEntries(array $entries): array
    {
        $out = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $row = [];
            foreach ($entry as $k => $v) {
                if (!is_string($k)) {
                    continue;
                }
                if (is_scalar($v) || $v === null) {
                    $row[$k] = trim((string) ($v ?? ''));
                }
            }
            if ($row === []) {
                continue;
            }
            if (!isset($row['id']) || $row['id'] === '') {
                $row['id'] = bin2hex(random_bytes(4));
            }
            $out[] = $row;
        }

        return $out;
    }

    /** @param list<array{name?: string, skills?: list<array{name?: string}>}> $categories */
    private static function categoriesHaveContent(array $categories): bool
    {
        foreach ($categories as $cat) {
            if (trim((string) ($cat['name'] ?? '')) !== '') {
                return true;
            }
            foreach ($cat['skills'] ?? [] as $skill) {
                if (trim((string) ($skill['name'] ?? '')) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<array<string, string>> $entries */
    private static function entriesHaveContent(array $entries): bool
    {
        foreach ($entries as $entry) {
            foreach ($entry as $k => $v) {
                if ($k === 'id') {
                    continue;
                }
                if (trim((string) $v) !== '') {
                    return true;
                }
            }
        }

        return false;
    }
}
