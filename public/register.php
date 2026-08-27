<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

if (Auth::id() > 0) {
    App::redirect(Site::portalHomeUrl());
}

$error = '';
$pendingNotice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::register(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        $pendingNotice = Auth::pendingAccessMessage();
        App::flash('Account created — login is disabled until an admin enables it.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

site_layout_header('Create account');
?>
<div class="site-auth-wrap">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h3 mb-3">Create account</h1>
      <?php if ($pendingNotice !== ''): ?>
        <div class="alert alert-warning" style="white-space: pre-line"><?= App::e($pendingNotice) ?></div>
        <p class="mb-0"><a class="btn btn-outline-primary" href="/login">Back to sign in</a></p>
      <?php else: ?>
        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?= App::e($error) ?></div>
        <?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" type="text" id="name" name="name" required value="<?= App::e((string) ($_POST['name'] ?? '')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" required value="<?= App::e((string) ($_POST['email'] ?? '')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password" required minlength="8">
          </div>
          <button type="submit" class="btn btn-primary w-100">Create account</button>
        </form>
        <p class="small text-secondary mt-3 mb-0">Already have an account? <a href="/login">Sign in</a></p>
        <p class="small text-secondary mt-2 mb-0">New accounts stay disabled until the admin enables them.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
site_layout_footer();
