<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

$pdo = Db::pdo();
Versions::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_design') {
        $theme = App::resolveTheme($_POST['theme'] ?? null);
        $accent = App::resolveAccent($_POST['accent_color'] ?? null);
        $font = App::resolveFont($_POST['font_family'] ?? null);
        App::setSetting('theme', $theme);
        App::setSetting('accent_color', $accent);
        App::setSetting('font_family', $font);
        App::setSetting('pdf_mode', isset($_POST['pdf_mode']) ? '1' : '0');
        App::setSetting('active_company', trim((string) ($_POST['active_company'] ?? '')));
        App::setSetting('name_size', App::resolveNameSize((string) ($_POST['name_size'] ?? '')));
        App::setSetting('font_size', App::resolveFontSize((string) ($_POST['font_size'] ?? '')));
        App::setSetting('section_spacing', App::resolveSectionSpacing((string) ($_POST['section_spacing'] ?? '')));

        $wantsJson = isset($_SERVER['HTTP_ACCEPT'])
            && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'application/json');
        if ($wantsJson || isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'theme' => $theme,
                'accent' => $accent,
                'font' => $font,
                'label' => App::themeLabel($theme),
                'font_label' => App::fontLabel($font),
            ]);
            exit;
        }

        $doc = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'cover-design.php' ? 'cover' : 'resume';
        App::flash('Style saved — ' . App::themeLabel($theme) . ' · ' . App::fontLabel($font) . '.');
        App::redirect($doc === 'cover' ? '/cover-design' : '/design');
    }
}

$script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$doc = $script === 'cover-design.php' ? 'cover' : 'resume';
if ($script === 'design.php' && ($_GET['doc'] ?? '') === 'cover') {
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $qs = trim((string) preg_replace('/(?:^|&)doc=cover/', '', $qs), '&');
    App::redirect('/cover-design' . ($qs !== '' ? '?' . $qs : ''));
}
$theme = App::resolveTheme($_GET['theme'] ?? null);
$accent = App::resolveAccent($_GET['accent'] ?? null);
$font = App::resolveFont($_GET['font'] ?? null);
$pdfMode = (App::setting('pdf_mode', '0') ?: '0') === '1';
$activeCompany = App::setting('active_company', '') ?: '';
$nameSize = App::resolveNameSize($_GET['name_size'] ?? null);
$fontSize = App::resolveFontSize($_GET['font_size'] ?? null);
$spacing = App::resolveSectionSpacing($_GET['spacing'] ?? null);
$previewPath = $doc === 'cover' ? '/cover-letter.php' : '/resume.php';
$profile = App::profile();
$q = 'theme=' . urlencode($theme) . '&accent=' . urlencode($accent) . '&font=' . urlencode($font)
    . '&name_size=' . urlencode($nameSize) . '&font_size=' . urlencode($fontSize) . '&spacing=' . urlencode($spacing);
