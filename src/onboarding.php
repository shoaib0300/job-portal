<?php

declare(strict_types=1);

/** @return list<array{id: string, label: string, caption: string, href: string, icon: string}> */
function onboarding_flow_steps(): array
{
    return [
        [
            'id' => 'master',
            'label' => 'Master templates',
            'caption' => 'Set up Master CV and Master cover letter once — they stay safe.',
            'href' => '/editor',
            'icon' => 'doc',
        ],
        [
            'id' => 'find',
            'label' => 'Find a job',
            'caption' => 'Search Jobs boards or paste a job description on New job.',
            'href' => '/jobs',
            'icon' => 'search',
        ],
        [
            'id' => 'copy',
            'label' => 'New job',
            'caption' => 'KaamMilo copies Master into a Job CV and job letter per application.',
            'href' => '/tailor',
            'icon' => 'apply',
        ],
        [
            'id' => 'edit',
            'label' => 'Tailor',
            'caption' => 'Edit summary, skills, and letter text for that company only.',
            'href' => '/resume-edit',
            'icon' => 'edit',
        ],
        [
            'id' => 'pdf',
            'label' => 'Export PDF',
            'caption' => 'Download PDF EN or DE from Resume or Cover letter pages.',
            'href' => '/design',
            'icon' => 'pdf',
        ],
        [
            'id' => 'track',
            'label' => 'Track',
            'caption' => 'Log status on Applications as you hear back.',
            'href' => '/applications',
            'icon' => 'track',
        ],
    ];
}

/** @return array<string, array{title: string, caption: string, cta_label: string, cta_href: string, mini: string}> */
function onboarding_section_meta(): array
{
    return [
        'resume' => [
            'title' => 'How Master CV works',
            'caption' => 'Master CV is your safe template. New job always copies from here — it never overwrites Master.',
            'cta_label' => 'Create Master CV',
            'cta_href' => '/resume-edit',
            'mini' => 'resume',
        ],
        'jobs' => [
            'title' => 'How Jobs works',
            'caption' => 'Search boards, filter companies, or paste a JD on New job when you already have a listing.',
            'cta_label' => 'Try New job',
            'cta_href' => '/tailor',
            'mini' => 'jobs',
        ],
        'cover' => [
            'title' => 'How cover letters work',
            'caption' => 'Master cover letter stays separate. Each application gets its own job letter copy.',
            'cta_label' => 'Edit Master cover',
            'cta_href' => '/cover-edit',
            'mini' => 'cover',
        ],
    ];
}

function onboarding_setting_key(string $section): string
{
    return 'onboarding_' . $section . '_seen';
}

function onboarding_is_seen(string $section): bool
{
    return (App::setting(onboarding_setting_key($section), '') ?: '') === '1';
}

function onboarding_mark_seen(string $section): void
{
    $allowed = ['resume', 'jobs', 'cover', 'hero'];
    if (!in_array($section, $allowed, true)) {
        return;
    }
    App::setSetting(onboarding_setting_key($section), '1');
}

function onboarding_clear_seen(string $section): void
{
    $allowed = ['resume', 'jobs', 'cover', 'hero'];
    if (!in_array($section, $allowed, true)) {
        return;
    }
    App::setSetting(onboarding_setting_key($section), '0');
}

function onboarding_mini_uid(): string
{
    static $n = 0;
    $n++;

    return 'kmMini' . $n;
}

