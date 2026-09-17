<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Interview\InterviewProgress;
use KaamFit\Interview\InterviewQuestionRepo;
use KaamFit\Interview\InterviewSchema;
use KaamFit\Interview\InterviewTaxonomy;

InterviewSchema::ensureSchema();

$uid = Auth::id();
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'industry' => trim((string) ($_GET['industry'] ?? '')),
    'occupation' => trim((string) ($_GET['occupation'] ?? '')),
    'specialization' => trim((string) ($_GET['specialization'] ?? '')),
    'skill' => trim((string) ($_GET['skill'] ?? '')),
    'type' => trim((string) ($_GET['type'] ?? '')),
    'level' => trim((string) ($_GET['level'] ?? '')),
    'stage' => trim((string) ($_GET['stage'] ?? '')),
    'language' => trim((string) ($_GET['language'] ?? '')),
    'difficulty' => trim((string) ($_GET['difficulty'] ?? '')),
    'for_user' => $uid,
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'per_page' => 24,
];
// Browse default: published universal (+ user's personal via for_user)
if (!isset($_GET['status'])) {
    // leave status unset so for_user branch handles visibility
}

$result = InterviewQuestionRepo::search($filters);
$stats = InterviewProgress::dashboard($uid);
$industries = InterviewTaxonomy::industries();
$cascade = InterviewTaxonomy::cascadePayload(
    $filters['industry'] !== '' ? $filters['industry'] : null,
    $filters['occupation'] !== '' ? $filters['occupation'] : null,
    $filters['specialization'] !== '' ? $filters['specialization'] : null
);

