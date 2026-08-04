<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Almedia / Freecash Working Student Product QA (Berlin).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'Almedia (Freecash)';
$role = 'Working Student Product QA';
$location = 'Berlin, Germany';

$jd = <<<'TXT'
Working Student Product QA – Freecash (Almedia)
Berlin, Germany · Full-time working student · On-site
Compensation: €18–€20 per hour · Equity for Berlin full-time employees

About the role
Hands-on Product QA focused primarily on manual testing after training. Test new features before they reach users; work with Product and Engineering; identify bugs, usability issues, and product improvements.

What you'll do
- Test new features across web and mobile products
- Follow test scenarios and explore features from a user's perspective
- Identify bugs, inconsistencies, and usability issues
- Document findings clearly for reproduction and fixes
- Work with Product Managers and Engineers to verify fixes
- Help improve testing processes
- Learn about automated testing and support QA initiatives over time

What you'll bring
- Currently studying Computer Science, Software Engineering, Information Systems, or related
- Strong attention to detail; interest in software quality and UX
- Organized, reliable, comfortable learning new tools
- Good English communication
- Basic technical knowledge of websites/apps a plus
- Testing/coding/personal projects a bonus, not required

Great fit: notice small details, think like a user, curious, ownership, collaboration, fast-paced startup
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Working Student Product QA | Manual Web & Mobile Testing';
$snapshot['location'] = $location;

$summary = 'Detail-oriented Computer Science student (Hochschule Schmalkalden; almost finished — one semester remaining) with hands-on experience in manual and exploratory testing for web and mobile applications. Skilled at spotting usability issues, writing clear reproducible bug reports, and collaborating with product and engineering teams. Seeking a Working Student Product QA role in Berlin to help test Freecash features before they reach users and grow in a fast-paced product environment.';

$skills = "Testing & product QA\nManual Testing · Exploratory Testing · Functional Testing · Regression Testing · Smoke Testing · Usability Awareness · Web Testing · Mobile Testing (iOS & Android) · Test Case Design · Test Execution · Defect Reporting · Clear Bug Documentation · Cross-browser Testing · Quality Assurance · Agile Scrum\n\nTools\nJira · Postman · Playwright · Appium · Apache JMeter · Azure DevOps · Mantis · Git · GitHub · Microsoft Office · SQL\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Working Student Product QA — Freecash / Almedia',
    $snapshot,
    $company,
    'Light tailor for Working Student Product QA (Berlin / Freecash). Copy of Main.',
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
Dear Hiring Team at Almedia,

I am writing to apply for the Working Student Product QA role supporting Freecash in Berlin. I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I am looking for an on-site working student role where I can test web and mobile features from a user’s perspective and help keep product quality high as you scale.

In my QA roles at Neural Spark Tech, I manually tested web, desktop, and mobile applications, followed and designed test scenarios, explored features to find defects and inconsistencies, and documented findings clearly so developers could reproduce and fix issues. I also collaborated closely with engineering teams to verify fixes. That experience matches the hands-on Product QA work you describe: careful manual testing, clear communication, and a focus on real user experience.

I naturally notice when something feels “off,” I enjoy figuring out why, and I am curious to learn how modern product and engineering teams ship software in a fast-paced startup. I communicate well in English and am eager to grow into automated testing over time while contributing solid manual QA from day one.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Working Student Product QA — Freecash / Almedia');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. On-site. Pay: €18–€20/hour.",
    ''
);

echo "OK Freecash/Almedia application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
