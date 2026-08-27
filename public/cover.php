<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$pdo = Db::pdo();
Versions::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'activate_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        Versions::activateCover($id);
        App::flash('Now editing this cover letter.');
        App::redirect('/cover-edit');
    }

    if ($action === 'new_job_cover') {
        $company = trim((string) ($_POST['company'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        if ($company === '') {
            App::flash('Enter a company name first.', 'error');
            App::redirect('/cover');
        }
        $base = Versions::baseCoverLetter();
        if ($base === null) {
            App::flash('No Main cover letter to copy from.', 'error');
            App::redirect('/cover');
        }
        if ($title === '') {
            $title = 'Cover letter — ' . $company;
        }
        $companyLine = $location !== '' ? ($company . ' · ' . $location) : $company;
        $newId = Versions::duplicateCover((int) $base['id'], $title);
        $pdo->prepare('UPDATE cover_letters SET company = ? WHERE id = ? AND user_id = ?')->execute([$companyLine, $newId, Auth::id()]);
        App::flash('Created cover letter #' . $newId . ' (copy of Main' . ($location !== '' ? ', ' . $location : '') . ').');
        App::redirect('/cover-edit');
    }

    if ($action === 'mark_cover_base') {
        $id = (int) ($_POST['id'] ?? 0);
        Versions::markCoverBase($id);
        App::flash('Marked as Main cover letter.');
        App::redirect('/cover');
    }

    if ($action === 'delete_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::deleteCover($id);
            App::flash('Cover letter deleted.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/cover');
    }

    App::flash('Unknown action.', 'error');
    App::redirect('/cover');
}

if (isset($_GET['cover']) && (int) $_GET['cover'] > 0) {
    App::redirect('/cover-edit?cover=' . (int) $_GET['cover']);
}

$letter = App::activeCoverLetter();
$coverLetters = App::coverLetters();

layout_header('Cover letter');
?>
<main class="editor">
  <header class="page-head">
    <h1>Cover letter</h1>
    <p>Pick a letter to edit. Style stays on its own page.</p>
  </header>

  <section class="editor-block" id="letters">
    <h2>My letters</h2>
    <ol class="simple-steps">
      <li><strong>Main</strong> = your normal letter.</li>
      <li>For a job: <strong>Add cover letter</strong>, then edit that copy.</li>
    </ol>

    <?php if (!empty($letter['id'])): ?>
      <div class="now-editing">
        <p>
          Selected:
          <span class="doc-id">#<?= (int) $letter['id'] ?></span>
          <strong><?= App::e((int) ($letter['is_base'] ?? 0) === 1 ? 'Main cover letter' : (string) ($letter['title'] ?? 'Cover letter')) ?></strong>
        </p>
        <a class="btn btn-primary" href="/cover-edit">Edit this letter</a>
      </div>
    <?php endif; ?>

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
                  <?php if ($isOpen): ?><span class="badge-active">Selected</span> <?php endif; ?>
                  <?= App::e($label) ?>
                </strong>
                <?php if (!$isMain && $cl['company'] !== ''): ?>
                  <span class="muted"><?= App::e((string) $cl['company']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="version-list-actions doc-card-actions">
              <?php if ($isOpen): ?>
                <a class="btn btn-sm btn-primary" href="/cover-edit">Edit</a>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="action" value="activate_cover">
                  <input type="hidden" name="id" value="<?= $cid ?>">
                  <button type="submit" class="btn btn-sm btn-primary">Edit / Select</button>
                </form>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHref('cover', 'en', ['id' => $cid])) ?>">PDF EN</a>
              <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHref('cover', 'de', ['id' => $cid])) ?>">PDF DE</a>
              <a class="btn btn-sm btn-outline-secondary" href="/cover-letter?id=<?= $cid ?>" target="_blank" rel="noopener">View</a>
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
  </section>
</main>
<?php
layout_footer();
