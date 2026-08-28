<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

SuperAdmin::ensureSchema();

$error = '';
$devLink = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!SuperAdmin::isRecoveryEmail($email)) {
        $error = 'That email is not a recovery address.';
    } else {
        $created = SuperAdmin::createResetToken();
        $body = 'Reset your ' . kaamfit_brand_name() . " super-admin password:\n\n" . $created['url'] . "\n\nThis link expires in 1 hour.";
        SuperAdmin::tryMail($email, kaamfit_brand_name() . ' Super Admin password reset', $body);
        if (SuperAdmin::isDev()) {
            $devLink = $created['url'];
        }
        App::flash('If the email is allowed, a reset link was created. Check your inbox' . (SuperAdmin::isDev() ? ' (dev link shown below).' : '.'));
        if ($devLink === '') {
            App::redirect('/super-admin/forgot.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot password · Super Admin</title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260826s">
</head>
<body class="auth-page">
<main class="card shadow-sm" style="max-width:480px;margin:4rem auto">
  <div class="card-body p-4">
    <h1 class="h4 mb-3">Reset super-admin password</h1>
    <p class="small text-secondary">Use a recovery email configured for this site.</p>
    <?php
    $flash = App::flash();
    if ($flash): ?>
      <div class="alert alert-success"><?= App::e((string) $flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?= App::e($error) ?></div>
    <?php endif; ?>
    <?php if ($devLink !== ''): ?>
      <div class="alert alert-warning small">Dev reset link:<br><a href="<?= App::e($devLink) ?>"><?= App::e($devLink) ?></a></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label" for="email">Recovery email</label>
        <input class="form-control" type="email" id="email" name="email" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Send reset link</button>
    </form>
    <p class="small mt-3 mb-0"><a href="/super-admin/">Back to sign in</a></p>
  </div>
</main>
</body>
</html>
