<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Interview\InterviewLibrary;
use KaamFit\Interview\InterviewSchema;

InterviewSchema::ensureSchema();
$uid = Auth::id();
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'per_page' => 24,
];
$result = InterviewLibrary::forUser($uid, $filters);

layout_header('My interview questions');
?>
<main class="container-fluid px-3 px-lg-4 py-3">
  <?php require dirname(__DIR__) . '/src/interview_prep_nav.php'; ?>
  <header class="page-head mb-3">
    <h1>My questions</h1>
    <p class="text-secondary mb-0">Imported and saved questions in your Interview Prep library.</p>
  </header>

  <form method="get" class="row g-2 mb-3">
    <div class="col-md-6"><input class="form-control" name="q" value="<?= App::e($filters['q']) ?>" placeholder="Search my questions"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Search</button></div>
  </form>

  <p class="small text-secondary"><?= (int) $result['total'] ?> in your library</p>
  <div class="list-group shadow-sm">
    <?php foreach ($result['items'] as $item): ?>
      <a class="list-group-item list-group-item-action" href="/interview-prep-question?id=<?= (int) $item['id'] ?>">
        <div class="d-flex justify-content-between gap-2">
          <span><?= App::e(mb_substr((string) $item['question_text'], 0, 160)) ?></span>
          <span class="badge text-bg-light text-nowrap"><?= App::e((string) ($item['origin'] ?? '')) ?></span>
        </div>
        <div class="small text-secondary"><?= App::e((string) $item['language']) ?> · <?= App::e((string) $item['question_type']) ?></div>
      </a>
    <?php endforeach; ?>
    <?php if ($result['items'] === []): ?>
      <div class="list-group-item text-secondary">No imported questions yet. <a href="/interview-prep-import">Import content</a>.</div>
    <?php endif; ?>
  </div>
  <?php if ($result['total'] > $result['per_page']): ?>
    <div class="mt-2 d-flex gap-2">
      <?php if ($result['page'] > 1): ?><a class="btn btn-sm btn-outline-secondary" href="?q=<?= urlencode($filters['q']) ?>&amp;page=<?= $result['page'] - 1 ?>">Prev</a><?php endif; ?>
      <?php if ($result['page'] * $result['per_page'] < $result['total']): ?><a class="btn btn-sm btn-outline-secondary" href="?q=<?= urlencode($filters['q']) ?>&amp;page=<?= $result['page'] + 1 ?>">Next</a><?php endif; ?>
    </div>
  <?php endif; ?>
</main>
<?php
layout_footer();
