<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Adobe Systems Europe — Senior Software Quality Engineer (Hamburg).
 * Resume: no company name; skill-based profile title (not job title).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'Adobe Systems Europe Limited';
$role = 'Senior Software Quality Engineer';
$location = 'Hamburg, Germany';

$jd = <<<'TXT'
Senior Software Quality Engineer
Adobe Systems Europe Limited · Hamburg, Germany · Full-time
Salary listed: 41.000 - 69.000 €/year

What You'll Do
- Define test strategies for new features (manual vs automate)
- Detailed testing of new features with editor workflows in mind
- Cross-platform testing on macOS and Windows (varying hardware)
- Develop/manage test cases, scripts, automated tests; use AI tools for custom testing solutions
- Clear reproducible bug reports (steps, expected vs actual, logs, crash data); verify fixes; regression before releases
- Product feedback on quality/usability; release readiness; collaborate outside Engineering
- Work with customers online and in person to capture feedback and diagnose issues

What you need
- Proficiency with Adobe Premiere (or another NLE) and familiarity with Adobe video/audio tools
- Understanding of real-world editorial workflows and performance factors
- Curiosity to learn new technologies
- Problem-solving; attention to detail; good communication
- Bachelor's in CS or TV/Film/Media, OR 3 years relevant practical experience in media/post production
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Software Quality Assurance | Manual & Exploratory Testing';
$snapshot['location'] = $location;

$summary = 'Computer Science student at Hochschule Schmalkalden (almost finished — one semester remaining) with hands-on software quality assurance experience: designing and executing test cases, exploratory and regression testing, and writing clear, reproducible defect reports with steps, expected vs actual results, and supporting evidence. Comfortable validating features across platforms, collaborating with engineering on fix verification, and giving practical quality and usability feedback. Curious about media/creative workflows and modern QA tooling (including automation and AI-assisted approaches). Seeking a Software Quality Engineer role in Hamburg to contribute to rigorous feature testing, release readiness, and continuous product quality.';

$skills = "Software quality\nManual Testing · Exploratory Testing · Functional Testing · Regression Testing · Smoke Testing · Cross-platform Testing · Test Case Design · Test Strategy Awareness · Test Documentation · Defect Reporting · Bug Reproduction · Fix Verification · Release Readiness Support · Attention to Detail · Usability Feedback\n\nAutomation & tools\nPlaywright · Postman · Apache JMeter · Appium · AI-assisted testing interest · Jira · Azure DevOps · Mantis · Git · GitHub · Microsoft Office · SQL\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL · Windows · macOS familiarity\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Senior SQE — Adobe Hamburg',
    $snapshot,
    $company,
    'Light tailor for Senior Software Quality Engineer (Hamburg). Copy of Main. No company in resume body; no false Premiere claims.',
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
Dear Hiring Team at Adobe,

I am writing to apply for the Senior Software Quality Engineer position in Hamburg. I am excited by the chance to help ensure high-quality video and creative products through thoughtful test strategy, careful feature validation, and clear collaboration with engineering and customers.

In my QA work at Neural Spark Tech, I manually tested applications with a strong focus on detail: designing and executing test cases, exploring edge cases, documenting defects with reproducible steps and expected versus actual results, verifying fixes, and supporting regression checks before release. I am comfortable thinking about how real users will use a product, giving practical quality and usability feedback, and working across teams to improve release readiness. I am also motivated to grow further in test automation and in using modern AI-assisted approaches to build efficient, custom testing solutions.

I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I communicate well in English, am improving my German, and I am eager to deepen my understanding of editorial and media workflows around tools such as Premiere and related Adobe video/audio products. I would welcome the opportunity to contribute to Adobe’s quality engineering efforts in Hamburg.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Senior SQE — Adobe Hamburg');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Full-time. Listed pay band 41–69k €/year. Note: Senior + Premiere/NLE experience is a stretch vs current profile.",
    ''
);

echo "OK Adobe application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
echo "status=applied\n";
echo "profile_title={$snapshot['profile_title']}\n";
