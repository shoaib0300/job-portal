<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';
require_once dirname(__DIR__) . '/src/onboarding.php';

site_layout_header('How to use', [
    'extra_stylesheets' => [
        '/assets/css/dashboard.css?v=20260828h',
        '/assets/css/onboarding.css?v=20260828i',
    ],
]);
?>
<section class="site-section" style="padding-top:2.25rem">
  <div class="site-kicker">Guide</div>
  <h1 class="h2">How to use <?= App::e(kaamfit_brand_name()) ?></h1>
  <p>Follow this path the first time you open the testing portal. It takes about 10–15 minutes for a full dry run.</p>

  <?php onboarding_render_hero(); ?>

  <div class="site-guide-list">
    <article class="site-guide-step site-reveal">
      <?= kaamfit_icon('user') ?>
      <div>
        <div class="site-guide-num">01 · Account</div>
        <h3>Register or sign in</h3>
        <p>Create an account on <a href="/register">Register</a>. After login you land on the dashboard (Home) with shortcuts to Jobs, Resume, Cover, and Applications.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= kaamfit_icon('search') ?>
      <div>
        <div class="site-guide-num">02 · Jobs</div>
        <?php onboarding_render_mini('jobs', true); ?>
        <h3>Search openings</h3>
        <p>Open <strong>Jobs</strong>. Add role keywords (e.g. QA, Verkäufer), optionally a city, pick sources, and optionally filter companies (Rossmann, DIS AG, Greenhouse boards…). Click Search. Open a listing and use its employer link to apply later.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= kaamfit_icon('company') ?>
      <div>
        <div class="site-guide-num">03 · Companies</div>
        <h3>Career boards</h3>
        <p>On <strong>Companies</strong>, browse the shared catalog (read-only) and add personal boards if needed. Filter by Shared / Mine / type. Sitemap boards (DIS AG, Rossmann) feed Jobs with live links.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= kaamfit_icon('letter') ?>
      <div>
        <div class="site-guide-num">04 · Tailor</div>
        <?php onboarding_render_mini('copy', true); ?>
        <h3>New job from a JD</h3>
        <p>Go to <strong>New job</strong> (tailor). Paste company, role, location, and the job description. <?= App::e(kaamfit_brand_name()) ?> copies your Master CV and Master cover letter into a Job CV for that application — Master CV stays clean.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= kaamfit_icon('doc') ?>
      <div>
        <div class="site-guide-num">05 · Edit &amp; style</div>
        <?php onboarding_render_mini('edit', true); ?>
        <h3>Resume and cover letter</h3>
        <p>Use <strong>Resume</strong> / <strong>Cover letter</strong> editors to adjust summary, skills, and letter text. Use Style pages for fonts and accents. <strong>Download PDF</strong> prints your document as written (free). Use <strong>Translate PDF</strong> for a DeepL translation (billed per character).</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= kaamfit_icon('track') ?>
      <div>
        <div class="site-guide-num">06 · Track</div>
        <?php onboarding_render_mini('track', true); ?>
        <h3>Applications board</h3>
        <p>Open <strong>Applications</strong> to see what you logged. Update status as you move from applied → interview → offer / rejected. Keep location filled so documents stay consistent.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= kaamfit_icon('lab') ?>
      <div>
        <div class="site-guide-num">07 · Testing tip</div>
        <h3>Expect change</h3>
        <p>This is a testing module. Boards, filters, and translation may behave differently tomorrow. Report issues to the contact on the About page.</p>
      </div>
    </article>
  </div>

  <div class="site-cta-band site-reveal" style="padding-left:0;padding-right:0;max-width:none">
    <div class="site-cta-band-inner">
      <div>
        <h2>Start a dry run</h2>
        <p>Register, search one company board, tailor a sample JD, export a PDF.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if (Auth::id() > 0): ?>
          <a class="btn btn-light" href="/jobs">Open Jobs</a>
        <?php else: ?>
          <a class="btn btn-light" href="/register">Create account</a>
          <a class="btn btn-outline-light" href="/login">Sign in</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php
site_layout_footer([
    'extra_scripts' => [
        '/assets/js/app.js?v=20260828i',
    ],
]);
