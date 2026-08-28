<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/layout.php';
require_once dirname(__DIR__, 2) . '/src/profile_meta.php';
require_once dirname(__DIR__, 2) . '/src/experience.php';

SuperAdmin::requireLogin();

$userId = (int) ($_GET['user'] ?? 0);
$doc = (string) ($_GET['doc'] ?? 'resume');
$user = SuperAdmin::getUser($userId);
if ($user === null) {
    http_response_code(404);
    echo 'User not found.';
    exit;
}

Versions::ensureSchema();

$theme = App::resolveTheme(App::userSetting($userId, 'theme', null));
$accent = App::resolveAccent(App::userSetting($userId, 'accent', null));
$font = App::resolveFont(App::userSetting($userId, 'font', null));
$userLabel = (string) ($user['name'] ?? $user['username'] ?? ('User #' . $userId));

if ($doc === 'cover') {
    $profile = App::profileForUser($userId);
    $letter = Versions::masterCoverForUser($userId);
    $pageTitle = $userLabel . ' — Master cover (view only)';
    layout_header($pageTitle, [
        'body_class' => 'page-doc theme-' . $theme . ' super-doc-readonly',
        'theme' => $theme,
        'accent' => $accent,
        'font' => $font,
        'hide_nav' => true,
        'hide_flash' => true,
    ]);
    ?>
<main class="doc-toolbar no-print super-doc-toolbar">
  <div class="doc-toolbar-inner d-flex flex-wrap justify-content-between align-items-center gap-2">
    <a class="btn btn-sm btn-link text-decoration-none" href="/super-admin/user.php?id=<?= $userId ?>">&larr; User #<?= $userId ?></a>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="badge text-bg-secondary">Read-only · Super admin</span>
      <?php if ($letter): ?>
        <span class="badge rounded-pill text-bg-light border"><?= App::e(Versions::MASTER_COVER_LABEL) ?></span>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="/super-admin/user-doc.php?user=<?= $userId ?>&doc=resume">Master CV</a>
      <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">Print</button>
    </div>
  </div>
</main>

<article class="cover-letter theme-<?= App::e($theme) ?>" data-doc="cover">
  <header class="letter-from">
    <strong><?= App::e($profile['full_name']) ?></strong>
    <?php if (App::filled($profile['title'] ?? null)): ?>
      <span><?= App::e($profile['title']) ?></span>
    <?php endif; ?>
    <?php render_profile_details($profile, true, true); ?>
  </header>

  <?php if ($letter): ?>
    <?php $companyLine = trim((string) ($letter['company'] ?? '')); ?>
    <?php if ($companyLine !== ''): ?>
      <p class="letter-company"><?= App::e($companyLine) ?></p>
    <?php endif; ?>
    <div class="letter-body"><?= App::nl2p($letter['body']) ?></div>
  <?php else: ?>
    <p class="empty">No master cover letter saved for this user.</p>
  <?php endif; ?>
</article>
    <?php
    layout_footer();
    exit;
}

$payload = Versions::resumePayloadForUser($userId);
$profile = $payload['profile'];
$sections = $payload['sections'];
$experiences = $payload['experiences'];
$version = $payload['version'];
$pageTitle = $userLabel . ' — Master CV (view only)';

layout_header($pageTitle, [
    'body_class' => 'page-doc theme-' . $theme . ' super-doc-readonly',
    'theme' => $theme,
    'accent' => $accent,
    'font' => $font,
    'hide_nav' => true,
    'hide_flash' => true,
]);
?>
<main class="doc-toolbar no-print super-doc-toolbar">
  <div class="doc-toolbar-inner d-flex flex-wrap justify-content-between align-items-center gap-2">
    <a class="btn btn-sm btn-link text-decoration-none" href="/super-admin/user.php?id=<?= $userId ?>">&larr; User #<?= $userId ?></a>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="badge text-bg-secondary">Read-only · Super admin</span>
      <?php if ($version): ?>
        <span class="badge rounded-pill text-bg-light border"><?= App::e(Versions::MASTER_CV_LABEL) ?></span>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="/super-admin/user-doc.php?user=<?= $userId ?>&doc=cover">Master cover</a>
      <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">Print</button>
    </div>
  </div>
</main>

<article class="resume theme-<?= App::e($theme) ?><?= App::shouldShowPhoto($profile) ? ' has-photo' : ' no-photo' ?>" data-doc="resume">
  <header class="resume-header">
    <?php if (App::shouldShowPhoto($profile)): ?>
      <div class="resume-photo">
        <img src="<?= App::e(App::photoUrl($profile)) ?>" alt="<?= App::e($profile['full_name']) ?>">
      </div>
    <?php endif; ?>
    <div class="resume-intro">
      <h1><?= App::e($profile['full_name']) ?></h1>
      <?php if (App::filled($profile['title'] ?? null)): ?>
        <p class="resume-title"><?= App::e($profile['title']) ?></p>
      <?php endif; ?>
      <?php render_profile_details($profile, true); ?>
    </div>
  </header>

  <div class="resume-sections">
  <?php foreach ($sections as $section): ?>
    <section class="resume-section" data-section="<?= App::e((string) ($section['section_key'] ?? '')) ?>">
      <h2><?= App::e($section['title']) ?></h2>
      <?php if (($section['section_key'] ?? '') === 'experience'): ?>
        <?php render_experience_entries($experiences); ?>
      <?php elseif (($section['section_key'] ?? '') === 'skills'): ?>
        <div class="resume-body"><?php render_skills_body((string) ($section['body'] ?? '')); ?></div>
      <?php elseif (($section['section_key'] ?? '') === 'education'): ?>
        <div class="resume-body"><?php render_education_body((string) ($section['body'] ?? '')); ?></div>
      <?php else: ?>
        <div class="resume-body"><?= App::nl2p($section['body']) ?></div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  </div>
</article>
<?php
layout_footer();
