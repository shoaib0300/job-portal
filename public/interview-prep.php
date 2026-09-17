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
    'technology' => trim((string) ($_GET['technology'] ?? '')),
    'type' => trim((string) ($_GET['type'] ?? '')),
    'level' => trim((string) ($_GET['level'] ?? '')),
    'stage' => trim((string) ($_GET['stage'] ?? '')),
    'language' => trim((string) ($_GET['language'] ?? '')),
    'difficulty' => trim((string) ($_GET['difficulty'] ?? '')),
    'favorite' => isset($_GET['favorite']) && (string) $_GET['favorite'] !== '' && (string) $_GET['favorite'] !== '0' ? '1' : '',
    'practiced' => isset($_GET['practiced']) && (string) $_GET['practiced'] !== '' && (string) $_GET['practiced'] !== '0' ? '1' : '',
    'for_user' => $uid,
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'per_page' => 24,
];

// Validate cascade parents → clear invalid children (also used for URL restore)
$cascade = InterviewTaxonomy::cascadePayload(
    $filters['industry'] !== '' ? $filters['industry'] : null,
    $filters['occupation'] !== '' ? $filters['occupation'] : null,
    $filters['specialization'] !== '' ? $filters['specialization'] : null
);
$filters['industry'] = (string) ($cascade['industry'] ?? $filters['industry'] ?? '');
$filters['occupation'] = (string) ($cascade['occupation'] ?? '');
$filters['specialization'] = (string) ($cascade['specialization'] ?? '');

$skillOk = $filters['skill'] === '' || in_array($filters['skill'], array_column($cascade['skills'], 'slug'), true);
if (!$skillOk) {
    $filters['skill'] = '';
}
$techOk = $filters['technology'] === '' || in_array($filters['technology'], array_column($cascade['technologies'], 'slug'), true);
if (!$techOk) {
    $filters['technology'] = '';
}

$searchFilters = $filters;
if ($searchFilters['favorite'] === '') {
    unset($searchFilters['favorite']);
}
if ($searchFilters['practiced'] === '') {
    unset($searchFilters['practiced']);
}

$result = InterviewQuestionRepo::search($searchFilters);
$stats = InterviewProgress::dashboard($uid);
$industries = InterviewTaxonomy::industries();

$wantsJson = (string) ($_GET['format'] ?? '') === 'json'
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'application/json')
        && (string) ($_GET['ajax'] ?? '') === '1');

ob_start();
?>
<div data-interview-results-panel>
  <p class="small text-secondary mb-2" data-interview-count><?= (int) $result['total'] ?> questions</p>
  <div class="list-group shadow-sm mb-3">
    <?php if ($result['items'] === []): ?>
      <div class="list-group-item text-secondary">No questions match these filters.</div>
    <?php endif; ?>
    <?php foreach ($result['items'] as $item): ?>
      <a class="list-group-item list-group-item-action" href="/interview-prep-question?id=<?= (int) $item['id'] ?>">
        <div class="d-flex justify-content-between gap-2">
          <span><?= App::e(mb_substr((string) $item['question_text'], 0, 180)) ?></span>
          <span class="badge text-bg-light text-nowrap"><?= App::e((string) $item['question_type']) ?></span>
        </div>
        <div class="small text-secondary">
          <?= App::e((string) $item['language']) ?>
          · <?= App::e((string) $item['difficulty']) ?>
          <?= (($item['visibility'] ?? '') === 'personal') ? ' · personal' : '' ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($result['total'] > $result['per_page']): ?>
    <div class="d-flex gap-2" data-interview-pagination>
      <?php
        $qbase = array_filter([
            'q' => $filters['q'],
            'industry' => $filters['industry'],
            'occupation' => $filters['occupation'],
            'specialization' => $filters['specialization'],
            'skill' => $filters['skill'],
            'technology' => $filters['technology'],
            'type' => $filters['type'],
            'difficulty' => $filters['difficulty'],
            'level' => $filters['level'],
            'stage' => $filters['stage'],
            'language' => $filters['language'],
            'favorite' => $filters['favorite'],
            'practiced' => $filters['practiced'],
        ], static fn($v) => $v !== null && $v !== '');
        $qstr = http_build_query($qbase);
      ?>
      <?php if ($result['page'] > 1): ?>
        <a class="btn btn-sm btn-outline-secondary" data-interview-page="<?= $result['page'] - 1 ?>" href="?<?= App::e($qstr . ($qstr !== '' ? '&' : '') . 'page=' . ($result['page'] - 1)) ?>">Previous</a>
      <?php endif; ?>
      <?php if ($result['page'] * $result['per_page'] < $result['total']): ?>
        <a class="btn btn-sm btn-outline-secondary" data-interview-page="<?= $result['page'] + 1 ?>" href="?<?= App::e($qstr . ($qstr !== '' ? '&' : '') . 'page=' . ($result['page'] + 1)) ?>">Next</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php
$listHtml = ob_get_clean();

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'html' => $listHtml,
        'total' => $result['total'],
        'page' => $result['page'],
        'filters' => [
            'industry' => $filters['industry'],
            'occupation' => $filters['occupation'],
            'specialization' => $filters['specialization'],
            'skill' => $filters['skill'],
            'technology' => $filters['technology'],
        ],
        'cascade' => $cascade,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

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
  </div>

  <form method="get" class="row g-2 mb-3" id="user-q-filters" data-interview-ajax>
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
      <select class="form-select" name="technology" id="f-technology">
        <option value="">All technologies</option>
        <?php foreach ($cascade['technologies'] as $tech): ?>
          <option value="<?= App::e($tech['slug']) ?>"<?= $filters['technology'] === $tech['slug'] ? ' selected' : '' ?>><?= App::e($tech['name_en']) ?></option>
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
    <?php if ($uid > 0): ?>
      <div class="col-md-2">
        <select class="form-select" name="favorite">
          <option value="">Favorites</option>
          <option value="1"<?= $filters['favorite'] === '1' ? ' selected' : '' ?>>Favorites only</option>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select" name="practiced">
          <option value="">Practice</option>
          <option value="1"<?= $filters['practiced'] === '1' ? ' selected' : '' ?>>Practiced only</option>
        </select>
      </div>
    <?php endif; ?>
    <div class="col-md-2 d-flex gap-1">
      <button class="btn btn-primary w-100" type="submit">Apply</button>
      <a class="btn btn-outline-secondary" href="/interview-prep">Reset</a>
    </div>
  </form>

  <div data-interview-results class="position-relative">
    <div data-interview-loading class="text-secondary small py-2" hidden aria-hidden="true">Updating…</div>
    <?= $listHtml ?>
  </div>
</main>
<script src="/assets/js/interview-cascade.js?v=2"></script>
<script>
window.InterviewCascade?.bind({
  form: '#user-q-filters',
  industry: '#f-industry',
  occupation: '#f-occupation',
  specialization: '#f-specialization',
  skill: '#f-skill',
  technology: '#f-technology',
  endpoint: '/interview-prep-taxonomy.php',
  listUrl: '/interview-prep',
  results: '[data-interview-results]',
  autoApply: true
});
</script>
<?php
layout_footer();
