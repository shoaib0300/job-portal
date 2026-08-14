<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

JobAggregator::ensureSchema();

$source = trim((string) ($_GET['source'] ?? $_POST['source'] ?? ''));
$externalId = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
if ($source === '' || $externalId === '') {
    App::flash('Pick a job from search first.', 'error');
    App::redirect('/jobs.php');
}

$job = JobAggregator::details($source, $externalId);
if ($job === null) {
    App::flash('That listing expired. Search again.', 'error');
    App::redirect('/jobs.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'apply') {
    $location = trim((string) ($_POST['location'] ?? $job->locationLine()));
    if ($location === '') {
        $location = 'Germany';
    }
    $jd = $job->description !== ''
        ? JobText::stripHtml($job->description)
        : ($job->title . ' at ' . $job->company);
    try {
        $result = App::tailorFromJd(
            $job->company !== '' ? $job->company : 'Company',
            $job->title !== '' ? $job->title : 'Role',
            $location,
            $jd,
            $job->url,
            'applied',
            null,
            null,
            null,
            null,
            'From Jobs · ' . (JobQuery::SOURCES[$job->source] ?? $job->source)
        );
        App::flash(
            'Copied Main into resume #' . $result['resume_id']
            . ' and cover #' . $result['cover_id']
            . '. Application #' . $result['application_id']
            . ' · ' . $result['location']
            . ' · ' . App::statusLabel($result['status']) . '.'
        );
        App::redirect('/resume-edit.php');
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/job.php?source=' . rawurlencode($source) . '&id=' . rawurlencode($externalId));
    }
}

$sourceLabels = JobQuery::SOURCES;

layout_header($job->title !== '' ? $job->title : 'Job');
?>
<main class="page-wide">
  <header class="page-head">
    <p><a href="/jobs.php">&larr; Jobs</a></p>
    <h1><?= App::e($job->title) ?></h1>
    <p>
      <span class="badge text-bg-light border"><?= App::e($sourceLabels[$job->source] ?? $job->source) ?></span>
      <strong><?= App::e($job->company) ?></strong>
      · <?= App::e($job->locationLine()) ?>
      <?php if ($job->postedAt): ?>
        · Posted <?= App::e($job->postedAt) ?>
      <?php endif; ?>
    </p>
    <div class="preview-links">
      <?php if ($job->url !== ''): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e($job->url) ?>" target="_blank" rel="noopener">Original posting</a>
      <?php endif; ?>
    </div>
  </header>

  <div class="row g-3">
    <div class="col-lg-8">
      <section class="card shadow-sm">
        <div class="card-body">
          <?php if ($job->description !== ''): ?>
            <div class="job-description">
              <?= JobText::displayHtml($job->description) ?>
            </div>
          <?php else: ?>
            <p class="text-secondary mb-0">No full description cached. Open the original posting, or apply anyway — we still copy Main resume and letter.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>
    <div class="col-lg-4">
      <section class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6">Apply with my resume</h2>
          <p class="small text-secondary">Copies Main resume and Main letter, sets this location, and logs Applications.</p>
          <dl class="small mb-3">
            <?php if ($job->workMode !== 'unknown'): ?>
              <dt>Mode</dt><dd><?= App::e($job->workMode) ?></dd>
            <?php endif; ?>
            <?php if ($job->employment !== 'unknown'): ?>
              <dt>Hours</dt><dd><?= App::e($job->employment) ?></dd>
            <?php endif; ?>
            <?php if ($job->offerType !== 'unknown'): ?>
              <dt>Type</dt><dd><?= App::e($job->offerType) ?></dd>
            <?php endif; ?>
            <?php if ($job->salaryText !== ''): ?>
              <dt>Salary</dt><dd><?= App::e($job->salaryText) ?></dd>
            <?php endif; ?>
          </dl>
          <form method="post">
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="source" value="<?= App::e($job->source) ?>">
            <input type="hidden" name="id" value="<?= App::e($job->externalId) ?>">
            <div class="mb-3">
              <label class="form-label" for="location">Job location</label>
              <input class="form-control" type="text" id="location" name="location" required value="<?= App::e($job->locationLine()) ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100">Apply with my resume</button>
          </form>
        </div>
      </section>
    </div>
  </div>
</main>
<?php
layout_footer();
