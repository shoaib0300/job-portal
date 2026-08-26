<?php

declare(strict_types=1);

function applypath_logo_mark(string $size = 'md'): string
{
    $cls = $size === 'sm' ? 'ap-mark ap-mark-sm' : 'ap-mark';
    return '<span class="' . $cls . '" aria-hidden="true">'
        . '<svg viewBox="0 0 32 32" width="28" height="28" fill="none" xmlns="http://www.w3.org/2000/svg">'
        . '<rect width="32" height="32" rx="8" fill="currentColor" opacity="0.12"/>'
        . '<path d="M8 22V10l8 6 8-6v12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle cx="16" cy="16" r="2.2" fill="currentColor"/>'
        . '</svg></span>';
}

function site_layout_header(string $title, array $opts = []): void
{
    $flash = empty($opts['hide_flash']) ? App::flash() : null;
    $bodyClass = trim('site-public ' . ($opts['body_class'] ?? ''));
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $loggedIn = Auth::id() > 0;
    $nav = [
        ['href' => '/', 'label' => 'Home', 'scripts' => ['index.php']],
        ['href' => '/features.php', 'label' => 'Features', 'scripts' => ['features.php']],
        ['href' => '/about.php', 'label' => 'About', 'scripts' => ['about.php']],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= App::e($title) ?> · ApplyPath</title>
  <meta name="description" content="ApplyPath — find German jobs, tailor resumes and cover letters, and track applications in one place.">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260826s">
  <link rel="stylesheet" href="/assets/css/site.css?v=20260826ap">
</head>
<body class="<?= App::e($bodyClass) ?>">
  <header class="site-header">
    <div class="site-header-inner">
      <a class="site-brand text-decoration-none" href="/">
        <?= applypath_logo_mark() ?>
        <span class="site-brand-text">ApplyPath</span>
      </a>
      <nav class="site-nav" aria-label="Primary">
        <?php foreach ($nav as $item): ?>
          <a class="site-nav-link<?= in_array($script, $item['scripts'], true) ? ' active' : '' ?>" href="<?= App::e($item['href']) ?>"><?= App::e($item['label']) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="site-header-actions">
        <?php if ($loggedIn): ?>
          <a class="btn btn-sm btn-primary" href="/">Dashboard</a>
          <a class="btn btn-sm btn-outline-secondary" href="/logout.php">Log out</a>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-secondary" href="/login.php">Sign in</a>
          <a class="btn btn-sm btn-primary" href="/register.php">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </header>
  <?php layout_flash($flash); ?>
  <div class="site-main">
    <?php
}

function site_layout_footer(): void
{
    ?>
  </div>
  <footer class="site-footer">
    <div class="site-footer-inner">
      <div class="site-footer-brand">
        <?= applypath_logo_mark('sm') ?>
        <span>ApplyPath</span>
      </div>
      <nav class="site-footer-nav" aria-label="Footer">
        <a href="/features.php">Features</a>
        <a href="/about.php">About</a>
        <a href="/login.php">Sign in</a>
      </nav>
      <p class="site-footer-copy mb-0">Job search, tailored documents, and application tracking for Germany.</p>
    </div>
  </footer>
  <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}
