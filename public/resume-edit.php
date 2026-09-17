<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Resume\ResumeLayout;
use KaamFit\Resume\ResumePhotoMode;

Versions::ensureSchema();

if (isset($_GET['version']) && (int) $_GET['version'] > 0) {
    try {
        Versions::loadResumeVersion((int) $_GET['version']);
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/documents');
    }
}

$active = Versions::activeResumeVersion();
$base = Versions::baseResumeVersion();
$isMain = $active ? Versions::isMasterResume($active) : true;
$resumeLabel = $active ? Versions::resumeUiLabel($active) : 'Main Resume';
$versionId = $active ? (int) $active['id'] : ($base ? (int) $base['id'] : 0);

$meta = ResumeLayout::mergeMeta(
    $active ? (Versions::decodeSnapshot((string) ($active['snapshot'] ?? ''))['meta'] ?? []) : []
);
$template = (string) ($meta['template'] ?? ResumeLayout::resolveTemplate(null));
$density = (string) ($meta['density'] ?? ResumeLayout::resolveDensity(null));
$previewQs = [
    'embed' => '1',
    'pdf' => '1',
    'theme' => $template,
    'density' => $density,
];
if ($versionId > 0) {
    $previewQs['version'] = $versionId;
}
$previewUrl = '/resume?' . http_build_query($previewQs);

$pdfExtra = $versionId > 0 ? ['version' => $versionId] : [];
$csrf = Csrf::token();

layout_header('Resume Studio', [
    'body_class' => 'page-resume-studio',
    'chrome' => 'resume',
]);
?>
<div
  class="rb-studio"
  id="resumeStudio"
  data-csrf="<?= App::e($csrf) ?>"
  data-api="/resume-api.php"
  data-version-id="<?= (int) $versionId ?>"
  data-preview-base="/resume"
  data-is-main="<?= $isMain ? '1' : '0' ?>"
