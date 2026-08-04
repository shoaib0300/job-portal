<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for IGEL Technology (Working Student/Intern QA, Augsburg).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'IGEL Technology';
$role = 'Working Student/Intern, Quality Assurance';
$location = 'Augsburg, Germany';

$jd = <<<'TXT'
Working Student/Intern, Quality Assurance (m/f/d)
Location: Augsburg, Germany · On-site · Internship / working student
Available up to 20 hours per week

Tasks and responsibilities
- Responsible for the execution of functional tests (regression, smoke, etc.)
- Responsible for the execution of non-functional tests (performance, etc.)
- Documenting the test results is a must

Experience and qualifications
- Technology-enthusiastic student; ideally studying computer science, business informatics, math or physics
- Available up to 20 hours per week
- Independent working attitude and organizational skills
- Quality awareness and ability to work in a team
- Very good written and spoken German and English

Apply via IGEL online applicant portal.
Contact: Florian Hermann, Senior Talent Acquisition Partner EMEA
Note: No sponsorship; applicants must reside in countries of IGEL legal entities.
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Working Student Quality Assurance | Manual & Performance Testing';
$snapshot['location'] = $location;

$summary = 'Quality-focused Computer Science student (Hochschule Schmalkalden; almost finished — one semester remaining) with hands-on experience in functional testing (regression, smoke, exploratory) and non-functional testing including performance/load testing. Experienced in documenting test results and defects clearly, and collaborating with developers in Agile teams. Seeking a Working Student / Intern Quality Assurance role in Augsburg (up to 20 hours/week) to contribute to reliable software quality.';

$skills = "Testing\nFunctional Testing · Regression Testing · Smoke Testing · Sanity Testing · Exploratory Testing · Manual Testing · Performance Testing · API Testing · UAT · Test Case Design · Test Execution · Test Result Documentation · Defect Reporting · Defect Life Cycle · Quality Assurance · SDLC · STLC · Agile Scrum\n\nTools\nPostman · Apache JMeter · Playwright · Jira · Azure DevOps · Mantis · Git · GitHub · SQL Server · Microsoft Office · Visual Studio · CI/CD basics\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL · SQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Working Student QA — IGEL Technology',
    $snapshot,
    $company,
    'Light tailor for Working Student/Intern QA (Augsburg). Copy of Main.',
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
Dear Hiring Team at IGEL Technology,

I am writing to apply for the Working Student/Intern, Quality Assurance (m/f/d) position in Augsburg. I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I can work up to 20 hours per week and am motivated to contribute to functional and non-functional testing in a quality-focused team.

In my QA roles at Neural Spark Tech, I executed functional tests including regression, smoke, and exploratory testing for web, desktop, and mobile applications. I also performed performance/load testing with Apache JMeter, API testing with Postman, and documented test results and defects clearly throughout the defect lifecycle. I enjoy working independently with a structured approach while collaborating closely with developers and teammates.

I am preparing for the ISTQB Foundation Level certification and continuously improving my German alongside strong English communication skills. I would welcome the opportunity to support IGEL’s Quality Assurance team in Augsburg and help deliver reliable software through careful testing and clear documentation.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Working Student QA — IGEL Technology');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Contact: Florian Hermann (Talent Acquisition).",
    ''
);

echo "OK IGEL application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
