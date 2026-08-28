<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$pdo = Db::pdo();
Versions::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $isBase = isset($_POST['is_base']) ? 1 : 0;
        if ($isActive) {
            $pdo->prepare('UPDATE cover_letters SET is_active = 0 WHERE user_id = ?')->execute([Auth::id()]);
        }
        if ($isBase) {
            $pdo->prepare('UPDATE cover_letters SET is_base = 0 WHERE user_id = ?')->execute([Auth::id()]);
        }
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE cover_letters SET title = ?, body = ?, company = ?, is_active = ?, is_base = ? WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([
                trim((string) ($_POST['title'] ?? '')),
                (string) ($_POST['body'] ?? ''),
                trim((string) ($_POST['company'] ?? '')),
                $isActive,
                $isBase,
                $id,
                Auth::id(),
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO cover_letters (user_id, title, body, company, is_active, is_base) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                Auth::id(),
                trim((string) ($_POST['title'] ?? 'Cover letter')) ?: 'Cover letter',
                (string) ($_POST['body'] ?? ''),
                trim((string) ($_POST['company'] ?? '')),
                1,
                $isBase,
            ]);
            if ($isBase) {
                $pdo->prepare(
                    'UPDATE cover_letters SET is_base = 0 WHERE user_id = ? AND id <> LAST_INSERT_ID()'
                )->execute([Auth::id()]);
            }
        }
        App::flash('Cover letter saved.');
        App::redirect('/cover-edit');
    }

    App::flash('Unknown action.', 'error');
    App::redirect('/cover-edit');
}

$letter = App::activeCoverLetter();
$editCoverId = isset($_GET['cover']) ? (int) $_GET['cover'] : 0;
if ($editCoverId > 0) {
    $picked = Versions::coverLetterById($editCoverId);
    if ($picked) {
        Versions::activateCover($editCoverId);
        $letter = $picked;
    }
}

if (empty($letter['id'])) {
    App::flash('Pick a cover letter first.', 'error');
    App::redirect('/cover');
}

$isMain = Versions::isMasterCover($letter);
$editingName = Versions::coverDisplayLabel($letter);

layout_header('Edit cover letter');
?>
<main class="editor">
  <header class="page-head">
    <h1>
      <span class="doc-id">#<?= (int) $letter['id'] ?></span>
      <?= App::e($editingName) ?>
    </h1>
    <p><a href="/cover">← My letters</a></p>
    <div class="preview-links">
      <a class="btn btn-sm btn-outline-secondary" href="/cover-letter" target="_blank" rel="noopener">Preview</a>
      <a class="btn btn-sm btn-outline-secondary" href="/cover-design">Style</a>
      <?php layout_pdf_buttons('cover', ['id' => (int) $letter['id']]); ?>
    </div>
  </header>

  <section class="editor-block">
    <form method="post" class="form">
      <h2>Letter</h2>
      <input type="hidden" name="action" value="save_cover">
      <input type="hidden" name="id" value="<?= (int) $letter['id'] ?>">
      <input type="hidden" name="is_active" value="1">
      <?php if ($isMain): ?>
        <input type="hidden" name="is_base" value="1">
      <?php endif; ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="letter-title">Name</label>
          <input class="form-control" type="text" id="letter-title" name="title" value="<?= App::e($letter['title'] ?? 'Cover letter') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="letter-company">Company</label>
          <input class="form-control" type="text" id="letter-company" name="company" value="<?= App::e($letter['company'] ?? '') ?>" placeholder="Optional">
        </div>
        <div class="col-12">
          <label class="form-label" for="letter-body">Letter text</label>
          <textarea class="form-control" id="letter-body" name="body" rows="16"><?= App::e($letter['body'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Save letter</button>
        </div>
      </div>
    </form>
  </section>
</main>
<?php
layout_footer();
