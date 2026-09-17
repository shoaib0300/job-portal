<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

use KaamFit\Interview\InterviewSchema;
use KaamFit\Interview\InterviewTaxonomy;

SuperAdmin::requireLogin();
InterviewSchema::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_industry') {
            InterviewTaxonomy::upsertIndustry(
                (string) ($_POST['slug'] ?? ''),
                (string) ($_POST['name_en'] ?? ''),
                (string) ($_POST['name_de'] ?? ''),
                (int) ($_POST['sort_order'] ?? 0)
            );
            App::flash('Industry saved.');
        } elseif ($action === 'add_occupation') {
            InterviewTaxonomy::upsertOccupation(
                (int) ($_POST['industry_id'] ?? 0),
                (string) ($_POST['slug'] ?? ''),
                (string) ($_POST['name_en'] ?? ''),
                (string) ($_POST['name_de'] ?? '')
            );
            App::flash('Occupation saved.');
        } elseif ($action === 'add_skill') {
            InterviewTaxonomy::upsertSkill(
                (string) ($_POST['slug'] ?? ''),
                (string) ($_POST['name_en'] ?? ''),
                (string) ($_POST['name_de'] ?? ''),
                (string) ($_POST['kind'] ?? 'universal')
            );
            App::flash('Skill saved.');
        } elseif ($action === 'toggle') {
            InterviewTaxonomy::setEnabled(
                (string) ($_POST['table'] ?? ''),
                (int) ($_POST['id'] ?? 0),
                (string) ($_POST['enabled'] ?? '') === '1'
            );
            App::flash('Updated.');
        } elseif ($action === 'reload_taxonomy') {
            $path = dirname(__DIR__, 2) . '/data/interview/v1/taxonomy.json';
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                $s = InterviewTaxonomy::importTaxonomyArray($data);
                App::flash('Taxonomy reloaded: ' . json_encode($s));
            } else {
                App::flash('taxonomy.json unreadable', 'error');
            }
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/super-admin/interview-taxonomy.php');
}

$industries = InterviewTaxonomy::industries(false);
$occupations = InterviewTaxonomy::occupations(null, false);
$skills = InterviewTaxonomy::skills(false);

super_layout_header('Interview taxonomy');
?>
<p class="text-secondary mb-3">Database-driven industries, occupations, and skills. Add rows here — no PHP enum changes.</p>

<form method="post" class="mb-3">
  <input type="hidden" name="action" value="reload_taxonomy">
  <button class="btn btn-outline-secondary btn-sm" type="submit">Reload from taxonomy.json</button>
</form>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Add industry</h2>
        <form method="post" class="vstack gap-2">
          <input type="hidden" name="action" value="add_industry">
          <input class="form-control form-control-sm" name="name_en" placeholder="Name EN" required>
          <input class="form-control form-control-sm" name="name_de" placeholder="Name DE">
          <input class="form-control form-control-sm" name="slug" placeholder="slug (optional)">
          <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Add occupation</h2>
        <form method="post" class="vstack gap-2">
          <input type="hidden" name="action" value="add_occupation">
          <select class="form-select form-select-sm" name="industry_id" required>
            <option value="">Industry…</option>
            <?php foreach ($industries as $ind): ?>
              <option value="<?= (int) $ind['id'] ?>"><?= App::e((string) $ind['name_en']) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="form-control form-control-sm" name="name_en" placeholder="Name EN" required>
          <input class="form-control form-control-sm" name="name_de" placeholder="Name DE">
          <input class="form-control form-control-sm" name="slug" placeholder="slug (optional)">
          <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Add skill</h2>
        <form method="post" class="vstack gap-2">
          <input type="hidden" name="action" value="add_skill">
          <input class="form-control form-control-sm" name="name_en" placeholder="Name EN" required>
          <input class="form-control form-control-sm" name="name_de" placeholder="Name DE">
          <select class="form-select form-select-sm" name="kind">
            <option value="universal">universal</option>
            <option value="domain">domain</option>
          </select>
          <input class="form-control form-control-sm" name="slug" placeholder="slug (optional)">
          <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-4">
    <h2 class="h6 mt-3">Industries (<?= count($industries) ?>)</h2>
    <div class="list-group list-group-flush small">
      <?php foreach ($industries as $ind): ?>
        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
          <span><?= App::e((string) $ind['name_en']) ?> <code><?= App::e((string) $ind['slug']) ?></code></span>
          <form method="post" class="m-0">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="table" value="interview_industries">
            <input type="hidden" name="id" value="<?= (int) $ind['id'] ?>">
            <input type="hidden" name="enabled" value="<?= (int) $ind['enabled'] === 1 ? '0' : '1' ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $ind['enabled'] === 1 ? 'On' : 'Off' ?></button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <h2 class="h6 mt-3">Occupations (<?= count($occupations) ?>)</h2>
    <div class="list-group list-group-flush small" style="max-height:28rem;overflow:auto">
      <?php foreach ($occupations as $occ): ?>
        <div class="list-group-item px-0"><?= App::e((string) $occ['name_en']) ?> <code><?= App::e((string) $occ['slug']) ?></code></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <h2 class="h6 mt-3">Skills (<?= count($skills) ?>)</h2>
    <div class="list-group list-group-flush small" style="max-height:28rem;overflow:auto">
      <?php foreach ($skills as $sk): ?>
        <div class="list-group-item px-0"><?= App::e((string) $sk['name_en']) ?> <span class="text-secondary"><?= App::e((string) $sk['kind']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php
super_layout_footer();
