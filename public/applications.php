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
        $allowed = ['applied', 'rejected', 'interview', 'offer', 'custom'];
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
        App::redirect('/applications.php');
    }

    if ($postAction === 'delete') {
        $delId = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM applications WHERE id = ? AND user_id = ?');
        $stmt->execute([$delId, Auth::id()]);
        App::flash('Application deleted.');
        App::redirect('/applications.php');
    }

    App::redirect('/applications.php');
}

$statuses = ['applied', 'rejected', 'interview', 'offer', 'custom'];

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
    <main class="page-narrow">
      <header class="page-head">
        <h1><?= $row['id'] ? 'Edit application' : 'Add entry' ?></h1>
        <p><a href="/applications.php">&larr; All applications</a></p>
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
              <div class="col-12">
                <p class="text-secondary small mb-0">Linked resume <a href="/resume.php?version=<?= (int) $row['resume_version_id'] ?>">#<?= (int) $row['resume_version_id'] ?></a>
                  · <a href="/pdf.php?doc=resume&amp;version=<?= (int) $row['resume_version_id'] ?>">PDF</a></p>
              </div>
            <?php endif; ?>
            <?php if ((int) ($row['cover_letter_id'] ?? 0) > 0): ?>
              <div class="col-12">
                <p class="text-secondary small mb-0">Linked cover <a href="/cover-letter.php?id=<?= (int) $row['cover_letter_id'] ?>">#<?= (int) $row['cover_letter_id'] ?></a>
                  · <a href="/pdf.php?doc=cover&amp;id=<?= (int) $row['cover_letter_id'] ?>">PDF</a></p>
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
$allowed = ['all', 'applied', 'rejected', 'interview', 'offer', 'custom'];
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
      <p>Company, location, and linked resume. <a href="/history.php">History</a></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="/tailor.php">New job</a>
      <a class="btn btn-outline-secondary" href="/applications.php?action=new">Add manually</a>
    </div>
  </header>

  <form class="row g-2 align-items-end mb-3" method="get" action="/applications.php">
    <div class="col-md">
      <label class="form-label" for="q">Search</label>
      <input class="form-control" type="search" id="q" name="q" value="<?= App::e($q) ?>" placeholder="Company, role, location…">
    </div>
    <input type="hidden" name="status" value="<?= App::e($status) ?>">
    <div class="col-auto">
      <button type="submit" class="btn btn-outline-secondary">Search</button>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php
    $chips = ['all' => 'All', 'applied' => 'Applied', 'interview' => 'Interview', 'offer' => 'Offer', 'rejected' => 'Rejected', 'custom' => 'Custom'];
    foreach ($chips as $key => $label):
        $href = '/applications.php?status=' . urlencode($key) . ($q !== '' ? '&q=' . urlencode($q) : '');
    ?>
      <a class="chip<?= $status === $key ? ' is-active' : '' ?>" href="<?= App::e($href) ?>">
        <?= App::e($label) ?> (<?= (int) ($counts[$key] ?? 0) ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$apps): ?>
    <div class="card shadow-sm"><div class="card-body text-secondary">Nothing in this filter. <a href="/tailor.php">Paste a job</a>.</div></div>
  <?php else: ?>
    <div class="table-responsive card shadow-sm">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Company</th>
            <th>Role</th>
            <th>Location</th>
            <th>Docs</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($apps as $app): ?>
            <?php
            $appId = (int) $app['id'];
            $jd = trim((string) ($app['jd_snippet'] ?? ''));
            $notes = trim((string) ($app['notes'] ?? ''));
            $link = trim((string) ($app['link'] ?? ''));
            $hasJd = $jd !== '';
            $rid = (int) ($app['resume_version_id'] ?? 0);
            $cid = (int) ($app['cover_letter_id'] ?? 0);
            $badge = match ($app['status']) {
                'rejected' => 'text-bg-danger',
                'interview' => 'text-bg-info',
                'offer' => 'text-bg-success',
                'custom' => 'text-bg-secondary',
                default => 'text-bg-primary',
            };
            ?>
            <tr>
              <td><?= App::e($app['company']) ?></td>
              <td><?= App::e($app['role']) ?></td>
              <td><?= App::e((string) ($app['location'] ?? '')) ?></td>
              <td class="small">
                <?php if ($rid > 0): ?>
                  <a href="/resume.php?version=<?= $rid ?>">Resume #<?= $rid ?></a>
                <?php endif; ?>
                <?php if ($cid > 0): ?>
                  <a href="/cover-letter.php?id=<?= $cid ?>">Letter #<?= $cid ?></a>
                <?php endif; ?>
                <?php if ($rid === 0 && $cid === 0): ?>
                  <span class="text-secondary">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $badge ?>"><?= App::e(App::statusLabel($app['status'])) ?></span></td>
              <td><?= App::e((string) $app['applied_date']) ?></td>
              <td class="text-nowrap">
                <?php if ($hasJd): ?>
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary"
                          data-toggle-jd
                          data-jd-target="jd-<?= $appId ?>"
                          aria-expanded="false"
                          aria-controls="jd-<?= $appId ?>">Show job</button>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="/applications.php?action=edit&amp;id=<?= $appId ?>">Edit</a>
              </td>
            </tr>
            <tr id="jd-<?= $appId ?>" class="app-jd-row" hidden>
              <td colspan="7">
                <div class="p-2">
                  <div class="d-flex justify-content-between gap-2 mb-2">
                    <strong>Job text</strong>
                    <?php if ($link !== ''): ?>
                      <a href="<?= App::e($link) ?>" target="_blank" rel="noopener">Open link</a>
                    <?php endif; ?>
                  </div>
                  <?php if ($hasJd): ?>
                    <div class="small"><?= App::nl2p($jd) ?></div>
                  <?php endif; ?>
                  <?php if ($notes !== ''): ?>
                    <p class="mb-0"><strong>Notes:</strong> <?= App::e($notes) ?></p>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
<?php
layout_footer();
