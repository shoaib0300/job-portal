<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$status = $_GET['status'] ?? 'all';
$allowed = ['all', 'applied', 'rejected', 'interview', 'offer', 'custom'];
if (!in_array($status, $allowed, true)) {
    $status = 'all';
}

$apps = App::applications($status === 'all' ? null : $status);
$counts = App::applicationCounts();
$profile = App::profile();
$activeResume = Versions::activeResumeVersion();
$activeCover = App::activeCoverLetter();
$recent = array_slice($apps, 0, 8);

layout_header('Dashboard');
?>
<main class="home">
  <section class="panel dash-welcome">
    <p class="eyebrow">Job search portal</p>
    <p class="hero-lead" style="margin:0.35rem 0 1rem">Track applications, tailor from a job description, and keep resumes ready — all in the database.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="/tailor.php">Apply from a JD</a>
      <a class="btn btn-secondary" href="/design.php?doc=resume">Design studio</a>
      <a class="btn btn-secondary" href="/editor.php">Open editor</a>
    </div>
  </section>

  <section class="stat-grid" aria-label="Application counts">
    <?php
    $statMeta = [
        'all' => 'All',
        'applied' => 'Applied',
        'interview' => 'Interview',
        'offer' => 'Offer',
        'rejected' => 'Rejected',
    ];
    foreach ($statMeta as $key => $label):
    ?>
      <a class="stat-card" href="/applications.php?status=<?= App::e($key) ?>">
        <span class="stat-n"><?= (int) ($counts[$key] ?? 0) ?></span>
        <span class="stat-l"><?= App::e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </section>

  <div class="dash-grid">
    <section class="apps-panel">
      <div class="apps-panel-head">
        <h2>Recent applications</h2>
        <a class="btn btn-small" href="/applications.php">View all</a>
      </div>
      <div class="status-chips" role="tablist">
        <?php foreach ($statMeta as $key => $label): ?>
          <a class="chip<?= $status === $key ? ' is-active' : '' ?>" href="/?status=<?= App::e($key) ?>"><?= App::e($label) ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (!$recent): ?>
        <p class="empty">No applications in this filter yet. <a href="/tailor.php">Apply from a JD</a>.</p>
      <?php else: ?>
        <ul class="app-list">
          <?php foreach ($recent as $app): ?>
            <li class="app-item status-<?= App::e($app['status']) ?>">
              <div class="app-main">
                <a href="/applications.php?action=edit&amp;id=<?= (int) $app['id'] ?>">
                  <strong><?= App::e($app['company']) ?></strong>
                  <span><?= App::e($app['role']) ?></span>
                </a>
              </div>
              <div class="app-meta">
                <span class="badge status-<?= App::e($app['status']) ?>"><?= App::e(App::statusLabel($app['status'])) ?></span>
                <?php if (!empty($app['location'])): ?>
                  <span><?= App::e((string) $app['location']) ?></span>
                <?php endif; ?>
                <?php if (!empty($app['applied_date'])): ?>
                  <time><?= App::e($app['applied_date']) ?></time>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <aside class="panel">
      <div class="panel-head">
        <h2>Now editing</h2>
      </div>
      <p class="muted" style="margin-top:0"><?= App::e((string) ($profile['full_name'] ?? '')) ?></p>
      <?php if ($activeResume): ?>
        <p>
          Resume <span class="doc-id">#<?= (int) $activeResume['id'] ?></span>
          <strong><?= (int) $activeResume['is_base'] === 1 ? 'Main resume' : App::e((string) $activeResume['title']) ?></strong>
        </p>
      <?php else: ?>
        <p class="empty">No active resume.</p>
      <?php endif; ?>
      <?php if ($activeCover): ?>
        <p>
          Cover <span class="doc-id">#<?= (int) $activeCover['id'] ?></span>
          <strong><?= (int) ($activeCover['is_base'] ?? 0) === 1 ? 'Main cover letter' : App::e((string) $activeCover['title']) ?></strong>
        </p>
      <?php endif; ?>
      <div class="hero-actions" style="margin-top:1rem">
        <a class="btn btn-small btn-primary" href="/editor.php">Edit content</a>
        <a class="btn btn-small" href="/documents.php">All documents</a>
      </div>
    </aside>
  </div>
</main>
<?php
layout_footer();
