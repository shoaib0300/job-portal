<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

Versions::ensureSchema();

if (isset($_GET['cover']) || (isset($_GET['new']) && (string) $_GET['new'] === '1')) {
    $coverQ = isset($_GET['cover']) ? ('?cover=' . (int) $_GET['cover']) : '';
    App::redirect('/cover' . $coverQ);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_resume_version') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $company = trim((string) ($_POST['company'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        $asBase = isset($_POST['as_base']);
        $id = (int) ($_POST['id'] ?? 0);
        if ($asBase) {
            $base = Versions::baseResumeVersion();
            if ($base) {
                $id = (int) $base['id'];
            }
            if ($title === '') {
                $title = Versions::MASTER_CV_LABEL;
            }
        } elseif ($title === '') {
            $title = $company !== '' ? $company . ' resume' : 'Job CV';
        }
        $snapshot = Versions::captureSnapshot();
        $versionId = Versions::saveResumeVersion(
            $title,
            $snapshot,
            $company,
            $note,
            $asBase,
            $id > 0 ? $id : null,
            true
        );
        if ($company !== '') {
            App::setSetting('active_company', $company);
        }
        App::flash($asBase ? 'Master CV saved.' : 'Job CV saved.');
        App::redirect('/editor');
    }

    if ($action === 'load_resume_version') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::loadResumeVersion($id);
            $row = Versions::resumeVersion($id);
            $name = $row ? Versions::resumeDisplayLabel($row) : 'resume';
            App::flash('Now editing: ' . $name);
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            App::redirect('/editor');
        }
        App::redirect('/resume-edit');
    }

    if ($action === 'reset_to_main_resume') {
        $base = Versions::baseResumeVersion();
        if (!$base) {
            App::flash('No Master CV yet.', 'error');
            App::redirect('/editor');
        }
        try {
            Versions::loadResumeVersion((int) $base['id']);
            App::setSetting('active_company', '');
            App::flash('Now editing: ' . Versions::MASTER_CV_LABEL);
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            App::redirect('/editor');
        }
        App::redirect('/resume-edit');
    }

    if ($action === 'delete_resume_version') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::deleteResumeVersion($id);
            App::flash('Job CV deleted.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/editor');
    }

    App::flash('Unknown action.', 'error');
    App::redirect('/editor');
}

$resumeVersions = Versions::resumeVersions();
$master = Versions::baseResumeVersion();
$jobCopies = [];
foreach ($resumeVersions as $ver) {
    if (!Versions::isMasterResume($ver)) {
        $jobCopies[] = $ver;
    }
}
$activeResume = Versions::activeResumeVersion();
$editingResumeName = Versions::MASTER_CV_LABEL;
if ($activeResume) {
    $editingResumeName = Versions::resumeDisplayLabel($activeResume);
} elseif ($master) {
    $editingResumeName = Versions::MASTER_CV_LABEL;
}

/**
 * @param array<string, mixed> $ver
 */
function editor_resume_card(array $ver, ?array $activeResume): void
{
    $rid = (int) $ver['id'];
    $isMaster = Versions::isMasterResume($ver);
    $isOpen = $activeResume !== null && (int) $activeResume['id'] === $rid;
    $label = Versions::resumeDisplayLabel($ver);
    ?>
    <li class="version-list-item doc-card<?= $isOpen ? ' is-open' : '' ?>">
      <div class="doc-card-main">
        <span class="doc-id" title="Resume ID #<?= $rid ?>">#<?= $rid ?></span>
        <div class="doc-card-text">
          <strong>
            <?php if ($isMaster): ?>
              <span class="badge-main"><?= App::e(Versions::MASTER_CV_LABEL) ?></span>
            <?php else: ?>
              <span class="badge-job">Job CV</span>
            <?php endif; ?>
            <?php if ($isOpen): ?><span class="badge-active">Selected</span> <?php endif; ?>
            <?= App::e($label) ?>
          </strong>
          <?php if (!$isMaster && $ver['company'] !== ''): ?>
            <span class="muted"><?= App::e((string) $ver['company']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="version-list-actions doc-card-actions">
        <?php if ($isOpen): ?>
          <a class="btn btn-sm btn-primary" href="/resume-edit">Edit</a>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="action" value="load_resume_version">
            <input type="hidden" name="id" value="<?= $rid ?>">
            <button type="submit" class="btn btn-sm btn-primary">Edit</button>
          </form>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHref('resume', 'en', ['version' => $rid])) ?>">PDF EN</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHref('resume', 'de', ['version' => $rid])) ?>">PDF DE</a>
        <a class="btn btn-sm btn-outline-secondary" href="/resume?version=<?= $rid ?>" target="_blank" rel="noopener">View</a>
        <?php if (!$isMaster): ?>
          <form method="post" onsubmit="return confirm('Delete this Job CV?');">
            <input type="hidden" name="action" value="delete_resume_version">
            <input type="hidden" name="id" value="<?= $rid ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        <?php endif; ?>
      </div>
    </li>
    <?php
}

layout_header('Resume');
?>
<main class="editor">
  <header class="page-head">
    <h1>Resume</h1>
    <p>Your Master CV is the safe template. Each application gets its own Job CV copy.</p>
  </header>

  <section class="editor-block" id="versions">
    <ol class="simple-steps">
      <li><strong>Master CV</strong> = your real CV at home — never overwritten by New job.</li>
      <li>For an application: <a href="/tailor">New job</a> copies Master into a <strong>Job CV</strong>.</li>
      <li>Edit the Job CV summary and skills, then export PDF.</li>
    </ol>

    <div class="now-editing">
      <p>
        Selected: <strong><?= App::e($editingResumeName) ?></strong>
        <?php if ($activeResume): ?>
          <span class="doc-id muted" title="Resume ID">#<?= (int) $activeResume['id'] ?></span>
        <?php endif; ?>
      </p>
      <a class="btn btn-primary" href="/resume-edit">Edit selected</a>
    </div>

    <div class="editor-master-card">
      <h2 class="d-flex align-items-center gap-2"><?= kaammilo_icon('doc', 'sm') ?> Master CV</h2>
      <p class="muted">Your safe template. New jobs always copy from here.</p>
      <?php if ($master === null): ?>
        <p class="empty">No Master CV yet. <a href="/resume-edit">Create your Master CV</a> first.</p>
      <?php else: ?>
        <ul class="version-list doc-card-list">
          <?php editor_resume_card($master, $activeResume); ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="editor-job-list">
      <div class="editor-job-list-head">
        <h2 class="d-flex align-items-center gap-2"><?= kaammilo_icon('track', 'sm') ?> Job CVs</h2>
        <a class="btn btn-primary btn-sm" href="/tailor">New job</a>
      </div>
      <p class="muted">One copy per company or application. Master CV stays unchanged.</p>
      <?php if ($jobCopies === []): ?>
        <p class="empty">No Job CVs yet. <a href="/tailor">New job</a> to create one from your Master CV.</p>
      <?php else: ?>
        <ul class="version-list doc-card-list">
          <?php foreach ($jobCopies as $ver): ?>
            <?php editor_resume_card($ver, $activeResume); ?>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php
layout_footer();
