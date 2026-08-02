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
  <link rel="stylesheet" href="/assets/css/app.css?v=20260802g">
  <link rel="stylesheet" href="/assets/css/resume-themes.css?v=20260802g">
  <style>:root { --accent: <?= App::e($accent) ?>; --doc-font: <?= App::e($fontStack) ?>; }</style>
</head>
<body class="<?= App::e($bodyClass) ?>" data-theme="<?= App::e($theme) ?>" data-font="<?= App::e($font) ?>">
  <div class="site-shell<?= $hideNav ? ' site-shell-embed' : '' ?>">
    <?php if (!$hideNav): ?>
    <header class="site-nav">
      <a class="brand" href="/">MNK</a>
      <nav>
        <a href="/">Home</a>
        <a href="/design.php?doc=resume">Resume</a>
        <a href="/design.php?doc=cover">Cover letter</a>
        <a href="/design.php">Design</a>
        <a href="/editor.php">Editor</a>
        <a href="/applications.php">Applications</a>
        <a href="/history.php">History</a>
      </nav>
    </header>
    <?php endif; ?>
    <?php if ($flash): ?>
      <div class="flash flash-<?= App::e($flash['type']) ?>"><?= App::e($flash['message']) ?></div>
    <?php endif; ?>
<?php
}

function layout_footer(bool $withJs = true): void
{
    ?>
  </div>
  <?php if ($withJs): ?>
  <script src="/assets/js/app.js?v=20260802b"></script>
  <?php endif; ?>
</body>
</html>
<?php
}
