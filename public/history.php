<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$pdo = Db::pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $pdo->prepare(
            'INSERT INTO search_history (user_id, company, role, note) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            Auth::id(),
            trim((string) ($_POST['company'] ?? '')),
            trim((string) ($_POST['role'] ?? '')),
            trim((string) ($_POST['note'] ?? '')),
        ]);
        App::flash('History entry added.');
        App::redirect('/history.php');
    }
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM search_history WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0), Auth::id()]);
        App::flash('History entry deleted.');
        App::redirect('/history.php');
    }
}

$history = App::searchHistory(100);

layout_header('History');
?>
<main class="page-wide">
  <header class="page-head">
    <h1>History</h1>
    <p>Optional notes. <a href="/applications.php">Applications</a> is the source of truth.</p>
  </header>

  <section class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h5 mb-3">Add note</h2>
      <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="action" value="add">
        <div class="col-md-4">
          <label class="form-label" for="hist-company">Company</label>
          <input class="form-control" type="text" id="hist-company" name="company">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="hist-role">Role</label>
          <input class="form-control" type="text" id="hist-role" name="role">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="hist-note">Note</label>
          <input class="form-control" type="text" id="hist-note" name="note" placeholder="What changed?">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Add</button>
        </div>
      </form>
    </div>
  </section>

  <?php if (!$history): ?>
    <div class="card shadow-sm"><div class="card-body text-secondary">No notes yet. <a href="/tailor.php">Paste a job</a> to start.</div></div>
  <?php else: ?>
    <div class="list-group shadow-sm">
      <?php foreach ($history as $row): ?>
        <div class="list-group-item d-flex flex-wrap justify-content-between align-items-start gap-2">
          <div>
            <strong><?= App::e($row['company'] !== '' ? $row['company'] : 'Untitled') ?></strong>
            <?php if ($row['role'] !== ''): ?>
              <span> — <?= App::e($row['role']) ?></span>
            <?php endif; ?>
            <?php if ($row['note']): ?>
              <p class="mb-1"><?= App::e($row['note']) ?></p>
            <?php endif; ?>
            <time class="text-secondary small"><?= App::e($row['created_at']) ?></time>
          </div>
          <form method="post" onsubmit="return confirm('Delete this entry?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php
layout_footer();
