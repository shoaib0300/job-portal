<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

SuperAdmin::logout();
App::redirect('/super-admin/');
