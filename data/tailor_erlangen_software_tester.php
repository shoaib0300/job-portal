<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Software-Tester (m/w/d) in Erlangen (industry/manufacturing client).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'Industry Client (Erlangen)';
$role = 'Software-Tester';
$location = 'Erlangen, Germany';

$jd = <<<'TXT'
Software-Tester (m/w/d)
Erlangen, Bavaria, Germany · Full-time · Temporary employment
Client in industry & manufacturing

Tasks
- Plan and execute software tests to ensure quality
- Analyze and document test results
- Create and maintain test cases and test plans
- Identify and report software defects
- Work closely with developers on bug fixing
- Improve test processes and methods
- Create reports and documentation of test results

Requirements
- Completed training or degree in Computer Science, Software Development, or comparable
- Experience in software testing and creating test plans
- Knowledge of test automation tools and methods
- Analytical thinking and structured working style
- Good communication skills and team spirit

Package: Varied work in technology department; temporary role with interesting tasks
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Software-Tester | Manual Testing & Test Documentation';
$snapshot['location'] = $location;

$summary = 'Software Tester / QA Engineer with hands-on experience planning and executing software tests, creating and maintaining test cases and test plans, analyzing results, and documenting defects clearly. Experienced collaborating with developers to reproduce and verify fixes, and improving QA processes. Computer Science student at Hochschule Schmalkalden (almost finished — one semester remaining). Seeking a full-time Software-Tester role in Erlangen to contribute structured quality assurance in a technically demanding environment.';

$skills = "Testing\nManual Testing · Functional Testing · Regression Testing · Smoke Testing · Exploratory Testing · Test Planning · Test Case Design · Test Execution · Test Result Analysis · Test Documentation · Defect Reporting · Defect Life Cycle · Quality Assurance · UAT · API Testing · Performance Testing · Cross-platform Testing · Agile Scrum\n\nAutomation & tools\nPlaywright · Appium · Postman · Apache JMeter · Jira · Azure DevOps · Mantis · Git · GitHub · Microsoft Office · SQL · CI/CD basics\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Software-Tester — Erlangen',
    $snapshot,
    $company,
    'Light tailor for Software-Tester (Erlangen / industry client). Copy of Main.',
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
Dear Hiring Team,

I am writing to apply for the Software-Tester (m/w/d) position in Erlangen. I bring hands-on experience in planning and executing software tests, creating and maintaining test cases and test plans, analyzing and documenting results, and working closely with developers to resolve defects.

In my QA roles at Neural Spark Tech, I tested web, desktop, and mobile applications, designed and executed test cases, tracked defects through the full lifecycle, and contributed to clearer QA documentation and processes. I also have exposure to test automation tools (e.g. Playwright, Appium) and API/performance testing, and I am motivated to keep improving test methods in a technically demanding industrial environment.

I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I work in a structured, analytical way, communicate clearly, and enjoy collaborating in a team. I would welcome the opportunity to support your technology department as a Software-Tester in Erlangen.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Software-Tester — Erlangen');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Full-time temporary. Client in industry/manufacturing.",
    ''
);

echo "OK Erlangen Software-Tester application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
