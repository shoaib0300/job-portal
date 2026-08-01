<?php

declare(strict_types=1);

/**
 * Render profile contact + demographic lines; skips empty values.
 */
function render_profile_details(array $profile, bool $includeLinks = true): void
{
    $dob = App::formatDate(isset($profile['date_of_birth']) ? (string) $profile['date_of_birth'] : null);
    $contact = [];
    if (App::filled($profile['email'] ?? null)) {
        $contact[] = ['text' => (string) $profile['email']];
    }
    if (App::filled($profile['phone'] ?? null)) {
        $contact[] = ['text' => (string) $profile['phone']];
    }
    if (App::filled($profile['location'] ?? null)) {
        $contact[] = ['text' => (string) $profile['location']];
    }
    if ($includeLinks && !empty($profile['links']) && is_array($profile['links'])) {
        foreach ($profile['links'] as $link) {
            if (!empty($link['url'])) {
                $contact[] = [
                    'text' => (string) ($link['label'] ?? $link['url']),
                    'url' => (string) $link['url'],
                ];
            }
        }
    }

    $meta = [];
    if (App::filled($profile['gender'] ?? null)) {
        $meta[] = ['label' => 'Gender', 'text' => (string) $profile['gender']];
    }
    if ($dob !== '') {
        $meta[] = ['label' => 'Date of birth', 'text' => $dob];
    }
    if (App::filled($profile['country'] ?? null)) {
        $meta[] = ['label' => 'Country', 'text' => (string) $profile['country']];
    }
    if (App::filled($profile['nationality'] ?? null)) {
        $meta[] = ['label' => 'Nationality', 'text' => (string) $profile['nationality']];
    }

    if ($contact): ?>
    <ul class="resume-contact">
      <?php foreach ($contact as $item): ?>
        <li>
          <?php if (!empty($item['url'])): ?>
            <a href="<?= App::e($item['url']) ?>"><?= App::e($item['text']) ?></a>
          <?php else: ?>
            <?= App::e($item['text']) ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif;

    if ($meta): ?>
    <ul class="resume-meta">
      <?php foreach ($meta as $item): ?>
        <li><span><?= App::e($item['label']) ?></span> <?= App::e($item['text']) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif;
}
