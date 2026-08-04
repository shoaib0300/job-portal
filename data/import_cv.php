<?php

declare(strict_types=1);

/**
 * Import LinkedIn-aligned CV as the live working copy and Main (base) resume.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Db::pdo();
Versions::ensureSchema();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS experience_entries (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      company VARCHAR(200) NOT NULL DEFAULT \'\',
      position VARCHAR(200) NOT NULL DEFAULT \'\',
      location VARCHAR(160) NOT NULL DEFAULT \'\',
      start_date VARCHAR(40) NOT NULL DEFAULT \'\',
      end_date VARCHAR(40) NOT NULL DEFAULT \'\',
      bullets MEDIUMTEXT NOT NULL,
      sort_order INT NOT NULL DEFAULT 0,
      visible TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$links = json_encode([], JSON_UNESCAPED_SLASHES);

$pdo->prepare(
    'UPDATE resume_profile SET
        full_name = ?,
        title = ?,
        email = ?,
        phone = ?,
        location = ?,
        gender = ?,
        date_of_birth = ?,
        country = ?,
        nationality = ?,
        show_photo = ?,
        links = ?
     WHERE id = 1'
)->execute([
    'Muqaddas Khan',
    'QA Engineer | Manual, API & Performance Testing',
    'mnkmuqaddaskhanajk@gmail.com',
    '+49 163 9064691',
    'Germany',
    'Female',
    null,
    '',
    '',
    0,
    $links,
]);

$sections = [
    [
        'summary',
        'Summary',
        'QA Engineer with hands-on experience in manual, API, and performance testing for web, mobile, and desktop applications. Skilled in designing and executing test cases, black-box and exploratory testing, regression and UAT, defect tracking, and collaborating with developers in Agile teams. Web development background strengthens understanding of application architecture and early risk detection. Currently studying at Hochschule Schmalkalden (FH Schmalkalden) and open to QA Engineer / Software Test Engineer roles in Germany (on-site, hybrid, or remote).',
        10,
        1,
    ],
    [
        'experience',
        'Experience',
        '',
        20,
        1,
    ],
    [
        'skills',
        'Skills',
        "Testing\nManual Testing · Functional Testing · Regression Testing · Smoke Testing · Sanity Testing · Exploratory Testing · Black Box Testing · UAT · Acceptance Testing · API Testing · Performance Testing · Mobile Testing · Web Testing · Desktop Application Testing · Cross-browser Testing · Cross-platform Testing · Test Case Design · Test Execution · Test Documentation · Defect Reporting · Defect Life Cycle · Requirement Analysis · Quality Assurance · Software Testing · Software Quality Assurance · SDLC · STLC · Agile Scrum\n\nTools\nPostman · Apache JMeter · Playwright · Appium · Jira · Azure DevOps · Zephyr · Mantis · Git · GitHub · SQL Server · Microsoft Office · Visual Studio · CI/CD pipelines\n\nProgramming\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL · WordPress",
        30,
        1,
    ],
    [
        'education',
        'Education',
        "M.Sc. Computer Science\nHochschule Schmalkalden – University of Applied Sciences, Germany\n2022 – Present\n\nBachelor's degree, Computer Science\nLahore Leads University – City Campus, Pakistan\n2017 – 2021",
        40,
        1,
    ],
    [
        'projects',
        'Certificates',
        "Software Testing Fundamentals for ISTQB Exams Prep Specialization — Coursera\nGoogle IT Support — Coursera\nTools & Techniques for Performance & Load Testing — Test Automation University\nAPI Test Automation with Postman — Test Automation University\nISTQB Foundation Level — Currently Preparing",
        50,
        1,
    ],
    [
        'languages',
        'Languages',
        "English — Professional Working Proficiency (C1)\nGerman — Basic Working Proficiency (A2, currently improving)",
        60,
        1,
    ],
];

$upd = $pdo->prepare(
    'UPDATE resume_sections SET title = ?, body = ?, sort_order = ?, visible = ? WHERE section_key = ?'
);
$ins = $pdo->prepare(
    'INSERT INTO resume_sections (section_key, title, body, sort_order, visible) VALUES (?, ?, ?, ?, ?)'
);

foreach ($sections as [$key, $title, $body, $order, $visible]) {
    $check = $pdo->prepare('SELECT id FROM resume_sections WHERE section_key = ?');
    $check->execute([$key]);
    if ($check->fetch()) {
        $upd->execute([$title, $body, $order, $visible, $key]);
    } else {
        $ins->execute([$key, $title, $body, $order, $visible]);
    }
}

$pdo->exec('DELETE FROM experience_entries');
$exp = $pdo->prepare(
    'INSERT INTO experience_entries (company, position, location, start_date, end_date, bullets, sort_order, visible)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
);

$exp->execute([
    'Neural Spark Tech',
    'Software Quality Assurance (SQA)',
    'Lahore, Punjab, Pakistan',
    'Aug 2025',
    'Dec 2025',
    "• Tested customer service web and desktop applications through manual testing.\n• Performed black box, exploratory, regression, smoke, and sanity testing.\n• Designed, executed, and maintained detailed test cases and test scenarios.\n• Identified, documented, and tracked software defects throughout the testing lifecycle.\n• Collaborated with developers to reproduce, verify, and validate bug fixes.\n• Participated in sprint planning, QA reviews, and release validation.\n• Performed cross-browser and cross-platform compatibility testing.\n• Assisted in improving QA processes, testing standards, and documentation.",
    10,
]);

$exp->execute([
    'Neural Spark Tech',
    'Associate Software Quality Assurance Engineer',
    'Lahore, Punjab, Pakistan',
    'Sep 2021',
    'Feb 2022',
    "• Performed quality assurance and comprehensive testing of web and mobile applications (iOS and Android), including cross-platform testing.\n• Designed, reviewed, and executed manual test cases and test scenarios for web and mobile applications.\n• Executed functional, regression, smoke, and user acceptance testing (UAT).\n• Performed API testing using Postman and performance/load testing with Apache JMeter.\n• Assisted with deployment automation scripts.\n• Logged and tracked defects through the defect life cycle using Mantis.\n• Executed SQL queries for backend data validation and database testing.\n• Validated email functionality using SMTP servers.",
    20,
]);

$exp->execute([
    'Digitizespot',
    'Web Developer Internship',
    'Lahore, Punjab, Pakistan',
    'Oct 2020',
    'Feb 2021',
    "• Developed and maintained responsive websites using PHP, WordPress, HTML5, CSS3, JavaScript, and MySQL.\n• Customized WordPress themes and plugins based on client requirements.\n• Built responsive user interfaces compatible with modern browsers and mobile devices.\n• Debugged frontend and backend issues to improve website functionality.\n• Collaborated with designers and developers to implement new features.\n• Used Git for version control and collaborative development.\n• Optimized website performance, usability, and page load speed.\n• Integrated third-party plugins, contact forms, and APIs.",
    30,
]);

$cover = <<<'TXT'
Dear Hiring Manager,

I am writing to apply for the QA Engineer / Software Quality Assurance role at your company. I bring hands-on experience in manual, API, and performance testing for web, mobile, and desktop applications, with a strong focus on structured test design, clear defect reporting, and close collaboration with developers in Agile environments.

At Neural Spark Tech, I tested customer-facing web and desktop applications using black-box, exploratory, regression, smoke, and sanity techniques. I designed and maintained detailed test cases, tracked defects through the full lifecycle, and partnered with developers to reproduce and validate fixes. In an earlier Associate SQA role, I also covered mobile (iOS/Android), API testing with Postman, performance testing with Apache JMeter, SQL-based data validation, and defect tracking in Mantis.

My earlier web development internship (PHP, WordPress, HTML/CSS/JavaScript, MySQL) helps me communicate clearly with engineers and spot quality risks earlier in the delivery cycle.

I am currently studying Computer Science at Hochschule Schmalkalden and am based in Germany, open to on-site, hybrid, or remote opportunities. I would welcome the chance to discuss how my testing experience and commitment to software quality can support your team.

Thank you for your time and consideration.

Sincerely,
Muqaddas Khan
TXT;

$pdo->exec('UPDATE cover_letters SET is_active = 0, is_base = 0');
$existsCover = $pdo->query('SELECT id FROM cover_letters ORDER BY id ASC LIMIT 1')->fetch();
if ($existsCover) {
    $pdo->prepare(
        'UPDATE cover_letters SET title = ?, body = ?, company = ?, is_active = 1, is_base = 1 WHERE id = ?'
    )->execute(['Main cover letter', $cover, '', (int) $existsCover['id']]);
} else {
    $pdo->prepare(
        'INSERT INTO cover_letters (title, body, company, is_active, is_base) VALUES (?, ?, ?, 1, 1)'
    )->execute(['Main cover letter', $cover, '']);
}

App::setSetting('active_company', '');
App::setSetting('theme', 'sage');
App::setSetting('font_family', 'candara');
App::setSetting('accent_color', '#4E6351');

$baseId = Versions::updateBaseFromLive('Main resume');

$pdo->prepare(
    'INSERT INTO search_history (company, role, note) VALUES (?, ?, ?)'
)->execute([
    'LinkedIn profile sync',
    'QA Engineer',
    'Updated live CV + Main resume/cover from LinkedIn (Neural Spark Tech, FH Schmalkalden, Germany).',
]);

echo 'OK ' . App::profile()['full_name'] . "\n";
echo 'title=' . App::profile()['title'] . "\n";
echo 'experiences=' . count(App::experiences()) . "\n";
echo 'main_resume_id=' . $baseId . "\n";
echo 'cover=' . (App::activeCoverLetter()['title'] ?? '') . "\n";
