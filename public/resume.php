<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/doc.php';
require_once dirname(__DIR__) . '/src/profile_meta.php';
require_once dirname(__DIR__) . '/src/experience.php';

use KaamFit\Resume\ResumeLayout;
use KaamFit\Resume\ResumePhotoMode;

Versions::ensureSchema();

$opts = doc_view_options();
$versionId = (int) ($opts['versionId'] ?? 0);
$atsMode = !empty($opts['ats']);
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
if ($atsMode) {
    $payload = AtsExport::sanitizeResumePayload($payload);
}

$meta = is_array($payload['meta'] ?? null)
    ? ResumeLayout::mergeMeta($payload['meta'])
    : ResumeLayout::defaultMeta();
$theme = $atsMode ? 'ats' : ResumeLayout::resolveTemplate($opts['theme'] ?: ($meta['template'] ?? null));
$density = ResumeLayout::resolveDensity($_GET['density'] ?? ($meta['density'] ?? null));
$photoMode = ResumePhotoMode::effective($meta['photo_mode'] ?? null, $atsMode);
$showExtras = !empty($meta['show_personal_extras']) && !$atsMode && $photoMode !== ResumePhotoMode::ANONYMIZED;

$profile = ResumePhotoMode::prepareProfile(
    $payload['profile'],
    $photoMode,
    $showExtras,
    $atsMode
);
$sections = $payload['sections'];
$experiences = $payload['experiences'];
$version = $payload['version'];
$accent = $opts['accent'];
$font = $opts['font'];
$embed = $opts['embed'];
$pdfMode = $opts['pdfMode'];
$company = $versionId > 0
    ? (string) ($payload['company'] ?? '')
    : ($opts['company'] ?? '');
$exportOptions = Versions::resumeExportOptions();
$showPhoto = ResumePhotoMode::shouldShowPhoto($profile, $photoMode, $atsMode);
$includeLinks = !$atsMode;
$includeMeta = $showExtras;

layout_header($profile['full_name'] . ' — ' . (str_starts_with(strtolower($lang), 'de') ? 'Lebenslauf' : 'Resume'), [
    'body_class' => 'page-doc theme-' . $theme
        . ' density-' . $density
        . ($embed ? ' is-embed' : ''),
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
    <a class="btn btn-sm btn-link text-decoration-none" href="/resume-edit">&larr; Studio</a>
    <div class="doc-actions d-flex flex-wrap gap-2 align-items-center">
      <?php if ($version): ?>
        <span class="badge rounded-pill text-bg-light border"><span class="doc-id">#<?= (int) $version['id'] ?></span> <?= App::e(Versions::resumeDisplayLabel($version)) ?></span>
      <?php else: ?>
        <span class="badge rounded-pill text-bg-light border"><?= str_starts_with(strtolower($lang), 'de') ? 'Lebenslauf' : 'Resume' ?></span>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="/editor#versions">My resumes</a>
      <a class="btn btn-sm btn-outline-secondary" href="/resume-edit">Edit</a>
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

<article
  class="resume theme-<?= App::e($theme) ?> density-<?= App::e($density) ?><?= $pdfMode ? ' pdf-ready' : '' ?><?= $showPhoto ? ' has-photo' : ' no-photo' ?>"
  data-doc="resume"
  data-template="<?= App::e($theme) ?>"
  data-photo-mode="<?= App::e($photoMode) ?>"
>
  <header class="resume-header">
    <?php if ($showPhoto): ?>
      <div class="resume-photo">
        <img src="<?= App::e(App::photoUrl($profile)) ?>" alt="<?= App::e($profile['full_name']) ?>">
      </div>
    <?php endif; ?>
    <div class="resume-intro">
      <h1><?= App::e($profile['full_name']) ?></h1>
      <?php if (App::filled($profile['title'] ?? null)): ?>
        <p class="resume-title"><?= App::e($profile['title']) ?></p>
      <?php endif; ?>
      <?php render_profile_details($profile, $includeLinks, $includeMeta, $lang); ?>
    </div>
  </header>

  <div class="resume-sections">
  <?php foreach ($sections as $section): ?>
    <?php
      $key = (string) ($section['section_key'] ?? '');
      $title = (string) ($section['title'] ?? '');
      if ($title === '' || in_array(strtolower($title), ['summary', 'experience', 'skills', 'education', 'profile'], true)) {
          $title = ResumeLayout::sectionLabel($key !== '' ? $key : 'summary', $lang);
      }
    ?>
    <section class="resume-section" data-section="<?= App::e($key) ?>">
      <h2><?= App::e($title) ?></h2>
      <?php render_resume_section_body($key, (string) ($section['body'] ?? ''), $experiences, $profile); ?>
    </section>
  <?php endforeach; ?>
  </div>
</article>
<?php
layout_footer();
