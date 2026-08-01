<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$pdo = Db::pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_design') {
        $theme = App::resolveTheme($_POST['theme'] ?? null);
        $accent = App::resolveAccent($_POST['accent_color'] ?? null);
        App::setSetting('theme', $theme);
        App::setSetting('accent_color', $accent);
        App::setSetting('pdf_mode', isset($_POST['pdf_mode']) ? '1' : '0');
        App::setSetting('active_company', trim((string) ($_POST['active_company'] ?? '')));

        $wantsJson = isset($_SERVER['HTTP_ACCEPT'])
            && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'application/json');
        if ($wantsJson || isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'theme' => $theme,
                'accent' => $accent,
                'label' => App::themeLabel($theme),
            ]);
            exit;
        }

        $doc = ($_POST['doc'] ?? 'resume') === 'cover' ? 'cover' : 'resume';
        App::flash('Style saved — preview uses ' . App::themeLabel($theme) . '.');
        App::redirect('/design.php?doc=' . $doc);
    }
}

$doc = ($_GET['doc'] ?? 'resume') === 'cover' ? 'cover' : 'resume';
$theme = App::resolveTheme($_GET['theme'] ?? null);
$accent = App::resolveAccent($_GET['accent'] ?? null);
$pdfMode = (App::setting('pdf_mode', '0') ?: '0') === '1';
$activeCompany = App::setting('active_company', '') ?: '';
$previewPath = $doc === 'cover' ? '/cover-letter.php' : '/resume.php';
$profile = App::profile();

layout_header('Design studio', [
    'pdf_mode' => false,
]);
?>
<main class="design-studio" data-design-studio
      data-doc="<?= App::e($doc) ?>"
      data-theme="<?= App::e($theme) ?>"
      data-accent="<?= App::e($accent) ?>">
  <header class="page-head">
    <h1>Design studio</h1>
    <p>Pick a style, preview it live, then print or download a PDF in that look.</p>
  </header>

  <div class="studio-layout">
    <aside class="studio-controls no-print">
      <div class="studio-block">
        <h2>Document</h2>
        <div class="doc-toggle" role="tablist">
          <a class="chip<?= $doc === 'resume' ? ' is-active' : '' ?>" href="/design.php?doc=resume&amp;theme=<?= urlencode($theme) ?>&amp;accent=<?= urlencode($accent) ?>">Resume</a>
          <a class="chip<?= $doc === 'cover' ? ' is-active' : '' ?>" href="/design.php?doc=cover&amp;theme=<?= urlencode($theme) ?>&amp;accent=<?= urlencode($accent) ?>">Cover letter</a>
        </div>
      </div>

      <div class="studio-block">
        <h2>Styles</h2>
        <div class="style-grid" role="listbox" aria-label="Resume styles">
          <?php foreach (App::themes() as $key => $meta): ?>
            <button type="button"
                    class="style-card<?= $theme === $key ? ' is-selected' : '' ?>"
                    data-theme-pick="<?= App::e($key) ?>"
                    aria-pressed="<?= $theme === $key ? 'true' : 'false' ?>">
              <span class="style-thumb theme-<?= App::e($key) ?>" aria-hidden="true">
                <span class="thumb-line thumb-name"></span>
                <span class="thumb-line thumb-sub"></span>
                <span class="thumb-line"></span>
                <span class="thumb-line"></span>
                <span class="thumb-line short"></span>
              </span>
              <span class="style-meta">
                <strong><?= App::e($meta['label']) ?></strong>
                <small><?= App::e($meta['blurb']) ?></small>
              </span>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="studio-block">
        <h2>Accent color</h2>
        <div class="color-presets">
          <?php foreach (App::colorPresets() as $hex => $name): ?>
            <button type="button"
                    class="color-swatch<?= strcasecmp($accent, $hex) === 0 ? ' is-selected' : '' ?>"
                    data-accent-pick="<?= App::e($hex) ?>"
                    title="<?= App::e($name) ?>"
                    style="--swatch: <?= App::e($hex) ?>"
                    aria-label="<?= App::e($name) ?>"></button>
          <?php endforeach; ?>
          <label class="color-custom" title="Custom color">
            <input type="color" data-accent-custom value="<?= App::e($accent) ?>" aria-label="Custom accent color">
          </label>
        </div>
      </div>

      <form method="post" class="studio-block studio-save" data-design-form>
        <input type="hidden" name="action" value="save_design">
        <input type="hidden" name="doc" value="<?= App::e($doc) ?>">
        <input type="hidden" name="theme" data-theme-input value="<?= App::e($theme) ?>">
        <input type="hidden" name="accent_color" data-accent-input value="<?= App::e($accent) ?>">
        <label>
          Active company tag
          <input type="text" name="active_company" value="<?= App::e($activeCompany) ?>" placeholder="Optional">
        </label>
        <label class="check">
          <input type="checkbox" name="pdf_mode" value="1"<?= $pdfMode ? ' checked' : '' ?>>
          Optimize layout for PDF / print
        </label>
        <button type="submit" class="btn btn-primary">Apply style</button>
      </form>

      <div class="studio-actions">
        <button type="button" class="btn btn-secondary" data-studio-print>Print</button>
        <button type="button" class="btn btn-primary" data-studio-pdf>Download PDF</button>
      </div>
      <p class="studio-hint">Download PDF opens the print dialog — choose <strong>Save as PDF</strong> as the printer. Filename: <?= App::e($profile['full_name']) ?>.</p>
    </aside>

    <section class="studio-preview">
      <div class="preview-bar no-print">
        <span data-preview-label>Preview · <?= App::e(App::themeLabel($theme)) ?></span>
        <a data-open-full href="<?= App::e($previewPath) ?>?theme=<?= urlencode($theme) ?>&amp;accent=<?= urlencode($accent) ?>&amp;pdf=1" target="_blank" rel="noopener">Open full page</a>
      </div>
      <div class="preview-frame-wrap">
        <iframe
          title="Document preview"
          class="preview-frame"
          data-preview-frame
          src="<?= App::e($previewPath) ?>?embed=1&amp;theme=<?= urlencode($theme) ?>&amp;accent=<?= urlencode($accent) ?>&amp;pdf=1"></iframe>
      </div>
    </section>
  </div>
</main>
<?php
layout_footer();
