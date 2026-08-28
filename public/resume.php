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
$documentLang = App::resolveDocumentLang();
$lang = (string) ($opts['lang'] ?? $documentLang);
$payload = Versions::resumePayloadForView($versionId > 0 ? $versionId : null);
$translateError = null;
if (!empty($opts['translate']) && ($opts['target'] ?? '') !== '' && $opts['target'] !== $documentLang) {
    try {
        $payload = DocTranslate::resume($payload, (string) $opts['target'], $documentLang);
    } catch (Throwable $e) {
        $translateError = $e->getMessage();
        if (!empty($opts['pdfMode']) || !empty($opts['embed'])) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "PDF translation failed.\n\n" . $translateError . "\n";
            exit;
        }
    }
}
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
    'lang' => $lang,
]);

if (!$embed):
?>
<main class="doc-toolbar no-print">
  <div class="doc-toolbar-inner d-flex flex-wrap justify-content-between align-items-center gap-2">
    <a class="btn btn-sm btn-link text-decoration-none" href="/design">&larr; Style</a>
    <div class="doc-actions d-flex flex-wrap gap-2 align-items-center">
      <?php if ($version): ?>
        <span class="badge rounded-pill text-bg-light border"><span class="doc-id">#<?= (int) $version['id'] ?></span> <?= App::e(Versions::resumeDisplayLabel($version)) ?></span>
      <?php else: ?>
        <span class="badge rounded-pill text-bg-light border">Resume</span>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="/editor#versions">My resumes</a>
      <a class="btn btn-sm btn-outline-secondary" href="/design">Change style</a>
      <button type="button" class="btn btn-sm btn-primary" data-print data-doc="resume"
              data-export-options="<?= App::e(json_encode($exportOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>">Print</button>
      <?php
        $pdfQs = $versionId > 0 ? ['version' => $versionId] : [];
        layout_pdf_buttons('resume', $pdfQs, ['export_options' => $exportOptions]);
      ?>
    </div>
  </div>
</main>
<?php if ($translateError): ?>
  <div class="alert alert-warning no-print"><?= App::e($translateError) ?></div>
<?php endif; ?>
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
