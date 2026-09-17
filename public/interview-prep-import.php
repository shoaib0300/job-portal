<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

use KaamFit\Interview\InterviewContentImport;
use KaamFit\Interview\InterviewSchema;

InterviewSchema::ensureSchema();
$uid = Auth::id();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::requireValid();
        $lang = trim((string) ($_POST['language'] ?? 'en')) ?: 'en';
        $paste = trim((string) ($_POST['paste'] ?? ''));
        if ($paste !== '') {
            $result = InterviewContentImport::importForUser($uid, [
                'text' => $paste,
                'language' => $lang,
                'source_type' => 'user_paste',
            ]);
        } elseif (!empty($_FILES['file']['tmp_name'])) {
            $result = InterviewContentImport::importForUser($uid, [
                'file' => $_FILES['file'],
                'language' => $lang,
                'source_type' => 'user_upload',
            ]);
        } else {
            throw new InvalidArgumentException('Upload a file or paste content.');
        }
        $s = $result['user_summary'];
        App::flash(
            'Import complete. ' . (int) $s['added'] . ' questions added to your Interview Prep. '
            . ((int) $s['answers'] > 0 ? (int) $s['answers'] . ' answers detected. ' : '')
            . 'Some newly imported content may be reviewed before being added to the shared Interview Knowledge Base.'
        );
        App::redirect('/interview-prep-mine');
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/interview-prep-import');
    }
}

layout_header('Import interview questions');
?>
<main class="container-fluid px-3 px-lg-4 py-3">
  <?php require dirname(__DIR__) . '/src/interview_prep_nav.php'; ?>
  <header class="page-head mb-3">
    <h1>Import questions</h1>
    <p class="text-secondary mb-0">Upload PDF, DOCX, TXT, Markdown — or paste Q&amp;A. Questions are added to your Interview Prep immediately.</p>
  </header>

  <form method="post" enctype="multipart/form-data" class="card shadow-sm">
    <div class="card-body vstack gap-3">
      <?= Csrf::field() ?>
      <div>
        <label class="form-label">File (PDF, DOCX, TXT, Markdown)</label>
        <input class="form-control" type="file" name="file" accept=".pdf,.docx,.txt,.md,.markdown,text/plain,application/pdf">
      </div>
      <div>
        <label class="form-label">Or paste content</label>
        <textarea class="form-control" name="paste" rows="12" placeholder="1. What is regression testing?&#10;Answer: …&#10;&#10;2. Tell me about a time you…"></textarea>
      </div>
      <div class="col-md-3">
        <label class="form-label">Language</label>
        <select class="form-select" name="language">
          <option value="en">English</option>
          <option value="de">German</option>
        </select>
      </div>
      <button class="btn btn-primary align-self-start" type="submit">Import</button>
    </div>
  </form>
</main>
<?php
layout_footer();
