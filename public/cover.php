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
            App::flash('No Master cover letter to copy from.', 'error');
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
        App::flash('Marked as Master cover letter.');
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
$master = Versions::baseCoverLetter();
$jobLetters = [];
foreach ($coverLetters as $cl) {
    if (!Versions::isMasterCover($cl)) {
        $jobLetters[] = $cl;
    }
}
$editingCoverName = Versions::MASTER_COVER_LABEL;
if ($letter) {
    $editingCoverName = Versions::coverDisplayLabel($letter);
} elseif ($master) {
    $editingCoverName = Versions::MASTER_COVER_LABEL;
}

/**
 * @param array<string, mixed> $cl
 */
function cover_letter_card(array $cl, ?array $activeLetter): void
{
    $cid = (int) $cl['id'];
    $isMaster = Versions::isMasterCover($cl);
    $isOpen = $activeLetter !== null && (int) $activeLetter['id'] === $cid;
    $label = Versions::coverDisplayLabel($cl);
    ?>
    <li class="version-list-item doc-card<?= $isOpen ? ' is-open' : '' ?>">
      <div class="doc-card-main">
        <span class="doc-id" title="Cover letter ID #<?= $cid ?>">#<?= $cid ?></span>
        <div class="doc-card-text">
          <strong>
            <?php if ($isMaster): ?>
              <span class="badge-main"><?= App::e(Versions::MASTER_COVER_LABEL) ?></span>
            <?php else: ?>
              <span class="badge-job">Job letter</span>
            <?php endif; ?>
            <?php if ($isOpen): ?><span class="badge-active">Selected</span> <?php endif; ?>
            <?= App::e($label) ?>
          </strong>
          <?php if (!$isMaster && $cl['company'] !== ''): ?>
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
            <button type="submit" class="btn btn-sm btn-primary">Edit</button>
          </form>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHref('cover', 'en', ['id' => $cid])) ?>">PDF EN</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHref('cover', 'de', ['id' => $cid])) ?>">PDF DE</a>
        <a class="btn btn-sm btn-outline-secondary" href="/cover-letter?id=<?= $cid ?>" target="_blank" rel="noopener">View</a>
        <?php if (!$isMaster): ?>
          <form method="post" onsubmit="return confirm('Delete cover letter #<?= $cid ?>?');">
            <input type="hidden" name="action" value="delete_cover">
            <input type="hidden" name="id" value="<?= $cid ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        <?php endif; ?>
      </div>
    </li>
    <?php
}

layout_header('Cover letter');
?>
<main class="editor">
  <header class="page-head">
    <h1>Cover letter</h1>
    <p>Your Master cover letter is the safe template. Each application gets its own copy.</p>
  </header>

  <section class="editor-block" id="letters">
    <?php onboarding_render_banner('cover'); ?>
    <ol class="simple-steps">
      <li><strong>Master cover letter</strong> = your normal letter — never overwritten by New job.</li>
      <li>For an application: <a href="/tailor">New job</a> copies Master into a <strong>job letter</strong>.</li>
      <li>Edit the job letter body, then export PDF. Style stays on <a href="/cover-design">Cover style</a>.</li>
    </ol>

    <div class="now-editing">
      <p>
        Selected: <strong><?= App::e($editingCoverName) ?></strong>
        <?php if ($letter): ?>
          <span class="doc-id muted" title="Cover letter ID">#<?= (int) $letter['id'] ?></span>
        <?php endif; ?>
      </p>
      <a class="btn btn-primary" href="/cover-edit">Edit selected</a>
    </div>

    <div class="editor-master-card">
      <h2 class="d-flex align-items-center gap-2"><?= kaammilo_icon('letter', 'sm') ?> Master cover letter</h2>
      <p class="muted">Your safe template. New jobs always copy from here.</p>
      <?php if ($master === null): ?>
        <p class="empty">No Master cover letter yet. <a href="/cover-edit">Create your Master cover letter</a> first.</p>
      <?php else: ?>
        <ul class="version-list doc-card-list">
          <?php cover_letter_card($master, $letter); ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="editor-job-list">
      <div class="editor-job-list-head">
        <h2 class="d-flex align-items-center gap-2"><?= kaammilo_icon('track', 'sm') ?> Job cover letters</h2>
        <a class="btn btn-primary btn-sm" href="/tailor">New job</a>
      </div>
      <p class="muted">One copy per company or application. Master cover letter stays unchanged.</p>
      <?php if ($jobLetters === []): ?>
        <p class="empty">No job letters yet. <a href="/tailor">New job</a> or use <a href="#add-cover">Add cover letter</a> below.</p>
      <?php else: ?>
        <ul class="version-list doc-card-list">
          <?php foreach ($jobLetters as $cl): ?>
            <?php cover_letter_card($cl, $letter); ?>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <form method="post" class="form new-job-form" id="add-cover">
      <h3>Add cover letter</h3>
      <p class="empty" style="margin:0 0 0.75rem">Always a copy of your Master cover letter.</p>
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
  </section>
</main>
<?php
layout_footer();
