<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Jenoptik AG Werkstudent Technologie (Jena).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'JENOPTIK AG';
$role = 'Werkstudent*in (w/m/d) Technologie';
$location = 'Jena, Germany';

$jd = <<<'TXT'
Werkstudent*in (w/m/d) Technologie
JENOPTIK AG · Jena, Thüringen · Student · Hybrid
Job ID: 4999 · Contact: Niklas Brod · recruiting@jenoptik.com
SBU Semiconductor & Advanced Manufacturing (photonics / optics)

Tasks
- Auswertung von Messdaten und Darstellung der Ergebnisse
- Aufbereitung von Daten in Excel (auch VBA)
- Darstellung von Sachverhalten in Powerpoint-Präsentationen
- Durchführung von Messungen an optischen Komponenten
- Vorbereitung und Durchführung von Bearbeitungstests unter Anleitung
- Unterstützende Arbeiten in Projekten zur Verbesserung der Prozesstechnologie
- Flexible Arbeitszeit in gegenseitiger Absprache

Profile
- Interesse an Technologien der Optikfertigung und Messtechnik
- Gute Auffassungsgabe und Neugier; selbständiges Arbeiten und Engagement
- Zuverlässigkeit und gewissenhafte Ausführung
- Sorgfalt beim Umgang mit optischen Komponenten
- Gute PC-Kenntnisse, besonders Microsoft Office (Excel, PowerPoint)
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Werkstudent Technologie | Data Analysis & Measurement Support';
$snapshot['location'] = $location;

$summary = 'Computer Science student (Hochschule Schmalkalden; almost finished — one semester remaining) with a careful, structured working style and strong Microsoft Office skills, especially Excel and PowerPoint. Experienced in documenting results clearly, analyzing information systematically, and supporting quality-focused technical work. Interested in optics manufacturing, measurement technology, and hands-on process improvement projects. Seeking a Werkstudent Technologie role in Jena to support measurement data evaluation, documentation, and guided lab/process tests at Jenoptik.';

$skills = "Technical support & analysis\nData analysis & result presentation · Microsoft Excel (incl. VBA interest) · PowerPoint · Structured documentation · Careful handling of technical tasks · Process improvement support · Quality awareness\n\nTesting & quality background\nManual Testing · Test Documentation · Defect Reporting · Requirement Analysis · Structured Working Style · Agile collaboration\n\nTools\nMicrosoft Office · Excel · PowerPoint · Jira · Git · GitHub · SQL · Postman\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Werkstudent Technologie — Jenoptik',
    $snapshot,
    $company,
    'Light tailor for Werkstudent Technologie (Jena / Job ID 4999). Copy of Main.',
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
Dear Niklas Brod,

I am writing to apply for the Werkstudent*in (w/m/d) Technologie position at JENOPTIK AG in Jena (Job ID: 4999). I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I am motivated to support your Semiconductor & Advanced Manufacturing team with measurement data evaluation, clear documentation, and guided process/technology improvement work.

Through my QA experience at Neural Spark Tech, I developed a careful, structured way of working: analyzing results, documenting findings clearly, and collaborating reliably in technical teams. I am comfortable with Microsoft Office, especially Excel and PowerPoint, and I enjoy turning data and observations into understandable presentations. I am curious about optics manufacturing and metrology and would be glad to learn measurement work on optical components under guidance while contributing diligently to project support tasks.

I work independently, take ownership of assigned tasks, and communicate well in English while continuing to improve my German. I would welcome the opportunity to join Jenoptik in Jena with flexible hours that fit around my studies.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Werkstudent Technologie — Jenoptik');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

// Update earlier XING placeholder if present
$xing = $pdo->prepare(
    "SELECT id FROM applications WHERE role LIKE '%Technologie%' AND (company LIKE '%XING%' OR company LIKE '%via XING%') ORDER BY id DESC LIMIT 1"
);
$xing->execute();
$existing = $xing->fetch();
if ($existing) {
    $pdo->prepare(
        'UPDATE applications SET company = ?, role = ?, status = ?, applied_date = ?, notes = ?, jd_snippet = ?, link = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
    )->execute([
        $company,
        $role,
        'applied',
        date('Y-m-d'),
        "Application via XING / Job ID 4999. Resume #{$resumeId}, cover #{$coverId}. Contact: Niklas Brod (recruiting@jenoptik.com). Location: {$location}.",
        $jd,
        'mailto:recruiting@jenoptik.com',
        (int) $existing['id'],
    ]);
    $appId = (int) $existing['id'];
} else {
    $appId = App::logJdApplication(
        $company,
        $role,
        $jd,
        'applied',
        "Tailored resume #{$resumeId} and cover letter #{$coverId}. Job ID 4999. Contact: Niklas Brod (recruiting@jenoptik.com). Location: {$location}.",
        'mailto:recruiting@jenoptik.com'
    );
}

echo "OK Jenoptik application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
