<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

use KaamMilo\Jobs\JobIngestLog;
use KaamMilo\Jobs\JobStore;
use KaamMilo\Jobs\JobsIngest;

SuperAdmin::requireLogin();
JobStore::ensureSchema();
JobIngestLog::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'ingest_now') {
            $started = JobsIngest::startBackground(null, 'admin');
            App::flash(
                'Job fetch started. Refresh in a few minutes to see new/updated counts in the log.'
                . ($started['pid'] ? ' (pid ' . $started['pid'] . ')' : '')
            );
        } elseif ($action === 'purge') {
            $days = (int) ($_POST['days'] ?? 0);
            if ($days === 0) {
                $n = JobStore::purgeAll();
                App::flash("Deleted all {$n} jobs.");
            } else {
                $days = max(1, min(3650, $days));
                $n = JobStore::purgeOlderThanDays($days);
                App::flash("Deleted {$n} jobs older than {$days} days.");
            }
        } elseif ($action === 'auto_delete') {
            $days = max(1, min(365, (int) ($_POST['auto_delete_days'] ?? JobsIngest::DEFAULT_AUTO_DELETE_DAYS)));
            App::setSetting(JobsIngest::SETTING_AUTO_DELETE_DAYS, (string) $days);
            App::flash("Jobs older than {$days} days will be removed automatically after each fetch.");
        } elseif ($action === 'clear_logs') {
            $n = (int) Db::pdo()->exec('DELETE FROM job_ingest_logs');
            App::flash("Cleared {$n} log rows.");
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/super-admin/jobs.php');
}

$total = JobStore::count();
$bySource = JobStore::countsBySource();
$autoDays = JobsIngest::autoDeleteDays();
$lastRun = App::setting(JobsIngest::SETTING_LAST_RUN, '') ?? '';
$runLogs = JobIngestLog::recent(25);
$ingestKey = trim((string) (App::setting('jobs_ingest_key', '') ?? ''));
if ($ingestKey === '') {
    $ingestKey = trim((string) (getenv('JOBS_INGEST_KEY') ?: ''));
}
$ingestUrl = $ingestKey !== ''
    ? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'kaammilo.ddev.site') . '/cron/jobs-ingest?key=' . rawurlencode($ingestKey)
    : '';
$hasBrightData = trim((string) (getenv('BRIGHT_DATA_API_TOKEN') ?: '')) !== '';

super_layout_header('Jobs');
?>
<?php if (!$hasBrightData): ?>
  <div class="alert alert-warning">
    <p class="mb-1"><strong>Glassdoor / Indeed / StepStone / XING</strong> need <code>BRIGHT_DATA_API_TOKEN</code> — without it they are empty.</p>
    <p class="mb-1"><strong>Jobware</strong> blocks our server IP (403); Unlocker fallback also needs that token.</p>
    <p class="mb-0"><strong>Jobexport</strong> works without an API. We crawl the newest pages of stellenboerse (and keep last 14 days).</p>
  </div>
<?php else: ?>
  <div class="alert alert-light border small">
    Bright Data token is set — SERP boards (Indeed, StepStone, XING, Glassdoor) and Jobware Unlocker can run on fetch.
  </div>
