<?php

declare(strict_types=1);

namespace KaamFit\Cover;

/**
 * Versioned structured JSON for cover_letters.body with plain-text fallback.
 *
 * Schema v1:
 * {
 *   "schema_version": 1,
 *   "sender": { "name", "address", "email", "phone" },
 *   "recipient": { "company", "name", "address" },
 *   "date": "",
 *   "subject": "",
 *   "greeting": "",
 *   "paragraphs": [{ "id", "text" }],
 *   "closing": "",
 *   "signoff": "",
 *   "signature": ""
 * }
 */
final class CoverBody
{
    public const SCHEMA_VERSION = 1;

    /** @return 'structured'|'plain' */
    public static function detect(string $body): string
    {
        return self::decode($body) !== null ? 'structured' : 'plain';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $body): ?array
    {
        $trimmed = trim($body);
        if ($trimmed === '' || ($trimmed[0] ?? '') !== '{') {
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

        // Must look like a cover letter structure (not a resume section body).
        $hasCoverShape = isset($data['paragraphs'])
            || isset($data['greeting'])
            || isset($data['signoff'])
            || isset($data['closing'])
            || isset($data['subject'])
            || isset($data['recipient'])
            || isset($data['sender']);
        if (!$hasCoverShape) {
            return null;
        }

        return self::normalize($data, $version);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function encode(array $data): string
    {
        $norm = self::normalize($data, self::SCHEMA_VERSION);
        unset($norm['kind']); // internal only

        return (string) json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Flatten structured body to plain text (for ATS / legacy consumers / tailor).
     */
    public static function toPlain(string $body): string
    {
        $data = self::decode($body);
        if ($data === null) {
            return $body;
        }

        $parts = [];
        $date = trim((string) ($data['date'] ?? ''));
        if ($date !== '') {
            $parts[] = $date;
        }
        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject !== '') {
            $parts[] = 'Subject: ' . $subject;
        }
        $greeting = trim((string) ($data['greeting'] ?? ''));
        if ($greeting !== '') {
            $parts[] = $greeting;
        }
        foreach ($data['paragraphs'] ?? [] as $p) {
            if (!is_array($p)) {
                continue;
            }
            $text = trim((string) ($p['text'] ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        $closing = trim((string) ($data['closing'] ?? ''));
        if ($closing !== '') {
            $parts[] = $closing;
        }
        $signoff = trim((string) ($data['signoff'] ?? ''));
        $signature = trim((string) ($data['signature'] ?? ''));
        if ($signoff !== '' || $signature !== '') {
            $block = trim($signoff . ($signoff !== '' && $signature !== '' ? "\n" : '') . $signature);
            if ($block !== '') {
                $parts[] = $block;
            }
        }

        return implode("\n\n", $parts);
    }

    public static function hasContent(string $body): bool
    {
        return trim(self::toPlain($body)) !== '';
    }

    /**
     * Soft-upgrade plain letter text into structured JSON without inventing content.
     * Splits on blank lines into greeting / paragraphs / closing heuristics.
     */
    public static function fromPlain(string $plain): array
    {
        $plain = trim($plain);
        if ($plain === '') {
            return self::emptyStructure();
        }

        $blocks = preg_split("/\n{2,}/", $plain) ?: [];
        $blocks = array_values(array_filter(array_map('trim', $blocks), static fn(string $b): bool => $b !== ''));

        $greeting = '';
        $signoff = '';
        $signature = '';
        $closing = '';
        $paragraphs = [];

        if ($blocks !== []) {
            $first = $blocks[0];
            if (preg_match('/^(dear|hallo|sehr geehrte|hello|hi)\b/iu', $first) && substr_count($first, "\n") < 2) {
                $greeting = $first;
                array_shift($blocks);
            }
        }

        if (count($blocks) >= 2) {
            $last = $blocks[count($blocks) - 1];
            if (preg_match('/^(with kind regards|kind regards|best regards|sincerely|yours|mit freundlichen|freundliche grüße|mfg)\b/iu', $last)
                || (substr_count($last, "\n") <= 2 && mb_strlen($last) < 120)
            ) {
                $lines = preg_split("/\R/u", $last) ?: [];
                $signoff = trim((string) ($lines[0] ?? ''));
                $signature = trim(implode("\n", array_slice($lines, 1)));
                array_pop($blocks);
            }
        }

        if ($blocks !== []) {
            $maybeClosing = $blocks[count($blocks) - 1];
            if (count($blocks) > 1 && mb_strlen($maybeClosing) < 280) {
                // Keep as a normal paragraph unless clearly a short closer — leave as paragraph.
            }
        }

        foreach ($blocks as $block) {
            $paragraphs[] = [
                'id' => bin2hex(random_bytes(4)),
                'text' => $block,
            ];
        }

        $data = self::emptyStructure();
        $data['greeting'] = $greeting;
        $data['paragraphs'] = $paragraphs !== [] ? $paragraphs : [['id' => bin2hex(random_bytes(4)), 'text' => $plain]];
        $data['closing'] = $closing;
        $data['signoff'] = $signoff;
        $data['signature'] = $signature;

        return $data;
    }

    /**
     * @param callable(string): string $mapper
     */
    public static function mapStrings(string $body, callable $mapper): string
    {
        $data = self::decode($body);
        if ($data === null) {
            return $mapper($body);
        }

        foreach (['date', 'subject', 'greeting', 'closing', 'signoff', 'signature'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = $mapper($data[$key]);
            }
        }
        foreach (['sender', 'recipient'] as $block) {
            if (!isset($data[$block]) || !is_array($data[$block])) {
                continue;
            }
            foreach ($data[$block] as $k => $v) {
                if (is_string($v)) {
                    $data[$block][$k] = $mapper($v);
                }
            }
        }
        $paras = [];
        foreach ($data['paragraphs'] ?? [] as $p) {
            if (!is_array($p)) {
                continue;
            }
            $paras[] = [
                'id' => (string) ($p['id'] ?? bin2hex(random_bytes(4))),
                'text' => $mapper((string) ($p['text'] ?? '')),
            ];
        }
        $data['paragraphs'] = $paras;

        return self::encode($data);
    }

    /** @return array<string, mixed> */
    public static function emptyStructure(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'kind' => 'cover',
            'sender' => ['name' => '', 'address' => '', 'email' => '', 'phone' => ''],
            'recipient' => ['company' => '', 'name' => '', 'address' => ''],
            'date' => '',
            'subject' => '',
            'greeting' => '',
            'paragraphs' => [
                ['id' => bin2hex(random_bytes(4)), 'text' => ''],
            ],
            'closing' => '',
            'signoff' => '',
            'signature' => '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data, int $version): array
    {
        $sender = is_array($data['sender'] ?? null) ? $data['sender'] : [];
        $recipient = is_array($data['recipient'] ?? null) ? $data['recipient'] : [];
        $paragraphs = [];
        foreach ($data['paragraphs'] ?? [] as $p) {
            if (is_string($p)) {
                $paragraphs[] = ['id' => bin2hex(random_bytes(4)), 'text' => $p];
                continue;
            }
            if (!is_array($p)) {
                continue;
            }
            $id = trim((string) ($p['id'] ?? ''));
            if ($id === '') {
                $id = bin2hex(random_bytes(4));
            }
            $paragraphs[] = [
                'id' => $id,
                'text' => (string) ($p['text'] ?? ''),
            ];
        }

        return [
            'schema_version' => max(1, $version),
            'kind' => 'cover',
            'sender' => [
                'name' => trim((string) ($sender['name'] ?? '')),
                'address' => trim((string) ($sender['address'] ?? '')),
                'email' => trim((string) ($sender['email'] ?? '')),
                'phone' => trim((string) ($sender['phone'] ?? '')),
            ],
            'recipient' => [
                'company' => trim((string) ($recipient['company'] ?? '')),
                'name' => trim((string) ($recipient['name'] ?? '')),
                'address' => trim((string) ($recipient['address'] ?? '')),
            ],
            'date' => trim((string) ($data['date'] ?? '')),
            'subject' => trim((string) ($data['subject'] ?? '')),
            'greeting' => trim((string) ($data['greeting'] ?? '')),
            'paragraphs' => $paragraphs,
            'closing' => trim((string) ($data['closing'] ?? '')),
            'signoff' => trim((string) ($data['signoff'] ?? '')),
            'signature' => trim((string) ($data['signature'] ?? '')),
        ];
    }
}
