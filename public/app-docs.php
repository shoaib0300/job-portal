<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\UserDocuments;

UserDocuments::ensureSchema();
$lang = App::resolveDocumentLang();
$pdo = Db::pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'upload') {
            $id = UserDocuments::upload(
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['doc_type'] ?? 'other'),
                $_FILES['file'] ?? [],
                (string) ($_POST['description'] ?? ''),
                isset($_POST['always_include']),
                null
            );
            App::flash('Document uploaded.');
            App::redirect('/app-docs?view=' . $id);
        }
        if ($action === 'replace') {
            $id = UserDocuments::upload(
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['doc_type'] ?? 'other'),
                $_FILES['file'] ?? [],
                (string) ($_POST['description'] ?? ''),
                isset($_POST['always_include']),
                (int) ($_POST['document_id'] ?? 0)
            );
            App::flash('Document replaced (new version). Existing applications keep the previous file.');
            App::redirect('/app-docs?view=' . $id);
        }
        if ($action === 'save_meta') {
            UserDocuments::updateMeta(
                (int) ($_POST['document_id'] ?? 0),
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['doc_type'] ?? 'other'),
                (string) ($_POST['description'] ?? ''),
                isset($_POST['always_include'])
            );
            App::flash('Document updated.');
            App::redirect('/app-docs?view=' . (int) ($_POST['document_id'] ?? 0));
        }
        if ($action === 'delete') {
            UserDocuments::delete((int) ($_POST['document_id'] ?? 0));
            App::flash('Document deleted.');
            App::redirect('/app-docs');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/app-docs');
    }
    App::redirect('/app-docs');
}

$type = (string) ($_GET['type'] ?? 'all');
$q = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'newest');
$viewId = isset($_GET['view']) ? (int) $_GET['view'] : 0;
$docs = UserDocuments::listDocuments($type === 'all' ? null : $type, $q, $sort);
$view = $viewId > 0 ? UserDocuments::get($viewId) : null;

