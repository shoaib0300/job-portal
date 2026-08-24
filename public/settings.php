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
        App::redirect('/settings.php');
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
        App::redirect('/settings.php');
    }
    if ($form === 'job_boards') {
        $raw = trim((string) ($_POST['job_ats_boards'] ?? ''));
        App::setSetting('job_ats_boards', $raw);
        App::flash('Career boards saved.');
        App::redirect('/settings.php');
    }
    $density = App::resolveDensity((string) ($_POST['ui_density'] ?? ''));
    $sidebar = App::resolveSidebar((string) ($_POST['sidebar_mode'] ?? ''));
    $ui = App::resolveUiMode((string) ($_POST['ui_mode'] ?? ''));
    $accent = App::resolveAccent((string) ($_POST['accent_color'] ?? ''));
    App::setSetting('ui_density', $density);
    App::setSetting('sidebar_mode', $sidebar);
    App::setSetting('ui_mode', $ui);
    App::setSetting('accent_color', $accent);
    App::flash('Look saved.');
    App::redirect('/settings.php');
}

$density = App::resolveDensity();
$sidebar = App::resolveSidebar();
$ui = App::resolveUiMode();
$accent = App::resolveAccent(null);
$account = Auth::user() ?? ['username' => '', 'email' => '', 'name' => ''];
$atsBoards = (string) (App::setting('job_ats_boards', '') ?: '');
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
$showAllTranslation = Auth::isOwner();
$allTranslation = $showAllTranslation ? LibreTranslate::usageByUserThisMonth() : [];
$deeplAccount = DeepL::configured() ? DeepL::accountUsage() : null;

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
        <p class="small text-secondary mb-0">DeepL is used only for the German cover letter. Price is 5 cents (€0.05) per 1,000 billed characters. Cache does not cost anything.</p>
        <form method="get" class="ms-auto">
          <label class="form-label small mb-1" for="usage">Period</label>
          <select class="form-select form-select-sm" id="usage" name="usage" onchange="this.form.submit()">
            <?php foreach ($usagePeriodLabels as $value => $label): ?>
              <option value="<?= App::e($value) ?>"<?= $usagePeriod === $value ? ' selected' : '' ?>><?= App::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <?php if ($deeplAccount): ?>
        <p class="mb-3">DeepL key this period: <strong><?= App::e(number_format($deeplAccount['character_count'])) ?></strong>
          / <?= App::e(number_format($deeplAccount['character_limit'])) ?> characters.</p>
      <?php endif; ?>
      <p class="mb-0"><?= App::e($usagePeriodLabels[$usagePeriod]) ?>: <strong><?= App::e(LibreTranslate::formatEuro($myTranslation['billed_chars'])) ?></strong>
        (<?= App::e(number_format($myTranslation['billed_chars'])) ?> billed,
        <?= App::e(number_format($myTranslation['cached_chars'])) ?> cache,
        <?= App::e(number_format($myTranslation['requests'])) ?> requests).</p>
      <?php if ($showAllTranslation): ?>
        <div class="table-responsive mt-3">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>User</th>
                <th class="text-end">Cost</th>
                <th class="text-end">Billed chars</th>
                <th class="text-end">Cached chars</th>
                <th class="text-end">Requests</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $visibleRows = [];
              foreach ($allTranslation as $row) {
                  $stats = LibreTranslate::usageForPeriod($row, $usagePeriod);
                  if ($stats['billed_chars'] === 0 && $stats['cached_chars'] === 0 && $stats['requests'] === 0) {
                      continue;
                  }
                  $visibleRows[] = [$row, $stats];
              }
              ?>
              <?php if ($visibleRows === []): ?>
                <tr><td colspan="5" class="text-secondary">No PDF DE translations in this period.</td></tr>
              <?php else: ?>
              <?php foreach ($visibleRows as [$row, $stats]): ?>
                <?php
                $label = trim($row['name'] !== '' ? $row['name'] : $row['username']);
                if ($label === '') {
                    $label = $row['user_id'] > 0 ? 'User #' . $row['user_id'] : 'CLI / unknown';
                } elseif ($row['username'] !== '' && $row['name'] !== '') {
                    $label .= ' (@' . $row['username'] . ')';
                }
                $label .= ' · ' . LibreTranslate::formatEuro($stats['billed_chars']);
                ?>
                <tr>
                  <td><?= App::e($label) ?></td>
                  <td class="text-end"><?= App::e(LibreTranslate::formatEuro($stats['billed_chars'])) ?></td>
                  <td class="text-end"><?= App::e(number_format($stats['billed_chars'])) ?></td>
                  <td class="text-end"><?= App::e(number_format($stats['cached_chars'])) ?></td>
                  <td class="text-end"><?= App::e(number_format($stats['requests'])) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <form method="post" class="card shadow-sm mb-3">
    <div class="card-body">
      <input type="hidden" name="form" value="job_boards">
      <h2 class="h5 mb-3">Career pages</h2>
      <p class="small text-secondary">Extra Personio and Greenhouse boards for Jobs search. One per line: <code>personio:slug</code> or <code>greenhouse:slug</code>.</p>
      <label class="form-label" for="job_ats_boards">Boards</label>
      <textarea class="form-control mb-3" id="job_ats_boards" name="job_ats_boards" rows="5" placeholder="greenhouse:n26&#10;personio:getyourguide"><?= App::e($atsBoards) ?></textarea>
      <button type="submit" class="btn btn-outline-secondary">Save boards</button>
    </div>
  </form>

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
      <div class="mb-3">
        <label class="form-label" for="accent_color">Accent</label>
        <input class="form-control form-control-color" type="color" id="accent_color" name="accent_color" value="<?= App::e($accent) ?>">
      </div>
      <div class="color-presets mb-3">
        <?php foreach (App::colorPresets() as $hex => $name): ?>
          <button type="button" class="color-swatch<?= strcasecmp($accent, $hex) === 0 ? ' is-selected' : '' ?>"
                  style="--swatch: <?= App::e($hex) ?>"
                  title="<?= App::e($name) ?>"
                  onclick="this.form.accent_color.value='<?= App::e($hex) ?>'"></button>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary">Save look</button>
    </div>
  </form>
</main>
<?php
layout_footer();
