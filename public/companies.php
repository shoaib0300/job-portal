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
    App::redirect('/companies.php');
}

$shared = CareerCompanies::forUser(0, true);
$mine = CareerCompanies::forUser($uid);
$enabledMine = count(array_filter($mine, static fn(array $c): bool => $c['enabled'] === 1));

layout_header('Companies');
?>
<main class="page-wide">
  <header class="page-head d-flex flex-wrap justify-content-between gap-2 align-items-start">
    <div>
      <h1>Company career boards</h1>
      <p class="mb-0">Shared catalog is managed for everyone. Add personal boards below; Jobs uses enabled shared + your enabled boards when <strong>Company career pages</strong> is on.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= App::e(JobQuery::jobsHref()) ?>">← Jobs</a>
  </header>

  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">Shared (on)</div><div class="h4 mb-0"><?= count($shared) ?></div></div></div></div>
    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">My boards</div><div class="h4 mb-0"><?= count($mine) ?></div></div></div></div>
    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-secondary small">My boards enabled</div><div class="h4 mb-0"><?= (int) $enabledMine ?></div></div></div></div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h5 mb-2">Add my board</h2>
      <p class="small text-secondary">Examples: Mercedes → type <code>site</code>, URL <code>https://jobs.mercedes-benz.com/</code>. N26 → type <code>greenhouse</code>, key <code>n26</code>.</p>
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

  <h2 class="h5 mb-2">Shared catalog</h2>
  <?php if ($shared === []): ?>
    <div class="card shadow-sm mb-4"><div class="card-body text-secondary">No shared companies enabled yet.</div></div>
  <?php else: ?>
    <div class="table-responsive card shadow-sm mb-4">
      <table class="table table-sm mb-0 align-middle">
        <thead>
          <tr>
            <th>Company</th>
            <th>Type</th>
            <th>Key / host</th>
            <th>Link</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($shared as $c): ?>
            <tr>
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
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h2 class="h5 mb-2">My boards</h2>
  <?php if ($mine === []): ?>
    <div class="card shadow-sm"><div class="card-body text-secondary">No personal boards yet. Add one above if you need a company not in the shared list.</div></div>
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
          <?php foreach ($mine as $c): ?>
            <tr>
              <td>
                <form method="post" class="m-0">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <input type="hidden" name="enabled" value="<?= $c['enabled'] ? '0' : '1' ?>">
                  <button type="submit" class="btn btn-sm <?= $c['enabled'] ? 'btn-success' : 'btn-outline-secondary' ?>">
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
                <form method="post" class="m-0" onsubmit="return confirm('Remove this personal board?');">
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
    Greenhouse / Personio / SmartRecruiters use public APIs.
    Site boards use Google <code>site:</code> search when <code>BRIGHT_DATA_API_TOKEN</code> is set.
  </p>
</main>
<?php
layout_footer();
