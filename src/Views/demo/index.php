<?php

declare(strict_types=1);

/** @var array{name:string,title:string,email:string} $persona */
/** @var array<string, int> $counts */
/** @var list<array<string, string>> $jobs */
/** @var list<array<string, mixed>> $applications */
/** @var array<string, string> $tailor */
/** @var array{summary:string,skills:string,cover:string} $preview */
?>
<section class="site-section demo-page-section">
  <div class="demo-intro mb-4">
    <div class="site-kicker">Interactive demo</div>
    <h1 class="h2 mb-2">Explore the dashboard — no account needed</h1>
    <p class="text-secondary mb-0">Sample data for <strong><?= App::e($persona['name']) ?></strong> — <?= App::e($persona['title']) ?>. Click through Jobs, tailor a JD, and see the application tracker.</p>
  </div>

  <div class="demo-portal" data-demo-root>
    <div class="demo-banner" role="status">
      <span><strong>Demo mode</strong> — sample data only. Nothing is saved.</span>
      <a class="btn btn-sm btn-light" href="/register">Create account</a>
    </div>

    <div class="demo-layout">
      <nav class="demo-nav" aria-label="Demo sections">
        <div class="demo-persona small text-secondary mb-3">
          <div class="fw-semibold text-body"><?= App::e($persona['name']) ?></div>
          <div><?= App::e($persona['title']) ?></div>
        </div>
        <button type="button" class="demo-nav-btn is-active" data-demo-tab="home" aria-selected="true">Home</button>
        <button type="button" class="demo-nav-btn" data-demo-tab="jobs">Jobs</button>
        <button type="button" class="demo-nav-btn" data-demo-tab="tailor">New job</button>
        <button type="button" class="demo-nav-btn" data-demo-tab="applications">Applications</button>
      </nav>

      <div class="demo-panels">
        <!-- Home -->
        <div class="demo-panel is-active" data-demo-panel="home">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="card h-100 shadow-sm">
                <div class="card-body d-flex gap-3">
                  <span class="step-n">1</span>
                  <div>
                    <h2 class="h5">Find or paste a job</h2>
                    <p class="text-secondary small mb-3">Search Bundesagentur listings, or paste a JD. Master CV and letter are copied into a Job CV per application.</p>
                    <button type="button" class="btn btn-primary btn-sm demo-goto" data-demo-goto="jobs">Find jobs</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm demo-goto" data-demo-goto="tailor">Paste a JD</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card h-100 shadow-sm">
                <div class="card-body d-flex gap-3">
                  <span class="step-n">2</span>
                  <div>
                    <h2 class="h5">Edit your resume</h2>
                    <p class="text-secondary small mb-3">Tweak summary and skills for that company. Master CV stays untouched.</p>
                    <button type="button" class="btn btn-outline-secondary btn-sm demo-locked">Open resume</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card h-100 shadow-sm">
                <div class="card-body d-flex gap-3">
                  <span class="step-n">3</span>
                  <div>
                    <h2 class="h5">Download PDF</h2>
                    <p class="text-secondary small mb-3">Pick a look, then export EN or DE PDFs.</p>
                    <button type="button" class="btn btn-outline-secondary btn-sm demo-locked">Resume style</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-4" aria-label="Application counts">
            <?php
            $statMeta = [
                'all' => 'All',
                'applied' => 'Applied',
                'interview' => 'Interview',
                'offer' => 'Offer',
                'rejected' => 'Rejected',
            ];
            foreach ($statMeta as $key => $label):
            ?>
              <div class="col">
                <button type="button" class="card shadow-sm h-100 text-decoration-none text-reset border-0 w-100 text-start demo-goto-apps" data-demo-app-status="<?= App::e($key) ?>">
                  <div class="card-body py-3">
                    <div class="text-secondary small"><?= App::e($label) ?></div>
                    <div class="h4 mb-0"><?= (int) ($counts[$key] ?? 0) ?></div>
                  </div>
                </button>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="card shadow-sm">
            <div class="card-body">
              <h2 class="h6">Recent applications</h2>
              <ul class="list-unstyled mb-0">
                <?php foreach (array_slice($applications, 0, 4) as $app): ?>
                  <li class="d-flex flex-wrap justify-content-between gap-2 py-2 border-bottom">
                    <span><strong><?= App::e((string) $app['company']) ?></strong> · <?= App::e((string) $app['role']) ?></span>
                    <span class="badge text-bg-light border"><?= App::e(App::statusLabel((string) $app['status'])) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>

        <!-- Jobs -->
        <div class="demo-panel" data-demo-panel="jobs" hidden>
          <header class="mb-3">
            <h2 class="h4 mb-1">Jobs</h2>
            <p class="text-secondary small mb-0">Sample listings from <?= App::e(DemoSample::JOB_SOURCE_LABEL) ?> only. Filters work in this demo — data is fictional.</p>
          </header>

          <div class="card shadow-sm mb-3">
            <div class="card-body py-3">
              <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <span class="small text-secondary">Quick filters:</span>
                <button type="button" class="btn btn-sm btn-outline-secondary demo-job-filter" data-demo-job-filter="all">All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary demo-job-filter" data-demo-job-filter="hamburg">Hamburg</button>
                <button type="button" class="btn btn-sm btn-outline-secondary demo-job-filter" data-demo-job-filter="student">Student</button>
                <button type="button" class="btn btn-sm btn-outline-secondary demo-job-filter" data-demo-job-filter="match">Match resume</button>
              </div>
              <p class="small text-secondary mb-0" data-demo-jobs-count><?= count($jobs) ?> sample jobs</p>
            </div>
          </div>

          <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 jobs-card-grid" data-demo-jobs-grid>
            <?php foreach ($jobs as $job): ?>
              <div class="col" data-demo-job-card
                   data-city="<?= App::e($job['city_filter']) ?>"
                   data-student="<?= App::e($job['student']) ?>"
                   data-match="<?= App::e($job['match']) ?>">
                <article class="card shadow-sm h-100 jobs-job-card">
                  <div class="card-body d-flex flex-column">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                      <span class="badge text-bg-light border"><?= App::e($job['source']) ?></span>
                      <span class="badge text-bg-light border"><?= App::e($job['work_mode']) ?></span>
                      <?php if ($job['fit'] !== ''): ?>
                        <span class="badge text-bg-success"><?= App::e($job['fit']) ?></span>
                      <?php endif; ?>
                    </div>
                    <h3 class="h6 card-title mb-2"><?= App::e($job['title']) ?></h3>
                    <p class="small mb-1"><strong><?= App::e($job['company']) ?></strong></p>
                    <p class="small text-secondary mb-2"><?= App::e($job['city']) ?>, Germany</p>
                    <p class="small text-secondary mb-3">Posted <?= App::e($job['posted']) ?></p>
                    <div class="mt-auto d-flex flex-wrap gap-2">
                      <button type="button" class="btn btn-sm btn-primary demo-locked">Apply</button>
                      <button type="button" class="btn btn-sm btn-outline-primary demo-locked">Open</button>
                    </div>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="small text-secondary mt-3 mb-0 d-none" data-demo-jobs-empty>No sample jobs match this filter.</p>
        </div>

        <!-- Tailor -->
        <div class="demo-panel" data-demo-panel="tailor" hidden>
          <header class="mb-3">
            <h2 class="h4 mb-1">New job</h2>
            <p class="text-secondary small mb-0">Paste a job description — we copy Master CV and cover into a new Job CV.</p>
          </header>

          <form class="card shadow-sm" data-demo-tailor-form onsubmit="return false;">
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="demo-company">Company</label>
                  <input class="form-control" type="text" id="demo-company" value="<?= App::e($tailor['company']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="demo-role">Role</label>
                  <input class="form-control" type="text" id="demo-role" value="<?= App::e($tailor['role']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="demo-location">Location</label>
                  <input class="form-control" type="text" id="demo-location" value="<?= App::e($tailor['location']) ?>" readonly>
                </div>
                <div class="col-12">
                  <label class="form-label" for="demo-jd">Job description</label>
                  <textarea class="form-control" id="demo-jd" rows="8" readonly><?= App::e($tailor['jd']) ?></textarea>
                </div>
                <div class="col-12">
                  <button type="button" class="btn btn-primary" data-demo-tailor-run>Tailor resume &amp; cover</button>
                </div>
              </div>
            </div>
          </form>

          <div class="alert alert-success mt-3 d-none" data-demo-tailor-result role="status">
            <strong>Done (demo).</strong> Created Job CV from Master CV and cover <strong>#14</strong>. Application <strong>#15</strong> logged · <?= App::e($tailor['location']) ?> · Applied.
          </div>

          <div class="row g-3 mt-1 d-none" data-demo-tailor-preview>
            <div class="col-lg-6">
              <div class="card shadow-sm h-100">
                <div class="card-body">
                  <h3 class="h6">Resume summary (tailored)</h3>
                  <p class="small mb-3"><?= App::e($preview['summary']) ?></p>
                  <h3 class="h6">Skills</h3>
                  <pre class="small mb-0 demo-pre"><?= App::e($preview['skills']) ?></pre>
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="card shadow-sm h-100">
                <div class="card-body">
                  <h3 class="h6">Cover letter (excerpt)</h3>
                  <pre class="small mb-0 demo-pre"><?= App::e($preview['cover']) ?></pre>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Applications -->
        <div class="demo-panel" data-demo-panel="applications" hidden>
          <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <h2 class="h4 mb-1">Applications</h2>
              <p class="text-secondary small mb-0">Track what you sent where — company, role, location, status.</p>
            </div>
            <button type="button" class="btn btn-primary btn-sm demo-goto" data-demo-goto="tailor">New job</button>
          </header>

          <div class="d-flex flex-wrap gap-2 mb-3" data-demo-app-filters>
            <?php
            $chips = ['all' => 'All', 'applied' => 'Applied', 'interview' => 'Interview', 'offer' => 'Offer', 'rejected' => 'Rejected'];
            foreach ($chips as $key => $label):
            ?>
              <button type="button" class="chip demo-app-filter<?= $key === 'all' ? ' is-active' : '' ?>" data-demo-app-filter="<?= App::e($key) ?>">
                <?= App::e($label) ?> (<?= (int) ($counts[$key] ?? 0) ?>)
              </button>
            <?php endforeach; ?>
          </div>

          <div class="table-responsive card shadow-sm">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Company</th>
                  <th>Role</th>
                  <th>Location</th>
                  <th>Docs</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody data-demo-apps-body>
                <?php foreach ($applications as $app): ?>
                  <?php
                  $badge = match ($app['status']) {
                      'rejected' => 'text-bg-danger',
                      'interview' => 'text-bg-info',
                      'offer' => 'text-bg-success',
                      default => 'text-bg-primary',
                  };
                  ?>
                  <tr data-demo-app-row data-status="<?= App::e((string) $app['status']) ?>">
                    <td><?= App::e((string) $app['company']) ?></td>
                    <td><?= App::e((string) $app['role']) ?></td>
                    <td><?= App::e((string) $app['location']) ?></td>
                    <td class="small">
                      Resume #<?= (int) $app['resume_id'] ?> · Letter #<?= (int) $app['cover_id'] ?>
                    </td>
                    <td><span class="badge <?= $badge ?>"><?= App::e(App::statusLabel((string) $app['status'])) ?></span></td>
                    <td><?= App::e((string) $app['applied_date']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="demo-cta mt-4 text-center">
      <p class="mb-2">Like what you see? Create your own workspace with real jobs and documents.</p>
      <a class="btn btn-primary" href="/register">Create free account</a>
      <a class="btn btn-outline-secondary" href="/login">Sign in</a>
    </div>
  </div>

  <div class="demo-toast" data-demo-toast hidden role="status"></div>
</section>
