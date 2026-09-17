<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Resume\ResumeLayout;

Versions::ensureSchema();

// Accept both ?id= and ?cover=
$editId = 0;
if (isset($_GET['id']) && (int) $_GET['id'] > 0) {
    $editId = (int) $_GET['id'];
} elseif (isset($_GET['cover']) && (int) $_GET['cover'] > 0) {
    $editId = (int) $_GET['cover'];
}
if ($editId > 0) {
    $picked = Versions::coverLetterById($editId);
    if ($picked) {
        Versions::activateCover($editId);
    }
}

$letter = App::activeCoverLetter();
if (empty($letter['id'])) {
    App::flash('Pick a cover letter first.', 'error');
    App::redirect('/cover');
}

$isMain = Versions::isMasterCover($letter);
$label = Versions::coverUiLabel($letter);
$coverId = (int) $letter['id'];
$meta = [
    'template' => ResumeLayout::resolveTemplate(null),
    'font_family' => App::resolveFont(null),
    'accent_color' => App::resolveAccent(null),
    'font_size' => App::resolveFontSize(null),
    'section_spacing' => App::setting('section_spacing', 'md') ?: 'md',
    'density' => ResumeLayout::resolveDensity(null),
    'page_format' => ResumeLayout::resolvePageFormat(null),
    'margin' => ResumeLayout::resolveMargin(null),
];
$headerVis = App::coverHeaderVisibility();
$previewUrl = '/cover-letter?' . http_build_query([
    'embed' => '1',
    'pdf' => '1',
    'id' => $coverId,
    'theme' => $meta['template'],
]);
$csrf = Csrf::token();

layout_header('Cover Letter Studio', [
    'body_class' => 'page-cover-studio page-resume-studio',
    'chrome' => 'cover',
]);
?>
<div
  class="rb-studio"
  id="coverStudio"
  data-csrf="<?= App::e($csrf) ?>"
  data-api="/cover-api.php"
  data-cover-id="<?= $coverId ?>"
  data-preview-base="/cover-letter"
  data-is-main="<?= $isMain ? '1' : '0' ?>"
