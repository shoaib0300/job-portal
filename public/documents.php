<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

Versions::ensureSchema();
$resumes = Versions::resumeVersions();

layout_header('Resume copies');
?>
<main class="page-wide">
  <header class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <h1>Resume copies</h1>
      <p>Every resume copy. Main stays stable.</p>
    </div>
    <a class="btn btn-primary" href="/editor.php">Open resume</a>
  </header>

  <section class="card shadow-sm">
    <div class="card-body">
      <?php if (!$resumes): ?>
        <p class="text-secondary mb-0">No resume versions yet.</p>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($resumes as $ver): ?>
            <?php
            $rid = (int) $ver['id'];
            $isMain = (int) $ver['is_base'] === 1;
            $isOpen = (int) $ver['is_active'] === 1;
            $label = $isMain ? 'Main resume' : (string) $ver['title'];
            ?>
            <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2 px-0">
              <div>
                <span class="doc-id">#<?= $rid ?></span>
                <?php if ($isMain): ?><span class="badge text-bg-secondary">Main</span> <?php endif; ?>
                <?php if ($isOpen): ?><span class="badge text-bg-primary">Editing</span> <?php endif; ?>
                <strong><?= App::e($label) ?></strong>
                <?php if (!$isMain && $ver['company'] !== ''): ?>
                  <div class="text-secondary small"><?= App::e((string) $ver['company']) ?></div>
                <?php endif; ?>
              </div>
              <div class="d-flex flex-wrap gap-1">
                <?php if ($isOpen): ?>
                  <a class="btn btn-sm btn-primary" href="/resume-edit.php">Edit</a>
                <?php else: ?>
                  <form method="post" action="/editor.php">
                    <input type="hidden" name="action" value="load_resume_version">
                    <input type="hidden" name="id" value="<?= $rid ?>">
                    <button type="submit" class="btn btn-sm btn-primary">Edit / Select</button>
                  </form>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="/resume.php?version=<?= $rid ?>" target="_blank" rel="noopener">View</a>
                <a class="btn btn-sm btn-outline-secondary" href="/pdf.php?doc=resume&amp;version=<?= $rid ?>">PDF</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php
layout_footer();
