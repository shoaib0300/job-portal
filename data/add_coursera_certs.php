<?php

declare(strict_types=1);

/**
 * Add Coursera certificates to live resume, Main, and every saved resume copy.
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Db::pdo();
Versions::ensureSchema();

$certificates = "Software Testing Fundamentals for ISTQB Exams Prep Specialization — Coursera\nGoogle IT Support — Coursera\nTools & Techniques for Performance & Load Testing — Test Automation University\nAPI Test Automation with Postman — Test Automation University\nISTQB Foundation Level — Currently Preparing";

$pdo->prepare(
    'UPDATE resume_sections SET title = ?, body = ? WHERE section_key = ?'
)->execute(['Certificates', $certificates, 'projects']);

$updatedLive = (int) $pdo->query(
    'SELECT COUNT(*) FROM resume_sections WHERE section_key = \'projects\''
)->fetchColumn();

$versions = $pdo->query('SELECT id, title, snapshot FROM resume_versions')->fetchAll();
$updVersion = $pdo->prepare('UPDATE resume_versions SET snapshot = ?, profile_title = ? WHERE id = ?');
$updatedVersions = 0;

foreach ($versions as $row) {
    $snapshot = Versions::decodeSnapshot((string) $row['snapshot']);
    $found = false;
    foreach ($snapshot['sections'] as &$section) {
        if (!is_array($section)) {
            continue;
        }
        if (($section['section_key'] ?? '') === 'projects') {
            $section['title'] = 'Certificates';
            $section['body'] = $certificates;
            $found = true;
        }
    }
    unset($section);
    if (!$found) {
        $snapshot['sections'][] = [
            'section_key' => 'projects',
            'title' => 'Certificates',
            'body' => $certificates,
            'sort_order' => 50,
            'visible' => 1,
        ];
    }
    $updVersion->execute([
        Versions::encodeSnapshot($snapshot),
        (string) ($snapshot['profile_title'] ?? ''),
        (int) $row['id'],
    ]);
    $updatedVersions++;
}

// Refresh Main from live if Main exists and live is currently Main (or always sync Main certificates via versions loop already done)
$base = Versions::baseResumeVersion();
echo "OK certificates updated\n";
echo "live_section={$updatedLive}\n";
echo "resume_versions={$updatedVersions}\n";
echo 'main_id=' . ($base ? (int) $base['id'] : 0) . "\n";