function onboarding_render_hero(bool $collapsible = false, bool $startOpen = true): void
{
    $steps = onboarding_flow_steps();
    $seen = onboarding_is_seen('hero');
    $open = $collapsible ? (!$seen && $startOpen) : true;
    $collapseId = 'kmFlowHeroCollapse';
    ?>
    <div class="km-flow-hero-wrap<?= $collapsible ? ' km-flow-hero-wrap--collapsible' : '' ?>" data-km-flow-hero>
      <?php if ($collapsible): ?>
        <button class="km-flow-hero-toggle btn btn-link text-decoration-none w-100 text-start p-0 mb-2"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#<?= $collapseId ?>"
                aria-expanded="<?= $open ? 'true' : 'false' ?>"
                aria-controls="<?= $collapseId ?>">
          <span class="settings-panel-title mb-0">How KaamMilo works</span>
          <span class="small text-secondary ms-2">— 30 second overview</span>
        </button>
        <div class="collapse<?= $open ? ' show' : '' ?>" id="<?= $collapseId ?>">
      <?php endif; ?>
      <div class="km-flow-hero card shadow-sm" data-active-step="0">
        <div class="card-body">
          <div class="km-flow-hero-stage" role="img" aria-label="Workflow diagram">
            <svg class="km-flow-hero-svg" viewBox="0 0 720 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path class="km-flow-path" d="M60 60 H660" fill="none" stroke-width="3" opacity="0.2"/>
              <path class="km-flow-path-anim" d="M60 60 H660" fill="none" stroke-width="3" opacity="0.85"/>
              <g class="km-flow-dot-wrap" transform="translate(60, 0)" data-flow-dot-wrap>
                <circle class="km-flow-dot" cx="0" cy="60" r="8"/>
              </g>
              <?php
              $xs = [60, 180, 300, 420, 540, 660];
              foreach ($steps as $i => $step):
                  $x = $xs[$i];
              ?>
                <g class="km-flow-node<?= $i === 0 ? ' is-active' : '' ?>" data-flow-step="<?= App::e($step['id']) ?>">
                  <rect x="<?= $x - 28 ?>" y="28" width="56" height="64" rx="10" class="km-flow-node-box"/>
                  <text x="<?= $x ?>" y="72" text-anchor="middle" class="km-flow-node-num"><?= $i + 1 ?></text>
                </g>
              <?php endforeach; ?>
            </svg>
          </div>
          <div class="km-flow-hero-tabs" role="tablist" aria-label="Workflow steps">
            <?php foreach ($steps as $i => $step): ?>
              <button type="button"
                      class="km-flow-tab<?= $i === 0 ? ' is-active' : '' ?>"
                      role="tab"
                      id="km-flow-tab-<?= App::e($step['id']) ?>"
                      aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                      aria-controls="km-flow-panel-<?= App::e($step['id']) ?>"
                      data-flow-tab="<?= App::e($step['id']) ?>">
                <?= App::e($step['label']) ?>
              </button>
            <?php endforeach; ?>
          </div>
          <div class="km-flow-hero-panels">
            <?php foreach ($steps as $i => $step): ?>
              <div class="km-flow-panel<?= $i === 0 ? ' is-active' : '' ?>"
                   id="km-flow-panel-<?= App::e($step['id']) ?>"
                   role="tabpanel"
                   aria-labelledby="km-flow-tab-<?= App::e($step['id']) ?>"
                   data-flow-panel="<?= App::e($step['id']) ?>"
                   <?= $i !== 0 ? 'hidden' : '' ?>>
                <p class="km-flow-caption mb-2"><?= App::e($step['caption']) ?></p>
                <a class="btn btn-sm btn-outline-primary" href="<?= App::e($step['href']) ?>">Go to <?= App::e($step['label']) ?></a>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="km-flow-live small text-secondary mb-0 mt-2" aria-live="polite" data-flow-live><?= App::e($steps[0]['label']) ?></p>
          <?php if ($collapsible && !$seen): ?>
            <form method="post" action="/onboarding-dismiss.php" class="mt-3 mb-0">
              <input type="hidden" name="section" value="hero">
              <button type="submit" class="btn btn-sm btn-link text-secondary p-0">Got it — collapse this</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($collapsible): ?>
        </div>
        <?php if ($seen): ?>
          <p class="km-onboard-reopen mb-0 mt-2">
            <a href="/onboarding-dismiss.php?reopen=hero">Show workflow animation again</a>
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php
}

