<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/doc.php';
require_once dirname(__DIR__) . '/src/profile_meta.php';
require_once dirname(__DIR__) . '/src/experience.php';

Versions::ensureSchema();

$opts = doc_view_options();
$versionId = (int) ($opts['versionId'] ?? 0);
$payload = Versions::resumePayloadForView($versionId > 0 ? $versionId : null);
$profile = $payload['profile'];
$sections = $payload['sections'];
$experiences = $payload['experiences'];
$version = $payload['version'];
$theme = $opts['theme'];
$accent = $opts['accent'];
$font = $opts['font'];
$embed = $opts['embed'];
$pdfMode = $opts['pdfMode'];
$company = $versionId > 0
    ? (string) ($payload['company'] ?? '')
    : ($opts['company'] ?? '');
$exportOptions = Versions::resumeExportOptions();

layout_header($profile['full_name'] . ' — Resume', [
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
    <a href="/design.php?doc=resume">&larr; Design studio</a>
    <div class="doc-actions">
      <?php if ($version): ?>
        <span class="version-pill"><span class="doc-id">#<?= (int) $version['id'] ?></span> <?= (int) $version['is_base'] === 1 ? 'Main resume' : App::e((string) $version['title']) ?></span>
      <?php else: ?>
        <span class="version-pill">Resume</span>
      <?php endif; ?>
      <a class="btn btn-small" href="/editor.php#versions">My resumes</a>
      <a class="btn btn-small" href="/design.php?doc=resume">Change style</a>
      <button type="button" class="btn btn-small btn-primary" data-print data-doc="resume"
              data-export-options="<?= App::e(json_encode($exportOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>">Print</button>
      <button type="button" class="btn btn-small btn-secondary" data-download-pdf data-doc="resume"
              data-export-options="<?= App::e(json_encode($exportOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>">Download PDF</button>
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
    </div>
  </header>

  <div class="resume-sections">
  <?php foreach ($sections as $section): ?>
    <section class="resume-section" data-section="<?= App::e((string) ($section['section_key'] ?? '')) ?>">
      <h2><?= App::e($section['title']) ?></h2>
      <?php if (($section['section_key'] ?? '') === 'experience'): ?>
        <?php render_experience_entries($experiences); ?>
      <?php elseif (($section['section_key'] ?? '') === 'skills'): ?>
        <div class="resume-body"><?php render_skills_body((string) ($section['body'] ?? '')); ?></div>
      <?php elseif (($section['section_key'] ?? '') === 'education'): ?>
        <div class="resume-body"><?php render_education_body((string) ($section['body'] ?? '')); ?></div>
      <?php else: ?>
        <div class="resume-body"><?= App::nl2p($section['body']) ?></div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  </div>
</article>
<?php
layout_footer();
