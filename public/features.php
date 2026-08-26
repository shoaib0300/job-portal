<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

site_layout_header('Features');
?>
<section class="site-section" style="padding-top:2.5rem">
  <h1 class="h2 mb-2">Features</h1>
  <p>Everything you need to search, tailor, and follow up — without juggling spreadsheets and folders.</p>
  <div class="site-feature-grid">
    <article class="site-feature">
      <h3>Jobs search</h3>
      <p>Search German sources in one place: Bundesagentur, Jobexport, company career boards, and more — with filters for city, level, and posted date.</p>
    </article>
    <article class="site-feature">
      <h3>Company career boards</h3>
      <p>A shared catalog of employer career pages (Greenhouse, Personio, SmartRecruiters, and sites), plus personal boards you add yourself.</p>
    </article>
    <article class="site-feature">
      <h3>Resume &amp; cover tailor</h3>
      <p>Paste a JD or pick a role. ApplyPath copies your Main documents so each application gets its own resume and letter — Main stays clean.</p>
    </article>
    <article class="site-feature">
      <h3>Applications tracker</h3>
      <p>Log applied, interview, offer, and rejected statuses with company, role, and location so nothing falls through the cracks.</p>
    </article>
    <article class="site-feature">
      <h3>EN &amp; DE PDFs</h3>
      <p>Download English and German PDFs for resumes and cover letters. Translation usage is tracked per account when enabled.</p>
    </article>
    <article class="site-feature">
      <h3>Design themes</h3>
      <p>Pick resume and cover styles, fonts, and accents so documents look consistent before you send them.</p>
    </article>
  </div>
  <p class="mt-4 mb-0">
    <?php if (Auth::id() > 0): ?>
      <a class="btn btn-primary" href="/">Open dashboard</a>
    <?php else: ?>
      <a class="btn btn-primary" href="/register.php">Create a free account</a>
      <a class="btn btn-outline-secondary" href="/login.php">Sign in</a>
    <?php endif; ?>
  </p>
</section>
<?php
site_layout_footer();
