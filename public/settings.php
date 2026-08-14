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

  <section class="panel account-card">
    <h2>Your account</h2>
    <form method="post" class="form">
      <input type="hidden" name="form" value="account">
      <label>Name <input type="text" name="name" required value="<?= App::e((string) $account['name']) ?>"></label>
      <label>Username <input type="text" name="username" required minlength="3" maxlength="80" pattern="[a-zA-Z0-9_]+" value="<?= App::e((string) $account['username']) ?>"></label>
      <label>Email <input type="email" name="email" required value="<?= App::e((string) $account['email']) ?>"></label>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save account</button>
      </div>
    </form>

    <form method="post" class="form password-form">
      <input type="hidden" name="form" value="password">
      <h3>Change password</h3>
      <label>Current password <input type="password" name="current_password" required autocomplete="current-password"></label>
      <label>New password <input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
      <label>Confirm new password <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
      <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Update password</button>
      </div>
    </form>
  </section>

  <form method="post" class="form panel">
    <input type="hidden" name="form" value="appearance">
    <h2>Look</h2>
    <fieldset>
      <legend>Density</legend>
      <div class="choice-row">
        <label><input type="radio" name="ui_density" value="comfortable"<?= $density === 'comfortable' ? ' checked' : '' ?>> Comfortable</label>
        <label><input type="radio" name="ui_density" value="compact"<?= $density === 'compact' ? ' checked' : '' ?>> Compact</label>
      </div>
    </fieldset>
    <fieldset>
      <legend>Sidebar</legend>
      <div class="choice-row">
        <label><input type="radio" name="sidebar_mode" value="expanded"<?= $sidebar === 'expanded' ? ' checked' : '' ?>> Expanded</label>
        <label><input type="radio" name="sidebar_mode" value="compact"<?= $sidebar === 'compact' ? ' checked' : '' ?>> Compact icons</label>
      </div>
    </fieldset>
    <fieldset>
      <legend>Theme</legend>
      <div class="choice-row">
        <label><input type="radio" name="ui_mode" value="warm"<?= $ui === 'warm' ? ' checked' : '' ?>> Warm paper</label>
        <label><input type="radio" name="ui_mode" value="warm-dark"<?= $ui === 'warm-dark' ? ' checked' : '' ?>> Warm dark</label>
      </div>
    </fieldset>
    <label>Accent
      <input type="color" name="accent_color" value="<?= App::e($accent) ?>">
    </label>
    <div class="color-presets">
      <?php foreach (App::colorPresets() as $hex => $name): ?>
        <button type="button" class="color-swatch<?= strcasecmp($accent, $hex) === 0 ? ' is-selected' : '' ?>"
                style="--swatch: <?= App::e($hex) ?>"
                title="<?= App::e($name) ?>"
                onclick="this.form.accent_color.value='<?= App::e($hex) ?>'"></button>
      <?php endforeach; ?>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save look</button>
    </div>
  </form>
</main>
<?php
layout_footer();
