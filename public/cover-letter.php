<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$profile = App::profile();
$letter = App::activeCoverLetter();
$theme = App::setting('theme', 'classic') ?: 'classic';
$pdfMode = (App::setting('pdf_mode', '0') ?: '0') === '1';

layout_header(($letter['title'] ?? 'Cover Letter'), [
    'body_class' => 'page-doc theme-' . $theme,
]);
?>
<main class="doc-toolbar no-print">
  <div class="doc-toolbar-inner">
    <a href="/">&larr; Portal</a>
    <div class="doc-actions">
      <a class="btn btn-small" href="/editor.php#cover">Edit</a>
      <button type="button" class="btn btn-small btn-primary" data-print>Print / Save PDF</button>
    </div>
  </div>
</main>

<article class="cover-letter theme-<?= App::e($theme) ?><?= $pdfMode ? ' pdf-ready' : '' ?>">
  <header class="letter-from">
    <strong><?= App::e($profile['full_name']) ?></strong>
    <?php if ($profile['title'] !== ''): ?>
      <span><?= App::e($profile['title']) ?></span>
    <?php endif; ?>
    <ul class="resume-contact">
      <?php if ($profile['email'] !== ''): ?><li><?= App::e($profile['email']) ?></li><?php endif; ?>
      <?php if ($profile['phone'] !== ''): ?><li><?= App::e($profile['phone']) ?></li><?php endif; ?>
      <?php if ($profile['location'] !== ''): ?><li><?= App::e($profile['location']) ?></li><?php endif; ?>
    </ul>
  </header>

  <?php if ($letter): ?>
    <?php if ($letter['company'] !== ''): ?>
      <p class="letter-company">Re: <?= App::e($letter['company']) ?><?= $letter['title'] !== '' ? ' — ' . App::e($letter['title']) : '' ?></p>
    <?php endif; ?>
    <div class="letter-body"><?= App::nl2p($letter['body']) ?></div>
  <?php else: ?>
    <p class="empty">No active cover letter. <a href="/editor.php#cover">Create one in the editor</a>.</p>
  <?php endif; ?>
</article>
<?php
layout_footer();
