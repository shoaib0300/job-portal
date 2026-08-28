<?php

declare(strict_types=1);

/** @return array<string, string> */
function kaammilo_brand_tokens(): array
{
    return [
        '--km-ink' => '#14213d',
        '--km-muted' => '#5c6578',
        '--km-accent' => '#0d7377',
        '--km-accent-2' => '#e07a3d',
        '--km-accent-soft' => '#e6f4f4',
        '--km-sand' => '#f6f3ee',
        '--km-line' => '#e2ddd4',
        '--km-display' => '"Nunito", "Segoe UI", sans-serif',
        '--km-body' => '"Source Sans 3", "Segoe UI", sans-serif',
    ];
}

function kaammilo_brand_css_vars(): string
{
    $lines = [];
    foreach (kaammilo_brand_tokens() as $key => $value) {
        $lines[] = $key . ': ' . $value . ';';
    }
    return implode("\n      ", $lines);
}

/**
 * @return array<string, array{label: string, is_dark: bool, tokens: array<string, string>}>
 */
function kaammilo_dashboard_palettes(): array
{
    return [
        'light' => [
            'label' => 'Light',
            'is_dark' => false,
            'tokens' => kaammilo_brand_tokens(),
        ],
        'dark' => [
            'label' => 'Dark',
            'is_dark' => true,
            'tokens' => [
                '--km-ink' => '#f0f4f8',
                '--km-muted' => '#9aa8b8',
                '--km-accent' => '#2ec4c7',
                '--km-accent-2' => '#e07a3d',
                '--km-accent-soft' => 'rgba(46, 196, 199, 0.15)',
                '--km-sand' => '#121820',
                '--km-line' => 'rgba(240, 244, 248, 0.12)',
                '--km-display' => '"Nunito", "Segoe UI", sans-serif',
                '--km-body' => '"Source Sans 3", "Segoe UI", sans-serif',
            ],
        ],
        'rose' => [
            'label' => 'Rose',
            'is_dark' => false,
            'tokens' => [
                '--km-ink' => '#3d2030',
                '--km-muted' => '#7a5a68',
                '--km-accent' => '#c45c7a',
                '--km-accent-2' => '#e07a3d',
                '--km-accent-soft' => '#fce8ee',
                '--km-sand' => '#fdf5f7',
                '--km-line' => '#edd8df',
                '--km-display' => '"Nunito", "Segoe UI", sans-serif',
                '--km-body' => '"Source Sans 3", "Segoe UI", sans-serif',
            ],
        ],
        'ocean' => [
            'label' => 'Ocean',
            'is_dark' => false,
            'tokens' => [
                '--km-ink' => '#0f2438',
                '--km-muted' => '#4a6278',
                '--km-accent' => '#1a6b8a',
                '--km-accent-2' => '#e07a3d',
                '--km-accent-soft' => '#e3f0f7',
                '--km-sand' => '#eef4f8',
                '--km-line' => '#d4e2ec',
                '--km-display' => '"Nunito", "Segoe UI", sans-serif',
                '--km-body' => '"Source Sans 3", "Segoe UI", sans-serif',
            ],
        ],
    ];
}

function kaammilo_palette_css_vars(string $paletteId): string
{
    $palettes = kaammilo_dashboard_palettes();
    $tokens = $palettes[$paletteId]['tokens'] ?? kaammilo_brand_tokens();
    $lines = [];
    foreach ($tokens as $key => $value) {
        $lines[] = $key . ': ' . $value . ';';
    }
    return implode("\n      ", $lines);
}

function kaammilo_palette_is_dark(string $paletteId): bool
{
    $palettes = kaammilo_dashboard_palettes();
    return (bool) ($palettes[$paletteId]['is_dark'] ?? false);
}

function kaammilo_portal_fonts_href(): string
{
    return 'https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800&family=Source+Sans+3:wght@400;500;600&display=swap';
}

function kaammilo_logo_mark(string $size = 'md'): string
{
    $cls = $size === 'sm' ? 'km-mark km-mark-sm' : 'km-mark';
    $uid = 'km' . substr(md5($size . microtime()), 0, 6);
    return '<span class="' . $cls . '" aria-hidden="true">'
        . '<svg viewBox="0 0 40 40" width="32" height="32" fill="none" xmlns="http://www.w3.org/2000/svg">'
        . '<defs><linearGradient id="' . $uid . '" x1="6" y1="4" x2="34" y2="36" gradientUnits="userSpaceOnUse">'
        . '<stop stop-color="#14a3a8"/><stop offset="1" stop-color="#0a5f62"/>'
        . '</linearGradient></defs>'
        . '<rect x="1" y="1" width="38" height="38" rx="12" fill="url(#' . $uid . ')"/>'
        . '<text x="20" y="26" text-anchor="middle" font-family="Nunito,Segoe UI,sans-serif" font-size="15" font-weight="800" fill="#fff">km</text>'
        . '<circle cx="31.5" cy="9.5" r="3.4" fill="#e07a3d"/>'
        . '</svg></span>';
}

