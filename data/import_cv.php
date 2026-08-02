<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Db::pdo();

// Ensure experience table exists
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS experience_entries (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      company VARCHAR(200) NOT NULL DEFAULT \'\',
      position VARCHAR(200) NOT NULL DEFAULT \'\',
      location VARCHAR(160) NOT NULL DEFAULT \'\',
      start_date VARCHAR(40) NOT NULL DEFAULT \'\',
      end_date VARCHAR(40) NOT NULL DEFAULT \'\',
      bullets MEDIUMTEXT NOT NULL,
      sort_order INT NOT NULL DEFAULT 0,
      visible TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$links = json_encode([], JSON_UNESCAPED_SLASHES);

$pdo->prepare(
    'UPDATE resume_profile SET
        full_name = ?,
        title = ?,
        email = ?,
        phone = ?,
        location = ?,
        gender = ?,
        date_of_birth = ?,
        country = ?,
        nationality = ?,
        show_photo = ?,
        links = ?
     WHERE id = 1'
)->execute([
    'Muqaddas Nasim Khan',
    'QA Engineer',
    'muqaddasnasimkhan04@gmail.com',
    '+49 163 9064691',
    'Erfurt, Germany',
    '',
    null,
    'Germany',
    '',
    0,
    $links,
]);

$sections = [
    [
        'summary',
        'Summary',
        'QA Engineer with hands-on experience testing web, mobile, and API layers in Agile environments. Skilled in designing and executing test cases, identifying and documenting bugs, and collaborating closely with development teams to ensure high software quality. Experienced in functional, regression, exploratory, and integration testing across web and mobile applications. Detail-oriented and self-organized, with a passion for improving test processes and delivering reliable, user-friendly software.',
        10,
        1,
    ],
    [
        'experience',
        'Experience',
        '',
        20,
        1,
    ],
    [
        'skills',
        'Skills',
        "Professional Skills\nManual & automation testing · Agile & Scrum · UI & API testing · Performance & load testing · Mobile app testing · Web & desktop app testing · Test case design · Bug tracking · SDLC · AI-assisted testing\n\nTools\nPlaywright · Postman · JMeter · Appium · Visual Studio · Jira · GitHub · CI/CD pipelines · Zephyr · Azure DevOps · Mantis · Microsoft Office · SQL Server\n\nProgramming Languages\nPython · C# · Java",
        30,
        1,
    ],
    [
        'education',
        'Education',
        "MS (Computer Science) — Hochschule Schmalkalden – University of Applied Sciences, Schmalkalden, Germany (Oct 2022–Present)\n\nBS (Computer Science) — Lahore Leads University, Lahore, Pakistan (2017–2021)",
        40,
        1,
    ],
    [
        'projects',
        'Certificates',
        "Tools & Techniques for Performance & Load Testing — Test Automation University\nAPI Test Automation with Postman — Test Automation University\n(Planned) ISTQB Foundation Level — preparing for certification",
        50,
        1,
    ],
    [
        'languages',
        'Languages',
        "English: C1\nGerman: A2 (actively learning)",
        60,
        1,
    ],
];

$upd = $pdo->prepare(
    'UPDATE resume_sections SET title = ?, body = ?, sort_order = ?, visible = ? WHERE section_key = ?'
);
$ins = $pdo->prepare(
    'INSERT INTO resume_sections (section_key, title, body, sort_order, visible) VALUES (?, ?, ?, ?, ?)'
);

foreach ($sections as [$key, $title, $body, $order, $visible]) {
    $check = $pdo->prepare('SELECT id FROM resume_sections WHERE section_key = ?');
    $check->execute([$key]);
    if ($check->fetch()) {
        $upd->execute([$title, $body, $order, $visible, $key]);
    } else {
        $ins->execute([$key, $title, $body, $order, $visible]);
    }
}

$pdo->exec('DELETE FROM experience_entries');
$exp = $pdo->prepare(
    'INSERT INTO experience_entries (company, position, location, start_date, end_date, bullets, sort_order, visible)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
);

$exp->execute([
    'NST Neural Spark Tech',
    'Software Quality Assurance',
    'Lahore, Pakistan',
    'Oct 2025',
    'Dec 2025',
    "• Worked on customer service web and desktop application\n• Performed black box testing\n• Bug reporting and test case execution\n• Participation in QA review of standards, procedures and process",
    10,
]);

$exp->execute([
    'NST Neural Spark Tech',
    'Associate Software Quality Assurance',
    'Lahore, Pakistan',
    'Sep 2021',
    'Feb 2022',
    "• Quality Assurance and comprehensive testing of web and mobile application (iOS and Android)\n• Responsible for writing and verifying test cases and test scenarios using MS Word and excel\n• Performed manual testing (Regression, functional and UAT testing)\n• Performed Automation API and Performance testing using JMeter & Postman\n• Wrote script for deployments\n• Bug reporting through a Mantis bug/defect tracking tool\n• SQL Queries for data retrieval and data testing\n• Email testing using SMTP server",
    20,
]);

$cover = <<<'TXT'
Dear Hiring Manager,

I am writing to express my interest in a QA Engineer / Software Quality Assurance role on your team. I bring hands-on experience testing web, mobile, and API layers in Agile environments, with a strong focus on clear test design, careful bug documentation, and close collaboration with development teams.

At NST Neural Spark Tech I tested customer-facing web and desktop applications as well as iOS and Android apps. My work covered functional, regression, exploratory, UAT, API, and performance testing using tools such as Postman, JMeter, Mantis, and SQL. I am comfortable owning test cases end to end — from writing scenarios to execution, defect tracking, and follow-up.

I am currently pursuing an MS in Computer Science at Hochschule Schmalkalden in Germany, and I am based in Erfurt. I am especially motivated by teams that value reliable releases, practical automation, and continuous improvement of QA processes. I would welcome the chance to discuss how my background can support your product quality goals.

Thank you for your time and consideration.

Sincerely,
Muqaddas Nasim Khan
TXT;

$pdo->exec('UPDATE cover_letters SET is_active = 0');
$existsCover = $pdo->query('SELECT id FROM cover_letters ORDER BY id ASC LIMIT 1')->fetch();
if ($existsCover) {
    $pdo->prepare(
        'UPDATE cover_letters SET title = ?, body = ?, company = ?, is_active = 1 WHERE id = ?'
    )->execute(['QA Engineer Cover Letter', $cover, '', (int) $existsCover['id']]);
} else {
    $pdo->prepare(
        'INSERT INTO cover_letters (title, body, company, is_active) VALUES (?, ?, ?, 1)'
    )->execute(['QA Engineer Cover Letter', $cover, '']);
}

App::setSetting('active_company', '');
App::setSetting('theme', 'sage');
App::setSetting('font_family', 'candara');
App::setSetting('accent_color', '#4E6351');

$pdo->prepare(
    'INSERT INTO search_history (company, role, note) VALUES (?, ?, ?)'
)->execute([
    'Personal CV import',
    'QA Engineer',
    'Re-imported mnk_testing_cv_.pdf into profile, structured experience, sections, and cover letter.',
]);

echo 'OK ' . App::profile()['full_name'] . "\n";
echo 'experiences=' . count(App::experiences()) . "\n";
echo 'cover=' . (App::activeCoverLetter()['title'] ?? '') . "\n";
