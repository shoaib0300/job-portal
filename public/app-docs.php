<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\UserDocuments;

UserDocuments::ensureSchema();
$lang = App::resolveDocumentLang();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'upload') {
            UserDocuments::upload(
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['doc_type'] ?? 'other'),
                $_FILES['file'] ?? [],
                (string) ($_POST['description'] ?? ''),
                false,
                null
            );
            App::flash('Document uploaded.');
        }
        if ($action === 'replace') {
            $id = (int) ($_POST['document_id'] ?? 0);
            $existing = UserDocuments::get($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Document not found.');
            }
            UserDocuments::upload(
                (string) ($_POST['name'] ?? $existing['name']),
                (string) ($_POST['doc_type'] ?? $existing['doc_type']),
                $_FILES['file'] ?? [],
                (string) ($_POST['description'] ?? ($existing['description'] ?? '')),
                (int) ($existing['always_include'] ?? 0) === 1,
                $id
            );
            App::flash('Document replaced (new version). Older applications keep the previous file.');
        }
        if ($action === 'delete') {
            UserDocuments::delete((int) ($_POST['document_id'] ?? 0));
            App::flash('Document deleted.');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    $back = (string) ($_POST['return_to'] ?? '/app-docs');
    if ($back === '' || !str_starts_with($back, '/')) {
        $back = '/app-docs';
    }
    App::redirect($back);
}

$library = UserDocuments::listDocuments(null, '', 'name');
$resumeId = UserDocuments::baseResumeId();
$coverId = UserDocuments::baseCoverId();
$resumeLabel = 'Master CV';
$coverLabel = 'Main cover letter';
if ($resumeId) {
    $st = Db::pdo()->prepare('SELECT title FROM resume_versions WHERE id = ? AND user_id = ?');
    $st->execute([$resumeId, Auth::id()]);
    $t = $st->fetchColumn();
    if (is_string($t) && $t !== '') {
        $resumeLabel = $t;
    }
}
if ($coverId) {
    $st = Db::pdo()->prepare('SELECT title FROM cover_letters WHERE id = ? AND user_id = ?');
    $st->execute([$coverId, Auth::id()]);
    $t = $st->fetchColumn();
    if (is_string($t) && $t !== '') {
        $coverLabel = $t;
    }
}

$applicationId = 0;
$company = '';
$precheckedDocIds = [];
$defaultResumeChecked = true;
$defaultCoverChecked = true;

layout_header(UserDocuments::ui('library', $lang));
?>
<main class="page-wide app-docs-page">
  <header class="page-head d-flex flex-wrap justify-content-between gap-2 align-items-start">
    <div>
      <h1><?= App::e(UserDocuments::ui('library', $lang)) ?></h1>
      <p class="text-secondary mb-0"><?= App::e(UserDocuments::ui('select_hint', $lang)) ?></p>
    </div>
    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#appDocUploadModal">
      <?= App::e(UserDocuments::ui('add', $lang)) ?>
    </button>
  </header>

  <div class="card shadow-sm app-docs-shell">
    <div class="card-body">
      <?php require dirname(__DIR__) . '/src/Views/application_documents_picker.php'; ?>
    </div>
  </div>
</main>

<div class="modal fade" id="appDocUploadModal" tabindex="-1" aria-labelledby="appDocUploadLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" enctype="multipart/form-data" action="/app-docs">
      <input type="hidden" name="action" value="upload">
      <div class="modal-header">
        <h2 class="modal-title h5" id="appDocUploadLabel"><?= App::e(UserDocuments::ui('upload', $lang)) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= App::e(UserDocuments::ui('cancel', $lang)) ?>"></button>
      </div>
      <input type="hidden" name="return_to" value="/app-docs">
      <div class="modal-body">
        <label class="form-label">Document name
          <input class="form-control" type="text" name="name" required placeholder="e.g. Bachelor Degree" autocomplete="off">
        </label>
        <label class="form-label">Document type
          <select class="form-select" name="doc_type">
            <?php foreach (UserDocuments::TYPES as $key => $_): ?>
              <option value="<?= App::e($key) ?>"><?= App::e(UserDocuments::typeLabel($key, $lang)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="form-label">PDF file
          <input class="form-control" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
        </label>
        <label class="form-label">Description <span class="text-secondary">(optional)</span>
          <textarea class="form-control" name="description" rows="2" placeholder="Optional"></textarea>
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= App::e(UserDocuments::ui('cancel', $lang)) ?></button>
        <button type="submit" class="btn btn-primary"><?= App::e(UserDocuments::ui('upload', $lang)) ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="appDocReplaceModal" tabindex="-1" aria-labelledby="appDocReplaceLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" enctype="multipart/form-data" action="/app-docs">
      <input type="hidden" name="action" value="replace">
      <input type="hidden" name="return_to" value="/app-docs">
      <input type="hidden" name="document_id" id="replaceDocId" value="">
      <input type="hidden" name="name" id="replaceDocName" value="">
      <input type="hidden" name="doc_type" id="replaceDocType" value="">
      <div class="modal-header">
        <h2 class="modal-title h5" id="appDocReplaceLabel"><?= App::e(UserDocuments::ui('replace', $lang)) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= App::e(UserDocuments::ui('cancel', $lang)) ?>"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2" id="replaceDocTitle"></p>
        <label class="form-label">Choose new PDF
          <input class="form-control" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
        </label>
        <p class="small text-secondary mb-0">Creates a new version. Applications that already used the old file keep it.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= App::e(UserDocuments::ui('cancel', $lang)) ?></button>
        <button type="submit" class="btn btn-primary"><?= App::e(UserDocuments::ui('replace', $lang)) ?></button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/app-docs-picker.js?v=20260907d"></script>
<script>
document.getElementById('appDocReplaceModal')?.addEventListener('show.bs.modal', function (ev) {
  var btn = ev.relatedTarget;
  if (!btn) return;
  document.getElementById('replaceDocId').value = btn.getAttribute('data-doc-id') || '';
  document.getElementById('replaceDocName').value = btn.getAttribute('data-doc-name') || '';
  document.getElementById('replaceDocType').value = btn.getAttribute('data-doc-type') || 'other';
  document.getElementById('replaceDocTitle').textContent = btn.getAttribute('data-doc-name') || '';
});
</script>
<?php
layout_footer();
