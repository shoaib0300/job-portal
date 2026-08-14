<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

if (Auth::id() > 0) {
    App::redirect('/');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::register(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        App::flash('Account created. This workspace is yours.');
        App::redirect('/');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

layout_header('Create account', [
    'hide_nav' => true,
    'body_class' => 'auth-page',
]);
?>
<main class="auth-card panel">
  <p class="eyebrow">MNK</p>
  <h1>Create account</h1>
  <p class="muted">You’ll get an empty resume workspace. Other people’s applications stay private.</p>
  <?php if ($error !== ''): ?>
    <div class="flash flash-error"><?= App::e($error) ?></div>
  <?php endif; ?>
  <form method="post" class="form">
    <label>Name <input type="text" name="name" required value="<?= App::e((string) ($_POST['name'] ?? '')) ?>"></label>
    <label>Email <input type="email" name="email" required value="<?= App::e((string) ($_POST['email'] ?? '')) ?>"></label>
    <label>Password <input type="password" name="password" required minlength="8"></label>
    <button type="submit" class="btn btn-primary">Create account</button>
  </form>
  <p class="muted">Already have an account? <a href="/login.php">Sign in</a></p>
</main>
<?php
layout_footer();
