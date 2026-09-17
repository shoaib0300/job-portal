(function () {
  function fillSelect(sel, items, selected, allLabel) {
    if (!sel) return;
    const keep = selected || '';
    sel.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = allLabel || 'All';
    sel.appendChild(opt0);
    (items || []).forEach((it) => {
      const o = document.createElement('option');
      o.value = it.slug;
      o.textContent = it.name_en || it.slug;
      if (keep && keep === it.slug) o.selected = true;
      sel.appendChild(o);
    });
    if (keep && ![...sel.options].some((o) => o.value === keep && o.value !== '')) {
      sel.value = '';
    }
  }

  async function refresh(cfg) {
    const ind = document.querySelector(cfg.industry);
    const occ = document.querySelector(cfg.occupation);
    const spec = document.querySelector(cfg.specialization);
    const skill = cfg.skill ? document.querySelector(cfg.skill) : null;
    const tech = cfg.technology ? document.querySelector(cfg.technology) : null;
    const params = new URLSearchParams();
    if (ind && ind.value) params.set('industry', ind.value);
    if (occ && occ.value) params.set('occupation', occ.value);
    if (spec && spec.value) params.set('specialization', spec.value);
    const url = (cfg.endpoint || '/interview-prep-taxonomy.php') + '?' + params.toString();
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) return;
    const data = await res.json();
    fillSelect(occ, data.occupations, data.occupation || (occ && occ.value), 'All occupations');
    fillSelect(spec, data.specializations, data.specialization || (spec && spec.value), 'All specializations');
    if (skill) fillSelect(skill, data.skills, skill.value, 'All skills');
    if (tech) fillSelect(tech, data.technologies, tech.value, 'All technologies');
  }

  window.InterviewCascade = {
    bind(cfg) {
      const ind = document.querySelector(cfg.industry);
      const occ = document.querySelector(cfg.occupation);
      const spec = document.querySelector(cfg.specialization);
      const onChange = (resetChild) => {
        if (resetChild === 'occ' && occ) occ.value = '';
        if (resetChild === 'occ' || resetChild === 'spec') {
          if (spec) spec.value = '';
        }
        refresh(cfg);
      };
      ind?.addEventListener('change', () => onChange('occ'));
      occ?.addEventListener('change', () => onChange('spec'));
      spec?.addEventListener('change', () => refresh(cfg));
    },
  };
})();
