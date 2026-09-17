(function () {
  function fillSelect(sel, items, selected, allLabel) {
    if (!sel) return '';
    const prev = selected !== undefined && selected !== null ? selected : (sel.value || '');
    sel.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = allLabel || 'All';
    sel.appendChild(opt0);
    let kept = '';
    (items || []).forEach((it) => {
      const o = document.createElement('option');
      o.value = it.slug;
      o.textContent = it.name_en || it.slug;
      if (prev && prev === it.slug) {
        o.selected = true;
        kept = prev;
      }
      sel.appendChild(o);
    });
    if (prev && !kept) {
      sel.value = '';
      return '';
    }
    return kept || sel.value || '';
  }

  function formParams(form) {
    const fd = new FormData(form);
    const params = new URLSearchParams();
    for (const [k, v] of fd.entries()) {
      if (v === null || String(v).trim() === '') continue;
      params.set(k, String(v));
    }
    return params;
  }

  function syncUrl(params, { push } = { push: true }) {
    const clean = new URLSearchParams(params);
    clean.delete('format');
    clean.delete('ajax');
    const qs = clean.toString();
    const next = '/interview-prep' + (qs ? '?' + qs : '');
    if (push) {
      window.history.pushState({ interviewPrep: true }, '', next);
    } else {
      window.history.replaceState({ interviewPrep: true }, '', next);
    }
  }

  function applyFormFromParams(form, params) {
    const keys = [
      'q', 'industry', 'occupation', 'specialization', 'skill', 'technology',
      'type', 'difficulty', 'level', 'stage', 'language', 'favorite', 'practiced', 'page',
    ];
    keys.forEach((k) => {
      const el = form.elements.namedItem(k);
      if (!el || el instanceof RadioNodeList) return;
      el.value = params.get(k) || '';
    });
  }

  async function refreshCascade(cfg, { clearOcc, clearSpec } = {}) {
    const ind = document.querySelector(cfg.industry);
    const occ = document.querySelector(cfg.occupation);
    const spec = document.querySelector(cfg.specialization);
    const skill = cfg.skill ? document.querySelector(cfg.skill) : null;
    const tech = cfg.technology ? document.querySelector(cfg.technology) : null;
    if (clearOcc && occ) occ.value = '';
    if ((clearOcc || clearSpec) && spec) spec.value = '';
    if (clearOcc || clearSpec) {
      if (skill) skill.value = '';
      if (tech) tech.value = '';
    }
    const params = new URLSearchParams();
    if (ind && ind.value) params.set('industry', ind.value);
    if (occ && occ.value) params.set('occupation', occ.value);
    if (spec && spec.value) params.set('specialization', spec.value);
    const url = (cfg.endpoint || '/interview-prep-taxonomy.php') + '?' + params.toString();
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!res.ok) return;
    const data = await res.json();
    fillSelect(occ, data.occupations, data.occupation || '', 'All occupations');
    fillSelect(spec, data.specializations, data.specialization || '', 'All specializations');
    if (skill) fillSelect(skill, data.skills, skill.value, 'All skills');
    if (tech) fillSelect(tech, data.technologies, tech.value, 'All technologies');
  }

  async function loadList(cfg, params, { push = true } = {}) {
    const results = cfg.resultsEl;
    if (!results) {
      syncUrl(params, { push });
      return;
    }
    const loading = results.querySelector('[data-interview-loading]');
    const mySeq = ++cfg._seq;
    if (cfg._abort) cfg._abort.abort();
    cfg._abort = new AbortController();
    if (loading) {
      loading.hidden = false;
      loading.setAttribute('aria-hidden', 'false');
    }
    results.classList.add('is-loading');
    const req = new URLSearchParams(params);
    req.set('format', 'json');
    try {
      const res = await fetch((cfg.listUrl || '/interview-prep') + '?' + req.toString(), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: cfg._abort.signal,
      });
      const json = await res.json();
      if (mySeq !== cfg._seq) return;
      if (!json || !json.ok || typeof json.html !== 'string') throw new Error('bad response');
      const panel = results.querySelector('[data-interview-results-panel]');
      if (panel) panel.outerHTML = json.html;
      else results.insertAdjacentHTML('beforeend', json.html);
      // Re-sync selects from validated server filters
      if (json.filters && cfg.formEl) {
        if (json.filters.occupation !== undefined) {
          const occ = document.querySelector(cfg.occupation);
          if (occ) occ.value = json.filters.occupation || '';
        }
        if (json.filters.specialization !== undefined) {
          const spec = document.querySelector(cfg.specialization);
          if (spec) spec.value = json.filters.specialization || '';
        }
        if (json.filters.skill !== undefined) {
          const skill = cfg.skill ? document.querySelector(cfg.skill) : null;
          if (skill) skill.value = json.filters.skill || '';
        }
        if (json.filters.technology !== undefined) {
          const tech = cfg.technology ? document.querySelector(cfg.technology) : null;
          if (tech) tech.value = json.filters.technology || '';
        }
      }
      if (json.cascade) {
        fillSelect(document.querySelector(cfg.occupation), json.cascade.occupations, json.filters?.occupation || '', 'All occupations');
        fillSelect(document.querySelector(cfg.specialization), json.cascade.specializations, json.filters?.specialization || '', 'All specializations');
        if (cfg.skill) fillSelect(document.querySelector(cfg.skill), json.cascade.skills, json.filters?.skill || '', 'All skills');
        if (cfg.technology) fillSelect(document.querySelector(cfg.technology), json.cascade.technologies, json.filters?.technology || '', 'All technologies');
      }
      syncUrl(params, { push });
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      syncUrl(params, { push });
      window.location.href = '/interview-prep?' + new URLSearchParams(params).toString();
    } finally {
      if (mySeq === cfg._seq) {
        if (loading) {
          loading.hidden = true;
          loading.setAttribute('aria-hidden', 'true');
        }
        results.classList.remove('is-loading');
      }
    }
  }

  window.InterviewCascade = {
    bind(cfg) {
      cfg._seq = 0;
      cfg.formEl = cfg.form ? document.querySelector(cfg.form) : null;
      cfg.resultsEl = cfg.results ? document.querySelector(cfg.results) : null;
      const ind = document.querySelector(cfg.industry);
      const occ = document.querySelector(cfg.occupation);
      const spec = document.querySelector(cfg.specialization);
      const form = cfg.formEl;

      const apply = async (opts = {}) => {
        if (opts.clearOcc || opts.clearSpec) {
          await refreshCascade(cfg, opts);
        } else if (opts.refreshOnly) {
          await refreshCascade(cfg, opts);
          return;
        } else {
          await refreshCascade(cfg, {});
        }
        if (!cfg.autoApply || !form) return;
        const params = formParams(form);
        params.set('page', '1');
        await loadList(cfg, params, { push: opts.push !== false });
      };

      ind?.addEventListener('change', () => apply({ clearOcc: true }));
      occ?.addEventListener('change', () => apply({ clearSpec: true }));
      spec?.addEventListener('change', () => apply({}));

      // Non-cascade filters: update list + URL without full reload
      if (form && cfg.autoApply) {
        form.addEventListener('change', (ev) => {
          const t = ev.target;
          if (!t || !t.name) return;
          if (['industry', 'occupation', 'specialization'].includes(t.name)) return;
          const params = formParams(form);
          params.set('page', '1');
          // skill/tech still need cascade refresh of sibling options sometimes
          if (t.name === 'skill' || t.name === 'technology') {
            refreshCascade(cfg, {}).then(() => loadList(cfg, params));
          } else {
            loadList(cfg, params);
          }
        });
        form.addEventListener('submit', (ev) => {
          ev.preventDefault();
          const params = formParams(form);
          params.set('page', '1');
          loadList(cfg, params);
        });
        cfg.resultsEl?.addEventListener('click', (ev) => {
          const link = ev.target.closest('[data-interview-page]');
          if (!link || !cfg.resultsEl.contains(link)) return;
          ev.preventDefault();
          const page = link.getAttribute('data-interview-page') || '1';
          const params = formParams(form);
          params.set('page', page);
          loadList(cfg, params);
        });
        window.addEventListener('popstate', async () => {
          const params = new URLSearchParams(window.location.search);
          applyFormFromParams(form, params);
          await refreshCascade(cfg, {});
          await loadList(cfg, formParams(form), { push: false });
        });
      } else {
        // Admin / cascade-only mode
        ind?.addEventListener('change', () => refreshCascade(cfg, { clearOcc: true }));
        occ?.addEventListener('change', () => refreshCascade(cfg, { clearSpec: true }));
        spec?.addEventListener('change', () => refreshCascade(cfg, {}));
      }
    },
  };
})();
