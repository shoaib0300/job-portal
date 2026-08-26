<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

SuperAdmin::ensureSchema();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    try {
        if ($new !== $confirm) {
            throw new InvalidArgumentException('Passwords do not match.');
        }
        SuperAdmin::resetPasswordWithToken($token, $new);
        App::flash('Password updated. Sign in with the new password.');
        App::redirect('/super-admin/');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set new password · Super Admin</title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=20260826s">
</head>
<body class="auth-page">
<main class="card shadow-sm" style="max-width:420px;margin:4rem auto">
  <div class="card-body p-4">
    <h1 class="h4 mb-3">New password</h1>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?= App::e($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="token" value="<?= App::e($token) ?>">
      <div class="mb-3">
        <label class="form-label" for="password">New password</label>
        <input class="form-control" type="password" id="password" name="password" required minlength="8">
      </div>
      <div class="mb-3">
        <label class="form-label" for="confirm">Confirm</label>
        <input class="form-control" type="password" id="confirm" name="confirm" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary w-100">Save password</button>
    </form>
  </div>
</main>
</body>
</html>
