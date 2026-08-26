<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

CareerCompanies::ensureSchema();
$uid = Auth::id();

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
            App::flash('Company board added.');
        } elseif ($action === 'toggle') {
            CareerCompanies::setEnabled($uid, (int) ($_POST['id'] ?? 0), (string) ($_POST['enabled'] ?? '') === '1');
            App::flash('Company updated.');
        } elseif ($action === 'delete') {
            CareerCompanies::delete($uid, (int) ($_POST['id'] ?? 0));
            App::flash('Company removed.');
        } elseif ($action === 'seed') {
            $n = CareerCompanies::seedDefaults($uid);
            App::flash($n > 0 ? "Added {$n} companies from the Germany catalog." : 'Catalog already loaded (duplicates skipped).');
        } elseif ($action === 'enable_all') {
            Db::pdo()->prepare('UPDATE career_companies SET enabled = 1 WHERE user_id = ?')->execute([$uid]);
            App::flash('All companies enabled.');
        } elseif ($action === 'disable_sites') {
            Db::pdo()->prepare('UPDATE career_companies SET enabled = 0 WHERE user_id = ? AND board_type = ?')
                ->execute([$uid, 'site']);
            App::flash('Site boards disabled (Greenhouse/Personio/SmartRecruiters stay on).');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/companies.php');
}

$companies = CareerCompanies::forUser($uid);
$enabled = count(array_filter($companies, static fn(array $c): bool => $c['enabled'] === 1));
$byType = ['greenhouse' => 0, 'personio' => 0, 'smartrecruiters' => 0, 'site' => 0];
foreach ($companies as $c) {
    $t = $c['board_type'];
    if (isset($byType[$t])) {
        $byType[$t]++;
    }
}

layout_header('Companies');
?>
<main class="page-wide">
  <header class="page-head d-flex flex-wrap justify-content-between gap-2 align-items-start">
    <div>
      <h1>Company career boards</h1>
      <p class="mb-0">Add employer career links. Jobs search uses enabled boards when <strong>Company career pages</strong> is selected (on by default).</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= App::e(JobQuery::jobsHref()) ?>">← Jobs</a>
  </header>

  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">Total</div><div class="h4 mb-0"><?= count($companies) ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">Enabled</div><div class="h4 mb-0"><?= (int) $enabled ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">API boards</div><div class="h4 mb-0"><?= (int) ($byType['greenhouse'] + $byType['personio'] + $byType['smartrecruiters']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">Career sites</div><div class="h4 mb-0"><?= (int) $byType['site'] ?></div></div></div></div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h5 mb-2">Add a company</h2>
      <p class="small text-secondary">Examples: Mercedes → type <code>site</code>, URL <code>https://jobs.mercedes-benz.com/</code>. N26 → type <code>greenhouse</code>, key <code>n26</code>.</p>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="add">
        <div class="col-md-3">
          <label class="form-label" for="name">Company</label>
          <input class="form-control" id="name" name="name" required placeholder="Mercedes-Benz">
        </div>
        <div class="col-md-2">
          <label class="form-label" for="board_type">Type</label>
          <select class="form-select" id="board_type" name="board_type" required>
            <option value="site">Career site URL</option>
            <option value="greenhouse">Greenhouse</option>
            <option value="personio">Personio</option>
            <option value="smartrecruiters">SmartRecruiters</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="board_key">Key / host</label>
          <input class="form-control" id="board_key" name="board_key" required placeholder="jobs.mercedes-benz.com or n26">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="careers_url">Careers URL</label>
          <input class="form-control" id="careers_url" name="careers_url" placeholder="https://jobs.mercedes-benz.com/">
        </div>
        <div class="col-md-1">
          <button type="submit" class="btn btn-primary w-100">Add</button>
        </div>
      </form>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <form method="post"><input type="hidden" name="action" value="seed"><button class="btn btn-outline-primary btn-sm" type="submit">Load Germany catalog (~100)</button></form>
    <form method="post"><input type="hidden" name="action" value="enable_all"><button class="btn btn-outline-secondary btn-sm" type="submit">Enable all</button></form>
    <form method="post"><input type="hidden" name="action" value="disable_sites"><button class="btn btn-outline-secondary btn-sm" type="submit">Disable site boards only</button></form>
  </div>

  <?php if ($companies === []): ?>
    <div class="card shadow-sm"><div class="card-body">No companies yet. Click <strong>Load Germany catalog</strong> or add Mercedes / BMW manually.</div></div>
  <?php else: ?>
    <div class="table-responsive card shadow-sm">
      <table class="table table-sm mb-0 align-middle">
        <thead>
          <tr>
            <th>On</th>
            <th>Company</th>
            <th>Type</th>
            <th>Key / host</th>
            <th>Link</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($companies as $c): ?>
            <tr>
              <td>
                <form method="post" class="m-0">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <input type="hidden" name="enabled" value="<?= $c['enabled'] ? '0' : '1' ?>">
                  <button type="submit" class="btn btn-sm <?= $c['enabled'] ? 'btn-success' : 'btn-outline-secondary' ?>" title="Toggle">
                    <?= $c['enabled'] ? 'On' : 'Off' ?>
                  </button>
                </form>
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
                <form method="post" class="m-0" onsubmit="return confirm('Remove this company?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="small text-secondary mt-3 mb-0">
    Greenhouse / Personio / SmartRecruiters use public APIs (no Bright Data).
    Site boards (Mercedes, BMW, SAP, …) use Google <code>site:</code> search when <code>BRIGHT_DATA_API_TOKEN</code> is set — up to 12 sites per search.
  </p>
</main>
<?php
layout_footer();
