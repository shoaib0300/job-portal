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
$documentLang = App::resolveDocumentLang();
$lang = (string) ($opts['lang'] ?? $documentLang);
$letter = $coverId > 0 ? Versions::coverLetterById($coverId) : App::activeCoverLetter();
$theme = $opts['theme'];
$accent = $opts['accent'];
$font = $opts['font'];
$embed = $opts['embed'];
$pdfMode = $opts['pdfMode'];
$atsMode = !empty($opts['ats']);
$exportOptions = Versions::coverExportOptions();
$translateError = null;
if (!empty($opts['translate']) && ($opts['target'] ?? '') !== '' && $opts['target'] !== $documentLang) {
    try {
        $translated = DocTranslate::cover(is_array($letter) ? $letter : null, $profile, (string) $opts['target'], $documentLang);
        $letter = $translated['letter'];
        $profile = $translated['profile'];
    } catch (Throwable $e) {
        $translateError = $e->getMessage();
        if ($pdfMode || $embed) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "PDF translation failed.\n\n" . $translateError . "\n";
            exit;
        }
    }
}

layout_header($profile['full_name'], [
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
    <a class="btn btn-sm btn-link text-decoration-none" href="/cover-design">&larr; Style</a>
    <div class="doc-actions d-flex flex-wrap gap-2 align-items-center">
      <?php if ($letter): ?>
        <span class="badge rounded-pill text-bg-light border"><span class="doc-id">#<?= (int) $letter['id'] ?></span> <?= App::e(Versions::coverDisplayLabel($letter)) ?></span>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="/cover-edit">Edit content</a>
      <a class="btn btn-sm btn-outline-secondary" href="/cover-design">Change style</a>
      <button type="button" class="btn btn-sm btn-primary" data-print data-doc="cover"
              data-export-options="<?= App::e(json_encode($exportOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>">Print</button>
      <?php
        $pdfQs = $coverId > 0 ? ['id' => $coverId] : [];
        layout_pdf_buttons('cover', $pdfQs, ['export_options' => $exportOptions]);
      ?>
    </div>
  </div>
</main>
<?php if ($translateError): ?>
  <div class="alert alert-warning no-print"><?= App::e($translateError) ?></div>
<?php endif; ?>
<?php endif; ?>

<article class="cover-letter theme-<?= App::e($theme) ?><?= $pdfMode ? ' pdf-ready' : '' ?>" data-doc="cover">
  <header class="letter-from">
    <strong><?= App::e($profile['full_name']) ?></strong>
    <?php if (App::filled($profile['title'] ?? null)): ?>
      <span><?= App::e($profile['title']) ?></span>
    <?php endif; ?>
    <?php render_profile_details($profile, !$atsMode, !$atsMode); ?>
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
    <p class="empty">No active cover letter. <a href="/cover">Create one</a>.</p>
  <?php endif; ?>
</article>
<?php
layout_footer();
