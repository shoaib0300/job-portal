<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/editor_ui.php';

use KaamFit\Resume\ResumeCheck;
use KaamFit\Resume\ResumeLayout;
use KaamFit\Resume\ResumePhotoMode;

$pdo = Db::pdo();
Versions::ensureSchema();

if (isset($_GET['version']) && (int) $_GET['version'] > 0) {
    try {
        Versions::loadResumeVersion((int) $_GET['version']);
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/editor');
    }
}

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
                App::redirect('/resume-edit#profile');
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
        App::redirect('/resume-edit#profile');
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
        App::redirect('/resume-edit#sections');
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
        App::redirect('/resume-edit#experience');
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
        App::redirect('/resume-edit#experience');
    }

    if ($action === 'delete_experience') {
        $stmt = $pdo->prepare('DELETE FROM experience_entries WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0), Auth::id()]);
        App::flash('Experience entry deleted.');
        App::redirect('/resume-edit#experience');
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
        App::redirect('/resume-edit#sections');
    }

    if ($action === 'delete_section') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM resume_sections WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, Auth::id()]);
        App::flash('Section deleted.');
        App::redirect('/resume-edit#sections');
    }

    if ($action === 'save_layout') {
        $template = \KaamFit\Resume\ResumeLayout::resolveTemplate((string) ($_POST['resume_template'] ?? ''));
        $mode = \KaamFit\Resume\ResumeLayout::resolveMode((string) ($_POST['resume_mode'] ?? ''));
        $photoMode = \KaamFit\Resume\ResumePhotoMode::resolve((string) ($_POST['photo_mode'] ?? ''));
        $density = \KaamFit\Resume\ResumeLayout::resolveDensity((string) ($_POST['resume_density'] ?? ''));
        $prevMode = \KaamFit\Resume\ResumeLayout::resolveMode(null);
        App::setSetting('resume_template', $template);
        App::setSetting('theme', $template);
        App::setSetting('resume_mode', $mode);
        App::setSetting('photo_mode', $photoMode);
        App::setSetting('resume_density', $density);
        App::setSetting('show_personal_extras', isset($_POST['show_personal_extras']) ? '1' : '0');
        App::setSetting('show_signature', isset($_POST['show_signature']) ? '1' : '0');
        if (!empty($_POST['accent_color'])) {
            App::setSetting('accent_color', App::resolveAccent((string) $_POST['accent_color']));
        }
        // Apply mode default section order only when mode actually changes.
        if ($mode !== $prevMode) {
            $order = \KaamFit\Resume\ResumeLayout::defaultOrder($mode);
            $rank = array_flip($order);
            $stmt = $pdo->prepare('SELECT id, section_key FROM resume_sections WHERE user_id = ?');
            $stmt->execute([Auth::id()]);
            $upd = $pdo->prepare('UPDATE resume_sections SET sort_order = ? WHERE id = ? AND user_id = ?');
            foreach ($stmt->fetchAll() as $row) {
                $key = (string) $row['section_key'];
                $sort = isset($rank[$key]) ? (10 + $rank[$key] * 10) : 900;
                $upd->execute([$sort, (int) $row['id'], Auth::id()]);
            }
        }
        App::flash('Layout saved.');
        App::redirect('/resume-edit#layout');
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
            App::flash(((int) ($target['is_base'] ?? 0) === 1 ? 'Master CV saved.' : 'Job CV saved.'));
        } else {
            Versions::updateBaseFromLive(Versions::MASTER_CV_LABEL);
            App::flash('Saved as ' . Versions::MASTER_CV_LABEL . '.');
        }
        App::redirect('/resume-edit');
    }

    App::flash('Unknown action.', 'error');
    App::redirect('/resume-edit');
}

$uiLang = App::resolveDocumentLang();
ResumeLayout::ensureMissingSections(Auth::id(), str_starts_with(strtolower($uiLang), 'de') ? 'de' : 'en');

$profile = App::profile();
$sections = App::sections(false);
$experiences = App::experiences(false);
$baseResume = Versions::baseResumeVersion();
$activeResume = Versions::activeResumeVersion();
$editingResumeId = 0;
$isEditingMaster = false;
$editingResumeName = Versions::MASTER_CV_LABEL;
if ($activeResume) {
    $editingResumeId = (int) $activeResume['id'];
    $isEditingMaster = Versions::isMasterResume($activeResume);
    $editingResumeName = Versions::resumeDisplayLabel($activeResume);
} elseif ($baseResume) {
    $editingResumeId = (int) $baseResume['id'];
    $isEditingMaster = true;
    $editingResumeName = Versions::MASTER_CV_LABEL;
}
$saveResumeLabel = $isEditingMaster ? 'Save Master CV' : 'Save Job CV';
$links = $profile['links'];
if (count($links) < 2) {
    $links[] = ['label' => '', 'url' => ''];
}

