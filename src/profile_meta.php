<?php

declare(strict_types=1);

/**
 * Render profile contact + demographic lines; skips empty values.
 *
 * @param array $profile
 * @param bool $includeLinks
 * @param bool $includeMeta  gender / DOB / country / nationality
 * @param string $uiLang  en|de for meta labels
 * @param array{location?:bool,phone?:bool,email?:bool,links?:bool}|null $fields  null = show all contact fields
 */
function render_profile_details(
    array $profile,
    bool $includeLinks = true,
    bool $includeMeta = true,
    string $uiLang = 'en',
    ?array $fields = null
): void {
    $isDe = str_starts_with(strtolower($uiLang), 'de');
    $dob = App::formatDate(isset($profile['date_of_birth']) ? (string) $profile['date_of_birth'] : null);
    $show = [
        'location' => $fields === null || ($fields['location'] ?? true),
        'phone' => $fields === null || ($fields['phone'] ?? true),
        'email' => $fields === null || ($fields['email'] ?? true),
        'links' => $fields === null || ($fields['links'] ?? true),
    ];
    $contact = [];
    if ($show['location'] && App::filled($profile['location'] ?? null)) {
        $contact[] = ['text' => (string) $profile['location']];
    }
    if ($show['phone'] && App::filled($profile['phone'] ?? null)) {
        $phone = (string) $profile['phone'];
        $item = ['text' => $phone];
        if ($includeLinks) {
            $tel = preg_replace('/[^\d+]/', '', $phone) ?: $phone;
            $item['url'] = 'tel:' . $tel;
        }
        $contact[] = $item;
    }
    if ($show['email'] && App::filled($profile['email'] ?? null)) {
        $email = (string) $profile['email'];
        $item = ['text' => $email];
        if ($includeLinks) {
            $item['url'] = 'mailto:' . $email;
        }
        $contact[] = $item;
    }
    if ($show['links'] && $includeLinks && !empty($profile['links']) && is_array($profile['links'])) {
        foreach ($profile['links'] as $link) {
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                // No URL → hide LinkedIn/GitHub/etc. (never show label-only fake links).
                continue;
            }
            if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, 'mailto:') && !str_starts_with($url, 'tel:')) {
                $url = 'https://' . ltrim($url, '/');
            }
            $label = profile_link_display_label((string) ($link['label'] ?? ''), $url);
            $contact[] = [
                'text' => $label,
                'url' => $url,
            ];
        }
    } elseif ($show['links'] && !$includeLinks && !empty($profile['links']) && is_array($profile['links'])) {
        foreach ($profile['links'] as $link) {
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            $label = profile_link_display_label((string) ($link['label'] ?? ''), $url, true);
            $contact[] = ['text' => $label];
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
            <a href="<?= App::e($item['url']) ?>" target="_blank" rel="noopener noreferrer"><?= App::e($item['text']) ?></a>
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

/**
 * Clean display label for profile links (LinkedIn / GitHub as short text).
 */
function profile_link_display_label(string $label, string $url, bool $atsPlain = false): string
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    if (str_contains($host, 'linkedin.com')) {
        return 'LinkedIn';
    }
    if (str_contains($host, 'github.com')) {
        return 'GitHub';
    }

    $label = trim($label);
    if ($label === '' || str_starts_with($label, 'http') || $label === $url) {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        if ($host !== '') {
            return $path !== '' ? $host . '/' . $path : $host;
        }

        return $url;
    }

    return $label;
}
