<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

site_layout_header('Features');
?>
<section class="site-section" style="padding-top:2.25rem">
  <div class="site-kicker">Features</div>
  <h1 class="h2">What the testing portal includes</h1>
  <p>Everything below is available in the logged-in app. Some capabilities (like DE PDF translation) depend on account flags set by an admin.</p>

  <div class="site-feature-grid">
    <article class="site-feature site-reveal">
      <?= kaammilo_icon('search') ?>
      <h3>Jobs search</h3>
      <p>German sources in one place: Bundesagentur, Jobexport, company career boards, and more — with city, level, posted date, and resume-match filters.</p>
    </article>
    <article class="site-feature site-reveal">
      <?= kaammilo_icon('company') ?>
      <h3>Company career boards</h3>
      <p>Shared catalog (Greenhouse, Personio, SmartRecruiters, sites, sitemaps like Rossmann &amp; DIS AG) plus personal boards. Filter Shared / Mine / type.</p>
    </article>
    <article class="site-feature site-reveal">
      <?= kaammilo_icon('letter') ?>
      <h3>Resume &amp; cover tailor</h3>
      <p>Paste a JD or start from a role. Copies of Main documents get light-tailored so each application is separate.</p>
    </article>
    <article class="site-feature site-reveal">
      <?= kaammilo_icon('track') ?>
      <h3>Applications tracker</h3>
      <p>Log applied, interview, offer, and rejected with company, role, and location.</p>
    </article>
    <article class="site-feature site-reveal">
      <?= kaammilo_icon('pdf') ?>
      <h3>EN &amp; DE PDFs</h3>
      <p>Download English and German PDFs. Translation usage is tracked per account when translate is enabled.</p>
    </article>
    <article class="site-feature site-reveal">
      <?= kaammilo_icon('spark') ?>
      <h3>Design themes</h3>
      <p>Pick resume and cover styles, fonts, and accents before you send.</p>
    </article>
  </div>

  <div class="site-module-grid mt-4">
    <article class="site-module site-reveal">
      <?= kaammilo_icon('lab') ?>
      <h3>Testing module note</h3>
      <p>Features can appear, move, or break while we iterate. Prefer throwing sample data into Applications rather than real credentials you care about.</p>
    </article>
    <article class="site-module site-reveal">
      <?= kaammilo_icon('rocket') ?>
      <h3>Suggested first path</h3>
      <p>Jobs → pick Rossmann or DIS AG → New job with a pasted JD → PDF EN → Applications. See the <a href="/guide">how-to guide</a> for detail.</p>
    </article>
  </div>

  <p class="mt-4 mb-0">
    <a href="/demo" class="btn btn-outline-primary btn-sm me-2">Try the interactive demo</a>
    <?php if (Auth::id() > 0): ?>
      <a class="btn btn-primary" href="<?= App::e(Site::portalHomeUrl()) ?>">Open dashboard</a>
    <?php else: ?>
      <a class="btn btn-primary" href="/register">Create a free account</a>
      <a class="btn btn-outline-secondary" href="/login">Sign in</a>
    <?php endif; ?>
  </p>
</section>
<?php
site_layout_footer();
