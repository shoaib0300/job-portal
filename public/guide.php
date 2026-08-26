<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

site_layout_header('How to use');
?>
<section class="site-section" style="padding-top:2.25rem">
  <div class="site-kicker">Guide</div>
  <h1 class="h2">How to use ApplyPath</h1>
  <p>Follow this path the first time you open the testing portal. It takes about 10–15 minutes for a full dry run.</p>

  <div class="site-guide-list">
    <article class="site-guide-step site-reveal">
      <?= applypath_icon('user') ?>
      <div>
        <div class="site-guide-num">01 · Account</div>
        <h3>Register or sign in</h3>
        <p>Create an account on <a href="/register.php">Register</a>. After login you land on the dashboard (Home) with shortcuts to Jobs, Resume, Cover, and Applications.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= applypath_icon('search') ?>
      <div>
        <div class="site-guide-num">02 · Jobs</div>
        <h3>Search openings</h3>
        <p>Open <strong>Jobs</strong>. Add role keywords (e.g. QA, Verkäufer), optionally a city, pick sources, and optionally filter companies (Rossmann, DIS AG, Greenhouse boards…). Click Search. Open a listing and use its employer link to apply later.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= applypath_icon('company') ?>
      <div>
        <div class="site-guide-num">03 · Companies</div>
        <h3>Career boards</h3>
        <p>On <strong>Companies</strong>, browse the shared catalog (read-only) and add personal boards if needed. Filter by Shared / Mine / type. Sitemap boards (DIS AG, Rossmann) feed Jobs with live links.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= applypath_icon('letter') ?>
      <div>
        <div class="site-guide-num">04 · Tailor</div>
        <h3>New job from a JD</h3>
        <p>Go to <strong>New job</strong> (tailor). Paste company, role, location, and the job description. ApplyPath copies your Main resume and Main cover into new versions for that application — Main stays clean.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= applypath_icon('doc') ?>
      <div>
        <div class="site-guide-num">05 · Edit &amp; style</div>
        <h3>Resume and cover letter</h3>
        <p>Use <strong>Resume</strong> / <strong>Cover letter</strong> editors to adjust summary, skills, and letter text. Use Style pages for fonts and accents. Download <strong>PDF EN</strong> or <strong>PDF DE</strong> when translation is allowed on your account.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= applypath_icon('track') ?>
      <div>
        <div class="site-guide-num">06 · Track</div>
        <h3>Applications board</h3>
        <p>Open <strong>Applications</strong> to see what you logged. Update status as you move from applied → interview → offer / rejected. Keep location filled so documents stay consistent.</p>
      </div>
    </article>
    <article class="site-guide-step site-reveal">
      <?= applypath_icon('lab') ?>
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
          <a class="btn btn-light" href="/jobs.php">Open Jobs</a>
        <?php else: ?>
          <a class="btn btn-light" href="/register.php">Create account</a>
          <a class="btn btn-outline-light" href="/login.php">Sign in</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php
site_layout_footer();
