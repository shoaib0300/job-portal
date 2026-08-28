<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

App::ensureDashboardSchema();

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
    $ui = App::resolveUiMode((string) ($_POST['ui_mode'] ?? ''));
    App::setSetting('ui_density', $density);
    App::setSetting('sidebar_mode', $sidebar);
    App::setSetting('ui_mode', $ui);
    App::flash('Look saved.');
    App::redirect('/settings');
}

$density = App::resolveDensity();
$sidebar = App::resolveSidebar();
$ui = App::resolveUiMode();
$account = Auth::user() ?? ['username' => '', 'email' => '', 'name' => ''];
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
<main class="page-narrow">
  <header class="page-head">
    <h1>Account</h1>
    <p>Your name, login, and how the dashboard looks.</p>
  </header>

  <section class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h5 mb-3">Your account</h2>
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
            <button type="submit" class="btn btn-primary">Save account</button>
          </div>
        </div>
      </form>

      <form method="post" class="password-form">
        <input type="hidden" name="form" value="password">
        <h3 class="h6">Change password</h3>
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
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h5 mb-2">German PDF translations</h2>
      <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <p class="small text-secondary mb-0">Your German PDF usage only. Other accounts cannot see this. Price is 5 cents (€0.05) per 1,000 billed characters.</p>
        <form method="get" class="ms-auto">
          <label class="form-label small mb-1" for="usage">Period</label>
          <select class="form-select form-select-sm" id="usage" name="usage" onchange="this.form.submit()">
            <?php foreach ($usagePeriodLabels as $value => $label): ?>
              <option value="<?= App::e($value) ?>"<?= $usagePeriod === $value ? ' selected' : '' ?>><?= App::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <p class="mb-0"><?= App::e($usagePeriodLabels[$usagePeriod]) ?>: <strong><?= App::e(LibreTranslate::formatEuro($myTranslation['billed_chars'])) ?></strong>
        (<?= App::e(number_format($myTranslation['billed_chars'])) ?> billed,
        <?= App::e(number_format($myTranslation['cached_chars'])) ?> cache,
        <?= App::e(number_format($myTranslation['requests'])) ?> requests).</p>
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h5 mb-2">Career pages</h2>
      <p class="small text-secondary mb-3">Manage Mercedes, BMW, and ~100 German company boards on the Companies page. Enabled boards feed the <strong>Company career pages</strong> Jobs source.</p>
      <a class="btn btn-outline-secondary" href="/companies">Open company boards</a>
    </div>
  </section>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <input type="hidden" name="form" value="appearance">
      <h2 class="h5 mb-3">Look</h2>
      <fieldset class="mb-3">
        <legend class="form-label">Density</legend>
        <div class="choice-row">
          <label><input class="form-check-input" type="radio" name="ui_density" value="comfortable"<?= $density === 'comfortable' ? ' checked' : '' ?>> Comfortable</label>
          <label><input class="form-check-input" type="radio" name="ui_density" value="compact"<?= $density === 'compact' ? ' checked' : '' ?>> Compact</label>
        </div>
      </fieldset>
      <fieldset class="mb-3">
        <legend class="form-label">Sidebar</legend>
        <div class="choice-row">
          <label><input class="form-check-input" type="radio" name="sidebar_mode" value="expanded"<?= $sidebar === 'expanded' ? ' checked' : '' ?>> Expanded</label>
          <label><input class="form-check-input" type="radio" name="sidebar_mode" value="compact"<?= $sidebar === 'compact' ? ' checked' : '' ?>> Compact icons</label>
        </div>
      </fieldset>
      <fieldset class="mb-3">
        <legend class="form-label">Theme</legend>
        <div class="choice-row">
          <label><input class="form-check-input" type="radio" name="ui_mode" value="warm"<?= $ui === 'warm' ? ' checked' : '' ?>> Light</label>
          <label><input class="form-check-input" type="radio" name="ui_mode" value="warm-dark"<?= $ui === 'warm-dark' ? ' checked' : '' ?>> Dark</label>
        </div>
      </fieldset>
      <p class="text-secondary small mb-0">Resume and cover accent colors are set on the <a href="/design">Resume style</a> and <a href="/cover-design">Cover style</a> pages.</p>
      <button type="submit" class="btn btn-primary mt-3">Save look</button>
    </div>
  </form>
</main>
<?php
layout_footer();