$template = ResumeLayout::resolveTemplate(null);
$mode = ResumeLayout::resolveMode(null);
$photoMode = ResumePhotoMode::resolve(null);
$density = ResumeLayout::resolveDensity(null);
$showExtras = (App::setting('show_personal_extras', '0') ?: '0') === '1';
$showSignature = (App::setting('show_signature', '0') ?: '0') === '1';
$accent = App::resolveAccent(null);

$payload = Versions::resumePayloadForView($editingResumeId > 0 ? $editingResumeId : null);
$check = ResumeCheck::analyze($payload, $payload['meta'] ?? ResumeLayout::defaultMeta());

$jdSnippet = '';
if ($editingResumeId > 0) {
    $appStmt = Db::pdo()->prepare(
        'SELECT jd_snippet FROM applications WHERE user_id = ? AND resume_version_id = ? ORDER BY id DESC LIMIT 1'
    );
    $appStmt->execute([Auth::id(), $editingResumeId]);
    $jdSnippet = (string) ($appStmt->fetchColumn() ?: '');
}
$match = $jdSnippet !== '' ? ResumeCheck::jdMatchEstimate($payload, $jdSnippet) : null;

$previewQs = [
    'embed' => '1',
    'pdf' => '1',
    'theme' => $template,
    'density' => $density,
];
if ($editingResumeId > 0) {
    $previewQs['version'] = $editingResumeId;
}
$previewUrl = '/resume?' . http_build_query($previewQs);
$pdfQs = $editingResumeId > 0 ? ['version' => $editingResumeId] : [];

