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
        App::redirect('/documents');
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'load_resume_version') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::loadResumeVersion($id);
            App::flash('Now editing: ' . Versions::resumeUiLabel(Versions::resumeVersion($id)));
            App::redirect('/resume-edit');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            App::redirect('/documents');
        }
    }

    if ($action === 'duplicate_resume') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        try {
            $newId = Versions::duplicateResume($id, $title, [
                'copy_content' => isset($_POST['copy_content']),
                'copy_section_order' => isset($_POST['copy_section_order']),
                'copy_design' => isset($_POST['copy_design']),
                'make_active' => true,
            ]);
            Versions::loadResumeVersion($newId);
            App::flash('Resume duplicated.');
            App::redirect('/resume-edit');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            App::redirect('/documents');
        }
    }

    if ($action === 'rename_resume') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        try {
            $row = Versions::resumeVersion($id);
            if ($row === null) {
                throw new RuntimeException('Resume not found');
            }
            if (Versions::isMasterResume($row)) {
                throw new RuntimeException('Main Resume title is fixed.');
            }
            if ($title === '') {
                throw new InvalidArgumentException('Title required');
            }
            Db::pdo()->prepare(
                'UPDATE resume_versions SET title = ? WHERE id = ? AND user_id = ?'
            )->execute([$title, $id, Auth::id()]);
            App::flash('Resume renamed.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/documents');
    }

    if ($action === 'delete_resume') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::deleteResumeVersion($id);
            App::flash('Resume deleted.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/documents');
    }
}

$resumes = Versions::resumeVersions();

layout_header('My Resumes', ['body_class' => 'page-my-resumes']);
?>
<main class="page-wide my-resumes">
  <header class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <h1>My Resumes</h1>
      <p class="mb-0 text-secondary">Main Resume stays stable. Job copies are tailored snapshots.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="/tailor">Tailor for a job</a>
      <a class="btn btn-primary" href="/resume-edit">Open editor</a>
    </div>
  </header>

  <section class="my-resumes-list">
    <?php if (!$resumes): ?>
      <p class="text-secondary mb-0">No resumes yet.</p>
    <?php else: ?>
      <?php foreach ($resumes as $ver): ?>
        <?php
        $rid = (int) $ver['id'];
        $isMain = Versions::isMasterResume($ver);
        $isOpen = (int) $ver['is_active'] === 1;
        $label = Versions::resumeUiLabel($ver);
        $updated = (string) ($ver['updated_at'] ?? '');
        $updatedLabel = $updated !== '' ? date('j M Y', strtotime($updated)) : '';
        ?>
        <article class="my-resume-card<?= $isMain ? ' is-main' : '' ?><?= $isOpen ? ' is-open' : '' ?>">
          <div class="my-resume-card__main">
            <div class="my-resume-card__badges">
              <?php if ($isMain): ?><span class="badge badge-main">MAIN</span><?php endif; ?>
              <?php if ($isOpen): ?><span class="badge text-bg-primary">Editing</span><?php endif; ?>
              <span class="doc-id text-secondary">#<?= $rid ?></span>
            </div>
            <h2 class="my-resume-card__title"><?= App::e($label) ?></h2>
            <?php if (!$isMain && ($ver['company'] ?? '') !== ''): ?>
              <p class="my-resume-card__meta text-secondary mb-0"><?= App::e((string) $ver['company']) ?></p>
            <?php endif; ?>
            <?php if ($updatedLabel !== ''): ?>
              <p class="my-resume-card__updated text-secondary small mb-0">Last updated: <?= App::e($updatedLabel) ?></p>
            <?php endif; ?>
          </div>
          <div class="my-resume-card__actions">
            <?php if ($isOpen): ?>
              <a class="btn btn-sm btn-primary" href="/resume-edit">Edit</a>
            <?php else: ?>
              <form method="post" action="/documents" class="d-inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="load_resume_version">
                <input type="hidden" name="id" value="<?= $rid ?>">
                <button type="submit" class="btn btn-sm btn-primary">Edit</button>
              </form>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#dupModal<?= $rid ?>">Duplicate</button>
            <?php if (!$isMain): ?>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#renameModal<?= $rid ?>">Rename</button>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="/resume?version=<?= $rid ?>" target="_blank" rel="noopener">View</a>
            <?php layout_pdf_buttons('resume', ['version' => $rid]); ?>
            <?php if (!$isMain): ?>
              <form method="post" action="/documents" class="d-inline" onsubmit="return confirm('Delete this job resume copy? Main Resume is never deleted this way.');">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete_resume">
                <input type="hidden" name="id" value="<?= $rid ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            <?php endif; ?>
          </div>
        </article>

        <div class="modal fade" id="dupModal<?= $rid ?>" tabindex="-1" aria-labelledby="dupLabel<?= $rid ?>" aria-hidden="true">
          <div class="modal-dialog">
            <form method="post" action="/documents" class="modal-content">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="duplicate_resume">
              <input type="hidden" name="id" value="<?= $rid ?>">
              <div class="modal-header">
                <h3 class="modal-title fs-5" id="dupLabel<?= $rid ?>">Duplicate Resume</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <label class="form-label" for="dupTitle<?= $rid ?>">Name</label>
                <input class="form-control" id="dupTitle<?= $rid ?>" name="title" value="<?= App::e($label . ' (copy)') ?>" required>
                <div class="form-check mt-3">
                  <input class="form-check-input" type="checkbox" name="copy_content" id="dupContent<?= $rid ?>" checked>
                  <label class="form-check-label" for="dupContent<?= $rid ?>">Copy content</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="copy_section_order" id="dupOrder<?= $rid ?>" checked>
                  <label class="form-check-label" for="dupOrder<?= $rid ?>">Copy section order</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="copy_design" id="dupDesign<?= $rid ?>" checked>
                  <label class="form-check-label" for="dupDesign<?= $rid ?>">Copy design</label>
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
          <div class="modal fade" id="renameModal<?= $rid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <form method="post" action="/documents" class="modal-content">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="rename_resume">
                <input type="hidden" name="id" value="<?= $rid ?>">
                <div class="modal-header">
                  <h3 class="modal-title fs-5">Rename Resume</h3>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <label class="form-label" for="renameTitle<?= $rid ?>">Name</label>
                  <input class="form-control" id="renameTitle<?= $rid ?>" name="title" value="<?= App::e((string) $ver['title']) ?>" required>
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
<?php
layout_footer();
