<?php

declare(strict_types=1);

/**
 * Render structured experience entries (company, dates, bold position, bullets).
 */
function render_experience_entries(array $entries): void
{
    if (!$entries) {
        echo '<p class="empty">No experience entries yet.</p>';
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
                <p class="experience-company"><?= App::e($entry['company']) ?></p>
              <?php endif; ?>
            </div>
            <div class="experience-right">
              <?php if ($range !== ''): ?>
                <p class="experience-dates"><?= App::e($range) ?></p>
              <?php endif; ?>
              <?php if (App::filled($entry['location'] ?? null)): ?>
                <p class="experience-location"><?= App::e($entry['location']) ?></p>
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
 * Render education entries: degree (bold), school, dates.
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
        ?>
        <div class="education-item">
          <p class="education-degree"><?= App::e($degree) ?></p>
          <?php if ($school !== ''): ?>
            <p class="education-school"><?= App::e($school) ?></p>
          <?php endif; ?>
          <?php if ($dates !== ''): ?>
            <p class="education-dates"><?= App::e($dates) ?></p>
          <?php endif; ?>
        </div>
        <?php
    }
    echo '</div>';
}
