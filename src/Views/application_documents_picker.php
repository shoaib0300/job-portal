<?php

declare(strict_types=1);

/**
 * Compact select → reorder → Generate PDF picker.
 *
 * Expected vars:
 * @var string $lang
 * @var list<array<string,mixed>> $library
 * @var int|null $resumeId
 * @var string $resumeLabel
 * @var int|null $coverId
 * @var string $coverLabel
 * @var int $applicationId  0 = library mode
 * @var string $company
 * @var list<int> $precheckedDocIds
 * @var bool $defaultResumeChecked
 * @var bool $defaultCoverChecked
 */

use KaamFit\UserDocuments;

$lang = $lang ?? 'en';
$library = $library ?? [];
$resumeId = $resumeId ?? null;
$coverId = $coverId ?? null;
$resumeLabel = $resumeLabel ?? 'Resume';
$coverLabel = $coverLabel ?? 'Cover Letter';
$applicationId = (int) ($applicationId ?? 0);
$company = (string) ($company ?? '');
$precheckedDocIds = $precheckedDocIds ?? [];
$defaultResumeChecked = (bool) ($defaultResumeChecked ?? true);
$defaultCoverChecked = (bool) ($defaultCoverChecked ?? true);
$precheckedMap = array_fill_keys(array_map('intval', $precheckedDocIds), true);
$returnTo = $applicationId > 0
    ? '/applications?action=edit&id=' . $applicationId . '#package'
    : '/app-docs';
