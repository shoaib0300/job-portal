<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

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
$tags = $question['tags'] ?? [];
$type = (string) ($question['question_type'] ?? 'general');

$short = (string) ($question['short_answer'] ?? '');
$detailed = (string) ($question['detailed_answer'] ?? '');
$example = (string) ($question['example_answer'] ?? '');
if ($detailed === '' && !InterviewMarkdown::isWeakFiller($example)) {
    $detailed = $example;
}
if (InterviewMarkdown::isWeakFiller($short)) {
    $short = '';
}
if (InterviewMarkdown::isWeakFiller($detailed)) {
    $detailed = '';
}

layout_header('Interview question');
?>
<main class="container-fluid px-3 px-lg-4 py-3">
  <?php require dirname(__DIR__) . '/src/interview_prep_nav.php'; ?>
  <p class="mb-2"><a href="/interview-prep">&larr; All questions</a></p>
  <article class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 small text-secondary mb-2">
        <span class="badge text-bg-light border"><?= App::e($type) ?></span>
        <span><?= App::e((string) $question['language']) ?></span>
        <span><?= App::e((string) $question['difficulty']) ?></span>
        <?php if (!empty($question['career_level'])): ?>
          <span><?= App::e((string) $question['career_level']) ?></span>
        <?php endif; ?>
        <?php if (($question['visibility'] ?? '') === 'personal'): ?>
          <span class="badge text-bg-secondary">Personal</span>
        <?php endif; ?>
      </div>
      <h1 class="h3"><?= App::e((string) $question['question_text']) ?></h1>

      <?php if (!empty($question['why_asked'])): ?>
        <h2 class="h6 mt-4">Why interviewers ask this</h2>
        <div><?= InterviewMarkdown::render((string) $question['why_asked']) ?></div>
      <?php endif; ?>

      <?php if (!empty($question['strong_answer_covers'])): ?>
        <h2 class="h6 mt-3">Key points</h2>
        <ul>
          <?php foreach ((array) $question['strong_answer_covers'] as $line): ?>
            <li><?= App::e(is_string($line) ? $line : (string) json_encode($line)) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($short !== ''): ?>
        <h2 class="h6 mt-3">Short answer</h2>
        <div><?= InterviewMarkdown::render($short) ?></div>
      <?php endif; ?>

      <?php if ($detailed !== ''): ?>
        <h2 class="h6 mt-3"><?= in_array($type, ['behavioral', 'situational', 'portfolio'], true) ? 'Example answer' : 'Detailed answer' ?></h2>
        <div class="interview-answer"><?= InterviewMarkdown::render($detailed) ?></div>
      <?php endif; ?>

      <?php if (!empty($question['answer_framework'])): ?>
        <h2 class="h6 mt-3"><?= $type === 'behavioral' ? 'STAR framework' : 'Answer framework' ?></h2>
        <ol>
          <?php foreach ((array) $question['answer_framework'] as $line): ?>
            <li><?= App::e(is_string($line) ? $line : (string) json_encode($line)) ?></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>

      <?php if (!empty($question['common_mistakes'])): ?>
        <h2 class="h6 mt-3">Common mistakes</h2>
        <div><?= InterviewMarkdown::render(is_string($question['common_mistakes']) ? $question['common_mistakes'] : (string) json_encode($question['common_mistakes'])) ?></div>
      <?php endif; ?>

      <?php if (!empty($question['follow_ups'])): ?>
        <h2 class="h6 mt-3">Likely follow-ups</h2>
        <ul>
          <?php foreach ((array) $question['follow_ups'] as $line): ?>
            <li><?= App::e(is_string($line) ? $line : (string) json_encode($line)) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if (!empty($question['related_concepts'])): ?>
        <h2 class="h6 mt-3">Related concepts</h2>
        <p class="small mb-0"><?= App::e(implode(' · ', array_map(static fn($x) => is_string($x) ? $x : (string) json_encode($x), (array) $question['related_concepts']))) ?></p>
      <?php endif; ?>

      <?php if (!empty($library['imported_answer'])): ?>
        <h2 class="h6 mt-3">Your imported answer</h2>
        <div class="border rounded p-2 bg-body-secondary"><?= nl2br(App::e((string) $library['imported_answer'])) ?></div>
      <?php endif; ?>

      <?php if (!empty($tags['skills'])): ?>
        <h2 class="h6 mt-3">Skills</h2>
        <p class="small mb-0"><?= App::e(implode(' · ', array_column($tags['skills'], 'name_en'))) ?></p>
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
<?php
layout_footer();
