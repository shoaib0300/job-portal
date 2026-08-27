<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

SuperAdmin::ensureSchema();

if (SuperAdmin::id() > 0) {
    // Dashboard
    $users = SuperAdmin::listUsers();
    $active = 0;
    $translateOn = 0;
    foreach ($users as $u) {
        if ((int) ($u['is_active'] ?? 1) === 1) {
            $active++;
        }
        if ((int) ($u['can_translate'] ?? 1) === 1) {
            $translateOn++;
        }
    }
    $usageRows = LibreTranslate::usageByUserThisMonth();
    $billedMonth = 0;
    foreach ($usageRows as $row) {
        $billedMonth += (int) ($row['billed_chars'] ?? 0);
    }
    $globalCompanies = CareerCompanies::forUser(0, false);

    super_layout_header('Dashboard');
    ?>
  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">Users</div><div class="h3 mb-0"><?= count($users) ?></div><div class="small text-secondary"><?= $active ?> active</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">Translate enabled</div><div class="h3 mb-0"><?= $translateOn ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">Billed chars (month)</div><div class="h3 mb-0"><?= App::e(number_format($billedMonth)) ?></div><div class="small text-secondary"><?= App::e(LibreTranslate::formatEuro($billedMonth)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">Global companies</div><div class="h3 mb-0"><?= count($globalCompanies) ?></div></div></div></div>
  </div>
  <p class="mb-0"><a class="btn btn-primary btn-sm" href="/super-admin/users">Manage users</a>
    <a class="btn btn-outline-secondary btn-sm" href="/super-admin/companies">Companies</a>
    <a class="btn btn-outline-secondary btn-sm" href="/super-admin/settings">Settings</a></p>
    <?php
    super_layout_footer();
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (SuperAdmin::login($email, $password)) {
        App::redirect('/super-admin/');
    }
    $error = 'Wrong email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Super Admin · Sign in</title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260826s">
</head>
<body class="auth-page">
<main class="card shadow-sm" style="max-width:420px;margin:4rem auto">
  <div class="card-body p-4">
    <h1 class="h4 mb-3">Super Admin</h1>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?= App::e($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" required autofocus value="<?= App::e((string) ($_POST['email'] ?? '')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>
    <p class="small mt-3 mb-0"><a href="/super-admin/forgot">Forgot password</a></p>
  </div>
</main>
</body>
</html>