$exportOptions = $doc === 'cover' ? Versions::coverExportOptions() : Versions::resumeExportOptions();
$exportJson = App::e(json_encode($exportOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');

layout_header($doc === 'cover' ? 'Cover style' : 'Resume style', [
    'pdf_mode' => false,
    'font' => $font,
    'accent' => $accent,
    'theme' => $theme,
]);
?>
<main class="design-studio" data-design-studio
      data-doc="<?= App::e($doc) ?>"
      data-theme="<?= App::e($theme) ?>"
      data-accent="<?= App::e($accent) ?>"
      data-font="<?= App::e($font) ?>"
      data-name-size="<?= App::e($nameSize) ?>"
      data-font-size="<?= App::e($fontSize) ?>"
      data-spacing="<?= App::e($spacing) ?>"
      data-export-options="<?= $exportJson ?>">
  <header class="page-head">
    <h1><?= $doc === 'cover' ? 'Cover style' : 'Resume style' ?></h1>
    <p><?= $doc === 'cover' ? 'Pick a look for this letter, then download the PDF.' : 'Pick a look for this resume, then download the PDF.' ?></p>
  </header>

  <div class="studio-layout">
    <aside class="studio-controls no-print">
      <div class="studio-block">
        <h2>Styles</h2>
        <div class="style-grid" role="listbox" aria-label="<?= $doc === 'cover' ? 'Cover letter styles' : 'Resume styles' ?>">
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
        <h2>Font</h2>
        <div class="font-grid" role="listbox" aria-label="Document fonts">
          <?php foreach (App::fonts() as $key => $meta): ?>
            <button type="button"
                    class="font-card<?= $font === $key ? ' is-selected' : '' ?>"
                    data-font-pick="<?= App::e($key) ?>"
                    aria-pressed="<?= $font === $key ? 'true' : 'false' ?>"
                    style="font-family: <?= App::e($meta['stack']) ?>">
              <strong><?= App::e($meta['label']) ?></strong>
              <span>Aa Bb Cc · The quick brown fox</span>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="studio-block">
        <h2>Font size</h2>
        <div class="doc-toggle" role="listbox" aria-label="Font size">
          <?php foreach (['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $key => $label): ?>
            <button type="button"
                    class="chip<?= $fontSize === $key ? ' is-selected is-active' : '' ?>"
                    data-font-size-pick="<?= App::e($key) ?>"><?= App::e($label) ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="studio-block">
        <h2>Section spacing</h2>
        <div class="doc-toggle" role="listbox" aria-label="Section spacing">
          <?php foreach (['tight' => 'Tight', 'md' => 'Medium', 'loose' => 'Loose'] as $key => $label): ?>
            <button type="button"
                    class="chip<?= $spacing === $key ? ' is-selected is-active' : '' ?>"
                    data-spacing-pick="<?= App::e($key) ?>"><?= App::e($label) ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="studio-block">
        <h2>Accent color</h2>
        <p class="studio-hint" style="margin-top:0">For your resume or letter PDF only — dashboard buttons stay the same.</p>
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
        <input type="hidden" name="font_family" data-font-input value="<?= App::e($font) ?>">
        <input type="hidden" name="name_size" data-name-size-input value="md">
        <input type="hidden" name="font_size" data-font-size-input value="<?= App::e($fontSize) ?>">
        <input type="hidden" name="section_spacing" data-spacing-input value="<?= App::e($spacing) ?>">
        <label class="form-label">
          Active company tag
          <input class="form-control" type="text" name="active_company" value="<?= App::e($activeCompany) ?>" placeholder="Optional">
        </label>
        <label class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="pdf_mode" value="1"<?= $pdfMode ? ' checked' : '' ?>>
          Optimize layout for PDF / print
        </label>
        <button type="submit" class="btn btn-primary">Apply style</button>
      </form>

      <div class="studio-actions d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary" data-studio-print
                data-doc="<?= App::e($doc) ?>"
                data-export-options="<?= $exportJson ?>">Print</button>
        <button type="button" class="btn btn-primary" data-studio-pdf
                data-doc="<?= App::e($doc) ?>"
                data-export-options="<?= $exportJson ?>">Download PDF</button>
      </div>
      <p class="studio-hint"><?= $doc === 'cover' ? 'Download asks which letter to send.' : 'Download asks which resume to send.' ?></p>
    </aside>

    <section class="studio-preview">
      <div class="preview-bar no-print">
        <span data-preview-label>Preview · <?= App::e(App::themeLabel($theme)) ?> · <?= App::e(App::fontLabel($font)) ?></span>
        <a data-open-full href="<?= App::e($previewPath) ?>?<?= App::e($q) ?>&amp;pdf=1" target="_blank" rel="noopener">Open full page</a>
      </div>
      <div class="preview-frame-wrap">
        <iframe
          title="Document preview"
          class="preview-frame"
          data-preview-frame
          src="<?= App::e($previewPath) ?>?embed=1&amp;<?= App::e($q) ?>&amp;pdf=1"></iframe>
      </div>
    </section>
  </div>
</main>
<?php
layout_footer();
