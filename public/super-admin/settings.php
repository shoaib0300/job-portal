<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

SuperAdmin::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'password') {
            SuperAdmin::changePassword(
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['new_password'] ?? '')
            );
            App::flash('Password changed.');
        } elseif ($action === 'email') {
            SuperAdmin::setPrimaryEmail((string) ($_POST['email'] ?? ''));
            App::flash('Primary email updated.');
        } elseif ($action === 'add_recovery') {
            SuperAdmin::addRecoveryEmail((string) ($_POST['email'] ?? ''));
            App::flash('Recovery email added.');
        } elseif ($action === 'remove_recovery') {
            SuperAdmin::removeRecoveryEmail((string) ($_POST['email'] ?? ''));
            App::flash('Recovery email removed.');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/super-admin/settings.php');
}

$admin = SuperAdmin::admin();
$emails = SuperAdmin::recoveryEmails();

super_layout_header('Settings');
?>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h5 mb-3">Primary email</h2>
        <form method="post">
          <input type="hidden" name="action" value="email">
          <label class="form-label" for="email">Email</label>
          <input class="form-control mb-3" type="email" id="email" name="email" required value="<?= App::e((string) ($admin['email'] ?? '')) ?>">
          <button class="btn btn-primary" type="submit">Save email</button>
        </form>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5 mb-3">Change password</h2>
        <form method="post">
          <input type="hidden" name="action" value="password">
          <div class="mb-3">
            <label class="form-label" for="current_password">Current</label>
            <input class="form-control" type="password" id="current_password" name="current_password" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="new_password">New</label>
            <input class="form-control" type="password" id="new_password" name="new_password" required minlength="8">
          </div>
          <button class="btn btn-primary" type="submit">Update password</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5 mb-3">Recovery emails</h2>
        <p class="small text-secondary">These addresses can request a password reset at <code>/super-admin/forgot.php</code>.</p>
        <ul class="list-group mb-3">
          <?php foreach ($emails as $em): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <?= App::e($em) ?>
              <form method="post" class="m-0">
                <input type="hidden" name="action" value="remove_recovery">
                <input type="hidden" name="email" value="<?= App::e($em) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="add_recovery">
          <div class="col-8"><input class="form-control" type="email" name="email" required placeholder="new@example.com"></div>
          <div class="col-4"><button class="btn btn-outline-primary w-100" type="submit">Add</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
super_layout_footer();