function onboarding_render_mini(string $variant, bool $animated = true): void
{
    $cls = 'km-flow-mini' . ($animated ? ' km-flow-mini--animated' : '');
    $uid = onboarding_mini_uid();
    ?>
    <div class="<?= $cls ?>" data-km-flow-mini="<?= App::e($variant) ?>" aria-hidden="true">
      <?php if ($variant === 'resume' || $variant === 'master' || $variant === 'copy'): ?>
        <svg viewBox="0 0 160 96" class="km-flow-mini-svg">
          <rect x="10" y="18" width="40" height="54" rx="5" fill="currentColor" opacity="0.12" stroke="currentColor" stroke-width="2"/>
          <text x="30" y="50" text-anchor="middle" font-size="10" font-weight="800" fill="currentColor">Master</text>
          <path class="km-mini-arrow" d="M58 45 H88" stroke="var(--km-accent, #0d7377)" stroke-width="2.5" stroke-linecap="round" marker-end="url(#<?= $uid ?>-arrow)"/>
          <g class="km-mini-copy-wrap">
            <rect x="94" y="22" width="40" height="54" rx="5" fill="var(--km-accent, #0d7377)" opacity="0.25" stroke="var(--km-accent, #0d7377)" stroke-width="2.5"/>
            <text x="114" y="54" text-anchor="middle" font-size="9" font-weight="800" fill="currentColor">Job CV</text>
          </g>
          <defs><marker id="<?= $uid ?>-arrow" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 Z" fill="var(--km-accent, #0d7377)"/></marker></defs>
        </svg>
      <?php elseif ($variant === 'jobs' || $variant === 'find'): ?>
        <svg viewBox="0 0 160 96" class="km-flow-mini-svg">
          <g class="km-mini-search-wrap">
            <circle cx="42" cy="42" r="20" fill="none" stroke="currentColor" stroke-width="2.5" opacity="0.55"/>
            <line x1="56" y1="56" x2="68" y2="68" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
          </g>
          <g class="km-mini-jobcard-wrap">
            <rect x="82" y="24" width="64" height="48" rx="7" fill="currentColor" opacity="0.12" stroke="currentColor" stroke-width="2"/>
            <line x1="92" y1="38" x2="134" y2="38" stroke="currentColor" stroke-width="2.5" opacity="0.45"/>
            <line x1="92" y1="50" x2="120" y2="50" stroke="currentColor" stroke-width="2" opacity="0.3"/>
          </g>
        </svg>
      <?php elseif ($variant === 'cover'): ?>
        <svg viewBox="0 0 160 96" class="km-flow-mini-svg">
          <rect x="10" y="24" width="44" height="36" rx="5" fill="currentColor" opacity="0.12" stroke="currentColor" stroke-width="2"/>
          <path d="M14 34 L32 44 L50 34" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.5"/>
          <text x="32" y="58" text-anchor="middle" font-size="7" font-weight="700" fill="currentColor">Master</text>
          <path class="km-mini-arrow" d="M60 42 H84" stroke="var(--km-accent, #0d7377)" stroke-width="2.5" stroke-linecap="round"/>
          <g class="km-mini-copy-wrap">
            <rect x="90" y="26" width="44" height="36" rx="5" fill="var(--km-accent, #0d7377)" opacity="0.25" stroke="var(--km-accent, #0d7377)" stroke-width="2.5"/>
            <path d="M94 36 L112 44 L130 36" fill="none" stroke="var(--km-accent, #0d7377)" stroke-width="1.5"/>
          </g>
        </svg>
      <?php elseif ($variant === 'edit'): ?>
        <svg viewBox="0 0 160 96" class="km-flow-mini-svg">
          <rect x="22" y="20" width="116" height="56" rx="7" fill="currentColor" opacity="0.08" stroke="currentColor" stroke-width="2"/>
          <line class="km-mini-edit-line" x1="36" y1="38" x2="124" y2="38" stroke="var(--km-accent, #0d7377)" stroke-width="3" stroke-linecap="round"/>
          <line x1="36" y1="52" x2="96" y2="52" stroke="currentColor" stroke-width="2" opacity="0.35" stroke-linecap="round"/>
        </svg>
      <?php elseif ($variant === 'pdf'): ?>
        <svg viewBox="0 0 160 96" class="km-flow-mini-svg">
          <rect x="50" y="12" width="60" height="72" rx="5" fill="currentColor" opacity="0.1" stroke="currentColor" stroke-width="2"/>
          <text x="80" y="54" text-anchor="middle" font-size="13" font-weight="800" fill="var(--km-accent, #0d7377)">PDF</text>
        </svg>
      <?php elseif ($variant === 'track'): ?>
        <svg viewBox="0 0 160 96" class="km-flow-mini-svg">
          <rect x="18" y="30" width="124" height="36" rx="7" fill="currentColor" opacity="0.08" stroke="currentColor" stroke-width="2"/>
          <circle class="km-mini-track-dot" cx="46" cy="48" r="9" fill="var(--km-accent, #0d7377)" opacity="0.35"/>
          <circle class="km-mini-track-dot" cx="80" cy="48" r="9" fill="var(--km-accent, #0d7377)" opacity="0.55"/>
          <circle class="km-mini-track-dot" cx="114" cy="48" r="9" fill="var(--km-accent, #0d7377)"/>
        </svg>
      <?php endif; ?>
    </div>
    <?php
}

function onboarding_render_reopen_link(string $section): void
{
    $meta = onboarding_section_meta()[$section] ?? null;
    if ($meta === null || !onboarding_is_seen($section)) {
        return;
    }
    ?>
    <p class="km-onboard-reopen">
      <a href="/onboarding-dismiss.php?reopen=<?= App::e($section) ?>">Show <?= App::e(strtolower($meta['title'])) ?> guide</a>
    </p>
    <?php
}

function onboarding_render_banner(string $section): void
{
    $meta = onboarding_section_meta()[$section] ?? null;
    if ($meta === null) {
        return;
    }
    if (onboarding_is_seen($section)) {
        onboarding_render_reopen_link($section);

        return;
    }
    ?>
    <aside class="km-onboard-banner card shadow-sm mb-3" data-onboarding-banner="<?= App::e($section) ?>">
      <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <?php onboarding_render_mini($meta['mini']); ?>
        <div class="flex-grow-1" style="min-width:12rem">
          <h2 class="h6 mb-1"><?= App::e($meta['title']) ?></h2>
          <p class="small text-secondary mb-2"><?= App::e($meta['caption']) ?></p>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-sm btn-primary" href="<?= App::e($meta['cta_href']) ?>"><?= App::e($meta['cta_label']) ?></a>
            <form method="post" action="/onboarding-dismiss.php" class="mb-0">
              <input type="hidden" name="section" value="<?= App::e($section) ?>">
              <button type="submit" class="btn btn-sm btn-link text-secondary">Dismiss</button>
            </form>
          </div>
        </div>
      </div>
    </aside>
    <?php
}
