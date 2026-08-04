<?php

declare(strict_types=1);

/**
 * Tailor resume + cover for Swift Games Junior QA Tester (Berlin).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
$pdo = Db::pdo();

$company = 'Swift Games';
$role = 'Junior QA Tester';
$location = 'Berlin, Germany';

$jd = <<<'TXT'
Junior QA Tester (f/m/d)
Swift Games (part of Ares Interactive) · Berlin, Germany · Full-time · Hybrid (Swift Games HQ)
Product: Heroes vs. Hordes (mobile survival action roguelite RPG, 13M+ installs); also The Walking Dead: Aftermath

What you'll do
- Test new features, systems, and SDK integrations — mostly manual and exploratory
- Write clear, reproducible bug reports; maintain test cases and test plans
- Support testing coordination within the sprint
- Turn player-reported issues (Discord, Helpshift) into actionable bug tickets with Community Manager
- Localization QA: check translated text in-game (truncation, context)
- Collaborate with developers, design, and product
- Bring a player's perspective to catch rough edges

Must-haves
- Solid understanding of manual and exploratory testing
- Strong written English for bug reports
- Genuine interest in mobile games (roguelite/roguelike action a plus)
- Detail-oriented, organized, team-oriented
- Openness to AI tools in daily work
- Willingness to learn JIRA (prior exposure a plus)

Nice to have: prior QA/games experience, agile/sprints, basic SQL/programming, GitLab
TXT;

$base = Versions::baseResumeVersion();
if ($base === null) {
    fwrite(STDERR, "No Main resume\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
$snapshot['profile_title'] = 'Junior QA Tester | Manual & Exploratory Mobile Testing';
$snapshot['location'] = $location;

$summary = 'Junior QA-focused Computer Science student (Hochschule Schmalkalden; almost finished — one semester remaining) with hands-on experience in manual and exploratory testing for mobile (iOS/Android), web, and desktop applications. Skilled in writing clear, reproducible defect reports, designing and maintaining test cases, and collaborating with developers in Agile sprints. Strong written English and genuine interest in mobile games. Seeking a Junior QA Tester role in Berlin to help keep product quality high as games scale.';

$skills = "Testing\nManual Testing · Exploratory Testing · Functional Testing · Regression Testing · Smoke Testing · Mobile Testing (iOS & Android) · Test Case Design · Test Plans · Defect Reporting · Reproducible Bug Reports · Cross-platform Testing · UAT · API Testing · Quality Assurance · Agile Scrum\n\nTools\nJira · Postman · Playwright · Appium · Apache JMeter · Azure DevOps · Mantis · Git · GitHub · Microsoft Office · SQL\n\nProgramming / systems\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL\n\nLanguages\nEnglish — Advanced / Professional (C1) · German — Basic / improving (A2+)";

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
    'Junior QA Tester — Swift Games',
    $snapshot,
    $company,
    'Light tailor for Junior QA Tester (Berlin / mobile games). Copy of Main.',
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
Dear Hiring Team at Swift Games,

I am writing to apply for the Junior QA Tester (f/m/d) position in Berlin. I am excited by the chance to help protect quality on Heroes vs. Hordes as the game continues to grow, and to learn in a hands-on hybrid QA role with mentorship and room to take on broader ownership over time.

In my QA roles at Neural Spark Tech, I performed manual and exploratory testing on web, desktop, and mobile applications (iOS and Android). I designed and executed test cases, wrote clear defect reports with steps to reproduce, tracked issues through the defect lifecycle, and collaborated with developers to verify fixes in Agile sprints. That experience maps well to feature and SDK testing, sprint coordination support, and turning messy real-world issues into clean, actionable tickets.

I communicate strongly in written English, work in an organized and detail-oriented way, and am open to using AI tools to move faster on day-to-day QA work. I also enjoy mobile games and bring a player’s eye for rough edges that real users will notice. I would welcome the opportunity to join your Berlin team and help keep Heroes vs. Hordes feeling sharp for millions of players.

Please find my resume and cover letter attached. Thank you for your time and consideration. I look forward to hearing from you.

Sincerely,
Muqaddas Khan
TXT;

$coverId = Versions::duplicateCover((int) $baseCover['id'], 'Junior QA Tester — Swift Games');
$pdo->prepare(
    'UPDATE cover_letters SET body = ?, company = ?, is_active = 1, is_base = 0 WHERE id = ?'
)->execute([$coverBody, $company . ' · ' . $location, $coverId]);
Versions::activateCover($coverId);

$appId = App::logJdApplication(
    $company,
    $role,
    $jd,
    'applied',
    "Tailored resume #{$resumeId} and cover letter #{$coverId}. Location: {$location}. Hybrid. Product: Heroes vs. Hordes.",
    ''
);

echo "OK Swift Games application logged\n";
echo "resume_id={$resumeId}\n";
echo "cover_id={$coverId}\n";
echo "application_id={$appId}\n";
echo "location={$location}\n";
