<?php

declare(strict_types=1);

/**
 * Render structured experience entries (company, dates, bold position, bullets).
 */
function render_experience_entries(array $entries): void
{
    if (!$entries) {
        return;
    }
    ?>
    <div class="experience-list">
      <?php foreach ($entries as $entry): ?>
        <article class="experience-item">
          <div class="experience-aside">
            <?php
            $range = App::experienceDateRange($entry);
            if ($range !== ''):
            ?>
              <p class="experience-dates"><?= App::e($range) ?></p>
            <?php endif; ?>
            <?php if (App::filled($entry['location'] ?? null)): ?>
              <p class="experience-location"><?= App::e($entry['location']) ?></p>
            <?php endif; ?>
          </div>
          <div class="experience-top">
            <div class="experience-left">
              <?php if (App::filled($entry['position'] ?? null)): ?>
                <p class="experience-position"><?= App::e($entry['position']) ?></p>
              <?php endif; ?>
              <?php if (App::filled($entry['company'] ?? null)): ?>
                <p class="experience-company"><?php
                  $companyLine = (string) $entry['company'];
                  if (App::filled($entry['location'] ?? null)) {
                      $companyLine .= ' · ' . (string) $entry['location'];
                  }
                  echo App::e($companyLine);
                ?></p>
              <?php endif; ?>
            </div>
            <div class="experience-right">
              <?php if ($range !== ''): ?>
                <p class="experience-dates"><?= App::e($range) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php if (App::filled($entry['bullets'] ?? null)): ?>
            <?php
            $lines = preg_split('/\R+/u', (string) $entry['bullets']) ?: [];
            $items = [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                $line = preg_replace('/^[•\x{2022}\x{00B7}\-\*]+\s*/u', '', $line) ?? $line;
                $line = trim($line);
                if ($line !== '') {
                    $items[] = $line;
                }
            }
            ?>
            <?php if ($items): ?>
              <ul class="experience-bullets">
                <?php foreach ($items as $item): ?>
                  <li><?= App::e($item) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Render skills body with bold category headlines (Testing / Tools / Programming).
 */
function render_skills_body(string $body): void
{
    $body = trim($body);
    if ($body === '') {
        return;
    }

    $blocks = preg_split("/\n{2,}/", $body) ?: [];
    echo '<div class="skills-groups">';
    foreach ($blocks as $block) {
        $block = trim((string) $block);
        if ($block === '') {
            continue;
        }
        $lines = preg_split("/\R/u", $block) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $l): bool => $l !== ''));
        if (!$lines) {
            continue;
        }
        $heading = array_shift($lines);
        $items = trim(implode(' ', $lines));
        // Single-line "Category: a · b" style
        if ($items === '' && str_contains($heading, ':')) {
            [$heading, $rest] = array_map('trim', explode(':', $heading, 2));
            $items = $rest;
        }
        ?>
        <div class="skills-group">
          <h3 class="skills-heading"><?= App::e($heading) ?></h3>
          <?php if ($items !== ''): ?>
            <p class="skills-items"><?= App::e($items) ?></p>
          <?php endif; ?>
        </div>
        <?php
    }
    echo '</div>';
}

/**
 * Render education entries: degree (bold), school · city, dates, optional focus lines.
 */
function render_education_body(string $body): void
{
    $body = trim($body);
    if ($body === '') {
        return;
    }

    $blocks = preg_split("/\n{2,}/", $body) ?: [];
    echo '<div class="education-groups">';
    foreach ($blocks as $block) {
        $block = trim((string) $block);
        if ($block === '') {
            continue;
        }
        $lines = preg_split("/\R/u", $block) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $l): bool => $l !== ''));
        if (!$lines) {
            continue;
        }
        $degree = array_shift($lines);
        $school = $lines[0] ?? '';
        $dates = $lines[1] ?? '';
        $extra = array_slice($lines, 2);
        ?>
        <div class="education-item">
          <p class="education-degree"><?= App::e($degree) ?></p>
          <?php if ($school !== ''): ?>
            <p class="education-school"><?= App::e($school) ?></p>
          <?php endif; ?>
          <?php if ($dates !== ''): ?>
            <p class="education-dates"><?= App::e($dates) ?></p>
          <?php endif; ?>
          <?php foreach ($extra as $line): ?>
            <p class="education-extra"><?= App::e($line) ?></p>
          <?php endforeach; ?>
        </div>
        <?php
    }
    echo '</div>';
}

