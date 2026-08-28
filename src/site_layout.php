<?php

declare(strict_types=1);

require_once __DIR__ . '/brand.php';

function site_layout_header(string $title, array $opts = []): void
{
    $flash = empty($opts['hide_flash']) ? App::flash() : null;
    $bodyClass = trim('site-public ' . ($opts['body_class'] ?? ''));
    $extraStylesheets = $opts['extra_stylesheets'] ?? [];
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $loggedIn = Auth::id() > 0;
    $nav = [
        ['href' => '/', 'label' => 'Home', 'scripts' => ['index.php']],
        ['href' => '/demo', 'label' => 'Try demo', 'scripts' => ['demo.php']],
        ['href' => '/guide', 'label' => 'How to use', 'scripts' => ['guide.php']],
        ['href' => '/features', 'label' => 'Features', 'scripts' => ['features.php']],
        ['href' => '/about', 'label' => 'About', 'scripts' => ['about.php']],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= App::e($title) ?> · <?= App::e(kaamfit_brand_name()) ?></title>
  <meta name="description" content="<?= App::e(kaamfit_brand_name()) ?> — test portal for German job search, tailored resumes, cover letters, and application tracking.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="<?= App::e(kaamfit_portal_fonts_href()) ?>" rel="stylesheet">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260826s">
  <link rel="stylesheet" href="/assets/css/site.css?v=20260826km1">
  <?php foreach ($extraStylesheets as $href): ?>
    <link rel="stylesheet" href="<?= App::e((string) $href) ?>">
  <?php endforeach; ?>
</head>
<body class="<?= App::e($bodyClass) ?>">
  <div class="site-test-banner" role="status">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
      <span><?= kaamfit_icon('lab') ?> <strong>Testing module</strong> — <?= App::e(kaamfit_brand_name()) ?> is a work-in-progress demo. Features may change; data is for testing.</span>
      <a class="small text-decoration-none" href="/about#testing">Learn more</a>
    </div>
  </div>
  <nav class="navbar navbar-expand-lg site-navbar sticky-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="/">
        <?= kaamfit_logo_mark() ?>
        <span><?= App::e(kaamfit_brand_name()) ?></span>
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
            <a class="btn btn-primary" href="<?= App::e(Site::portalHomeUrl()) ?>">Dashboard</a>
            <a class="btn btn-outline-secondary" href="/logout">Log out</a>
          <?php else: ?>
            <a class="btn btn-outline-secondary" href="/login">Sign in</a>
            <a class="btn btn-primary" href="/register">Register</a>
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

function site_layout_footer(array $opts = []): void
{
    $extraScripts = $opts['extra_scripts'] ?? [];
    ?>
  </div>
  <footer class="site-footer mt-auto">
    <div class="container py-4">
      <div class="row g-3 align-items-start">
        <div class="col-lg-5">
          <div class="d-flex align-items-center gap-2 fw-semibold mb-2">
            <?= kaamfit_logo_mark('sm') ?>
            <span><?= App::e(kaamfit_brand_name()) ?></span>
            <span class="badge site-badge-test">BETA</span>
          </div>
          <p class="text-secondary small mb-0">Testing portal for German job search, tailored resumes &amp; cover letters, and application tracking.</p>
        </div>
        <div class="col-lg-7">
          <ul class="nav flex-column flex-sm-row flex-wrap gap-sm-3">
            <li class="nav-item"><a class="nav-link px-0" href="/guide">How to use</a></li>
            <li class="nav-item"><a class="nav-link px-0" href="/features">Features</a></li>
            <li class="nav-item"><a class="nav-link px-0" href="/about">About</a></li>
            <li class="nav-item"><a class="nav-link px-0" href="/login">Sign in</a></li>
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
  <?php foreach ($extraScripts as $src): ?>
    <script src="<?= App::e((string) $src) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
    <?php
}
