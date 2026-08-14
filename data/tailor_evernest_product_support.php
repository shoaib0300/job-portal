<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Evernest Werkstudent Product Support — Hamburg.
 * Resume: no company name; skill-based profile title (not job title).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'Evernest';
$role = 'Werkstudent Product Support (m/w/d)';
$location = 'Hamburg, Germany';

$jd = <<<'TXT'
Werkstudent Product Support (m/w/d)
Evernest · Hamburg, Germany · Hybrid
Contact: Nora Topfstedt (Talent Acquisition) · recruiting@evernest.com

Product* / cross-functional product teams — Makler software, design thinking culture.

Responsibilities
- Support: help Agents with platform questions/problems; optimize support process via Intercom
- Testing: test tickets in test environments; ensure quality of new features
- Documentation: revise/develop guides for platform usage and internal processes; improve efficiency
- Project Management: smaller projects; support Product team on operational tasks; learn PM facets

Profile
- Advanced studies in BWL, Informatik, or related
- Tech-savvy; apps, digital solutions, elegant UX
- Practical experience preferred (Werkstudent/internship/Ausbildung); modern Product Management basics a plus
- G-Suite, modern Office tools (e.g. Notion); first SQL for data analysis
- Open, communicative team player; ownership; process improvement

Benefits: modern city offices, teamwork, flat hierarchy, onboarding, team events, training
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Software Quality Assurance | Testing & Documentation';
$snapshot['location'] = $location;

$summary = 'Computer Science student at Hochschule Schmalkalden (almost finished — one semester remaining) with hands-on experience in software quality assurance: manual and exploratory testing, clear defect documentation, and structured collaboration with product and engineering teams. Comfortable validating features in test environments, writing user-facing and internal documentation, and supporting day-to-day product operations. Strong interest in digital products, UX, and improving support/quality processes. Seeking a Werkstudent role in Hamburg to contribute to testing, documentation, and product support in a cross-functional product team.';

$skills = "Quality & product support\nManual Testing · Exploratory Testing · Functional Testing · Regression Testing · Test Environments · Feature Validation · Defect Reporting · Test Documentation · User / Process Documentation · Support Ticket Follow-up · Process Improvement · Attention to Detail\n\nCollaboration & tools\nAgile collaboration · Cross-functional teamwork · Microsoft Office · G-Suite familiarity · Notion interest · Jira · Azure DevOps · Git · GitHub · SQL (basic data analysis)\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL · REST APIs\n\nLanguages\nEnglish — Professional Working Proficiency (C1) · German — Basic / improving (A2+)";

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
    'Werkstudent Product Support — Evernest Hamburg',
    $snapshot,
    $company,
    'Light tailor for Werkstudent Product Support (Hamburg / Hybrid). Copy of Main. No company in resume body.',
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
Dear Nora Topfstedt,

I am writing to apply for the Werkstudent Product Support (m/w/d) position at Evernest in Hamburg. I am motivated by the mix of product support, feature testing, documentation, and hands-on work with your product teams — and by the chance to help Agents get more value from your Makler software.

In my QA roles at Neural Spark Tech, I tested web and related applications in structured test setups, documented defects clearly, and collaborated with developers to verify fixes. That experience maps well to validating tickets in your test environments and protecting the quality of new features. I also enjoy turning processes into clear guides and improving how teams track and resolve issues — skills I would bring to documentation and to support workflows (including tools such as Intercom).

I am studying Computer Science at Hochschule Schmalkalden and am almost finished — one semester remaining. I am comfortable with modern office tooling, have basic SQL experience for simple data checks, and communicate confidently in English while actively improving my German. I would welcome the opportunity to join your Hamburg team in a hybrid setup and grow across support, testing, documentation, and smaller product projects.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Werkstudent Product Support — Evernest Hamburg');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Hybrid. Contact: Nora Topfstedt / recruiting@evernest.com.",
    'mailto:recruiting@evernest.com'
);

echo "OK Evernest application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
echo "status=applied\n";
echo "profile_title={$snapshot['profile_title']}\n";
