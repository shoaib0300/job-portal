<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

site_layout_header('About');
?>
<section class="site-section" style="padding-top:2.25rem">
  <div class="site-kicker">About</div>
  <h1 class="h2">About KaamMilo</h1>
  <p>KaamMilo helps job seekers in Germany move from finding a role to sending a tailored application — without losing track of what was sent where.</p>
  <p>Search boards and company career pages, tailor a copy of your resume and cover letter for each job, download EN/DE PDFs, and keep applications organized in one dashboard.</p>

  <div class="site-module-grid mt-4">
    <article class="site-module site-reveal" id="testing">
      <?= kaammilo_icon('lab') ?>
      <h3>Testing module</h3>
      <p>This public site and the logged-in app are a <strong>testing module</strong>. We use it to try UX, job sources, PDF export, and admin controls. Expect incomplete polish, occasional downtime, and data resets.</p>
    </article>
    <article class="site-module site-reveal">
      <?= kaammilo_icon('user') ?>
      <h3>Who it’s for</h3>
      <p>Candidates applying to German employers — students, juniors, and experienced professionals who want a practical workflow instead of scattered Word files.</p>
    </article>
  </div>

  <h2 class="h5 mt-4">What “portal” means here</h2>
  <p>Public pages (Home, How to use, Features, About) explain the product. After you sign in, the dashboard is the working app: Jobs, Companies, Resume, Cover letter, Applications, Account.</p>

  <h2 class="h5 mt-4">Contact</h2>
  <p class="mb-0">Questions about access or feedback on the testing module: <a href="mailto:shoaibsarwar187@gmail.com">shoaibsarwar187@gmail.com</a>.</p>
</section>
<?php
site_layout_footer();
