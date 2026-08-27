<?php

declare(strict_types=1);

/** @var \KaamMilo\Jobs\JobQuery $query */
/** @var array $result */
/** @var bool $ran */
/** @var array<string, string> $sourceLabels */
/** @var list<array{key:string,label:string}> $companyOptions */
/** @var array<int, string> $postedOptions */
/** @var string $resumeTitle */
/** @var bool $serpConfigured */
/** @var string $resultsHtml */

use App;
?>
<main class="jobs-page">
  <header class="page-head">
    <h1>Jobs</h1>
    <p>Search German boards in one place. Prepare your resume here, then apply on the employer site.</p>
  </header>

  <form method="get" action="/jobs" class="jobs-layout" data-jobs-form data-jobs-ajax>
    <input type="hidden" name="search" value="1">
    <input type="hidden" name="posted" value="<?= (int) $query->postedDays ?>" data-jobs-posted>

    <div class="card shadow-sm jobs-filters mb-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h2 class="h5 mb-0">Filters</h2>
          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <a class="btn btn-outline-secondary btn-sm" href="/jobs?reset=1">Reset</a>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-4" data-keyword-chips>
            <label class="form-label" for="q-input">Role keywords</label>
            <div class="keyword-chip-list" data-keyword-list>
              <?php foreach ($query->keywords as $kw): ?>
                <span class="keyword-chip">
                  <input type="hidden" name="q[]" value="<?= App::e($kw) ?>">
                  <span class="keyword-chip-label"><?= App::e($kw) ?></span>
                  <button type="button" class="keyword-chip-remove" data-keyword-remove aria-label="Remove <?= App::e($kw) ?>">&times;</button>
                </span>
              <?php endforeach; ?>
            </div>
            <div class="input-group input-group-sm mt-2">
              <input class="form-control" type="text" id="q-input" name="q_add" data-keyword-input
                     placeholder="e.g. QA, Tester, Werkstudent" autocomplete="off">
              <button class="btn btn-outline-secondary" type="button" data-keyword-add>Add</button>
            </div>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="match_resume" value="1" id="f-resume"<?= $query->matchResume ? ' checked' : '' ?>>
              <label class="form-check-label" for="f-resume">Match my resume</label>
            </div>
            <?php if ($resumeTitle !== ''): ?>
              <p class="small text-secondary mb-0 mt-1">Active: <?= App::e($resumeTitle) ?></p>
            <?php endif; ?>
          </div>

          <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="city">City</label>
            <input class="form-control form-control-sm" type="text" id="city" name="city" value="<?= App::e($query->city) ?>" placeholder="München">
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="bundesland">Bundesland</label>
            <select class="form-select form-select-sm" id="bundesland" name="bundesland">
              <option value="">Any</option>
              <?php foreach (\KaamMilo\Jobs\JobQuery::BUNDESLAENDER as $bl): ?>
                <option value="<?= App::e($bl) ?>"<?= $query->bundesland === $bl ? ' selected' : '' ?>><?= App::e($bl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="work_mode">Work mode</label>
            <select class="form-select form-select-sm" id="work_mode" name="work_mode">
              <option value="">Any</option>
              <option value="remote"<?= $query->workMode === 'remote' ? ' selected' : '' ?>>Remote</option>
              <option value="hybrid"<?= $query->workMode === 'hybrid' ? ' selected' : '' ?>>Hybrid</option>
              <option value="onsite"<?= $query->workMode === 'onsite' ? ' selected' : '' ?>>On-site</option>
            </select>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="employment">Hours</label>
            <select class="form-select form-select-sm" id="employment" name="employment">
              <option value="">Any</option>
              <option value="fulltime"<?= $query->employment === 'fulltime' ? ' selected' : '' ?>>Vollzeit</option>
              <option value="parttime"<?= $query->employment === 'parttime' ? ' selected' : '' ?>>Teilzeit</option>
              <option value="mini"<?= $query->employment === 'mini' ? ' selected' : '' ?>>Minijob</option>
            </select>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-lg-4">
            <div class="form-label mb-1">Level</div>
            <div class="d-flex flex-wrap gap-3">
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="student" value="1" id="f-student"<?= $query->student ? ' checked' : '' ?>>
                <label class="form-check-label" for="f-student">Student</label>
              </div>
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="junior" value="1" id="f-junior"<?= $query->junior ? ' checked' : '' ?>>
                <label class="form-check-label" for="f-junior">Junior</label>
              </div>
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="graduate" value="1" id="f-graduate"<?= $query->graduate ? ' checked' : '' ?>>
                <label class="form-check-label" for="f-graduate">Absolvent</label>
              </div>
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="internship" value="1" id="f-intern"<?= $query->internship ? ' checked' : '' ?>>
                <label class="form-check-label" for="f-intern">Praktikum</label>
              </div>
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="no_experience" value="1" id="f-noexp"<?= $query->noExperience ? ' checked' : '' ?>>
                <label class="form-check-label" for="f-noexp">No experience</label>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="form-label mb-1">Language</div>
            <div class="d-flex flex-wrap align-items-center gap-3">
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="english" value="1" id="f-en"<?= $query->english ? ' checked' : '' ?>>
                <label class="form-check-label" for="f-en">English</label>
              </div>
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="has_salary" value="1" id="f-sal"<?= $query->hasSalary ? ' checked' : '' ?>>
                <label class="form-check-label" for="f-sal">Mentions salary</label>
              </div>
              <div>
                <label class="visually-hidden" for="german_level">German level</label>
                <select class="form-select form-select-sm" id="german_level" name="german_level" style="min-width:7rem">
                  <option value="">German: any</option>
                  <?php foreach (['A1', 'A2', 'B1', 'B2', 'C1'] as $lvl): ?>
                    <option value="<?= $lvl ?>"<?= $query->germanLevel === $lvl ? ' selected' : '' ?>><?= $lvl ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="form-label mb-1">Posted</div>
            <div class="jobs-chip-row" role="group" aria-label="Posted when">
              <?php foreach ($postedOptions as $days => $label): ?>
                <?php
                $active = (int) $query->postedDays === (int) $days;
                $href = '/jobs?' . $query->toQuery(['posted' => $days, 'page' => 1]);
                ?>
                <a class="jobs-chip<?= $active ? ' is-active' : '' ?>" href="<?= App::e($href) ?>" data-jobs-posted-chip="<?= (int) $days ?>"><?= App::e($label) ?></a>
              <?php endforeach; ?>
            </div>
            <p class="small text-secondary mb-0 mt-1">Older than 7 days are never shown.</p>
          </div>
        </div>

        <div class="mt-3 pt-3 border-top">
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#jobsMoreFilters" aria-expanded="false" aria-controls="jobsMoreFilters">
            Sources &amp; companies
          </button>
          <div class="collapse mt-3" id="jobsMoreFilters">
            <div class="row g-3">
              <div class="col-lg-6">
                <div class="form-label mb-2">Sources</div>
                <div class="row row-cols-1 row-cols-sm-2 g-1">
                  <?php foreach ($sourceLabels as $sid => $slabel): ?>
                    <div class="col">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="sources[]" value="<?= App::e($sid) ?>" id="src-<?= App::e($sid) ?>"<?= $query->wantsSource($sid) ? ' checked' : '' ?>>
                        <label class="form-check-label small" for="src-<?= App::e($sid) ?>"><?= App::e($slabel) ?></label>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <p class="small text-secondary mt-2 mb-0">LinkedIn / Indeed / StepStone need <code>BRIGHT_DATA_API_TOKEN</code> (Web Unlocker). Without it, LinkedIn’s guest API is blocked from this server. Free boards: Arbeitsagentur, Jobexport, Greenhouse/Personio.</p>
                <?php if (!$serpConfigured && App::isDev()): ?>
                  <p class="small text-secondary mt-1 mb-0">Token missing — SERP boards and LinkedIn Unlocker will stay empty.</p>
                <?php endif; ?>
              </div>
              <?php if ($companyOptions !== []): ?>
                <div class="col-lg-6">
                  <div class="form-label mb-2">Companies</div>
                  <p class="small text-secondary mb-2">Leave empty for all enabled boards.</p>
                  <div class="jobs-company-scroll border rounded p-2">
                    <div class="row row-cols-1 row-cols-sm-2 g-1">
                      <?php foreach ($companyOptions as $i => $opt): ?>
                        <?php $cid = 'co-' . $i; ?>
                        <div class="col">
                          <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="companies[]" value="<?= App::e($opt['key']) ?>" id="<?= App::e($cid) ?>"<?= in_array($opt['key'], $query->companies, true) ? ' checked' : '' ?>>
                            <label class="form-check-label small" for="<?= App::e($cid) ?>"><?= App::e($opt['label']) ?></label>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            <p class="small text-secondary mt-2 mb-0">Manage boards under <a href="/companies">Companies</a>.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="jobs-results" data-jobs-results>
      <div class="jobs-results-loading" data-jobs-loading hidden aria-hidden="true">
        <div class="jobs-spinner" role="status" aria-label="Loading jobs"></div>
      </div>
      <div data-jobs-panel>
        <?= $resultsHtml ?>
      </div>
    </div>
  </form>
</main>
