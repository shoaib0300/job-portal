<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$pdo = Db::pdo();
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $status = (string) ($_POST['status'] ?? 'applied');
        $allowed = ['applied', 'rejected', 'interview', 'offer', 'custom'];
        if (!in_array($status, $allowed, true)) {
            $status = 'applied';
        }
        $editId = (int) ($_POST['id'] ?? 0);
        $date = trim((string) ($_POST['applied_date'] ?? ''));
        $dateVal = $date !== '' ? $date : null;

        if ($editId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE applications SET company = ?, role = ?, status = ?, applied_date = ?, notes = ?, jd_snippet = ?, link = ? WHERE id = ?'
            );
            $stmt->execute([
                trim((string) ($_POST['company'] ?? '')),
                trim((string) ($_POST['role'] ?? '')),
                $status,
                $dateVal,
                trim((string) ($_POST['notes'] ?? '')),
                trim((string) ($_POST['jd_snippet'] ?? '')),
                trim((string) ($_POST['link'] ?? '')),
                $editId,
            ]);
            App::flash('Application updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO applications (company, role, status, applied_date, notes, jd_snippet, link) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim((string) ($_POST['company'] ?? '')),
                trim((string) ($_POST['role'] ?? '')),
                $status,
                $dateVal,
                trim((string) ($_POST['notes'] ?? '')),
                trim((string) ($_POST['jd_snippet'] ?? '')),
                trim((string) ($_POST['link'] ?? '')),
            ]);
            App::flash('Application created.');
        }
        App::redirect('/applications.php');
    }

    if ($postAction === 'delete') {
        $delId = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM applications WHERE id = ?');
        $stmt->execute([$delId]);
        App::flash('Application deleted.');
        App::redirect('/applications.php');
    }

    App::redirect('/applications.php');
}

$statuses = ['applied', 'rejected', 'interview', 'offer', 'custom'];

if ($action === 'new' || $action === 'edit') {
    $row = [
        'id' => 0,
        'company' => '',
        'role' => '',
        'status' => 'custom',
        'applied_date' => date('Y-m-d'),
        'notes' => '',
        'jd_snippet' => '',
        'link' => '',
    ];
    if ($action === 'edit' && $id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if ($found) {
            $row = $found;
        }
    }

    layout_header($row['id'] ? 'Edit application' : 'New application');
    ?>
    <main class="page-narrow">
      <header class="page-head">
        <h1><?= $row['id'] ? 'Edit application' : 'Add entry' ?></h1>
        <p><a href="/applications.php">&larr; All applications</a></p>
      </header>
      <form method="post" class="form">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <label>Company <input type="text" name="company" required value="<?= App::e($row['company']) ?>"></label>
        <label>Role <input type="text" name="role" required value="<?= App::e($row['role']) ?>"></label>
        <label>Status
          <select name="status">
            <?php foreach ($statuses as $s): ?>
              <option value="<?= App::e($s) ?>"<?= $row['status'] === $s ? ' selected' : '' ?>><?= App::e(App::statusLabel($s)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Applied date <input type="date" name="applied_date" value="<?= App::e((string) $row['applied_date']) ?>"></label>
        <label>Link <input type="url" name="link" value="<?= App::e((string) $row['link']) ?>" placeholder="https://"></label>
        <label>JD snippet <textarea name="jd_snippet" rows="4"><?= App::e((string) $row['jd_snippet']) ?></textarea></label>
        <label>Notes <textarea name="notes" rows="3"><?= App::e((string) $row['notes']) ?></textarea></label>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
      <?php if ((int) $row['id'] > 0): ?>
      <form method="post" class="inline-delete" onsubmit="return confirm('Delete this application?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <button type="submit" class="btn btn-danger">Delete</button>
      </form>
      <?php endif; ?>
    </main>
    <?php
    layout_footer();
    exit;
}

$apps = App::applications(null);
layout_header('Applications');
?>
<main class="page-wide">
  <header class="page-head row">
    <div>
      <h1>Applications</h1>
      <p>Track where you applied, rejections, interviews, and custom entries.</p>
    </div>
    <a class="btn btn-primary" href="/applications.php?action=new">Add custom entry</a>
  </header>

  <?php if (!$apps): ?>
    <p class="empty">No applications yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Company</th>
            <th>Role</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($apps as $app): ?>
            <tr>
              <td><?= App::e($app['company']) ?></td>
              <td><?= App::e($app['role']) ?></td>
              <td><span class="badge status-<?= App::e($app['status']) ?>"><?= App::e(App::statusLabel($app['status'])) ?></span></td>
              <td><?= App::e((string) $app['applied_date']) ?></td>
              <td><a href="/applications.php?action=edit&amp;id=<?= (int) $app['id'] ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
<?php
layout_footer();
