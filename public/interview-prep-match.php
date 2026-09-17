<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Interview\InterviewMatcher;
use KaamFit\Interview\InterviewQuestionRepo;
use KaamFit\Interview\InterviewSchema;

InterviewSchema::ensureSchema();

$resumes = Versions::resumeVersions();
$apps = [];
try {
    $stmt = Db::pdo()->prepare(
        'SELECT id, company, role, jd_snippet, resume_version_id FROM applications WHERE user_id = ? ORDER BY id DESC LIMIT 80'
    );
    $stmt->execute([Auth::id()]);
    $apps = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $apps = [];
}

$resumeId = isset($_GET['resume']) ? (int) $_GET['resume'] : 0;
$appId = isset($_GET['application']) ? (int) $_GET['application'] : (isset($_GET['app']) ? (int) $_GET['app'] : 0);
$language = trim((string) ($_GET['language'] ?? ''));
$typeFilter = trim((string) ($_GET['type'] ?? ''));

$jd = '';
$role = '';
if ($appId > 0) {
    foreach ($apps as $app) {
        if ((int) $app['id'] === $appId) {
            $jd = (string) ($app['jd_snippet'] ?? '');
            $role = trim((string) ($app['role'] ?? '') . ' ' . (string) ($app['company'] ?? ''));
            if ($resumeId < 1 && !empty($app['resume_version_id'])) {
                $resumeId = (int) $app['resume_version_id'];
            }
            break;
        }
    }
}

$match = InterviewMatcher::match(
    $resumeId > 0 ? $resumeId : null,
    $jd !== '' ? $jd : null,
    $role !== '' ? $role : null,
    $language,
    Auth::id()
);

$ids = $match['question_ids'];
if ($typeFilter !== '') {
    // Keep ranking but filter type via search
    $page = InterviewQuestionRepo::search([
        'ids' => array_slice($ids, 0, 200),
        'type' => $typeFilter,
        'language' => $language,
        'status' => 'published',
        'per_page' => 60,
        'page' => 1,
    ]);
} else {
    $page = InterviewQuestionRepo::search([
        'ids' => array_slice($ids, 0, 120),
        'language' => $language,
        'status' => 'published',
        'per_page' => 60,
        'page' => 1,
    ]);
    // Re-order by matcher score order
    $byId = [];
    foreach ($page['items'] as $item) {
        $byId[(int) $item['id']] = $item;
    }
    $ordered = [];
    foreach (array_slice($ids, 0, 60) as $qid) {
        if (isset($byId[$qid])) {
            $ordered[] = $byId[$qid];
        }
    }
    $page['items'] = $ordered;
}

layout_header('Match interview questions');
?>
<main class="container-fluid px-3 px-lg-4 py-3">
  <?php require dirname(__DIR__) . '/src/interview_prep_nav.php'; ?>
  <header class="page-head mb-3">
    <h1>Match with my resume<?= $appId > 0 ? ' + job' : '' ?></h1>
    <p class="text-secondary">One matcher for every occupation — skills, role, and JD drive the list.</p>
  </header>

  <form method="get" class="row g-2 mb-4">
    <div class="col-md-4">
      <label class="form-label">Resume</label>
      <select class="form-select" name="resume">
        <option value="0">Active / Main resume</option>
        <?php foreach ($resumes as $rv): ?>
          <option value="<?= (int) $rv['id'] ?>"<?= $resumeId === (int) $rv['id'] ? ' selected' : '' ?>>
            #<?= (int) $rv['id'] ?> <?= App::e(Versions::resumeDisplayLabel($rv)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Optional application / job</label>
      <select class="form-select" name="application">
        <option value="0">None</option>
        <?php foreach ($apps as $app): ?>
          <option value="<?= (int) $app['id'] ?>"<?= $appId === (int) $app['id'] ? ' selected' : '' ?>>
            #<?= (int) $app['id'] ?> <?= App::e((string) $app['company']) ?> — <?= App::e((string) $app['role']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Language</label>
      <select class="form-select" name="language">
        <option value="">Any</option>
        <option value="en"<?= $language === 'en' ? ' selected' : '' ?>>EN</option>
        <option value="de"<?= $language === 'de' ? ' selected' : '' ?>>DE</option>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary w-100" type="submit">Match</button>
    </div>
  </form>

  <div class="row g-2 mb-3">
    <?php foreach ([
        'resume_skills' => 'Resume skills',
        'experience' => 'Experience',
        'occupation' => 'Occupation',
        'industry' => 'Industry',
        'general' => 'General',
        'total' => 'Total',
    ] as $k => $label): ?>
      <div class="col-6 col-md-2">
        <div class="card shadow-sm"><div class="card-body py-2">
          <div class="small text-secondary"><?= App::e($label) ?></div>
          <div class="h5 mb-0"><?= (int) ($match['buckets'][$k] ?? 0) ?></div>
        </div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($match['terms'] !== []): ?>
    <p class="small text-secondary mb-3">Matched terms: <?= App::e(implode(', ', array_slice($match['terms'], 0, 20))) ?></p>
  <?php endif; ?>

  <div class="list-group shadow-sm">
    <?php foreach ($page['items'] as $item): ?>
      <a class="list-group-item list-group-item-action" href="/interview-prep-question?id=<?= (int) $item['id'] ?>">
        <div class="small text-secondary mb-1"><?= App::e((string) $item['question_type']) ?> · <?= App::e((string) $item['language']) ?></div>
        <div><?= App::e((string) $item['question_text']) ?></div>
      </a>
    <?php endforeach; ?>
    <?php if ($page['items'] === []): ?>
      <div class="list-group-item text-secondary">No matched questions yet — try another resume or add skills to your CV.</div>
    <?php endif; ?>
  </div>
</main>
<?php
layout_footer();
