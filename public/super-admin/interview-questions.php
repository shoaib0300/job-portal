<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

use KaamFit\Interview\InterviewMarkdown;
use KaamFit\Interview\InterviewQuestionRepo;
use KaamFit\Interview\InterviewSchema;
use KaamFit\Interview\InterviewTaxonomy;

SuperAdmin::requireLogin();
InterviewSchema::ensureSchema();
$adminId = (int) (SuperAdmin::admin()['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::requireValid();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $data = [
                'id' => $id > 0 ? $id : null,
                'slug' => (string) ($_POST['slug'] ?? ''),
                'language' => (string) ($_POST['language'] ?? 'en'),
                'question' => (string) ($_POST['question_text'] ?? ''),
                'why_asked' => (string) ($_POST['why_asked'] ?? ''),
                'strong_answer_covers' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\n/', (string) ($_POST['strong_answer_covers'] ?? '')) ?: []))),
                'short_answer' => (string) ($_POST['short_answer'] ?? ''),
                'detailed_answer' => (string) ($_POST['detailed_answer'] ?? ''),
                'example_answer' => (string) ($_POST['example_answer'] ?? ''),
                'answer_framework' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\n/', (string) ($_POST['answer_framework'] ?? '')) ?: []))),
                'common_mistakes' => (string) ($_POST['common_mistakes'] ?? ''),
                'follow_ups' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\n/', (string) ($_POST['follow_ups'] ?? '')) ?: []))),
                'related_concepts' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\n/', (string) ($_POST['related_concepts'] ?? '')) ?: []))),
                'question_type' => (string) ($_POST['question_type'] ?? 'general'),
                'difficulty' => (string) ($_POST['difficulty'] ?? 'medium'),
                'career_level' => (string) ($_POST['career_level'] ?? ''),
                'category' => (string) ($_POST['category'] ?? ''),
                'visibility' => (string) ($_POST['visibility'] ?? 'universal'),
                'is_universal' => (($_POST['visibility'] ?? '') === 'universal') ? 1 : 0,
                'status' => (string) ($_POST['status'] ?? 'published'),
                'review_status' => (string) ($_POST['review_status'] ?? 'none'),
                'source_type' => (string) ($_POST['source_type'] ?? 'kaamfit_original'),
                'source_name' => (string) ($_POST['source_name'] ?? 'KaamFit'),
                'source_url' => (string) ($_POST['source_url'] ?? ''),
                'license' => (string) ($_POST['license'] ?? 'proprietary'),
                'attribution' => (string) ($_POST['attribution'] ?? ''),
                'diagram_path' => (string) ($_POST['diagram_path'] ?? ''),
                'diagram_alt' => (string) ($_POST['diagram_alt'] ?? ''),
                'diagram_caption' => (string) ($_POST['diagram_caption'] ?? ''),
                'diagram_license' => (string) ($_POST['diagram_license'] ?? ''),
                'diagram_source_url' => (string) ($_POST['diagram_source_url'] ?? ''),
                'updated_by' => $adminId,
                'industries' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['industries'] ?? ''))))),
                'occupations' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['occupations'] ?? ''))))),
                'specializations' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['specializations'] ?? ''))))),
                'skills' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['skills'] ?? ''))))),
                'technologies' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['technologies'] ?? ''))))),
                'stages' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['stages'] ?? ''))))),
            ];
            if ($id < 1) {
                $data['created_by'] = $adminId;
                if (($data['visibility'] ?? '') === 'universal') {
                    $data['status'] = $data['status'] ?: 'published';
                    $data['source_type'] = $data['source_type'] ?: 'kaamfit_original';
                }
            } elseif ($data['slug'] === '') {
                $data['slug'] = (string) (InterviewQuestionRepo::find($id)['slug'] ?? '');
            }
            $result = InterviewQuestionRepo::upsert($data, [], true);
            App::flash('Question ' . $result['action'] . ' (#' . $result['id'] . ').');
            App::redirect('/super-admin/interview-questions.php?view=' . $result['id']);
        }
        if ($action === 'archive') {
            InterviewQuestionRepo::archive((int) ($_POST['id'] ?? 0), $adminId);
            App::flash('Question archived.');
        }
        if ($action === 'restore') {
            InterviewQuestionRepo::restore((int) ($_POST['id'] ?? 0), $adminId);
            App::flash('Question restored.');
        }
        if ($action === 'bulk') {
            $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
            $bulk = (string) ($_POST['bulk_action'] ?? '');
            if ($bulk === 'archive') {
                InterviewQuestionRepo::bulkUpdate($ids, ['status' => 'archived'], $adminId);
                App::flash('Archived ' . count($ids) . ' questions.');
            } elseif ($bulk === 'activate') {
                InterviewQuestionRepo::bulkUpdate($ids, ['status' => 'published', 'visibility' => 'universal'], $adminId);
                App::flash('Activated ' . count($ids) . ' questions.');
            } elseif ($bulk === 'approve') {
                InterviewQuestionRepo::bulkUpdate($ids, ['status' => 'published', 'review_status' => 'approved', 'visibility' => 'universal'], $adminId);
                App::flash('Approved ' . count($ids) . ' questions.');
            } elseif ($bulk === 'reject') {
                InterviewQuestionRepo::bulkUpdate($ids, ['status' => 'rejected', 'review_status' => 'rejected'], $adminId);
                App::flash('Rejected ' . count($ids) . ' questions.');
            } elseif ($bulk === 'set_difficulty') {
                InterviewQuestionRepo::bulkUpdate($ids, ['difficulty' => (string) ($_POST['bulk_difficulty'] ?? 'medium')], $adminId);
                App::flash('Updated difficulty.');
            } elseif ($bulk === 'set_language') {
                InterviewQuestionRepo::bulkUpdate($ids, ['language' => (string) ($_POST['bulk_language'] ?? 'en')], $adminId);
                App::flash('Updated language.');
            } elseif ($bulk === 'set_industry') {
                $slug = trim((string) ($_POST['bulk_industry'] ?? ''));
                if ($slug !== '') {
                    InterviewQuestionRepo::bulkSetTaxonomy($ids, [$slug], []);
                }
                App::flash('Updated industry tags.');
            } elseif ($bulk === 'set_occupation') {
                $slug = trim((string) ($_POST['bulk_occupation'] ?? ''));
                if ($slug !== '') {
                    InterviewQuestionRepo::bulkSetTaxonomy($ids, [], [$slug]);
                }
                App::flash('Updated occupation tags.');
            }
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    $qs = $_SERVER['HTTP_REFERER'] ?? '/super-admin/interview-questions.php';
    App::redirect(str_contains($qs, 'interview-questions.php') ? $qs : '/super-admin/interview-questions.php');
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => (string) ($_GET['status'] ?? 'any'),
    'visibility' => (string) ($_GET['visibility'] ?? 'any'),
    'industry' => trim((string) ($_GET['industry'] ?? '')),
    'occupation' => trim((string) ($_GET['occupation'] ?? '')),
    'specialization' => trim((string) ($_GET['specialization'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'type' => trim((string) ($_GET['type'] ?? '')),
    'difficulty' => trim((string) ($_GET['difficulty'] ?? '')),
    'level' => trim((string) ($_GET['level'] ?? '')),
    'stage' => trim((string) ($_GET['stage'] ?? '')),
    'language' => trim((string) ($_GET['language'] ?? '')),
    'source_type' => trim((string) ($_GET['source_type'] ?? '')),
    'review_status' => trim((string) ($_GET['review_status'] ?? 'any')),
    'has_answer' => trim((string) ($_GET['has_answer'] ?? '')),
    'skill' => trim((string) ($_GET['skill'] ?? '')),
    'admin' => true,
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'per_page' => 40,
];
$result = InterviewQuestionRepo::search($filters);
$viewId = (int) ($_GET['view'] ?? 0);
$editId = (int) ($_GET['edit'] ?? 0);
$creating = isset($_GET['create']);
$edit = null;
$view = null;
if ($creating) {
    $edit = [];
} elseif ($editId > 0) {
    $edit = InterviewQuestionRepo::find($editId);
}
if ($viewId > 0) {
    $view = InterviewQuestionRepo::find($viewId);
    $usage = $view ? InterviewQuestionRepo::usageStats($viewId) : null;
}
$industries = InterviewTaxonomy::industries(false);
$cascade = InterviewTaxonomy::cascadePayload(
    $filters['industry'] !== '' ? $filters['industry'] : null,
    $filters['occupation'] !== '' ? $filters['occupation'] : null,
    $filters['specialization'] !== '' ? $filters['specialization'] : null
);

$qsKeep = static function (array $extra = []) use ($filters): string {
    $base = array_filter([
        'q' => $filters['q'],
        'status' => $filters['status'] !== 'any' ? $filters['status'] : null,
        'visibility' => $filters['visibility'] !== 'any' ? $filters['visibility'] : null,
        'industry' => $filters['industry'] ?: null,
        'occupation' => $filters['occupation'] ?: null,
        'specialization' => $filters['specialization'] ?: null,
        'type' => $filters['type'] ?: null,
        'difficulty' => $filters['difficulty'] ?: null,
        'level' => $filters['level'] ?: null,
        'stage' => $filters['stage'] ?: null,
        'language' => $filters['language'] ?: null,
        'source_type' => $filters['source_type'] ?: null,
        'review_status' => $filters['review_status'] !== 'any' ? $filters['review_status'] : null,
        'has_answer' => $filters['has_answer'] ?: null,
        'skill' => $filters['skill'] ?: null,
    ], static fn($v) => $v !== null && $v !== '');
    return http_build_query(array_merge($base, $extra));
};

$lines = static function (mixed $v): string {
    if (is_array($v)) {
        return implode("\n", array_map(static fn($x) => is_string($x) ? $x : (string) json_encode($x), $v));
    }
    return is_string($v) ? $v : '';
};

super_layout_header($creating || $edit ? ($creating ? 'Create question' : 'Edit question') : ($view ? 'Question #' . $viewId : 'Interview questions'));
?>
<p class="mb-3">
  <a class="btn btn-sm btn-primary" href="?create=1">+ Create Question</a>
  <a class="btn btn-sm btn-outline-secondary" href="/super-admin/interview-review.php">Review queue</a>
  <a class="btn btn-sm btn-outline-secondary" href="/super-admin/interview-taxonomy.php">Taxonomy</a>
</p>

<?php if ($view && !$edit): ?>
  <?php $tags = $view['tags'] ?? []; ?>
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between flex-wrap gap-2">
        <h2 class="h5 mb-0">Interview Question #<?= (int) $view['id'] ?></h2>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $view['id'] ?>">Edit</a>
          <?php if (($view['status'] ?? '') === 'archived'): ?>
            <form method="post" class="d-inline"><?= Csrf::field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><button class="btn btn-sm btn-outline-success" type="submit">Restore</button></form>
          <?php else: ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Archive this question?');"><?= Csrf::field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Archive</button></form>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline-secondary" href="?<?= App::e($qsKeep()) ?>">Back to list</a>
        </div>
      </div>
      <p class="mt-3 fw-semibold"><?= App::e((string) $view['question_text']) ?></p>
      <?php if (!InterviewMarkdown::isWeakFiller($view['short_answer'] ?? null)): ?>
        <h3 class="h6">Short answer</h3>
        <div class="mb-2"><?= InterviewMarkdown::render((string) $view['short_answer']) ?></div>
      <?php endif; ?>
      <?php
        $detail = (string) ($view['detailed_answer'] ?? '');
        if ($detail === '') {
            $detail = (string) ($view['example_answer'] ?? '');
        }
      ?>
      <?php if (!InterviewMarkdown::isWeakFiller($detail)): ?>
        <h3 class="h6">Detailed answer</h3>
        <div class="mb-2"><?= InterviewMarkdown::render($detail) ?></div>
      <?php endif; ?>
      <h3 class="h6 mt-3">Classification</h3>
      <ul class="small">
        <li>Industry: <?= App::e(implode(', ', array_column($tags['industries'] ?? [], 'name_en')) ?: '—') ?></li>
        <li>Occupation: <?= App::e(implode(', ', array_column($tags['occupations'] ?? [], 'name_en')) ?: '—') ?></li>
        <li>Type: <?= App::e((string) $view['question_type']) ?> · Difficulty: <?= App::e((string) $view['difficulty']) ?></li>
        <li>Language: <?= App::e((string) $view['language']) ?> · Visibility: <?= App::e((string) ($view['visibility'] ?? '')) ?> · Status: <?= App::e((string) $view['status']) ?></li>
        <li>Source: <?= App::e((string) $view['source_type']) ?> / <?= App::e((string) $view['source_name']) ?></li>
        <li>Review: <?= App::e((string) ($view['review_status'] ?? 'none')) ?></li>
      </ul>
      <?php if ($usage): ?>
        <h3 class="h6">Usage</h3>
        <p class="small mb-0">Users linked: <?= (int) $usage['linked_users'] ?> · Practiced: <?= (int) $usage['practiced'] ?> · Favorites: <?= (int) $usage['favorites'] ?></p>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($edit !== null): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h2 class="h5"><?= $creating ? 'New question' : 'Edit #' . (int) ($edit['id'] ?? 0) ?></h2>
      <form method="post" class="row g-2" id="q-edit-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="col-md-8"><textarea class="form-control" name="question_text" rows="3" required placeholder="Question"><?= App::e((string) ($edit['question_text'] ?? '')) ?></textarea></div>
        <div class="col-md-4">
          <input class="form-control form-control-sm mb-1" name="slug" value="<?= App::e((string) ($edit['slug'] ?? '')) ?>" placeholder="slug (optional)">
          <select class="form-select form-select-sm mb-1" name="language"><?php foreach (['en','de'] as $l): ?><option value="<?= $l ?>"<?= (($edit['language'] ?? 'en') === $l) ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
          <select class="form-select form-select-sm mb-1" name="visibility"><?php foreach (InterviewSchema::visibilities() as $v): ?><option value="<?= $v ?>"<?= (($edit['visibility'] ?? 'universal') === $v) ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
          <select class="form-select form-select-sm" name="status"><?php foreach (InterviewSchema::statuses() as $s): ?><option value="<?= $s ?>"<?= (($edit['status'] ?? 'published') === $s) ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-md-6"><label class="form-label small">Why asked</label><textarea class="form-control form-control-sm" name="why_asked" rows="2"><?= App::e((string) ($edit['why_asked'] ?? '')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label small">Key points (one per line)</label><textarea class="form-control form-control-sm" name="strong_answer_covers" rows="2"><?= App::e($lines($edit['strong_answer_covers'] ?? '')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label small">Short answer</label><textarea class="form-control form-control-sm" name="short_answer" rows="3"><?= App::e((string) ($edit['short_answer'] ?? '')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label small">Detailed answer (Markdown)</label><textarea class="form-control form-control-sm" name="detailed_answer" rows="3" id="detailed_answer"><?= App::e((string) ($edit['detailed_answer'] ?? $edit['example_answer'] ?? '')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label small">Example answer (legacy)</label><textarea class="form-control form-control-sm" name="example_answer" rows="2"><?= App::e((string) ($edit['example_answer'] ?? '')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label small">Answer framework (one per line)</label><textarea class="form-control form-control-sm" name="answer_framework" rows="2"><?= App::e($lines($edit['answer_framework'] ?? '')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label small">Common mistakes</label><textarea class="form-control form-control-sm" name="common_mistakes" rows="2"><?= App::e($lines($edit['common_mistakes'] ?? '')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label small">Follow-ups (one per line)</label><textarea class="form-control form-control-sm" name="follow_ups" rows="2"><?= App::e($lines($edit['follow_ups'] ?? '')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label small">Related concepts (one per line)</label><textarea class="form-control form-control-sm" name="related_concepts" rows="2"><?= App::e($lines($edit['related_concepts'] ?? '')) ?></textarea></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="question_type"><?php foreach (InterviewSchema::questionTypes() as $t): ?><option value="<?= $t ?>"<?= (($edit['question_type'] ?? '') === $t) ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="difficulty"><?php foreach (InterviewSchema::difficulties() as $d): ?><option value="<?= $d ?>"<?= (($edit['difficulty'] ?? 'medium') === $d) ? ' selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="career_level"><option value="">Career level</option><?php foreach (InterviewSchema::careerLevels() as $lv): ?><option value="<?= $lv ?>"<?= (($edit['career_level'] ?? '') === $lv) ? ' selected' : '' ?>><?= $lv ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="category" value="<?= App::e((string) ($edit['category'] ?? '')) ?>" placeholder="category"></div>
        <div class="col-md-4"><input class="form-control form-control-sm" name="industries" value="<?= App::e(isset($edit['tags']['industries']) ? implode(',', array_column($edit['tags']['industries'], 'slug')) : '') ?>" placeholder="industry slugs"></div>
        <div class="col-md-4"><input class="form-control form-control-sm" name="occupations" value="<?= App::e(isset($edit['tags']['occupations']) ? implode(',', array_column($edit['tags']['occupations'], 'slug')) : '') ?>" placeholder="occupation slugs"></div>
        <div class="col-md-4"><input class="form-control form-control-sm" name="specializations" value="<?= App::e(isset($edit['tags']['specializations']) ? implode(',', array_column($edit['tags']['specializations'], 'slug')) : '') ?>" placeholder="specialization slugs"></div>
        <div class="col-md-4"><input class="form-control form-control-sm" name="skills" value="<?= App::e(isset($edit['tags']['skills']) ? implode(',', array_column($edit['tags']['skills'], 'slug')) : '') ?>" placeholder="skill slugs"></div>
        <div class="col-md-4"><input class="form-control form-control-sm" name="technologies" value="<?= App::e(isset($edit['tags']['technologies']) ? implode(',', array_column($edit['tags']['technologies'], 'slug')) : '') ?>" placeholder="technology slugs"></div>
        <div class="col-md-4"><input class="form-control form-control-sm" name="stages" value="<?= App::e(isset($edit['tags']['stages']) ? implode(',', array_column($edit['tags']['stages'], 'slug')) : '') ?>" placeholder="stage slugs"></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="source_type"><?php foreach (InterviewSchema::sourceTypes() as $st): ?><option value="<?= $st ?>"<?= (($edit['source_type'] ?? 'kaamfit_original') === $st) ? ' selected' : '' ?>><?= $st ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="source_name" value="<?= App::e((string) ($edit['source_name'] ?? 'KaamFit')) ?>" placeholder="source name"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="source_url" value="<?= App::e((string) ($edit['source_url'] ?? '')) ?>" placeholder="source URL"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="license" value="<?= App::e((string) ($edit['license'] ?? 'proprietary')) ?>" placeholder="license"></div>
        <div class="col-md-6"><input class="form-control form-control-sm" name="attribution" value="<?= App::e((string) ($edit['attribution'] ?? '')) ?>" placeholder="attribution"></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="review_status"><?php foreach (InterviewSchema::reviewStatuses() as $rs): ?><option value="<?= $rs ?>"<?= (($edit['review_status'] ?? 'none') === $rs) ? ' selected' : '' ?>><?= $rs ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="diagram_path" value="<?= App::e((string) ($edit['diagram_path'] ?? '')) ?>" placeholder="diagram path"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="diagram_alt" value="<?= App::e((string) ($edit['diagram_alt'] ?? '')) ?>" placeholder="diagram alt"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="diagram_caption" value="<?= App::e((string) ($edit['diagram_caption'] ?? '')) ?>" placeholder="diagram caption"></div>
        <div class="col-12">
          <button class="btn btn-primary btn-sm" type="submit">Save</button>
          <button class="btn btn-outline-secondary btn-sm" type="button" id="preview-btn">Preview answer</button>
          <a class="btn btn-outline-secondary btn-sm" href="/super-admin/interview-questions.php">Cancel</a>
        </div>
        <div class="col-12 d-none" id="preview-box"><div class="border rounded p-2 bg-light small" id="preview-html"></div></div>
      </form>
    </div>
  </div>
  <script>
  document.getElementById('preview-btn')?.addEventListener('click', () => {
    const t = document.getElementById('detailed_answer')?.value || '';
    const box = document.getElementById('preview-box');
    const html = document.getElementById('preview-html');
    if (!box || !html) return;
    box.classList.remove('d-none');
    // Lightweight client preview: escape + newlines
    html.textContent = t;
    html.style.whiteSpace = 'pre-wrap';
  });
  </script>
<?php endif; ?>

<form class="row g-2 mb-3" method="get" id="admin-q-filters">
  <div class="col-md-3"><input class="form-control form-control-sm" name="q" value="<?= App::e($filters['q']) ?>" placeholder="Search questions..."></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="status"><option value="any">Status: All</option><?php foreach (InterviewSchema::statuses() as $s): ?><option value="<?= $s ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="visibility"><option value="any">Visibility: All</option><?php foreach (InterviewSchema::visibilities() as $v): ?><option value="<?= $v ?>"<?= $filters['visibility'] === $v ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="review_status"><option value="any">Review: All</option><?php foreach (InterviewSchema::reviewStatuses() as $rs): ?><option value="<?= $rs ?>"<?= $filters['review_status'] === $rs ? ' selected' : '' ?>><?= $rs ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select class="form-select form-select-sm" name="industry" id="f-industry"><option value="">Industry: All</option><?php foreach ($industries as $ind): ?><option value="<?= App::e((string)$ind['slug']) ?>"<?= $filters['industry']===$ind['slug']?' selected':'' ?>><?= App::e((string)$ind['name_en']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select class="form-select form-select-sm" name="occupation" id="f-occupation"><option value="">Occupation: All</option><?php foreach ($cascade['occupations'] as $o): ?><option value="<?= App::e($o['slug']) ?>"<?= $filters['occupation']===$o['slug']?' selected':'' ?>><?= App::e($o['name_en']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select class="form-select form-select-sm" name="specialization" id="f-specialization"><option value="">Specialization: All</option><?php foreach ($cascade['specializations'] as $s): ?><option value="<?= App::e($s['slug']) ?>"<?= $filters['specialization']===$s['slug']?' selected':'' ?>><?= App::e($s['name_en']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="skill" id="f-skill"><option value="">Skill: All</option><?php foreach ($cascade['skills'] as $sk): ?><option value="<?= App::e($sk['slug']) ?>"<?= $filters['skill']===$sk['slug']?' selected':'' ?>><?= App::e($sk['name_en']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="type"><option value="">Type</option><?php foreach (InterviewSchema::questionTypes() as $t): ?><option value="<?= $t ?>"<?= $filters['type']===$t?' selected':'' ?>><?= $t ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="difficulty"><option value="">Difficulty</option><?php foreach (InterviewSchema::difficulties() as $d): ?><option value="<?= $d ?>"<?= $filters['difficulty']===$d?' selected':'' ?>><?= $d ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="level"><option value="">Level</option><?php foreach (InterviewSchema::careerLevels() as $lv): ?><option value="<?= $lv ?>"<?= $filters['level']===$lv?' selected':'' ?>><?= $lv ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="stage"><option value="">Stage</option><?php foreach (InterviewTaxonomy::stages(false) as $st): ?><option value="<?= App::e((string)$st['slug']) ?>"<?= $filters['stage']===$st['slug']?' selected':'' ?>><?= App::e((string)$st['name_en']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-1"><select class="form-select form-select-sm" name="language"><option value="">Lang</option><?php foreach (['en','de'] as $l): ?><option value="<?= $l ?>"<?= $filters['language']===$l?' selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="source_type"><option value="">Source</option><?php foreach (InterviewSchema::sourceTypes() as $st): ?><option value="<?= $st ?>"<?= $filters['source_type']===$st?' selected':'' ?>><?= $st ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="has_answer"><option value="">Has answer</option><option value="yes"<?= $filters['has_answer']==='yes'?' selected':'' ?>>Yes</option><option value="no"<?= $filters['has_answer']==='no'?' selected':'' ?>>No</option></select></div>
  <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary" type="submit">Search</button><a class="btn btn-sm btn-outline-secondary" href="?">Reset</a></div>
</form>

<?php
$from = $result['total'] === 0 ? 0 : (($result['page'] - 1) * $result['per_page'] + 1);
$to = min($result['total'], $result['page'] * $result['per_page']);
?>
<p class="small text-secondary">Questions <?= $from ?>–<?= $to ?> of <?= (int) $result['total'] ?></p>

<form method="post">
  <?= Csrf::field() ?>
  <input type="hidden" name="action" value="bulk">
  <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
    <select class="form-select form-select-sm w-auto" name="bulk_action">
      <option value="">Bulk action…</option>
      <option value="approve">Approve</option>
      <option value="reject">Reject</option>
      <option value="archive">Archive</option>
      <option value="activate">Activate / publish</option>
      <option value="set_difficulty">Set difficulty</option>
      <option value="set_language">Set language</option>
      <option value="set_industry">Set industry</option>
      <option value="set_occupation">Set occupation</option>
    </select>
    <select class="form-select form-select-sm w-auto" name="bulk_difficulty"><?php foreach (InterviewSchema::difficulties() as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?></select>
    <select class="form-select form-select-sm w-auto" name="bulk_language"><option value="en">en</option><option value="de">de</option></select>
    <input class="form-control form-control-sm w-auto" name="bulk_industry" placeholder="industry slug">
    <input class="form-control form-control-sm w-auto" name="bulk_occupation" placeholder="occupation slug">
    <button class="btn btn-sm btn-outline-primary" type="submit" onclick="return confirm('Apply bulk action to selected?');">Apply</button>
  </div>
  <div class="table-responsive shadow-sm bg-white rounded">
    <table class="table table-sm table-hover mb-0 align-middle">
      <thead><tr>
        <th><input type="checkbox" onclick="document.querySelectorAll('.qcb').forEach(c=>c.checked=this.checked)"></th>
        <th>ID</th><th>Question</th><th>Type</th><th>Lang</th><th>Visibility</th><th>Status</th><th>Review</th><th>Updated</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($result['items'] as $item): ?>
        <tr>
          <td><input class="qcb" type="checkbox" name="ids[]" value="<?= (int)$item['id'] ?>"></td>
          <td class="small"><?= (int)$item['id'] ?></td>
          <td class="small" style="max-width:28rem"><?= App::e(mb_substr((string)$item['question_text'], 0, 120)) ?></td>
          <td class="small"><?= App::e((string)$item['question_type']) ?></td>
          <td class="small"><?= App::e((string)$item['language']) ?></td>
          <td class="small"><?= App::e((string)($item['visibility'] ?? '')) ?></td>
          <td><span class="badge text-bg-light"><?= App::e((string)$item['status']) ?></span></td>
          <td class="small"><?= App::e((string)($item['review_status'] ?? '')) ?></td>
          <td class="small"><?= App::e(substr((string)($item['updated_at'] ?? ''), 0, 10)) ?></td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-link py-0" href="?view=<?= (int)$item['id'] ?>&amp;<?= App::e($qsKeep()) ?>">View</a>
            <a class="btn btn-sm btn-link py-0" href="?edit=<?= (int)$item['id'] ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</form>

<?php if ($result['total'] > $result['per_page']): ?>
  <div class="mt-2 d-flex gap-2">
    <?php if ($result['page'] > 1): ?><a class="btn btn-sm btn-outline-secondary" href="?<?= App::e($qsKeep(['page' => $result['page'] - 1])) ?>">Previous</a><?php endif; ?>
    <?php if ($result['page'] * $result['per_page'] < $result['total']): ?><a class="btn btn-sm btn-outline-secondary" href="?<?= App::e($qsKeep(['page' => $result['page'] + 1])) ?>">Next</a><?php endif; ?>
  </div>
<?php endif; ?>

<script src="/assets/js/interview-cascade.js?v=1"></script>
<script>window.InterviewCascade?.bind({industry:'#f-industry',occupation:'#f-occupation',specialization:'#f-specialization',skill:'#f-skill',endpoint:'/interview-prep-taxonomy.php'});</script>
<?php
super_layout_footer();
