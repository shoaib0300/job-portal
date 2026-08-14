<?php

declare(strict_types=1);

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
    $activeResume = null;
    $activeCover = null;
    try {
        $activeResume = Versions::activeResumeVersion();
        $activeCover = App::activeCoverLetter();
    } catch (Throwable $e) {
        // Schema not ready yet
    }

    $nav = [
        ['key' => 'dashboard', 'href' => '/', 'label' => 'Dashboard', 'icon' => 'home'],
        ['key' => 'apply', 'href' => '/tailor.php', 'label' => 'Apply', 'icon' => 'apply'],
        ['key' => 'applications', 'href' => '/applications.php', 'label' => 'Applications', 'icon' => 'apps'],
        ['key' => 'documents', 'href' => '/documents.php', 'label' => 'Documents', 'icon' => 'docs'],
        ['key' => 'editor', 'href' => '/editor.php', 'label' => 'Editor', 'icon' => 'edit'],
        ['key' => 'design', 'href' => '/design.php', 'label' => 'Design', 'icon' => 'design'],
        ['key' => 'history', 'href' => '/history.php', 'label' => 'History', 'icon' => 'history'],
        ['key' => 'settings', 'href' => '/settings.php', 'label' => 'Settings', 'icon' => 'gear'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= App::e($title) ?> · MNK</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="<?= App::e(App::googleFontsHref($font)) ?>" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260814a">
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=20260814a">
  <link rel="stylesheet" href="/assets/css/resume-themes.css?v=20260814a">
  <style>
    :root {
      --accent: <?= App::e($accent) ?>;
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
      data-spacing="<?= App::e($spacing) ?>">
<?php if ($hideNav): ?>
  <div class="site-shell site-shell-embed">
    <?php if ($flash): ?>
      <div class="flash flash-<?= App::e($flash['type']) ?>"><?= App::e($flash['message']) ?></div>
    <?php endif; ?>
<?php elseif ($isDoc): ?>
  <div class="site-shell site-shell-doc">
    <header class="doc-chrome no-print">
      <a class="brand" href="/">MNK</a>
      <span class="doc-chrome-title"><?= App::e($title) ?></span>
      <a class="btn btn-small" href="/design.php">Design</a>
    </header>
    <?php if ($flash): ?>
      <div class="flash flash-<?= App::e($flash['type']) ?>"><?= App::e($flash['message']) ?></div>
    <?php endif; ?>
<?php else: ?>
  <div class="dash">
    <aside class="dash-sidebar" data-sidebar-panel>
      <a class="dash-brand" href="/">
        <span class="dash-mark">M</span>
        <span class="dash-brand-text">MNK</span>
      </a>
      <nav class="dash-nav" aria-label="Main">
        <?php foreach ($nav as $item): ?>
          <a class="dash-nav-link<?= $navKey === $item['key'] ? ' is-active' : '' ?>"
             href="<?= App::e($item['href']) ?>"
             data-nav="<?= App::e($item['key']) ?>">
            <span class="dash-ico" aria-hidden="true" data-ico="<?= App::e($item['icon']) ?>"></span>
            <span class="dash-nav-label"><?= App::e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="dash-sidebar-foot">
        <?php $authUser = Auth::user(); ?>
        <p class="dash-user"><?= App::e((string) ($authUser['name'] ?? $profile['full_name'] ?? 'You')) ?></p>
        <p class="dash-user-meta"><?= App::e((string) ($authUser['email'] ?? $authUser['username'] ?? '')) ?></p>
        <a class="dash-logout" href="/logout.php">Log out</a>
      </div>
    </aside>
    <div class="dash-main">
      <header class="dash-topbar">
        <button type="button" class="dash-menu-btn" data-sidebar-toggle aria-label="Toggle sidebar">
          <span></span><span></span><span></span>
        </button>
        <div class="dash-topbar-title">
          <h1><?= App::e($title) ?></h1>
        </div>
        <div class="dash-topbar-meta">
          <?php if ($activeResume): ?>
            <a class="top-pill" href="/editor.php#versions" title="Active resume">
              CV <span class="doc-id">#<?= (int) $activeResume['id'] ?></span>
            </a>
          <?php endif; ?>
          <?php if ($activeCover): ?>
            <a class="top-pill" href="/editor.php#cover" title="Active cover letter">
              Letter <span class="doc-id">#<?= (int) $activeCover['id'] ?></span>
            </a>
          <?php endif; ?>
          <a class="btn btn-small" href="/resume.php" target="_blank" rel="noopener">Resume</a>
          <a class="btn btn-small btn-primary" href="/pdf.php?doc=resume">PDF</a>
        </div>
      </header>
      <div class="dash-content">
        <?php if ($flash): ?>
          <div class="flash flash-<?= App::e($flash['type']) ?>"><?= App::e($flash['message']) ?></div>
        <?php endif; ?>
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
  <script src="/assets/js/app.js?v=20260814a"></script>
        <?php
    endif;
    ?>
</body>
</html>
<?php
}
