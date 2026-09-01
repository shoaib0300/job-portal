<?php

declare(strict_types=1);

/** @var list<array{
 *   id:int,
 *   job_source:string,
 *   job_external_id:string,
 *   title:string,
 *   company:string,
 *   location:string,
 *   apply_url:string,
 *   created_at:string,
 *   listing:?\KaamFit\Jobs\JobListing
 * }> $items */
/** @var array<string, string> $sourceLabels */

require_once dirname(__DIR__) . '/jobs/_bookmark_button.php';
?>
<main class="page-wide saved-jobs-page">
  <header class="page-head">
    <h1>Saved jobs</h1>
    <p class="text-secondary mb-0">Jobs you bookmarked to apply later. Bookmarking does not create an application.</p>
  </header>

  <?php if ($items === []): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <p class="mb-2">No saved jobs yet.</p>
        <p class="text-secondary small mb-3">Use the book icon on a job card to save it for later.</p>
        <a class="btn btn-primary btn-sm" href="/jobs">Browse jobs</a>
      </div>
    </div>
  <?php else: ?>
    <ul class="saved-jobs-list list-unstyled mb-0">
      <?php foreach ($items as $item): ?>
        <?php
        $listing = $item['listing'];
        $source = (string) $item['job_source'];
        $externalId = (string) $item['job_external_id'];
        $title = $listing !== null && $listing->title !== '' ? $listing->title : (string) $item['title'];
        $company = $listing !== null && $listing->company !== '' ? $listing->company : (string) $item['company'];
        $location = $listing !== null ? $listing->locationLine() : (string) $item['location'];
        $applyUrl = $listing !== null ? $listing->applyHref() : (string) $item['apply_url'];
        if ($applyUrl === '' && (string) $item['apply_url'] !== '') {
            $applyUrl = (string) $item['apply_url'];
        }
        $detail = '/job?source=' . rawurlencode($source) . '&id=' . rawurlencode($externalId);
        $savedAt = (string) $item['created_at'];
        ?>
      <li class="saved-jobs-item">
        <article class="card shadow-sm">
          <div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-3 position-relative">
            <div class="saved-jobs-item-main flex-grow-1 pe-md-4">
              <div class="d-flex flex-wrap gap-1 mb-2">
                <span class="badge text-bg-light border"><?= App::e($sourceLabels[$source] ?? $source) ?></span>
                <?php if ($listing === null): ?>
                  <span class="badge text-bg-warning">Listing expired</span>
                <?php endif; ?>
              </div>
              <h2 class="h6 mb-1">
                <a class="text-decoration-none" href="<?= App::e($detail) ?>"><?= App::e($title !== '' ? $title : 'Untitled role') ?></a>
              </h2>
              <p class="small mb-1"><strong><?= App::e($company !== '' ? $company : 'Company') ?></strong></p>
              <?php if ($location !== ''): ?>
                <p class="small text-secondary mb-1"><?= App::e($location) ?></p>
              <?php endif; ?>
              <?php if ($savedAt !== ''): ?>
                <p class="small text-secondary mb-0">Saved <?= App::e(substr($savedAt, 0, 10)) ?></p>
              <?php endif; ?>
            </div>
            <div class="saved-jobs-item-actions d-flex flex-wrap gap-2 align-items-center">
              <?php if ($applyUrl !== ''): ?>
                <a class="btn btn-sm btn-primary" href="<?= App::e($applyUrl) ?>" target="_blank" rel="noopener">Apply</a>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= App::e($detail) ?>">Open</a>
            </div>
            <div class="saved-jobs-item-bookmark">
              <?php render_job_bookmark_button($source, $externalId, true, [
                  'title' => $title,
                  'company' => $company,
                  'location' => $location,
                  'apply_url' => $applyUrl,
              ]); ?>
            </div>
          </div>
        </article>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
