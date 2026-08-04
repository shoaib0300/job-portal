<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

Versions::ensureSchema();
echo "OK resume_versions + cover_letters.is_base ready\n";
echo 'resume_versions=' . count(Versions::resumeVersions()) . "\n";
echo 'cover_letters=' . count(App::coverLetters()) . "\n";
