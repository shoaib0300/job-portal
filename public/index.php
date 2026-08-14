<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$apps = App::applications(null);
$counts = App::applicationCounts();
$recent = array_slice($apps, 0, 6);

layout_header('Home');
?>
<main class="home">
  <ol class="step-grid">
    <li class="step-card">
      <span class="step-n">1</span>
      <div>
        <h2>Paste a job</h2>
        <p>We copy your Main resume and letter, then save the application.</p>
        <a class="btn btn-primary" href="/tailor.php">New job</a>
      </div>
    </li>
    <li class="step-card">
      <span class="step-n">2</span>
      <div>
        <h2>Edit your resume</h2>
        <p>Change summary and skills for that company. Main stays untouched.</p>
        <a class="btn btn-secondary" href="/editor.php">Open resume</a>
      </div>
    </li>
    <li class="step-card">
      <span class="step-n">3</span>
      <div>
        <h2>Download PDF</h2>
        <p>Pick a look, then print or save the file to send.</p>
        <a class="btn btn-secondary" href="/design.php">Choose style</a>
      </div>
    </li>
  </ol>

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

  <section class="apps-panel">
    <div class="apps-panel-head">
      <h2>Recent</h2>
      <a class="btn btn-small" href="/applications.php">All applications</a>
    </div>
    <?php if (!$recent): ?>
      <p class="empty">Nothing yet. Start with <a href="/tailor.php">New job</a>.</p>
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
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>
<?php
layout_footer();
