<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';

site_layout_header('About');
?>
<section class="site-section" style="padding-top:2.5rem">
  <h1 class="h2 mb-2">About ApplyPath</h1>
  <p>ApplyPath helps job seekers in Germany move from finding a role to sending a tailored application — without losing track of what was sent where.</p>
  <p>Search boards and company career pages, tailor a copy of your resume and cover letter for each job, download EN/DE PDFs, and keep applications organized in one dashboard.</p>
  <h2 class="h5 mt-4">Who it’s for</h2>
  <p>Candidates applying to German employers — students, juniors, and experienced professionals who want a calm, practical workflow instead of scattered Word files.</p>
  <h2 class="h5 mt-4">Contact</h2>
  <p class="mb-0">Questions about access or the product: <a href="mailto:shoaibsarwar187@gmail.com">shoaibsarwar187@gmail.com</a>.</p>
</section>
<?php
site_layout_footer();
