<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

Versions::ensureSchema();

if (isset($_GET['cover']) || (isset($_GET['new']) && (string) $_GET['new'] === '1')) {
    $coverQ = isset($_GET['cover']) ? ('?cover=' . (int) $_GET['cover']) : '';
    App::redirect('/cover.php' . $coverQ);
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
                $title = 'Main resume';
            }
        } elseif ($title === '') {
            $title = $company !== '' ? $company . ' resume' : 'Tailored resume';
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
        App::flash($asBase ? 'Main resume saved.' : 'Resume saved.');
        App::redirect('/editor.php');
    }

    if ($action === 'new_job_resume') {
        $company = trim((string) ($_POST['company'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        if ($company === '') {
            App::flash('Enter a company name first.', 'error');
            App::redirect('/editor.php');
        }
        $base = Versions::baseResumeVersion();
        if ($base === null) {
            App::flash('No Main resume to copy from. Save Main first.', 'error');
            App::redirect('/editor.php');
        }
        // Always clone Main (never the currently open tailored copy).
        $snapshot = Versions::decodeSnapshot((string) $base['snapshot']);
        if ($location !== '') {
            $snapshot['location'] = $location;
        }
        $title = $role !== '' ? $role . ' — ' . $company : $company;
        $id = Versions::saveResumeVersion(
            $title,
            $snapshot,
            $company,
            'Copy of Main resume' . ($location !== '' ? ' · ' . $location : ''),
            false,
            null,
            true
        );
        Versions::loadResumeVersion($id);
        App::flash('Created resume #' . $id . ' (copy of Main' . ($location !== '' ? ', ' . $location : '') . '). Change it, then Save.');
        App::redirect('/resume-edit.php');
    }

    if ($action === 'load_resume_version') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::loadResumeVersion($id);
            $row = Versions::resumeVersion($id);
            $name = $row && (int) ($row['is_base'] ?? 0) === 1 ? 'Main resume' : ($row['title'] ?? 'resume');
            App::flash('Now editing: ' . $name);
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            App::redirect('/editor.php');
        }
        App::redirect('/resume-edit.php');
    }

    if ($action === 'reset_to_main_resume') {
        $base = Versions::baseResumeVersion();
        if (!$base) {
            App::flash('No Main resume yet.', 'error');
            App::redirect('/editor.php');
        }
        try {
            Versions::loadResumeVersion((int) $base['id']);
            App::setSetting('active_company', '');
            App::flash('Now editing: Main resume');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
            App::redirect('/editor.php');
        }
        App::redirect('/resume-edit.php');
    }

    if ($action === 'delete_resume_version') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::deleteResumeVersion($id);
            App::flash('Resume deleted.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/editor.php');
    }

    App::flash('Unknown action.', 'error');
    App::redirect('/editor.php');
}

$resumeVersions = Versions::resumeVersions();
$baseResume = Versions::baseResumeVersion();
$activeResume = Versions::activeResumeVersion();
$editingResumeName = 'Main resume';
if ($activeResume) {
    $editingResumeName = (int) ($activeResume['is_base'] ?? 0) === 1
        ? 'Main resume'
        : (string) $activeResume['title'];
} elseif ($baseResume) {
    $editingResumeName = 'Main resume';
}

layout_header('Resume');
?>
<main class="editor">
  <header class="page-head">
    <h1>Resume</h1>
    <p>Pick a copy to edit. Style stays on its own page.</p>
  </header>

  <section class="editor-block" id="versions">
    <h2>My resumes</h2>

    <ol class="simple-steps">
      <li><strong>Main</strong> = your normal CV.</li>
      <li>For a job: <strong>Add resume</strong>, then edit that copy.</li>
      <li>Each resume has a unique ID (shown below).</li>
    </ol>

    <div class="now-editing">
      <p>
        Selected:
        <?php if ($activeResume): ?>
          <span class="doc-id">#<?= (int) $activeResume['id'] ?></span>
        <?php endif; ?>
        <strong><?= App::e($editingResumeName) ?></strong>
      </p>
      <a class="btn btn-primary" href="/resume-edit.php">Edit this resume</a>
    </div>

    <form method="post" class="form new-job-form" id="add-resume">
      <h3>Add resume</h3>
      <p class="empty" style="margin:0 0 0.75rem">Always a copy of Main. Give it a company name.</p>
      <input type="hidden" name="action" value="new_job_resume">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="add-company">Company</label>
          <input class="form-control" type="text" id="add-company" name="company" required placeholder="e.g. SAP">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="add-role">Job title</label>
          <input class="form-control" type="text" id="add-role" name="role" placeholder="e.g. QA Engineer">
        </div>
        <div class="col-12">
          <label class="form-label" for="add-location">Job location</label>
          <input class="form-control" type="text" id="add-location" name="location" placeholder="e.g. München, Germany" required>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Add resume</button>
        </div>
      </div>
    </form>

    <?php if (!$resumeVersions): ?>
      <p class="empty">No resumes yet. <a href="#add-resume">Add resume</a>.</p>
    <?php else: ?>
      <ul class="version-list doc-card-list">
        <?php foreach ($resumeVersions as $ver): ?>
          <?php
          $rid = (int) $ver['id'];
          $isMain = (int) $ver['is_base'] === 1;
          $isOpen = (int) $ver['is_active'] === 1;
          $label = $isMain ? 'Main resume' : (string) $ver['title'];
          ?>
          <li class="version-list-item doc-card<?= $isOpen ? ' is-open' : '' ?>">
            <div class="doc-card-main">
              <span class="doc-id" title="Unique resume ID">#<?= $rid ?></span>
              <div class="doc-card-text">
                <strong>
                  <?php if ($isMain): ?><span class="badge-main">Main</span> <?php endif; ?>
                  <?php if ($isOpen): ?><span class="badge-active">Selected</span> <?php endif; ?>
                  <?= App::e($label) ?>
                </strong>
                <?php if (!$isMain && $ver['company'] !== ''): ?>
                  <span class="muted"><?= App::e((string) $ver['company']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="version-list-actions doc-card-actions">
              <?php if ($isOpen): ?>
                <a class="btn btn-sm btn-primary" href="/resume-edit.php">Edit</a>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="action" value="load_resume_version">
                  <input type="hidden" name="id" value="<?= $rid ?>">
                  <button type="submit" class="btn btn-sm btn-primary">Edit / Select</button>
                </form>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-secondary" href="/pdf.php?doc=resume&amp;version=<?= $rid ?>">Download</a>
              <a class="btn btn-sm btn-outline-secondary" href="/resume.php?version=<?= $rid ?>" target="_blank" rel="noopener">View</a>
              <?php if (!$isMain): ?>
                <form method="post" onsubmit="return confirm('Delete resume #<?= $rid ?>?');">
                  <input type="hidden" name="action" value="delete_resume_version">
                  <input type="hidden" name="id" value="<?= $rid ?>">
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
