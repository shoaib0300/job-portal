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
    <h1>Search history</h1>
    <p>Optional notes for tailor sessions. Applications (with JD + resume/cover IDs) are the source of truth.</p>
  </header>

  <section class="editor-block">
    <h2>Add note</h2>
    <form method="post" class="form form-row">
      <input type="hidden" name="action" value="add">
      <label>Company <input type="text" name="company"></label>
      <label>Role <input type="text" name="role"></label>
      <label class="grow">Note <input type="text" name="note" placeholder="What changed?"></label>
      <button type="submit" class="btn btn-primary">Add</button>
    </form>
  </section>

  <?php if (!$history): ?>
    <p class="empty-card empty">No tailor notes yet. <a href="/tailor.php">Apply from a JD</a> to log an application.</p>
  <?php else: ?>
    <ul class="history-list panel" style="padding:0 1.25rem">
      <?php foreach ($history as $row): ?>
        <li>
          <div>
            <strong><?= App::e($row['company'] !== '' ? $row['company'] : 'Untitled') ?></strong>
            <?php if ($row['role'] !== ''): ?>
              <span> — <?= App::e($row['role']) ?></span>
            <?php endif; ?>
            <?php if ($row['note']): ?>
              <p><?= App::e($row['note']) ?></p>
            <?php endif; ?>
            <time><?= App::e($row['created_at']) ?></time>
          </div>
          <form method="post" onsubmit="return confirm('Delete this entry?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button type="submit" class="btn btn-small btn-danger">Delete</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
<?php
layout_footer();
