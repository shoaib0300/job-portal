<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

if (Auth::id() > 0) {
    App::redirect(App::portalHomePath());
}

$error = '';
$defaultNext = App::portalHomePath();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = (string) ($_POST['login'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (Auth::login($login, $password)) {
        $next = App::safeAppNext((string) ($_GET['next'] ?? $_POST['next'] ?? $defaultNext), $defaultNext);
        App::redirect($next);
    }
    $error = 'Wrong username/email or password.';
}

$next = App::safeAppNext((string) ($_GET['next'] ?? $defaultNext), $defaultNext);

site_layout_header('Sign in');
?>
<div class="site-auth-wrap">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h3 mb-3">Sign in</h1>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= App::e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="next" value="<?= App::e($next) ?>">
        <div class="mb-3">
          <label class="form-label" for="login">Username or email</label>
          <input class="form-control" type="text" id="login" name="login" required autofocus value="<?= App::e((string) ($_POST['login'] ?? '')) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <input class="form-control" type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Sign in</button>
      </form>
      <p class="small text-secondary mt-3 mb-0">No account? <a href="/register.php">Create one</a></p>
    </div>
  </div>
</div>
<?php
site_layout_footer();
