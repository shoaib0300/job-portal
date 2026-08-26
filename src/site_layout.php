<?php

declare(strict_types=1);

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

/** @deprecated Use kaammilo_logo_mark */
function applypath_logo_mark(string $size = 'md'): string
{
    return kaammilo_logo_mark($size);
}

/** Simple cartoon-style SVG icons for marketing pages. */
function kaammilo_icon_paths(string $name): string
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
        'home' => '<path d="M6 14L16 5l10 9" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 13v12h5v-7h4v7h5V13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
        'gear' => '<circle cx="16" cy="16" r="3.2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 6.5v2.2M16 23.3v2.2M6.5 16h2.2M23.3 16h2.2M9.2 9.2l1.6 1.6M21.2 21.2l1.6 1.6M9.2 22.8l1.6-1.6M21.2 10.8l1.6-1.6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    ];
    return $icons[$name] ?? $icons['spark'];
}

function kaammilo_icon(string $name): string
{
    $paths = kaammilo_icon_paths($name);
    return '<span class="km-ico" aria-hidden="true"><svg viewBox="0 0 32 32" width="40" height="40" fill="none" xmlns="http://www.w3.org/2000/svg">' . $paths . '</svg></span>';
}

/** Compact icon for portal sidebar / chrome (same artwork as marketing). */
function kaammilo_nav_icon(string $name): string
{
    $paths = kaammilo_icon_paths($name);
    return '<span class="km-nav-ico" aria-hidden="true"><svg viewBox="0 0 32 32" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg">' . $paths . '</svg></span>';
}

/** @deprecated Use kaammilo_icon */
function applypath_icon(string $name): string
{
    return kaammilo_icon($name);
}

function site_layout_header(string $title, array $opts = []): void
{
    $flash = empty($opts['hide_flash']) ? App::flash() : null;
    $bodyClass = trim('site-public ' . ($opts['body_class'] ?? ''));
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $loggedIn = Auth::id() > 0;
    $portalHref = App::portalHomePath();
    $nav = [
        ['href' => '/', 'label' => 'Home', 'scripts' => ['index.php']],
        ['href' => '/guide.php', 'label' => 'How to use', 'scripts' => ['guide.php']],
        ['href' => '/features.php', 'label' => 'Features', 'scripts' => ['features.php']],
        ['href' => '/about.php', 'label' => 'About', 'scripts' => ['about.php']],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= App::e($title) ?> · KaamMilo</title>
  <meta name="description" content="KaamMilo — test portal for German job search, tailored resumes, cover letters, and application tracking.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260826s">
  <link rel="stylesheet" href="/assets/css/site.css?v=20260826km2">
</head>
<body class="<?= App::e($bodyClass) ?>">
  <div class="site-test-banner" role="status">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
      <span><?= kaammilo_icon('lab') ?> <strong>Testing module</strong> — KaamMilo is a work-in-progress demo. Features may change; data is for testing.</span>
      <a class="small text-decoration-none" href="/about.php#testing">Learn more</a>
    </div>
  </div>
  <nav class="navbar navbar-expand-lg site-navbar sticky-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="/">
        <?= kaammilo_logo_mark() ?>
        <span>KaamMilo</span>
        <span class="badge site-badge-test">BETA</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav"
              aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="siteNav">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php foreach ($nav as $item): ?>
            <li class="nav-item">
              <a class="nav-link<?= in_array($script, $item['scripts'], true) ? ' active' : '' ?>"
                 href="<?= App::e($item['href']) ?>"
                 <?= in_array($script, $item['scripts'], true) ? 'aria-current="page"' : '' ?>>
                <?= App::e($item['label']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="d-flex flex-column flex-lg-row gap-2 align-items-stretch align-items-lg-center pt-2 pt-lg-0 border-top border-lg-0 mt-2 mt-lg-0">
          <?php if ($loggedIn): ?>
            <a class="btn btn-primary" href="<?= App::e($portalHref) ?>">Open portal</a>
            <a class="btn btn-outline-secondary" href="/logout.php">Log out</a>
          <?php else: ?>
            <a class="btn btn-outline-secondary" href="/login.php">Sign in</a>
            <a class="btn btn-primary" href="/register.php">Register</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
  <?php
  if ($flash):
      $cls = ($flash['type'] ?? '') === 'error' ? 'alert-danger' : 'alert-success';
      ?>
  <div class="container mt-3">
    <div class="alert <?= $cls ?> alert-dismissible fade show" role="alert">
      <?= App::e((string) $flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
  <?php endif; ?>
  <div class="site-main">
    <?php
}

function site_layout_footer(): void
{
    ?>
  </div>
  <footer class="site-footer mt-auto">
    <div class="container py-4">
      <div class="row g-3 align-items-start">
        <div class="col-lg-5">
          <div class="d-flex align-items-center gap-2 fw-semibold mb-2">
            <?= kaammilo_logo_mark('sm') ?>
            <span>KaamMilo</span>
            <span class="badge site-badge-test">BETA</span>
          </div>
          <p class="text-secondary small mb-0">Testing portal for German job search, tailored resumes &amp; cover letters, and application tracking.</p>
        </div>
        <div class="col-lg-7">
          <ul class="nav flex-column flex-sm-row flex-wrap gap-sm-3">
            <li class="nav-item"><a class="nav-link px-0" href="/guide.php">How to use</a></li>
            <li class="nav-item"><a class="nav-link px-0" href="/features.php">Features</a></li>
            <li class="nav-item"><a class="nav-link px-0" href="/about.php">About</a></li>
            <li class="nav-item"><a class="nav-link px-0" href="/login.php">Sign in</a></li>
          </ul>
        </div>
      </div>
    </div>
  </footer>
  <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
  <script>
    (function () {
      var nodes = document.querySelectorAll('.site-reveal');
      if (!('IntersectionObserver' in window) || !nodes.length) {
        nodes.forEach(function (n) { n.classList.add('is-in'); });
        return;
      }
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            e.target.classList.add('is-in');
            io.unobserve(e.target);
          }
        });
      }, { threshold: 0.12 });
      nodes.forEach(function (n) { io.observe(n); });
    })();
  </script>
</body>
</html>
    <?php
}
