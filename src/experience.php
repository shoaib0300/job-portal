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
 * Supports structured JSON (schema_version ≥ 1) with plain-text fallback.
 */
function render_skills_body(string $body): void
{
    $structured = \KaamFit\Resume\SectionBody::decode($body);
    if ($structured !== null && ($structured['kind'] ?? '') === 'skills') {
        echo '<div class="skills-groups" data-preview-skills>';
        foreach ($structured['categories'] ?? [] as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $heading = trim((string) ($cat['name'] ?? ''));
            $parts = [];
            foreach ($cat['skills'] ?? [] as $skill) {
                if (!is_array($skill)) {
                    continue;
                }
                $name = trim((string) ($skill['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $level = trim((string) ($skill['level'] ?? ''));
                $parts[] = $level !== '' ? $name . ' (' . $level . ')' : $name;
            }
            if ($heading === '' && $parts === []) {
                continue;
            }
            ?>
            <div class="skills-group">
              <?php if ($heading !== ''): ?>
                <h3 class="skills-heading"><?= App::e($heading) ?></h3>
              <?php endif; ?>
              <?php if ($parts !== []): ?>
                <p class="skills-items"><?= App::e(implode(' · ', $parts)) ?></p>
              <?php endif; ?>
            </div>
            <?php
        }
        echo '</div>';

        return;
    }

    $body = trim($body);
    if ($body === '') {
        return;
    }

    $blocks = preg_split("/\n{2,}/", $body) ?: [];
    echo '<div class="skills-groups" data-preview-skills>';
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
 * Render education entries stacked on separate lines:
 * degree → school → dates. Never put the next degree beside dates.
 * Supports structured JSON with plain-text fallback.
 */
function render_education_body(string $body): void
{
    $structured = \KaamFit\Resume\SectionBody::decode($body);
    if ($structured !== null && ($structured['kind'] ?? '') === 'entries') {
        echo '<div class="education-groups" data-preview-education>';
        foreach ($structured['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $degree = trim((string) ($entry['title'] ?? $entry['degree'] ?? ''));
            $school = trim((string) ($entry['institution'] ?? $entry['school'] ?? ''));
            $field = trim((string) ($entry['field'] ?? ''));
            $location = trim((string) ($entry['location'] ?? ''));
            $start = trim((string) ($entry['start'] ?? ''));
            $end = trim((string) ($entry['end'] ?? ''));
            $dates = '';
            if ($start !== '' || $end !== '') {
                $dates = trim($start . ($start !== '' && $end !== '' ? ' – ' : '') . $end);
            }
            $desc = trim((string) ($entry['description'] ?? ''));
            if ($degree === '' && $school === '' && $dates === '' && $desc === '') {
                continue;
            }
            if ($field !== '' && $school !== '') {
                $school = $school . ' · ' . $field;
            } elseif ($field !== '') {
                $school = $field;
            }
            if ($location !== '' && $school !== '') {
                $school .= ' · ' . $location;
            } elseif ($location !== '') {
                $school = $location;
            }
            ?>
            <div class="education-item">
              <?php if ($degree !== ''): ?>
                <p class="education-degree"><?= App::e($degree) ?></p>
              <?php endif; ?>
              <?php if ($school !== ''): ?>
                <p class="education-school"><?= App::e($school) ?></p>
              <?php endif; ?>
              <?php if ($dates !== ''): ?>
                <p class="education-dates"><?= App::e($dates) ?></p>
              <?php endif; ?>
              <?php if ($desc !== ''): ?>
                <p class="education-extra"><?= App::e($desc) ?></p>
              <?php endif; ?>
            </div>
            <?php
        }
        echo '</div>';

        return;
    }

    $body = trim($body);
    if ($body === '') {
        return;
    }

    // Unstick "2022 – heute Bachelor…" before block splitting.
    $body = preg_replace_callback(
        '/(\d{4}\s*[–—\-]\s*(?:\d{4}|heute|present|aktuell|now))\s+((?:M\.?\s*Sc\.|B\.?\s*Sc\.|Bachelor|Master|Diplom|Dr\.|Ph\.?D\.)[^\n]*)/iu',
        static fn(array $m): string => $m[1] . "\n\n" . $m[2],
        $body
    ) ?? $body;

    $blocks = preg_split("/\n{2,}/", $body) ?: [];
    // If blank lines were lost, still split before a new degree heading.
    if (count($blocks) < 2) {
        $parts = preg_split(
            '/\R(?=(?:M\.?\s*Sc\.|B\.?\s*Sc\.|Bachelor|Master|Diplom|Dr\.|Ph\.?D\.|Abitur)\b)/iu',
            $body
        ) ?: [];
        if (count($parts) > 1) {
            $blocks = $parts;
        }
    }

    echo '<div class="education-groups" data-preview-education>';
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
        $school = '';
        $dates = '';
        $extra = [];
        foreach ($lines as $line) {
            if ($dates === '' && preg_match('/^\d{4}\s*[–—\-]\s*(?:\d{4}|heute|present|aktuell|now)\b/iu', $line)) {
                $dates = $line;
            } elseif ($school === '' && $dates === '') {
                $school = $line;
            } else {
                $extra[] = $line;
            }
        }
        ?>
        <div class="education-item">
          <p class="education-degree"><?= App::e((string) $degree) ?></p>
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
 * Languages: structured JSON or plain "Deutsch — B1" lines.
 */
function render_languages_body(string $body): void
{
    $structured = \KaamFit\Resume\SectionBody::decode($body);
    if ($structured !== null && ($structured['kind'] ?? '') === 'entries') {
        echo '<ul class="languages-list" data-preview-languages>';
        foreach ($structured['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = trim((string) ($entry['name'] ?? $entry['title'] ?? ''));
            $level = trim((string) ($entry['level'] ?? ''));
            if ($name === '') {
                continue;
            }
            if ($level !== '') {
                echo '<li><span class="lang-name">' . App::e($name) . '</span>'
                    . ' <span class="lang-sep">—</span> '
                    . '<span class="lang-level">' . App::e($level) . '</span></li>';
            } else {
                echo '<li>' . App::e($name) . '</li>';
            }
        }
        echo '</ul>';

        return;
    }

    $body = trim($body);
    if ($body === '') {
        return;
    }
    $lines = preg_split('/\R+/u', $body) ?: [];
    echo '<ul class="languages-list" data-preview-languages>';
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
 * Certificates: structured entries or blank-line blocks.
 */
function render_certificates_body(string $body): void
{
    $structured = \KaamFit\Resume\SectionBody::decode($body);
    if ($structured !== null && ($structured['kind'] ?? '') === 'entries') {
        echo '<div class="certificate-list" data-preview-certificates>';
        foreach ($structured['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = trim((string) ($entry['title'] ?? $entry['name'] ?? ''));
            $provider = trim((string) ($entry['issuer'] ?? $entry['institution'] ?? $entry['org'] ?? ''));
            $date = trim((string) ($entry['end'] ?? $entry['start'] ?? ''));
            $extra = trim((string) ($entry['description'] ?? ''));
            if ($name === '' && $provider === '') {
                continue;
            }
            echo '<div class="certificate-item">';
            if ($name !== '') {
                echo '<p class="certificate-name">' . App::e($name) . '</p>';
            }
            if ($provider !== '') {
                echo '<p class="certificate-provider">' . App::e($provider) . '</p>';
            }
            if ($date !== '') {
                echo '<p class="certificate-date">' . App::e($date) . '</p>';
            }
            if ($extra !== '') {
                echo '<p class="certificate-extra">' . App::e($extra) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';

        return;
    }

    $body = trim($body);
    if ($body === '') {
        return;
    }
    $blocks = preg_split("/\n{2,}/", $body) ?: [];
    echo '<div class="certificate-list" data-preview-certificates>';
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
 * Projects: structured entries or blank-line blocks.
 */
function render_projects_body(string $body): void
{
    $structured = \KaamFit\Resume\SectionBody::decode($body);
    if ($structured !== null && ($structured['kind'] ?? '') === 'entries') {
        echo '<div class="project-list" data-preview-projects>';
        foreach ($structured['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = trim((string) ($entry['title'] ?? $entry['name'] ?? ''));
            $desc = trim((string) ($entry['description'] ?? ''));
            $tech = trim((string) ($entry['tech'] ?? ''));
            $url = trim((string) ($entry['url'] ?? ''));
            if ($name === '' && $desc === '') {
                continue;
            }
            echo '<div class="project-item">';
            if ($name !== '') {
                echo '<p class="project-name">' . App::e($name) . '</p>';
            }
            if ($desc !== '') {
                echo '<p class="project-desc">' . App::e($desc) . '</p>';
            }
            if ($tech !== '') {
                echo '<p class="project-tech">' . App::e($tech) . '</p>';
            }
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
                $label = str_contains($host, 'github.com') ? 'GitHub'
                    : (str_contains($host, 'linkedin.com') ? 'LinkedIn' : $url);
                echo '<p class="project-extra"><a href="' . App::e($url) . '">' . App::e($label) . '</a></p>';
            }
            echo '</div>';
        }
        echo '</div>';

        return;
    }

    $body = trim($body);
    if ($body === '') {
        return;
    }
    $blocks = preg_split("/\n{2,}/", $body) ?: [];
    echo '<div class="project-list" data-preview-projects>';
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
            if (preg_match('#^https?://#i', $extra)) {
                $host = strtolower((string) (parse_url($extra, PHP_URL_HOST) ?: ''));
                $label = str_contains($host, 'github.com') ? 'GitHub'
                    : (str_contains($host, 'linkedin.com') ? 'LinkedIn' : $extra);
                echo '<p class="project-extra"><a href="' . App::e($extra) . '">' . App::e($label) . '</a></p>';
            } elseif (strcasecmp($extra, 'GitHub') === 0 || strcasecmp($extra, 'LinkedIn') === 0) {
                // Label-only line without URL — skip (URL usually follows)
                continue;
            } else {
                echo '<p class="project-extra">' . App::e($extra) . '</p>';
            }
        }
        echo '</div>';
    }
    echo '</div>';
}

/** Generic structured entry list (awards, volunteering, publications, …). */
function render_structured_entries_body(string $body, string $listClass = 'entry-list'): void
{
    $structured = \KaamFit\Resume\SectionBody::decode($body);
    if ($structured !== null && ($structured['kind'] ?? '') === 'entries') {
        echo '<div class="' . App::e($listClass) . '">';
        foreach ($structured['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $title = trim((string) ($entry['title'] ?? $entry['name'] ?? ''));
            $org = trim((string) ($entry['org'] ?? $entry['institution'] ?? $entry['issuer'] ?? ''));
            $location = trim((string) ($entry['location'] ?? ''));
            $start = trim((string) ($entry['start'] ?? ''));
            $end = trim((string) ($entry['end'] ?? ''));
            $dates = trim($start . ($start !== '' && $end !== '' ? ' – ' : '') . $end);
            $desc = trim((string) ($entry['description'] ?? ''));
            if ($title === '' && $org === '' && $desc === '') {
                continue;
            }
            echo '<div class="entry-item">';
            if ($title !== '') {
                echo '<p class="entry-title">' . App::e($title) . '</p>';
            }
            if ($org !== '') {
                echo '<p class="entry-org">' . App::e($org . ($location !== '' ? ' · ' . $location : '')) . '</p>';
            } elseif ($location !== '') {
                echo '<p class="entry-org">' . App::e($location) . '</p>';
            }
            if ($dates !== '') {
                echo '<p class="entry-dates">' . App::e($dates) . '</p>';
            }
            if ($desc !== '') {
                echo '<p class="entry-desc">' . App::e($desc) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';

        return;
    }

    echo App::nl2p($body);
}

/** Internships reuse experience-style free text (degree-like blocks or bullets). */
function render_internships_body(string $body): void
{
    $structured = \KaamFit\Resume\SectionBody::decode($body);
    if ($structured !== null) {
        render_structured_entries_body($body, 'internship-list');

        return;
    }
    render_education_body($body);
}

/** Signature / place+date block. */
function render_signature_body(string $body, array $profile = []): void
{
    $structured = \KaamFit\Resume\SectionBody::decode($body);
    if ($structured !== null && ($structured['kind'] ?? '') === 'text') {
        $body = (string) ($structured['text'] ?? '');
    }
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
    // Text-kind structured bodies for summary / hobbies / additional / etc.
    $structured = \KaamFit\Resume\SectionBody::decode($body);
    if ($structured !== null && ($structured['kind'] ?? '') === 'text'
        && !in_array($key, ['skills', 'education', 'languages', 'certificates', 'projects', 'internships', 'signature'], true)
    ) {
        echo '<div class="resume-body" data-preview-section="' . App::e($key) . '">'
            . App::nl2p((string) ($structured['text'] ?? ''))
            . '</div>';

        return;
    }

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
        case 'awards':
        case 'achievements':
        case 'volunteering':
        case 'publications':
        case 'references':
            echo '<div class="resume-body">';
            render_structured_entries_body($body);
            echo '</div>';
            break;
        case 'signature':
            render_signature_body($body, $profile);
            break;
        default:
            if (str_starts_with($key, 'custom_') && $structured !== null && ($structured['kind'] ?? '') === 'entries') {
                echo '<div class="resume-body">';
                render_structured_entries_body($body);
                echo '</div>';
                break;
            }
            if ($structured !== null && ($structured['kind'] ?? '') === 'text') {
                echo '<div class="resume-body">' . App::nl2p((string) ($structured['text'] ?? '')) . '</div>';
                break;
            }
            echo '<div class="resume-body" data-preview-section="' . App::e($key) . '">' . App::nl2p($body) . '</div>';
            break;
    }
}