/** Simple cartoon-style SVG icons for marketing and dashboard. */
function kaammilo_icon(string $name, string $size = 'md'): string
{
    $icons = [
        'search' => '<circle cx="14" cy="14" r="8" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M20 20l6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="14" cy="14" r="3" fill="currentColor" opacity="0.25"/>',
        'doc' => '<rect x="8" y="4" width="16" height="24" rx="3" fill="currentColor" opacity="0.15"/><rect x="8" y="4" width="16" height="24" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 12h8M12 17h8M12 22h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'letter' => '<rect x="4" y="8" width="24" height="16" rx="3" fill="currentColor" opacity="0.15"/><rect x="4" y="8" width="24" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 10l11 8 11-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'track' => '<rect x="5" y="6" width="22" height="20" rx="3" fill="currentColor" opacity="0.12"/><path d="M10 14l4 4 8-9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
        'pdf' => '<path d="M10 4h9l7 7v15a2 2 0 01-2 2H10a2 2 0 01-2-2V6a2 2 0 012-2z" fill="currentColor" opacity="0.15"/><path d="M10 4h9l7 7v15a2 2 0 01-2 2H10a2 2 0 01-2-2V6a2 2 0 012-2z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M19 4v7h7" fill="none" stroke="currentColor" stroke-width="2"/><text x="11" y="22" font-size="7" font-weight="700" fill="currentColor">PDF</text>',
        'company' => '<rect x="6" y="10" width="20" height="16" rx="2" fill="currentColor" opacity="0.15"/><path d="M6 26V10h20v16M10 14h2M14 14h2M18 14h2M10 18h2M14 18h2M18 18h2M10 22h2M14 22h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'rocket' => '<path d="M16 4c4 4 6 10 6 14-4 0-10-2-14-6 4-4 10-6 14-6z" fill="currentColor" opacity="0.18"/><path d="M16 4c4 4 6 10 6 14-4 0-10-2-14-6 4-4 10-6 14-6z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="18" cy="10" r="1.8" fill="currentColor"/><path d="M8 18l-3 7 7-3M10 22l-4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'lab' => '<path d="M12 4h8v8l5 12H7l5-12V4z" fill="currentColor" opacity="0.15"/><path d="M12 4h8v8l5 12H7l5-12V4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M10 18h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'user' => '<circle cx="16" cy="11" r="5" fill="currentColor" opacity="0.18"/><circle cx="16" cy="11" r="5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M6 27c2-5 6-7 10-7s8 2 10 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'spark' => '<path d="M16 3l2.2 8.2L26 16l-7.8 2.2L16 29l-2.2-10.8L6 16l7.8-4.8L16 3z" fill="currentColor" opacity="0.2"/><path d="M16 3l2.2 8.2L26 16l-7.8 2.2L16 29l-2.2-10.8L6 16l7.8-4.8L16 3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
        'applied' => '<circle cx="16" cy="16" r="12" fill="currentColor" opacity="0.12"/><path d="M11 16l3 3 7-7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
        'interview' => '<rect x="8" y="10" width="16" height="14" rx="2" fill="currentColor" opacity="0.12"/><path d="M8 14h16M12 8v4M20 8v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'offer' => '<path d="M8 14l4-6 4 4 8-10" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="16" r="12" fill="currentColor" opacity="0.1"/>',
        'rejected' => '<circle cx="16" cy="16" r="12" fill="currentColor" opacity="0.1"/><path d="M11 11l10 10M21 11L11 21" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
    ];
    $paths = $icons[$name] ?? $icons['spark'];
    $px = $size === 'sm' ? 28 : ($size === 'lg' ? 48 : 40);
    $cls = 'km-ico' . ($size !== 'md' ? ' km-ico-' . $size : '');
    return '<span class="' . $cls . '" aria-hidden="true"><svg viewBox="0 0 32 32" width="' . $px . '" height="' . $px . '" fill="none" xmlns="http://www.w3.org/2000/svg">' . $paths . '</svg></span>';
}

/** @deprecated Use kaammilo_logo_mark */
function applypath_logo_mark(string $size = 'md'): string
{
    return kaammilo_logo_mark($size);
}

/** @deprecated Use kaammilo_icon */
function applypath_icon(string $name): string
{
    return kaammilo_icon($name);
}
