<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Neumann Kaffee Gruppe (NKG)
 * Working Student Green Coffee (Quality Operations) — Hamburg.
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'Neumann Kaffee Gruppe (NKG)';
$role = 'Working Student Green Coffee (Quality Operations)';
$location = 'Hamburg, Germany';

$jd = <<<'TXT'
Working Student Green Coffee (Quality Operations) (f/m/d)
Neumann Kaffee Gruppe (NKG) · Hamburg, Germany · Hybrid · Part-time
Bernhard Rothfos / NKG — green coffee trading & quality/food safety support.

Role
- Support Quality and Food Safety Team: sample handling and cupping activities
- Assist preparation and follow-up of internal and customer cuppings
- Prepare, organize, and dispatch coffee samples to customers worldwide
- Prepare samples for quality evaluation and tasting sessions
- Organize and maintain sample inventory and storage areas
- Documentation and administrative tasks (data entry, sample-related records)
- Support day-to-day quality processes and operational tasks

Profile
- Currently enrolled in a university program
- Strong interest in food, beverages, coffee, quality or supply chains
- Eager to learn; reliable, organized, detail-oriented
- Comfortable with routine/repetitive tasks; hands-on mentality
- Strong team player; good communication
- Gastronomy, hospitality, food science, food culture, or related background a plus
- Good English; German beneficial
- Good knowledge of MS Office

Offers: central Hamburg (Elbphilharmonie view), hybrid/flexible hours, training budget, HVV/jobRad, etc.
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Software Quality Assurance | Manual Testing';
$snapshot['location'] = $location;

$summary = 'Computer Science student at Hochschule Schmalkalden (almost finished — one semester remaining) with a reliable, detail-oriented working style from hands-on quality assurance roles. Experienced in structured documentation, careful sample/process follow-through, inventory of work items, and clear MS Office–based records. Strong interest in food, beverages, and quality-driven supply chains. Seeking a Working Student role in Quality Operations in Hamburg to support cupping logistics, sample preparation/dispatch, and day-to-day green coffee quality processes.';

$skills = "Quality operations & organization\nDetail-oriented process support · Sample / work-item tracking · Inventory & storage organization · Documentation & data entry · Administrative follow-up · Routine operational tasks · Hands-on teamwork · Quality awareness\n\nQuality & testing background\nManual Testing · Exploratory Testing · Functional Testing · Test Documentation · Defect Reporting · Attention to Detail · Structured Working Style\n\nTools\nMicrosoft Office (Excel, Word, PowerPoint) · Jira · Azure DevOps · Git · GitHub · SQL · Postman\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Working Student Quality Ops — NKG Hamburg',
    $snapshot,
    $company,
    'Light tailor for Working Student Green Coffee Quality Operations (Hamburg / Hybrid). Copy of Main.',
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
Dear Hiring Team at Neumann Kaffee Gruppe,

I am writing to apply for the Working Student Green Coffee (Quality Operations) (f/m/d) position in Hamburg. I am drawn to NKG’s focus on green coffee and reliable supply chains, and I would welcome the chance to support your Quality and Food Safety Team with sample handling, cupping preparation, and day-to-day quality operations.

Through my quality assurance work at Neural Spark Tech, I developed a careful, organized way of working: preparing and tracking work items, documenting results accurately, maintaining clear records, and reliably completing routine follow-up tasks. I am comfortable with hands-on operational support, inventory organization, and MS Office–based administration, and I am eager to learn cupping logistics and green coffee quality processes in an international trading environment.

I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I communicate well in English, am improving my German, and value hybrid, flexible hours alongside my studies. I would be glad to contribute as a reliable team member in Hamburg and grow my understanding of coffee quality and supply chains at NKG / Bernhard Rothfos.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Working Student Quality Ops — NKG Hamburg');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Hybrid / part-time Working Student Quality Operations (green coffee).",
    ''
);

echo "OK NKG application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
echo "status=applied\n";
