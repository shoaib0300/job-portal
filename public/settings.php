<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

App::ensureDashboardSchema();
Versions::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = (string) ($_POST['form'] ?? 'appearance');
    if ($form === 'account') {
        try {
            Auth::updateAccount(
                Auth::id(),
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['username'] ?? ''),
                (string) ($_POST['email'] ?? '')
            );
            App::setSetting('document_lang', App::resolveDocumentLang((string) ($_POST['document_lang'] ?? '')));
            App::setSetting('translate_target_lang', App::resolveTranslateTargetLang((string) ($_POST['translate_target_lang'] ?? '')));
            App::flash('Account saved.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/settings');
    }
    if ($form === 'password') {
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        try {
            if ($new !== $confirm) {
                throw new InvalidArgumentException('New password and confirmation do not match.');
            }
            Auth::changePassword(
                Auth::id(),
                (string) ($_POST['current_password'] ?? ''),
                $new
            );
            App::flash('Password updated.');
        } catch (Throwable $e) {
            App::flash($e->getMessage(), 'error');
        }
        App::redirect('/settings');
    }
    $density = App::resolveDensity((string) ($_POST['ui_density'] ?? ''));
    $sidebar = App::resolveSidebar((string) ($_POST['sidebar_mode'] ?? ''));
    $palette = App::resolveDashboardPalette((string) ($_POST['dashboard_palette'] ?? ''));
    App::setSetting('ui_density', $density);
    App::setSetting('sidebar_mode', $sidebar);
    App::setSetting('dashboard_palette', $palette);
    App::setSetting('ui_mode', $palette === 'dark' ? 'warm-dark' : 'warm');
    App::flash('Look saved.');
    App::redirect('/settings');
}

$density = App::resolveDensity();
$sidebar = App::resolveSidebar();
$palette = App::resolveDashboardPalette();
$palettes = App::dashboardPalettes();
$masterResume = Versions::baseResumeVersion();
$masterCover = Versions::baseCoverLetter();
$resumeEditHref = $masterResume ? '/resume-edit' : '/editor';
$coverEditHref = $masterCover ? '/cover-edit' : '/cover';
$account = Auth::user() ?? ['username' => '', 'email' => '', 'name' => ''];
$documentLang = App::resolveDocumentLang();
$translateTargetLang = App::resolveTranslateTargetLang();
$translateLanguageOptions = TranslateLanguages::optionsForJs();
$deeplLanguageCount = TranslateLanguages::count();
$usagePeriod = strtolower(trim((string) ($_GET['usage'] ?? 'month')));
if (!in_array($usagePeriod, ['month', 'last', 'year'], true)) {
    $usagePeriod = 'month';
}
$usagePeriodLabels = [
    'month' => 'This month',
    'last' => 'Last month',
    'year' => 'This year',
];
$myTranslationRaw = LibreTranslate::usageForUserThisMonth(Auth::id());
$myTranslation = LibreTranslate::usageForPeriod($myTranslationRaw, $usagePeriod);

