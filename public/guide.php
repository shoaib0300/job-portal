<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/site_layout.php';
require_once dirname(__DIR__) . '/src/onboarding.php';
require_once dirname(__DIR__) . '/src/guide_page.php';

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
        <div class="site-guide-num">00 · Account</div>
        <h3>Register or sign in</h3>
        <p>Create an account on <a href="/register">Register</a>. After login you land on the dashboard with a full <a href="/help">How to use</a> guide in the sidebar.</p>
      </div>
    </article>
  </div>

  <?php guide_render_steps('site'); ?>

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
