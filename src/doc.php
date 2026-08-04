<?php

declare(strict_types=1);

/**
 * Shared query overrides for live preview / design studio.
 *
 * @return array{theme: string, accent: string, font: string, embed: bool, pdfMode: bool, company: string, versionId: int, coverId: int}
 */
function doc_view_options(): array
{
    $theme = App::resolveTheme($_GET['theme'] ?? null);
    $accent = App::resolveAccent($_GET['accent'] ?? null);
    $font = App::resolveFont($_GET['font'] ?? null);
    $embed = isset($_GET['embed']) && (string) $_GET['embed'] === '1';
    $pdfMode = (App::setting('pdf_mode', '0') ?: '0') === '1'
        || (isset($_GET['pdf']) && (string) $_GET['pdf'] === '1');

    return [
        'theme' => $theme,
        'accent' => $accent,
        'font' => $font,
        'embed' => $embed,
        'pdfMode' => $pdfMode,
        'company' => App::setting('active_company', '') ?: '',
        'versionId' => isset($_GET['version']) ? (int) $_GET['version'] : 0,
        'coverId' => isset($_GET['id']) ? (int) $_GET['id'] : 0,
    ];
}
