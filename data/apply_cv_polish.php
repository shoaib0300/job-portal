<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Db::pdo();

$summary = 'QA Engineer with hands-on experience in manual, API, and performance testing for web, mobile, and desktop applications. Skilled in designing and executing test cases, black-box and exploratory testing, regression and UAT, defect tracking, and collaborating with developers in Agile teams. Web development background strengthens understanding of application architecture and early risk detection. Currently studying at Hochschule Schmalkalden (FH Schmalkalden) and open to QA Engineer / Software Test Engineer roles in Germany (on-site, hybrid, or remote).';

$skills = "Testing\nManual Testing · Functional Testing · Regression Testing · Smoke Testing · Sanity Testing · Exploratory Testing · Black Box Testing · UAT · Acceptance Testing · API Testing · Performance Testing · Mobile Testing · Web Testing · Desktop Application Testing · Cross-browser Testing · Cross-platform Testing · Test Case Design · Test Execution · Test Documentation · Defect Reporting · Defect Life Cycle · Requirement Analysis · Quality Assurance · Software Testing · Software Quality Assurance · SDLC · STLC · Agile Scrum\n\nTools\nPostman · Apache JMeter · Playwright · Appium · Jira · Azure DevOps · Zephyr · Mantis · Git · GitHub · SQL Server · Microsoft Office · Visual Studio · CI/CD pipelines\n\nProgramming\nPython · C# · PHP · JavaScript · HTML5 · CSS3 · MySQL · WordPress";

$certificates = "Software Testing Fundamentals for ISTQB Exams Prep Specialization — Coursera\nGoogle IT Support — Coursera\nTools & Techniques for Performance & Load Testing — Test Automation University\nAPI Test Automation with Postman — Test Automation University\nISTQB Foundation Level — Currently Preparing";

$languages = "English — Professional Working Proficiency (C1)\nGerman — Basic Working Proficiency (A2, currently improving)";

$pdo->prepare('UPDATE resume_sections SET body = ? WHERE section_key = ?')->execute([$summary, 'summary']);
$pdo->prepare('UPDATE resume_sections SET body = ? WHERE section_key = ?')->execute([$skills, 'skills']);
$pdo->prepare('UPDATE resume_sections SET title = ?, body = ? WHERE section_key = ?')->execute(['Certificates', $certificates, 'projects']);
$pdo->prepare('UPDATE resume_sections SET body = ? WHERE section_key = ?')->execute([$languages, 'languages']);

$education = "M.Sc. Computer Science\nHochschule Schmalkalden – University of Applied Sciences, Germany\n2022 – Present\n\nBachelor's degree, Computer Science\nLahore Leads University – City Campus, Pakistan\n2017 – 2021";
$pdo->prepare('UPDATE resume_sections SET body = ? WHERE section_key = ?')->execute([$education, 'education']);

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

Versions::ensureSchema();
Versions::updateBaseFromLive('Main resume');

echo "OK polished CV content applied + Main resume updated\n";
echo 'summary=' . strlen($summary) . "\n";
echo 'experiences=' . count(App::experiences()) . "\n";
