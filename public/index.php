<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

if (Auth::id() <= 0) {
    site_layout_header('Home');
    ?>
  <section class="site-hero">
    <div class="site-hero-brand">
      <?= applypath_logo_mark() ?>
      <span>ApplyPath</span>
    </div>
    <p class="lead">Find German jobs, tailor your resume and cover letter for each role, and track every application — in one place.</p>
    <div class="site-hero-cta">
      <a class="btn btn-primary btn-lg" href="/register.php">Get started</a>
      <a class="btn btn-outline-secondary btn-lg" href="/login.php">Sign in</a>
    </div>
  </section>
  <section class="site-section">
    <h2>How it works</h2>
    <p>Three steps from search to send — without losing your main resume.</p>
    <div class="site-steps">
      <div class="site-step">
        <div class="site-step-n">Step 1</div>
        <h3>Find jobs</h3>
        <p>Search German boards and company career pages, or paste a job description.</p>
      </div>
      <div class="site-step">
        <div class="site-step-n">Step 2</div>
        <h3>Tailor</h3>
        <p>We copy your Main resume and letter, then you adjust summary, skills, and the cover for that company.</p>
      </div>
      <div class="site-step">
        <div class="site-step-n">Step 3</div>
        <h3>Apply &amp; track</h3>
        <p>Download EN/DE PDFs and log the application so interviews and offers stay organized.</p>
      </div>
    </div>
    <p class="mt-4 mb-0"><a href="/features.php">See all features</a> · <a href="/about.php">About ApplyPath</a></p>
  </section>
    <?php
    site_layout_footer();
    exit;
}

$apps = App::applications(null);
$counts = App::applicationCounts();
$recent = array_slice($apps, 0, 6);

layout_header('Home');
?>
<main class="home">
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex gap-3">
          <span class="step-n">1</span>
          <div>
            <h2 class="h5">Find or paste a job</h2>
            <p class="text-secondary small mb-3">Search boards, or paste a JD. We copy Main resume and Main letter into separate documents.</p>
            <a class="btn btn-primary" href="/jobs.php">Find jobs</a>
            <a class="btn btn-outline-secondary" href="/tailor.php">Paste a JD</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex gap-3">
          <span class="step-n">2</span>
          <div>
            <h2 class="h5">Edit your resume</h2>
            <p class="text-secondary small mb-3">Change summary and skills for that company. Main stays untouched.</p>
            <a class="btn btn-outline-secondary" href="/editor.php">Open resume</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex gap-3">
          <span class="step-n">3</span>
          <div>
            <h2 class="h5">Download resume PDF</h2>
            <p class="text-secondary small mb-3">Pick a look for the resume, then print or save.</p>
            <a class="btn btn-outline-secondary" href="/design.php">Resume style</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <h2 class="h5">Cover letter</h2>
          <p class="text-secondary small mb-3">Write the letter on its own page. Style is separate from the resume.</p>
          <a class="btn btn-outline-secondary" href="/cover.php">Open cover letter</a>
          <a class="btn btn-outline-secondary" href="/cover-design.php">Cover style</a>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4" aria-label="Application counts">
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
      <div class="col">
        <a class="card shadow-sm h-100 text-decoration-none text-reset" href="/applications.php?status=<?= App::e($key) ?>">
          <div class="card-body">
            <div class="fs-3 fw-semibold"><?= (int) ($counts[$key] ?? 0) ?></div>
            <div class="text-secondary small text-uppercase"><?= App::e($label) ?></div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <section class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0">Recent</h2>
        <a class="btn btn-sm btn-outline-secondary" href="/applications.php">All applications</a>
      </div>
      <?php if (!$recent): ?>
        <p class="text-secondary mb-0">Nothing yet. Start with <a href="/tailor.php">New job</a>.</p>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($recent as $app): ?>
            <?php
            $badge = match ($app['status']) {
                'rejected' => 'text-bg-danger',
                'interview' => 'text-bg-info',
                'offer' => 'text-bg-success',
                'custom' => 'text-bg-secondary',
                default => 'text-bg-primary',
            };
            ?>
            <a class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between gap-2 px-0" href="/applications.php?action=edit&amp;id=<?= (int) $app['id'] ?>">
              <div>
                <strong><?= App::e($app['company']) ?></strong>
                <div class="text-secondary small"><?= App::e($app['role']) ?></div>
              </div>
              <div class="d-flex align-items-center gap-2 small text-secondary">
                <span class="badge <?= $badge ?>"><?= App::e(App::statusLabel($app['status'])) ?></span>
                <?php if (!empty($app['location'])): ?>
                  <span><?= App::e((string) $app['location']) ?></span>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php
layout_footer();
