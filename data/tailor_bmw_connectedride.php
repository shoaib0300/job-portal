<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for BMW Motorrad ConnectedRide Werkstudent SQA (Munich).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'BMW Group';
$role = 'Werkstudent Software Quality Assurance BMW Motorrad ConnectedRide';
$location = 'Munich, Germany';

$jd = <<<'TXT'
Werkstudent Software Quality Assurance BMW Motorrad ConnectedRide (w/m/x)
BMW Group · Munich, Germany · Internship / working student · Part-time
Start: earliest 01.07.2026 · Duration: 12 months
Team: ZX-EE-6 — BMW Motorrad ConnectedRide (app, navigation, connected riding)

What you'll do
- Structured QA and scaling of the BMW Motorrad Connected App through comprehensive testing
- Work with Product Owners on clarifying and tracking requirements
- Requirements management: prepare, structure, and track specifications
- Plan and execute software tests (test cases, regression tests)
- Analyze defects and support QA across the development process
- Support evaluation of AI-based tools for test automation and defect analysis
- Collaborate with development, Product Owners, and external partners

What you bring
- Studies in Computer Science, Business Informatics, Software Engineering, Data Science or comparable
- Interest in software testing, mobile apps, and QA
- First knowledge of test methods and software/app development a plus
- Analytical thinking and structured problem-solving
- Interest in AI in development/testing
- Motorcycle license a plus
- Very good German or English

Offers: mentoring, flexible hours, mobile work, fair pay, student apartments (Munich, subject to availability)
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Werkstudent Software Quality Assurance | Mobile App Testing';
$snapshot['location'] = $location;

$summary = 'Computer Science student (Hochschule Schmalkalden; almost finished — one semester remaining) with hands-on experience in software quality assurance for web, mobile (iOS/Android), and desktop applications. Skilled in designing and executing test cases, regression testing, defect analysis, and collaborating with developers and stakeholders. Strong interest in mobile app quality and structured QA processes. Seeking a Werkstudent Software Quality Assurance role in Munich to support BMW Motorrad ConnectedRide digital products.';

$skills = "Testing & QA\nManual Testing · Functional Testing · Regression Testing · Smoke Testing · Exploratory Testing · Mobile Testing (iOS & Android) · Test Case Design · Test Execution · Defect Analysis · Defect Reporting · Requirement Review · Test Documentation · Quality Assurance · UAT · API Testing · Cross-platform Testing · Agile Scrum\n\nTools\nPostman · Playwright · Appium · Apache JMeter · Jira · Azure DevOps · Mantis · Git · GitHub · Microsoft Office · SQL\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

foreach ($snapshot['sections'] as &$section) {
    if (!is_array($section)) {
        continue;
    }
    $key = (string) ($section['section_key'] ?? '');
    if ($key === 'summary') {
        $section['body'] = $summary;
    }
    if ($key === 'skills') {
        $section['body'] = $skills;
    }
}
unset($section);

$resumeId = Versions::saveResumeVersion(
    'Werkstudent SQA — BMW Motorrad ConnectedRide',
    $snapshot,
    $company,
    'Light tailor for Werkstudent SQA ConnectedRide (Munich). Copy of Main.',
    false,
    null,
    true
);
Versions::loadResumeVersion($resumeId);

$baseCover = Versions::baseCoverLetter();
if ($baseCover === null) {
    fwrite(STDERR, "No Main cover letter\n");
    exit(1);
}

$coverBody = <<<TXT
Dear Hiring Team at BMW Group,

I am writing to apply for the Werkstudent Software Quality Assurance position with BMW Motorrad ConnectedRide (ZX-EE-6) in Munich. I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I am available for a 12-month part-time role from July 2026 and would be glad to support structured QA for the Connected App, navigation, and connected riding experiences.

In my QA roles at Neural Spark Tech, I designed and executed test cases, performed regression and exploratory testing for web, desktop, and mobile applications (iOS and Android), analyzed and tracked defects, and collaborated closely with developers to verify fixes. I also have experience documenting test results clearly and reviewing application behavior against requirements — skills I would bring to test planning, regression testing, and requirements follow-up with Product Owners.

I am especially interested in mobile app quality and in learning how AI-based tools can support test automation and defect analysis. I communicate well in English and continue to improve my German. I would welcome the opportunity to contribute to your ConnectedRide team with a structured, quality-focused approach and curiosity for innovative digital motorcycle experiences.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Werkstudent SQA — BMW Motorrad ConnectedRide');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Start from 01.07.2026, 12 months part-time. Team ZX-EE-6 ConnectedRide.",
    'https://www.bmw.jobs/'
);

echo "OK BMW application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
