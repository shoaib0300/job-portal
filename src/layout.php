<?php

declare(strict_types=1);

function layout_accent_rgb(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return '91, 76, 219';
    }
    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
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

function layout_header(string $title, array $opts = []): void
{
    $theme = App::resolveTheme($opts['theme'] ?? null);
    $accent = App::resolveAccent($opts['accent'] ?? null);
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
    $density = App::resolveDensity();
    $sidebar = App::resolveSidebar();
    $nameSize = App::resolveNameSize($_GET['name_size'] ?? null);
    $spacing = App::resolveSectionSpacing($_GET['spacing'] ?? null);
    $navKey = App::currentNavKey();
    $profile = App::profile();
    $bsTheme = $uiMode === 'warm-dark' ? 'dark' : 'light';
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
        ['key' => 'companies', 'href' => '/companies', 'label' => 'Companies', 'icon' => 'jobs'],
        ['key' => 'applications', 'href' => '/applications', 'label' => 'Applications', 'icon' => 'apps'],
        ['key' => 'resume', 'href' => '/editor', 'label' => 'Resume', 'icon' => 'edit'],
        ['key' => 'cover', 'href' => '/cover', 'label' => 'Cover letter', 'icon' => 'letter'],
        ['key' => 'account', 'href' => '/settings', 'label' => 'Account', 'icon' => 'gear'],
    ];
    $chrome = $opts['chrome'] ?? $navKey;
    $pdfKind = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'cover') ? 'cover' : 'resume';
    $pdfLang = LibreTranslate::normalizeLang($opts['lang'] ?? ($_GET['lang'] ?? 'en'));
    $pdfTitle = PdfExport::printDocumentTitle($pdfKind, (string) ($profile['full_name'] ?? 'Document'), $pdfLang);
    $htmlTitle = ($pdfMode && $isDoc) ? $pdfTitle : ($title . ' · KaamMilo');
    ?>
<!DOCTYPE html>
<html lang="<?= App::e($pdfLang) ?>" data-bs-theme="<?= App::e($bsTheme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= App::e($htmlTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="<?= App::e(App::googleFontsHref($font)) ?>" rel="stylesheet">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260814h">
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=20260827j">
  <link rel="stylesheet" href="/assets/css/resume-themes.css?v=20260814h">
  <style>
    :root {
      --accent: <?= App::e($accent) ?>;
      --bs-primary: <?= App::e($accent) ?>;
      --bs-primary-rgb: <?= layout_accent_rgb($accent) ?>;
      --doc-font: <?= App::e($fontStack) ?>;
      --resume-name-scale: <?= App::e(App::nameSizeScale($nameSize)) ?>;
      --resume-section-gap: <?= App::e(App::sectionSpacingValue($spacing)) ?>;
    }
  </style>
</head>
<body class="<?= App::e($bodyClass) ?>"
      data-theme="<?= App::e($theme) ?>"
      data-font="<?= App::e($font) ?>"
      data-ui="<?= App::e($uiMode) ?>"
      data-density="<?= App::e($density) ?>"
      data-sidebar="<?= App::e($sidebar) ?>"
      data-name-size="<?= App::e($nameSize) ?>"
      data-spacing="<?= App::e($spacing) ?>"
      data-pdf-title="<?= App::e($pdfTitle) ?>">
<?php if ($hideNav): ?>
  <div class="site-shell site-shell-embed">
    <?php layout_flash($flash); ?>
<?php elseif ($isDoc): ?>
  <div class="site-shell site-shell-doc">
    <header class="doc-chrome no-print d-flex align-items-center gap-3 px-3 py-2 border-bottom bg-white">
      <a class="brand text-decoration-none fw-semibold" href="<?= App::e(Site::portalHomePath()) ?>">KaamMilo</a>
      <span class="doc-chrome-title text-secondary small me-auto"><?= App::e($title) ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="<?= basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'cover-letter.php' ? '/cover-design' : '/design' ?>">Style</a>
    </header>
    <?php layout_flash($flash); ?>
<?php else: ?>
  <div class="dash d-flex min-vh-100">
    <aside class="offcanvas-lg offcanvas-start dash-sidebar" tabindex="-1" id="dashSidebar" aria-label="Main">
      <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title">KaamMilo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#dashSidebar" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body d-flex flex-column p-3">
        <a class="dash-brand text-decoration-none d-none d-lg-flex align-items-center gap-2 mb-3" href="<?= App::e(Site::portalHomePath()) ?>">
          <span class="dash-mark">K</span>
          <span class="dash-brand-text">KaamMilo</span>
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
          <?php $authUser = Auth::user(); ?>
          <p class="dash-user mb-0 fw-semibold"><?= App::e((string) ($authUser['name'] ?? $profile['full_name'] ?? 'You')) ?></p>
          <p class="dash-user-meta small text-secondary mb-2"><?= App::e((string) ($authUser['email'] ?? $authUser['username'] ?? '')) ?></p>
          <a class="dash-logout small" href="/logout">Log out</a>
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
              Resume #<?= (int) $activeResume['id'] ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="/design">Style</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHref('resume', 'en')) ?>">PDF EN</a>
            <a class="btn btn-sm btn-primary" href="<?= App::e(PdfExport::downloadHref('resume', 'de')) ?>">PDF DE</a>
          <?php elseif ($chrome === 'cover' && $activeCover): ?>
            <a class="badge rounded-pill text-bg-light border text-decoration-none fw-semibold" href="/cover-edit" title="Edit cover letter">
              Letter #<?= (int) $activeCover['id'] ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="/cover-design">Style</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHref('cover', 'en')) ?>">PDF EN</a>
            <a class="btn btn-sm btn-primary" href="<?= App::e(PdfExport::downloadHref('cover', 'de')) ?>">PDF DE</a>
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
  <script src="/assets/js/app.js?v=20260824c"></script>
        <?php
    endif;
    ?>
</body>
</html>
<?php
}
