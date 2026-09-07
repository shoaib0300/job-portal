<?php

declare(strict_types=1);

/**
 * Render profile contact + demographic lines; skips empty values.
 *
 * @param array $profile
 * @param bool $includeLinks
 * @param bool $includeMeta  gender / DOB / country / nationality
 * @param string $uiLang  en|de for meta labels
 */
function render_profile_details(
    array $profile,
    bool $includeLinks = true,
    bool $includeMeta = true,
    string $uiLang = 'en'
): void {
    $isDe = str_starts_with(strtolower($uiLang), 'de');
    $dob = App::formatDate(isset($profile['date_of_birth']) ? (string) $profile['date_of_birth'] : null);
    $contact = [];
    if (App::filled($profile['location'] ?? null)) {
        $contact[] = ['text' => (string) $profile['location']];
    }
    if (App::filled($profile['phone'] ?? null)) {
        $phone = (string) $profile['phone'];
        $item = ['text' => $phone];
        if ($includeLinks) {
            $tel = preg_replace('/[^\d+]/', '', $phone) ?: $phone;
            $item['url'] = 'tel:' . $tel;
        }
        $contact[] = $item;
    }
    if (App::filled($profile['email'] ?? null)) {
        $email = (string) $profile['email'];
        $item = ['text' => $email];
        if ($includeLinks) {
            $item['url'] = 'mailto:' . $email;
        }
        $contact[] = $item;
    }
    if ($includeLinks && !empty($profile['links']) && is_array($profile['links'])) {
        foreach ($profile['links'] as $link) {
            if (!empty($link['url'])) {
                $label = (string) ($link['label'] ?? $link['url']);
                $url = (string) $link['url'];
                // Prefer host path for display when label looks like a full URL
                if (str_starts_with($label, 'http')) {
                    $host = parse_url($url, PHP_URL_HOST) ?: $label;
                    $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
                    $label = $path !== '' ? $host . '/' . $path : (string) $host;
                }
                $contact[] = [
                    'text' => $label,
                    'url' => $url,
                ];
            } elseif (!empty($link['label'])) {
                $contact[] = ['text' => (string) $link['label']];
            }
        }
    } elseif (!$includeLinks && !empty($profile['links']) && is_array($profile['links'])) {
        foreach ($profile['links'] as $link) {
            if (!empty($link['url'])) {
                $label = (string) ($link['label'] ?? $link['url']);
                $url = (string) $link['url'];
                if (str_starts_with($label, 'http') || $label === $url) {
                    $host = parse_url($url, PHP_URL_HOST) ?: $url;
                    $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
                    $label = $path !== '' ? $host . '/' . $path : (string) $host;
                }
                $contact[] = ['text' => $label];
            }
        }
    }

    $meta = [];
    if ($includeMeta) {
        $labels = $isDe
            ? ['gender' => 'Geschlecht', 'dob' => 'Geburtsdatum', 'country' => 'Land', 'nationality' => 'Staatsangehörigkeit']
            : ['gender' => 'Gender', 'dob' => 'Date of birth', 'country' => 'Country', 'nationality' => 'Nationality'];
        if (App::filled($profile['gender'] ?? null)) {
            $meta[] = ['label' => $labels['gender'], 'text' => (string) $profile['gender']];
        }
        if ($dob !== '') {
            $meta[] = ['label' => $labels['dob'], 'text' => $dob];
        }
        if (App::filled($profile['country'] ?? null)) {
            $meta[] = ['label' => $labels['country'], 'text' => (string) $profile['country']];
        }
        if (App::filled($profile['nationality'] ?? null)) {
            $meta[] = ['label' => $labels['nationality'], 'text' => (string) $profile['nationality']];
        }
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
