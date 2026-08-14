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
        <div class="mb-3">
          <label class="form-label" for="name">Name</label>
          <input class="form-control" type="text" id="name" name="name" required value="<?= App::e((string) $account['name']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label" for="username">Username</label>
          <input class="form-control" type="text" id="username" name="username" required minlength="3" maxlength="80" pattern="[a-zA-Z0-9_]+" value="<?= App::e((string) $account['username']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label" for="email">Email</label>
          <input class="form-control" type="email" id="email" name="email" required value="<?= App::e((string) $account['email']) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save account</button>
      </form>

      <form method="post" class="password-form">
        <input type="hidden" name="form" value="password">
        <h3 class="h6">Change password</h3>
        <div class="mb-3">
          <label class="form-label" for="current_password">Current password</label>
          <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="mb-3">
          <label class="form-label" for="new_password">New password</label>
          <input class="form-control" type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="mb-3">
          <label class="form-label" for="confirm_password">Confirm new password</label>
          <input class="form-control" type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-outline-secondary">Update password</button>
      </form>
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
