<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

CareerCompanies::ensureSchema();
$uid = Auth::id();
CareerCompanies::purgePersonalDuplicates($uid);

$filterQs = static function (): string {
    $q = [];
    foreach (['scope', 'type', 'q'] as $k) {
        $v = trim((string) ($_GET[$k] ?? ''));
        if ($v !== '') {
            $q[$k] = $v;
        }
    }
    return $q === [] ? '' : ('?' . http_build_query($q));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            CareerCompanies::add(
                $uid,
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['board_type'] ?? ''),
                (string) ($_POST['board_key'] ?? ''),
                (string) ($_POST['careers_url'] ?? '')
            );
            App::flash('Personal board added.');
        } elseif ($action === 'toggle') {
            CareerCompanies::setEnabled($uid, (int) ($_POST['id'] ?? 0), (string) ($_POST['enabled'] ?? '') === '1');
            App::flash('Board updated.');
        } elseif ($action === 'delete') {
            CareerCompanies::delete($uid, (int) ($_POST['id'] ?? 0));
            App::flash('Personal board removed.');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/companies.php' . $filterQs());
}

$scope = (string) ($_GET['scope'] ?? 'all');
if (!in_array($scope, ['all', 'shared', 'mine'], true)) {
    $scope = 'all';
}
$type = strtolower(trim((string) ($_GET['type'] ?? '')));
if (!in_array($type, ['', 'greenhouse', 'personio', 'smartrecruiters', 'site'], true)) {
    $type = '';
}
$search = trim((string) ($_GET['q'] ?? ''));

$shared = CareerCompanies::forUser(0, true);
$mine = CareerCompanies::personalExtras($uid);

$rows = [];
if ($scope !== 'mine') {
    foreach ($shared as $c) {
        $rows[] = $c + ['scope' => 'shared'];
    }
}
if ($scope !== 'shared') {
    foreach ($mine as $c) {
        $rows[] = $c + ['scope' => 'mine'];
    }
}

if ($type !== '') {
    $rows = array_values(array_filter(
        $rows,
        static fn(array $c): bool => $c['board_type'] === $type
    ));
}
if ($search !== '') {
    $needle = mb_strtolower($search);
    $rows = array_values(array_filter(
        $rows,
        static function (array $c) use ($needle): bool {
            $hay = mb_strtolower($c['name'] . ' ' . $c['board_key'] . ' ' . $c['board_type']);
            return str_contains($hay, $needle);
        }
    ));
}

layout_header('Companies');
?>
<main class="page-wide">
  <header class="page-head d-flex flex-wrap justify-content-between gap-2 align-items-start">
    <div>
      <h1>Company career boards</h1>
      <p class="mb-0">One list: shared boards for everyone, plus any extras you add. Jobs uses these when <strong>Company career pages</strong> is on.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= App::e(JobQuery::jobsHref()) ?>">← Jobs</a>
  </header>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h5 mb-2">Add a personal board</h2>
      <p class="small text-secondary mb-2">Only for companies not already in the shared list. Use type <code>sitemap</code> when the careers site has a job sitemap (like DIS AG / Rossmann).</p>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="add">
        <div class="col-md-3">
          <label class="form-label" for="name">Company</label>
          <input class="form-control" id="name" name="name" required placeholder="Acme GmbH">
        </div>
        <div class="col-md-2">
          <label class="form-label" for="board_type">Type</label>
          <select class="form-select" id="board_type" name="board_type" required>
            <option value="site">Career site URL</option>
            <option value="sitemap">Job sitemap</option>
            <option value="greenhouse">Greenhouse</option>
            <option value="personio">Personio</option>
            <option value="smartrecruiters">SmartRecruiters</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="board_key">Key / host</label>
          <input class="form-control" id="board_key" name="board_key" required placeholder="jobs.example.com or slug">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="careers_url">Careers URL</label>
          <input class="form-control" id="careers_url" name="careers_url" placeholder="https://…">
        </div>
        <div class="col-md-1">
          <button type="submit" class="btn btn-primary w-100">Add</button>
        </div>
      </form>
    </div>
  </div>

  <form method="get" class="card shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label" for="filter-scope">Scope</label>
        <select class="form-select" id="filter-scope" name="scope">
          <option value="all"<?= $scope === 'all' ? ' selected' : '' ?>>All</option>
          <option value="shared"<?= $scope === 'shared' ? ' selected' : '' ?>>Shared</option>
          <option value="mine"<?= $scope === 'mine' ? ' selected' : '' ?>>Mine</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="filter-type">Type</label>
        <select class="form-select" id="filter-type" name="type">
          <option value=""<?= $type === '' ? ' selected' : '' ?>>All types</option>
          <?php foreach (['greenhouse', 'personio', 'smartrecruiters', 'site', 'sitemap'] as $t): ?>
            <option value="<?= $t ?>"<?= $type === $t ? ' selected' : '' ?>><?= App::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="filter-q">Search</label>
        <input class="form-control" id="filter-q" name="q" value="<?= App::e($search) ?>" placeholder="Company name or key">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary flex-grow-1">Filter</button>
        <a class="btn btn-outline-secondary" href="/companies.php">Reset</a>
      </div>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <div class="card shadow-sm"><div class="card-body text-secondary">No companies match this filter.</div></div>
  <?php else: ?>
    <div class="table-responsive card shadow-sm">
      <table class="table table-sm mb-0 align-middle">
        <thead>
          <tr>
            <th>Scope</th>
            <th>On</th>
            <th>Company</th>
            <th>Type</th>
            <th>Key / host</th>
            <th>Link</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $c): ?>
            <?php $isMine = ($c['scope'] ?? '') === 'mine'; ?>
            <tr>
              <td>
                <?php if ($isMine): ?>
                  <span class="badge text-bg-primary">Mine</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Shared</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isMine): ?>
                  <form method="post" class="m-0">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <input type="hidden" name="enabled" value="<?= $c['enabled'] ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-sm <?= $c['enabled'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                      <?= $c['enabled'] ? 'On' : 'Off' ?>
                    </button>
                  </form>
                <?php else: ?>
                  <span class="visually-hidden">On</span>
                <?php endif; ?>
              </td>
              <td class="fw-semibold"><?= App::e($c['name']) ?></td>
              <td><span class="badge text-bg-light border"><?= App::e($c['board_type']) ?></span></td>
              <td class="small"><code><?= App::e($c['board_key']) ?></code></td>
              <td class="small">
                <?php if ($c['careers_url'] !== ''): ?>
                  <a href="<?= App::e($c['careers_url']) ?>" target="_blank" rel="noopener">Open</a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if ($isMine): ?>
                  <form method="post" class="m-0" onsubmit="return confirm('Remove this personal board?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small text-secondary mt-2 mb-0"><?= count($rows) ?> shown</p>
  <?php endif; ?>

  <p class="small text-secondary mt-3 mb-0">
    Shared rows are read-only. Toggle or remove only applies to boards you added.
    Greenhouse / Personio / SmartRecruiters use public APIs; site boards use Google <code>site:</code> when Bright Data is set.
  </p>
</main>
<?php
layout_footer();