layout_header('Interview preparation');
?>
<main class="container-fluid px-3 px-lg-4 py-3">
  <?php require dirname(__DIR__) . '/src/interview_prep_nav.php'; ?>
  <header class="page-head mb-3">
    <h1>Interview preparation</h1>
    <p class="text-secondary mb-0">Browse questions across every occupation — or match to your resume and a job.</p>
  </header>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Overall</div>
        <div class="h4 mb-1"><?= (int) $stats['percent'] ?>%</div>
        <div class="progress" style="height:8px"><div class="progress-bar" style="width:<?= (int) $stats['percent'] ?>%"></div></div>
      </div></div>
    </div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Practiced</div><div class="h4 mb-0"><?= (int) $stats['practiced'] ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Favorites</div><div class="h4 mb-0"><?= (int) $stats['favorites'] ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Need practice</div><div class="h4 mb-0"><?= (int) $stats['need_practice'] ?></div></div></div></div>
  </div>

  <?php if ($stats['focus'] !== []): ?>
    <p class="small mb-3">Focus areas: <strong><?= App::e(implode(' · ', $stats['focus'])) ?></strong></p>
  <?php endif; ?>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-primary btn-sm" href="/interview-prep-match">Match with my resume</a>
    <a class="btn btn-outline-primary btn-sm" href="/interview-prep-import">Import questions</a>
    <span class="align-self-center small text-secondary"><?= (int) $result['total'] ?> questions</span>
  </div>

  <form method="get" class="row g-2 mb-3" id="user-q-filters">
    <div class="col-md-3"><input class="form-control" name="q" value="<?= App::e($filters['q']) ?>" placeholder="Search questions"></div>
    <div class="col-md-3">
      <select class="form-select" name="industry" id="f-industry">
        <option value="">All industries</option>
        <?php foreach ($industries as $ind): ?>
          <option value="<?= App::e((string) $ind['slug']) ?>"<?= $filters['industry'] === $ind['slug'] ? ' selected' : '' ?>><?= App::e((string) $ind['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select class="form-select" name="occupation" id="f-occupation">
        <option value="">All occupations</option>
        <?php foreach ($cascade['occupations'] as $occ): ?>
          <option value="<?= App::e($occ['slug']) ?>"<?= $filters['occupation'] === $occ['slug'] ? ' selected' : '' ?>><?= App::e($occ['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select class="form-select" name="specialization" id="f-specialization">
        <option value="">All specializations</option>
        <?php foreach ($cascade['specializations'] as $sp): ?>
          <option value="<?= App::e($sp['slug']) ?>"<?= $filters['specialization'] === $sp['slug'] ? ' selected' : '' ?>><?= App::e($sp['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select" name="skill" id="f-skill">
        <option value="">All skills</option>
        <?php foreach ($cascade['skills'] as $sk): ?>
          <option value="<?= App::e($sk['slug']) ?>"<?= $filters['skill'] === $sk['slug'] ? ' selected' : '' ?>><?= App::e($sk['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select" name="type">
        <option value="">All types</option>
        <?php foreach (InterviewSchema::questionTypes() as $t): ?>
          <option value="<?= App::e($t) ?>"<?= $filters['type'] === $t ? ' selected' : '' ?>><?= App::e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select" name="difficulty">
        <option value="">Difficulty</option>
        <?php foreach (InterviewSchema::difficulties() as $d): ?>
          <option value="<?= App::e($d) ?>"<?= $filters['difficulty'] === $d ? ' selected' : '' ?>><?= App::e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select" name="level">
        <option value="">Career level</option>
        <?php foreach (InterviewSchema::careerLevels() as $lv): ?>
          <option value="<?= App::e($lv) ?>"<?= $filters['level'] === $lv ? ' selected' : '' ?>><?= App::e($lv) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select" name="stage">
        <option value="">Stage</option>
        <?php foreach (InterviewTaxonomy::stages() as $st): ?>
          <option value="<?= App::e((string) $st['slug']) ?>"<?= $filters['stage'] === $st['slug'] ? ' selected' : '' ?>><?= App::e((string) $st['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1">
      <select class="form-select" name="language">
        <option value="">Lang</option>
        <?php foreach (['en', 'de'] as $lang): ?>
          <option value="<?= $lang ?>"<?= $filters['language'] === $lang ? ' selected' : '' ?>><?= $lang ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-1">
      <button class="btn btn-primary w-100" type="submit">Filter</button>
      <a class="btn btn-outline-secondary" href="/interview-prep">Reset</a>
    </div>
  </form>

  <div class="list-group shadow-sm mb-3">
    <?php foreach ($result['items'] as $item): ?>
      <a class="list-group-item list-group-item-action" href="/interview-prep-question?id=<?= (int) $item['id'] ?>">
        <div class="d-flex justify-content-between gap-2">
          <span><?= App::e(mb_substr((string) $item['question_text'], 0, 180)) ?></span>
          <span class="badge text-bg-light text-nowrap"><?= App::e((string) $item['question_type']) ?></span>
        </div>
        <div class="small text-secondary"><?= App::e((string) $item['language']) ?> · <?= App::e((string) $item['difficulty']) ?><?= (($item['visibility'] ?? '') === 'personal') ? ' · personal' : '' ?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($result['total'] > $result['per_page']): ?>
    <div class="d-flex gap-2">
      <?php
        $qbase = $_GET;
        unset($qbase['page']);
        $qstr = http_build_query($qbase);
      ?>
      <?php if ($result['page'] > 1): ?>
        <a class="btn btn-sm btn-outline-secondary" href="?<?= App::e($qstr . ($qstr !== '' ? '&' : '') . 'page=' . ($result['page'] - 1)) ?>">Previous</a>
      <?php endif; ?>
      <?php if ($result['page'] * $result['per_page'] < $result['total']): ?>
        <a class="btn btn-sm btn-outline-secondary" href="?<?= App::e($qstr . ($qstr !== '' ? '&' : '') . 'page=' . ($result['page'] + 1)) ?>">Next</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>
<script src="/assets/js/interview-cascade.js?v=1"></script>
<script>window.InterviewCascade?.bind({industry:'#f-industry',occupation:'#f-occupation',specialization:'#f-specialization',skill:'#f-skill',endpoint:'/interview-prep-taxonomy.php'});</script>
<?php
layout_footer();
