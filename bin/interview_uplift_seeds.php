#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Upgrade weak placeholder answers in interview seed JSON packs.
 * Prefer substantive short_answer + detailed_answer; keep IDs stable on re-import via slug+language.
 *
 *   php bin/interview_uplift_seeds.php
 *   php bin/interview_uplift_seeds.php --packs=universal,software,maritime,german
 *   php bin/interview_uplift_seeds.php --import   # also run importer --update
 */

$root = dirname(__DIR__);
$packsArg = null;
$doImport = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--packs=')) {
        $packsArg = substr($arg, 8);
    } elseif ($arg === '--import') {
        $doImport = true;
    }
}

$allPacks = [
    'universal', 'software', 'maritime', 'german', 'business', 'finance',
    'logistics', 'events', 'healthcare', 'engineering', 'marketing',
    'hospitality', 'education', 'legal', 'retail',
];
$packs = $packsArg !== null
    ? array_values(array_filter(array_map('trim', explode(',', $packsArg))))
    : ['universal', 'software', 'maritime', 'german', 'business'];

$weakPatterns = [
    'provide a relevant',
    'share a concise',
    'antworten sie',
    'give a clear example',
    'describe your approach briefly',
    'role-relevant example with a clear outcome',
    'what you would do next',
];

$isWeak = static function (?string $text) use ($weakPatterns): bool {
    if ($text === null || trim($text) === '' || mb_strlen(trim($text)) < 50) {
        return true;
    }
    $l = mb_strtolower($text);
    foreach ($weakPatterns as $w) {
        if (str_contains($l, $w)) {
            return true;
        }
    }
    return false;
};

$buildAnswer = static function (array $q, string $pack): array {
    $question = trim((string) ($q['question'] ?? $q['question_text'] ?? ''));
    $type = (string) ($q['question_type'] ?? 'general');
    $lang = strtolower((string) ($q['language'] ?? 'en'));
    $de = $lang === 'de';

    $topic = preg_replace('/^(can you |could you |please |tell me |explain |describe |what is |what are |how do you |how would you |warum |was ist |wie )/iu', '', mb_strtolower($question)) ?? $question;
    $topic = trim($topic, " ?.!");

    if ($type === 'behavioral' || $type === 'situational') {
        $short = $de
            ? 'Kurz mit STAR: Situation, Aufgabe, Handlung, Ergebnis — und was Sie daraus für diese Rolle mitnehmen.'
            : 'Answer with STAR: Situation, Task, Action, Result — then connect the learning to this role.';
        $detailed = $de
            ? "## Struktur (STAR)\n1. **Situation** — Kontext in 1–2 Sätzen\n2. **Aufgabe** — Ihre Verantwortung\n3. **Handlung** — konkrete Schritte, Tools, Stakeholder\n4. **Ergebnis** — messbarer Effekt + Lernen\n\n## Beispielrichtung\nWählen Sie ein reales Beispiel zu „{$topic}“. Nennen Sie Zahlen wo möglich (Zeit, Fehlerquote, Kosten, Zufriedenheit). Schließen Sie mit: „Deshalb würde ich in dieser Rolle …“"
            : "## STAR structure\n1. **Situation** — 1–2 sentences of context\n2. **Task** — your ownership\n3. **Action** — concrete steps, tools, stakeholders\n4. **Result** — measurable outcome + learning\n\n## Direction for this question\nPick a real example about \"{$topic}\". Quantify where you can (time, defect rate, cost, satisfaction). Close with how you would apply that approach in this role.";
        $framework = $de
            ? ['Situation', 'Aufgabe', 'Handlung', 'Ergebnis', 'Transfer zur Rolle']
            : ['Situation', 'Task', 'Action', 'Result', 'Transfer to this role'];
    } elseif ($type === 'technical' || $type === 'practical' || $pack === 'software' || $pack === 'engineering') {
        $short = $de
            ? "Definition in einem Satz, dann warum es wichtig ist, dann ein praxisnahes Beispiel zu „{$topic}“."
            : "Define it in one sentence, why it matters, then a practical example for \"{$topic}\".";
        $detailed = $de
            ? "## Kurzdefinition\nErklären Sie „{$topic}“ so, dass auch ein fachfremder Stakeholder folgt.\n\n## Warum es zählt\n- Risiko oder Kosten, die es reduziert\n- Qualität / Sicherheit / Lieferfähigkeit\n\n## Praxisbeispiel\nSchildern Sie einen konkreten Ablauf (Input → Schritte → Output → Kontrolle).\n\n## Abgrenzung\nNennen Sie 1–2 verwandte Begriffe und den Unterschied."
            : "## Definition\nExplain \"{$topic}\" so a non-specialist stakeholder can follow.\n\n## Why it matters\n- Risk or cost it reduces\n- Quality / safety / delivery impact\n\n## Practical example\nWalk through a concrete flow (input → steps → output → verification).\n\n## Boundaries\nName 1–2 related concepts and how they differ.";
        $framework = $de
            ? ['Definition', 'Warum relevant', 'Beispiel / Ablauf', 'Grenzen / Trade-offs']
            : ['Definition', 'Why it matters', 'Example / workflow', 'Trade-offs'];
    } elseif ($pack === 'maritime') {
        $short = $de
            ? "Sicherheits- und Compliance-Kontext zuerst, dann operativer Ablauf zu „{$topic}“."
            : "Lead with safety/compliance context, then the operational flow for \"{$topic}\".";
        $detailed = $de
            ? "## Sicherheit & Regeln\nWelche Vorschriften, Checklisten oder ISM-/Hafenregeln greifen?\n\n## Operativer Ablauf\nRollen an Bord / an Land, Kommunikation, Eskalation.\n\n## Risiken\nTypische Fehler und wie Sie sie vermeiden.\n\n## Nachweis\nDokumentation, Übergabe, Lessons learned."
            : "## Safety & rules\nWhich regulations, checklists, or ISM/port rules apply?\n\n## Operational flow\nRoles onboard/ashore, communication, escalation.\n\n## Risks\nCommon failure modes and how you prevent them.\n\n## Evidence\nDocumentation, handover, lessons learned.";
        $framework = ['Safety/compliance', 'Operational steps', 'Risks', 'Evidence'];
    } else {
        $short = $de
            ? "Klare Position zu „{$topic}“, Begründung, kurzes Beispiel, Bezug zur Stelle."
            : "Clear stance on \"{$topic}\", rationale, brief example, link to the role.";
        $detailed = $de
            ? "## Kernaussage\nEine klare Antwort auf die Frage.\n\n## Begründung\n2–3 fachliche oder organisatorische Argumente.\n\n## Beispiel\nKurzer Fall aus Studium, Beruf oder Projekt.\n\n## Bezug zur Rolle\nWarum das für den Arbeitgeber relevant ist."
            : "## Core answer\nState the answer directly.\n\n## Rationale\n2–3 professional or organisational reasons.\n\n## Example\nA short case from study, work, or a project.\n\n## Role fit\nWhy this matters for the employer.";
        $framework = $de
            ? ['Kernaussage', 'Begründung', 'Beispiel', 'Rollenbezug']
            : ['Core answer', 'Rationale', 'Example', 'Role fit'];
    }

    $keyPoints = $q['strong_answer_covers'] ?? $q['key_points'] ?? null;
    if (!is_array($keyPoints) || count($keyPoints) < 2) {
        $keyPoints = $de
            ? ['Klare Struktur', 'Konkretes Beispiel', 'Messbares Ergebnis oder Kriterien', 'Bezug zur ausgeschriebenen Rolle']
            : ['Clear structure', 'Concrete example', 'Measurable outcome or criteria', 'Link to the open role'];
    }

    return [
        'short_answer' => $short,
        'detailed_answer' => $detailed,
        'example_answer' => $detailed,
        'answer_framework' => $framework,
        'strong_answer_covers' => $keyPoints,
        'is_universal' => true,
        'visibility' => 'universal',
        'status' => 'published',
    ];
};