layout_header(UserDocuments::ui('library', $lang));
?>
<main class="page-wide app-docs-page">
  <header class="page-head d-flex flex-wrap justify-content-between gap-2 align-items-start">
    <div>
      <h1><?= App::e(UserDocuments::ui('library', $lang)) ?></h1>
      <p class="text-secondary mb-0">Upload once, attach to many applications. Versions stay frozen when you apply.</p>
    </div>
    <a class="btn btn-primary" href="#upload"><?= App::e(UserDocuments::ui('upload', $lang)) ?></a>
  </header>

  <form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-md-4">
      <label class="form-label" for="q">Search</label>
      <input class="form-control" type="search" id="q" name="q" value="<?= App::e($q) ?>" placeholder="Search documents…">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="type">Type</label>
      <select class="form-select" id="type" name="type">
        <option value="all"<?= $type === 'all' ? ' selected' : '' ?>>All</option>
        <?php foreach (UserDocuments::TYPES as $key => $_): ?>
          <option value="<?= App::e($key) ?>"<?= $type === $key ? ' selected' : '' ?>><?= App::e(UserDocuments::typeLabel($key, $lang)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="sort">Sort</label>
      <select class="form-select" id="sort" name="sort">
        <option value="newest"<?= $sort === 'newest' ? ' selected' : '' ?>>Newest</option>
        <option value="oldest"<?= $sort === 'oldest' ? ' selected' : '' ?>>Oldest</option>
        <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Name</option>
        <option value="type"<?= $sort === 'type' ? ' selected' : '' ?>>Type</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
    </div>
  </form>

  <div class="row g-3">
    <div class="col-lg-7">
      <?php if ($docs === []): ?>
        <div class="card shadow-sm app-docs-empty">
          <div class="card-body text-center py-5">
            <h2 class="h5"><?= App::e(UserDocuments::ui('empty', $lang)) ?></h2>
            <p class="text-secondary"><?= App::e(UserDocuments::ui('empty_hint', $lang)) ?></p>
            <a class="btn btn-primary" href="#upload"><?= App::e(UserDocuments::ui('upload', $lang)) ?></a>
          </div>
        </div>
      <?php else: ?>
        <div class="app-docs-grid">
          <?php foreach ($docs as $doc): ?>
            <?php
              $did = (int) $doc['id'];
              $isPdf = str_contains((string) ($doc['mime_type'] ?? ''), 'pdf');
            ?>
            <article class="card shadow-sm app-doc-card<?= $viewId === $did ? ' is-active' : '' ?>">
              <div class="card-body">
                <div class="d-flex justify-content-between gap-2">
                  <div>
                    <span class="badge text-bg-light border"><?= App::e(UserDocuments::typeLabel((string) $doc['doc_type'], $lang)) ?></span>
                    <?php if ((int) ($doc['always_include'] ?? 0) === 1): ?>
                      <span class="badge text-bg-success-subtle border">Always</span>
                    <?php endif; ?>
                    <h2 class="h6 mt-2 mb-1"><a class="text-decoration-none" href="/app-docs?view=<?= $did ?>"><?= App::e((string) $doc['name']) ?></a></h2>
                    <p class="small text-secondary mb-2">
                      v<?= (int) ($doc['version_no'] ?? 1) ?>
                      · <?= $isPdf ? 'PDF' : App::e((string) ($doc['mime_type'] ?? '')) ?>
                      · <?= number_format(((int) ($doc['file_size'] ?? 0)) / 1024, 0) ?> KB
                    </p>
                  </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(UserDocuments::downloadUrl($did, true)) ?>" target="_blank" rel="noopener"><?= App::e(UserDocuments::ui('preview', $lang)) ?></a>
                  <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(UserDocuments::downloadUrl($did)) ?>"><?= App::e(UserDocuments::ui('download', $lang)) ?></a>
                  <a class="btn btn-sm btn-outline-primary" href="/app-docs?view=<?= $did ?>">Edit</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-5">
      <?php if ($view): ?>
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h2 class="h5"><?= App::e((string) $view['name']) ?></h2>
            <p class="small text-secondary"><?= App::e(UserDocuments::typeLabel((string) $view['doc_type'], $lang)) ?> · v<?= (int) ($view['version_no'] ?? 1) ?></p>
            <?php if (str_contains((string) ($view['mime_type'] ?? ''), 'pdf')): ?>
              <iframe class="app-doc-preview" title="PDF preview" src="<?= App::e(UserDocuments::downloadUrl((int) $view['id'], true)) ?>"></iframe>
            <?php else: ?>
              <p class="alert alert-warning py-2">Preview works best for PDF. <a href="<?= App::e(UserDocuments::downloadUrl((int) $view['id'])) ?>">Download</a></p>
            <?php endif; ?>
            <form method="post" class="mt-3">
              <input type="hidden" name="action" value="save_meta">
              <input type="hidden" name="document_id" value="<?= (int) $view['id'] ?>">
              <label class="form-label">Name <input class="form-control" name="name" required value="<?= App::e((string) $view['name']) ?>"></label>
              <label class="form-label">Type
                <select class="form-select" name="doc_type">
                  <?php foreach (UserDocuments::TYPES as $key => $_): ?>
                    <option value="<?= App::e($key) ?>"<?= ($view['doc_type'] ?? '') === $key ? ' selected' : '' ?>><?= App::e(UserDocuments::typeLabel($key, $lang)) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="form-label">Description <textarea class="form-control" name="description" rows="2"><?= App::e((string) ($view['description'] ?? '')) ?></textarea></label>
              <label class="check d-block my-2"><input type="checkbox" name="always_include" value="1"<?= (int) ($view['always_include'] ?? 0) === 1 ? ' checked' : '' ?>> <?= App::e(UserDocuments::ui('always', $lang)) ?></label>
              <button class="btn btn-primary" type="submit">Save</button>
            </form>
            <hr>
            <form method="post" enctype="multipart/form-data" class="mt-2">
              <input type="hidden" name="action" value="replace">
              <input type="hidden" name="document_id" value="<?= (int) $view['id'] ?>">
              <input type="hidden" name="name" value="<?= App::e((string) $view['name']) ?>">
              <input type="hidden" name="doc_type" value="<?= App::e((string) $view['doc_type']) ?>">
              <input type="hidden" name="description" value="<?= App::e((string) ($view['description'] ?? '')) ?>">
              <?php if ((int) ($view['always_include'] ?? 0) === 1): ?><input type="hidden" name="always_include" value="1"><?php endif; ?>
              <label class="form-label"><?= App::e(UserDocuments::ui('replace', $lang)) ?> (PDF/JPG/PNG)
                <input class="form-control" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
              </label>
              <button class="btn btn-outline-secondary" type="submit"><?= App::e(UserDocuments::ui('replace', $lang)) ?></button>
            </form>
            <form method="post" class="mt-3" onsubmit="return confirm('Delete this document from the library?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="document_id" value="<?= (int) $view['id'] ?>">
              <button class="btn btn-outline-danger btn-sm" type="submit"><?= App::e(UserDocuments::ui('delete', $lang)) ?></button>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm" id="upload">
        <div class="card-body">
          <h2 class="h5"><?= App::e(UserDocuments::ui('upload', $lang)) ?></h2>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <label class="form-label">Document name
              <input class="form-control" type="text" name="name" required placeholder="e.g. Bachelor Degree">
            </label>
            <label class="form-label">Type
              <select class="form-select" name="doc_type">
                <?php foreach (UserDocuments::TYPES as $key => $_): ?>
                  <option value="<?= App::e($key) ?>"><?= App::e(UserDocuments::typeLabel($key, $lang)) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="form-label">File (PDF preferred)
              <input class="form-control" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
            </label>
            <label class="form-label">Description
              <textarea class="form-control" name="description" rows="2" placeholder="Optional"></textarea>
            </label>
            <label class="check d-block my-2"><input type="checkbox" name="always_include" value="1"> <?= App::e(UserDocuments::ui('always', $lang)) ?></label>
            <button class="btn btn-primary" type="submit"><?= App::e(UserDocuments::ui('upload', $lang)) ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>
<?php
layout_footer();
