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
          <div class="experience-top">
            <div class="experience-left">
              <?php if (App::filled($entry['company'] ?? null)): ?>
                <p class="experience-company"><?= App::e($entry['company']) ?></p>
              <?php endif; ?>
              <?php if (App::filled($entry['position'] ?? null)): ?>
                <p class="experience-position"><?= App::e($entry['position']) ?></p>
              <?php endif; ?>
            </div>
            <div class="experience-right">
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
          </div>
          <?php if (App::filled($entry['bullets'] ?? null)): ?>
            <div class="experience-bullets"><?= App::nl2p((string) $entry['bullets']) ?></div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}
