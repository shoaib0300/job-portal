<?php

declare(strict_types=1);

/**
 * Light JD tailor for resume #2 → 1KOMMA5° Working Student Software Engineering (Berlin).
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();

$id = 2;
$row = Versions::resumeVersion($id);
if ($row === null) {
    fwrite(STDERR, "Resume #{$id} not found\n");
    exit(1);
}

$snapshot = Versions::decodeSnapshot((string) $row['snapshot']);

$snapshot['profile_title'] = 'Working Student Software Engineering | CS Student';
$snapshot['location'] = 'Berlin, Germany';

$summary = 'Computer Science student (Hochschule Schmalkalden) with hands-on experience in software quality, web development, and collaborative Agile delivery. Comfortable contributing to customer-facing products, writing maintainable and testable code, and validating APIs and web applications. Seeking a Working Student Software Engineering role in Berlin to grow in cross-functional product & tech teams while supporting reliable software delivery.';

$skills = "Development & quality\nTypeScript · JavaScript · PHP · Python · C# · HTML5 · CSS3 · MySQL · REST APIs · Git · GitHub · CI/CD · Agile Scrum · Maintainable & testable code\n\nTesting\nManual Testing · Functional Testing · API Testing · Regression Testing · Exploratory Testing · UAT · Test Case Design · Defect Tracking · Quality Assurance\n\nTools\nPostman · Playwright · Apache JMeter · Jira · Azure DevOps · Mantis · SQL Server · Microsoft Office · Visual Studio\n\nAlso familiar with\nReact · Node.js · WordPress · Cross-browser / cross-platform testing";

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

Versions::saveResumeVersion(
    'Working Student SE — 1KOMMA5°',
    $snapshot,
    '1KOMMA5°',
    'Light tailor for Working Student Software Engineering (Berlin).',
    false,
    $id,
    true
);

Versions::loadResumeVersion($id);

echo "OK resume #{$id} tailored for 1KOMMA5°\n";
echo 'title=' . $snapshot['profile_title'] . "\n";
echo 'location=' . $snapshot['location'] . "\n";
echo 'company=1KOMMA5°' . "\n";
