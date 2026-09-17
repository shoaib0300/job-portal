<?php

declare(strict_types=1);

/**
 * Render cover letter body — structured JSON or plain-text fallback.
 */
function render_cover_letter_body(string $body): void
{
    $data = \KaamFit\Cover\CoverBody::decode($body);
    if ($data === null) {
        echo App::nl2p($body);

        return;
    }

    echo '<div class="letter-structured" data-cover-structured="1">';

    $date = trim((string) ($data['date'] ?? ''));
    if ($date !== '') {
        echo '<p class="letter-date" data-cover-field="date">' . App::e($date) . '</p>';
    }

    $recipient = is_array($data['recipient'] ?? null) ? $data['recipient'] : [];
    $recLines = array_filter([
        trim((string) ($recipient['name'] ?? '')),
        trim((string) ($recipient['company'] ?? '')),
        trim((string) ($recipient['address'] ?? '')),
    ], static fn(string $l): bool => $l !== '');
    if ($recLines !== []) {
        echo '<div class="letter-recipient" data-cover-field="recipient">';
        foreach ($recLines as $line) {
            echo '<p>' . App::e($line) . '</p>';
        }
        echo '</div>';
    }

    $subject = trim((string) ($data['subject'] ?? ''));
    if ($subject !== '') {
        echo '<p class="letter-subject" data-cover-field="subject"><strong>' . App::e($subject) . '</strong></p>';
    }

    $greeting = trim((string) ($data['greeting'] ?? ''));
    if ($greeting !== '') {
        echo '<p class="letter-greeting" data-cover-field="greeting">' . App::e($greeting) . '</p>';
    }

    foreach ($data['paragraphs'] ?? [] as $p) {
        if (!is_array($p)) {
            continue;
        }
        $text = trim((string) ($p['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $id = App::e((string) ($p['id'] ?? ''));
        echo '<div class="letter-paragraph" data-cover-para="' . $id . '">' . App::nl2p($text) . '</div>';
    }

    $closing = trim((string) ($data['closing'] ?? ''));
    if ($closing !== '') {
        echo '<div class="letter-closing" data-cover-field="closing">' . App::nl2p($closing) . '</div>';
    }

    $signoff = trim((string) ($data['signoff'] ?? ''));
    $signature = trim((string) ($data['signature'] ?? ''));
    if ($signoff !== '' || $signature !== '') {
        echo '<div class="letter-signoff-block">';
        if ($signoff !== '') {
            echo '<p class="letter-signoff" data-cover-field="signoff">' . App::e($signoff) . '</p>';
        }
        if ($signature !== '') {
            echo '<div class="letter-signature" data-cover-field="signature">' . App::nl2p($signature) . '</div>';
        }
        echo '</div>';
    }

    echo '</div>';
}