layout_header(ResumeLayout::ui('studio', $uiLang));
?>
<main class="editor resume-studio-page">
  <header class="page-head resume-studio-toolbar">
    <div>
      <h1 class="h4 mb-1">
        <?php if ($editingResumeId > 0): ?>
          <span class="doc-id">#<?= $editingResumeId ?></span>
        <?php endif; ?>
        <?= App::e($editingResumeName) ?>
      </h1>
      <p class="mb-0 small text-muted">
        <a href="/editor">← My resumes</a>
        · <?= $isEditingMaster ? 'Master CV' : 'Job CV' ?>
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="save_open_resume">
        <button type="submit" class="btn btn-sm btn-primary"><?= App::e($saveResumeLabel) ?></button>
      </form>
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <?= App::e(ResumeLayout::ui('download', $uiLang)) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= App::e(PdfExport::downloadHrefOriginal('resume', $pdfQs)) ?>"><?= App::e(ResumeLayout::ui('standard', $uiLang)) ?></a></li>
          <li><a class="dropdown-item" href="<?= App::e(PdfExport::downloadHrefAts('resume', $pdfQs)) ?>"><?= App::e(ResumeLayout::ui('ats', $uiLang)) ?></a></li>
          <li><a class="dropdown-item" href="<?= App::e(PdfExport::downloadHrefTranslated('resume', 'de', $pdfQs)) ?>"><?= App::e(ResumeLayout::ui('german', $uiLang)) ?></a></li>
          <li><a class="dropdown-item" href="<?= App::e(PdfExport::downloadHrefTranslated('resume', 'en', $pdfQs)) ?>"><?= App::e(ResumeLayout::ui('english', $uiLang)) ?></a></li>
        </ul>
      </div>
      <span class="badge text-bg-light border" id="studio-page-badge" title="Estimated A4 pages">— / 2 pages</span>
    </div>
  </header>

  <div class="resume-context-banner<?= $isEditingMaster ? ' is-master' : ' is-job' ?> mb-3">
    <?php if ($isEditingMaster): ?>
      <strong>Editing Master CV</strong> — your template. Tailoring always copies from here.
    <?php else: ?>
      <strong>Editing Job CV:</strong> <?= App::e($editingResumeName) ?>. Master CV is unchanged.
    <?php endif; ?>
  </div>

  <div class="resume-studio" data-resume-studio data-preview-base="<?= App::e($previewUrl) ?>" data-check-url="/resume-check<?= $editingResumeId > 0 ? '?version=' . $editingResumeId : '' ?>">
    <aside class="resume-studio-nav" aria-label="<?= App::e(ResumeLayout::ui('sections', $uiLang)) ?>">
      <p class="small text-uppercase text-muted fw-semibold mb-2"><?= App::e(ResumeLayout::ui('sections', $uiLang)) ?></p>
      <a class="studio-nav-item is-active" href="#panel-layout" data-studio-panel="layout"><?= App::e(ResumeLayout::ui('layout', $uiLang)) ?></a>
      <a class="studio-nav-item" href="#panel-profile" data-studio-panel="profile"><?= App::e(ResumeLayout::ui('profile', $uiLang)) ?></a>
      <a class="studio-nav-item" href="#panel-experience" data-studio-panel="experience"><?= App::e(ResumeLayout::sectionLabel('experience', $uiLang)) ?></a>
      <?php foreach ($sections as $section): ?>
        <?php
          $sk = (string) ($section['section_key'] ?? '');
          if ($sk === 'experience') {
              continue;
          }
          $label = (string) ($section['title'] ?? $sk);
          $hidden = (int) ($section['visible'] ?? 1) !== 1;
        ?>
        <a class="studio-nav-item<?= $hidden ? ' is-hidden-section' : '' ?>" href="#panel-sections" data-studio-panel="sections" data-section-id="<?= (int) $section['id'] ?>">
          <span><?= App::e($label) ?></span>
          <?php if ($hidden): ?><span class="small">hidden</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a class="studio-nav-item" href="#panel-check" data-studio-panel="check"><?= App::e(ResumeLayout::ui('check', $uiLang)) ?></a>
      <hr>
      <a class="studio-nav-item" href="/editor">My resumes</a>
    </aside>

    <div class="resume-studio-preview">
      <div class="d-flex justify-content-between w-100 align-items-center" style="max-width:210mm">
        <strong class="small"><?= App::e(ResumeLayout::ui('preview', $uiLang)) ?></strong>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-studio-refresh>Refresh</button>
      </div>
      <iframe title="A4 resume preview" data-studio-preview src="<?= App::e($previewUrl) ?>" loading="lazy"></iframe>
    </div>

    <div class="resume-studio-editor">
      <p class="small text-uppercase text-muted fw-semibold mb-2"><?= App::e(ResumeLayout::ui('editor', $uiLang)) ?></p>

      <section class="studio-panel is-active" id="panel-layout" data-panel="layout">
        <h2 class="h5"><?= App::e(ResumeLayout::ui('layout', $uiLang)) ?></h2>
        <form method="post" class="form">
          <input type="hidden" name="action" value="save_layout">
          <label class="form-label">Template
            <select class="form-select" name="resume_template">
              <?php foreach (ResumeLayout::TEMPLATES as $key => $meta): ?>
                <option value="<?= App::e($key) ?>"<?= $template === $key ? ' selected' : '' ?>><?= App::e($meta['label']) ?> — <?= App::e($meta['blurb']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="form-label mt-2">Mode
            <select class="form-select" name="resume_mode">
              <option value="professional"<?= $mode === 'professional' ? ' selected' : '' ?>>Professional</option>
              <option value="student"<?= $mode === 'student' ? ' selected' : '' ?>>Student / Werkstudent</option>
            </select>
          </label>
          <label class="form-label mt-2">Photo mode
            <select class="form-select" name="photo_mode">
              <option value="with_photo"<?= $photoMode === 'with_photo' ? ' selected' : '' ?>>With photo</option>
              <option value="without_photo"<?= $photoMode === 'without_photo' ? ' selected' : '' ?>>Without photo</option>
              <option value="anonymized"<?= $photoMode === 'anonymized' ? ' selected' : '' ?>>Anonymized</option>
            </select>
          </label>
          <label class="form-label mt-2">Density
            <select class="form-select" name="resume_density">
              <option value="normal"<?= $density === 'normal' ? ' selected' : '' ?>>Normal</option>
              <option value="tight"<?= $density === 'tight' ? ' selected' : '' ?>>Tight</option>
              <option value="compact"<?= $density === 'compact' ? ' selected' : '' ?>>Compact (~2 pages)</option>
            </select>
          </label>
          <label class="form-label mt-2">Accent
            <input class="form-control form-control-color" type="color" name="accent_color" value="<?= App::e($accent) ?>">
          </label>
          <label class="check d-block mt-2">
            <input type="checkbox" name="show_personal_extras" value="1"<?= $showExtras ? ' checked' : '' ?>>
            Show optional personal extras (DOB, nationality…)
          </label>
          <label class="check d-block mt-1">
            <input type="checkbox" name="show_signature" value="1"<?= $showSignature ? ' checked' : '' ?>>
            Prefer signature section when filled
          </label>
          <button type="submit" class="btn btn-primary mt-3">Save layout</button>
        </form>
      </section>

      <section class="studio-panel" id="panel-profile" data-panel="profile">
        <h2 class="h5"><?= App::e(ResumeLayout::ui('profile', $uiLang)) ?></h2>
        <form method="post" class="form" enctype="multipart/form-data">
          <input type="hidden" name="action" value="save_profile">
          <input type="hidden" name="id" value="<?= (int) $profile['id'] ?>">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label" for="full_name">Full name</label>
              <input class="form-control" type="text" id="full_name" name="full_name" required value="<?= App::e($profile['full_name']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label" for="title">Professional title</label>
              <input class="form-control" type="text" id="title" name="title" value="<?= App::e($profile['title']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input class="form-control" type="email" id="email" name="email" value="<?= App::e($profile['email']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="phone">Phone</label>
              <input class="form-control" type="text" id="phone" name="phone" value="<?= App::e($profile['phone']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label" for="location">City / location</label>
              <input class="form-control" type="text" id="location" name="location" value="<?= App::e($profile['location']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="gender">Gender (optional)</label>
              <select class="form-select" id="gender" name="gender">
                <?php
                $gender = (string) ($profile['gender'] ?? '');
                $genders = ['' => '— Hide —', 'Male' => 'Male', 'Female' => 'Female', 'Non-binary' => 'Non-binary', 'Other' => 'Other'];
                foreach ($genders as $val => $label):
                ?>
                  <option value="<?= App::e($val) ?>"<?= $gender === $val ? ' selected' : '' ?>><?= App::e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="date_of_birth">Date of birth</label>
              <input class="form-control" type="date" id="date_of_birth" name="date_of_birth" value="<?= App::e((string) ($profile['date_of_birth'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="country">Country</label>
              <input class="form-control" type="text" id="country" name="country" value="<?= App::e((string) ($profile['country'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="nationality">Nationality</label>
              <input class="form-control" type="text" id="nationality" name="nationality" value="<?= App::e((string) ($profile['nationality'] ?? '')) ?>">
            </div>
          </div>
          <fieldset class="photo-fieldset mt-3">
            <legend>Application photo</legend>
            <?php $photoUrl = App::photoUrl($profile); ?>
            <?php if ($photoUrl !== ''): ?>
              <div class="photo-preview mb-2"><img src="<?= App::e($photoUrl) ?>" alt="" style="width:84px;height:105px;object-fit:cover;border-radius:2px"></div>
            <?php endif; ?>
            <input class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
            <label class="check d-block mt-2">
              <input type="checkbox" name="show_photo" value="1"<?= (int) ($profile['show_photo'] ?? 1) === 1 ? ' checked' : '' ?>>
              Allow photo when photo mode is “with photo”
            </label>
            <?php if ($photoUrl !== ''): ?>
              <label class="check d-block"><input type="checkbox" name="remove_photo" value="1"> Remove photo</label>
            <?php endif; ?>
          </fieldset>
          <fieldset class="links-fieldset mt-3">
            <legend>Links</legend>
            <?php foreach ($links as $link): ?>
              <div class="link-row d-flex gap-2 mb-2">
                <input class="form-control" type="text" name="link_label[]" placeholder="Label" value="<?= App::e($link['label'] ?? '') ?>">
                <input class="form-control" type="url" name="link_url[]" placeholder="https://" value="<?= App::e($link['url'] ?? '') ?>">
              </div>
            <?php endforeach; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-link>Add link</button>
          </fieldset>
          <button type="submit" class="btn btn-primary mt-3">Save profile</button>
        </form>
      </section>

      <section class="studio-panel" id="panel-experience" data-panel="experience">
        <h2 class="h5"><?= App::e(ResumeLayout::sectionLabel('experience', $uiLang)) ?></h2>
        <form method="post" class="section-order-form" data-section-sorter>
          <input type="hidden" name="action" value="save_experiences">
          <div class="section-sort-list" data-sort-list>
            <?php foreach ($experiences as $job): ?>
              <?php $jid = (int) $job['id']; ?>
              <div class="section-sort-item experience-edit-item mb-3" data-sort-item draggable="true">
                <input type="hidden" name="experience_id[]" value="<?= $jid ?>">
                <?php editor_render_sort_controls(); ?>
                <div class="section-sort-body">
                  <label class="form-label">Position <input class="form-control" type="text" name="position[<?= $jid ?>]" value="<?= App::e($job['position']) ?>" required></label>
                  <label class="form-label">Company <input class="form-control" type="text" name="company[<?= $jid ?>]" value="<?= App::e($job['company']) ?>" required></label>
                  <label class="form-label">Location <input class="form-control" type="text" name="location[<?= $jid ?>]" value="<?= App::e($job['location']) ?>"></label>
                  <div class="row g-2">
                    <div class="col-6"><label class="form-label">Start <input class="form-control" type="text" name="start_date[<?= $jid ?>]" value="<?= App::e($job['start_date']) ?>" placeholder="03/2024"></label></div>
                    <div class="col-6"><label class="form-label">End <input class="form-control" type="text" name="end_date[<?= $jid ?>]" value="<?= App::e($job['end_date']) ?>" placeholder="heute"></label></div>
                  </div>
                  <label class="check"><input type="checkbox" name="visible[<?= $jid ?>]" value="1"<?= (int) $job['visible'] === 1 ? ' checked' : '' ?>> Visible</label>
                  <label class="form-label">Bullets<textarea class="form-control" name="bullets[<?= $jid ?>]" rows="5"><?= App::e($job['bullets']) ?></textarea></label>
                  <button type="submit" form="experience-delete-<?= $jid ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?');">Delete</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary">Save experience</button>
        </form>
        <?php foreach ($experiences as $job): ?>
          <form method="post" id="experience-delete-<?= (int) $job['id'] ?>" hidden>
            <input type="hidden" name="action" value="delete_experience">
            <input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
          </form>
        <?php endforeach; ?>
        <form method="post" class="form mt-4">
          <h3 class="h6">Add role</h3>
          <input type="hidden" name="action" value="add_experience">
          <label class="form-label">Company <input class="form-control" name="company" required></label>
          <label class="form-label">Position <input class="form-control" name="position" required></label>
          <label class="form-label">Location <input class="form-control" name="location"></label>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Start <input class="form-control" name="start_date" placeholder="03/2024"></label></div>
            <div class="col-6"><label class="form-label">End <input class="form-control" name="end_date" placeholder="heute"></label></div>
          </div>
          <label class="form-label">Bullets <textarea class="form-control" name="bullets" rows="4"></textarea></label>
          <button type="submit" class="btn btn-primary">Add</button>
        </form>
      </section>

      <section class="studio-panel" id="panel-sections" data-panel="sections">
        <h2 class="h5"><?= App::e(ResumeLayout::ui('sections', $uiLang)) ?></h2>
        <form method="post" class="section-order-form" data-section-sorter>
          <input type="hidden" name="action" value="save_sections">
          <div class="section-sort-list" data-sort-list>
            <?php foreach ($sections as $section): ?>
              <?php
              $sid = (int) $section['id'];
              $isExperience = ($section['section_key'] ?? '') === 'experience';
              ?>
              <div class="section-sort-item mb-3" data-sort-item draggable="true" id="section-<?= $sid ?>">
                <input type="hidden" name="section_id[]" value="<?= $sid ?>">
                <?php editor_render_sort_controls(); ?>
                <div class="section-sort-body">
                  <label class="form-label">Title <input class="form-control" type="text" name="title[<?= $sid ?>]" value="<?= App::e($section['title']) ?>"></label>
                  <label class="check"><input type="checkbox" name="visible[<?= $sid ?>]" value="1"<?= (int) $section['visible'] === 1 ? ' checked' : '' ?>> Visible</label>
                  <?php if ($isExperience): ?>
                    <p class="small text-muted">Managed under Experience.</p>
                    <input type="hidden" name="body[<?= $sid ?>]" value="">
                  <?php else: ?>
                    <label class="form-label">Body <textarea class="form-control" name="body[<?= $sid ?>]" rows="7"><?= App::e($section['body']) ?></textarea></label>
                  <?php endif; ?>
                  <button type="submit" form="section-delete-<?= $sid ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete section?');">Delete</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary">Save sections</button>
        </form>
        <?php foreach ($sections as $section): ?>
          <form method="post" id="section-delete-<?= (int) $section['id'] ?>" hidden>
            <input type="hidden" name="action" value="delete_section">
            <input type="hidden" name="id" value="<?= (int) $section['id'] ?>">
          </form>
        <?php endforeach; ?>
        <form method="post" class="form mt-4">
          <h3 class="h6">Add section</h3>
          <input type="hidden" name="action" value="add_section">
          <label class="form-label">Key <input class="form-control" name="section_key" placeholder="languages"></label>
          <label class="form-label">Title <input class="form-control" name="title"></label>
          <label class="form-label">Body <textarea class="form-control" name="body" rows="3"></textarea></label>
          <button type="submit" class="btn btn-primary">Add</button>
        </form>
      </section>

      <section class="studio-panel" id="panel-check" data-panel="check">
        <h2 class="h5"><?= App::e(ResumeLayout::ui('check', $uiLang)) ?></h2>
        <div class="resume-check-panel" data-check-panel>
          <p class="small mb-2">
            <?= (int) $check['summary']['errors'] ?> errors ·
            <?= (int) $check['summary']['warnings'] ?> warnings ·
            <?= (int) $check['summary']['info'] ?> notes
          </p>
          <ul class="list-unstyled mb-3" data-check-list>
            <?php foreach ($check['issues'] as $issue): ?>
              <li class="issue-<?= App::e($issue['level']) ?> mb-1">• <?= App::e($issue['message']) ?></li>
            <?php endforeach; ?>
            <?php if ($check['issues'] === []): ?>
              <li class="text-muted">No issues detected.</li>
            <?php endif; ?>
          </ul>
          <?php if ($match): ?>
            <div class="border rounded p-2 bg-light">
              <strong><?= App::e(ResumeLayout::ui('match', $uiLang)) ?>: <?= (int) $match['overall'] ?>%</strong>
              <p class="small mb-0 mt-1">Skills <?= (int) $match['skills'] ?>% · Experience <?= (int) $match['experience'] ?>% · Education <?= (int) $match['education'] ?>% · Keywords <?= (int) $match['keywords'] ?>% · Languages <?= (int) $match['languages'] ?>%</p>
              <p class="small text-muted mb-0">Estimate only — not an ATS guarantee.</p>
            </div>
          <?php else: ?>
            <p class="small text-muted">Link a JD via Applications / tailor to see a match estimate.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</main>
<script>
(function () {
  var root = document.querySelector('[data-resume-studio]');
  if (!root) return;
  var iframe = root.querySelector('[data-studio-preview]');
  var badge = document.getElementById('studio-page-badge');
  var panels = root.querySelectorAll('[data-panel]');
  var nav = root.querySelectorAll('[data-studio-panel]');

  function showPanel(name) {
    panels.forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-panel') === name); });
    nav.forEach(function (n) { n.classList.toggle('is-active', n.getAttribute('data-studio-panel') === name); });
  }
  nav.forEach(function (n) {
    n.addEventListener('click', function (e) {
      e.preventDefault();
      showPanel(n.getAttribute('data-studio-panel'));
    });
  });

  function refreshPreview() {
    if (!iframe) return;
    var url = root.getAttribute('data-preview-base') || iframe.src;
    iframe.src = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_ts=' + Date.now();
  }
  var refreshBtn = root.querySelector('[data-studio-refresh]');
  if (refreshBtn) refreshBtn.addEventListener('click', refreshPreview);

  function updatePages() {
    try {
      var doc = iframe.contentDocument;
      if (!doc) return;
      var article = doc.querySelector('.resume');
      if (!article) return;
      var h = article.scrollHeight || article.offsetHeight;
      var pages = Math.max(1, Math.ceil(h / (297 * 3.78))); // ~px per mm at 96dpi
      // Prefer mm via CSS pixels: 1mm ≈ 3.7795px
      pages = Math.max(1, Math.ceil(h / 1122.5));
      if (badge) badge.textContent = pages + ' / 2 pages';
      var checkUrl = root.getAttribute('data-check-url') || '/resume-check';
      fetch(checkUrl + (checkUrl.indexOf('?') >= 0 ? '&' : '?') + 'pages=' + encodeURIComponent(pages))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.check) return;
          var list = root.querySelector('[data-check-list]');
          if (!list) return;
          list.innerHTML = '';
          (data.check.issues || []).forEach(function (issue) {
            var li = document.createElement('li');
            li.className = 'issue-' + issue.level + ' mb-1';
            li.textContent = '• ' + issue.message;
            list.appendChild(li);
          });
          if (!(data.check.issues || []).length) {
            list.innerHTML = '<li class="text-muted">No issues detected.</li>';
          }
        }).catch(function () {});
    } catch (e) {}
  }
  if (iframe) {
    iframe.addEventListener('load', updatePages);
  }
})();
</script>
<?php
layout_footer();
