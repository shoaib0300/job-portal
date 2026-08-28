<?php

declare(strict_types=1);

function super_layout_header(string $title): void
{
    $admin = SuperAdmin::admin();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= App::e($title) ?> · Super Admin</title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260826s">
  <style>
    body { background: #f4f5f7; }
    .sa-shell { min-height: 100vh; display: flex; }
    .sa-nav { width: 220px; background: #1a1d23; color: #fff; padding: 1.25rem 1rem; flex-shrink: 0; }
    .sa-nav a { color: #c8ccd4; text-decoration: none; display: block; padding: 0.45rem 0.6rem; border-radius: 6px; margin-bottom: 0.25rem; }
    .sa-nav a:hover, .sa-nav a.active { background: #2a2f3a; color: #fff; }
    .sa-brand { font-weight: 700; margin-bottom: 1.25rem; color: #fff; }
    .sa-main { flex: 1; padding: 1.5rem; min-width: 0; }
    .sa-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
    @media (max-width: 768px) {
      .sa-shell { flex-direction: column; }
      .sa-nav { width: 100%; }
    }
  </style>
</head>
<body>
  <div class="sa-shell">
    <aside class="sa-nav">
      <div class="sa-brand"><?= App::e(kaamfit_brand_name()) ?> Super</div>
      <a href="/super-admin/" class="<?= basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php' ? 'active' : '' ?>">Dashboard</a>
      <a href="/super-admin/users.php" class="<?= str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'user') ? 'active' : '' ?>">Users</a>
      <a href="/super-admin/companies.php" class="<?= str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'companies') ? 'active' : '' ?>">Companies</a>
      <a href="/super-admin/jobs.php" class="<?= str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'jobs') ? 'active' : '' ?>">Jobs</a>
      <a href="/super-admin/settings.php" class="<?= str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'settings') ? 'active' : '' ?>">Settings</a>
      <a href="/super-admin/logout.php">Log out</a>
      <?php if ($admin): ?>
        <p class="small text-secondary mt-3 mb-0" style="color:#8b919a!important"><?= App::e((string) $admin['email']) ?></p>
      <?php endif; ?>
    </aside>
    <div class="sa-main">
      <div class="sa-top">
        <h1 class="h3 mb-0"><?= App::e($title) ?></h1>
      </div>
      <?php
      $flash = App::flash();
      if ($flash):
          $cls = ($flash['type'] ?? '') === 'error' ? 'alert-danger' : 'alert-success';
          ?>
        <div class="alert <?= $cls ?>"><?= App::e((string) $flash['message']) ?></div>
      <?php endif;
}

function super_layout_footer(): void
{
    ?>
    </div>
  </div>
</body>
</html>
    <?php
}
