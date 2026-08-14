<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Microsoft Data Center Technician — Frankfurt.
 * Resume: no company name; skill-based profile title (not job title).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'Microsoft';
$role = 'Data Center Technician';
$location = 'Frankfurt, Germany';

$jd = <<<'TXT'
Data Center Technician
Microsoft · Germany, Hessen, Frankfurt · Fully on-site · Full-Time
Job number: 200047775 · Posted Aug 11, 2026
Profession: Data Center / Data Center Technicians · Individual Contributor
Travel: Less than 25%
Pay (Germany): ATR-B €43,600–€57,800 · ATR-C €53,700–€74,700

Overview
Stage, set up and perform hardware deployments; troubleshooting/diagnostics; hardware decommissions for simple changes/refreshes following SOPs. CO+I powers Bing, Office 365, Xbox, OneDrive, Azure.

Responsibilities
- Diagnostics and troubleshooting per SOPs; replace faulty components with minimal disruption
- Post-execution quality checks: grounding, staging, labeling, cabling per safety/deployment standards and NDTs
- Decommission hardware for simple changes/refreshes (memory upgrades, rebuilds)
- Communicate, report, escalate incidents to DC ops management, Technician Leads, engineering
- Guide other technicians on challenging tasks; complete training; observe experienced techs
- Positive team environment; ownership of service quality and facilities

Required
- High school diploma/GED/equivalent + basic knowledge of computer hardware/components AND relevant experience supporting IT equipment or related technology
- Microsoft Cloud Background Check (hire/transfer + every 2 years)

Preferred
- Experience supporting IT equipment
- CompTIA A+/Server+/Network+, Basic Structure Cabling (BSC)
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'IT Quality & Support | Hardware & Systems Awareness';
$snapshot['location'] = $location;

$summary = 'Computer Science student at Hochschule Schmalkalden (almost finished — one semester remaining) with a detail-oriented background in software quality assurance and hands-on troubleshooting. Experienced in following structured procedures, diagnosing issues systematically, documenting findings clearly, verifying fixes, and delivering reliable service quality. Strong interest in computer hardware, IT infrastructure, and on-site operational work. Seeking a Data Center Technician role in Frankfurt to support hardware deployment, diagnostics, quality checks, and day-to-day data center operations in a safety- and procedure-driven environment.';

$skills = "Operations & quality\nStructured troubleshooting · Procedure-driven work · Quality checks · Issue diagnosis · Clear incident documentation · Escalation awareness · Attention to Detail · Ownership & accountability · Team collaboration · Customer / service mindset\n\nIT & systems\nComputer hardware awareness · Windows · Basic networking concepts · SQL · Git · GitHub · Microsoft Office\n\nSoftware quality background\nManual Testing · Exploratory Testing · Functional Testing · Regression Testing · Defect Reporting · Fix Verification\n\nProgramming\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Data Center Technician — Microsoft Frankfurt',
    $snapshot,
    $company,
    'Light tailor for Data Center Technician (Frankfurt / on-site). Copy of Main. No company in resume body.',
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
Dear Hiring Team at Microsoft,

I am writing to apply for the Data Center Technician position in Frankfurt (Job number: 200047775). I am motivated by hands-on infrastructure work — staging and deploying hardware, diagnosing issues with a clear process, and owning service quality in a large-scale cloud environment.

Through my quality assurance experience at Neural Spark Tech, I developed a careful, procedure-oriented way of working: identifying root causes, documenting issues clearly, verifying that fixes work, and keeping disruption low for users and the business. I take pride in thorough quality checks and reliable follow-through, and I am eager to apply that mindset to hardware diagnostics, post-execution checks (staging, labeling, cabling), and simple decommission/refresh tasks following Microsoft standard operating procedures.

I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I communicate well in English, am improving my German, and I am ready for fully on-site work in Frankfurt with a strong sense of accountability for safety, security, and operational excellence. I would welcome the opportunity to grow as a Data Center Technician within Cloud Operations & Innovation.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Data Center Technician — Microsoft Frankfurt');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Fully on-site. Job #200047775.",
    'https://careers.microsoft.com/'
);

echo "OK Microsoft DCT application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
echo "status=applied\n";
echo "profile_title={$snapshot['profile_title']}\n";
