<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Jobs\JobAggregator;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobText;

JobAggregator::ensureSchema();
App::ensureDashboardSchema();

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

$company = $job->company !== '' ? $job->company : 'Company';
$role = $job->title !== '' ? $job->title : 'Role';
$existingApp = App::applicationForJob($source, $externalId, $company, $role, $applyHref);

$postAction = (string) ($_POST['action'] ?? '');
$backToJob = '/job?source=' . rawurlencode($source) . '&id=' . rawurlencode($externalId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'confirm_applied') {
    try {
        $resumeId = $existingApp !== null ? ($existingApp['resume_version_id'] ?? null) : null;
        $coverId = $existingApp !== null ? ($existingApp['cover_letter_id'] ?? null) : null;
        $appId = App::logJdApplication(
            $company,
            $role,
            $jdPlain,
            'applied',
            'Applied on employer website from Jobs · ' . (JobQuery::SOURCES[$job->source] ?? $job->source),
            $applyHref,
            date('Y-m-d'),
            $locationDefault,
            $resumeId,
            $coverId,
            $source,
            $externalId
        );
        App::flash('Marked as applied (#' . $appId . ').');
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect($backToJob);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'discard') {
    try {
        if ($existingApp === null || ($existingApp['status'] ?? '') !== 'preparing') {
            throw new InvalidArgumentException('Nothing to discard.');
        }
        App::discardPreparingApplication((int) $existingApp['id']);
        App::flash('Discarded preparing application and tailored documents.');
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect($backToJob);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($postAction === 'prepare' || $postAction === 'apply')) {
    try {
        $result = App::tailorFromJd(
            $company,
            $role,
            $locationDefault,
            $jdPlain,
            $applyHref,
            'preparing',
            null,
            null,
            null,
            null,
            'From Jobs · ' . (JobQuery::SOURCES[$job->source] ?? $job->source),
            $source,
            $externalId
        );
        $ready = $applyHref !== ''
            ? ' Apply on the employer site when you are done editing.'
            : '';
        App::flash(
            'Resume #' . $result['resume_id']
            . ' and cover #' . $result['cover_id']
            . ' ready · ' . $result['location']
            . ' · ' . App::statusLabel($result['status']) . '.'
            . $ready
        );
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect($backToJob);
}

$existingApp = App::applicationForJob($source, $externalId, $company, $role, $applyHref);
$sourceLabels = JobQuery::SOURCES;

layout_header($job->title !== '' ? $job->title : 'Job');
?>
<main class="page-wide">
  <header class="page-head">
    <p><a href="<?= App::e(JobQuery::jobsHref()) ?>">&larr; Jobs</a></p>
    <h1><?= App::e($job->title) ?></h1>
    <p>
      <span class="badge text-bg-light border"><?= App::e($sourceLabels[$job->source] ?? $job->source) ?></span>
      <?php if ($job->workMode !== 'unknown'): ?>
        <span class="badge text-bg-light border"><?= App::e($job->workMode) ?></span>
      <?php endif; ?>
      <?php if ($job->employment !== 'unknown'): ?>
        <span class="badge text-bg-light border"><?= App::e($job->employment) ?></span>
      <?php endif; ?>
      <?php if ($existingApp !== null): ?>
        <span class="badge <?= App::e(App::applicationStatusBadgeClass($existingApp['status'])) ?>">
          <?= App::e($existingApp['status'] === 'applied' ? 'Applied already' : App::statusLabel($existingApp['status'])) ?>
        </span>
      <?php endif; ?>
      <strong><?= App::e($job->company) ?></strong>
      · <?= App::e($job->locationLine()) ?>
      <?php if ($job->postedAt): ?>
        · Posted <?= App::e(JobText::formatPosted($job->postedAt)) ?>
      <?php else: ?>
        · Posted date not listed
      <?php endif; ?>
    </p>
    <?php if ($existingApp !== null && ($existingApp['resume_version_id'] || $existingApp['cover_letter_id'])): ?>
      <div class="d-flex flex-wrap gap-2 mb-2">
        <?php if ($existingApp['resume_version_id']): ?>
          <a class="btn btn-sm btn-outline-primary" href="/resume-edit?version=<?= (int) $existingApp['resume_version_id'] ?>">Edit resume</a>
        <?php endif; ?>
        <?php if ($existingApp['cover_letter_id']): ?>
          <a class="btn btn-sm btn-outline-primary" href="/cover-edit?id=<?= (int) $existingApp['cover_letter_id'] ?>">Edit cover</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
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
          <p class="small text-secondary">Use the button bottom-right to prepare docs. Status stays <strong>Preparing</strong> until you confirm you applied.</p>
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
            <a class="btn btn-primary w-100 mb-2" href="<?= App::e($applyHref) ?>" target="_blank" rel="noopener">Apply on employer website</a>
          <?php endif; ?>
          <?php if ($existingApp === null || $existingApp['status'] === 'preparing'): ?>
            <form method="post">
              <input type="hidden" name="action" value="prepare">
              <input type="hidden" name="source" value="<?= App::e($job->source) ?>">
              <input type="hidden" name="id" value="<?= App::e($job->externalId) ?>">
              <button type="submit" class="btn btn-outline-primary w-100">
                <?= $existingApp !== null ? 'Refresh resume and letter' : 'Prepare resume and letter' ?>
              </button>
            </form>
            <?php if ($existingApp !== null): ?>
              <form method="post" class="mt-2" onsubmit="return confirm('Discard this preparing application and delete the tailored resume and cover letter?');">
                <input type="hidden" name="action" value="discard">
                <input type="hidden" name="source" value="<?= App::e($job->source) ?>">
                <input type="hidden" name="id" value="<?= App::e($job->externalId) ?>">
                <button type="submit" class="btn btn-outline-danger w-100">Discard application</button>
              </form>
            <?php endif; ?>
          <?php elseif ($existingApp['status'] === 'applied'): ?>
            <p class="small text-success mb-0">You marked this job as applied. Docs are linked in Applications.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</main>
<?php if ($existingApp === null || $existingApp['status'] === 'preparing'): ?>
  <div class="job-prepare-fab" aria-label="Prepare application documents">
    <form method="post">
      <input type="hidden" name="action" value="prepare">
      <input type="hidden" name="source" value="<?= App::e($job->source) ?>">
      <input type="hidden" name="id" value="<?= App::e($job->externalId) ?>">
      <button type="submit" class="btn btn-primary shadow">
        <?= $existingApp !== null ? 'Refresh resume &amp; letter' : 'Prepare resume &amp; letter' ?>
      </button>
    </form>
  </div>
<?php endif; ?>
<?php
layout_footer();
