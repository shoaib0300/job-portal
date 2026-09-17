<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Interview\InterviewAnswerPresentation;
use KaamFit\Interview\InterviewLibrary;
use KaamFit\Interview\InterviewMarkdown;
use KaamFit\Interview\InterviewProgress;
use KaamFit\Interview\InterviewQuestionRepo;
use KaamFit\Interview\InterviewSchema;

InterviewSchema::ensureSchema();

$id = (int) ($_GET['id'] ?? 0);
$question = InterviewQuestionRepo::find($id);
$uid = Auth::id();
if ($question === null || !InterviewQuestionRepo::userCanView($uid, $question)) {
    App::flash('Question not found.', 'error');
    App::redirect('/interview-prep');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::requireValid();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'progress' && $uid > 0) {
            InterviewProgress::upsert($uid, $id, [
                'viewed' => 1,
                'practiced' => isset($_POST['practiced']),
                'confident' => isset($_POST['confident']),
                'favorite' => isset($_POST['favorite']),
                'confidence' => (int) ($_POST['confidence'] ?? 0),
                'notes' => (string) ($_POST['notes'] ?? ''),
                'personal_answer' => (string) ($_POST['personal_answer'] ?? ''),
                'bump_attempt' => isset($_POST['practiced']),
            ]);
            App::flash('Progress saved.');
        }
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
    }
    App::redirect('/interview-prep-question?id=' . $id);
}

InterviewProgress::markViewed($id);
$progress = $uid > 0 ? InterviewProgress::forUserQuestion($uid, $id) : null;
$library = $uid > 0 ? InterviewLibrary::entry($uid, $id) : null;
$type = (string) ($question['question_type'] ?? 'general');
$content = InterviewAnswerPresentation::forQuestion($question);

layout_header('Interview question');
?>
<main class="container-fluid px-3 px-lg-4 py-3">
  <?php require dirname(__DIR__) . '/src/interview_prep_nav.php'; ?>
  <p class="mb-2"><a href="/interview-prep">&larr; All questions</a></p>
  <article class="card shadow-sm interview-q-article">
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 small text-secondary mb-2 align-items-center">
        <span class="badge text-bg-light border"><?= App::e(ucfirst(str_replace('_', ' ', $type))) ?></span>
        <span><?= App::e((string) $question['language']) ?></span>
        <span><?= App::e(ucfirst((string) $question['difficulty'])) ?></span>
        <?php if (!empty($question['career_level'])): ?>
          <span><?= App::e((string) $question['career_level']) ?></span>
        <?php endif; ?>
        <?php if (($question['visibility'] ?? '') === 'personal'): ?>
          <span class="badge text-bg-secondary">Personal</span>
        <?php endif; ?>
      </div>

      <h1 class="h3 mb-3"><?= App::e((string) $question['question_text']) ?></h1>

      <?php if ($content['has_answer']): ?>
        <h2 class="h5 mb-3">Answer</h2>
        <div class="interview-answer">
          <?= InterviewMarkdown::render($content['answer_markdown']) ?>
        </div>
      <?php else: ?>
        <p class="text-secondary mb-0">No answer is available for this question yet.</p>
      <?php endif; ?>

      <?php if ($content['common_mistakes'] !== []): ?>
        <h2 class="h6 mt-4">Common mistakes</h2>
        <ul class="interview-answer-extra">
          <?php foreach ($content['common_mistakes'] as $line): ?>
            <li><?= App::e($line) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($content['follow_ups'] !== []): ?>
        <h2 class="h6 mt-4">Related questions</h2>
        <ul class="interview-answer-extra">
          <?php foreach ($content['follow_ups'] as $line): ?>
            <li><?= App::e($line) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($content['related_concepts'] !== []): ?>
        <h2 class="h6 mt-4">Related concepts</h2>
        <ul class="interview-answer-extra">
          <?php foreach ($content['related_concepts'] as $line): ?>
            <li><?= App::e($line) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if (!empty($library['imported_answer']) && !InterviewMarkdown::isWeakFiller((string) $library['imported_answer'])): ?>
        <h2 class="h6 mt-4">Your imported answer</h2>
        <div class="border rounded p-3 bg-body-secondary interview-answer"><?= InterviewMarkdown::render((string) $library['imported_answer']) ?></div>
      <?php endif; ?>
    </div>
  </article>

  <?php if ($uid > 0): ?>
    <form method="post" class="card shadow-sm mt-3">
      <div class="card-body">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="progress">
        <h2 class="h6">Your practice</h2>
        <div class="d-flex flex-wrap gap-3 mb-2">
          <label class="form-check"><input class="form-check-input" type="checkbox" name="practiced" value="1"<?= !empty($progress['practiced']) ? ' checked' : '' ?>> Practiced</label>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="confident" value="1"<?= !empty($progress['confident']) ? ' checked' : '' ?>> Confident</label>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="favorite" value="1"<?= !empty($progress['favorite']) ? ' checked' : '' ?>> Favorite</label>
        </div>
        <label class="form-label">Confidence (0–100)
          <input class="form-control" type="number" min="0" max="100" name="confidence" value="<?= (int) ($progress['confidence'] ?? 0) ?>">
        </label>
        <label class="form-label">Your personal answer
          <textarea class="form-control" name="personal_answer" rows="4"><?= App::e((string) ($progress['personal_answer'] ?? '')) ?></textarea>
        </label>
        <label class="form-label">Notes
          <textarea class="form-control" name="notes" rows="2"><?= App::e((string) ($progress['notes'] ?? '')) ?></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  <?php endif; ?>
</main>
<style>
.interview-answer pre.interview-code {
  background: var(--bs-secondary-bg, #f8f9fa);
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: .375rem;
  padding: .75rem 1rem;
  overflow-x: auto;
  font-size: .875rem;
}
.interview-answer code {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.interview-answer table { margin-bottom: 1rem; }
.interview-answer h3, .interview-answer h4, .interview-answer h5 {
  margin-top: 1.15rem;
  margin-bottom: .5rem;
  font-size: 1.05rem;
}
.interview-answer p:last-child { margin-bottom: 0; }
.interview-answer-extra { margin-bottom: 0; }
[data-bs-theme="dark"] .interview-answer pre.interview-code {
  background: var(--bs-tertiary-bg, #2b3035);
}
</style>
<?php
layout_footer();
