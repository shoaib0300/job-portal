<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for ExpressVPN / CyberGhost & PIA Manual Application Tester (Übach-Palenberg).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'ExpressVPN';
$role = 'Manual Application Tester';
$location = 'Übach-Palenberg, Germany';

$jd = <<<'TXT'
QA Tester / Manual Application Tester — Übach-Palenberg (Onsite)
Company: ExpressVPN (CyberGhost & Private Internet Access teams)
Full-time · 100% onsite

What you'll do
- Manually testing software on iOS, Android, Windows, macOS, Linux
- Identifying, documenting, and tracking software issues
- Assisting with automated tests (e.g. Appium or other frameworks)
- Working closely with developers and QA teams
- Supporting test plans, test cases, and QA processes

What they're looking for
- Entry-level or minimal QA experience welcome
- Strong interest in software testing and eagerness to learn
- Familiarity with mobile and desktop platforms a plus
- Practical experience with testing methods and tools a plus
- Strong analytical skills and attention to detail
- Independent and team work
- Advanced English; German a plus

Offers: flexible hours, modern office near train station, international team, cybersecurity products used by millions.
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Manual Application Tester | Mobile & Desktop QA';
$snapshot['location'] = $location;

$summary = 'Detail-oriented Manual / Application Tester with hands-on experience testing web, desktop, and mobile applications (iOS and Android), including cross-platform checks. Skilled in designing and executing test cases, identifying and documenting defects, and collaborating with developers to improve product quality. Eager to grow in a full-time onsite QA role in Übach-Palenberg, contributing to CyberGhost and PIA product quality across iOS, Android, Windows, macOS, and Linux.';

$skills = "Testing\nManual Testing · Functional Testing · Regression Testing · Smoke Testing · Sanity Testing · Exploratory Testing · Mobile Testing (iOS & Android) · Desktop Application Testing · Cross-platform Testing · Cross-browser Testing · Test Case Design · Test Execution · Defect Reporting · Defect Life Cycle · Test Documentation · Quality Assurance · UAT · API Testing · Performance Testing\n\nTools\nPostman · Apache JMeter · Playwright · Appium · Jira · Azure DevOps · Mantis · Git · GitHub · SQL Server · Microsoft Office · Visual Studio\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL · SQL\n\nLanguages\nEnglish — Advanced / Professional (C1) · German — Basic / improving (A2+)";

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
    'Manual Application Tester — ExpressVPN',
    $snapshot,
    $company,
    'Light tailor for Manual Application Tester (Übach-Palenberg / CG & PIA). Copy of Main.',
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

I am writing to apply for the Manual Application Tester / QA Tester position with the CyberGhost and Private Internet Access teams in Übach-Palenberg. I am excited by the chance to build a QA career testing products used by millions across iOS, Android, Windows, macOS, and Linux in a fully onsite lab environment.

In my QA roles at Neural Spark Tech, I manually tested web, desktop, and mobile applications (iOS and Android), including cross-platform checks. I designed and executed test cases, identified and tracked defects, documented results clearly, and worked closely with developers to reproduce and verify fixes. I also have exposure to API testing with Postman, performance testing with Apache JMeter, and test automation tools including Playwright and Appium — and I am eager to grow further in automation while contributing strong manual testing skills day to day.

I bring attention to detail, a structured approach, and a strong interest in software quality and cybersecurity products. I communicate at an advanced English level and continue to improve my German. I would welcome the opportunity to join your Übach-Palenberg team and help improve the quality of CG and PIA products.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Manual Application Tester — ExpressVPN');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Teams: CyberGhost & PIA. Onsite.",
    ''
);

echo "OK ExpressVPN application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
