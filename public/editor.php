<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$pdo = Db::pdo();
Versions::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_profile') {
        $links = [];
        $labels = $_POST['link_label'] ?? [];
        $urls = $_POST['link_url'] ?? [];
        foreach ($labels as $i => $label) {
            $url = trim((string) ($urls[$i] ?? ''));
            $label = trim((string) $label);
            if ($url !== '' || $label !== '') {
                $links[] = ['label' => $label !== '' ? $label : $url, 'url' => $url];
            }
        }

        $current = App::profile();
        $profileId = (int) ($current['id'] ?? 0);
        $photoPath = (string) ($current['photo_path'] ?? '');
        $uploadDir = dirname(__DIR__) . '/public/uploads/photos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
            if ($photoPath !== '') {
                $old = dirname(__DIR__) . '/public/' . $photoPath;
                if (is_file($old)) {
                    @unlink($old);
                }
            }
            $photoPath = '';
        }

        if (!empty($_FILES['photo']['name']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) $_FILES['photo']['tmp_name'];
            $size = (int) ($_FILES['photo']['size'] ?? 0);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
            $map = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if ($size > 0 && $size <= 3 * 1024 * 1024 && isset($map[$mime])) {
                $name = 'photo_' . $profileId . '_' . time() . '.' . $map[$mime];
                $dest = $uploadDir . '/' . $name;
                if (move_uploaded_file($tmp, $dest)) {
                    if ($photoPath !== '') {
                        $old = dirname(__DIR__) . '/public/' . $photoPath;
                        if (is_file($old)) {
                            @unlink($old);
                        }
                    }
                    $photoPath = 'uploads/photos/' . $name;
                }
            } else {
                App::flash('Photo must be JPG, PNG, or WebP under 3MB.', 'error');
                App::redirect('/editor.php#profile');
            }
        }

        $dobRaw = trim((string) ($_POST['date_of_birth'] ?? ''));
        $dob = $dobRaw !== '' ? $dobRaw : null;
        $stmt = $pdo->prepare(
            'UPDATE resume_profile SET full_name = ?, title = ?, email = ?, phone = ?, location = ?, gender = ?, date_of_birth = ?, country = ?, nationality = ?, photo_path = ?, show_photo = ?, links = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            trim((string) ($_POST['full_name'] ?? '')),
            trim((string) ($_POST['title'] ?? '')),
            trim((string) ($_POST['email'] ?? '')),
            trim((string) ($_POST['phone'] ?? '')),
            trim((string) ($_POST['location'] ?? '')),
            trim((string) ($_POST['gender'] ?? '')),
            $dob,
            trim((string) ($_POST['country'] ?? '')),
            trim((string) ($_POST['nationality'] ?? '')),
            $photoPath,
            isset($_POST['show_photo']) ? 1 : 0,
            json_encode($links, JSON_UNESCAPED_SLASHES),
            $profileId,
            Auth::id(),
        ]);
        App::flash('Profile saved.');
        App::redirect('/editor.php#profile');
    }

    if ($action === 'save_sections') {
        $ids = $_POST['section_id'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $titles = $_POST['title'] ?? [];
        $bodies = $_POST['body'] ?? [];
        $visibles = $_POST['visible'] ?? [];
        if (!is_array($titles)) {
            $titles = [];
        }
        if (!is_array($bodies)) {
            $bodies = [];
        }
        if (!is_array($visibles)) {
            $visibles = [];
        }

        $keysStmt = $pdo->prepare('SELECT id, section_key FROM resume_sections WHERE user_id = ?');
        $keysStmt->execute([Auth::id()]);
        $keyById = [];
        foreach ($keysStmt->fetchAll() as $row) {
            $keyById[(int) $row['id']] = (string) $row['section_key'];
        }

        $stmt = $pdo->prepare(
            'UPDATE resume_sections SET title = ?, body = ?, visible = ?, sort_order = ? WHERE id = ? AND user_id = ?'
        );
        $order = 10;
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            $key = (string) $id;
            $body = (string) ($bodies[$key] ?? '');
            if (($keyById[$id] ?? '') === 'experience') {
                $body = '';
            }
            $stmt->execute([
                trim((string) ($titles[$key] ?? '')),
                $body,
                isset($visibles[$key]) ? 1 : 0,
                $order,
                $id,
                Auth::id(),
            ]);
            $order += 10;
        }
        App::flash('Sections saved.');
        App::redirect('/editor.php#sections');
    }

    if ($action === 'save_experiences') {
        $ids = $_POST['experience_id'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $companies = $_POST['company'] ?? [];
        $positions = $_POST['position'] ?? [];
        $locations = $_POST['location'] ?? [];
        $starts = $_POST['start_date'] ?? [];
        $ends = $_POST['end_date'] ?? [];
        $bullets = $_POST['bullets'] ?? [];
        $visibles = $_POST['visible'] ?? [];

        $stmt = $pdo->prepare(
            'UPDATE experience_entries
             SET company = ?, position = ?, location = ?, start_date = ?, end_date = ?, bullets = ?, visible = ?, sort_order = ?
             WHERE id = ? AND user_id = ?'
        );
        $order = 10;
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            $key = (string) $id;
            $stmt->execute([
                trim((string) ($companies[$key] ?? '')),
                trim((string) ($positions[$key] ?? '')),
                trim((string) ($locations[$key] ?? '')),
                trim((string) ($starts[$key] ?? '')),
                trim((string) ($ends[$key] ?? '')),
                (string) ($bullets[$key] ?? ''),
                isset($visibles[$key]) ? 1 : 0,
                $order,
                $id,
                Auth::id(),
            ]);
            $order += 10;
        }
        App::flash('Experience saved.');
        App::redirect('/editor.php#experience');
    }

    if ($action === 'add_experience') {
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM experience_entries WHERE user_id = ?');
        $maxStmt->execute([Auth::id()]);
        $max = (int) $maxStmt->fetchColumn();
        $stmt = $pdo->prepare(
            'INSERT INTO experience_entries (user_id, company, position, location, start_date, end_date, bullets, sort_order, visible)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            Auth::id(),
            trim((string) ($_POST['company'] ?? '')),
            trim((string) ($_POST['position'] ?? '')),
            trim((string) ($_POST['location'] ?? '')),
            trim((string) ($_POST['start_date'] ?? '')),
            trim((string) ($_POST['end_date'] ?? '')),
            (string) ($_POST['bullets'] ?? ''),
            $max + 10,
        ]);
        App::flash('Experience entry added.');
        App::redirect('/editor.php#experience');
    }

    if ($action === 'delete_experience') {
        $stmt = $pdo->prepare('DELETE FROM experience_entries WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0), Auth::id()]);
        App::flash('Experience entry deleted.');
        App::redirect('/editor.php#experience');
    }

    if ($action === 'add_section') {
        $key = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($_POST['section_key'] ?? '')))) ?: ('custom_' . time());
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM resume_sections WHERE user_id = ?');
        $maxStmt->execute([Auth::id()]);
        $max = (int) $maxStmt->fetchColumn();
        $stmt = $pdo->prepare(
            'INSERT INTO resume_sections (user_id, section_key, title, body, sort_order, visible) VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            Auth::id(),
            $key,
            trim((string) ($_POST['title'] ?? 'New section')) ?: 'New section',
            (string) ($_POST['body'] ?? ''),
            $max + 10,
        ]);
        App::flash('Section added.');
        App::redirect('/editor.php#sections');
    }

    if ($action === 'delete_section') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM resume_sections WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, Auth::id()]);
        App::flash('Section deleted.');
        App::redirect('/editor.php#sections');
    }

    if ($action === 'save_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $isBase = isset($_POST['is_base']) ? 1 : 0;
        if ($isActive) {
            $pdo->prepare('UPDATE cover_letters SET is_active = 0 WHERE user_id = ?')->execute([Auth::id()]);
        }
        if ($isBase) {
            $pdo->prepare('UPDATE cover_letters SET is_base = 0 WHERE user_id = ?')->execute([Auth::id()]);
        }
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE cover_letters SET title = ?, body = ?, company = ?, is_active = ?, is_base = ? WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([
                trim((string) ($_POST['title'] ?? '')),
                (string) ($_POST['body'] ?? ''),
                trim((string) ($_POST['company'] ?? '')),
                $isActive,
                $isBase,
                $id,
                Auth::id(),
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO cover_letters (user_id, title, body, company, is_active, is_base) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                Auth::id(),
                trim((string) ($_POST['title'] ?? 'Cover letter')) ?: 'Cover letter',
                (string) ($_POST['body'] ?? ''),
                trim((string) ($_POST['company'] ?? '')),
                1,
                $isBase,
            ]);
            if ($isBase) {
                $pdo->prepare(
                    'UPDATE cover_letters SET is_base = 0 WHERE user_id = ? AND id <> LAST_INSERT_ID()'
                )->execute([Auth::id()]);
            }
        }
        App::flash('Cover letter saved.');
        App::redirect('/editor.php#cover');
    }

    if ($action === 'activate_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        Versions::activateCover($id);
        App::flash('Now editing this cover letter.');
        App::redirect('/editor.php?cover=' . $id . '#cover');
    }

    if ($action === 'new_job_cover') {
        $company = trim((string) ($_POST['company'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        if ($company === '') {
            App::flash('Enter a company name first.', 'error');
            App::redirect('/editor.php#cover');
        }
        $base = Versions::baseCoverLetter();
        if ($base === null) {
            App::flash('No Main cover letter to copy from.', 'error');
            App::redirect('/editor.php#cover');
        }
        if ($title === '') {
            $title = 'Cover letter — ' . $company;
        }
        $companyLine = $location !== '' ? ($company . ' · ' . $location) : $company;
        $newId = Versions::duplicateCover((int) $base['id'], $title);
        $pdo->prepare('UPDATE cover_letters SET company = ? WHERE id = ? AND user_id = ?')->execute([$companyLine, $newId, Auth::id()]);
        App::flash('Created cover letter #' . $newId . ' (copy of Main' . ($location !== '' ? ', ' . $location : '') . '). Edit it below.');
        App::redirect('/editor.php?cover=' . $newId . '#cover');
    }

    if ($action === 'mark_cover_base') {
        $id = (int) ($_POST['id'] ?? 0);
        Versions::markCoverBase($id);
        App::flash('Marked as Main cover letter.');
        App::redirect('/editor.php#cover');
    }

    if ($action === 'delete_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::deleteCover($id);
            App::flash('Cover letter deleted.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/editor.php#cover');
    }

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
        App::redirect('/editor.php#versions');
    }

    if ($action === 'save_open_resume') {
        $active = Versions::activeResumeVersion();
        $base = Versions::baseResumeVersion();
        $target = $active ?: $base;
        $snapshot = Versions::captureSnapshot();
        if ($target) {
            Versions::saveResumeVersion(
                (string) $target['title'],
                $snapshot,
                (string) ($target['company'] ?? ''),
                (string) ($target['note'] ?? ''),
                (int) ($target['is_base'] ?? 0) === 1,
                (int) $target['id'],
                true
            );
            $name = (int) ($target['is_base'] ?? 0) === 1 ? 'Main resume' : (string) $target['title'];
            App::flash('Saved: ' . $name);
        } else {
            Versions::updateBaseFromLive('Main resume');
            App::flash('Saved as Main resume.');
        }
        App::redirect('/editor.php#versions');
    }

    if ($action === 'new_job_resume') {
        $company = trim((string) ($_POST['company'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        if ($company === '') {
            App::flash('Enter a company name first.', 'error');
            App::redirect('/editor.php#versions');
        }
        $base = Versions::baseResumeVersion();
        if ($base === null) {
            App::flash('No Main resume to copy from. Save Main first.', 'error');
            App::redirect('/editor.php#versions');
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
        App::redirect('/editor.php#versions');
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
        }
        App::redirect('/editor.php#versions');
    }

    if ($action === 'reset_to_main_resume') {
        $base = Versions::baseResumeVersion();
        if (!$base) {
            App::flash('No Main resume yet.', 'error');
            App::redirect('/editor.php#versions');
        }
        try {
            Versions::loadResumeVersion((int) $base['id']);
            App::setSetting('active_company', '');
            App::flash('Now editing: Main resume');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/editor.php#versions');
    }

    if ($action === 'delete_resume_version') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            Versions::deleteResumeVersion($id);
            App::flash('Resume deleted.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/editor.php#versions');
    }

    if ($action === 'save_design') {
        $theme = App::resolveTheme($_POST['theme'] ?? null);
        $color = App::resolveAccent($_POST['accent_color'] ?? null);
        $font = App::resolveFont($_POST['font_family'] ?? null);
        App::setSetting('theme', $theme);
        App::setSetting('accent_color', $color);
        App::setSetting('font_family', $font);
        App::setSetting('pdf_mode', isset($_POST['pdf_mode']) ? '1' : '0');
        App::setSetting('active_company', trim((string) ($_POST['active_company'] ?? '')));
        App::setSetting('name_size', App::resolveNameSize((string) ($_POST['name_size'] ?? '')));
        App::setSetting('section_spacing', App::resolveSectionSpacing((string) ($_POST['section_spacing'] ?? '')));
        App::flash('Design settings saved.');
        App::redirect('/editor.php#design');
    }

    App::flash('Unknown action.', 'error');
    App::redirect('/editor.php');
}

$profile = App::profile();
$sections = App::sections(false);
$experiences = App::experiences(false);
$letter = App::activeCoverLetter();
$coverLetters = App::coverLetters();
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
$editCoverId = isset($_GET['cover']) ? (int) $_GET['cover'] : 0;
$newCover = isset($_GET['new']) && (string) $_GET['new'] === '1';
if ($newCover) {
    $letter = [
        'id' => 0,
        'title' => 'Cover letter',
        'body' => '',
        'company' => '',
        'is_active' => 1,
        'is_base' => 0,
    ];
} elseif ($editCoverId > 0) {
    $picked = Versions::coverLetterById($editCoverId);
    if ($picked) {
        $letter = $picked;
    }
}
$theme = App::setting('theme', 'classic') ?: 'classic';
$accent = App::setting('accent_color', '#1a5f4a') ?: '#1a5f4a';
$font = App::resolveFont(null);
$pdfMode = (App::setting('pdf_mode', '0') ?: '0') === '1';
$activeCompany = App::setting('active_company', '') ?: '';
$links = $profile['links'];
if (count($links) < 2) {
    $links[] = ['label' => '', 'url' => ''];
}

layout_header('Editor');
?>
<main class="editor">
  <header class="page-head">
    <h1>Editor</h1>
    <p>Edit the open resume and cover letter. Use <a href="/tailor.php">Apply from a JD</a> to copy Main for a company instead of PHP files.</p>
    <div class="preview-links">
      <a class="btn btn-small" href="/resume.php" target="_blank" rel="noopener">Preview resume</a>
      <a class="btn btn-small" href="/cover-letter.php" target="_blank" rel="noopener">Preview cover letter</a>
    </div>
  </header>

  <div class="editor-grid">
    <aside class="editor-nav">
      <a href="#versions">My resumes</a>
      <a href="#design">Design</a>
      <a href="#profile">Profile</a>
      <a href="#experience">Experience</a>
      <a href="#sections">Sections</a>
      <a href="#cover">Cover letters</a>
    </aside>

    <div class="editor-main">
      <section class="editor-block" id="versions">
        <h2>My resumes</h2>

        <ol class="simple-steps">
          <li><strong>Main</strong> = your normal CV.</li>
          <li>For a job: <strong>Add resume</strong>, change it, then Download that one.</li>
          <li>Each resume has a unique ID (shown below).</li>
        </ol>

        <div class="now-editing">
          <p>
            Now editing:
            <?php if ($activeResume): ?>
              <span class="doc-id">#<?= (int) $activeResume['id'] ?></span>
            <?php endif; ?>
            <strong><?= App::e($editingResumeName) ?></strong>
          </p>
          <form method="post" class="form form-inline-actions">
            <input type="hidden" name="action" value="save_open_resume">
            <button type="submit" class="btn btn-primary">Save</button>
          </form>
        </div>

        <form method="post" class="form new-job-form" id="add-resume">
          <h3>Add resume</h3>
          <p class="empty" style="margin:0 0 0.75rem">Always a copy of Main. Give it a company name.</p>
          <input type="hidden" name="action" value="new_job_resume">
          <label>Company <input type="text" name="company" required placeholder="e.g. SAP"></label>
          <label>Job title <input type="text" name="role" placeholder="e.g. QA Engineer"></label>
          <label>Job location <input type="text" name="location" placeholder="e.g. München, Germany" required></label>
          <button type="submit" class="btn btn-primary">Add resume</button>
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
                      <?php if ($isOpen): ?><span class="badge-active">Editing</span> <?php endif; ?>
                      <?= App::e($label) ?>
                    </strong>
                    <?php if (!$isMain && $ver['company'] !== ''): ?>
                      <span class="muted"><?= App::e((string) $ver['company']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="version-list-actions doc-card-actions">
                  <form method="post">
                    <input type="hidden" name="action" value="load_resume_version">
                    <input type="hidden" name="id" value="<?= $rid ?>">
                    <button type="submit" class="btn btn-small btn-primary">Edit</button>
                  </form>
                  <a class="btn btn-small btn-secondary" href="/pdf.php?doc=resume&amp;version=<?= $rid ?>">Download</a>
                  <a class="btn btn-small" href="/resume.php?version=<?= $rid ?>" target="_blank" rel="noopener">View</a>
                  <?php if (!$isMain): ?>
                    <form method="post" onsubmit="return confirm('Delete resume #<?= $rid ?>?');">
                      <input type="hidden" name="action" value="delete_resume_version">
                      <input type="hidden" name="id" value="<?= $rid ?>">
                      <button type="submit" class="btn btn-small btn-danger">Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="editor-block" id="design">
        <h2>Design &amp; PDF</h2>
        <p class="empty" style="margin-top:0">Choose a visual style, preview live, then print or download PDF.</p>
        <p><a class="btn btn-primary" href="/design.php">Open design studio</a></p>
        <form method="post" class="form" style="margin-top:1.25rem">
          <input type="hidden" name="action" value="save_design">
          <label>
            Theme
            <select name="theme">
              <?php foreach (App::themes() as $key => $meta): ?>
                <option value="<?= App::e($key) ?>"<?= $theme === $key ? ' selected' : '' ?>><?= App::e($meta['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Font
            <select name="font_family">
              <?php foreach (App::fonts() as $key => $meta): ?>
                <option value="<?= App::e($key) ?>"<?= $font === $key ? ' selected' : '' ?>><?= App::e($meta['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Accent color
            <input type="color" name="accent_color" value="<?= App::e($accent) ?>">
          </label>
          <label>
            Name size
            <select name="name_size">
              <?php
              $nameSize = App::resolveNameSize(null);
              foreach (['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $key => $label):
              ?>
                <option value="<?= App::e($key) ?>"<?= $nameSize === $key ? ' selected' : '' ?>><?= App::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Section spacing
            <select name="section_spacing">
              <?php
              $spacing = App::resolveSectionSpacing(null);
              foreach (['tight' => 'Tight', 'md' => 'Medium', 'loose' => 'Loose'] as $key => $label):
              ?>
                <option value="<?= App::e($key) ?>"<?= $spacing === $key ? ' selected' : '' ?>><?= App::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Active company (for tailor tag)
            <input type="text" name="active_company" value="<?= App::e($activeCompany) ?>" placeholder="Company name">
          </label>
          <label class="check">
            <input type="checkbox" name="pdf_mode" value="1"<?= $pdfMode ? ' checked' : '' ?>>
            Optimize for PDF / print
          </label>
          <button type="submit" class="btn btn-primary">Save design</button>
        </form>
      </section>

      <section class="editor-block" id="profile">
        <h2>Profile</h2>
        <form method="post" class="form" enctype="multipart/form-data">
          <input type="hidden" name="action" value="save_profile">
          <input type="hidden" name="id" value="<?= (int) $profile['id'] ?>">
          <label>Full name <input type="text" name="full_name" required value="<?= App::e($profile['full_name']) ?>"></label>
          <label>Title <input type="text" name="title" value="<?= App::e($profile['title']) ?>"></label>
          <label>Email <input type="email" name="email" value="<?= App::e($profile['email']) ?>"></label>
          <label>Mobile <input type="text" name="phone" value="<?= App::e($profile['phone']) ?>" placeholder="+1 555 0100"></label>
          <label>Location <input type="text" name="location" value="<?= App::e($profile['location']) ?>"></label>
          <label>Gender
            <select name="gender">
              <?php
              $gender = (string) ($profile['gender'] ?? '');
              $genders = ['' => '— Prefer not to say / hide —', 'Male' => 'Male', 'Female' => 'Female', 'Non-binary' => 'Non-binary', 'Other' => 'Other'];
              foreach ($genders as $val => $label):
              ?>
                <option value="<?= App::e($val) ?>"<?= $gender === $val ? ' selected' : '' ?>><?= App::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Date of birth <input type="date" name="date_of_birth" value="<?= App::e((string) ($profile['date_of_birth'] ?? '')) ?>"></label>
          <label>Country <input type="text" name="country" value="<?= App::e((string) ($profile['country'] ?? '')) ?>"></label>
          <label>Nationality <input type="text" name="nationality" value="<?= App::e((string) ($profile['nationality'] ?? '')) ?>"></label>

          <fieldset class="photo-fieldset">
            <legend>Profile picture</legend>
            <?php $photoUrl = App::photoUrl($profile); ?>
            <?php if ($photoUrl !== ''): ?>
              <div class="photo-preview">
                <img src="<?= App::e($photoUrl) ?>" alt="Current profile photo">
              </div>
            <?php endif; ?>
            <label>Upload photo
              <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
            </label>
            <label class="check">
              <input type="checkbox" name="show_photo" value="1"<?= (int) ($profile['show_photo'] ?? 1) === 1 ? ' checked' : '' ?>>
              Show picture on resume templates
            </label>
            <?php if ($photoUrl !== ''): ?>
              <label class="check">
                <input type="checkbox" name="remove_photo" value="1">
                Remove picture
              </label>
            <?php endif; ?>
            <p class="empty" style="margin:0">JPG, PNG, or WebP · max 3MB. Uncheck “Show picture” to hide it without deleting.</p>
          </fieldset>

          <fieldset class="links-fieldset">
            <legend>Links</legend>
            <?php foreach ($links as $link): ?>
              <div class="link-row">
                <input type="text" name="link_label[]" placeholder="Label" value="<?= App::e($link['label'] ?? '') ?>">
                <input type="url" name="link_url[]" placeholder="https://" value="<?= App::e($link['url'] ?? '') ?>">
              </div>
            <?php endforeach; ?>
            <button type="button" class="btn btn-small" data-add-link>Add link</button>
          </fieldset>
          <p class="empty" style="margin:0">Empty fields are hidden on the resume and cover letter.</p>
          <button type="submit" class="btn btn-primary">Save profile</button>
        </form>
      </section>

      <section class="editor-block" id="experience">
        <h2>Experience</h2>
        <p class="empty" style="margin-top:0">Add each company as its own entry. Position is bold on the resume. Company and dates sit on the left/right layout.</p>

        <form method="post" class="section-order-form" data-section-sorter>
          <input type="hidden" name="action" value="save_experiences">
          <div class="section-sort-list" data-sort-list>
            <?php foreach ($experiences as $job): ?>
              <?php $jid = (int) $job['id']; ?>
              <div class="section-sort-item experience-edit-item" data-sort-item draggable="true" id="experience-<?= $jid ?>">
                <input type="hidden" name="experience_id[]" value="<?= $jid ?>">
                <div class="section-sort-controls">
                  <button type="button" class="btn btn-small sort-btn" data-move-up title="Move up" aria-label="Move up">↑</button>
                  <button type="button" class="btn btn-small sort-btn" data-move-down title="Move down" aria-label="Move down">↓</button>
                  <span class="drag-hint" title="Drag to reorder" aria-hidden="true">⋮⋮</span>
                </div>
                <div class="section-sort-body">
                  <div class="experience-fields">
                    <label>Company
                      <input type="text" name="company[<?= $jid ?>]" value="<?= App::e($job['company']) ?>" required>
                    </label>
                    <label>Position (bold)
                      <input type="text" name="position[<?= $jid ?>]" value="<?= App::e($job['position']) ?>" required>
                    </label>
                    <label>Location
                      <input type="text" name="location[<?= $jid ?>]" value="<?= App::e($job['location']) ?>" placeholder="City, Country">
                    </label>
                    <label>Start date
                      <input type="text" name="start_date[<?= $jid ?>]" value="<?= App::e($job['start_date']) ?>" placeholder="Oct 2025">
                    </label>
                    <label>End date
                      <input type="text" name="end_date[<?= $jid ?>]" value="<?= App::e($job['end_date']) ?>" placeholder="Dec 2025 or Present">
                    </label>
                    <label class="check exp-visible">
                      <input type="checkbox" name="visible[<?= $jid ?>]" value="1"<?= (int) $job['visible'] === 1 ? ' checked' : '' ?>>
                      Visible
                    </label>
                  </div>
                  <label>Bullets / details
                    <textarea name="bullets[<?= $jid ?>]" rows="6" placeholder="• Achievement one&#10;• Achievement two"><?= App::e($job['bullets']) ?></textarea>
                  </label>
                  <div class="form-actions">
                    <button type="submit" form="experience-delete-<?= $jid ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this experience entry?');">Delete</button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-actions section-order-actions">
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>

        <?php foreach ($experiences as $job): ?>
          <form method="post" id="experience-delete-<?= (int) $job['id'] ?>" hidden>
            <input type="hidden" name="action" value="delete_experience">
            <input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
          </form>
        <?php endforeach; ?>

        <form method="post" class="form add-section">
          <h3>Add company / role</h3>
          <input type="hidden" name="action" value="add_experience">
          <div class="experience-fields">
            <label>Company <input type="text" name="company" required placeholder="Company name"></label>
            <label>Position <input type="text" name="position" required placeholder="Job title"></label>
            <label>Location <input type="text" name="location" placeholder="City, Country"></label>
            <label>Start date <input type="text" name="start_date" placeholder="Oct 2025"></label>
            <label>End date <input type="text" name="end_date" placeholder="Present"></label>
          </div>
          <label>Bullets / details
            <textarea name="bullets" rows="5" placeholder="• First achievement&#10;• Second achievement"></textarea>
          </label>
          <button type="submit" class="btn btn-primary">Add experience</button>
        </form>
      </section>

      <section class="editor-block" id="sections">
        <h2>Resume sections</h2>
        <p class="empty" style="margin-top:0">Use the arrows (or drag) to reorder, then click <strong>Save</strong>. Experience content is edited in the <a href="#experience">Experience</a> tab.</p>

        <form method="post" class="section-order-form" data-section-sorter>
          <input type="hidden" name="action" value="save_sections">
          <div class="section-sort-list" data-sort-list>
            <?php foreach ($sections as $section): ?>
              <?php
              $sid = (int) $section['id'];
              $isExperience = ($section['section_key'] ?? '') === 'experience';
              ?>
              <div class="section-sort-item" data-sort-item draggable="true" id="section-<?= $sid ?>">
                <input type="hidden" name="section_id[]" value="<?= $sid ?>">
                <div class="section-sort-controls">
                  <button type="button" class="btn btn-small sort-btn" data-move-up title="Move up" aria-label="Move up">↑</button>
                  <button type="button" class="btn btn-small sort-btn" data-move-down title="Move down" aria-label="Move down">↓</button>
                  <span class="drag-hint" title="Drag to reorder" aria-hidden="true">⋮⋮</span>
                </div>
                <div class="section-sort-body">
                  <div class="section-form-head">
                    <label class="grow">Title
                      <input type="text" name="title[<?= $sid ?>]" value="<?= App::e($section['title']) ?>">
                    </label>
                    <label class="check">
                      <input type="checkbox" name="visible[<?= $sid ?>]" value="1"<?= (int) $section['visible'] === 1 ? ' checked' : '' ?>>
                      Visible
                    </label>
                  </div>
                  <?php if ($isExperience): ?>
                    <p class="empty" style="margin:0">Company roles are managed under <a href="#experience">Experience</a>.</p>
                    <input type="hidden" name="body[<?= $sid ?>]" value="">
                  <?php else: ?>
                    <label>Body
                      <textarea name="body[<?= $sid ?>]" rows="8"><?= App::e($section['body']) ?></textarea>
                    </label>
                  <?php endif; ?>
                  <div class="form-actions">
                    <button type="submit" form="section-delete-<?= $sid ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this section?');">Delete</button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-actions section-order-actions">
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>

        <?php foreach ($sections as $section): ?>
          <form method="post" id="section-delete-<?= (int) $section['id'] ?>" hidden>
            <input type="hidden" name="action" value="delete_section">
            <input type="hidden" name="id" value="<?= (int) $section['id'] ?>">
          </form>
        <?php endforeach; ?>

        <form method="post" class="form add-section">
          <h3>Add section</h3>
          <input type="hidden" name="action" value="add_section">
          <label>Key <input type="text" name="section_key" placeholder="e.g. certifications"></label>
          <label>Title <input type="text" name="title" placeholder="Certifications"></label>
          <label>Body <textarea name="body" rows="4"></textarea></label>
          <button type="submit" class="btn btn-primary">Add section</button>
        </form>
      </section>

      <section class="editor-block" id="cover">
        <h2>Cover letters</h2>
        <ol class="simple-steps">
          <li><strong>Main</strong> = your normal letter.</li>
          <li>For a job: <strong>Add cover letter</strong>, change it, then Download that one.</li>
          <li>Each letter has a unique ID (shown below).</li>
        </ol>

        <form method="post" class="form new-job-form" id="add-cover">
          <h3>Add cover letter</h3>
          <p class="empty" style="margin:0 0 0.75rem">Always a copy of Main cover letter.</p>
          <input type="hidden" name="action" value="new_job_cover">
          <label>Company <input type="text" name="company" required placeholder="e.g. SAP"></label>
          <label>Job location <input type="text" name="location" placeholder="e.g. München, Germany" required></label>
          <label>Name <input type="text" name="title" placeholder="e.g. QA Engineer — SAP"></label>
          <button type="submit" class="btn btn-primary">Add cover letter</button>
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
                      <?php if ($isOpen): ?><span class="badge-active">Editing</span> <?php endif; ?>
                      <?= App::e($label) ?>
                    </strong>
                    <?php if (!$isMain && $cl['company'] !== ''): ?>
                      <span class="muted"><?= App::e((string) $cl['company']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="version-list-actions doc-card-actions">
                  <form method="post">
                    <input type="hidden" name="action" value="activate_cover">
                    <input type="hidden" name="id" value="<?= $cid ?>">
                    <button type="submit" class="btn btn-small btn-primary">Edit</button>
                  </form>
                  <a class="btn btn-small btn-secondary" href="/pdf.php?doc=cover&amp;id=<?= $cid ?>">Download</a>
                  <a class="btn btn-small" href="/cover-letter.php?id=<?= $cid ?>" target="_blank" rel="noopener">View</a>
                  <?php if (!$isMain): ?>
                    <form method="post" onsubmit="return confirm('Delete cover letter #<?= $cid ?>?');">
                      <input type="hidden" name="action" value="delete_cover">
                      <input type="hidden" name="id" value="<?= $cid ?>">
                      <button type="submit" class="btn btn-small btn-danger">Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if (!empty($letter['id'])): ?>
        <form method="post" class="form" style="margin-top:1.25rem">
          <h3>Edit letter <span class="doc-id">#<?= (int) $letter['id'] ?></span></h3>
          <input type="hidden" name="action" value="save_cover">
          <input type="hidden" name="id" value="<?= (int) $letter['id'] ?>">
          <input type="hidden" name="is_active" value="1">
          <?php if ((int) ($letter['is_base'] ?? 0) === 1): ?>
            <input type="hidden" name="is_base" value="1">
          <?php endif; ?>
          <label>Name <input type="text" name="title" value="<?= App::e($letter['title'] ?? 'Cover letter') ?>"></label>
          <label>Company <input type="text" name="company" value="<?= App::e($letter['company'] ?? '') ?>" placeholder="Optional"></label>
          <label>Letter text
            <textarea name="body" rows="16"><?= App::e($letter['body'] ?? '') ?></textarea>
          </label>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save letter</button>
          </div>
        </form>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>
<?php
layout_footer();
