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
        App::redirect('/resume-edit');
    }

    App::flash('Unknown action.', 'error');
    App::redirect('/resume-edit');
}

$profile = App::profile();
$sections = App::sections(false);
$experiences = App::experiences(false);
$baseResume = Versions::baseResumeVersion();
$activeResume = Versions::activeResumeVersion();
$editingResumeName = 'Main resume';
$editingResumeId = 0;
if ($activeResume) {
    $editingResumeId = (int) $activeResume['id'];
    $editingResumeName = (int) ($activeResume['is_base'] ?? 0) === 1
        ? 'Main resume'
        : (string) $activeResume['title'];
} elseif ($baseResume) {
    $editingResumeId = (int) $baseResume['id'];
    $editingResumeName = 'Main resume';
}
$links = $profile['links'];
if (count($links) < 2) {
    $links[] = ['label' => '', 'url' => ''];
}

layout_header('Edit resume');
?>
<main class="editor">
  <header class="page-head">
    <h1>
      <?php if ($editingResumeId > 0): ?>
        <span class="doc-id">#<?= $editingResumeId ?></span>
      <?php endif; ?>
      <?= App::e($editingResumeName) ?>
    </h1>
    <p><a href="/editor">← My resumes</a></p>
    <div class="preview-links">
      <a class="btn btn-sm btn-outline-secondary" href="/resume" target="_blank" rel="noopener">Preview</a>
      <a class="btn btn-sm btn-outline-secondary" href="/design">Style</a>
      <?php
        $resumePdfQs = $editingResumeId > 0 ? ['version' => $editingResumeId] : [];
      ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?= App::e(PdfExport::downloadHref('resume', 'en', $resumePdfQs)) ?>">PDF EN</a>
      <a class="btn btn-sm btn-primary" href="<?= App::e(PdfExport::downloadHref('resume', 'de', $resumePdfQs)) ?>">PDF DE</a>
    </div>
  </header>

  <div class="editor-grid">
    <aside class="editor-nav nav flex-column">
      <a class="nav-link px-0" href="/editor">My resumes</a>
      <a class="nav-link px-0" href="#profile">Profile</a>
      <a class="nav-link px-0" href="#experience">Experience</a>
      <a class="nav-link px-0" href="#sections">Sections</a>
      <a class="nav-link px-0" href="/design">Style</a>
    </aside>

    <div class="editor-main">
      <div class="now-editing">
        <p>Editing this copy. Save writes the open snapshot.</p>
        <form method="post" class="form form-inline-actions">
          <input type="hidden" name="action" value="save_open_resume">
          <button type="submit" class="btn btn-primary">Save</button>
        </form>
      </div>

      <section class="editor-block" id="profile">
        <h2>Profile</h2>
        <form method="post" class="form" enctype="multipart/form-data">
          <input type="hidden" name="action" value="save_profile">
          <input type="hidden" name="id" value="<?= (int) $profile['id'] ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="full_name">Full name</label>
              <input class="form-control" type="text" id="full_name" name="full_name" required value="<?= App::e($profile['full_name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="title">Title</label>
              <input class="form-control" type="text" id="title" name="title" value="<?= App::e($profile['title']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input class="form-control" type="email" id="email" name="email" value="<?= App::e($profile['email']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="phone">Mobile</label>
              <input class="form-control" type="text" id="phone" name="phone" value="<?= App::e($profile['phone']) ?>" placeholder="+1 555 0100">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="location">Location</label>
              <input class="form-control" type="text" id="location" name="location" value="<?= App::e($profile['location']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="gender">Gender</label>
              <select class="form-select" id="gender" name="gender">
                <?php
                $gender = (string) ($profile['gender'] ?? '');
                $genders = ['' => '— Prefer not to say / hide —', 'Male' => 'Male', 'Female' => 'Female', 'Non-binary' => 'Non-binary', 'Other' => 'Other'];
                foreach ($genders as $val => $label):
                ?>
                  <option value="<?= App::e($val) ?>"<?= $gender === $val ? ' selected' : '' ?>><?= App::e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="date_of_birth">Date of birth</label>
              <input class="form-control" type="date" id="date_of_birth" name="date_of_birth" value="<?= App::e((string) ($profile['date_of_birth'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="country">Country</label>
              <input class="form-control" type="text" id="country" name="country" value="<?= App::e((string) ($profile['country'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="nationality">Nationality</label>
              <input class="form-control" type="text" id="nationality" name="nationality" value="<?= App::e((string) ($profile['nationality'] ?? '')) ?>">
            </div>
          </div>

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
            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-link>Add link</button>
          </fieldset>
          <p class="empty" style="margin:0">Empty fields are hidden on the resume.</p>
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
                  <button type="button" class="btn btn-sm btn-outline-secondary sort-btn" data-move-up title="Move up" aria-label="Move up">↑</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary sort-btn" data-move-down title="Move down" aria-label="Move down">↓</button>
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
                    <button type="submit" form="experience-delete-<?= $jid ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this experience entry?');">Delete</button>
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
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Company</label>
              <input class="form-control" type="text" name="company" required placeholder="Company name">
            </div>
            <div class="col-md-6">
              <label class="form-label">Position</label>
              <input class="form-control" type="text" name="position" required placeholder="Job title">
            </div>
            <div class="col-md-6">
              <label class="form-label">Location</label>
              <input class="form-control" type="text" name="location" placeholder="City, Country">
            </div>
            <div class="col-md-3">
              <label class="form-label">Start date</label>
              <input class="form-control" type="text" name="start_date" placeholder="Oct 2025">
            </div>
            <div class="col-md-3">
              <label class="form-label">End date</label>
              <input class="form-control" type="text" name="end_date" placeholder="Present">
            </div>
            <div class="col-12">
              <label class="form-label">Bullets / details</label>
              <textarea class="form-control" name="bullets" rows="5" placeholder="• First achievement&#10;• Second achievement"></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Add experience</button>
            </div>
          </div>
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
                  <button type="button" class="btn btn-sm btn-outline-secondary sort-btn" data-move-up title="Move up" aria-label="Move up">↑</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary sort-btn" data-move-down title="Move down" aria-label="Move down">↓</button>
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
                    <button type="submit" form="section-delete-<?= $sid ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this section?');">Delete</button>
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
    </div>
  </div>
</main>
<?php
layout_footer();
