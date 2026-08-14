<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

App::ensureDashboardSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = (string) ($_POST['form'] ?? 'appearance');
    if ($form === 'account') {
        try {
            Auth::updateEmail(Auth::id(), (string) ($_POST['email'] ?? ''));
            App::flash('Email updated.');
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
    App::flash('Appearance saved.');
    App::redirect('/settings.php');
}

$density = App::resolveDensity();
$sidebar = App::resolveSidebar();
$ui = App::resolveUiMode();
$accent = App::resolveAccent(null);
$account = Auth::user() ?? ['username' => '', 'email' => '', 'name' => ''];

layout_header('Settings');
?>
<main class="page-narrow">
  <header class="page-head">
    <h1>Settings</h1>
    <p>Dashboard chrome only. Resume PDF themes stay in <a href="/design.php">Design studio</a>.</p>
  </header>

  <form method="post" class="form panel" style="margin-bottom:1.25rem">
    <input type="hidden" name="form" value="account">
    <h2 style="margin:0 0 0.75rem;font-family:var(--display);font-weight:400">Account</h2>
    <p class="muted" style="margin-top:0">Username: <strong><?= App::e((string) $account['username']) ?></strong></p>
    <label>Email <input type="email" name="email" required value="<?= App::e((string) $account['email']) ?>"></label>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save email</button>
    </div>
  </form>

  <form method="post" class="form panel">
    <input type="hidden" name="form" value="appearance">
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
      <legend>Look</legend>
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
      <button type="submit" class="btn btn-primary">Save appearance</button>
    </div>
  </form>
</main>
<?php
layout_footer();
