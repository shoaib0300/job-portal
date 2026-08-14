<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

JobAggregator::ensureSchema();

$query = JobQuery::fromRequest($_GET);
$ran = isset($_GET['search']) || $query->q !== '' || $query->city !== '' || $query->bundesland !== '';
$result = [
    'listings' => [],
    'total' => 0,
    'notices' => [],
    'page' => 1,
    'pages' => 1,
];
if ($ran) {
    $result = JobAggregator::search($query);
}

$sourceLabels = JobQuery::SOURCES;

layout_header('Jobs');
?>
<main class="jobs-page">
  <header class="page-head">
    <h1>Jobs</h1>
    <p>Search German boards in one place. Apply copies your Main resume and letter.</p>
  </header>

  <form method="get" class="jobs-layout">
    <input type="hidden" name="search" value="1">
    <aside class="jobs-filters card shadow-sm">
      <div class="card-body">
        <h2 class="h6">Search</h2>
        <div class="mb-3">
          <label class="form-label" for="q">Role or keywords</label>
          <input class="form-control" type="search" id="q" name="q" value="<?= App::e($query->q) ?>" placeholder="e.g. Werkstudent QA">
        </div>
        <div class="mb-3">
          <label class="form-label" for="city">City</label>
          <input class="form-control" type="text" id="city" name="city" value="<?= App::e($query->city) ?>" placeholder="München">
        </div>
        <div class="mb-3">
          <label class="form-label" for="bundesland">Bundesland</label>
          <select class="form-select" id="bundesland" name="bundesland">
            <option value="">Any</option>
            <?php foreach (JobQuery::BUNDESLAENDER as $bl): ?>
              <option value="<?= App::e($bl) ?>"<?= $query->bundesland === $bl ? ' selected' : '' ?>><?= App::e($bl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="umkreis">Radius (km)</label>
          <input class="form-control" type="number" id="umkreis" name="umkreis" min="0" max="200" value="<?= (int) $query->radiusKm ?>">
        </div>

        <h2 class="h6">Work mode</h2>
        <div class="mb-3">
          <select class="form-select" name="work_mode">
            <option value="">Any</option>
            <option value="remote"<?= $query->workMode === 'remote' ? ' selected' : '' ?>>Remote</option>
            <option value="hybrid"<?= $query->workMode === 'hybrid' ? ' selected' : '' ?>>Hybrid</option>
            <option value="onsite"<?= $query->workMode === 'onsite' ? ' selected' : '' ?>>On-site</option>
          </select>
        </div>

        <h2 class="h6">Hours</h2>
        <div class="mb-3">
          <select class="form-select" name="employment">
            <option value="">Any</option>
            <option value="fulltime"<?= $query->employment === 'fulltime' ? ' selected' : '' ?>>Vollzeit</option>
            <option value="parttime"<?= $query->employment === 'parttime' ? ' selected' : '' ?>>Teilzeit</option>
          </select>
        </div>

        <h2 class="h6">Posted</h2>
        <div class="mb-3">
          <select class="form-select" name="posted">
            <option value="">Any time</option>
            <option value="1"<?= $query->postedDays === 1 ? ' selected' : '' ?>>Today</option>
            <option value="3"<?= $query->postedDays === 3 ? ' selected' : '' ?>>Last 3 days</option>
            <option value="7"<?= $query->postedDays === 7 ? ' selected' : '' ?>>Last 7 days</option>
          </select>
        </div>

        <h2 class="h6">Level</h2>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="student" value="1" id="f-student"<?= $query->student ? ' checked' : '' ?>>
          <label class="form-check-label" for="f-student">Student / Werkstudent</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="junior" value="1" id="f-junior"<?= $query->junior ? ' checked' : '' ?>>
          <label class="form-check-label" for="f-junior">Junior</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="graduate" value="1" id="f-graduate"<?= $query->graduate ? ' checked' : '' ?>>
          <label class="form-check-label" for="f-graduate">Absolvent</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="internship" value="1" id="f-intern"<?= $query->internship ? ' checked' : '' ?>>
          <label class="form-check-label" for="f-intern">Internship / Praktikum</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="no_experience" value="1" id="f-noexp"<?= $query->noExperience ? ' checked' : '' ?>>
          <label class="form-check-label" for="f-noexp">No experience required</label>
        </div>

        <h2 class="h6">Language</h2>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="english" value="1" id="f-en"<?= $query->english ? ' checked' : '' ?>>
          <label class="form-check-label" for="f-en">English-speaking</label>
        </div>
        <div class="mb-3 mt-2">
          <label class="form-label" for="german_level">German level</label>
          <select class="form-select" id="german_level" name="german_level">
            <option value="">Any</option>
            <?php foreach (['A1', 'A2', 'B1', 'B2', 'C1'] as $lvl): ?>
              <option value="<?= $lvl ?>"<?= $query->germanLevel === $lvl ? ' selected' : '' ?>><?= $lvl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="has_salary" value="1" id="f-sal"<?= $query->hasSalary ? ' checked' : '' ?>>
          <label class="form-check-label" for="f-sal">Mentions salary</label>
        </div>

        <h2 class="h6">Sources</h2>
        <?php foreach ($sourceLabels as $sid => $slabel): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="sources[]" value="<?= App::e($sid) ?>" id="src-<?= App::e($sid) ?>"<?= $query->wantsSource($sid) ? ' checked' : '' ?>>
            <label class="form-check-label" for="src-<?= App::e($sid) ?>"><?= App::e($slabel) ?></label>
          </div>
        <?php endforeach; ?>
        <?php if (!SerpBoardSource::configured()): ?>
          <p class="small text-secondary mt-2 mb-0">LinkedIn, Indeed, StepStone, XING, and Jobware use Google site search when <code>BRIGHT_DATA_API_TOKEN</code> is set.</p>
        <?php endif; ?>

        <div class="d-grid gap-2 mt-3">
          <button type="submit" class="btn btn-primary">Search</button>
          <a class="btn btn-outline-secondary" href="/jobs.php">Reset</a>
        </div>
      </div>
    </aside>

    <div class="jobs-results">
      <?php if (!$ran): ?>
        <div class="card shadow-sm">
          <div class="card-body">
            <p class="mb-2">Pick filters and search. Arbeitsagentur is on by default — it is the largest German job database.</p>
            <p class="text-secondary small mb-0">Student preset: Werkstudent + Praktikum in Berlin.</p>
            <a class="btn btn-sm btn-outline-primary mt-3" href="/jobs.php?search=1&amp;q=Werkstudent&amp;city=Berlin&amp;student=1&amp;internship=1&amp;sources%5B%5D=arbeitsagentur&amp;sources%5B%5D=university">Student jobs in Berlin</a>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($result['notices'] as $notice): ?>
          <div class="alert alert-warning"><?= App::e((string) $notice) ?></div>
        <?php endforeach; ?>
        <p class="text-secondary small"><?= (int) $result['total'] ?> jobs · page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></p>
        <?php if ($result['listings'] === []): ?>
          <div class="card shadow-sm">
            <div class="card-body">
              <p class="mb-0">No jobs matched. Broaden the city or turn off extra sources.</p>
            </div>
          </div>
        <?php else: ?>
          <ul class="list-unstyled jobs-list">
            <?php foreach ($result['listings'] as $job): ?>
              <?php
              /** @var JobListing $job */
              $detail = '/job.php?source=' . rawurlencode($job->source) . '&id=' . rawurlencode($job->externalId);
              ?>
              <li class="card shadow-sm mb-3">
                <div class="card-body">
                  <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                      <span class="badge text-bg-light border"><?= App::e($sourceLabels[$job->source] ?? $job->source) ?></span>
                      <?php if ($job->workMode !== 'unknown'): ?>
                        <span class="badge text-bg-light border"><?= App::e($job->workMode) ?></span>
                      <?php endif; ?>
                      <h2 class="h5 mt-2 mb-1"><a class="text-decoration-none" href="<?= App::e($detail) ?>"><?= App::e($job->title) ?></a></h2>
                      <p class="mb-1"><strong><?= App::e($job->company) ?></strong>
                        · <?= App::e($job->locationLine()) ?></p>
                      <?php if ($job->postedAt): ?>
                        <p class="small text-secondary mb-0">Posted <?= App::e($job->postedAt) ?></p>
                      <?php endif; ?>
                    </div>
                    <div class="d-flex flex-column gap-2 align-items-stretch">
                      <a class="btn btn-sm btn-primary" href="<?= App::e($detail) ?>">Open</a>
                      <?php if ($job->url !== ''): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e($job->url) ?>" target="_blank" rel="noopener">Original</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if ($result['pages'] > 1): ?>
            <nav class="d-flex gap-2">
              <?php if ($result['page'] > 1): ?>
                <a class="btn btn-outline-secondary" href="/jobs.php?<?= App::e($query->toQuery(['page' => $result['page'] - 1])) ?>">Previous</a>
              <?php endif; ?>
              <?php if ($result['page'] < $result['pages']): ?>
                <a class="btn btn-outline-secondary" href="/jobs.php?<?= App::e($query->toQuery(['page' => $result['page'] + 1])) ?>">Next</a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </form>
</main>
<?php
layout_footer();
