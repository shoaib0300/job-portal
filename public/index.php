<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';
require_once dirname(__DIR__) . '/src/dashboard_home.php';

// Portal subdomain = job app home (not marketing).
if (Site::isPortalHost()) {
    if (Auth::id() <= 0) {
        App::redirect('/login?next=' . rawurlencode('/'));
    }
    dashboard_home_render();
    exit;
}

// Marketing host root is ALWAYS the public website (even when logged in).
site_layout_header('Home');
?>
  <section class="site-hero">
    <div class="site-hero-grid">
      <div>
        <div class="site-hero-brand">
          <?= kaamfit_logo_mark() ?>
          <span><?= App::e(kaamfit_brand_name()) ?></span>
        </div>
        <h1><?= App::e(kaamfit_brand_tagline()) ?></h1>
        <p class="lead">Find roles, tailor resume &amp; cover letter copies for each company, export PDFs, and keep applications organized.</p>
        <div class="site-hero-cta">
          <?php if (Auth::id() > 0): ?>
            <a class="btn btn-primary btn-lg" href="<?= App::e(Site::portalHomeUrl()) ?>">Open dashboard</a>
            <a class="btn btn-outline-secondary btn-lg" href="/guide">How to use</a>
          <?php else: ?>
            <a class="btn btn-primary btn-lg" href="/demo">Try the demo</a>
            <a class="btn btn-outline-secondary btn-lg" href="/register">Create account</a>
            <a class="btn btn-outline-secondary btn-lg" href="/guide">How to use</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="site-hero-art" aria-hidden="true">
        <div class="site-blob site-blob-a"></div>
        <div class="site-blob site-blob-b"></div>
        <div class="site-cartoon-card site-cartoon-1"><?= kaamfit_icon('search') ?> Find jobs</div>
        <div class="site-cartoon-card site-cartoon-2"><?= kaamfit_icon('doc') ?> Tailor docs</div>
        <div class="site-cartoon-card site-cartoon-3"><?= kaamfit_icon('track') ?> Track apps</div>
      </div>
    </div>
  </section>

  <section class="site-section site-reveal">
    <div class="site-kicker">What this portal is</div>
    <h2>A testing module, not a finished product</h2>
    <p><?= App::e(kaamfit_brand_name()) ?> is under active testing. Expect rough edges, changing features, and demo-style data. Use it to explore the workflow — then give feedback.</p>
    <div class="site-module-grid">
      <article class="site-module">
        <?= kaamfit_icon('lab') ?>
        <h3>Why “testing module”?</h3>
        <p>We are validating job search, document tailoring, PDF export, and applications tracking before a wider release. Accounts may be reset.</p>
      </article>
      <article class="site-module">
        <?= kaamfit_icon('spark') ?>
        <h3>What you can try today</h3>
        <p>Register, search German boards &amp; company career pages, paste a JD, edit resume/cover copies, download PDFs, and log applications.</p>
      </article>
    </div>
  </section>

  <section class="site-section site-reveal">
    <div class="site-kicker">How it works</div>
    <h2>From open role to sent application</h2>
    <p>Three playful steps — your Master CV and Master cover letter stay untouched.</p>
    <div class="site-steps">
      <div class="site-step">
        <?= kaamfit_icon('search') ?>
        <div class="site-step-n">Step 1</div>
        <h3>Find jobs</h3>
        <p>Search Arbeitsagentur, Jobexport, and company career boards (Greenhouse, Personio, Rossmann, DIS AG, and more).</p>
      </div>
      <div class="site-step">
        <?= kaamfit_icon('letter') ?>
        <div class="site-step-n">Step 2</div>
        <h3>Tailor</h3>
        <p>Paste a JD or start from a listing. <?= App::e(kaamfit_brand_name()) ?> copies your Master CV into a Job CV so you can tweak summary, skills, and the cover for that company.</p>
      </div>
      <div class="site-step">
        <?= kaamfit_icon('rocket') ?>
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
        <?= kaamfit_icon('search') ?>
        <h3>Jobs</h3>
        <p>Filters for city, level, posted date, sources, and company boards. Match against your active resume when you want.</p>
      </article>
      <article class="site-feature">
        <?= kaamfit_icon('company') ?>
        <h3>Companies</h3>
        <p>Shared career catalog for everyone, plus personal boards you add. Sitemap boards pull live openings with links.</p>
      </article>
      <article class="site-feature">
        <?= kaamfit_icon('doc') ?>
        <h3>Resume &amp; cover</h3>
        <p>Editors, design themes, and PDF export. Free download in your document language; optional DeepL translation is billed per account.</p>
      </article>
      <article class="site-feature">
        <?= kaamfit_icon('track') ?>
        <h3>Applications</h3>
        <p>A simple board of what you sent where — company, role, location, and status.</p>
      </article>
    </div>
    <p class="mt-4 mb-0"><a href="/guide">Full how-to guide →</a> · <a href="/features">All features →</a></p>
  </section>

  <section class="site-cta-band site-reveal">
    <div class="site-cta-band-inner">
      <div>
        <h2>Ready to poke around?</h2>
        <p>Create a test account and walk through a sample application flow in a few minutes.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if (Auth::id() > 0): ?>
          <a class="btn btn-light" href="<?= App::e(Site::portalHomeUrl()) ?>">Open dashboard</a>
        <?php else: ?>
          <a class="btn btn-light" href="/demo">Try the demo</a>
          <a class="btn btn-outline-light" href="/register">Create account</a>
          <a class="btn btn-outline-light" href="/login">Sign in</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php
site_layout_footer();
