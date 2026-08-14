<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

App::ensureDashboardSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = App::tailorFromJd(
            (string) ($_POST['company'] ?? ''),
            (string) ($_POST['role'] ?? ''),
            (string) ($_POST['location'] ?? ''),
            (string) ($_POST['jd_snippet'] ?? ''),
            (string) ($_POST['link'] ?? ''),
            (string) ($_POST['status'] ?? 'applied'),
            null,
            null,
            null,
            null,
            ''
        );
        App::flash(
            'Copied Main into resume #' . $result['resume_id']
            . ' and cover #' . $result['cover_id']
            . '. Application #' . $result['application_id']
            . ' · ' . $result['location']
            . ' · ' . App::statusLabel($result['status']) . '.'
        );
        App::redirect('/editor.php#versions');
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/tailor.php');
    }
}

layout_header('Apply from a JD');
?>
<main class="page-narrow">
  <header class="page-head">
    <h1>Apply from a JD</h1>
    <p>Paste a job description. This copies your Main resume and Main cover letter into the database and logs the application. No PHP files.</p>
  </header>

  <form method="post" class="form panel">
    <label>Company <input type="text" name="company" required placeholder="GlobalConnect"></label>
    <label>Role <input type="text" name="role" required placeholder="Werkstudent Sales &amp; Marketing"></label>
    <label>Location <input type="text" name="location" required placeholder="Hamburg, Germany"></label>
    <label>Link <input type="url" name="link" placeholder="https://"></label>
    <label>Status
      <select name="status">
        <option value="applied" selected>Applied</option>
        <option value="custom">Custom</option>
        <option value="interview">Interview</option>
        <option value="offer">Offer</option>
        <option value="rejected">Rejected</option>
      </select>
    </label>
    <label>Job description
      <textarea name="jd_snippet" rows="16" required placeholder="Paste the full JD here"></textarea>
    </label>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Copy Main &amp; log application</button>
    </div>
    <p class="muted">Then edit summary, skills, and the letter in the Editor. Main is never overwritten.</p>
  </form>
</main>
<?php
layout_footer();
