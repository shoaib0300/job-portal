<?php

declare(strict_types=1);

/**
 * Shared query overrides for live preview / design studio.
 *
 * @return array{theme: string, accent: string, font: string, embed: bool, pdfMode: bool, ats: bool, company: string, versionId: int, coverId: int, lang: string, translate: bool, target: string}
 */
function doc_view_options(): array
{
    $ats = isset($_GET['ats']) && (string) $_GET['ats'] === '1';
    $theme = App::resolveTheme($_GET['theme'] ?? null);
    if ($ats) {
        $theme = 'ivory';
    }
    $accent = App::resolveAccent($_GET['accent'] ?? null);
    $font = App::resolveFont($_GET['font'] ?? null);
    $embed = isset($_GET['embed']) && (string) $_GET['embed'] === '1';
    $pdfMode = (App::setting('pdf_mode', '0') ?: '0') === '1'
        || (isset($_GET['pdf']) && (string) $_GET['pdf'] === '1');
    $translate = isset($_GET['translate']) && (string) $_GET['translate'] === '1';
    $target = $translate ? TranslateLanguages::normalize((string) ($_GET['target'] ?? '')) : '';
    $documentLang = App::resolveDocumentLang();
    $lang = $translate && $target !== '' ? $target : $documentLang;

    return [
        'theme' => $theme,
        'accent' => $accent,
        'font' => $font,
        'embed' => $embed,
        'pdfMode' => $pdfMode,
        'ats' => $ats,
        'company' => App::setting('active_company', '') ?: '',
        'versionId' => isset($_GET['version']) ? (int) $_GET['version'] : 0,
        'coverId' => isset($_GET['id']) ? (int) $_GET['id'] : 0,
        'lang' => $lang,
        'translate' => $translate,
        'target' => $target,
    ];
}
