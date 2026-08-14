<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for NEXUS Deutschland Softwaretester (Berlin / Ismaning / Jena).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'NEXUS Deutschland GmbH';
$role = 'Softwaretester';
$location = 'Berlin, Germany'; // also Ismaning & Jena

$jd = <<<'TXT'
Softwaretester (m/w/d)
NEXUS Deutschland GmbH · Berlin / Ismaning / Jena · E-Health
Contact: Maike Hinirchs · Sachsendamm 2-7, 10829 Berlin

Tasks
- Independent execution of software tests
- Creation and documentation of test cases; preparation of test data
- Review of concepts and specifications
- Verify bugfixes; defect analysis and documentation
- Support Support department with problem analysis for incidents/requests and joint solution development

Profile
- Completed degree in Computer Science, Mathematics, Business Informatics, or comparable technical training
- MS Office knowledge and basic SQL (Oracle)
- High technical understanding, quality awareness, analytical thinking
- Database knowledge advantageous
- C# programming knowledge desirable
- Reliability, care, initiative

Please include salary expectation and earliest start date in application.
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Softwaretester | Manual Testing · SQL · Quality Assurance';
$snapshot['location'] = $location;

$summary = 'Detail-oriented Softwaretester / QA Engineer with hands-on experience executing software tests independently, creating and documenting test cases, analyzing defects, and verifying bugfixes in collaboration with developers. Comfortable with Microsoft Office and SQL for data checks; solid quality awareness and analytical working style. Computer Science student at Hochschule Schmalkalden (almost finished — one semester remaining). Seeking a Softwaretester role in E-Health at NEXUS (Berlin / Ismaning / Jena) to support clinical software quality.';

$skills = "Testing\nManual Testing · Functional Testing · Regression Testing · Smoke Testing · Exploratory Testing · Test Case Design · Test Documentation · Test Data Preparation · Defect Analysis · Bugfix Verification · Specification Review · Quality Assurance · Support / Incident Collaboration · Agile Scrum\n\nTools & data\nMicrosoft Office · SQL · Postman · Jira · Azure DevOps · Mantis · Git · GitHub · Playwright · Apache JMeter · Appium\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL · SQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Softwaretester — NEXUS Deutschland',
    $snapshot,
    $company,
    'Light tailor for Softwaretester (Berlin/Ismaning/Jena / E-Health). Copy of Main.',
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
Dear Maike Hinirchs,

I am writing to apply for the Softwaretester (m/w/d) position at NEXUS Deutschland GmbH. I am interested in contributing to E-Health software quality at your locations in Berlin, Ismaning, or Jena, and in supporting clinical digitalization with careful, structured testing.

In my QA roles at Neural Spark Tech, I independently executed software tests, designed and documented test cases, prepared and validated data where needed, analyzed and tracked defects, and verified bugfixes together with developers. I also collaborated on clarifying issues and documenting findings clearly — experience that fits well with supporting Support on incident analysis. I am comfortable with Microsoft Office and have practical SQL experience for data checks; I also have C# exposure and am motivated to grow further in this stack.

I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. My earliest possible start is flexible after alignment with my remaining semester schedule. Regarding salary, I am looking at a market-aligned range for a Softwaretester role in E-Health and am open to discussion based on location, scope, and package.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Softwaretester — NEXUS Deutschland');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · Berlin / Ismaning / Jena', $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Locations: Berlin / Ismaning / Jena. Contact: Maike Hinirchs (Sachsendamm 2-7, 10829 Berlin). Include salary + earliest start in application.",
    ''
);

echo "OK NEXUS application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
