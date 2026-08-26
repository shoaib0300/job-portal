<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

// Portal subdomain: authenticated app home (not marketing).
if (App::isPortalHost()) {
    require_once dirname(__DIR__) . '/src/dashboard_home.php';
    dashboard_home_render();
    exit;
}

// Main host root is always the marketing site — even when logged in.
$loggedIn = Auth::id() > 0;
$portalHref = App::portalHomePath();

site_layout_header('Home');
?>
  <section class="site-hero">
    <div class="site-hero-grid">
      <div>
        <div class="site-hero-brand">
          <?= kaammilo_logo_mark() ?>
          <span>KaamMilo</span>
        </div>
        <h1>Your German job-hunt cockpit — search, tailor, track.</h1>
        <p class="lead">A testing portal to find roles, customize resume &amp; cover letter copies for each company, export EN/DE PDFs, and keep applications organized.</p>
        <div class="site-hero-cta">
          <?php if ($loggedIn): ?>
            <a class="btn btn-primary btn-lg" href="<?= App::e($portalHref) ?>">Open portal</a>
            <a class="btn btn-outline-secondary btn-lg" href="/guide.php">How to use</a>
          <?php else: ?>
            <a class="btn btn-primary btn-lg" href="/register.php">Try the demo</a>
            <a class="btn btn-outline-secondary btn-lg" href="/guide.php">How to use</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="site-hero-art" aria-hidden="true">
        <div class="site-blob site-blob-a"></div>
        <div class="site-blob site-blob-b"></div>
        <div class="site-cartoon-card site-cartoon-1"><?= kaammilo_icon('search') ?> Find jobs</div>
        <div class="site-cartoon-card site-cartoon-2"><?= kaammilo_icon('doc') ?> Tailor docs</div>
        <div class="site-cartoon-card site-cartoon-3"><?= kaammilo_icon('track') ?> Track apps</div>
      </div>
    </div>
  </section>

  <section class="site-section site-reveal">
    <div class="site-kicker">What this portal is</div>
    <h2>A testing module, not a finished product</h2>
    <p>KaamMilo is under active testing. Expect rough edges, changing features, and demo-style data. Use it to explore the workflow — then give feedback.</p>
    <div class="site-module-grid">
      <article class="site-module">
        <?= kaammilo_icon('lab') ?>
        <h3>Why “testing module”?</h3>
        <p>We are validating job search, document tailoring, PDF export, and applications tracking before a wider release. Accounts may be reset.</p>
      </article>
      <article class="site-module">
        <?= kaammilo_icon('spark') ?>
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
        <?= kaammilo_icon('search') ?>
        <div class="site-step-n">Step 1</div>
        <h3>Find jobs</h3>
        <p>Search Arbeitsagentur, Jobexport, and company career boards (Greenhouse, Personio, Rossmann, DIS AG, and more).</p>
      </div>
      <div class="site-step">
        <?= kaammilo_icon('letter') ?>
        <div class="site-step-n">Step 2</div>
        <h3>Tailor</h3>
        <p>Paste a JD or start from a listing. KaamMilo copies Main docs so you can tweak summary, skills, and the cover for that company.</p>
      </div>
      <div class="site-step">
        <?= kaammilo_icon('rocket') ?>
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
        <?= kaammilo_icon('search') ?>
        <h3>Jobs</h3>
        <p>Filters for city, level, posted date, sources, and company boards. Match against your active resume when you want.</p>
      </article>
      <article class="site-feature">
        <?= kaammilo_icon('company') ?>
        <h3>Companies</h3>
        <p>Shared career catalog for everyone, plus personal boards you add. Sitemap boards pull live openings with links.</p>
      </article>
      <article class="site-feature">
        <?= kaammilo_icon('doc') ?>
        <h3>Resume &amp; cover</h3>
        <p>Editors, design themes, and PDF EN/DE. Translation is gated per account when enabled by an admin.</p>
      </article>
      <article class="site-feature">
        <?= kaammilo_icon('track') ?>
        <h3>Applications</h3>
        <p>A simple board of what you sent where — company, role, location, and status.</p>
      </article>
    </div>
    <p class="mt-4 mb-0"><a href="/guide.php">Full how-to guide →</a> · <a href="/features.php">All features →</a></p>
  </section>

  <section class="site-cta-band site-reveal">
    <div class="site-cta-band-inner">
      <div>
        <h2><?= $loggedIn ? 'Continue in the portal' : 'Ready to poke around?' ?></h2>
        <p><?= $loggedIn
            ? 'You are signed in. Open the job portal to search roles, tailor docs, and track applications.'
            : 'Create a test account and walk through a sample application flow in a few minutes.' ?></p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($loggedIn): ?>
          <a class="btn btn-light" href="<?= App::e($portalHref) ?>">Open portal</a>
          <a class="btn btn-outline-light" href="/logout.php">Log out</a>
        <?php else: ?>
          <a class="btn btn-light" href="/register.php">Create account</a>
          <a class="btn btn-outline-light" href="/login.php">Sign in</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php
site_layout_footer();