>
  <header class="rb-toolbar">
    <div class="rb-toolbar__left">
      <a class="rb-back" href="/documents">← My Resumes</a>
      <div class="rb-title-block">
        <h1 class="rb-title"><?= App::e($resumeLabel) ?></h1>
        <?php if ($isMain): ?><span class="badge badge-main">MAIN</span><?php endif; ?>
      </div>
      <span class="rb-save-state" data-rb-save-state aria-live="polite">Saved ✓</span>
    </div>
    <div class="rb-toolbar__right">
      <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" data-rb-mobile-tab="content">Content</button>
      <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" data-rb-mobile-tab="preview">Preview</button>
      <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" data-rb-mobile-tab="design">Design</button>
      <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#rbTailorModal">Tailor for Job</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rbDupModal">Duplicate</button>
      <a class="btn btn-sm btn-outline-secondary" href="<?= App::e('/resume?' . http_build_query($versionId > 0 ? ['version' => $versionId] : [])) ?>" target="_blank" rel="noopener">Preview</a>
      <div class="dropdown">
        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Download</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= App::e(PdfExport::downloadHrefOriginal('resume', $pdfExtra)) ?>" data-download-pdf data-doc="resume">Download PDF</a></li>
          <li><a class="dropdown-item" href="<?= App::e(PdfExport::downloadHrefAts('resume', $pdfExtra)) ?>">Download ATS PDF</a></li>
        </ul>
      </div>
    </div>
  </header>

  <nav class="rb-mobile-tabs d-lg-none" role="tablist" aria-label="Editor panels">
    <button type="button" class="rb-mobile-tab is-active" data-rb-mobile-tab="content" aria-selected="true">Content</button>
    <button type="button" class="rb-mobile-tab" data-rb-mobile-tab="preview" aria-selected="false">Preview</button>
    <button type="button" class="rb-mobile-tab" data-rb-mobile-tab="design" aria-selected="false">Design</button>
  </nav>

  <div class="rb-panels">
    <aside class="rb-panel rb-panel--content is-active" data-rb-panel="content" aria-label="Content">
      <div class="rb-section-nav" data-rb-section-list>
        <p class="rb-panel-label">Sections</p>
        <ul class="rb-section-list" data-rb-sortable role="list"></ul>
        <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-2" data-bs-toggle="modal" data-bs-target="#rbAddSectionModal">+ Add Section</button>
      </div>
      <div class="rb-section-editor" data-rb-editor>
        <p class="text-secondary small mb-0">Select a section to edit.</p>
      </div>
    </aside>

    <section class="rb-panel rb-panel--preview" data-rb-panel="preview" aria-label="Live preview">
      <div class="rb-preview-controls">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-rb-zoom="-">−</button>
        <span data-rb-zoom-label>100%</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-rb-zoom="+">+</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-rb-zoom-fit>Fit</button>
        <span class="rb-page-count" data-rb-page-count>Page —</span>
      </div>
      <div class="rb-preview-stage" data-rb-preview-stage>
        <iframe
          class="rb-preview-frame"
          data-rb-preview
          title="Resume preview"
          src="<?= App::e($previewUrl) ?>"
        ></iframe>
      </div>
    </section>

    <aside class="rb-panel rb-panel--design" data-rb-panel="design" aria-label="Design">
      <p class="rb-panel-label">Template</p>
      <div class="rb-template-grid" data-rb-templates>
        <?php foreach (ResumeLayout::TEMPLATES as $key => $info): ?>
          <button
            type="button"
            class="rb-template-card<?= $template === $key ? ' is-active' : '' ?>"
            data-rb-template="<?= App::e($key) ?>"
            aria-pressed="<?= $template === $key ? 'true' : 'false' ?>"
          >
            <span class="rb-template-thumb theme-<?= App::e($key) ?>"></span>
            <span class="rb-template-name"><?= App::e((string) $info['label']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <p class="rb-panel-label mt-3">Color</p>
      <div class="rb-accent-row" data-rb-accents>
        <?php
        $accents = ['#17365D', '#1F4E79', '#0F766E', '#B45309', '#7C2D12', '#5B4CDB', '#111827'];
        $currentAccent = App::resolveAccent($meta['accent_color'] ?? null);
        foreach ($accents as $hex):
        ?>
          <button type="button" class="rb-accent-swatch<?= strcasecmp($currentAccent, $hex) === 0 ? ' is-active' : '' ?>" data-rb-accent="<?= App::e($hex) ?>" style="--swatch:<?= App::e($hex) ?>" aria-label="Accent <?= App::e($hex) ?>"></button>
        <?php endforeach; ?>
        <label class="rb-accent-custom">
          <span class="visually-hidden">Custom accent</span>
          <input type="color" data-rb-accent-custom value="<?= App::e($currentAccent) ?>">
        </label>
      </div>

      <p class="rb-panel-label mt-3">Font</p>
      <select class="form-select form-select-sm" data-rb-font aria-label="Font">
        <?php
        $currentFont = App::resolveFont($meta['font_family'] ?? null);
        foreach (App::fonts() as $fkey => $fmeta):
        ?>
          <option value="<?= App::e($fkey) ?>"<?= $currentFont === $fkey ? ' selected' : '' ?>><?= App::e((string) $fmeta['label']) ?></option>
        <?php endforeach; ?>
      </select>

      <p class="rb-panel-label mt-3">Font size</p>
      <div class="btn-group btn-group-sm w-100" role="group" aria-label="Font size">
        <?php foreach (['sm' => 'S', 'md' => 'M', 'lg' => 'L'] as $sz => $lab): ?>
          <button type="button" class="btn btn-outline-secondary<?= ($meta['font_size'] ?? 'md') === $sz || App::resolveFontSize(null) === $sz ? ' active' : '' ?>" data-rb-font-size="<?= $sz ?>"><?= $lab ?></button>
        <?php endforeach; ?>
      </div>

      <p class="rb-panel-label mt-3">Line spacing</p>
      <select class="form-select form-select-sm" data-rb-density aria-label="Line spacing">
        <?php foreach (['compact' => 'Compact', 'tight' => 'Normal', 'normal' => 'Relaxed'] as $d => $lab): ?>
          <option value="<?= $d ?>"<?= $density === $d ? ' selected' : '' ?>><?= $lab ?></option>
        <?php endforeach; ?>
      </select>

      <p class="rb-panel-label mt-3">Page size</p>
      <select class="form-select form-select-sm" data-rb-page-format aria-label="Page size">
        <option value="A4"<?= ($meta['page_format'] ?? 'A4') === 'A4' ? ' selected' : '' ?>>A4</option>
        <option value="LETTER"<?= ($meta['page_format'] ?? '') === 'LETTER' ? ' selected' : '' ?>>Letter</option>
      </select>

      <p class="rb-panel-label mt-3">Margins</p>
      <select class="form-select form-select-sm" data-rb-margin aria-label="Margins">
        <?php foreach (['narrow' => 'Narrow', 'normal' => 'Normal', 'wide' => 'Wide'] as $m => $lab): ?>
          <option value="<?= $m ?>"<?= ($meta['margin'] ?? 'normal') === $m ? ' selected' : '' ?>><?= $lab ?></option>
        <?php endforeach; ?>
      </select>

      <p class="rb-panel-label mt-3">Date format</p>
      <select class="form-select form-select-sm" data-rb-date-format aria-label="Date format">
        <?php foreach (['MM/YYYY', 'MMM YYYY', 'YYYY'] as $df): ?>
          <option value="<?= App::e($df) ?>"<?= ($meta['date_format'] ?? 'MMM YYYY') === $df ? ' selected' : '' ?>><?= App::e($df) ?></option>
        <?php endforeach; ?>
      </select>

      <p class="rb-panel-label mt-3">Photo</p>
      <select class="form-select form-select-sm" data-rb-photo-mode aria-label="Photo mode">
        <?php
        $photoMode = ResumePhotoMode::resolve($meta['photo_mode'] ?? null);
        foreach ([
            ResumePhotoMode::WITH_PHOTO => 'Show photo',
            ResumePhotoMode::WITHOUT_PHOTO => 'Hide photo',
            ResumePhotoMode::ANONYMIZED => 'Anonymized',
        ] as $pm => $lab):
        ?>
          <option value="<?= App::e($pm) ?>"<?= $photoMode === $pm ? ' selected' : '' ?>><?= App::e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </aside>
  </div>
</div>

<!-- Add section -->
<div class="modal fade" id="rbAddSectionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" data-rb-add-section>
      <div class="modal-header">
        <h2 class="modal-title fs-5">Add Section</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="rbAddType">Section type</label>
        <select class="form-select" id="rbAddType" name="type" required>
          <?php foreach (ResumeLayout::addableSectionKeys() as $key): ?>
            <option value="<?= App::e($key) ?>"><?= App::e($key === 'custom' ? 'Custom Section' : ResumeLayout::sectionLabel($key)) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="mt-3" data-rb-custom-title-wrap hidden>
          <label class="form-label" for="rbCustomTitle">Custom title</label>
          <input class="form-control" id="rbCustomTitle" name="title" placeholder="Section title">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Duplicate -->
<div class="modal fade" id="rbDupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" data-rb-duplicate>
      <div class="modal-header">
        <h2 class="modal-title fs-5">Duplicate Resume</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="rbDupTitle">Name</label>
        <input class="form-control" id="rbDupTitle" name="title" value="<?= App::e($resumeLabel . ' (copy)') ?>" required>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="copy_content" id="rbDupContent" checked>
          <label class="form-check-label" for="rbDupContent">Copy content</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="copy_section_order" id="rbDupOrder" checked>
          <label class="form-check-label" for="rbDupOrder">Copy section order</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="copy_design" id="rbDupDesign" checked>
          <label class="form-check-label" for="rbDupDesign">Copy design</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create copy</button>
      </div>
    </form>
  </div>
</div>

<!-- Tailor -->
<div class="modal fade" id="rbTailorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" data-rb-tailor>
      <div class="modal-header">
        <h2 class="modal-title fs-5">Tailor for Job</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-secondary small">Creates a <strong>new</strong> job-specific resume from Main. Your Main Resume is never modified.</p>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label" for="rbTailorRole">Job title</label>
            <input class="form-control" id="rbTailorRole" name="role" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="rbTailorCompany">Company</label>
            <input class="form-control" id="rbTailorCompany" name="company" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="rbTailorLocation">Location</label>
            <input class="form-control" id="rbTailorLocation" name="location" placeholder="e.g. München, Germany" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="rbTailorLink">Job link (optional)</label>
            <input class="form-control" id="rbTailorLink" name="link" type="url">
          </div>
          <div class="col-12">
            <label class="form-label" for="rbTailorJd">Job description</label>
            <textarea class="form-control" id="rbTailorJd" name="jd" rows="8" required></textarea>
          </div>
        </div>
        <div class="mt-3" data-rb-tailor-preview hidden>
          <h3 class="fs-6">Suggestions (review before applying)</h3>
          <div data-rb-tailor-keywords class="small mb-2"></div>
          <label class="form-label" for="rbTailorSummary">Summary override (optional)</label>
          <textarea class="form-control mb-2" id="rbTailorSummary" name="summary" rows="4"></textarea>
          <label class="form-label" for="rbTailorSkills">Skills override (optional)</label>
          <textarea class="form-control" id="rbTailorSkills" name="skills" rows="4"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-rb-tailor-analyze>Analyze Job</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create tailored copy</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/resume-studio.js?v=20260917a"></script>
<?php
layout_footer();
