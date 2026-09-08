<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$pdo = Db::pdo();
App::ensureDashboardSchema();
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $status = (string) ($_POST['status'] ?? 'applied');
        $allowed = App::applicationStatuses();
        if (!in_array($status, $allowed, true)) {
            $status = 'applied';
        }
        $editId = (int) ($_POST['id'] ?? 0);
        $date = trim((string) ($_POST['applied_date'] ?? ''));
        $dateVal = $date !== '' ? $date : null;
        $resumeId = (int) ($_POST['resume_version_id'] ?? 0);
        $coverId = (int) ($_POST['cover_letter_id'] ?? 0);

        if ($editId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE applications SET company = ?, role = ?, location = ?, status = ?, applied_date = ?, notes = ?, jd_snippet = ?, link = ?, resume_version_id = ?, cover_letter_id = ? WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([
                trim((string) ($_POST['company'] ?? '')),
                trim((string) ($_POST['role'] ?? '')),
                trim((string) ($_POST['location'] ?? '')),
                $status,
                $dateVal,
                trim((string) ($_POST['notes'] ?? '')),
                trim((string) ($_POST['jd_snippet'] ?? '')),
                App::normalizeHttpUrl((string) ($_POST['link'] ?? '')),
                $resumeId > 0 ? $resumeId : null,
                $coverId > 0 ? $coverId : null,
                $editId,
                Auth::id(),
            ]);
            App::flash('Application updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO applications (user_id, company, role, location, status, applied_date, notes, jd_snippet, link, resume_version_id, cover_letter_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                Auth::id(),
                trim((string) ($_POST['company'] ?? '')),
                trim((string) ($_POST['role'] ?? '')),
                trim((string) ($_POST['location'] ?? '')),
                $status,
                $dateVal,
                trim((string) ($_POST['notes'] ?? '')),
                trim((string) ($_POST['jd_snippet'] ?? '')),
                App::normalizeHttpUrl((string) ($_POST['link'] ?? '')),
                $resumeId > 0 ? $resumeId : null,
                $coverId > 0 ? $coverId : null,
            ]);
            App::flash('Application created.');
        }
        App::redirect('/applications');
    }

    if ($postAction === 'delete') {
        $delId = (int) ($_POST['id'] ?? 0);
        if ($delId > 0) {
            $check = $pdo->prepare('SELECT status FROM applications WHERE id = ? AND user_id = ? LIMIT 1');
            $check->execute([$delId, Auth::id()]);
            $statusRow = $check->fetch(PDO::FETCH_ASSOC);
            if (is_array($statusRow) && ($statusRow['status'] ?? '') === 'preparing') {
                App::discardPreparingApplication($delId);
            } else {
                $stmt = $pdo->prepare('DELETE FROM applications WHERE id = ? AND user_id = ?');
                $stmt->execute([$delId, Auth::id()]);
            }
        }
        App::flash('Application deleted.');
        $backStatus = (string) ($_POST['return_status'] ?? 'all');
        $backQ = trim((string) ($_POST['return_q'] ?? ''));
        $allowedBack = ['all', 'preparing', 'applied', 'rejected', 'interview', 'offer', 'custom'];
        if (!in_array($backStatus, $allowedBack, true)) {
            $backStatus = 'all';
        }
        $back = '/applications?status=' . rawurlencode($backStatus);
        if ($backQ !== '') {
            $back .= '&q=' . rawurlencode($backQ);
        }
        App::redirect($back);
    }

    App::redirect('/applications');
}

$statuses = App::applicationStatuses();

