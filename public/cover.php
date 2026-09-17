<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

Versions::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::requireValid();
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/cover');
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'activate_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::activateCover($id);
            App::flash('Now editing: ' . Versions::coverUiLabel(Versions::coverLetterById($id)));
            App::redirect('/cover-edit?id=' . $id);
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            App::redirect('/cover');
        }
    }

    if ($action === 'duplicate_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        try {
            $newId = \KaamFit\Cover\CoverEditorService::duplicate($id, $title, [
                'copy_content' => isset($_POST['copy_content']),
                'make_active' => true,
            ]);
            App::flash('Cover letter duplicated.');
            App::redirect('/cover-edit?id=' . (int) ($newId['cover_id'] ?? 0));
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            App::redirect('/cover');
        }
    }

    if ($action === 'rename_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        try {
            \KaamFit\Cover\CoverEditorService::rename($id, $title);
            App::flash('Cover letter renamed.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
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

    if ($action === 'new_job_cover') {
        $base = Versions::baseCoverLetter();
        if ($base === null) {
            App::flash('Create a Main Cover Letter first.', 'error');
            App::redirect('/cover');
        }
        $company = trim((string) ($_POST['company'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $name = trim((string) ($_POST['title'] ?? ''));
        $title = $name !== '' ? $name : ($company !== '' ? $company . ' cover letter' : 'Job cover letter');
        try {
            $newId = Versions::duplicateCover((int) $base['id'], $title);
            $companyLine = $company;
            if ($location !== '') {
                $companyLine = $company !== '' ? ($company . ' · ' . $location) : $location;
            }
            if ($companyLine !== '') {
                Db::pdo()->prepare(
                    'UPDATE cover_letters SET company = ? WHERE id = ? AND user_id = ?'
                )->execute([$companyLine, $newId, Auth::id()]);
            }
            App::flash('Job cover letter created.');
            App::redirect('/cover-edit?id=' . $newId);
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            App::redirect('/cover');
        }
    }
}

$letters = App::coverLetters();
$theme = \KaamFit\Resume\ResumeLayout::resolveTemplate(null);
$docLang = App::resolveDocumentLang();

layout_header('My Cover Letters', ['body_class' => 'page-my-covers', 'chrome' => 'cover']);
?>
<main class="page-wide my-resumes">
  <header class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <h1>My Cover Letters</h1>
      <p class="mb-0 text-secondary">Main Cover Letter stays stable. Job copies are tailored for applications.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#newCoverModal">New job letter</button>
      <a class="btn btn-outline-secondary" href="/tailor">Tailor for a job</a>
      <a class="btn btn-primary" href="/cover-edit">Open editor</a>
    </div>
  </header>

  <section class="my-resumes-list">
    <?php if (!$letters): ?>
      <p class="text-secondary mb-0">No cover letters yet.</p>
    <?php else: ?>
      <?php foreach ($letters as $letter): ?>
        <?php
        $cid = (int) $letter['id'];
        $isMain = Versions::isMasterCover($letter);
        $isOpen = (int) ($letter['is_active'] ?? 0) === 1;
        $label = Versions::coverUiLabel($letter);
        $updated = (string) ($letter['updated_at'] ?? '');
        $updatedLabel = $updated !== '' ? date('j M Y', strtotime($updated)) : '';
        $company = trim((string) ($letter['company'] ?? ''));
        ?>
        <article class="my-resume-card<?= $isMain ? ' is-main' : '' ?><?= $isOpen ? ' is-open' : '' ?>">
          <div class="my-resume-card__main">
            <div class="my-resume-card__badges">
              <?php if ($isMain): ?><span class="badge badge-main">MAIN</span><?php endif; ?>
              <?php if (!$isMain): ?><span class="badge text-bg-light border">Job</span><?php endif; ?>
              <?php if ($isOpen): ?><span class="badge text-bg-primary">Editing</span><?php endif; ?>
              <span class="doc-id text-secondary">#<?= $cid ?></span>
              <span class="badge text-bg-light border text-uppercase"><?= App::e($docLang) ?></span>
            </div>
            <h2 class="my-resume-card__title"><?= App::e($label) ?></h2>
            <?php if ($company !== ''): ?>
              <p class="my-resume-card__meta text-secondary mb-0"><?= App::e($company) ?></p>
            <?php endif; ?>
            <p class="my-resume-card__updated text-secondary small mb-0">
              Template: <?= App::e($theme) ?>
              <?php if ($updatedLabel !== ''): ?> · Last updated: <?= App::e($updatedLabel) ?><?php endif; ?>
            </p>
          </div>
          <div class="my-resume-card__actions">
            <?php if ($isOpen): ?>
              <a class="btn btn-sm btn-primary" href="/cover-edit?id=<?= $cid ?>">Edit</a>
            <?php else: ?>
              <form method="post" action="/cover" class="d-inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="activate_cover">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <button type="submit" class="btn btn-sm btn-primary">Edit</button>
              </form>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="/cover-letter?id=<?= $cid ?>" target="_blank" rel="noopener">Preview</a>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#dupCover<?= $cid ?>">Duplicate</button>
            <?php if (!$isMain): ?>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#renameCover<?= $cid ?>">Rename</button>
            <?php endif; ?>
            <?php layout_pdf_buttons('cover', ['id' => $cid]); ?>
            <a class="btn btn-sm btn-outline-secondary" href="/tailor">Tailor for Job</a>
            <?php if (!$isMain): ?>
              <form method="post" action="/cover" class="d-inline" onsubmit="return confirm('Delete this job cover letter? Main is never deleted this way.');">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete_cover">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            <?php endif; ?>
          </div>
        </article>

        <div class="modal fade" id="dupCover<?= $cid ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <form method="post" action="/cover" class="modal-content">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="duplicate_cover">
              <input type="hidden" name="id" value="<?= $cid ?>">
              <div class="modal-header">
                <h3 class="modal-title fs-5">Duplicate Cover Letter</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <label class="form-label" for="dupCoverTitle<?= $cid ?>">Name</label>
                <input class="form-control" id="dupCoverTitle<?= $cid ?>" name="title" value="<?= App::e($label . ' (copy)') ?>" required>
                <div class="form-check mt-3">
                  <input class="form-check-input" type="checkbox" name="copy_content" id="dupCoverContent<?= $cid ?>" checked>
                  <label class="form-check-label" for="dupCoverContent<?= $cid ?>">Copy content</label>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create copy</button>
              </div>
            </form>
          </div>
        </div>

        <?php if (!$isMain): ?>
          <div class="modal fade" id="renameCover<?= $cid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <form method="post" action="/cover" class="modal-content">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="rename_cover">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <div class="modal-header">
                  <h3 class="modal-title fs-5">Rename</h3>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <label class="form-label" for="renameCoverTitle<?= $cid ?>">Name</label>
                  <input class="form-control" id="renameCoverTitle<?= $cid ?>" name="title" value="<?= App::e((string) $letter['title']) ?>" required>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Save</button>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>

<div class="modal fade" id="newCoverModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="/cover" class="modal-content">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="new_job_cover">
      <div class="modal-header">
        <h3 class="modal-title fs-5">New job cover letter</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-secondary small">Copies Main Cover Letter into a job-specific version.</p>
        <label class="form-label" for="newCoverCompany">Company</label>
        <input class="form-control mb-2" id="newCoverCompany" name="company">
        <label class="form-label" for="newCoverLocation">Location</label>
        <input class="form-control mb-2" id="newCoverLocation" name="location" placeholder="e.g. Berlin, Germany">
        <label class="form-label" for="newCoverTitle">Name (optional)</label>
        <input class="form-control" id="newCoverTitle" name="title" placeholder="Defaults from company">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create</button>
      </div>
    </form>
  </div>
</div>
<?php
layout_footer();
