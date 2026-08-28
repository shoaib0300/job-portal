<?php

declare(strict_types=1);

/**
 * Shared guide steps for marketing /guide and dashboard /help.
 *
 * @return list<array{num: string, icon: string, title: string, body: string, mini: ?string, links: list<array{href: string, label: string, primary?: bool}>}>
 */
function guide_steps_data(): array
{
    $brand = kaamfit_brand_name();

    return [
        [
            'num' => '01',
            'icon' => 'doc',
            'title' => 'Set up Master CV & cover letter',
            'body' => '<strong>Master CV</strong> and <strong>Master cover letter</strong> are your safe templates — they never get overwritten when you apply. '
                . 'Fill them once under <a href="/editor">Resume</a> and <a href="/cover">Cover letter</a>. Pick fonts and colours on <a href="/design">Resume style</a> and <a href="/cover-design">Cover style</a>.',
            'mini' => 'resume',
            'links' => [
                ['href' => '/editor', 'label' => 'Master CV', 'primary' => true],
                ['href' => '/cover', 'label' => 'Master cover'],
            ],
        ],
        [
            'num' => '02',
            'icon' => 'search',
            'title' => 'Find jobs & filter',
            'body' => 'Open <a href="/jobs">Jobs</a>. Add role keywords (e.g. QA, Werkstudent), city, and how recent the posting is. '
                . 'Under <strong>Sources &amp; companies</strong>, tick job boards (Bundesagentur, Jobexport, Adzuna, company career pages…). '
                . 'Use <strong>Filter by company</strong> on the right to narrow to boards like Rossmann or Greenhouse employers. Click <strong>Search</strong>, open a listing, then apply on the employer site.',
            'mini' => 'jobs',
            'links' => [
                ['href' => '/jobs', 'label' => 'Open Jobs', 'primary' => true],
                ['href' => '/companies', 'label' => 'Manage boards'],
            ],
        ],
        [
            'num' => '03',
            'icon' => 'letter',
            'title' => 'Apply with New job (paste a JD)',
            'body' => 'Already have a listing? Go to <a href="/tailor">New job</a>. Paste company, role, <strong>location</strong> (city + country), and the job description. '
                . $brand . ' copies Master CV and Master cover into a <strong>Job CV</strong> and job letter for that application — Master stays clean. The job is logged on <a href="/applications">Applications</a>.',
            'mini' => 'copy',
            'links' => [
                ['href' => '/tailor', 'label' => 'New job', 'primary' => true],
            ],
        ],
        [
            'num' => '04',
            'icon' => 'doc',
            'title' => 'Tailor resume & letter for that company',
            'body' => 'Open <a href="/editor">Resume</a> and switch to your job copy (or use the version picker). Adjust summary, skills, and experience wording for the role. '
                . 'Do the same on <a href="/cover">Cover letter</a> for that application. Keep content in <strong>English</strong> in the editor; use translation only when exporting (see next step).',
            'mini' => 'edit',
            'links' => [
                ['href' => '/editor', 'label' => 'Edit resume', 'primary' => true],
                ['href' => '/cover', 'label' => 'Edit cover'],
            ],
        ],
        [
            'num' => '05',
            'icon' => 'pdf',
            'title' => 'Download PDF vs Translate PDF',
            'body' => '<strong>Download PDF</strong> — free. Exports exactly what you wrote, in your document language (set under <a href="/settings">Account</a>). No DeepL. '
                . '<strong>Translate PDF…</strong> — paid via DeepL. Pick any target language (German, Urdu, …). '
                . 'Repeat downloads of the <em>same text</em> use the cache — you are not billed again until you edit the document.',
            'mini' => null,
            'links' => [
                ['href' => '/design', 'label' => 'Resume style', 'primary' => true],
                ['href' => '/settings', 'label' => 'Account & usage'],
            ],
        ],
        [
            'num' => '06',
            'icon' => 'track',
            'title' => 'Track applications',
            'body' => '<a href="/applications">Applications</a> lists every job you tailored or logged. Filter by status: Applied, Interview, Offer, Rejected, or Custom. '
                . 'Update status as you hear back. CV and cover links open the job-specific copies; use PDF or Translate PDF from there.',
            'mini' => 'track',
            'links' => [
                ['href' => '/applications', 'label' => 'Applications', 'primary' => true],
            ],
        ],
        [
            'num' => '07',
            'icon' => 'user',
            'title' => 'Account & language settings',
            'body' => 'On <a href="/settings">Account</a>: profile, <strong>document language</strong> (English/German for free PDF), default translate language, translation usage, dashboard colours, and password.',
            'mini' => null,
            'links' => [
                ['href' => '/settings', 'label' => 'Account settings', 'primary' => true],
            ],
        ],
    ];
}

/** @param 'dash'|'site' $variant */
function guide_render_steps(string $variant = 'dash'): void
{
    $listClass = $variant === 'site' ? 'site-guide-list' : 'guide-page-list';
    $stepClass = $variant === 'site' ? 'site-guide-step site-reveal' : 'guide-step card shadow-sm';
    $numClass = $variant === 'site' ? 'site-guide-num' : 'guide-step-num';

    echo '<div class="' . $listClass . '">';
    foreach (guide_steps_data() as $step) {
        echo '<article class="' . $stepClass . '">';
        echo kaamfit_icon($step['icon']);
        echo '<div class="guide-step-body">';
        echo '<div class="' . $numClass . '">' . App::e($step['num']) . '</div>';
        echo '<h3 class="h5">' . App::e($step['title']) . '</h3>';
        if (!empty($step['mini'])) {
            onboarding_render_mini($step['mini'], true);
        }
        echo '<p class="guide-step-text">' . $step['body'] . '</p>';
        if (!empty($step['links'])) {
            echo '<div class="guide-step-actions d-flex flex-wrap gap-2 mt-2">';
            foreach ($step['links'] as $link) {
                $btn = !empty($link['primary']) ? 'btn-primary' : 'btn-outline-secondary';
                echo '<a class="btn btn-sm ' . $btn . '" href="' . App::e($link['href']) . '">' . App::e($link['label']) . '</a>';
            }
            echo '</div>';
        }
        echo '</div></article>';
    }
    echo '</div>';
}

function guide_render_dashboard_page(): void
{
    ?>
<main class="guide-page">
  <header class="page-head mb-3">
    <p class="text-secondary small mb-1">Guide</p>
    <h1 class="h2 mb-2">How to use <?= App::e(kaamfit_brand_name()) ?></h1>
    <p class="text-secondary mb-0">Resume, job search, tailoring, PDF export, and tracking — step by step. First full run takes about 10–15 minutes.</p>
  </header>

  <?php onboarding_render_hero(true, false); ?>

  <?php guide_render_steps('dash'); ?>

  <div class="card shadow-sm mt-4 guide-page-cta">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h2 class="h5 mb-1">Ready to try?</h2>
        <p class="small text-secondary mb-0">Search one board, paste a sample JD, export a PDF.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="/jobs">Find jobs</a>
        <a class="btn btn-outline-secondary" href="/tailor">New job</a>
      </div>
    </div>
  </div>
</main>
    <?php
}
