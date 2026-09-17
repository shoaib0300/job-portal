<?php

declare(strict_types=1);

$script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$items = [
    ['href' => '/interview-prep', 'label' => 'Browse', 'match' => 'interview-prep.php'],
    ['href' => '/interview-prep-match', 'label' => 'Match / Practice', 'match' => 'interview-prep-match.php'],
    ['href' => '/interview-prep-mine', 'label' => 'My questions', 'match' => 'interview-prep-mine.php'],
    ['href' => '/interview-prep-import', 'label' => 'Import', 'match' => 'interview-prep-import.php'],
];
?>
<nav class="mb-3 d-flex flex-wrap gap-2" aria-label="Interview prep">
  <?php foreach ($items as $it): ?>
    <a class="btn btn-sm <?= $script === $it['match'] ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= App::e($it['href']) ?>"><?= App::e($it['label']) ?></a>
  <?php endforeach; ?>
</nav>
