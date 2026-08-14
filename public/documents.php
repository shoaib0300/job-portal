<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

Versions::ensureSchema();
$resumes = Versions::resumeVersions();
$covers = App::coverLetters();

layout_header('Documents');
?>
<main class="page-wide">
  <header class="page-head row">
    <div>
      <h1>Documents</h1>
      <p>Every resume and cover letter copy stored in the database. Main stays stable; job copies are listed here.</p>
    </div>
    <div class="hero-actions">
      <a class="btn btn-primary" href="/tailor.php">Apply from a JD</a>
      <a class="btn btn-secondary" href="/editor.php#versions">Edit in editor</a>
    </div>
  </header>

  <section class="editor-block">
    <h2>Resumes</h2>
    <?php if (!$resumes): ?>
      <p class="empty">No resume versions yet.</p>
    <?php else: ?>
      <ul class="version-list doc-card-list">
        <?php foreach ($resumes as $ver): ?>
          <?php
          $rid = (int) $ver['id'];
          $isMain = (int) $ver['is_base'] === 1;
          $isOpen = (int) $ver['is_active'] === 1;
          $label = $isMain ? 'Main resume' : (string) $ver['title'];
          ?>
          <li class="version-list-item doc-card<?= $isOpen ? ' is-open' : '' ?>">
            <div class="doc-card-main">
              <span class="doc-id">#<?= $rid ?></span>
              <div class="doc-card-text">
                <strong>
                  <?php if ($isMain): ?><span class="badge-main">Main</span> <?php endif; ?>
                  <?php if ($isOpen): ?><span class="badge-active">Editing</span> <?php endif; ?>
                  <?= App::e($label) ?>
                </strong>
                <?php if (!$isMain && $ver['company'] !== ''): ?>
                  <span class="muted"><?= App::e((string) $ver['company']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="doc-card-actions version-list-actions">
              <a class="btn btn-small btn-primary" href="/editor.php#versions">Edit</a>
              <a class="btn btn-small" href="/resume.php?version=<?= $rid ?>" target="_blank" rel="noopener">View</a>
              <a class="btn btn-small btn-secondary" href="/pdf.php?doc=resume&amp;version=<?= $rid ?>">PDF</a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="editor-block">
    <h2>Cover letters</h2>
    <?php if (!$covers): ?>
      <p class="empty">No cover letters yet.</p>
    <?php else: ?>
      <ul class="version-list doc-card-list">
        <?php foreach ($covers as $cl): ?>
          <?php
          $cid = (int) $cl['id'];
          $isMain = (int) ($cl['is_base'] ?? 0) === 1;
          $isOpen = (int) ($cl['is_active'] ?? 0) === 1;
          $label = $isMain ? 'Main cover letter' : (string) $cl['title'];
          ?>
          <li class="version-list-item doc-card<?= $isOpen ? ' is-open' : '' ?>">
            <div class="doc-card-main">
              <span class="doc-id">#<?= $cid ?></span>
              <div class="doc-card-text">
                <strong>
                  <?php if ($isMain): ?><span class="badge-main">Main</span> <?php endif; ?>
                  <?php if ($isOpen): ?><span class="badge-active">Active</span> <?php endif; ?>
                  <?= App::e($label) ?>
                </strong>
                <?php if (!$isMain && $cl['company'] !== ''): ?>
                  <span class="muted"><?= App::e((string) $cl['company']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="doc-card-actions version-list-actions">
              <a class="btn btn-small btn-primary" href="/editor.php#cover">Edit</a>
              <a class="btn btn-small" href="/cover-letter.php?id=<?= $cid ?>" target="_blank" rel="noopener">View</a>
              <a class="btn btn-small btn-secondary" href="/pdf.php?doc=cover&amp;id=<?= $cid ?>">PDF</a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>
<?php
layout_footer();
