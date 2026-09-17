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

$short = trim((string) ($question['short_answer'] ?? ''));
$detailed = trim((string) ($question['detailed_answer'] ?? ''));
$example = trim((string) ($question['example_answer'] ?? ''));
$why = trim((string) ($question['why_asked'] ?? ''));
$keyPoints = $question['strong_answer_covers'] ?? null;
$framework = $question['answer_framework'] ?? null;
$mistakes = $question['common_mistakes'] ?? null;
$followUps = $question['follow_ups'] ?? null;
$related = $question['related_concepts'] ?? null;

if ($detailed === '' && !InterviewMarkdown::isWeakFiller($example)) {
    $detailed = $example;
    $example = '';
} elseif (InterviewMarkdown::isWeakFiller($example)) {
    $example = '';
}
if (InterviewMarkdown::isWeakFiller($short)) {
    $short = '';
}
if (InterviewMarkdown::isWeakFiller($detailed)) {
    $detailed = '';
}
if (InterviewMarkdown::isWeakFiller($why)) {
    $why = '';
}

$isBehavioral = in_array($type, ['behavioral', 'portfolio'], true);
$isSituational = in_array($type, ['situational', 'leadership'], true);
$isCase = in_array($type, ['case_study'], true);
$isTechnical = in_array($type, ['technical', 'practical', 'regulatory', 'safety', 'industry_specific', 'role_specific'], true);

$section = static function (string $title, string $html): void {
    if (trim(strip_tags($html)) === '') {
        return;
    }
    echo '<h2 class="h6 mt-4">' . App::e($title) . '</h2>';
    echo '<div class="interview-answer">' . $html . '</div>';
};

$listSection = static function (string $title, mixed $items): void {
    if ($items === null || $items === '' || $items === []) {
        return;
    }
    if (is_string($items)) {
        $decoded = json_decode($items, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            echo '<h2 class="h6 mt-4">' . App::e($title) . '</h2>';
            echo '<div class="interview-answer">' . InterviewMarkdown::render($items) . '</div>';
            return;
        }
    }
    if (!is_array($items) || $items === []) {
        return;
    }
    echo '<h2 class="h6 mt-4">' . App::e($title) . '</h2><ul>';
    foreach ($items as $line) {
        $text = is_string($line) ? $line : (string) json_encode($line);
        if (trim($text) === '') {
            continue;
        }
        echo '<li>' . App::e($text) . '</li>';
    }
    echo '</ul>';
};

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

      <?php
      // Type-adaptive order — only non-empty sections render
      if ($isBehavioral || $isSituational) {
          $section($isSituational ? 'What interviewers assess' : 'Why interviewers ask this', InterviewMarkdown::render($why));
          $listSection('What a strong answer should demonstrate', $keyPoints);
          $listSection($isBehavioral ? 'Recommended structure (STAR)' : 'Recommended approach', $framework);
          if ($short !== '') {
              $section('Short answer', InterviewMarkdown::render($short));
          }
          $section('Example answer', InterviewMarkdown::render($detailed));
          if ($example !== '' && $example !== $detailed) {
              $section('Additional example', InterviewMarkdown::render($example));
          }
          $listSection('Common mistakes', $mistakes);
          $listSection('Likely follow-ups', $followUps);
          $listSection('Related concepts', $related);
      } elseif ($isCase) {
          $section('Problem framing', InterviewMarkdown::render($why));
          $listSection('Assumptions & analysis points', $keyPoints);
          $listSection('Approach', $framework);
          if ($short !== '') {
              $section('Short recommendation', InterviewMarkdown::render($short));
          }
          $section('Example reasoning', InterviewMarkdown::render($detailed));
          $listSection('Common mistakes', $mistakes);
          $listSection('Follow-up questions', $followUps);
          $listSection('Related concepts', $related);
      } else {
          // Technical / practical / general / regulatory
          if ($short !== '') {
              $section('Short answer', InterviewMarkdown::render($short));
          }
          $section($isTechnical ? 'Detailed explanation' : 'Answer', InterviewMarkdown::render($detailed));
          if ($example !== '' && $example !== $detailed) {
              $section('Example', InterviewMarkdown::render($example));
          }
          $section('Why interviewers ask this', InterviewMarkdown::render($why));
          $listSection('Key points', $keyPoints);
          $listSection('Answer framework', $framework);
          $listSection('Common mistakes', $mistakes);
          $listSection('Related concepts', $related);
          $listSection('Likely follow-ups', $followUps);
      }
      ?>

      <?php if (!empty($library['imported_answer'])): ?>
        <h2 class="h6 mt-4">Your imported answer</h2>
        <div class="border rounded p-3 bg-body-secondary interview-answer"><?= InterviewMarkdown::render((string) $library['imported_answer']) ?></div>
      <?php endif; ?>

      <?php if (!empty($tags['skills'])): ?>
        <h2 class="h6 mt-4">Skills</h2>
        <p class="small mb-0"><?= App::e(implode(' · ', array_column($tags['skills'], 'name_en'))) ?></p>
      <?php endif; ?>
      <?php if (!empty($tags['technologies'])): ?>
        <h2 class="h6 mt-3">Technologies</h2>
        <p class="small mb-0"><?= App::e(implode(' · ', array_column($tags['technologies'], 'name_en'))) ?></p>
      <?php endif; ?>
      <?php if (!empty($tags['occupations'])): ?>
        <h2 class="h6 mt-3">Occupations</h2>
        <p class="small mb-0"><?= App::e(implode(' · ', array_column($tags['occupations'], 'name_en'))) ?></p>
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
[data-bs-theme="dark"] .interview-answer pre.interview-code {
  background: var(--bs-tertiary-bg, #2b3035);
}
</style>
<?php
layout_footer();
