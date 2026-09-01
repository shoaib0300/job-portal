<?php

declare(strict_types=1);

/**
 * Bookmark button for job cards and job detail.
 *
 * @param string $source
 * @param string $externalId
 * @param bool $saved
 * @param array{title?:string,company?:string,location?:string,apply_url?:string} $snapshot
 */
function render_job_bookmark_button(
    string $source,
    string $externalId,
    bool $saved,
    array $snapshot = []
): void {
    $title = (string) ($snapshot['title'] ?? '');
    $company = (string) ($snapshot['company'] ?? '');
    $location = (string) ($snapshot['location'] ?? '');
    $applyUrl = (string) ($snapshot['apply_url'] ?? '');
    $label = $saved ? 'Remove saved job' : 'Save job';
    $outlineSrc = '/assets/img/bookmark-outline.png';
    $filledSrc = '/assets/img/bookmark-filled.png';
    $src = $saved ? $filledSrc : $outlineSrc;
    ?>
<button type="button"
        class="jobs-bookmark-btn<?= $saved ? ' is-saved' : '' ?>"
        data-job-bookmark
        data-outline-src="<?= App::e($outlineSrc) ?>"
        data-filled-src="<?= App::e($filledSrc) ?>"
        data-source="<?= App::e($source) ?>"
        data-external-id="<?= App::e($externalId) ?>"
        data-title="<?= App::e($title) ?>"
        data-company="<?= App::e($company) ?>"
        data-location="<?= App::e($location) ?>"
        data-apply-url="<?= App::e($applyUrl) ?>"
        aria-pressed="<?= $saved ? 'true' : 'false' ?>"
        aria-label="<?= App::e($label) ?>"
        title="<?= App::e($label) ?>">
  <img class="jobs-bookmark-img" src="<?= App::e($src) ?>" width="22" height="22" alt="" decoding="async">
</button>
    <?php
}
