<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Munich Surgical Imaging (Software Tester), log application.
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'Munich Surgical Imaging GmbH';
$role = 'Software Tester';
$location = 'München, Germany';

$jd = <<<'TXT'
Software Tester (m/f/d)
Office: München · Experience: 2–5 years · Full time

Munich Surgical Imaging GmbH (MSI), headquartered in Munich, specializes in fully digital surgical microscopy, surgical imaging, and image-guided applications (ophthalmology and ENT). Subsidiary of Heidelberg Engineering GmbH.

Start: immediately · Location: Munich

Responsibilities
- Conducting verification and validation tasks
- Analyzing and reviewing product-specific requirements
- Designing and specifying test cases for medical devices and software systems
- Executing test cases
- Formally documenting test results
- Documenting and tracking nonconformities
- Preparing verification documentation for medical device approval
- Setting up and maintaining test environments

Qualifications
- Relevant IT training or comparable qualification
- Proficiency with internal testing tools; basic command-line knowledge
- Basic understanding of medical device standards (e.g. IEC 62304, ISO 13485, ISO 14971)
- ISTQB Certified Tester (Foundation Level) a plus
- Independent, structured work; strong quality focus
- Very good German and good English
- Good Microsoft Office

Contact: bewerbung@munichimaging.de
TXT;

// --- Resume from Main ---
$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Software Tester | Manual, API & Verification Testing';
$snapshot['location'] = $location;

$summary = 'Detail-oriented Software Tester and QA Engineer with hands-on experience in verification-style testing for web, desktop, and mobile applications. Skilled in designing and executing test cases, reviewing requirements, documenting defects, and collaborating with developers in structured delivery processes. Currently studying Computer Science at Hochschule Schmalkalden (Germany) and preparing for ISTQB Foundation Level. Seeking a Software Tester role in München to contribute to high-quality medical device software and imaging products.';

$skills = "Testing & quality\nManual Testing · Functional Testing · Verification & Validation · Test Case Design · Test Execution · Test Documentation · Defect Reporting · Defect Life Cycle · Requirement Analysis · Regression Testing · Smoke / Sanity Testing · Exploratory Testing · UAT · API Testing · Cross-browser Testing · Quality Assurance · SDLC · STLC · Agile Scrum\n\nTools\nPostman · Apache JMeter · Playwright · Jira · Azure DevOps · Mantis · Git · GitHub · SQL Server · Microsoft Office · Visual Studio · Command line / CI basics\n\nStandards & learning\nISTQB Foundation Level — preparing · Awareness of medical device quality processes · Structured documentation\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL · SQL";

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
    'Software Tester — Munich Surgical Imaging',
    $snapshot,
    $company,
    'Light tailor for Software Tester (München). Copy of Main.',
    false,
    null,
    true
);
Versions::loadResumeVersion($resumeId);

// --- Cover from Main ---
$baseCover = Versions::baseCoverLetter();
if ($baseCover === null) {
    fwrite(STDERR, "No Main cover letter\n");
    exit(1);
}

$coverBody = <<<TXT
Dear Hiring Team at Munich Surgical Imaging GmbH,

I am writing to apply for the Software Tester (m/f/d) position at your Munich location. I am based in Germany, currently studying Computer Science at Hochschule Schmalkalden, and I am motivated to contribute to high-quality software for digital surgical microscopy and image-guided applications.

In my QA roles at Neural Spark Tech, I conducted structured testing of web, desktop, and mobile applications: designing and executing test cases, reviewing workflows against requirements, documenting and tracking defects, and collaborating with developers to verify fixes. I also performed API testing with Postman and maintained clear test documentation — skills that map well to verification, validation, and formal test reporting.

I am preparing for the ISTQB Foundation Level certification and am eager to deepen my knowledge of medical device quality processes and standards such as IEC 62304, ISO 13485, and ISO 14971 in a regulated product environment. I work in a structured, quality-focused way, and I communicate in English at a professional level while continuing to improve my German.

I would welcome the opportunity to support your Munich team in building and verifying reliable medical imaging software. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Software Tester — Munich Surgical Imaging');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Contact: bewerbung@munichimaging.de",
    'mailto:bewerbung@munichimaging.de'
);

echo "OK MSI application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
