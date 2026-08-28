<?php

declare(strict_types=1);

/** Authenticated portal home (jobs / applications overview). */
function dashboard_home_render(): void
{
    $apps = App::applications(null);
    $counts = App::applicationCounts();
    $recent = array_slice($apps, 0, 6);

    layout_header('Home');
    $heroOpen = !onboarding_is_seen('hero');
    ?>
<main class="home">
  <?php onboarding_render_hero(true, $heroOpen); ?>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex gap-3">
          <div class="dash-card-ico"><?= kaamfit_icon('search', 'sm') ?></div>
          <div>
            <h2 class="h5">Find or paste a job</h2>
            <p class="text-secondary small mb-3">Search boards, or paste a JD. We copy your Master CV and Master cover letter into a Job CV per application.</p>
            <a class="btn btn-primary" href="/jobs">Find jobs</a>
            <a class="btn btn-outline-secondary" href="/tailor">Paste a JD</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex gap-3">
          <div class="dash-card-ico"><?= kaamfit_icon('doc', 'sm') ?></div>
          <div>
            <h2 class="h5">Edit your resume</h2>
            <p class="text-secondary small mb-3">Tweak summary and skills for that company. Master CV stays untouched.</p>
            <a class="btn btn-outline-secondary" href="/editor">Open resume</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex gap-3">
          <div class="dash-card-ico"><?= kaamfit_icon('pdf', 'sm') ?></div>
          <div>
            <h2 class="h5">Download resume PDF</h2>
            <p class="text-secondary small mb-3">Pick a look for the resume, then print or save.</p>
            <a class="btn btn-outline-secondary" href="/design">Resume style</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex gap-3">
          <div class="dash-card-ico"><?= kaamfit_icon('letter', 'sm') ?></div>
          <div>
            <h2 class="h5">Cover letter</h2>
            <p class="text-secondary small mb-3">Write the letter on its own page. Style is separate from the resume.</p>
            <a class="btn btn-outline-secondary" href="/cover">Open cover letter</a>
            <a class="btn btn-outline-secondary" href="/cover-design">Cover style</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4" aria-label="Application counts">
    <?php
    $statMeta = [
        'all' => ['label' => 'All', 'icon' => 'track'],
        'applied' => ['label' => 'Applied', 'icon' => 'applied'],
        'interview' => ['label' => 'Interview', 'icon' => 'interview'],
        'offer' => ['label' => 'Offer', 'icon' => 'offer'],
        'rejected' => ['label' => 'Rejected', 'icon' => 'rejected'],
    ];
    foreach ($statMeta as $key => $meta):
    ?>
      <div class="col">
        <a class="card shadow-sm h-100 text-decoration-none text-reset dash-stat-card" data-stat="<?= App::e($key) ?>" href="/applications?status=<?= App::e($key) ?>">
          <div class="card-body d-flex align-items-center gap-2">
            <div class="dash-card-ico"><?= kaamfit_icon($meta['icon'], 'sm') ?></div>
            <div>
              <div class="fs-3 fw-semibold"><?= (int) ($counts[$key] ?? 0) ?></div>
              <div class="text-secondary small text-uppercase"><?= App::e($meta['label']) ?></div>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <section class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0 d-flex align-items-center gap-2"><?= kaamfit_icon('track', 'sm') ?> Recent</h2>
        <a class="btn btn-sm btn-outline-secondary" href="/applications">All applications</a>
      </div>
      <?php if (!$recent): ?>
        <div class="text-center py-3">
          <div class="dash-empty-ico"><?= kaamfit_icon('rocket', 'lg') ?></div>
          <p class="text-secondary mb-2">Nothing yet.</p>
          <a class="btn btn-primary btn-sm" href="/tailor">Start with New job</a>
        </div>
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
            <a class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between gap-2 px-0" href="/applications?action=edit&amp;id=<?= (int) $app['id'] ?>">
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
}
