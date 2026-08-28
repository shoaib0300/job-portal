<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Jobs\JobAggregator;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobText;

JobAggregator::ensureSchema();

$source = trim((string) ($_GET['source'] ?? $_POST['source'] ?? ''));
$externalId = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
if ($source === '' || $externalId === '') {
    App::flash('Pick a job from search first.', 'error');
    App::redirect('/jobs');
}

$job = JobAggregator::details($source, $externalId);
if ($job === null) {
    App::flash('That listing expired. Search again.', 'error');
    App::redirect('/jobs');
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
$backToJob = '/job?source=' . rawurlencode($source) . '&id=' . rawurlencode($externalId);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'confirm_applied') {
    try {
        $appId = App::logJdApplication(
            $job->company !== '' ? $job->company : 'Company',
            $job->title !== '' ? $job->title : 'Role',
            $jdPlain,
            'applied',
            'Applied on employer website from Jobs · ' . (JobQuery::SOURCES[$job->source] ?? $job->source),
            $applyHref,
            null,
            $locationDefault
        );
        App::flash('Logged as applied (#' . $appId . '). No new resume was created.');
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect($backToJob);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($postAction === 'prepare' || $postAction === 'apply')) {
    $location = $locationDefault;
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
        App::redirect('/resume-edit');
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/job?source=' . rawurlencode($source) . '&id=' . rawurlencode($externalId));
    }
}

$sourceLabels = JobQuery::SOURCES;

layout_header($job->title !== '' ? $job->title : 'Job');
?>
<main class="page-wide">
  <header class="page-head">
    <p><a href="<?= App::e(JobQuery::jobsHref()) ?>">&larr; Jobs</a></p>
    <h1><?= App::e($job->title) ?></h1>
    <p>
      <span class="badge text-bg-light border"><?= App::e($sourceLabels[$job->source] ?? $job->source) ?></span>
      <strong><?= App::e($job->company) ?></strong>
      · <?= App::e($job->locationLine()) ?>
      <?php if ($job->postedAt): ?>
        · Posted <?= App::e(JobText::formatPosted($job->postedAt)) ?>
      <?php else: ?>
        · Posted date not listed
      <?php endif; ?>
    </p>
    <div class="preview-links d-flex flex-wrap gap-2">
      <?php if ($job->source === 'arbeitsagentur' && $job->listingHref() !== ''): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e($job->listingHref()) ?>" target="_blank" rel="noopener">Original BA listing</a>
      <?php elseif ($job->listingUrlDiffers() || ($job->listingHref() !== '' && $applyHref === '')): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e($job->listingHref()) ?>" target="_blank" rel="noopener">Original posting</a>
      <?php endif; ?>
      <?php if ($applyHref !== ''): ?>
        <a class="btn btn-sm btn-primary" href="<?= App::e($applyHref) ?>" target="_blank" rel="noopener">Apply on employer website</a>
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
            <p class="text-secondary mb-0">No full description cached.
              <?php if ($job->listingHref() !== ''): ?>
                <a href="<?= App::e($job->listingHref()) ?>" target="_blank" rel="noopener">Open the original posting</a>.
              <?php else: ?>
                Open the original posting, or prepare your resume anyway.
              <?php endif; ?>
            </p>
          <?php endif; ?>
        </div>
      </section>
    </div>
    <div class="col-lg-4">
      <?php if ($job->source === 'arbeitsagentur'): ?>
        <section class="card shadow-sm mb-3">
          <div class="card-body">
            <h2 class="h6">Informationen zur Bewerbung</h2>
            <p class="small mb-2"><strong>Kontaktadresse</strong><br><?= App::e($job->company !== '' ? $job->company : 'Arbeitgeber') ?></p>
            <p class="small mb-3"><strong>Bewerben Sie sich</strong><br>
              <?= $job->applyUrl !== ''
                  ? 'Über Internetseite des Arbeitgebers (Stellenanzeige / Bewerbungsformular)'
                  : 'Über die Jobsuche der Bundesagentur für Arbeit' ?>
            </p>
            <div class="d-grid gap-2">
              <?php if ($job->listingHref() !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= App::e($job->listingHref()) ?>" target="_blank" rel="noopener">Original BA listing</a>
              <?php endif; ?>
              <?php if ($applyHref !== ''): ?>
                <a class="btn btn-primary" href="<?= App::e($applyHref) ?>" target="_blank" rel="noopener">Apply on employer website</a>
                <p class="small text-secondary mb-0 text-break">Company job link:<br><a href="<?= App::e($applyHref) ?>" target="_blank" rel="noopener"><?= App::e($applyHref) ?></a></p>
              <?php else: ?>
                <p class="small text-secondary mb-0">No company job link in the BA feed. Use <strong>Original BA listing</strong> for Bewerbung / captcha contacts.</p>
              <?php endif; ?>
            </div>
            <p class="small text-secondary mt-3 mb-0">For name, phone, and email: use <strong>Original BA listing</strong> and complete the Sicherheitsabfrage there.</p>
          </div>
        </section>
      <?php endif; ?>
      <section class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6">Apply</h2>
          <?php if ($job->source === 'arbeitsagentur'): ?>
            <p class="small text-secondary">Use the buttons above for BA contacts or the employer site. Prepare your docs here first if you want.</p>
          <?php else: ?>
            <p class="small text-secondary">Prepare your docs here, then apply on the employer site.</p>
          <?php endif; ?>
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
          <?php if ($job->source !== 'arbeitsagentur' && $applyHref !== ''): ?>
            <a class="btn btn-primary w-100 mb-2" href="<?= App::e($applyHref) ?>" target="_blank" rel="noopener">Apply on employer website</a>
            <p class="small text-secondary mb-3">Opens the employer form only. Nothing is logged until you confirm below.</p>
          <?php endif; ?>
          <form method="post" class="mb-3 p-3 border rounded bg-body-secondary">
            <input type="hidden" name="action" value="confirm_applied">
            <input type="hidden" name="source" value="<?= App::e($job->source) ?>">
            <input type="hidden" name="id" value="<?= App::e($job->externalId) ?>">
            <p class="small mb-2"><strong>Have you applied on the employer website?</strong></p>
            <p class="small text-secondary mb-2">Adds this role to Applications as applied. Does not copy or create a resume.</p>
            <button type="submit" class="btn btn-outline-primary w-100">Yes, I applied</button>
          </form>
          <form method="post">
            <input type="hidden" name="action" value="prepare">
            <input type="hidden" name="source" value="<?= App::e($job->source) ?>">
            <input type="hidden" name="id" value="<?= App::e($job->externalId) ?>">
            <div class="mb-3">
              <label class="form-label" for="location">Job location</label>
              <input type="hidden" name="location" value="<?= App::e($locationDefault) ?>">
              <p class="form-control-plaintext border rounded px-3 py-2 mb-0 bg-body-secondary" id="location"><?= App::e($locationDefault) ?></p>
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
