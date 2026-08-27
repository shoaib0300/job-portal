<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

SuperAdmin::requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$user = SuperAdmin::getUser($id);
if ($user === null) {
    App::flash('User not found.', 'error');
    App::redirect('/super-admin/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'set_password') {
            SuperAdmin::setUserPassword($id, (string) ($_POST['password'] ?? ''));
            App::flash('Password updated.');
        } elseif ($action === 'toggle_active') {
            SuperAdmin::setUserActive($id, (string) ($_POST['value'] ?? '') === '1');
            App::flash('Login access updated.');
        } elseif ($action === 'toggle_translate') {
            SuperAdmin::setUserCanTranslate($id, (string) ($_POST['value'] ?? '') === '1');
            App::flash('Translation access updated.');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/super-admin/user.php?id=' . $id);
}

$usage = LibreTranslate::usageForUserThisMonth($id);
$month = LibreTranslate::usageForPeriod($usage, 'month');
$last = LibreTranslate::usageForPeriod($usage, 'last');
$year = LibreTranslate::usageForPeriod($usage, 'year');

$engineStmt = Db::pdo()->prepare(
    'SELECT engine,
            SUM(CASE WHEN billed = 1 THEN chars_in ELSE 0 END) AS billed_chars,
            SUM(CASE WHEN billed = 0 THEN chars_in ELSE 0 END) AS cached_chars,
            COUNT(*) AS requests
     FROM translation_usage
     WHERE user_id = ? AND created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01\')
     GROUP BY engine'
);
$engineStmt->execute([$id]);
$engines = $engineStmt->fetchAll() ?: [];

super_layout_header('User #' . $id);
?>
<p><a href="/super-admin/users">← Users</a></p>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h2 class="h5"><?= App::e((string) $user['name']) ?></h2>
    <p class="mb-1">@<?= App::e((string) $user['username']) ?> · <?= App::e((string) $user['email']) ?></p>
    <p class="small text-secondary mb-3">Created <?= App::e((string) $user['created_at']) ?> · Last login <?= App::e((string) ($user['last_login_at'] ?? 'never')) ?></p>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <form method="post">
        <input type="hidden" name="action" value="toggle_active">
        <input type="hidden" name="value" value="<?= (int) $user['is_active'] === 1 ? '0' : '1' ?>">
        <button class="btn btn-sm <?= (int) $user['is_active'] === 1 ? 'btn-success' : 'btn-warning' ?>" type="submit">
          Login: <?= (int) $user['is_active'] === 1 ? 'Allowed' : 'Pending' ?>
        </button>
      </form>
      <form method="post">
        <input type="hidden" name="action" value="toggle_translate">
        <input type="hidden" name="value" value="<?= (int) $user['can_translate'] === 1 ? '0' : '1' ?>">
        <button class="btn btn-sm <?= (int) $user['can_translate'] === 1 ? 'btn-success' : 'btn-outline-secondary' ?>" type="submit">
          Translate: <?= (int) $user['can_translate'] === 1 ? 'On' : 'Off' ?>
        </button>
      </form>
    </div>
    <form method="post" class="row g-2 align-items-end" style="max-width:420px">
      <input type="hidden" name="action" value="set_password">
      <div class="col-8">
        <label class="form-label">Set temporary password</label>
        <input class="form-control" type="password" name="password" required minlength="8">
      </div>
      <div class="col-4"><button class="btn btn-outline-primary w-100" type="submit">Save</button></div>
    </form>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h2 class="h5 mb-3">German PDF translations</h2>
    <div class="row g-3">
      <div class="col-md-4"><div class="border rounded p-3"><div class="small text-secondary">This month</div><div class="h5 mb-0"><?= App::e(LibreTranslate::formatEuro($month['billed_chars'])) ?></div><div class="small"><?= App::e(number_format($month['billed_chars'])) ?> billed · <?= App::e(number_format($month['cached_chars'])) ?> cache · <?= (int) $month['requests'] ?> req</div></div></div>
      <div class="col-md-4"><div class="border rounded p-3"><div class="small text-secondary">Last month</div><div class="h5 mb-0"><?= App::e(LibreTranslate::formatEuro($last['billed_chars'])) ?></div><div class="small"><?= App::e(number_format($last['billed_chars'])) ?> billed</div></div></div>
      <div class="col-md-4"><div class="border rounded p-3"><div class="small text-secondary">This year</div><div class="h5 mb-0"><?= App::e(LibreTranslate::formatEuro($year['billed_chars'])) ?></div><div class="small"><?= App::e(number_format($year['billed_chars'])) ?> billed</div></div></div>
    </div>
    <?php if ($engines !== []): ?>
      <h3 class="h6 mt-4">By engine (this month)</h3>
      <table class="table table-sm">
        <thead><tr><th>Engine</th><th class="text-end">Billed</th><th class="text-end">Cache</th><th class="text-end">Requests</th></tr></thead>
        <tbody>
          <?php foreach ($engines as $eng): ?>
            <tr>
              <td><?= App::e((string) $eng['engine']) ?></td>
              <td class="text-end"><?= App::e(number_format((int) $eng['billed_chars'])) ?></td>
              <td class="text-end"><?= App::e(number_format((int) $eng['cached_chars'])) ?></td>
              <td class="text-end"><?= (int) $eng['requests'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php
super_layout_footer();
