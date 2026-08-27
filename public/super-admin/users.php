<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

SuperAdmin::requireLogin();
SuperAdmin::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($action === 'create') {
            $uid = SuperAdmin::createUser(
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['username'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? '')
            );
            App::flash('User #' . $uid . ' created.');
        } elseif ($action === 'toggle_active') {
            SuperAdmin::setUserActive($id, (string) ($_POST['value'] ?? '') === '1');
            App::flash('Login access updated.');
        } elseif ($action === 'toggle_translate') {
            SuperAdmin::setUserCanTranslate($id, (string) ($_POST['value'] ?? '') === '1');
            App::flash('Translation access updated.');
        } elseif ($action === 'delete') {
            SuperAdmin::deleteUser($id);
            App::flash('User deleted.');
        } elseif ($action === 'set_password') {
            SuperAdmin::setUserPassword($id, (string) ($_POST['password'] ?? ''));
            App::flash('Password updated.');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/super-admin/users.php');
}

$users = SuperAdmin::listUsers();
$usageByUser = [];
foreach (LibreTranslate::usageByUserThisMonth() as $row) {
    $usageByUser[(int) $row['user_id']] = $row;
}

super_layout_header('Users');
?>
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h2 class="h5 mb-3">Create user</h2>
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="action" value="create">
      <div class="col-md-3"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
      <div class="col-md-2"><label class="form-label">Username</label><input class="form-control" name="username" required pattern="[a-zA-Z0-9_]{3,80}"></div>
      <div class="col-md-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
      <div class="col-md-2"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required minlength="8"></div>
      <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Create</button></div>
    </form>
  </div>
</div>

<div class="table-responsive card shadow-sm">
  <table class="table table-sm mb-0 align-middle">
    <thead>
      <tr>
        <th>ID</th>
        <th>User</th>
        <th>Login</th>
        <th>Translate</th>
        <th>This month</th>
        <th>Last login</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <?php
        $uid = (int) $u['id'];
        $usage = $usageByUser[$uid] ?? ['billed_chars' => 0, 'cached_chars' => 0, 'requests' => 0];
        ?>
        <tr>
          <td><?= $uid ?></td>
          <td>
            <a href="/super-admin/user?id=<?= $uid ?>"><?= App::e((string) $u['name']) ?></a>
            <div class="small text-secondary">@<?= App::e((string) $u['username']) ?> · <?= App::e((string) $u['email']) ?></div>
          </td>
          <td>
            <form method="post" class="d-inline">
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="id" value="<?= $uid ?>">
              <input type="hidden" name="value" value="<?= (int) $u['is_active'] === 1 ? '0' : '1' ?>">
              <button class="btn btn-sm <?= (int) $u['is_active'] === 1 ? 'btn-success' : 'btn-warning' ?>" type="submit">
                <?= (int) $u['is_active'] === 1 ? 'Allowed' : 'Pending' ?>
              </button>
            </form>
          </td>
          <td>
            <form method="post" class="d-inline">
              <input type="hidden" name="action" value="toggle_translate">
              <input type="hidden" name="id" value="<?= $uid ?>">
              <input type="hidden" name="value" value="<?= (int) $u['can_translate'] === 1 ? '0' : '1' ?>">
              <button class="btn btn-sm <?= (int) $u['can_translate'] === 1 ? 'btn-success' : 'btn-outline-secondary' ?>" type="submit">
                <?= (int) $u['can_translate'] === 1 ? 'On' : 'Off' ?>
              </button>
            </form>
          </td>
          <td class="small">
            <?= App::e(LibreTranslate::formatEuro((int) $usage['billed_chars'])) ?>
            <div class="text-secondary"><?= App::e(number_format((int) $usage['billed_chars'])) ?> billed</div>
          </td>
          <td class="small"><?= App::e((string) ($u['last_login_at'] ?? '—')) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/super-admin/user?id=<?= $uid ?>">Open</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this user and their data?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $uid ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
super_layout_footer();