$updatedFiles = 0;
$updatedQuestions = 0;
foreach ($packs as $pack) {
    $path = $root . '/data/interview/v1/' . $pack . '/questions.json';
    if (!is_readable($path)) {
        fwrite(STDERR, "Skip missing {$path}\n");
        continue;
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        fwrite(STDERR, "Invalid JSON {$path}\n");
        continue;
    }
    $list = isset($raw['questions']) && is_array($raw['questions']) ? $raw['questions'] : $raw;
    $changed = 0;
    foreach ($list as $i => $q) {
        if (!is_array($q)) {
            continue;
        }
        $ex = (string) ($q['example_answer'] ?? '');
        $sh = (string) ($q['short_answer'] ?? '');
        $det = (string) ($q['detailed_answer'] ?? '');
        if (!$isWeak($ex) && !$isWeak($sh) && !$isWeak($det) && mb_strlen($det) > 120) {
            $list[$i]['visibility'] = 'universal';
            $list[$i]['is_universal'] = true;
            continue;
        }
        $up = $buildAnswer($q, $pack);
        $list[$i] = array_merge($q, $up);
        $changed++;
        $updatedQuestions++;
    }
    if (isset($raw['questions'])) {
        $raw['questions'] = $list;
        $out = $raw;
    } else {
        $out = $list;
    }
    file_put_contents(
        $path,
        json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    echo "Uplifted {$changed} in {$pack}\n";
    $updatedFiles++;
}

echo "Files touched: {$updatedFiles}, questions upgraded: {$updatedQuestions}\n";

if ($doImport) {
    require_once $root . '/src/bootstrap.php';
    passthru('php ' . escapeshellarg($root . '/bin/interview_import.php') . ' --dir=' . escapeshellarg($root . '/data/interview/v1') . ' --update', $code);
    exit($code);
}
