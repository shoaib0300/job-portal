<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/doc.php';
require_once dirname(__DIR__) . '/src/profile_meta.php';

$opts = doc_view_options();
$profile = App::profile();
$sections = App::sections(true);
$theme = $opts['theme'];
$accent = $opts['accent'];
$embed = $opts['embed'];
$pdfMode = $opts['pdfMode'];
$company = $opts['company'];

layout_header($profile['full_name'] . ' — Resume', [
    'body_class' => 'page-doc theme-' . $theme . ($embed ? ' is-embed' : ''),
    'theme' => $theme,
    'accent' => $accent,
    'pdf_mode' => $pdfMode,
    'hide_nav' => $embed,
    'hide_flash' => $embed,
]);

if (!$embed):
?>
<main class="doc-toolbar no-print">
  <div class="doc-toolbar-inner">
    <a href="/design.php?doc=resume">&larr; Design studio</a>
    <div class="doc-actions">
      <a class="btn btn-small" href="/editor.php">Edit content</a>
      <a class="btn btn-small" href="/design.php?doc=resume">Change style</a>
      <button type="button" class="btn btn-small btn-primary" data-print>Print</button>
      <button type="button" class="btn btn-small btn-secondary" data-download-pdf>Download PDF</button>
    </div>
  </div>
</main>
<?php endif; ?>

<article class="resume theme-<?= App::e($theme) ?><?= $pdfMode ? ' pdf-ready' : '' ?><?= App::shouldShowPhoto($profile) ? ' has-photo' : ' no-photo' ?>" data-doc="resume">
  <header class="resume-header">
    <?php if (App::shouldShowPhoto($profile)): ?>
      <div class="resume-photo">
        <img src="<?= App::e(App::photoUrl($profile)) ?>" alt="<?= App::e($profile['full_name']) ?>">
      </div>
    <?php endif; ?>
    <div class="resume-intro">
      <h1><?= App::e($profile['full_name']) ?></h1>
      <?php if (App::filled($profile['title'] ?? null)): ?>
        <p class="resume-title"><?= App::e($profile['title']) ?></p>
      <?php endif; ?>
      <?php render_profile_details($profile, true); ?>
      <?php if (App::filled($company) && !$embed): ?>
        <p class="resume-company-tag no-print">Tailored for <?= App::e($company) ?></p>
      <?php endif; ?>
    </div>
  </header>

  <?php foreach ($sections as $section): ?>
    <section class="resume-section">
      <h2><?= App::e($section['title']) ?></h2>
      <div class="resume-body"><?= App::nl2p($section['body']) ?></div>
    </section>
  <?php endforeach; ?>
</article>
<?php
layout_footer();
