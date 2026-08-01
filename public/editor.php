<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$pdo = Db::pdo();

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

        $profileId = (int) ($_POST['id'] ?? 0);
        $current = App::profile();
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
            'UPDATE resume_profile SET full_name = ?, title = ?, email = ?, phone = ?, location = ?, gender = ?, date_of_birth = ?, country = ?, nationality = ?, photo_path = ?, show_photo = ?, links = ? WHERE id = ?'
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

        $stmt = $pdo->prepare(
            'UPDATE resume_sections SET title = ?, body = ?, visible = ?, sort_order = ? WHERE id = ?'
        );
        $order = 10;
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            $key = (string) $id;
            $stmt->execute([
                trim((string) ($titles[$key] ?? '')),
                (string) ($bodies[$key] ?? ''),
                isset($visibles[$key]) ? 1 : 0,
                $order,
                $id,
            ]);
            $order += 10;
        }
        App::flash('Sections saved.');
        App::redirect('/editor.php#sections');
    }

    if ($action === 'add_section') {
        $key = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($_POST['section_key'] ?? '')))) ?: ('custom_' . time());
        $max = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM resume_sections')->fetchColumn();
        $stmt = $pdo->prepare(
            'INSERT INTO resume_sections (section_key, title, body, sort_order, visible) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([
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
        $stmt = $pdo->prepare('DELETE FROM resume_sections WHERE id = ?');
        $stmt->execute([$id]);
        App::flash('Section deleted.');
        App::redirect('/editor.php#sections');
    }

    if ($action === 'save_cover') {
        $id = (int) ($_POST['id'] ?? 0);
        $makeActive = isset($_POST['is_active']);
        if ($makeActive) {
            $pdo->exec('UPDATE cover_letters SET is_active = 0');
        }
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE cover_letters SET title = ?, body = ?, company = ?, is_active = ? WHERE id = ?'
            );
            $stmt->execute([
                trim((string) ($_POST['title'] ?? '')),
                (string) ($_POST['body'] ?? ''),
                trim((string) ($_POST['company'] ?? '')),
                $makeActive ? 1 : 0,
                $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO cover_letters (title, body, company, is_active) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                trim((string) ($_POST['title'] ?? 'Cover letter')) ?: 'Cover letter',
                (string) ($_POST['body'] ?? ''),
                trim((string) ($_POST['company'] ?? '')),
                $makeActive ? 1 : 1,
            ]);
        }
        App::flash('Cover letter saved.');
        App::redirect('/editor.php#cover');
    }

    if ($action === 'save_design') {
        $theme = App::resolveTheme($_POST['theme'] ?? null);
        $color = App::resolveAccent($_POST['accent_color'] ?? null);
        App::setSetting('theme', $theme);
        App::setSetting('accent_color', $color);
        App::setSetting('pdf_mode', isset($_POST['pdf_mode']) ? '1' : '0');
        App::setSetting('active_company', trim((string) ($_POST['active_company'] ?? '')));
        App::flash('Design settings saved.');
        App::redirect('/editor.php#design');
    }

    App::flash('Unknown action.', 'error');
    App::redirect('/editor.php');
}

$profile = App::profile();
$sections = App::sections(false);
$letter = App::activeCoverLetter();
$theme = App::setting('theme', 'classic') ?: 'classic';
$accent = App::setting('accent_color', '#1a5f4a') ?: '#1a5f4a';
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
    <p>Edit resume sections, cover letter text, and design. Preview with the links on the right.</p>
    <div class="preview-links">
      <a class="btn btn-small" href="/resume.php" target="_blank" rel="noopener">Preview resume</a>
      <a class="btn btn-small" href="/cover-letter.php" target="_blank" rel="noopener">Preview cover letter</a>
    </div>
  </header>

  <div class="editor-grid">
    <aside class="editor-nav">
      <a href="#design">Design</a>
      <a href="#profile">Profile</a>
      <a href="#sections">Sections</a>
      <a href="#cover">Cover letter</a>
    </aside>

    <div class="editor-main">
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
            Accent color
            <input type="color" name="accent_color" value="<?= App::e($accent) ?>">
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
          <label>Phone <input type="text" name="phone" value="<?= App::e($profile['phone']) ?>"></label>
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

      <section class="editor-block" id="sections">
        <h2>Resume sections</h2>
        <p class="empty" style="margin-top:0">Use the arrows (or drag) to reorder, then click <strong>Save</strong> to keep content and order.</p>

        <form method="post" class="section-order-form" data-section-sorter>
          <input type="hidden" name="action" value="save_sections">
          <div class="section-sort-list" data-sort-list>
            <?php foreach ($sections as $section): ?>
              <?php $sid = (int) $section['id']; ?>
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
                  <label>Body
                    <textarea name="body[<?= $sid ?>]" rows="8"><?= App::e($section['body']) ?></textarea>
                  </label>
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
        <h2>Cover letter</h2>
        <form method="post" class="form">
          <input type="hidden" name="action" value="save_cover">
          <input type="hidden" name="id" value="<?= (int) ($letter['id'] ?? 0) ?>">
          <label>Title <input type="text" name="title" value="<?= App::e($letter['title'] ?? 'Cover letter') ?>"></label>
          <label>Company <input type="text" name="company" value="<?= App::e($letter['company'] ?? '') ?>"></label>
          <label>Body
            <textarea name="body" rows="16"><?= App::e($letter['body'] ?? '') ?></textarea>
          </label>
          <label class="check">
            <input type="checkbox" name="is_active" value="1"<?= empty($letter) || (int) ($letter['is_active'] ?? 0) === 1 ? ' checked' : '' ?>>
            Active cover letter
          </label>
          <button type="submit" class="btn btn-primary">Save cover letter</button>
        </form>
      </section>
    </div>
  </div>
</main>
<?php
layout_footer();
