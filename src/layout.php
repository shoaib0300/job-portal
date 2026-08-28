<?php

declare(strict_types=1);

require_once __DIR__ . '/brand.php';

function layout_accent_rgb(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return '13, 115, 119';
    }
    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
}

function layout_user_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/u', $name) ?: [];
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }

    return mb_strtoupper(mb_substr($name, 0, 2));
}

function layout_flash(?array $flash): void
{
    if (!$flash) {
        return;
    }
    $cls = ($flash['type'] ?? '') === 'error' ? 'alert-danger' : 'alert-success';
    ?>
  <div class="alert <?= $cls ?> alert-dismissible fade show" role="alert">
    <?= App::e((string) $flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
    <?php
}

/**
 * Download PDF (free) + Translate PDF (paid) controls.
 *
 * @param array<string, mixed> $extra Query params for pdf.php (version, id, theme, …)
 * @param array{export_options?: list<array<string, mixed>>} $opts
 */
function layout_pdf_buttons(string $doc, array $extra = [], array $opts = []): void
{
    $doc = $doc === 'cover' ? 'cover' : 'resume';
    $docAttr = App::e($doc);
    $exportJson = '';
    if (!empty($opts['export_options'])) {
        $exportJson = App::e(json_encode($opts['export_options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
    }
    $exportAttr = $exportJson !== '' ? ' data-export-options="' . $exportJson . '"' : '';
    ?>
    <a class="btn btn-sm btn-primary" href="<?= App::e(PdfExport::downloadHrefOriginal($doc, $extra)) ?>" data-download-pdf data-doc="<?= $docAttr ?>"<?= $exportAttr ?>>Download PDF</a>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-translate-pdf data-doc="<?= $docAttr ?>"<?= $exportAttr ?>>Translate PDF…</button>
    <?php
}

function layout_header(string $title, array $opts = []): void
{
    require_once __DIR__ . '/onboarding.php';
    $theme = App::resolveTheme($opts['theme'] ?? null);
    $docAccent = App::resolveAccent($opts['accent'] ?? null);
    $uiAccent = App::uiAccent();
    $font = App::resolveFont($opts['font'] ?? null);
    $fontStack = App::fontStack($font);
    $pdfMode = array_key_exists('pdf_mode', $opts)
        ? (bool) $opts['pdf_mode']
        : ((App::setting('pdf_mode', '0') ?: '0') === '1');
    $bodyClass = trim(($opts['body_class'] ?? '') . ($pdfMode ? ' pdf-mode' : ''));
    $flash = empty($opts['hide_flash']) ? App::flash() : null;
    $hideNav = !empty($opts['hide_nav']);
    $isDoc = str_contains(' ' . $bodyClass . ' ', ' page-doc ');
    $uiMode = App::resolveUiMode();
    $dashboardPalette = App::resolveDashboardPalette();
    $density = App::resolveDensity();
    $sidebar = App::resolveSidebar();
    $nameSize = App::resolveNameSize($_GET['name_size'] ?? null);
    $fontSize = App::resolveFontSize($_GET['font_size'] ?? null);
    $spacing = App::resolveSectionSpacing($_GET['spacing'] ?? null);
    $navKey = App::currentNavKey();
    $profile = App::profile();
    $bsTheme = App::dashboardPaletteIsDark($dashboardPalette) ? 'dark' : 'light';
    $activeResume = null;
    $activeCover = null;
    try {
        $activeResume = Versions::activeResumeVersion();
        $activeCover = App::activeCoverLetter();
    } catch (Throwable $e) {
        // Schema not ready yet
    }

    $nav = [
        ['key' => 'dashboard', 'href' => Site::portalHomePath(), 'label' => 'Home', 'icon' => 'home'],
        ['key' => 'apply', 'href' => '/tailor', 'label' => 'New job', 'icon' => 'apply'],
        ['key' => 'jobs', 'href' => '/jobs', 'label' => 'Jobs', 'icon' => 'jobs'],
        ['key' => 'companies', 'href' => '/companies', 'label' => 'Companies', 'icon' => 'company'],
        ['key' => 'applications', 'href' => '/applications', 'label' => 'Applications', 'icon' => 'apps'],
        ['key' => 'resume', 'href' => '/editor', 'label' => 'Resume', 'icon' => 'edit'],
        ['key' => 'cover', 'href' => '/cover', 'label' => 'Cover letter', 'icon' => 'letter'],
        ['key' => 'guide', 'href' => '/help', 'label' => 'How to use', 'icon' => 'spark'],
    ];
    $chrome = $opts['chrome'] ?? $navKey;
    $documentLang = App::resolveDocumentLang();
    $translateTargetLang = App::resolveTranslateTargetLang();
    $pdfKind = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'cover') ? 'cover' : 'resume';
    $pdfLang = LibreTranslate::normalizeLang($opts['lang'] ?? ($_GET['lang'] ?? 'en'));
    $pdfTitle = PdfExport::printDocumentTitle($pdfKind, (string) ($profile['full_name'] ?? 'Document'), $pdfLang);
    $htmlTitle = ($pdfMode && $isDoc) ? $pdfTitle : ($title . ' · ' . kaamfit_brand_name());
    $paletteAccent = App::dashboardPalettes()[$dashboardPalette]['tokens']['--kf-accent'] ?? App::uiAccent();
    if (!str_starts_with($paletteAccent, '#')) {
        $paletteAccent = App::uiAccent();
    }
    ?>
<!DOCTYPE html>
<html lang="<?= App::e($pdfLang) ?>" data-bs-theme="<?= App::e($bsTheme) ?>" data-document-lang="<?= App::e($documentLang) ?>" data-translate-target-lang="<?= App::e($translateTargetLang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= App::e($htmlTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="<?= App::e(kaamfit_portal_fonts_href()) ?>" rel="stylesheet">
  <?php if ($isDoc): ?>
  <link href="<?= App::e(App::googleFontsHref($font)) ?>" rel="stylesheet">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260828l">
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=20260828v">
  <link rel="stylesheet" href="/assets/css/onboarding.css?v=20260828q">
  <link rel="stylesheet" href="/assets/css/resume-themes.css?v=20260828b">
  <style>
    :root {
      <?= kaamfit_palette_css_vars($dashboardPalette) ?>

      --accent: var(--kf-accent);
      --bs-primary: var(--kf-accent);
      --bs-primary-rgb: <?= layout_accent_rgb($paletteAccent) ?>;
      --doc-accent: <?= App::e($docAccent) ?>;
      --doc-font: <?= App::e($fontStack) ?>;
      --resume-name-scale: <?= App::e(App::nameSizeScale($nameSize)) ?>;
      --resume-section-gap: <?= App::e(App::sectionSpacingValue($spacing)) ?>;
      --doc-font-scale: <?= App::e(App::fontSizeScale($fontSize)) ?>;
    }
  </style>
</head>
<body class="<?= App::e($bodyClass) ?>"
      data-theme="<?= App::e($theme) ?>"
      data-font="<?= App::e($font) ?>"
      data-ui="<?= App::e($uiMode) ?>"
      data-palette="<?= App::e($dashboardPalette) ?>"
      data-density="<?= App::e($density) ?>"
      data-sidebar="<?= App::e($sidebar) ?>"
      data-name-size="<?= App::e($nameSize) ?>"
      data-font-size="<?= App::e($fontSize) ?>"
      data-spacing="<?= App::e($spacing) ?>"
      data-pdf-title="<?= App::e($pdfTitle) ?>">
<?php if ($hideNav): ?>
  <div class="site-shell site-shell-embed">
    <?php layout_flash($flash); ?>
<?php elseif ($isDoc): ?>
  <div class="site-shell site-shell-doc">
    <header class="doc-chrome no-print d-flex align-items-center gap-3 px-3 py-2 border-bottom bg-white">
      <a class="brand text-decoration-none fw-semibold" href="<?= App::e(Site::portalHomePath()) ?>"><?= App::e(kaamfit_brand_name()) ?></a>
      <span class="doc-chrome-title text-secondary small me-auto"><?= App::e($title) ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="<?= basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'cover-letter.php' ? '/cover-design' : '/design' ?>">Style</a>
    </header>
    <?php layout_flash($flash); ?>
<?php else: ?>
  <div class="dash d-flex min-vh-100">
    <aside class="offcanvas-lg offcanvas-start dash-sidebar" tabindex="-1" id="dashSidebar" aria-label="Main">
      <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title"><?= App::e(kaamfit_brand_name()) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#dashSidebar" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body d-flex flex-column p-3">
        <a class="dash-brand text-decoration-none d-none d-lg-flex align-items-center gap-2 mb-3" href="<?= App::e(Site::portalHomePath()) ?>">
          <?= kaamfit_logo_mark('sm') ?>
          <span class="dash-brand-text"><?= App::e(kaamfit_brand_name()) ?></span>
        </a>
        <nav class="nav flex-column dash-nav gap-1 flex-grow-1">
          <?php foreach ($nav as $item): ?>
            <a class="nav-link dash-nav-link d-flex align-items-center gap-2<?= $navKey === $item['key'] ? ' active' : '' ?>"
               href="<?= App::e($item['href']) ?>"
               data-nav="<?= App::e($item['key']) ?>">
              <span class="dash-ico" aria-hidden="true" data-ico="<?= App::e($item['icon']) ?>"></span>
              <span class="dash-nav-label"><?= App::e($item['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
        <div class="dash-sidebar-foot pt-3 mt-auto">
          <?php
          $authUser = Auth::user();
          $userName = (string) ($authUser['name'] ?? $profile['full_name'] ?? 'You');
          $userEmail = (string) ($authUser['email'] ?? $authUser['username'] ?? '');
          $userInitials = layout_user_initials($userName);
          $accountActive = $navKey === 'account';
          ?>
          <div class="dash-user-card<?= $accountActive ? ' is-active' : '' ?>">
            <a class="dash-user-link" href="/settings" title="Account settings"<?= $accountActive ? ' aria-current="page"' : '' ?>>
              <span class="dash-user-avatar" aria-hidden="true"><?= App::e($userInitials) ?></span>
              <span class="dash-user-text">
                <span class="dash-user-name"><?= App::e($userName) ?></span>
                <?php if ($userEmail !== ''): ?>
                  <span class="dash-user-email"><?= App::e($userEmail) ?></span>
                <?php endif; ?>
              </span>
            </a>
            <a class="dash-user-logout" href="/logout" title="Log out">
              <svg viewBox="0 0 16 16" width="18" height="18" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M6 14H3.5A1.5 1.5 0 0 1 2 12.5v-9A1.5 1.5 0 0 1 3.5 2H6v1H3.5a.5.5 0 0 0-.5.5v9a.5.5 0 0 0 .5.5H6zm4.854-4.146a.5.5 0 0 0 0-.708L9.207 7.5H14.5a.5.5 0 0 0 0-1H9.207l1.647-1.646a.5.5 0 1 0-.708-.708l-2.5 2.5a.5.5 0 0 0 0 .708l2.5 2.5a.5.5 0 0 0 .708 0"/>
              </svg>
              <span class="dash-user-logout-label">Log out</span>
            </a>
          </div>
        </div>
      </div>
    </aside>
    <div class="dash-main flex-grow-1 min-w-0">
      <header class="dash-topbar d-flex align-items-center gap-2 px-3 py-2">
        <button type="button" class="btn btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#dashSidebar" aria-controls="dashSidebar" aria-label="Open menu">
          <span class="dash-menu-bars" aria-hidden="true"></span>
        </button>
        <div class="dash-topbar-title">
          <h1 class="h4 mb-0"><?= App::e($title) ?></h1>
        </div>
        <div class="dash-topbar-meta ms-auto d-flex flex-wrap align-items-center gap-2">
          <?php if ($chrome === 'resume' && $activeResume): ?>
            <a class="badge rounded-pill text-bg-light border text-decoration-none fw-semibold" href="/resume-edit" title="Edit resume">
              <?= App::e(Versions::resumeDisplayLabel($activeResume)) ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="/design">Style</a>
            <?php layout_pdf_buttons('resume'); ?>
          <?php elseif ($chrome === 'cover' && $activeCover): ?>
            <a class="badge rounded-pill text-bg-light border text-decoration-none fw-semibold" href="/cover-edit" title="Edit cover letter">
              <?= App::e(Versions::coverDisplayLabel($activeCover)) ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="/cover-design">Style</a>
            <?php layout_pdf_buttons('cover'); ?>
          <?php endif; ?>
        </div>
      </header>
      <div class="dash-content">
        <?php layout_flash($flash); ?>
<?php endif; ?>
<?php
}

function layout_footer(bool $withJs = true): void
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $embed = isset($_GET['embed']) && (string) $_GET['embed'] === '1';
    $isDocPage = in_array($script, ['resume.php', 'cover-letter.php'], true);
    $isAuthPage = in_array($script, ['login.php', 'register.php'], true);
    if ($embed || $isAuthPage):
        ?>
  </div>
        <?php
    elseif ($isDocPage):
        ?>
  </div>
        <?php
    else:
        ?>
      </div>
    </div>
  </div>
        <?php
    endif;
    if ($withJs):
        ?>
  <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/app.js?v=20260828m"></script>
  <script>window.kmTranslateLangs=<?= json_encode(TranslateLanguages::optionsForJs(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]' ?>;</script>
        <?php
    endif;
    ?>
</body>
</html>
<?php
}
