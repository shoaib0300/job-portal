<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/layout.php';
require_once dirname(__DIR__, 2) . '/src/site_layout.php';
require_once dirname(__DIR__, 2) . '/src/dashboard_home.php';

dashboard_home_render();