<?php endif; ?>
<div class="card shadow-sm border-success mb-4">
  <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h2 class="h4 mb-1">Fetch jobs now</h2>
      <p class="text-secondary mb-0">Downloads jobs from LinkedIn, Arbeitsagentur, career sites, etc. into the database. Dashboard users then search this list (fast, no internet).</p>
    </div>
    <form method="post" class="m-0">
      <input type="hidden" name="action" value="ingest_now">
      <button class="btn btn-success btn-lg px-4" type="submit">Fetch all jobs</button>
    </form>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-secondary small">Jobs stored</div>
      <div class="display-6"><?= (int) $total ?></div>
    </div></div>
  </div>
  <div class="col-sm-4">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-secondary small">Last fetch</div>
      <div class="fs-5"><?= App::e($lastRun !== '' ? $lastRun : 'Never') ?></div>
    </div></div>
  </div>
  <div class="col-sm-4">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-secondary small">Auto-delete older than</div>
      <form method="post" class="d-flex gap-2 align-items-center mt-1">
        <input type="hidden" name="action" value="auto_delete">
        <input class="form-control" style="max-width:5rem" type="number" name="auto_delete_days"
               min="1" max="365" value="<?= (int) $autoDays ?>">
        <span class="text-secondary">days</span>
        <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
      </form>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h2 class="h5 mb-3">Where jobs came from</h2>
        <?php if ($bySource === []): ?>
          <p class="text-secondary mb-0">Empty — click <strong>Fetch all jobs</strong>.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($bySource as $row): ?>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span><?= App::e($row['source']) ?></span>
                <strong><?= (int) $row['cnt'] ?></strong>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 mb-0">Fetch history</h2>
          <?php if ($runLogs !== []): ?>
            <form method="post" class="m-0" onsubmit="return confirm('Clear history?');">
              <input type="hidden" name="action" value="clear_logs">
              <button class="btn btn-sm btn-link text-secondary" type="submit">Clear</button>
            </form>
          <?php endif; ?>
        </div>
        <?php if ($runLogs === []): ?>
          <p class="text-secondary mb-0">No fetches yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>When</th>
                  <th class="text-end text-success">New</th>
                  <th class="text-end">Updated</th>
                  <th>From</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($runLogs as $run): ?>
                  <?php
                  $srcMap = is_array($run['by_source'] ?? null) ? $run['by_source'] : [];
                  $bits = [];
                  foreach ($srcMap as $src => $c) {
                      if (!is_array($c)) {
                          continue;
                      }
                      $new = (int) ($c['inserted'] ?? 0);
                      $upd = (int) ($c['updated'] ?? 0);
                      if ($new + $upd === 0) {
                          continue;
                      }
                      $bits[] = App::e((string) $src) . ' +' . $new;
                  }
                  ?>
                  <tr>
                    <td class="small text-nowrap"><?= App::e((string) ($run['finished_at'] ?? '')) ?></td>
                    <td class="text-end fw-semibold text-success"><?= (int) ($run['inserted_count'] ?? 0) ?></td>
                    <td class="text-end"><?= (int) ($run['updated_count'] ?? 0) ?></td>
                    <td class="small"><?= $bits !== [] ? implode(', ', $bits) : '—' ?></td>
                    <td class="text-end"><?= (int) ($run['total_after'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <h2 class="h5 mb-2">Delete old jobs</h2>
    <p class="small text-secondary">Remove stale listings from the database.</p>
    <div class="d-flex flex-wrap gap-2">
      <form method="post" onsubmit="return confirm('Delete jobs older than 14 days?');">
        <input type="hidden" name="action" value="purge">
        <input type="hidden" name="days" value="14">
        <button class="btn btn-outline-danger" type="submit">Older than 14 days</button>
      </form>
      <form method="post" onsubmit="return confirm('Delete jobs older than 30 days?');">
        <input type="hidden" name="action" value="purge">
        <input type="hidden" name="days" value="30">
        <button class="btn btn-outline-danger" type="submit">Older than 30 days</button>
      </form>
      <form method="post" onsubmit="return confirm('Delete ALL jobs?');">
        <input type="hidden" name="action" value="purge">
        <input type="hidden" name="days" value="0">
        <button class="btn btn-danger" type="submit">Delete all</button>
      </form>
    </div>
  </div>
</div>

<?php if ($ingestUrl !== ''): ?>
<details class="mb-3">
  <summary class="text-secondary small" style="cursor:pointer">Advanced: automatic / URL trigger</summary>
  <div class="card shadow-sm mt-2"><div class="card-body small">
    <p class="mb-2">DDEV also fetches every 2 hours while running. Or open this URL (bookmark / crontab):</p>
    <code class="user-select-all"><?= App::e($ingestUrl) ?></code>
  </div></div>
</details>
<?php endif; ?>
<?php
super_layout_footer();
