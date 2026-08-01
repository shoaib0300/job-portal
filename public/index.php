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
$profile = App::profile();

layout_header('Job Search Portal');
?>
<main class="home">
  <section class="hero">
    <p class="eyebrow">Job search portal</p>
    <h1 class="hero-brand">MNK</h1>
    <p class="hero-lead">Track applications, tailor your resume, and keep cover letters ready.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="/resume.php">Show Resume</a>
      <a class="btn btn-secondary" href="/cover-letter.php">Show Cover Letter</a>
    </div>
    <p class="hero-meta">Editing as <?= App::e($profile['full_name']) ?> · <a href="/editor.php">Open editor</a></p>
  </section>

  <section class="apps-panel">
    <div class="apps-panel-head">
      <h2>Where you applied</h2>
      <a class="btn btn-small" href="/applications.php?action=new">Add custom entry</a>
    </div>

    <div class="status-chips" role="tablist">
      <?php
      $chips = [
          'all' => 'All',
          'applied' => 'Applied',
          'rejected' => 'Rejected',
          'interview' => 'Interview',
          'offer' => 'Offer',
          'custom' => 'Custom',
      ];
      foreach ($chips as $key => $label):
      ?>
        <a class="chip<?= $status === $key ? ' is-active' : '' ?>" href="/?status=<?= App::e($key) ?>"><?= App::e($label) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$apps): ?>
      <p class="empty">No applications in this filter yet. <a href="/applications.php?action=new">Create one</a>.</p>
    <?php else: ?>
      <ul class="app-list">
        <?php foreach ($apps as $app): ?>
          <li class="app-item status-<?= App::e($app['status']) ?>">
            <div class="app-main">
              <a href="/applications.php?action=edit&amp;id=<?= (int) $app['id'] ?>">
                <strong><?= App::e($app['company']) ?></strong>
                <span><?= App::e($app['role']) ?></span>
              </a>
            </div>
            <div class="app-meta">
              <span class="badge"><?= App::e(App::statusLabel($app['status'])) ?></span>
              <?php if (!empty($app['applied_date'])): ?>
                <time><?= App::e($app['applied_date']) ?></time>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <p class="panel-foot">
      <a href="/applications.php">Manage all applications</a>
      ·
      <a href="/history.php">Search history</a>
    </p>
  </section>
</main>
<?php
layout_footer();