?>
<div class="app-docs-picker" data-app-docs-picker
     data-selected-label="<?= App::e(UserDocuments::ui('selected_n', $lang)) ?>"
     data-generating="<?= App::e(UserDocuments::ui('generating', $lang)) ?>">
  <div class="app-docs-picker-toolbar">
    <div class="app-docs-filters" role="tablist">
      <button type="button" class="app-docs-chip is-active" data-filter="all"><?= App::e(UserDocuments::ui('filter_all', $lang)) ?></button>
      <button type="button" class="app-docs-chip" data-filter="certificates"><?= App::e(UserDocuments::ui('filter_certs', $lang)) ?></button>
      <button type="button" class="app-docs-chip" data-filter="education"><?= App::e(UserDocuments::ui('filter_edu', $lang)) ?></button>
      <button type="button" class="app-docs-chip" data-filter="work"><?= App::e(UserDocuments::ui('filter_work', $lang)) ?></button>
      <button type="button" class="app-docs-chip" data-filter="other"><?= App::e(UserDocuments::ui('filter_other', $lang)) ?></button>
    </div>
    <input type="search" class="form-control form-control-sm app-docs-search" placeholder="Search documents…" data-docs-search autocomplete="off">
  </div>

  <ul class="app-docs-list list-unstyled mb-0">
    <?php if ($resumeId): ?>
      <li class="app-docs-row" data-group="core" data-key="resume" data-name="<?= App::e(strtolower($resumeLabel)) ?>">
        <label class="app-docs-row-main">
          <input type="checkbox" class="form-check-input" data-item-key="resume"<?= $defaultResumeChecked ? ' checked' : '' ?>>
          <span class="app-docs-icon" aria-hidden="true">📄</span>
          <span class="app-docs-text">
            <span class="app-docs-title"><?= App::e(UserDocuments::slotLabel('resume', $lang)) ?></span>
            <span class="app-docs-meta"><?= App::e($resumeLabel) ?></span>
          </span>
        </label>
      </li>
    <?php endif; ?>

    <?php if ($coverId): ?>
      <li class="app-docs-row" data-group="core" data-key="cover" data-name="<?= App::e(strtolower($coverLabel)) ?>">
        <label class="app-docs-row-main">
          <input type="checkbox" class="form-check-input" data-item-key="cover"<?= $defaultCoverChecked ? ' checked' : '' ?>>
          <span class="app-docs-icon" aria-hidden="true">📄</span>
          <span class="app-docs-text">
            <span class="app-docs-title"><?= App::e(UserDocuments::slotLabel('cover', $lang)) ?></span>
            <span class="app-docs-meta"><?= App::e($coverLabel) ?></span>
          </span>
        </label>
      </li>
    <?php endif; ?>

    <?php if ($library === []): ?>
      <li class="app-docs-empty-inline" data-group="empty">
        <p class="mb-1 fw-semibold"><?= App::e(UserDocuments::ui('empty', $lang)) ?></p>
        <p class="small text-secondary mb-2"><?= App::e(UserDocuments::ui('empty_hint', $lang)) ?></p>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#appDocUploadModal"><?= App::e(UserDocuments::ui('add', $lang)) ?></button>
      </li>
    <?php else: ?>
      <?php foreach ($library as $doc): ?>
        <?php
          $did = (int) $doc['id'];
          $dtype = (string) ($doc['doc_type'] ?? 'other');
          $group = UserDocuments::filterGroup($dtype);
          $isPdf = str_contains((string) ($doc['mime_type'] ?? ''), 'pdf') || str_ends_with(strtolower((string) ($doc['storage_path'] ?? '')), '.pdf');
          $checked = isset($precheckedMap[$did]);
        ?>
        <li class="app-docs-row"
            data-group="<?= App::e($group) ?>"
            data-key="doc:<?= $did ?>"
            data-name="<?= App::e(strtolower((string) $doc['name'])) ?>"
            data-doc-id="<?= $did ?>">
          <label class="app-docs-row-main">
            <input type="checkbox" class="form-check-input" data-item-key="doc:<?= $did ?>"<?= $checked ? ' checked' : '' ?>>
            <span class="app-docs-icon" aria-hidden="true">📄</span>
            <span class="app-docs-text">
              <span class="app-docs-title"><?= App::e((string) $doc['name']) ?></span>
              <span class="app-docs-meta">
                <span class="badge text-bg-light border"><?= App::e(UserDocuments::typeLabel($dtype, $lang)) ?></span>
                · v<?= (int) ($doc['version_no'] ?? 1) ?>
                · <?= $isPdf ? 'PDF' : 'File' ?>
              </span>
            </span>
          </label>
          <div class="app-docs-row-actions">
            <a href="<?= App::e(UserDocuments::downloadUrl($did, true)) ?>" target="_blank" rel="noopener"><?= App::e(UserDocuments::ui('preview', $lang)) ?></a>
            <a href="<?= App::e(UserDocuments::downloadUrl($did)) ?>"><?= App::e(UserDocuments::ui('download', $lang)) ?></a>
            <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#appDocReplaceModal"
                    data-doc-id="<?= $did ?>"
                    data-doc-name="<?= App::e((string) $doc['name']) ?>"
                    data-doc-type="<?= App::e($dtype) ?>"><?= App::e(UserDocuments::ui('replace', $lang)) ?></button>
            <form method="post" action="/app-docs" class="d-inline" onsubmit="return confirm('Delete this document?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="document_id" value="<?= $did ?>">
              <input type="hidden" name="return_to" value="<?= App::e($returnTo) ?>">
              <button type="submit" class="btn btn-link btn-sm p-0 text-danger"><?= App::e(UserDocuments::ui('delete', $lang)) ?></button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    <?php endif; ?>
  </ul>

  <form method="post" action="/app-package" class="app-docs-generate-form" data-generate-form>
    <input type="hidden" name="action" value="generate">
    <?php if ($applicationId > 0): ?>
      <input type="hidden" name="application_id" value="<?= $applicationId ?>">
    <?php endif; ?>
    <?php if ($resumeId): ?>
      <input type="hidden" name="resume_version_id" value="<?= (int) $resumeId ?>">
    <?php endif; ?>
    <?php if ($coverId): ?>
      <input type="hidden" name="cover_letter_id" value="<?= (int) $coverId ?>">
    <?php endif; ?>
    <?php if ($company !== ''): ?>
      <input type="hidden" name="company" value="<?= App::e($company) ?>">
    <?php endif; ?>
    <div class="app-docs-order-fields" data-order-fields hidden></div>

    <div class="app-docs-selected-panel" data-selected-panel>
      <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <strong class="small mb-0"><?= App::e(UserDocuments::ui('reorder_hint', $lang)) ?></strong>
        <span class="small text-secondary" data-selected-count>0</span>
      </div>
      <ul class="app-docs-order list-unstyled mb-3" data-order-list></ul>
      <button type="submit" class="btn btn-primary w-100 app-docs-generate-btn" data-generate-btn disabled>
        <?= App::e(UserDocuments::ui('generate', $lang)) ?>
      </button>
      <p class="small text-secondary text-center mt-2 mb-0" data-generate-status hidden></p>
    </div>
  </form>
</div>
