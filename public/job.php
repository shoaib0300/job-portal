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

$applyHref = $job->applyHref();
$jdPlain = $job->description !== ''
    ? JobText::stripHtml($job->description)
    : ($job->title . ' at ' . $job->company);
$locationDefault = trim($job->locationLine());
if ($locationDefault === '') {
    $locationDefault = 'Germany';
}

$postAction = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'apply_external') {
    if ($applyHref === '') {
        App::flash('No employer application link for this job.', 'error');
        App::redirect('/job.php?source=' . rawurlencode($source) . '&id=' . rawurlencode($externalId));
    }
    try {
        $appId = App::logJdApplication(
            $job->company !== '' ? $job->company : 'Company',
            $job->title !== '' ? $job->title : 'Role',
            $jdPlain,
            'applied',
            'Opened employer application from Jobs · ' . (JobQuery::SOURCES[$job->source] ?? $job->source),
            $applyHref,
            null,
            $locationDefault
        );
        App::flash('Logged as applied (#' . $appId . '). Finish the form on the employer site.');
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/job.php?source=' . rawurlencode($source) . '&id=' . rawurlencode($externalId));
    }
    App::redirect($applyHref);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($postAction === 'prepare' || $postAction === 'apply')) {
    $location = trim((string) ($_POST['location'] ?? $locationDefault));
    if ($location === '') {
        $location = 'Germany';
    }
    try {
        $result = App::tailorFromJd(
            $job->company !== '' ? $job->company : 'Company',
            $job->title !== '' ? $job->title : 'Role',
            $location,
            $jdPlain,
            $applyHref,
            'applied',
            null,
            null,
            null,
            null,
            'From Jobs · ' . (JobQuery::SOURCES[$job->source] ?? $job->source)
        );
        $ready = $applyHref !== ''
            ? ' Docs ready. Apply on the employer site when you are done editing.'
            : '';
        App::flash(
            'Copied Main into resume #' . $result['resume_id']
            . ' and cover #' . $result['cover_id']
            . '. Application #' . $result['application_id']
            . ' · ' . $result['location']
            . ' · ' . App::statusLabel($result['status']) . '.'
            . $ready
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
      <?php if ($job->listingUrlDiffers()): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e($job->url) ?>" target="_blank" rel="noopener">Original posting</a>
      <?php elseif ($job->url !== '' && $applyHref === ''): ?>
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
            <p class="text-secondary mb-0">No full description cached. Open the original posting, or prepare your resume anyway.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>
    <div class="col-lg-4">
      <section class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6">Apply</h2>
          <p class="small text-secondary">We do not submit into LinkedIn, Indeed, or company ATS. Prepare your docs here, then apply on the employer site.</p>
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
          <?php if ($applyHref !== ''): ?>
            <form method="post" target="_blank" rel="noopener" class="mb-3">
              <input type="hidden" name="action" value="apply_external">
              <input type="hidden" name="source" value="<?= App::e($job->source) ?>">
              <input type="hidden" name="id" value="<?= App::e($job->externalId) ?>">
              <button type="submit" class="btn btn-primary w-100">Apply now</button>
            </form>
            <p class="small text-secondary">Opens the employer page and logs Applications as applied.</p>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="action" value="prepare">
            <input type="hidden" name="source" value="<?= App::e($job->source) ?>">
            <input type="hidden" name="id" value="<?= App::e($job->externalId) ?>">
            <div class="mb-3">
              <label class="form-label" for="location">Job location</label>
              <input class="form-control" type="text" id="location" name="location" required value="<?= App::e($locationDefault) ?>">
            </div>
            <button type="submit" class="btn btn-outline-primary w-100">Prepare resume and letter</button>
          </form>
        </div>
      </section>
    </div>
  </div>
</main>
<?php
layout_footer();
