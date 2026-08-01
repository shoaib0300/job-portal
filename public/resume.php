<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$profile = App::profile();
$sections = App::sections(true);
$theme = App::setting('theme', 'classic') ?: 'classic';
$pdfMode = (App::setting('pdf_mode', '0') ?: '0') === '1';
$company = App::setting('active_company', '') ?: '';

layout_header($profile['full_name'] . ' — Resume', [
    'body_class' => 'page-doc theme-' . $theme,
]);
?>
<main class="doc-toolbar no-print">
  <div class="doc-toolbar-inner">
    <a href="/">&larr; Portal</a>
    <div class="doc-actions">
      <a class="btn btn-small" href="/editor.php">Edit</a>
      <button type="button" class="btn btn-small btn-primary" data-print>Print / Save PDF</button>
    </div>
  </div>
</main>

<article class="resume theme-<?= App::e($theme) ?><?= $pdfMode ? ' pdf-ready' : '' ?>">
  <header class="resume-header">
    <h1><?= App::e($profile['full_name']) ?></h1>
    <?php if ($profile['title'] !== ''): ?>
      <p class="resume-title"><?= App::e($profile['title']) ?></p>
    <?php endif; ?>
    <ul class="resume-contact">
      <?php if ($profile['email'] !== ''): ?><li><?= App::e($profile['email']) ?></li><?php endif; ?>
      <?php if ($profile['phone'] !== ''): ?><li><?= App::e($profile['phone']) ?></li><?php endif; ?>
      <?php if ($profile['location'] !== ''): ?><li><?= App::e($profile['location']) ?></li><?php endif; ?>
      <?php foreach ($profile['links'] as $link): ?>
        <?php if (!empty($link['url'])): ?>
          <li><a href="<?= App::e($link['url']) ?>"><?= App::e($link['label'] ?? $link['url']) ?></a></li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>
    <?php if ($company !== ''): ?>
      <p class="resume-company-tag no-print">Tailored for <?= App::e($company) ?></p>
    <?php endif; ?>
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
