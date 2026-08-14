<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

if (Auth::id() > 0) {
    App::redirect('/');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = (string) ($_POST['login'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (Auth::login($login, $password)) {
        $next = (string) ($_GET['next'] ?? $_POST['next'] ?? '/');
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
            $next = '/';
        }
        App::redirect($next);
    }
    $error = 'Wrong username/email or password.';
}

$next = (string) ($_GET['next'] ?? '/');

layout_header('Sign in', [
    'hide_nav' => true,
    'body_class' => 'auth-page',
]);
?>
<main class="auth-card panel">
  <p class="eyebrow">MNK</p>
  <h1>Sign in</h1>
  <p class="muted">Each account has its own resume, letters, and applications.</p>
  <?php if ($error !== ''): ?>
    <div class="flash flash-error"><?= App::e($error) ?></div>
  <?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="next" value="<?= App::e($next) ?>">
    <label>Username or email <input type="text" name="login" required autofocus value="<?= App::e((string) ($_POST['login'] ?? '')) ?>"></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit" class="btn btn-primary">Sign in</button>
  </form>
  <p class="muted">No account? <a href="/register.php">Create one</a></p>
</main>
<?php
layout_footer();
