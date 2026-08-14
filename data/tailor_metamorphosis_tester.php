<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for metamorphosis Software Tester (Werkstudent / part-time).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'metamorphosis';
$role = 'Software Tester (Teilzeit / Werkstudent)';
$location = 'Germany'; // Office / Hybrid / Remote — no city specified

$jd = <<<'TXT'
Software Tester (m/w/d)
metamorphosis · Teilzeit / Werkstudent · Büro / Hybrid / Remote · Ab sofort
Medical technology: test software using past surgery X-ray images and an interactive in-house simulator.

Tasks
- Test software based on existing X-ray images
- Test software using an interactive simulator
- Ensure software works according to its definition / quality requirements

Profile
- Eye for detail; ensure software meets strict quality requirements
- Curiosity for medical knowledge and human anatomy (medical prior knowledge not required — they train you)
- Enjoy discussion / collaboration
- Confident English in speaking and writing

Advantageous: software testing experience

Offers: medtech future industry, training, competitive pay, flexible hours, societal impact
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Software Tester | Manual QA · MedTech Interest';
$snapshot['location'] = $location;

$summary = 'Detail-oriented Software Tester / QA Engineer with hands-on experience in manual testing, exploratory testing, and clear defect documentation for web, desktop, and mobile applications. Strong attention to quality and structured collaboration with developers. Computer Science student at Hochschule Schmalkalden (almost finished — one semester remaining). Seeking a part-time / Werkstudent Software Tester role to contribute to medical technology software quality, including image- and simulator-based testing, while growing in a hybrid/remote-friendly team.';

$skills = "Testing\nManual Testing · Exploratory Testing · Functional Testing · Regression Testing · Smoke Testing · Test Case Design · Test Execution · Test Documentation · Defect Reporting · Defect Life Cycle · Quality Assurance · Attention to Detail · Cross-platform Testing · UAT · API Testing\n\nTools\nPostman · Playwright · Apache JMeter · Appium · Jira · Azure DevOps · Mantis · Git · GitHub · Microsoft Office · SQL\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL\n\nLanguages\nEnglish — Confident / Professional (C1) · German — Basic / improving (A2+)";

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
    'Software Tester — metamorphosis',
    $snapshot,
    $company,
    'Light tailor for Software Tester Werkstudent/Teilzeit (medtech / X-ray + simulator). Copy of Main.',
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
Dear Hiring Team at metamorphosis,

I am writing to apply for the Software Tester (m/w/d) position (Teilzeit / Werkstudent). I am excited by the chance to help ensure software excellence in medical technology — including testing based on real surgical X-ray images and your interactive simulator — and to contribute to products that can improve patient care.

In my QA roles at Neural Spark Tech, I manually tested web, desktop, and mobile applications with a strong focus on detail: designing and executing test cases, exploring edge cases, documenting defects clearly, and collaborating with developers to verify fixes. I enjoy figuring out why something feels “off” and turning that into actionable quality feedback. Medical prior knowledge is not required for this role, and I am eager to learn the anatomy and clinical context you teach so I can test more effectively.

I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I communicate confidently in English, work carefully and reliably, and appreciate flexible hybrid/remote arrangements that fit alongside my studies. I would welcome the opportunity to join your team and grow as a Software Tester in medtech.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Software Tester — metamorphosis');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location . ' (Hybrid/Remote)', $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Teilzeit/Werkstudent. Office/Hybrid/Remote. Medtech X-ray + simulator testing.",
    ''
);

echo "OK metamorphosis application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
