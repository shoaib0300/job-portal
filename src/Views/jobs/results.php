<?php

declare(strict_types=1);

/** @var \KaamMilo\Jobs\JobQuery $query */
/** @var array{listings: list<\KaamMilo\Jobs\JobListing>, total: int, notices: list<string>, page: int, pages: int} $result */
/** @var bool $ran */
/** @var array<string, string> $sourceLabels */
/** @var string $resumeTitle */
/** @var array<string, int> $resumeTerms */

use App;
use KaamMilo\Jobs\JobText;
use KaamMilo\Jobs\ResumeJobMatch;
?>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-3" data-jobs-meta>
        <?php if ($ran): ?>
          <p class="text-secondary small mb-0 me-auto" data-jobs-count><?= (int) $result['total'] ?> jobs · page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></p>
        <?php else: ?>
          <p class="text-secondary small mb-0 me-auto" data-jobs-count>Set filters above, then search.</p>
        <?php endif; ?>
        <label class="small text-secondary mb-0" for="sort">Sort</label>
        <select class="form-select form-select-sm jobs-sort" id="sort" name="sort" data-jobs-sort>
          <option value="relevance"<?= $query->sort === 'relevance' ? ' selected' : '' ?>>Best match</option>
          <option value="recent"<?= $query->sort === 'recent' ? ' selected' : '' ?>>Most recent</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
      </div>

      <div data-jobs-body>
      <?php if (!$ran): ?>
        <div class="card shadow-sm">
          <div class="card-body">
            <p class="mb-2">Pick filters and search. Default sources are Bundesagentur für Arbeit and Jobexport — your last search is restored until you Reset.</p>
            <p class="text-secondary small mb-0">Student preset: Werkstudent + Praktikum in Berlin.</p>
            <a class="btn btn-sm btn-outline-primary mt-3" href="/jobs?search=1&amp;posted=14&amp;q%5B%5D=Werkstudent&amp;city=Berlin&amp;student=1&amp;internship=1&amp;sources%5B%5D=arbeitsagentur&amp;sources%5B%5D=jobexport&amp;sources%5B%5D=university">Student jobs in Berlin</a>
          </div>
        </div>
      <?php else: ?>
        <?php if ($query->matchResume && $query->sort !== 'recent'): ?>
          <p class="small mb-2">Sorted by Master CV fit<?= $resumeTitle !== '' ? ' · ' . App::e($resumeTitle) : '' ?>.</p>
        <?php elseif ($query->sort === 'recent'): ?>
          <p class="small mb-2">Sorted by most recent post.</p>
        <?php endif; ?>
        <?php if ($query->keywords !== []): ?>
          <p class="small mb-3">Roles:
            <?php foreach ($query->keywords as $kw): ?>
              <span class="badge text-bg-light border"><?= App::e($kw) ?></span>
            <?php endforeach; ?>
          </p>
        <?php endif; ?>
        <?php foreach ($result['notices'] as $notice): ?>
          <div class="alert alert-warning"><?= App::e((string) $notice) ?></div>
        <?php endforeach; ?>

        <?php if ($result['listings'] === []): ?>
          <div class="card shadow-sm">
            <div class="card-body">
              <p class="mb-2">No jobs matched.</p>
              <p class="text-secondary small mb-0">Some filters or sources may show few or no jobs while we expand coverage. Try different filters, another source, or a job portal.</p>
            </div>
          </div>
        <?php else: ?>
          <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 jobs-card-grid">
            <?php foreach ($result['listings'] as $job): ?>
              <?php
              $detail = '/job?source=' . rawurlencode($job->source) . '&id=' . rawurlencode($job->externalId);
              $fitScore = $query->matchResume ? ResumeJobMatch::fitScore($job, $resumeTerms) : 0;
              $fitLabel = $query->matchResume ? ResumeJobMatch::fitLabel($fitScore) : '';
              ?>
              <div class="col">
                <article class="card shadow-sm h-100 jobs-job-card">
                  <div class="card-body d-flex flex-column">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                      <span class="badge text-bg-light border"><?= App::e($sourceLabels[$job->source] ?? $job->source) ?></span>
                      <?php if ($job->workMode !== 'unknown'): ?>
                        <span class="badge text-bg-light border"><?= App::e($job->workMode) ?></span>
                      <?php endif; ?>
                      <?php if ($fitLabel !== ''): ?>
                        <span class="badge <?= $fitScore >= 12 ? 'text-bg-success' : 'text-bg-light border' ?>"><?= App::e($fitLabel) ?></span>
                      <?php endif; ?>
                    </div>
                    <h2 class="h6 card-title mb-2">
                      <a class="stretched-link text-decoration-none text-reset" href="<?= App::e($detail) ?>"><?= App::e($job->title) ?></a>
                    </h2>
                    <p class="small mb-1"><strong><?= App::e($job->company) ?></strong></p>
                    <p class="small text-secondary mb-2"><?= App::e($job->locationLine()) ?></p>
                    <?php
                    $postedLabel = JobText::formatPosted($job->postedAt);
                    ?>
                    <p class="small text-secondary mb-3"><?= $postedLabel !== ''
                        ? ('Posted ' . App::e($postedLabel))
                        : 'Posted date not listed' ?></p>
                    <div class="mt-auto d-flex flex-wrap gap-2 position-relative" style="z-index:1">
                      <?php if ($job->applyHref() !== ''): ?>
                        <a class="btn btn-sm btn-primary" href="<?= App::e($job->applyHref()) ?>" target="_blank" rel="noopener">Apply</a>
                      <?php endif; ?>
                      <a class="btn btn-sm <?= $job->applyHref() !== '' ? 'btn-outline-primary' : 'btn-primary' ?>" href="<?= App::e($detail) ?>">Open</a>
                      <?php if ($job->listingUrlDiffers()): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= App::e($job->listingHref()) ?>" target="_blank" rel="noopener">Original</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ($result['pages'] > 1): ?>
            <nav class="d-flex flex-wrap align-items-center gap-2 mt-4" data-jobs-pagination aria-label="Jobs pages">
              <?php if ($result['page'] > 1): ?>
                <a class="btn btn-outline-secondary" data-jobs-page="<?= (int) ($result['page'] - 1) ?>" href="/jobs?<?= App::e($query->toQuery(['page' => $result['page'] - 1])) ?>">Previous</a>
              <?php endif; ?>
              <span class="small text-secondary px-1">Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
              <?php
              $pages = (int) $result['pages'];
              $cur = (int) $result['page'];
              $from = max(1, $cur - 2);
              $to = min($pages, $cur + 2);
              for ($p = $from; $p <= $to; $p++):
              ?>
                <a class="btn btn-sm <?= $p === $cur ? 'btn-primary' : 'btn-outline-secondary' ?>" data-jobs-page="<?= $p ?>" href="/jobs?<?= App::e($query->toQuery(['page' => $p])) ?>"<?= $p === $cur ? ' aria-current="page"' : '' ?>><?= $p ?></a>
              <?php endfor; ?>
              <?php if ($result['page'] < $result['pages']): ?>
                <a class="btn btn-outline-secondary" data-jobs-page="<?= (int) ($result['page'] + 1) ?>" href="/jobs?<?= App::e($query->toQuery(['page' => $result['page'] + 1])) ?>">Next</a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
      </div>
