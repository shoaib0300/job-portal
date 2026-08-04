<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

Db::pdo()->prepare('UPDATE resume_profile SET date_of_birth = ? WHERE id = 1')
    ->execute(['2000-10-28']);

$p = App::profile();
echo 'OK date_of_birth=' . ($p['date_of_birth'] ?? '') . "\n";
echo 'formatted=' . App::formatDate((string) ($p['date_of_birth'] ?? '')) . "\n";