/**
 * Languages: one per line "Deutsch — B1" or "English: C1".
 */
function render_languages_body(string $body): void
{
    $body = trim($body);
    if ($body === '') {
        return;
    }
    $lines = preg_split('/\R+/u', $body) ?: [];
    echo '<ul class="languages-list">';
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^(.+?)\s*[—\-–:]\s*(.+)$/u', $line, $m)) {
            echo '<li><span class="lang-name">' . App::e(trim($m[1])) . '</span>'
                . ' <span class="lang-sep">—</span> '
                . '<span class="lang-level">' . App::e(trim($m[2])) . '</span></li>';
        } else {
            echo '<li>' . App::e($line) . '</li>';
        }
    }
    echo '</ul>';
}

/**
 * Certificates: blank-line blocks — name / provider / date (+ optional ID/URL).
 */
function render_certificates_body(string $body): void
{
    $body = trim($body);
    if ($body === '') {
        return;
    }
    $blocks = preg_split("/\n{2,}/", $body) ?: [];
    echo '<div class="certificate-list">';
    foreach ($blocks as $block) {
        $lines = array_values(array_filter(array_map('trim', preg_split("/\R/u", trim((string) $block)) ?: [])));
        if ($lines === []) {
            continue;
        }
        echo '<div class="certificate-item">';
        echo '<p class="certificate-name">' . App::e($lines[0]) . '</p>';
        if (!empty($lines[1])) {
            echo '<p class="certificate-provider">' . App::e($lines[1]) . '</p>';
        }
        if (!empty($lines[2])) {
            echo '<p class="certificate-date">' . App::e($lines[2]) . '</p>';
        }
        foreach (array_slice($lines, 3) as $extra) {
            echo '<p class="certificate-extra">' . App::e($extra) . '</p>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Projects: blank-line blocks — name / description / tech · urls.
 */
function render_projects_body(string $body): void
{
    $body = trim($body);
    if ($body === '') {
        return;
    }
    $blocks = preg_split("/\n{2,}/", $body) ?: [];
    echo '<div class="project-list">';
    foreach ($blocks as $block) {
        $lines = array_values(array_filter(array_map('trim', preg_split("/\R/u", trim((string) $block)) ?: [])));
        if ($lines === []) {
            continue;
        }
        echo '<div class="project-item">';
        echo '<p class="project-name">' . App::e($lines[0]) . '</p>';
        if (!empty($lines[1])) {
            echo '<p class="project-desc">' . App::e($lines[1]) . '</p>';
        }
        if (!empty($lines[2])) {
            echo '<p class="project-tech">' . App::e($lines[2]) . '</p>';
        }
        foreach (array_slice($lines, 3) as $extra) {
            echo '<p class="project-extra">' . App::e($extra) . '</p>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/** Internships reuse experience-style free text (degree-like blocks or bullets). */
function render_internships_body(string $body): void
{
    render_education_body($body);
}

/** Signature / place+date block. */
function render_signature_body(string $body, array $profile = []): void
{
    $body = trim($body);
    if ($body === '') {
        $loc = trim((string) ($profile['location'] ?? ''));
        $name = trim((string) ($profile['full_name'] ?? ''));
        $body = ($loc !== '' ? $loc . ', ' : '') . date('d.m.Y') . "\n\n" . $name;
    }
    echo '<div class="resume-signature">' . App::nl2p($body) . '</div>';
}

/**
 * Dispatch section body by section_key.
 */
function render_resume_section_body(string $key, string $body, array $experiences = [], array $profile = []): void
{
    switch ($key) {
        case 'experience':
            render_experience_entries($experiences);
            break;
        case 'skills':
            echo '<div class="resume-body">';
            render_skills_body($body);
            echo '</div>';
            break;
        case 'education':
            echo '<div class="resume-body">';
            render_education_body($body);
            echo '</div>';
            break;
        case 'languages':
            echo '<div class="resume-body">';
            render_languages_body($body);
            echo '</div>';
            break;
        case 'certificates':
            echo '<div class="resume-body">';
            render_certificates_body($body);
            echo '</div>';
            break;
        case 'projects':
            echo '<div class="resume-body">';
            render_projects_body($body);
            echo '</div>';
            break;
        case 'internships':
            echo '<div class="resume-body">';
            render_internships_body($body);
            echo '</div>';
            break;
        case 'signature':
            render_signature_body($body, $profile);
            break;
        default:
            echo '<div class="resume-body">' . App::nl2p($body) . '</div>';
            break;
    }
}