if ($action === 'new' || $action === 'edit') {
    $row = [
        'id' => 0,
        'company' => '',
        'role' => '',
        'location' => '',
        'status' => 'custom',
        'applied_date' => date('Y-m-d'),
        'notes' => '',
        'jd_snippet' => '',
        'link' => '',
        'resume_version_id' => '',
        'cover_letter_id' => '',
    ];
    if ($action === 'edit' && $id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, Auth::id()]);
        $found = $stmt->fetch();
        if ($found) {
            $row = $found;
        }
    }

    layout_header($row['id'] ? 'Edit application' : 'New application');
    ?>
    <main class="<?= (int) $row['id'] > 0 ? 'page-wide' : 'page-narrow' ?>">
      <header class="page-head">
        <h1><?= $row['id'] ? 'Edit application' : 'Add entry' ?></h1>
        <p><a href="/applications">&larr; All applications</a></p>
      </header>
      <form method="post" class="card shadow-sm">
        <div class="card-body">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="company">Company</label>
              <input class="form-control" type="text" id="company" name="company" required value="<?= App::e($row['company']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="role">Role</label>
              <input class="form-control" type="text" id="role" name="role" required value="<?= App::e($row['role']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="location">Location</label>
              <input class="form-control" type="text" id="location" name="location" value="<?= App::e((string) ($row['location'] ?? '')) ?>" placeholder="Hamburg, Germany">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="status">Status</label>
              <select class="form-select" id="status" name="status">
                <?php foreach ($statuses as $s): ?>
                  <option value="<?= App::e($s) ?>"<?= $row['status'] === $s ? ' selected' : '' ?>><?= App::e(App::statusLabel($s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="applied_date">Applied date</label>
              <input class="form-control" type="date" id="applied_date" name="applied_date" value="<?= App::e((string) $row['applied_date']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="link">Link <span class="text-secondary fw-normal">(optional)</span></label>
              <input class="form-control" type="text" id="link" name="link" inputmode="url" value="<?= App::e((string) $row['link']) ?>" placeholder="https:// or leave blank">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="resume_version_id">Resume ID</label>
              <input class="form-control" type="number" id="resume_version_id" name="resume_version_id" min="0" value="<?= App::e((string) ($row['resume_version_id'] ?? '')) ?>" placeholder="From resume copies">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="cover_letter_id">Cover letter ID</label>
              <input class="form-control" type="number" id="cover_letter_id" name="cover_letter_id" min="0" value="<?= App::e((string) ($row['cover_letter_id'] ?? '')) ?>" placeholder="From cover letters">
            </div>
            <?php if ((int) ($row['resume_version_id'] ?? 0) > 0): ?>
              <?php
                $resumePdfExtra = ['version' => (int) $row['resume_version_id']];
                $translateTarget = App::resolveTranslateTargetLang();
              ?>
              <div class="col-12">
                <p class="text-secondary small mb-0">Linked resume <a href="/resume.php?version=<?= (int) $row['resume_version_id'] ?>">#<?= (int) $row['resume_version_id'] ?></a>
                  · <a href="<?= App::e(PdfExport::downloadHrefOriginal('resume', $resumePdfExtra)) ?>">PDF</a>
                  · <a href="<?= App::e(PdfExport::downloadHrefAts('resume', $resumePdfExtra)) ?>">ATS PDF</a>
                  · <a href="<?= App::e(PdfExport::downloadHrefTranslated('resume', $translateTarget, $resumePdfExtra)) ?>">Translate to <?= App::e(TranslateLanguages::label($translateTarget)) ?></a></p>
              </div>
            <?php endif; ?>
            <?php if ((int) ($row['cover_letter_id'] ?? 0) > 0): ?>
              <?php
                $coverPdfExtra = ['id' => (int) $row['cover_letter_id']];
                $translateTarget = App::resolveTranslateTargetLang();
              ?>
              <div class="col-12">
                <p class="text-secondary small mb-0">Linked cover <a href="/cover-letter.php?id=<?= (int) $row['cover_letter_id'] ?>">#<?= (int) $row['cover_letter_id'] ?></a>
                  · <a href="<?= App::e(PdfExport::downloadHrefOriginal('cover', $coverPdfExtra)) ?>">PDF</a>
                  · <a href="<?= App::e(PdfExport::downloadHrefTranslated('cover', $translateTarget, $coverPdfExtra)) ?>">Translate to <?= App::e(TranslateLanguages::label($translateTarget)) ?></a></p>
              </div>
            <?php endif; ?>

            <div class="col-12">
              <label class="form-label" for="jd_snippet">Job text</label>
              <textarea class="form-control" id="jd_snippet" name="jd_snippet" rows="10" placeholder="Paste the job description"><?= App::e((string) $row['jd_snippet']) ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label" for="notes">Notes</label>
              <textarea class="form-control" id="notes" name="notes" rows="3"><?= App::e((string) $row['notes']) ?></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </div>
        </div>
      </form>

      <?php if ((int) $row['id'] > 0): ?>
        <?php
          \KaamFit\UserDocuments::ensureSchema();
          $lang = App::resolveDocumentLang();
          $pkgLang = $lang;
          $attached = \KaamFit\UserDocuments::forApplication((int) $row['id']);
          $library = \KaamFit\UserDocuments::listDocuments(null, '', 'name');
          $precheckedDocIds = array_map(static fn($a): int => (int) $a['document_id'], $attached);
          $resumeId = (int) ($row['resume_version_id'] ?? 0) ?: null;
          $coverId = (int) ($row['cover_letter_id'] ?? 0) ?: null;
          $resumeLabel = $resumeId ? ('Resume #' . $resumeId) : 'Resume';
          $coverLabel = $coverId ? ('Cover #' . $coverId) : 'Cover Letter';
          if ($resumeId) {
              $rst = Db::pdo()->prepare('SELECT title, company FROM resume_versions WHERE id = ? AND user_id = ?');
              $rst->execute([$resumeId, Auth::id()]);
              $rr = $rst->fetch(PDO::FETCH_ASSOC);
              if ($rr) {
                  $resumeLabel = trim((string) ($rr['title'] ?: $rr['company'] ?: $resumeLabel));
              }
          }
          if ($coverId) {
              $cst = Db::pdo()->prepare('SELECT title, company FROM cover_letters WHERE id = ? AND user_id = ?');
              $cst->execute([$coverId, Auth::id()]);
              $cr = $cst->fetch(PDO::FETCH_ASSOC);
              if ($cr) {
                  $coverLabel = trim((string) ($cr['title'] ?: $cr['company'] ?: $coverLabel));
              }
          }
          $applicationId = (int) $row['id'];
          $company = (string) ($row['company'] ?? '');
          $defaultResumeChecked = $resumeId !== null;
          $defaultCoverChecked = $coverId !== null;
          $returnPkg = '/applications?action=edit&id=' . $applicationId . '#package';
        ?>
        <div class="card shadow-sm app-docs-shell mt-4" id="package">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3">
              <div>
                <h2 class="h5 mb-1"><?= App::e(\KaamFit\UserDocuments::ui('package', $pkgLang)) ?></h2>
                <p class="small text-secondary mb-0">
                  <?= App::e(trim((string) ($row['role'] ?? '') . (($row['company'] ?? '') !== '' ? ' — ' . $row['company'] : ''))) ?>
                  · <?= App::e(\KaamFit\UserDocuments::ui('select_hint', $pkgLang)) ?>
                </p>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#appDocUploadModal">
                  <?= App::e(\KaamFit\UserDocuments::ui('add', $pkgLang)) ?>
                </button>
                <?php if ($resumeId): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHrefAts('resume', ['version' => $resumeId])) ?>">ATS Resume</a>
                <?php endif; ?>
              </div>
            </div>
            <?php require dirname(__DIR__) . '/src/Views/application_documents_picker.php'; ?>
            <p class="small text-secondary mt-3 mb-0">ATS Resume stays separate (resume only). Generate PDF merges your selection into one file.</p>
          </div>
        </div>

        <div class="modal fade" id="appDocUploadModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <form class="modal-content" method="post" enctype="multipart/form-data" action="/app-docs">
              <input type="hidden" name="action" value="upload">
              <input type="hidden" name="return_to" value="<?= App::e($returnPkg) ?>">
              <div class="modal-header">
                <h2 class="modal-title h5"><?= App::e(\KaamFit\UserDocuments::ui('upload', $pkgLang)) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <label class="form-label">Document name
                  <input class="form-control" type="text" name="name" required placeholder="e.g. Bachelor Degree">
                </label>
                <label class="form-label">Document type
                  <select class="form-select" name="doc_type">
                    <?php foreach (\KaamFit\UserDocuments::TYPES as $key => $_): ?>
                      <option value="<?= App::e($key) ?>"><?= App::e(\KaamFit\UserDocuments::typeLabel($key, $pkgLang)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="form-label">PDF file
                  <input class="form-control" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                </label>
                <label class="form-label">Description <span class="text-secondary">(optional)</span>
                  <textarea class="form-control" name="description" rows="2"></textarea>
                </label>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= App::e(\KaamFit\UserDocuments::ui('cancel', $pkgLang)) ?></button>
                <button type="submit" class="btn btn-primary"><?= App::e(\KaamFit\UserDocuments::ui('upload', $pkgLang)) ?></button>
              </div>
            </form>
          </div>
        </div>
        <div class="modal fade" id="appDocReplaceModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <form class="modal-content" method="post" enctype="multipart/form-data" action="/app-docs">
              <input type="hidden" name="action" value="replace">
              <input type="hidden" name="return_to" value="<?= App::e($returnPkg) ?>">
              <input type="hidden" name="document_id" id="replaceDocId" value="">
              <input type="hidden" name="name" id="replaceDocName" value="">
              <input type="hidden" name="doc_type" id="replaceDocType" value="">
              <div class="modal-header">
                <h2 class="modal-title h5"><?= App::e(\KaamFit\UserDocuments::ui('replace', $pkgLang)) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p class="mb-2" id="replaceDocTitle"></p>
                <label class="form-label">Choose new PDF
                  <input class="form-control" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                </label>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= App::e(\KaamFit\UserDocuments::ui('cancel', $pkgLang)) ?></button>
                <button type="submit" class="btn btn-primary"><?= App::e(\KaamFit\UserDocuments::ui('replace', $pkgLang)) ?></button>
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
      <?php endif; ?>

      <?php if ((int) $row['id'] > 0): ?>
      <form method="post" class="mt-3" onsubmit="return confirm('Delete this application?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <button type="submit" class="btn btn-outline-danger">Delete</button>
      </form>
      <?php endif; ?>
    </main>
    <?php
    layout_footer();
    exit;
}

$status = $_GET['status'] ?? 'all';
$allowed = ['all', 'preparing', 'applied', 'rejected', 'interview', 'offer', 'custom'];
if (!in_array($status, $allowed, true)) {
    $status = 'all';
}
$q = trim((string) ($_GET['q'] ?? ''));
$apps = App::applications($status === 'all' ? null : $status, $q);
$counts = App::applicationCounts();

layout_header('Applications');
?>
<main class="page-wide">
  <header class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <h1>Applications</h1>
      <p>Company, location, and linked resume. <a href="/history">History</a></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="/tailor">New job</a>
      <a class="btn btn-outline-secondary" href="/applications?action=new">Add manually</a>
    </div>
  </header>

  <form class="row g-2 align-items-end mb-3" method="get" action="/applications">
    <div class="col-md">
      <label class="form-label" for="q">Search</label>
      <input class="form-control" type="search" id="q" name="q" value="<?= App::e($q) ?>" placeholder="Company, role, location…">
    </div>
    <input type="hidden" name="status" value="<?= App::e($status) ?>">
    <div class="col-auto">
      <button type="submit" class="btn btn-outline-secondary">Search</button>
    </div>
  </form>

  <div class="app-status-cards mb-4">
    <?php
    $statusFilters = [
        'all' => ['label' => 'All', 'icon' => 'track'],
        'preparing' => ['label' => 'Preparing', 'icon' => 'edit'],
        'applied' => ['label' => 'Applied', 'icon' => 'applied'],
        'interview' => ['label' => 'Interview', 'icon' => 'interview'],
        'offer' => ['label' => 'Offer', 'icon' => 'offer'],
        'rejected' => ['label' => 'Rejected', 'icon' => 'rejected'],
        'custom' => ['label' => 'Custom', 'icon' => 'spark'],
    ];
    foreach ($statusFilters as $key => $meta):
        $href = '/applications?status=' . urlencode($key) . ($q !== '' ? '&q=' . urlencode($q) : '');
        $count = (int) ($counts[$key] ?? 0);
    ?>
      <a class="app-status-card app-status-card--<?= App::e($key) ?><?= $status === $key ? ' is-active' : '' ?>" href="<?= App::e($href) ?>">
        <span class="app-status-card-icon"><?= kaamfit_icon($meta['icon'], 'sm') ?></span>
        <span class="app-status-card-count"><?= $count ?></span>
        <span class="app-status-card-label"><?= App::e($meta['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$apps): ?>
    <div class="card shadow-sm"><div class="card-body text-secondary">Nothing in this filter. <a href="/tailor">Paste a job</a>.</div></div>
  <?php else: ?>
    <ul class="applications-list list-unstyled mb-0">
          <?php foreach ($apps as $app): ?>
            <?php
            $appId = (int) $app['id'];
            $jd = trim((string) ($app['jd_snippet'] ?? ''));
            $notes = trim((string) ($app['notes'] ?? ''));
            $link = trim((string) ($app['link'] ?? ''));
            $hasJd = $jd !== '';
            $rid = (int) ($app['resume_version_id'] ?? 0);
            $cid = (int) ($app['cover_letter_id'] ?? 0);
            $badge = App::applicationStatusBadgeClass((string) $app['status']);
            ?>
      <li class="application-item">
        <article class="application-card card shadow-sm">
          <div class="application-card-main">
            <div class="application-head">
              <h2 class="application-company h6 mb-0"><?= App::e($app['company']) ?></h2>
              <span class="badge <?= $badge ?>"><?= App::e(App::statusLabel($app['status'])) ?></span>
            </div>
            <p class="application-role mb-1"><?= App::e($app['role']) ?></p>
            <p class="application-meta small text-secondary mb-0">
              <?php if ((string) ($app['location'] ?? '') !== ''): ?>
                <span><?= App::e((string) $app['location']) ?></span>
              <?php endif; ?>
              <?php if ((string) ($app['applied_date'] ?? '') !== ''): ?>
                <?php if ((string) ($app['location'] ?? '') !== ''): ?>
                  <span class="application-meta-sep" aria-hidden="true">·</span>
                <?php endif; ?>
                <time datetime="<?= App::e((string) $app['applied_date']) ?>"><?= App::e((string) $app['applied_date']) ?></time>
              <?php elseif ((string) ($app['status'] ?? '') === 'preparing'): ?>
                <?php if ((string) ($app['location'] ?? '') !== ''): ?>
                  <span class="application-meta-sep" aria-hidden="true">·</span>
                <?php endif; ?>
                <span>Not applied yet</span>
              <?php endif; ?>
            </p>
          </div>

          <?php
            \KaamFit\UserDocuments::ensureSchema();
            $attCount = 0;
            try {
                $attCount = count(\KaamFit\UserDocuments::forApplication($appId));
            } catch (Throwable) {
                $attCount = 0;
            }
          ?>
          <?php if ($rid > 0 || $cid > 0 || $attCount > 0): ?>
            <div class="application-docs" aria-label="Job documents">
              <?php if ($rid > 0): ?>
                <a class="application-doc" href="/resume?version=<?= $rid ?>" title="View job CV">CV</a>
                <a class="application-doc application-doc-ats" href="<?= App::e(PdfExport::downloadHrefAts('resume', ['version' => $rid])) ?>" title="Upload to employer portal (SAP, Workday)">ATS CV</a>
              <?php endif; ?>
              <?php if ($cid > 0): ?>
                <a class="application-doc" href="/cover-letter?id=<?= $cid ?>" title="View job cover letter">Cover</a>
                <a class="application-doc application-doc-ats" href="<?= App::e(PdfExport::downloadHrefAts('cover', ['id' => $cid])) ?>" title="Upload to employer portal">ATS Cover</a>
              <?php endif; ?>
              <?php if ($attCount > 0): ?>
                <a class="application-doc" href="/applications?action=edit&amp;id=<?= $appId ?>#package" title="Supporting documents">Docs <?= (int) $attCount ?></a>
              <?php endif; ?>
              <a class="application-doc" href="/applications?action=edit&amp;id=<?= $appId ?>#package" title="Application package">Package</a>
            </div>
          <?php endif; ?>

          <div class="application-actions">
            <?php if ($hasJd): ?>
              <button type="button"
                      class="btn btn-sm btn-outline-secondary"
                      data-toggle-jd
                      data-jd-target="jd-<?= $appId ?>"
                      aria-expanded="false"
                      aria-controls="jd-<?= $appId ?>">Job</button>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="/applications?action=edit&amp;id=<?= $appId ?>">Edit</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this application?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $appId ?>">
              <input type="hidden" name="return_status" value="<?= App::e($status) ?>">
              <input type="hidden" name="return_q" value="<?= App::e($q) ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete <?= App::e($app['company']) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                  <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                  <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                </svg>
              </button>
            </form>
          </div>
        </article>

        <div id="jd-<?= $appId ?>" class="application-jd card shadow-sm" hidden>
          <div class="card-body py-3">
            <div class="d-flex justify-content-between gap-2 mb-2">
              <strong class="small text-uppercase text-secondary">Job text</strong>
              <?php if ($link !== ''): ?>
                <a class="small" href="<?= App::e($link) ?>" target="_blank" rel="noopener">Open posting</a>
              <?php endif; ?>
            </div>
            <?php if ($hasJd): ?>
              <div class="small application-jd-body"><?= App::nl2p($jd) ?></div>
            <?php endif; ?>
            <?php if ($notes !== ''): ?>
              <p class="small mb-0 mt-2"><strong>Notes:</strong> <?= App::e($notes) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </li>
          <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
<?php
layout_footer();