>
  <header class="rb-toolbar">
    <div class="rb-toolbar__left">
      <a class="rb-back" href="/cover">← My Cover Letters</a>
      <div class="rb-title-block">
        <h1 class="rb-title"><?= App::e($label) ?></h1>
        <?php if ($isMain): ?><span class="badge badge-main">MAIN</span><?php endif; ?>
      </div>
      <span class="rb-save-state" data-cl-save-state aria-live="polite">Saved ✓</span>
    </div>
    <div class="rb-toolbar__right">
      <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" data-cl-mobile-tab="content">Content</button>
      <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" data-cl-mobile-tab="preview">Preview</button>
      <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" data-cl-mobile-tab="design">Design</button>
      <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#clTailorModal">Tailor for Job</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#clDupModal">Duplicate</button>
      <a class="btn btn-sm btn-outline-secondary" href="/cover-letter?id=<?= $coverId ?>" target="_blank" rel="noopener">Preview</a>
      <div class="dropdown">
        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Download</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= App::e(PdfExport::downloadHrefOriginal('cover', ['id' => $coverId])) ?>" data-download-pdf data-doc="cover">Download PDF</a></li>
          <li><a class="dropdown-item" href="<?= App::e(PdfExport::downloadHrefAts('cover', ['id' => $coverId])) ?>">Download ATS PDF</a></li>
        </ul>
      </div>
    </div>
  </header>

  <nav class="rb-mobile-tabs d-lg-none" role="tablist" aria-label="Editor panels">
    <button type="button" class="rb-mobile-tab is-active" data-cl-mobile-tab="content" aria-selected="true">Content</button>
    <button type="button" class="rb-mobile-tab" data-cl-mobile-tab="preview" aria-selected="false">Preview</button>
    <button type="button" class="rb-mobile-tab" data-cl-mobile-tab="design" aria-selected="false">Design</button>
  </nav>

  <div class="rb-panels">
    <aside class="rb-panel rb-panel--content is-active" data-cl-panel="content" aria-label="Content">
      <p class="rb-panel-label">Content</p>
      <ul class="rb-section-list" data-cl-nav>
        <li class="rb-section-item is-active"><button type="button" class="rb-section-item__btn" data-cl-nav="meta">Name &amp; company</button></li>
        <li class="rb-section-item"><button type="button" class="rb-section-item__btn" data-cl-nav="recipient">Recipient</button></li>
        <li class="rb-section-item"><button type="button" class="rb-section-item__btn" data-cl-nav="date">Date &amp; subject</button></li>
        <li class="rb-section-item"><button type="button" class="rb-section-item__btn" data-cl-nav="greeting">Greeting</button></li>
        <li class="rb-section-item"><button type="button" class="rb-section-item__btn" data-cl-nav="body">Paragraphs</button></li>
        <li class="rb-section-item"><button type="button" class="rb-section-item__btn" data-cl-nav="closing">Closing &amp; sign-off</button></li>
        <li class="rb-section-item"><button type="button" class="rb-section-item__btn" data-cl-nav="plain">Plain text mode</button></li>
      </ul>
      <div class="rb-section-editor mt-3" data-cl-editor></div>
    </aside>

    <section class="rb-panel rb-panel--preview" data-cl-panel="preview" aria-label="Live preview">
      <div class="rb-preview-controls">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-cl-zoom="-">−</button>
        <span data-cl-zoom-label>100%</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-cl-zoom="+">+</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-cl-zoom-fit>Fit</button>
      </div>
      <div class="rb-preview-stage">
        <iframe
          class="rb-preview-frame"
          data-cl-preview
          title="Cover letter preview"
          src="<?= App::e($previewUrl) ?>"
        ></iframe>
      </div>
    </section>

    <aside class="rb-panel rb-panel--design" data-cl-panel="design" aria-label="Design">
      <p class="rb-panel-label">Template</p>
      <div class="rb-template-grid">
        <?php foreach (ResumeLayout::TEMPLATES as $key => $info): ?>
          <button
            type="button"
            class="rb-template-card<?= $meta['template'] === $key ? ' is-active' : '' ?>"
            data-cl-template="<?= App::e($key) ?>"
            aria-pressed="<?= $meta['template'] === $key ? 'true' : 'false' ?>"
          >
            <span class="rb-template-thumb theme-<?= App::e($key) ?>"></span>
            <span class="rb-template-name"><?= App::e((string) $info['label']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <p class="rb-panel-label mt-3">Color</p>
      <div class="rb-accent-row">
        <?php
        $accents = ['#17365D', '#1F4E79', '#0F766E', '#B45309', '#7C2D12', '#5B4CDB', '#111827'];
        foreach ($accents as $hex):
        ?>
          <button type="button" class="rb-accent-swatch<?= strcasecmp($meta['accent_color'], $hex) === 0 ? ' is-active' : '' ?>" data-cl-accent="<?= App::e($hex) ?>" style="--swatch:<?= App::e($hex) ?>" aria-label="Accent <?= App::e($hex) ?>"></button>
        <?php endforeach; ?>
        <label class="rb-accent-custom">
          <span class="visually-hidden">Custom</span>
          <input type="color" data-cl-accent-custom value="<?= App::e($meta['accent_color']) ?>">
        </label>
      </div>

      <p class="rb-panel-label mt-3">Font</p>
      <select class="form-select form-select-sm" data-cl-font aria-label="Font">
        <?php foreach (App::fonts() as $fkey => $fmeta): ?>
          <option value="<?= App::e($fkey) ?>"<?= $meta['font_family'] === $fkey ? ' selected' : '' ?>><?= App::e((string) $fmeta['label']) ?></option>
        <?php endforeach; ?>
      </select>

      <p class="rb-panel-label mt-3">Font size</p>
      <div class="btn-group btn-group-sm w-100" role="group">
        <?php foreach (['sm' => 'S', 'md' => 'M', 'lg' => 'L'] as $sz => $lab): ?>
          <button type="button" class="btn btn-outline-secondary<?= $meta['font_size'] === $sz ? ' active' : '' ?>" data-cl-font-size="<?= $sz ?>"><?= $lab ?></button>
        <?php endforeach; ?>
      </div>

      <p class="rb-panel-label mt-3">Spacing</p>
      <select class="form-select form-select-sm" data-cl-spacing aria-label="Spacing">
        <?php foreach (['sm' => 'Compact', 'md' => 'Normal', 'lg' => 'Relaxed'] as $sp => $lab): ?>
          <option value="<?= $sp ?>"<?= $meta['section_spacing'] === $sp ? ' selected' : '' ?>><?= $lab ?></option>
        <?php endforeach; ?>
      </select>

      <p class="rb-panel-label mt-3">Page size</p>
      <select class="form-select form-select-sm" data-cl-page-format>
        <option value="A4"<?= $meta['page_format'] === 'A4' ? ' selected' : '' ?>>A4</option>
        <option value="LETTER"<?= $meta['page_format'] === 'LETTER' ? ' selected' : '' ?>>Letter</option>
      </select>

      <p class="rb-panel-label mt-3">Margins</p>
      <select class="form-select form-select-sm" data-cl-margin>
        <?php foreach (['narrow' => 'Narrow', 'normal' => 'Normal', 'wide' => 'Wide'] as $m => $lab): ?>
          <option value="<?= $m ?>"<?= $meta['margin'] === $m ? ' selected' : '' ?>><?= $lab ?></option>
        <?php endforeach; ?>
      </select>

      <p class="rb-panel-label mt-3">Header fields</p>
      <?php
      $headerLabels = [
          'cover_show_title' => 'Show job title',
          'cover_show_location' => 'Show location',
          'cover_show_phone' => 'Show phone',
          'cover_show_email' => 'Show email',
          'cover_show_links' => 'Show links',
          'cover_show_personal_extras' => 'Show personal extras',
      ];
      $visMap = [
          'cover_show_title' => 'title',
          'cover_show_location' => 'location',
          'cover_show_phone' => 'phone',
          'cover_show_email' => 'email',
          'cover_show_links' => 'links',
          'cover_show_personal_extras' => 'personal_extras',
      ];
      foreach ($headerLabels as $key => $lab):
          $checked = !empty($headerVis[$visMap[$key]]);
      ?>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="<?= App::e($key) ?>" data-cl-header="<?= App::e($key) ?>"<?= $checked ? ' checked' : '' ?>>
          <label class="form-check-label" for="<?= App::e($key) ?>"><?= App::e($lab) ?></label>
        </div>
      <?php endforeach; ?>
    </aside>
  </div>
</div>

<div class="modal fade" id="clDupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" data-cl-duplicate>
      <div class="modal-header">
        <h2 class="modal-title fs-5">Duplicate Cover Letter</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="clDupTitle">Name</label>
        <input class="form-control" id="clDupTitle" name="title" value="<?= App::e($label . ' (copy)') ?>" required>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="copy_content" id="clDupContent" checked>
          <label class="form-check-label" for="clDupContent">Copy content</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create copy</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="clTailorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" data-cl-tailor>
      <div class="modal-header">
        <h2 class="modal-title fs-5">Tailor for Job</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-secondary small">Creates a <strong>new</strong> job-specific resume and cover letter from Main. Main documents are never modified.</p>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label" for="clTailorRole">Job title</label>
            <input class="form-control" id="clTailorRole" name="role" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="clTailorCompany">Company</label>
            <input class="form-control" id="clTailorCompany" name="company" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="clTailorLocation">Location</label>
            <input class="form-control" id="clTailorLocation" name="location" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="clTailorLink">Job link (optional)</label>
            <input class="form-control" id="clTailorLink" name="link" type="url">
          </div>
          <div class="col-12">
            <label class="form-label" for="clTailorJd">Job description</label>
            <textarea class="form-control" id="clTailorJd" name="jd" rows="7" required></textarea>
          </div>
        </div>
        <div class="mt-3" data-cl-tailor-preview hidden>
          <div data-cl-tailor-keywords class="small mb-2"></div>
          <label class="form-label" for="clTailorBody">Cover letter override (optional — review before applying)</label>
          <textarea class="form-control" id="clTailorBody" name="cover_body" rows="8"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-cl-tailor-analyze>Analyze Job</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create tailored copy</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/cover-studio.js?v=20260917b"></script>
<?php
layout_footer();
