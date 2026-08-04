<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Db::pdo();
Versions::ensureSchema();

$body = <<<'TXT'
Dear Hiring Manager,

I am writing to apply for the QA Engineer / Software Quality Assurance role at your company. I bring hands-on experience in manual, API, and performance testing for web, mobile, and desktop applications, with a strong focus on structured test design, clear defect reporting, and close collaboration with developers in Agile environments.

At Neural Spark Tech, I tested customer-facing web and desktop applications using black-box, exploratory, regression, smoke, and sanity techniques. I designed and maintained detailed test cases, tracked defects through the full lifecycle, and partnered with developers to reproduce and validate fixes. In an earlier Associate SQA role, I also covered mobile (iOS/Android), API testing with Postman, performance testing with Apache JMeter, SQL-based data validation, and defect tracking in Mantis.

My earlier web development internship (PHP, WordPress, HTML/CSS/JavaScript, MySQL) helps me communicate clearly with engineers and spot quality risks earlier in the delivery cycle.

I am currently studying Computer Science at Hochschule Schmalkalden and am based in Germany, open to on-site, hybrid, or remote opportunities. I would welcome the chance to discuss how my testing experience and commitment to software quality can support your team.

Thank you for your time and consideration.

Sincerely,
Muqaddas Khan
TXT;

$pdo->exec('UPDATE cover_letters SET is_active = 0');
$exists = $pdo->query('SELECT id FROM cover_letters ORDER BY id ASC LIMIT 1')->fetch();
if ($exists) {
    $pdo->prepare(
        'UPDATE cover_letters SET title = ?, body = ?, company = ?, is_active = 1, is_base = 1 WHERE id = ?'
    )->execute(['Main cover letter', $body, '', (int) $exists['id']]);
    Versions::markCoverBase((int) $exists['id']);
} else {
    $pdo->prepare(
        'INSERT INTO cover_letters (title, body, company, is_active, is_base) VALUES (?, ?, ?, 1, 1)'
    )->execute(['Main cover letter', $body, '']);
}

echo "OK cover letter updated\n";
echo 'chars=' . strlen((string) (App::activeCoverLetter()['body'] ?? '')) . "\n";
