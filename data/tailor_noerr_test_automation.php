<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Noerr Test Automation Engineer (München).
 * Note: JD asks for several years of test management/automation — light tailor, no overclaim.
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'Noerr';
$role = 'Test Automation Engineer';
$location = 'München, Germany';

$jd = <<<'TXT'
Test Automation Engineer (w/m/d)
Noerr · München · ERP system / internal IT platform
Contact: Julia Skilandat, HR-Business Partner · +49 (89) 28628-2617

Tasks
- Design, align, and develop project-specific test strategies, methodologies, and concepts
- Plan, steer, and monitor the full test process incl. quality gates and defect management
- Strong focus on test automation and selecting/using suitable tools
- Steer and monitor automated component, integration, system, load and performance tests; evaluate results
- Coordinate test execution with project leads, business units, and business analysts
- Build and establish automated test processes, standards, and tools

Requirements
- Several years of experience in test management and test automation
- Confident with test methods/tools and selecting/adapting test strategies
- ISTQB certification a plus
- Analytical thinking, structured working style
- Strong teamwork and communication skills

Offers: challenging technical environment, modern IT landscape, market-aligned pay, flexible hours, mobile work, young dynamic team
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'QA Engineer | Manual, API & Test Automation Focus';
$snapshot['location'] = $location;

$summary = 'QA Engineer with hands-on experience in structured software testing, defect management, and collaboration with developers across web, desktop, and mobile applications. Experienced in designing and executing test cases, documenting results, and using tools such as Playwright, Postman, and Apache JMeter for automation-oriented and performance testing. Preparing for ISTQB Foundation Level. Seeking a Test Automation Engineer role in München to grow deeper into test strategy, automation tooling, and quality processes for complex business systems.';

$skills = "Testing & quality\nTest Case Design · Test Execution · Test Documentation · Defect Management · Regression Testing · Functional Testing · Exploratory Testing · API Testing · Performance / Load Testing · Quality Assurance · Agile collaboration · Structured test approach\n\nAutomation & tools\nPlaywright · Appium · Postman · Apache JMeter · Git · GitHub · Jira · Azure DevOps · Mantis · CI/CD basics · Microsoft Office\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL · SQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)\n\nCertification\nISTQB Foundation Level — Currently Preparing";

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
    'Test Automation Engineer — Noerr',
    $snapshot,
    $company,
    'Light tailor for Test Automation Engineer (München / Noerr ERP). Copy of Main. JD asks for multi-year senior experience — keep claims honest.',
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
Dear Julia Skilandat,

I am writing to apply for the Test Automation Engineer (w/m/d) position at Noerr in München. I am motivated by the chance to contribute to quality assurance for your internal ERP platform and to grow further in test automation, structured test processes, and close collaboration with project and business stakeholders.

In my QA roles at Neural Spark Tech, I designed and executed test cases, performed functional and regression testing, documented and tracked defects, and worked closely with developers to verify fixes. I also used Postman for API testing, Apache JMeter for performance/load testing, and automation-oriented tools such as Playwright and Appium. I am currently preparing for the ISTQB Foundation Level certification and am eager to deepen my experience in test strategy, automation tooling, and quality gates in a complex business-system environment.

I work in a structured, analytical way and communicate clearly in English while continuing to improve my German. I would welcome the opportunity to support your IT team in building reliable automated test processes for Noerr’s ERP landscape.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Test Automation Engineer — Noerr');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Contact: Julia Skilandat (+49 89 28628-2617). Note: JD expects several years of test management/automation experience.",
    ''
);

echo "OK Noerr application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
