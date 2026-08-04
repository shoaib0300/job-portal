<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for CARIAD Infotainment Apps Testing & Tooling (Ingolstadt).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'CARIAD';
$role = 'Internship / Working Student - Infotainment Apps Testing & Tooling';
$location = 'Ingolstadt, Germany';

$jd = <<<'TXT'
Internship / Working Student - Infotainment Apps Testing & Tooling (f/m/d)
CARIAD (Volkswagen Group) · Ingolstadt, Bavaria, Germany · On-site · Part-time
Team: Smartphone Integration and Cross Apps — infotainment test environments & validation

What you will do
- Support development and maintenance of tooling for remote access to test setups
- Create, maintain, and improve test specifications and test cases for infotainment apps
- Learn, operate, maintain, and troubleshoot test environments
- Execute and document tests (e.g. Keyboard, Smartlight, Global Search, Settings)
- Support innovation and automation of testing/validation
- Collaborate with developers, testers, and product teams in an agile environment

Who you are
- Enrolled student: Computer Science, Software Engineering, Electrical Engineering, or comparable
- Interest in automotive infotainment and software quality assurance
- Basic programming/scripting (e.g. Python, Bash, PowerShell)
- Structured, independent; good communication; willingness to learn

Internship: 3–6 months, 35 h/week, €13.90/h
Working student: 6 months (extendable up to 2 years), 20 h/week (up to 35 in semester break), €17.80/h
Remote work options within Germany
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Working Student QA | Infotainment Testing & Tooling';
$snapshot['location'] = $location;

$summary = 'Computer Science student (Hochschule Schmalkalden; almost finished — one semester remaining) with hands-on experience in software quality assurance, test case design, and documenting test results for web, mobile, and desktop applications. Interested in automotive infotainment systems, test environments, and tooling. Basic scripting skills (Python) and a structured, independent working style. Seeking an Internship / Working Student role in Ingolstadt to support infotainment apps testing and tooling at CARIAD.';

$skills = "Testing & QA\nManual Testing · Functional Testing · Regression Testing · Smoke Testing · Exploratory Testing · Test Case Design · Test Specifications · Test Execution · Test Documentation · Defect Reporting · Cross-platform Testing · Quality Assurance · Agile Scrum\n\nTooling & technical\nPython · Git · GitHub · Postman · Playwright · Apache JMeter · Appium · Jira · Azure DevOps · SQL · Basic scripting interest (Bash/PowerShell)\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Infotainment Testing & Tooling — CARIAD',
    $snapshot,
    $company,
    'Light tailor for Infotainment Apps Testing & Tooling (Ingolstadt). Copy of Main.',
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
Dear Hiring Team at CARIAD,

I am writing to apply for the Internship / Working Student position in Infotainment Apps Testing & Tooling (Smartphone Integration and Cross Apps) in Ingolstadt. I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I am excited by the chance to support infotainment validation, test environments, and tooling for automotive software used across Volkswagen Group brands.

In my QA roles at Neural Spark Tech, I designed and maintained test cases, executed functional and regression tests, documented results clearly, and tracked defects while collaborating with developers in Agile teams. I also have a web development background and basic Python scripting skills, which help me learn tooling, troubleshoot setups, and contribute to improving test infrastructure over time.

I work in a structured and independent way, communicate well in English, and am eager to learn automotive infotainment systems and stronger automation/validation practices. I would welcome the opportunity to contribute to your team’s test specifications, app testing (e.g. Keyboard, Settings, Global Search), and continuous improvement of remote test setups.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Infotainment Testing & Tooling — CARIAD');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. On-site. Internship €13.90/h or Working Student €17.80/h.",
    'mailto:careers@cariad.technology'
);

echo "OK CARIAD application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
