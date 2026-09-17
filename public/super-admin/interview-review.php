<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/super_layout.php';

use KaamFit\Interview\InterviewQuestionRepo;
use KaamFit\Interview\InterviewReview;
use KaamFit\Interview\InterviewSchema;

SuperAdmin::requireLogin();
InterviewSchema::ensureSchema();
$adminId = (int) (SuperAdmin::admin()['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::requireValid();
        $action = (string) ($_POST['action'] ?? '');
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if ($action === 'approve') {
            InterviewReview::approve($reviewId, $adminId, [
                'example_answer' => (string) ($_POST['example_answer'] ?? ''),
                'question_type' => (string) ($_POST['question_type'] ?? ''),
                'difficulty' => (string) ($_POST['difficulty'] ?? ''),
                'admin_notes' => (string) ($_POST['admin_notes'] ?? ''),
                'industries' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['industries'] ?? ''))))),
                'occupations' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['occupations'] ?? ''))))),
                'skills' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['skills'] ?? ''))))),
            ]);
            App::flash('Approved and promoted to universal.');
        } elseif ($action === 'reject') {
            InterviewReview::reject($reviewId, $adminId, (string) ($_POST['admin_notes'] ?? ''));
            App::flash('Rejected (personal copy retained for importer).');
        } elseif ($action === 'merge') {
            InterviewReview::merge($reviewId, (int) ($_POST['canonical_id'] ?? 0), $adminId, (string) ($_POST['admin_notes'] ?? ''));
            App::flash('Merged into canonical question.');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/super-admin/interview-review.php');
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$result = InterviewReview::pending($page, 30);

super_layout_header('Interview review queue');
?>
<p class="text-secondary mb-3">Pending imported questions waiting for verification before becoming universal.</p>
<p class="fw-semibold">Pending review: <?= (int) $result['total'] ?></p>

<?php if ($result['items'] === []): ?>
  <p class="text-secondary">Queue is empty.</p>
<?php endif; ?>

<?php foreach ($result['items'] as $item): ?>
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
        <div class="small text-secondary">
          Review #<?= (int) $item['id'] ?> · Question #<?= (int) $item['question_id'] ?> ·
          Imported by user #<?= (int) $item['submitted_by'] ?> ·
          <?= App::e((string) $item['source_type']) ?> / <?= App::e((string) $item['source_name']) ?> ·
          <?= App::e(substr((string) $item['created_at'], 0, 16)) ?>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="/super-admin/interview-questions.php?edit=<?= (int) $item['question_id'] ?>">Edit</a>
      </div>
      <p class="fw-semibold mb-1"><?= App::e((string) $item['question_text']) ?></p>
      <?php
        $libAns = '';
        try {
            $st = Db::pdo()->prepare('SELECT imported_answer FROM user_interview_questions WHERE question_id = ? AND imported_answer IS NOT NULL AND imported_answer != \'\' LIMIT 1');
            $st->execute([(int) $item['question_id']]);
            $libAns = (string) ($st->fetchColumn() ?: '');
        } catch (Throwable) {
        }
      ?>
      <?php if ($libAns !== ''): ?>
        <p class="small"><strong>Imported answer:</strong> <?= nl2br(App::e(mb_substr($libAns, 0, 800))) ?></p>
      <?php endif; ?>
      <?php if (!empty($item['possible_match_id'])): ?>
        <div class="alert alert-warning small mb-2">
          <strong>Potential existing match</strong> (#<?= (int) $item['possible_match_id'] ?>, score <?= App::e((string) $item['match_score']) ?>)<br>
          <?= App::e((string) ($item['match_question_text'] ?? '')) ?>
        </div>
      <?php endif; ?>
      <div class="d-flex flex-wrap gap-2">
        <form method="post" class="d-inline"><?= Csrf::field() ?>
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="review_id" value="<?= (int) $item['id'] ?>">
          <input type="hidden" name="example_answer" value="<?= App::e($libAns) ?>">
          <button class="btn btn-sm btn-success" type="submit">Approve</button>
        </form>
        <form method="post" class="d-inline" onsubmit="return confirm('Reject this submission?');"><?= Csrf::field() ?>
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="review_id" value="<?= (int) $item['id'] ?>">
          <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
        </form>
        <?php if (!empty($item['possible_match_id'])): ?>
          <form method="post" class="d-inline"><?= Csrf::field() ?>
            <input type="hidden" name="action" value="merge">
            <input type="hidden" name="review_id" value="<?= (int) $item['id'] ?>">
            <input type="hidden" name="canonical_id" value="<?= (int) $item['possible_match_id'] ?>">
            <button class="btn btn-sm btn-warning" type="submit">Merge / link existing</button>
          </form>
        <?php else: ?>
          <form method="post" class="d-flex gap-1 align-items-center"><?= Csrf::field() ?>
            <input type="hidden" name="action" value="merge">
            <input type="hidden" name="review_id" value="<?= (int) $item['id'] ?>">
            <input class="form-control form-control-sm" style="width:7rem" name="canonical_id" placeholder="Canonical ID" required>
            <button class="btn btn-sm btn-outline-warning" type="submit">Merge</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($result['total'] > $result['per_page']): ?>
  <div class="d-flex gap-2">
    <?php if ($result['page'] > 1): ?><a class="btn btn-sm btn-outline-secondary" href="?page=<?= $result['page'] - 1 ?>">Previous</a><?php endif; ?>
    <?php if ($result['page'] * $result['per_page'] < $result['total']): ?><a class="btn btn-sm btn-outline-secondary" href="?page=<?= $result['page'] + 1 ?>">Next</a><?php endif; ?>
  </div>
<?php endif; ?>
<?php
super_layout_footer();