layout_header('Account');
?>
<main class="settings-page">
  <header class="page-head">
    <h1>Account</h1>
    <p>Profile, your master documents, and how the dashboard looks.</p>
  </header>

  <div class="row g-4">
    <div class="col-lg-6">
      <section class="card shadow-sm settings-panel">
        <div class="card-body">
          <h2 class="settings-panel-title">Profile</h2>
          <form method="post">
            <input type="hidden" name="form" value="account">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="name">Name</label>
                <input class="form-control" type="text" id="name" name="name" required value="<?= App::e((string) $account['name']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="username">Username</label>
                <input class="form-control" type="text" id="username" name="username" required minlength="3" maxlength="80" pattern="[a-zA-Z0-9_]+" value="<?= App::e((string) $account['username']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" required value="<?= App::e((string) $account['email']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label" for="document_lang">Document language</label>
                <select class="form-select" id="document_lang" name="document_lang">
                  <option value="en"<?= $documentLang === 'en' ? ' selected' : '' ?>>English</option>
                  <option value="de"<?= $documentLang === 'de' ? ' selected' : '' ?>>German</option>
                </select>
                <p class="form-text small mb-0">Free PDF prints your document as written. Optional translation uses DeepL and is billed per character.</p>
              </div>
              <div class="col-12">
                <label class="form-label" for="translate_target_lang">Default translate-to language</label>
                <select class="form-select" id="translate_target_lang" name="translate_target_lang">
                  <?php foreach ($translateLanguageOptions as $opt): ?>
                    <option value="<?= App::e($opt['code']) ?>"<?= $translateTargetLang === $opt['code'] ? ' selected' : '' ?>><?= App::e($opt['label']) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="form-text small mb-0">Pre-selected when you click Translate PDF. Change anytime in the picker.</p>
              </div>
              <div class="col-12" id="deepl-languages">
                <details class="settings-deepl-langs">
                  <summary class="h6 mb-0" style="cursor:pointer">All DeepL languages (<?= (int) $deeplLanguageCount ?>)</summary>
                  <p class="small text-secondary mt-2 mb-2">PDF translation supports every target language listed on <a href="https://developers.deepl.com/docs/getting-started/supported-languages" target="_blank" rel="noopener">DeepL</a>, including Urdu, Hindi, Arabic, and more.</p>
                  <input type="search" class="form-control form-control-sm mb-2" id="deepl-lang-filter" placeholder="Filter languages…" autocomplete="off">
                  <ul class="settings-deepl-lang-list list-unstyled small mb-0" id="deepl-lang-list">
                    <?php foreach ($translateLanguageOptions as $opt): ?>
                      <li data-lang-label="<?= App::e(strtolower($opt['label'])) ?>" data-lang-code="<?= App::e($opt['code']) ?>"><?= App::e($opt['label']) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Save profile</button>
              </div>
            </div>
          </form>

          <details class="password-form">
            <summary class="h6 mb-0" style="cursor:pointer">Change password</summary>
            <form method="post" class="mt-3">
              <input type="hidden" name="form" value="password">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="current_password">Current password</label>
                  <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="new_password">New password</label>
                  <input class="form-control" type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="confirm_password">Confirm new password</label>
                  <input class="form-control" type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-outline-secondary">Update password</button>
                </div>
              </div>
            </form>
          </details>
        </div>
      </section>

      <section class="card shadow-sm settings-panel">
        <div class="card-body">
          <h2 class="settings-panel-title">Your documents</h2>
          <p class="small text-secondary mb-3">Master CV and Master cover letter stay separate. New job copies both for each application.</p>
          <div class="settings-doc-grid">
            <div class="settings-doc-tile">
              <div class="dash-card-ico"><?= kaammilo_icon('doc', 'sm') ?></div>
              <div>
                <h3>Master CV</h3>
                <?php if ($masterResume): ?>
                  <p>#<?= (int) $masterResume['id'] ?> · <?= App::e(Versions::resumeDisplayLabel($masterResume)) ?></p>
                <?php else: ?>
                  <p>Not created yet</p>
                <?php endif; ?>
                <div class="settings-doc-actions">
                  <a class="btn btn-sm btn-primary" href="<?= App::e($resumeEditHref) ?>">Edit</a>
                  <a class="btn btn-sm btn-outline-secondary" href="/design">Style</a>
                </div>
              </div>
            </div>
            <div class="settings-doc-tile">
              <div class="dash-card-ico"><?= kaammilo_icon('letter', 'sm') ?></div>
              <div>
                <h3>Master cover letter</h3>
                <?php if ($masterCover): ?>
                  <p>#<?= (int) $masterCover['id'] ?> · <?= App::e(Versions::coverDisplayLabel($masterCover)) ?></p>
                <?php else: ?>
                  <p>Not created yet</p>
                <?php endif; ?>
                <div class="settings-doc-actions">
                  <a class="btn btn-sm btn-primary" href="<?= App::e($coverEditHref) ?>">Edit</a>
                  <a class="btn btn-sm btn-outline-secondary" href="/cover-design">Style</a>
                </div>
              </div>
            </div>
          </div>
          <p class="settings-footnote mb-0"><a href="/companies">Manage company job boards</a> used in Jobs search.</p>
        </div>
      </section>

      <section class="card shadow-sm settings-panel">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
            <h2 class="settings-panel-title mb-0">Translation usage</h2>
            <form method="get">
              <label class="form-label small mb-1" for="usage">Period</label>
              <select class="form-select form-select-sm" id="usage" name="usage" onchange="this.form.submit()">
                <?php foreach ($usagePeriodLabels as $value => $label): ?>
                  <option value="<?= App::e($value) ?>"<?= $usagePeriod === $value ? ' selected' : '' ?>><?= App::e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
          <p class="settings-usage-line"><?= App::e($usagePeriodLabels[$usagePeriod]) ?>: <strong><?= App::e(LibreTranslate::formatEuro($myTranslation['billed_chars'])) ?></strong>
            · <?= App::e(number_format($myTranslation['billed_chars'])) ?> billed
            · <?= App::e(number_format($myTranslation['requests'])) ?> requests</p>
        </div>
      </section>
    </div>

    <div class="col-lg-6">
      <form method="post" class="card shadow-sm settings-panel settings-look-card" data-settings-look-form>
        <div class="card-body">
          <input type="hidden" name="form" value="appearance">
          <h2 class="settings-panel-title">Dashboard look</h2>
          <p class="small text-secondary mb-3">Preview updates as you pick options. PDF colors stay on Resume style and Cover style.</p>

          <div class="settings-theme-preview"
               data-settings-preview
               data-palette="<?= App::e($palette) ?>"
               data-density="<?= App::e($density) ?>"
               data-sidebar="<?= App::e($sidebar) ?>"
               aria-hidden="true">
            <div class="settings-preview-shell">
              <div class="settings-preview-sidebar">
                <div class="settings-preview-brand"></div>
                <div class="settings-preview-nav is-active"></div>
                <div class="settings-preview-nav"></div>
                <div class="settings-preview-nav"></div>
              </div>
              <div class="settings-preview-main">
                <div class="settings-preview-topbar"></div>
                <div class="settings-preview-card"></div>
                <div class="settings-preview-card short"></div>
              </div>
            </div>
          </div>

          <div class="settings-look-options">
            <fieldset>
              <legend class="form-label">Color palette</legend>
              <div class="settings-palette-row">
                <?php foreach ($palettes as $id => $meta): ?>
                  <label class="settings-palette-pill">
                    <input type="radio" name="dashboard_palette" value="<?= App::e($id) ?>"<?= $palette === $id ? ' checked' : '' ?>>
                    <span class="settings-palette-dot" style="background: <?= App::e($meta['tokens']['--km-accent'] ?? '#0d7377') ?>"></span>
                    <?= App::e($meta['label']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <div class="row g-3">
              <fieldset class="col-sm-6 mb-0">
                <legend class="form-label">Density</legend>
                <div class="choice-row">
                  <label><input class="form-check-input" type="radio" name="ui_density" value="comfortable"<?= $density === 'comfortable' ? ' checked' : '' ?>> Comfortable</label>
                  <label><input class="form-check-input" type="radio" name="ui_density" value="compact"<?= $density === 'compact' ? ' checked' : '' ?>> Compact</label>
                </div>
              </fieldset>
              <fieldset class="col-sm-6 mb-0">
                <legend class="form-label">Sidebar</legend>
                <div class="choice-row">
                  <label><input class="form-check-input" type="radio" name="sidebar_mode" value="expanded"<?= $sidebar === 'expanded' ? ' checked' : '' ?>> Expanded</label>
                  <label><input class="form-check-input" type="radio" name="sidebar_mode" value="compact"<?= $sidebar === 'compact' ? ' checked' : '' ?>> Icons only</label>
                </div>
              </fieldset>
            </div>
          </div>

          <button type="submit" class="btn btn-primary mt-3">Save look</button>
        </div>
      </form>
    </div>
  </div>
</main>
<?php
layout_footer();
