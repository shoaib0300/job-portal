<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobText;

function assertEq(?string $actual, ?string $expected, string $label): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "OK {$label}\n";
}

function assertTrue(bool $cond, string $label): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }
    echo "OK {$label}\n";
}

// Start date in title must not become posted date.
assertEq(
    JobText::parsePostedDate('Junior Mitarbeiter Sanierung - Start 01.02.2027'),
    null,
    'start date in title ignored'
);

// Posting context in description.
assertEq(
    JobText::parsePostedDate('Veröffentlicht am 15.03.2026 auf stepstone.de'),
    '2026-03-15',
    'published date in description'
);

// ISO in snippet.
assertEq(
    JobText::parsePostedDate('Online since 2026-03-10 for applicants'),
    '2026-03-10',
    'iso date in snippet'
);

// Future ISO rejected.
assertEq(
    JobText::parsePostedDate('Posted 2031-01-01'),
    null,
    'future iso rejected'
);

// Relative DE.
$twoDays = date('Y-m-d', time() - 2 * 86400);
assertEq(
    JobText::parsePostedDate('vor 2 Tagen'),
    $twoDays,
    'vor 2 tagen'
);

// enrich: description before title; start in title only.
$job = new JobListing(
    'stepstone',
    '1',
    'Junior Mitarbeiter (m/w/d) - Start 01.02.2027',
    'Hamburger Sparkasse',
    'Hamburg',
    '',
    'Germany',
    'unknown',
    'unknown',
    'job',
    [],
    [],
    '',
    null,
    'https://example.test/job',
    'Stellenanzeige veröffentlicht am 20.08.2026.',
);
$job = JobText::enrich($job);
assertEq($job->postedAt, '2026-08-20', 'enrich uses description not title start date');

// sanitize future stored value.
assertEq(JobText::sanitizePostedAt('2027-02-01'), null, 'sanitize future');
assertEq(JobText::sanitizePostedAt('2026-08-20'), '2026-08-20', 'sanitize valid');

// formatPosted hides future.
assertEq(JobText::formatPosted('2027-02-01'), '', 'formatPosted future empty');
assertEq(JobText::formatPosted('2026-08-20'), date('j M Y', strtotime('2026-08-20')), 'formatPosted valid');

echo "All JobText posted-date tests passed.\n";
