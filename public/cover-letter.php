<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/doc.php';
require_once dirname(__DIR__) . '/src/profile_meta.php';

$opts = doc_view_options();
$profile = App::profile();
$letter = App::activeCoverLetter();
$theme = $opts['theme'];
$accent = $opts['accent'];
$font = $opts['font'];
$embed = $opts['embed'];
$pdfMode = $opts['pdfMode'];

layout_header(($letter['title'] ?? 'Cover Letter'), [
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
  <div class="doc-toolbar-inner">
    <a href="/design.php?doc=cover">&larr; Design studio</a>
    <div class="doc-actions">
      <a class="btn btn-small" href="/editor.php#cover">Edit content</a>
      <a class="btn btn-small" href="/design.php?doc=cover">Change style</a>
      <button type="button" class="btn btn-small btn-primary" data-print>Print</button>
      <button type="button" class="btn btn-small btn-secondary" data-download-pdf>Download PDF</button>
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
    <?php render_profile_details($profile, false); ?>
  </header>

  <?php if ($letter): ?>
    <?php if (App::filled($letter['company'] ?? null)): ?>
      <p class="letter-company">Re: <?= App::e($letter['company']) ?><?= App::filled($letter['title'] ?? null) ? ' — ' . App::e($letter['title']) : '' ?></p>
    <?php endif; ?>
    <div class="letter-body"><?= App::nl2p($letter['body']) ?></div>
  <?php else: ?>
    <p class="empty">No active cover letter. <a href="/editor.php#cover">Create one in the editor</a>.</p>
  <?php endif; ?>
</article>
<?php
layout_footer();
