<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

if (Auth::id() <= 0) {
    site_layout_header('Home');
    ?>
  <section class="site-hero">
    <div class="site-hero-grid">
      <div>
        <div class="site-hero-brand">
          <?= applypath_logo_mark() ?>
          <span>ApplyPath</span>
        </div>
        <h1>Your German job-hunt cockpit — search, tailor, track.</h1>
        <p class="lead">A testing portal to find roles, customize resume &amp; cover letter copies for each company, export EN/DE PDFs, and keep applications organized.</p>
        <div class="site-hero-cta">
          <a class="btn btn-primary btn-lg" href="/register.php">Try the demo</a>
          <a class="btn btn-outline-secondary btn-lg" href="/guide.php">How to use</a>
        </div>
      </div>
      <div class="site-hero-art" aria-hidden="true">
        <div class="site-blob site-blob-a"></div>
        <div class="site-blob site-blob-b"></div>
        <div class="site-cartoon-card site-cartoon-1"><?= applypath_icon('search') ?> Find jobs</div>
        <div class="site-cartoon-card site-cartoon-2"><?= applypath_icon('doc') ?> Tailor docs</div>
        <div class="site-cartoon-card site-cartoon-3"><?= applypath_icon('track') ?> Track apps</div>
      </div>
    </div>
  </section>

  <section class="site-section site-reveal">
    <div class="site-kicker">What this portal is</div>
    <h2>A testing module, not a finished product</h2>
    <p>ApplyPath is under active testing. Expect rough edges, changing features, and demo-style data. Use it to explore the workflow — then give feedback.</p>
    <div class="site-module-grid">
      <article class="site-module">
        <?= applypath_icon('lab') ?>
        <h3>Why “testing module”?</h3>
        <p>We are validating job search, document tailoring, PDF export, and applications tracking before a wider release. Accounts may be reset.</p>
      </article>
      <article class="site-module">
        <?= applypath_icon('spark') ?>
        <h3>What you can try today</h3>
        <p>Register, search German boards &amp; company career pages, paste a JD, edit resume/cover copies, download PDFs, and log applications.</p>
      </article>
    </div>
  </section>

  <section class="site-section site-reveal">
    <div class="site-kicker">How it works</div>
    <h2>From open role to sent application</h2>
    <p>Three playful steps — your Main resume and Main letter stay untouched.</p>
    <div class="site-steps">
      <div class="site-step">
        <?= applypath_icon('search') ?>
        <div class="site-step-n">Step 1</div>
        <h3>Find jobs</h3>
        <p>Search Arbeitsagentur, Jobexport, and company career boards (Greenhouse, Personio, Rossmann, DIS AG, and more).</p>
      </div>
      <div class="site-step">
        <?= applypath_icon('letter') ?>
        <div class="site-step-n">Step 2</div>
        <h3>Tailor</h3>
        <p>Paste a JD or start from a listing. ApplyPath copies Main docs so you can tweak summary, skills, and the cover for that company.</p>
      </div>
      <div class="site-step">
        <?= applypath_icon('rocket') ?>
        <div class="site-step-n">Step 3</div>
        <h3>Export &amp; track</h3>
        <p>Download EN/DE PDFs, apply on the employer site, and log status (applied → interview → offer).</p>
      </div>
    </div>
  </section>

  <section class="site-section site-reveal">
    <div class="site-kicker">Inside the portal</div>
    <h2>Modules you will see after login</h2>
    <div class="site-feature-grid">
      <article class="site-feature">
        <?= applypath_icon('search') ?>
        <h3>Jobs</h3>
        <p>Filters for city, level, posted date, sources, and company boards. Match against your active resume when you want.</p>
      </article>
      <article class="site-feature">
        <?= applypath_icon('company') ?>
        <h3>Companies</h3>
        <p>Shared career catalog for everyone, plus personal boards you add. Sitemap boards pull live openings with links.</p>
      </article>
      <article class="site-feature">
        <?= applypath_icon('doc') ?>
        <h3>Resume &amp; cover</h3>
        <p>Editors, design themes, and PDF EN/DE. Translation is gated per account when enabled by an admin.</p>
      </article>
      <article class="site-feature">
        <?= applypath_icon('track') ?>
        <h3>Applications</h3>
        <p>A simple board of what you sent where — company, role, location, and status.</p>
      </article>
    </div>
    <p class="mt-4 mb-0"><a href="/guide.php">Full how-to guide →</a> · <a href="/features.php">All features →</a></p>
  </section>

  <section class="site-cta-band site-reveal">
    <div class="site-cta-band-inner">
      <div>
        <h2>Ready to poke around?</h2>
        <p>Create a test account and walk through a sample application flow in a few minutes.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-light" href="/register.php">Create account</a>
        <a class="btn btn-outline-light" href="/login.php">Sign in</a>
      </div>
    </div>
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
