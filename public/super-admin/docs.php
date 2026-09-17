<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

use KaamFit\Interview\InterviewMarkdown;

SuperAdmin::requireLogin();

$docsRoot = realpath(dirname(__DIR__, 2) . '/docs');
if ($docsRoot === false || !is_dir($docsRoot)) {
    App::flash('Docs folder not found.', 'error');
    App::redirect('/super-admin/');
}

/**
 * @return list<array{section:string,slug:string,title:string,path:string}>
 */
$catalog = static function (string $root): array {
    $out = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
            continue;
        }
        $full = $file->getRealPath();
        if ($full === false) {
            continue;
        }
        $rel = substr($full, strlen($root) + 1);
        $rel = str_replace('\\', '/', $rel);
        $section = dirname($rel);
        if ($section === '.') {
            $section = 'general';
        }
        $base = basename($rel, '.md');
        $slug = $section . '/' . $base;
        $title = $base === 'README' ? ucfirst($section) . ' overview' : str_replace('-', ' ', $base);
        $raw = (string) file_get_contents($full);
        if (preg_match('/^#\s+(.+)$/m', $raw, $m)) {
            $title = trim($m[1]);
        }
        $out[] = [
            'section' => $section,
            'slug' => $slug,
            'title' => $title,
            'path' => $rel,
            'sort' => $section . '/' . ($base === 'README' ? '00-readme' : $base),
        ];
    }
    usort($out, static fn(array $a, array $b): int => strcmp($a['sort'], $b['sort']));
    return array_map(static function (array $row): array {
        unset($row['sort']);
        return $row;
    }, $out);
};

$items = $catalog($docsRoot);
$bySlug = [];
foreach ($items as $item) {
    $bySlug[$item['slug']] = $item;
}

$requested = trim((string) ($_GET['doc'] ?? ''));
$requested = str_replace('\\', '/', $requested);
$requested = preg_replace('#\.md$#i', '', $requested) ?? $requested;
$requested = trim($requested, '/');

if ($requested === '' && $items !== []) {
    // Prefer redis/README if present
    $requested = isset($bySlug['redis/README']) ? 'redis/README' : $items[0]['slug'];
}

$current = $bySlug[$requested] ?? null;
$html = '';
$error = '';

if ($current === null) {
    $error = $requested === '' ? 'No documentation files found under /docs.' : 'Document not found.';
} else {
    $full = realpath($docsRoot . '/' . $current['path']);
    if ($full === false || !str_starts_with($full, $docsRoot . DIRECTORY_SEPARATOR) || !is_file($full)) {
        $error = 'Document path is invalid.';
        $current = null;
    } else {
        $markdown = (string) file_get_contents($full);
        $sectionDir = dirname($current['path']);
        $linkPlaceholders = [];
        // Rewrite relative .md links → Super Admin docs URLs (placeholders survive Markdown render)
        $markdown = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+\.md)(#[^)]*)?\)/i',
            static function (array $m) use ($sectionDir, &$linkPlaceholders): string {
                $label = $m[1];
                $target = str_replace('\\', '/', $m[2]);
                $hash = $m[3] ?? '';
                if (preg_match('#^(https?:)?//#i', $target) || str_starts_with($target, '/')) {
                    return $m[0];
                }
                $joined = $sectionDir === '.' ? $target : ($sectionDir . '/' . $target);
                $parts = [];
                foreach (explode('/', $joined) as $p) {
                    if ($p === '' || $p === '.') {
                        continue;
                    }
                    if ($p === '..') {
                        array_pop($parts);
                        continue;
                    }
                    $parts[] = $p;
                }
                $norm = implode('/', $parts);
                $slug = preg_replace('#\.md$#i', '', $norm) ?? $norm;
                $idx = count($linkPlaceholders);
                $href = '/super-admin/docs?doc=' . rawurlencode($slug) . $hash;
                $linkPlaceholders[$idx] = '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                    . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
                return '%%DOCLINK' . $idx . '%%';
            },
            $markdown
        ) ?? $markdown;
        $html = InterviewMarkdown::render($markdown);
        foreach ($linkPlaceholders as $idx => $anchor) {
            $html = str_replace('%%DOCLINK' . $idx . '%%', $anchor, $html);
            $html = str_replace(htmlspecialchars('%%DOCLINK' . $idx . '%%', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $anchor, $html);
        }
    }
}

$sections = [];
foreach ($items as $item) {
    $sections[$item['section']][] = $item;
}

super_layout_header($current ? $current['title'] : 'Docs');
?>
<style>
  .sa-docs { display: grid; grid-template-columns: 240px 1fr; gap: 1.25rem; }
  .sa-docs-nav { background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,.06); padding: .75rem; max-height: calc(100vh - 8rem); overflow: auto; }
  .sa-docs-nav h2 { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; margin: .75rem .4rem .35rem; }
  .sa-docs-nav a { display: block; padding: .35rem .5rem; border-radius: 6px; color: #343a40; text-decoration: none; font-size: .9rem; }
  .sa-docs-nav a:hover { background: #f1f3f5; }
  .sa-docs-nav a.active { background: #e7f1ff; color: #0d6efd; font-weight: 600; }
  .sa-docs-body { background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,.06); padding: 1.25rem 1.5rem; min-width: 0; }
  .sa-docs-body h3, .sa-docs-body h4, .sa-docs-body h5 { margin-top: 1.25rem; }
  .sa-docs-body pre.interview-code { background: #1e1e1e; color: #f8f8f2; padding: .85rem 1rem; border-radius: 6px; overflow: auto; font-size: .85rem; }
  .sa-docs-body table { width: 100%; margin: 1rem 0; border-collapse: collapse; font-size: .9rem; }
  .sa-docs-body th, .sa-docs-body td { border: 1px solid #dee2e6; padding: .4rem .55rem; vertical-align: top; }
  .sa-docs-body th { background: #f8f9fa; }
  .sa-docs-body code { background: #f1f3f5; padding: .1rem .35rem; border-radius: 4px; font-size: .88em; }
  .sa-docs-body pre code { background: transparent; padding: 0; color: inherit; }
  @media (max-width: 900px) {
    .sa-docs { grid-template-columns: 1fr; }
    .sa-docs-nav { max-height: none; }
  }
</style>

<p class="small text-secondary mb-3">
  Internal documentation from <code>docs/</code> — Super Admin only.
</p>

<div class="sa-docs">
  <nav class="sa-docs-nav" aria-label="Documentation">
    <?php foreach ($sections as $section => $list): ?>
      <h2><?= App::e($section) ?></h2>
      <?php foreach ($list as $item): ?>
        <a href="/super-admin/docs?doc=<?= App::e(rawurlencode($item['slug'])) ?>"
           class="<?= ($current && $current['slug'] === $item['slug']) ? 'active' : '' ?>">
          <?= App::e($item['title']) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if ($items === []): ?>
      <p class="small text-secondary mb-0 px-2">No markdown files found.</p>
    <?php endif; ?>
  </nav>
  <article class="sa-docs-body">
    <?php if ($error !== ''): ?>
      <div class="alert alert-warning mb-0"><?= App::e($error) ?></div>
    <?php else: ?>
      <p class="small text-secondary mb-3">
        <code><?= App::e('docs/' . ($current['path'] ?? '')) ?></code>
      </p>
      <?= $html ?>
    <?php endif; ?>
  </article>
</div>
<?php
super_layout_footer();
