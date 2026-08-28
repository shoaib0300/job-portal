<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/layout.php';

App::ensureDashboardSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $company = trim((string) ($_POST['company'] ?? ''));
        $result = App::tailorFromJd(
            $company,
            (string) ($_POST['role'] ?? ''),
            (string) ($_POST['location'] ?? ''),
            (string) ($_POST['jd_snippet'] ?? ''),
            App::normalizeHttpUrl((string) ($_POST['link'] ?? '')),
            (string) ($_POST['status'] ?? 'applied'),
            null,
            null,
            null,
            null,
            ''
        );
        App::flash(
            'Created Job CV for ' . $result['company'] . '. Master CV unchanged.'
        );
        App::redirect('/resume-edit');
    } catch (Throwable $e) {
        App::flash($e->getMessage(), 'error');
        App::redirect('/tailor');
    }
}

layout_header('New job');
?>
<main class="page-narrow">
  <header class="page-head">
    <h1 class="d-flex align-items-center gap-2"><?= kaamfit_icon('rocket', 'sm') ?> New job</h1>
    <p>Copies your <strong>Master CV</strong> and <strong>Master cover letter</strong> into a new <strong>Job CV</strong> for this application.</p>
  </header>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="company">Company</label>
          <input class="form-control" type="text" id="company" name="company" required placeholder="GlobalConnect">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="role">Role</label>
          <input class="form-control" type="text" id="role" name="role" required placeholder="Werkstudent Sales &amp; Marketing">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="location">Location</label>
          <input class="form-control" type="text" id="location" name="location" required placeholder="Hamburg, Germany">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="status">Status</label>
          <select class="form-select" id="status" name="status">
            <option value="applied" selected>Applied</option>
            <option value="custom">Custom</option>
            <option value="interview">Interview</option>
            <option value="offer">Offer</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="link">Link <span class="text-secondary fw-normal">(optional)</span></label>
          <input class="form-control" type="text" id="link" name="link" inputmode="url" placeholder="https:// or leave blank">
        </div>
        <div class="col-12">
          <label class="form-label" for="jd_snippet">Job text</label>
          <textarea class="form-control" id="jd_snippet" name="jd_snippet" rows="16" required placeholder="Paste the job description"></textarea>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Create Job CV</button>
        </div>
      </div>
    </div>
  </form>
</main>
<?php
layout_footer();
