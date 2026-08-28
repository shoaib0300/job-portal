<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';
require_once dirname(__DIR__) . '/src/onboarding.php';
require_once dirname(__DIR__) . '/src/guide_page.php';

Auth::requireLogin();

layout_header('How to use');
guide_render_dashboard_page();
layout_footer();
