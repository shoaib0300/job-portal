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
        App::redirect('/cover.php');
    }

    if ($action === 'activate_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        Versions::activateCover($id);
        App::flash('Now editing this cover letter.');
        App::redirect('/cover.php?cover=' . $id);
    }

    if ($action === 'new_job_cover') {
        $company = trim((string) ($_POST['company'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        if ($company === '') {
            App::flash('Enter a company name first.', 'error');
            App::redirect('/cover.php');
        }
        $base = Versions::baseCoverLetter();
        if ($base === null) {
            App::flash('No Main cover letter to copy from.', 'error');
            App::redirect('/cover.php');
        }
        if ($title === '') {
            $title = 'Cover letter — ' . $company;
        }
        $companyLine = $location !== '' ? ($company . ' · ' . $location) : $company;
        $newId = Versions::duplicateCover((int) $base['id'], $title);
        $pdo->prepare('UPDATE cover_letters SET company = ? WHERE id = ? AND user_id = ?')->execute([$companyLine, $newId, Auth::id()]);
        App::flash('Created cover letter #' . $newId . ' (copy of Main' . ($location !== '' ? ', ' . $location : '') . ').');
        App::redirect('/cover.php?cover=' . $newId);
    }

    if ($action === 'mark_cover_base') {
        $id = (int) ($_POST['id'] ?? 0);
        Versions::markCoverBase($id);
        App::flash('Marked as Main cover letter.');
        App::redirect('/cover.php');
    }

    if ($action === 'delete_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::deleteCover($id);
            App::flash('Cover letter deleted.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/cover.php');
    }

    App::flash('Unknown action.', 'error');
    App::redirect('/cover.php');
}

$letter = App::activeCoverLetter();
$coverLetters = App::coverLetters();
$editCoverId = isset($_GET['cover']) ? (int) $_GET['cover'] : 0;
if ($editCoverId > 0) {
    $picked = Versions::coverLetterById($editCoverId);
    if ($picked) {
        $letter = $picked;
    }
}

layout_header('Cover letter');
?>
<main class="editor">
  <header class="page-head">
    <h1>Cover letter</h1>
    <p>Write the letter. Style it separately.</p>
    <div class="preview-links">
      <a class="btn btn-sm btn-outline-secondary" href="/cover-letter.php" target="_blank" rel="noopener">Preview</a>
      <a class="btn btn-sm btn-outline-secondary" href="/cover-design.php">Style</a>
      <a class="btn btn-sm btn-primary" href="/pdf.php?doc=cover">PDF</a>
    </div>
  </header>

  <section class="editor-block" id="letters">
    <h2>My letters</h2>
    <ol class="simple-steps">
      <li><strong>Main</strong> = your normal letter.</li>
      <li>For a job: <strong>Add cover letter</strong>, change it, then download that one.</li>
    </ol>

    <form method="post" class="form new-job-form" id="add-cover">
      <h3>Add cover letter</h3>
      <p class="empty" style="margin:0 0 0.75rem">Always a copy of Main cover letter.</p>
      <input type="hidden" name="action" value="new_job_cover">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="add-company">Company</label>
          <input class="form-control" type="text" id="add-company" name="company" required placeholder="e.g. SAP">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="add-location">Job location</label>
          <input class="form-control" type="text" id="add-location" name="location" placeholder="e.g. München, Germany" required>
        </div>
        <div class="col-12">
          <label class="form-label" for="add-title">Name</label>
          <input class="form-control" type="text" id="add-title" name="title" placeholder="e.g. QA Engineer — SAP">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Add cover letter</button>
        </div>
      </div>
    </form>

    <?php if (!$coverLetters): ?>
      <p class="empty">No cover letters yet. <a href="#add-cover">Add cover letter</a>.</p>
    <?php else: ?>
      <ul class="version-list doc-card-list">
        <?php foreach ($coverLetters as $cl): ?>
          <?php
          $cid = (int) $cl['id'];
          $isMain = (int) ($cl['is_base'] ?? 0) === 1;
          $isOpen = (int) ($letter['id'] ?? 0) === $cid;
          $label = $isMain ? 'Main cover letter' : (string) $cl['title'];
          ?>
          <li class="version-list-item doc-card<?= $isOpen ? ' is-open' : '' ?>">
            <div class="doc-card-main">
              <span class="doc-id" title="Unique cover letter ID">#<?= $cid ?></span>
              <div class="doc-card-text">
                <strong>
                  <?php if ($isMain): ?><span class="badge-main">Main</span> <?php endif; ?>
                  <?php if ($isOpen): ?><span class="badge-active">Editing</span> <?php endif; ?>
                  <?= App::e($label) ?>
                </strong>
                <?php if (!$isMain && $cl['company'] !== ''): ?>
                  <span class="muted"><?= App::e((string) $cl['company']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="version-list-actions doc-card-actions">
              <?php if ($isOpen): ?>
                <span class="btn btn-sm btn-outline-primary disabled" aria-current="true">Selected</span>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="action" value="activate_cover">
                  <input type="hidden" name="id" value="<?= $cid ?>">
                  <button type="submit" class="btn btn-sm btn-primary">Edit / Select</button>
                </form>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-secondary" href="/pdf.php?doc=cover&amp;id=<?= $cid ?>">Download</a>
              <a class="btn btn-sm btn-outline-secondary" href="/cover-letter.php?id=<?= $cid ?>" target="_blank" rel="noopener">View</a>
              <?php if (!$isMain): ?>
                <form method="post" onsubmit="return confirm('Delete cover letter #<?= $cid ?>?');">
                  <input type="hidden" name="action" value="delete_cover">
                  <input type="hidden" name="id" value="<?= $cid ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (!empty($letter['id'])): ?>
    <form method="post" class="form" style="margin-top:1.25rem">
      <h3>Edit letter <span class="doc-id">#<?= (int) $letter['id'] ?></span></h3>
      <input type="hidden" name="action" value="save_cover">
      <input type="hidden" name="id" value="<?= (int) $letter['id'] ?>">
      <input type="hidden" name="is_active" value="1">
      <?php if ((int) ($letter['is_base'] ?? 0) === 1): ?>
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
    <?php endif; ?>
  </section>
</main>
<?php
layout_footer();
