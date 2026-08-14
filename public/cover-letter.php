<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/doc.php';
require_once dirname(__DIR__) . '/src/profile_meta.php';

Versions::ensureSchema();

$opts = doc_view_options();
$profile = App::profile();
$coverId = (int) ($opts['coverId'] ?? 0);
$letter = $coverId > 0 ? Versions::coverLetterById($coverId) : App::activeCoverLetter();
$theme = $opts['theme'];
$accent = $opts['accent'];
$font = $opts['font'];
$embed = $opts['embed'];
$pdfMode = $opts['pdfMode'];
$exportOptions = Versions::coverExportOptions();

layout_header($profile['full_name'], [
    'body_class' => 'page-doc theme-' . $theme . ($embed ? ' is-embed' : ''),
    'theme' => $theme,
    'accent' => $accent,
    'font' => $font,
    'pdf_mode' => $pdfMode,
    'hide_nav' => $embed,
    'hide_flash' => $embed,
]);

if (!$embed):
?>
<main class="doc-toolbar no-print">
  <div class="doc-toolbar-inner d-flex flex-wrap justify-content-between align-items-center gap-2">
    <a class="btn btn-sm btn-link text-decoration-none" href="/cover-design.php">&larr; Style</a>
    <div class="doc-actions d-flex flex-wrap gap-2 align-items-center">
      <?php if ($letter): ?>
        <span class="badge rounded-pill text-bg-light border"><span class="doc-id">#<?= (int) $letter['id'] ?></span> <?= (int) ($letter['is_base'] ?? 0) === 1 ? 'Main cover letter' : App::e((string) ($letter['title'] ?? 'Cover letter')) ?></span>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="/cover-edit.php">Edit content</a>
      <a class="btn btn-sm btn-outline-secondary" href="/cover-design.php">Change style</a>
      <button type="button" class="btn btn-sm btn-primary" data-print data-doc="cover"
              data-export-options="<?= App::e(json_encode($exportOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>">Print</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-download-pdf data-doc="cover"
              data-export-options="<?= App::e(json_encode($exportOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>">Download PDF</button>
    </div>
  </div>
</main>
<?php endif; ?>

<article class="cover-letter theme-<?= App::e($theme) ?><?= $pdfMode ? ' pdf-ready' : '' ?>" data-doc="cover">
  <header class="letter-from">
    <strong><?= App::e($profile['full_name']) ?></strong>
    <?php if (App::filled($profile['title'] ?? null)): ?>
      <span><?= App::e($profile['title']) ?></span>
    <?php endif; ?>
    <?php render_profile_details($profile, true, true); ?>
  </header>

  <?php if ($letter): ?>
    <?php
    $companyLine = trim((string) ($letter['company'] ?? ''));
    // Don't repeat the letter title in the header line.
    ?>
    <?php if ($companyLine !== ''): ?>
      <p class="letter-company"><?= App::e($companyLine) ?></p>
    <?php endif; ?>
    <div class="letter-body"><?= App::nl2p($letter['body']) ?></div>
  <?php else: ?>
    <p class="empty">No active cover letter. <a href="/cover.php">Create one</a>.</p>
  <?php endif; ?>
</article>
<?php
layout_footer();
