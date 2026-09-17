/**
 * KaamFit Resume Studio — autosave, section editors, hybrid preview, design panel.
 */
(function () {
  "use strict";

  const root = document.getElementById("resumeStudio");
  if (!root) return;

  const apiUrl = root.dataset.api || "/resume-api.php";
  const csrf = root.dataset.csrf || "";
  const saveEl = root.querySelector("[data-rb-save-state]");
  const sectionList = root.querySelector("[data-rb-sortable]");
  const editorEl = root.querySelector("[data-rb-editor]");
  const iframe = root.querySelector("[data-rb-preview]");
  const zoomLabel = root.querySelector("[data-rb-zoom-label]");
  const pageCountEl = root.querySelector("[data-rb-page-count]");

  let state = null;
  let selectedKey = "personal";
  let saveTimer = null;
  let versionTimer = null;
  let dirty = false;
  let zoom = 1;
  let dragId = null;

  function setSaveState(mode, msg) {
    if (!saveEl) return;
    saveEl.classList.remove("is-saving", "is-saved", "is-error");
    if (mode === "saving") {
      saveEl.classList.add("is-saving");
      saveEl.textContent = "Saving…";
    } else if (mode === "error") {
      saveEl.classList.add("is-error");
      saveEl.textContent = msg || "Unable to save";
    } else {
      saveEl.classList.add("is-saved");
      saveEl.textContent = "Saved ✓";
    }
  }

  async function api(action, payload) {
    const body = Object.assign({ action: action, _csrf: csrf }, payload || {});
    const res = await fetch(apiUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-Token": csrf,
      },
      credentials: "same-origin",
      body: JSON.stringify(body),
    });
    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error("Invalid server response");
    }
    if (!res.ok || data.ok === false) {
      throw new Error((data && data.error) || "Request failed");
    }
    return data;
  }

  function scheduleSave(fn) {
    dirty = true;
    setSaveState("saving");
    clearTimeout(saveTimer);
    saveTimer = setTimeout(async () => {
      try {
        await fn();
        dirty = false;
        setSaveState("saved");
        scheduleVersionFlush();
      } catch (err) {
        setSaveState("error", err.message || "Unable to save");
      }
    }, 700);
  }

  function scheduleVersionFlush() {
    clearTimeout(versionTimer);
    versionTimer = setTimeout(async () => {
      try {
        await api("save_version", {});
      } catch (e) {
        /* keep live tables; version flush is best-effort after field saves */
      }
    }, 1500);
  }

  function escapeHtml(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function sectionByKey(key) {
    return (state.sections || []).find((s) => s.section_key === key);
  }

  function renderSectionList() {
    if (!sectionList || !state) return;
    const items = [
      { key: "personal", title: "Personal", id: null, fixed: true },
    ];
    (state.sections || []).forEach((s) => {
      items.push({
        key: s.section_key,
        title: s.title || s.section_key,
        id: s.id,
        visible: s.visible,
        fixed: false,
      });
    });
    if (!(state.sections || []).some((s) => s.section_key === "experience")) {
      items.splice(2, 0, { key: "experience", title: "Work Experience", id: null, fixed: true });
    }

    sectionList.innerHTML = items
      .map((item) => {
        const active = selectedKey === item.key ? " is-active" : "";
        const handle = item.fixed && item.key === "personal" ? "" : `<button type="button" class="rb-drag" data-rb-drag="${item.id || ""}" aria-label="Drag to reorder" title="Drag">☰</button>`;
        const moveBtns =
          item.id
            ? `<button type="button" class="rb-icon-btn" data-rb-move-up="${item.id}" aria-label="Move up">↑</button>
               <button type="button" class="rb-icon-btn" data-rb-move-down="${item.id}" aria-label="Move down">↓</button>`
            : "";
        return `<li class="rb-section-item${active}" data-rb-section-key="${escapeHtml(item.key)}" data-section-id="${item.id || ""}" draggable="${item.id ? "true" : "false"}">
          ${handle}
          <button type="button" class="rb-section-item__btn" data-rb-select="${escapeHtml(item.key)}">${escapeHtml(item.title)}</button>
          ${moveBtns}
        </li>`;
      })
      .join("");
  }

  function renderEditor() {
    if (!editorEl || !state) return;
    if (selectedKey === "personal") {
      const p = state.profile || {};
      const links = Array.isArray(p.links) ? p.links : [];
      editorEl.innerHTML = `
        <h2 class="rb-editor-title">Personal Information</h2>
        <div class="rb-form-grid">
          <label>Full name<input class="form-control form-control-sm" data-rb-profile="full_name" value="${escapeHtml(p.full_name || "")}"></label>
          <label>Title / role<input class="form-control form-control-sm" data-rb-profile="title" value="${escapeHtml(p.title || "")}"></label>
          <label>Email<input class="form-control form-control-sm" type="email" data-rb-profile="email" value="${escapeHtml(p.email || "")}"></label>
          <label>Phone<input class="form-control form-control-sm" data-rb-profile="phone" value="${escapeHtml(p.phone || "")}"></label>
          <label>Location<input class="form-control form-control-sm" data-rb-profile="location" value="${escapeHtml(p.location || "")}"></label>
          <label>Country<input class="form-control form-control-sm" data-rb-profile="country" value="${escapeHtml(p.country || "")}"></label>
          <label>Nationality<input class="form-control form-control-sm" data-rb-profile="nationality" value="${escapeHtml(p.nationality || "")}"></label>
          <label>Date of birth<input class="form-control form-control-sm" type="date" data-rb-profile="date_of_birth" value="${escapeHtml(p.date_of_birth || "")}"></label>
        </div>
        <div class="mt-3" data-rb-links>
          <p class="rb-panel-label">Links</p>
          ${links
            .map(
              (l, i) => `<div class="rb-link-row" data-link-i="${i}">
              <input class="form-control form-control-sm" data-rb-link-label placeholder="Label" value="${escapeHtml(l.label || "")}">
              <input class="form-control form-control-sm" data-rb-link-url placeholder="URL" value="${escapeHtml(l.url || "")}">
            </div>`
            )
            .join("")}
          <button type="button" class="btn btn-sm btn-outline-secondary mt-1" data-rb-add-link>+ Link</button>
        </div>`;
      bindProfileFields();
      return;
    }

    if (selectedKey === "experience") {
      renderExperienceEditor();
      return;
    }

    const section = sectionByKey(selectedKey);
    if (!section) {
      editorEl.innerHTML = `<p class="text-secondary">Section not found.</p>`;
      return;
    }

    if (section.section_key === "summary" || section.body_format === "plain" && !section.structured) {
      // Prefer structured text editor when structured text; else textarea for plain.
      if (section.structured && section.structured.kind === "text") {
        editorEl.innerHTML = `
          <div class="d-flex justify-content-between align-items-center gap-2">
            <h2 class="rb-editor-title mb-0">${escapeHtml(section.title)}</h2>
            <button type="button" class="btn btn-sm btn-outline-danger" data-rb-remove-section="${section.id}">Remove</button>
          </div>
          <label class="form-label mt-2">Title<input class="form-control form-control-sm" data-rb-sec-title value="${escapeHtml(section.title)}"></label>
          <textarea class="form-control mt-2" rows="10" data-rb-sec-text>${escapeHtml(section.structured.text || "")}</textarea>`;
        bindTextSection(section);
        return;
      }
      if (!section.structured) {
        editorEl.innerHTML = `
          <div class="d-flex justify-content-between align-items-center gap-2">
            <h2 class="rb-editor-title mb-0">${escapeHtml(section.title)}</h2>
            <button type="button" class="btn btn-sm btn-outline-danger" data-rb-remove-section="${section.id}">Remove</button>
          </div>
          <label class="form-label mt-2">Title<input class="form-control form-control-sm" data-rb-sec-title value="${escapeHtml(section.title)}"></label>
          <textarea class="form-control mt-2" rows="12" data-rb-sec-plain>${escapeHtml(section.body || "")}</textarea>
          <p class="small text-secondary mt-1">Plain-text section (existing format preserved).</p>`;
        bindPlainSection(section);
        return;
      }
    }

    if (section.structured && section.structured.kind === "skills") {
      renderSkillsEditor(section);
      return;
    }

    if (section.structured && section.structured.kind === "entries") {
      renderEntriesEditor(section);
      return;
    }

    if (section.structured && section.structured.kind === "text") {
      editorEl.innerHTML = `
        <div class="d-flex justify-content-between align-items-center gap-2">
          <h2 class="rb-editor-title mb-0">${escapeHtml(section.title)}</h2>
          <button type="button" class="btn btn-sm btn-outline-danger" data-rb-remove-section="${section.id}">Remove</button>
        </div>
        <label class="form-label mt-2">Title<input class="form-control form-control-sm" data-rb-sec-title value="${escapeHtml(section.title)}"></label>
        <textarea class="form-control mt-2" rows="10" data-rb-sec-text>${escapeHtml(section.structured.text || "")}</textarea>`;
      bindTextSection(section);
      return;
    }

    // Fallback plain
    editorEl.innerHTML = `
      <h2 class="rb-editor-title">${escapeHtml(section.title)}</h2>
      <textarea class="form-control" rows="12" data-rb-sec-plain>${escapeHtml(section.body || "")}</textarea>`;
    bindPlainSection(section);
  }

  function bindProfileFields() {
    editorEl.querySelectorAll("[data-rb-profile]").forEach((input) => {
      input.addEventListener("input", () => {
        const key = input.getAttribute("data-rb-profile");
        const val = input.value;
        state.profile[key] = val;
        patchPreviewProfile(key, val);
        scheduleSave(() => api("patch_profile", { fields: { [key]: val } }));
      });
    });
    const saveLinks = () => {
      const rows = [...editorEl.querySelectorAll(".rb-link-row")];
      const links = rows.map((row) => ({
        label: (row.querySelector("[data-rb-link-label]") || {}).value || "",
        url: (row.querySelector("[data-rb-link-url]") || {}).value || "",
      }));
      state.profile.links = links;
      scheduleSave(() => api("patch_profile", { fields: { links: links } }));
    };
    editorEl.querySelectorAll("[data-rb-link-label],[data-rb-link-url]").forEach((el) => {
      el.addEventListener("input", saveLinks);
    });
    const addBtn = editorEl.querySelector("[data-rb-add-link]");
    if (addBtn) {
      addBtn.addEventListener("click", () => {
        state.profile.links = state.profile.links || [];
        state.profile.links.push({ label: "", url: "" });
        renderEditor();
      });
    }
    const removeBtn = editorEl.querySelector("[data-rb-remove-section]");
    if (removeBtn) bindRemove(removeBtn);
  }

  function bindPlainSection(section) {
    const title = editorEl.querySelector("[data-rb-sec-title]");
    const ta = editorEl.querySelector("[data-rb-sec-plain]");
    if (title) {
      title.addEventListener("input", () => {
        section.title = title.value;
        scheduleSave(() => api("patch_section", { id: section.id, fields: { title: title.value } }));
        renderSectionList();
      });
    }
    if (ta) {
      ta.addEventListener("input", () => {
        section.body = ta.value;
        section.structured = null;
        section.body_format = "plain";
        patchPreviewSectionText(section.section_key, ta.value);
        scheduleSave(() => api("patch_section", { id: section.id, fields: { body: ta.value } }));
      });
    }
    const removeBtn = editorEl.querySelector("[data-rb-remove-section]");
    if (removeBtn) bindRemove(removeBtn);
  }

  function bindTextSection(section) {
    const title = editorEl.querySelector("[data-rb-sec-title]");
    const ta = editorEl.querySelector("[data-rb-sec-text]");
    if (title) {
      title.addEventListener("input", () => {
        section.title = title.value;
        scheduleSave(() => api("patch_section", { id: section.id, fields: { title: title.value } }));
        renderSectionList();
      });
    }
    if (ta) {
      ta.addEventListener("input", () => {
        const structured = { text: ta.value };
        section.structured = { schema_version: 1, kind: "text", text: ta.value };
        patchPreviewSectionText(section.section_key, ta.value);
        scheduleSave(() =>
          api("patch_section", { id: section.id, fields: { structured: structured } })
        );
      });
    }
    const removeBtn = editorEl.querySelector("[data-rb-remove-section]");
    if (removeBtn) bindRemove(removeBtn);
  }

  function bindRemove(btn) {
    btn.addEventListener("click", async () => {
      const id = parseInt(btn.getAttribute("data-rb-remove-section"), 10);
      if (!id || !confirm("Remove this section?")) return;
      try {
        setSaveState("saving");
        await api("remove_section", { id: id });
        await reloadState();
        selectedKey = "personal";
        renderSectionList();
        renderEditor();
        refreshIframe();
        setSaveState("saved");
      } catch (err) {
        setSaveState("error", err.message);
      }
    });
  }

  function renderExperienceEditor() {
    const jobs = state.experiences || [];
    editorEl.innerHTML = `
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="rb-editor-title mb-0">Work Experience</h2>
        <button type="button" class="btn btn-sm btn-primary" data-rb-add-exp>+ Add</button>
      </div>
      <div class="rb-exp-list" data-rb-exp-list>
        ${jobs
          .map(
            (job, idx) => `
          <details class="rb-exp-card" data-exp-id="${job.id}" ${idx === 0 ? "open" : ""}>
            <summary>${escapeHtml(job.position || "Role")} — ${escapeHtml(job.company || "Company")}</summary>
            <div class="rb-form-grid mt-2">
              <label>Job title<input class="form-control form-control-sm" data-exp-field="position" value="${escapeHtml(job.position)}"></label>
              <label>Company<input class="form-control form-control-sm" data-exp-field="company" value="${escapeHtml(job.company)}"></label>
              <label>City / location<input class="form-control form-control-sm" data-exp-field="location" value="${escapeHtml(job.location)}"></label>
              <label>Start<input class="form-control form-control-sm" data-exp-field="start_date" value="${escapeHtml(job.start_date)}" placeholder="2023-01"></label>
              <label>End<input class="form-control form-control-sm" data-exp-field="end_date" value="${escapeHtml(job.end_date)}" placeholder="Present"></label>
            </div>
            <label class="form-label mt-2">Description (one bullet per line)
              <textarea class="form-control" rows="5" data-exp-field="bullets">${escapeHtml(job.bullets)}</textarea>
            </label>
            <div class="d-flex gap-2 mt-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-exp-dup="${job.id}">Duplicate</button>
              <button type="button" class="btn btn-sm btn-outline-danger" data-exp-del="${job.id}">Delete</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-exp-up="${job.id}">↑</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-exp-down="${job.id}">↓</button>
            </div>
          </details>`
          )
          .join("")}
      </div>`;

    editorEl.querySelector("[data-rb-add-exp]").addEventListener("click", async () => {
      try {
        await api("add_experience", { fields: {} });
        await reloadState();
        renderEditor();
        refreshIframe();
      } catch (err) {
        setSaveState("error", err.message);
      }
    });

    editorEl.querySelectorAll("[data-exp-id]").forEach((card) => {
      const id = parseInt(card.getAttribute("data-exp-id"), 10);
      card.querySelectorAll("[data-exp-field]").forEach((input) => {
        input.addEventListener("input", () => {
          const field = input.getAttribute("data-exp-field");
          const job = state.experiences.find((j) => j.id === id);
          if (job) job[field] = input.value;
          patchPreviewExperience();
          scheduleSave(() => api("patch_experience", { id: id, fields: { [field]: input.value } }));
        });
      });
    });

    editorEl.querySelectorAll("[data-exp-dup]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        await api("duplicate_experience", { id: parseInt(btn.getAttribute("data-exp-dup"), 10) });
        await reloadState();
        renderEditor();
        refreshIframe();
      });
    });
    editorEl.querySelectorAll("[data-exp-del]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        if (!confirm("Delete this experience entry?")) return;
        await api("delete_experience", { id: parseInt(btn.getAttribute("data-exp-del"), 10) });
        await reloadState();
        renderEditor();
        refreshIframe();
      });
    });
    editorEl.querySelectorAll("[data-exp-up],[data-exp-down]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = parseInt(btn.getAttribute("data-exp-up") || btn.getAttribute("data-exp-down"), 10);
        const ids = state.experiences.map((j) => j.id);
        const i = ids.indexOf(id);
        if (i < 0) return;
        const j = btn.hasAttribute("data-exp-up") ? i - 1 : i + 1;
        if (j < 0 || j >= ids.length) return;
        const tmp = ids[i];
        ids[i] = ids[j];
        ids[j] = tmp;
        await api("reorder_experiences", { ids: ids });
        await reloadState();
        renderEditor();
        refreshIframe();
      });
    });
  }

  function renderSkillsEditor(section) {
    const cats = (section.structured && section.structured.categories) || [];
    editorEl.innerHTML = `
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="rb-editor-title mb-0">${escapeHtml(section.title)}</h2>
        <button type="button" class="btn btn-sm btn-outline-danger" data-rb-remove-section="${section.id}">Remove</button>
      </div>
      <label class="form-label mt-2">Title<input class="form-control form-control-sm" data-rb-sec-title value="${escapeHtml(section.title)}"></label>
      <div data-rb-skill-cats>
        ${cats
          .map(
            (cat, ci) => `
          <div class="rb-skill-cat" data-cat-i="${ci}">
            <input class="form-control form-control-sm mb-1" data-cat-name placeholder="Category" value="${escapeHtml(cat.name || "")}">
            <textarea class="form-control" rows="2" data-cat-skills placeholder="Skills separated by ·">${escapeHtml(
              (cat.skills || []).map((s) => s.name + (s.level ? " (" + s.level + ")" : "")).join(" · ")
            )}</textarea>
            <button type="button" class="btn btn-sm btn-link text-danger px-0" data-cat-del="${ci}">Remove category</button>
          </div>`
          )
          .join("")}
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-rb-add-cat>+ Category</button>`;

    const persist = () => {
      const categories = [...editorEl.querySelectorAll(".rb-skill-cat")].map((row) => {
        const name = (row.querySelector("[data-cat-name]") || {}).value || "";
        const raw = (row.querySelector("[data-cat-skills]") || {}).value || "";
        const skills = raw
          .split(/\s*[·|,;]\s*/)
          .map((p) => p.trim())
          .filter(Boolean)
          .map((part) => {
            const m = part.match(/^(.+?)\s*\((.+)\)\s*$/);
            return m ? { name: m[1].trim(), level: m[2].trim() } : { name: part, level: "" };
          });
        return { name: name, skills: skills };
      });
      section.structured = { schema_version: 1, kind: "skills", categories: categories };
      patchPreviewSkills(categories);
      scheduleSave(() =>
        api("patch_section", { id: section.id, fields: { structured: { categories: categories } } })
      );
    };

    editorEl.querySelectorAll("[data-cat-name],[data-cat-skills]").forEach((el) => el.addEventListener("input", persist));
    editorEl.querySelector("[data-rb-add-cat]").addEventListener("click", () => {
      section.structured.categories = section.structured.categories || [];
      section.structured.categories.push({ name: "", skills: [] });
      renderSkillsEditor(section);
    });
    editorEl.querySelectorAll("[data-cat-del]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const i = parseInt(btn.getAttribute("data-cat-del"), 10);
        section.structured.categories.splice(i, 1);
        renderSkillsEditor(section);
        persist();
      });
    });
    const title = editorEl.querySelector("[data-rb-sec-title]");
    if (title) {
      title.addEventListener("input", () => {
        section.title = title.value;
        scheduleSave(() => api("patch_section", { id: section.id, fields: { title: title.value } }));
        renderSectionList();
      });
    }
    const removeBtn = editorEl.querySelector("[data-rb-remove-section]");
    if (removeBtn) bindRemove(removeBtn);
  }

  function renderEntriesEditor(section) {
    const isLang = section.section_key === "languages";
    const entries = (section.structured && section.structured.entries) || [];
    editorEl.innerHTML = `
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="rb-editor-title mb-0">${escapeHtml(section.title)}</h2>
        <button type="button" class="btn btn-sm btn-outline-danger" data-rb-remove-section="${section.id}">Remove</button>
      </div>
      <label class="form-label mt-2">Title<input class="form-control form-control-sm" data-rb-sec-title value="${escapeHtml(section.title)}"></label>
      <div data-rb-entries>
        ${entries
          .map((en, i) =>
            isLang
              ? `<div class="rb-entry-card" data-entry-i="${i}">
                  <div class="rb-form-grid">
                    <label>Language<input class="form-control form-control-sm" data-en="name" value="${escapeHtml(en.name || en.title || "")}"></label>
                    <label>Level<input class="form-control form-control-sm" data-en="level" value="${escapeHtml(en.level || "")}" placeholder="e.g. B1"></label>
                  </div>
                  <button type="button" class="btn btn-sm btn-link text-danger px-0" data-en-del="${i}">Remove</button>
                </div>`
              : `<div class="rb-entry-card" data-entry-i="${i}">
                  <div class="rb-form-grid">
                    <label>Title<input class="form-control form-control-sm" data-en="title" value="${escapeHtml(en.title || "")}"></label>
                    <label>Institution / org<input class="form-control form-control-sm" data-en="institution" value="${escapeHtml(en.institution || en.issuer || en.org || "")}"></label>
                    <label>Location<input class="form-control form-control-sm" data-en="location" value="${escapeHtml(en.location || "")}"></label>
                    <label>Start<input class="form-control form-control-sm" data-en="start" value="${escapeHtml(en.start || "")}"></label>
                    <label>End<input class="form-control form-control-sm" data-en="end" value="${escapeHtml(en.end || "")}"></label>
                  </div>
                  <label class="form-label mt-1">Description<textarea class="form-control" rows="2" data-en="description">${escapeHtml(en.description || "")}</textarea></label>
                  <button type="button" class="btn btn-sm btn-link text-danger px-0" data-en-del="${i}">Remove</button>
                </div>`
          )
          .join("")}
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-rb-add-entry>+ Entry</button>`;

    const persist = () => {
      const list = [...editorEl.querySelectorAll(".rb-entry-card")].map((card) => {
        const obj = { id: (entries[parseInt(card.getAttribute("data-entry-i"), 10)] || {}).id || "" };
        card.querySelectorAll("[data-en]").forEach((input) => {
          obj[input.getAttribute("data-en")] = input.value;
        });
        return obj;
      });
      section.structured = { schema_version: 1, kind: "entries", entries: list };
      scheduleSave(() =>
        api("patch_section", { id: section.id, fields: { structured: { entries: list } } })
      );
      // Soft preview: refresh after short delay for complex structures
      clearTimeout(renderEntriesEditor._t);
      renderEntriesEditor._t = setTimeout(refreshIframe, 900);
    };

    editorEl.querySelectorAll("[data-en]").forEach((el) => el.addEventListener("input", persist));
    editorEl.querySelector("[data-rb-add-entry]").addEventListener("click", () => {
      section.structured.entries = section.structured.entries || [];
      section.structured.entries.push(isLang ? { name: "", level: "" } : { title: "", institution: "", location: "", start: "", end: "", description: "" });
      renderEntriesEditor(section);
    });
    editorEl.querySelectorAll("[data-en-del]").forEach((btn) => {
      btn.addEventListener("click", () => {
        section.structured.entries.splice(parseInt(btn.getAttribute("data-en-del"), 10), 1);
        renderEntriesEditor(section);
        persist();
      });
    });
    const title = editorEl.querySelector("[data-rb-sec-title]");
    if (title) {
      title.addEventListener("input", () => {
        section.title = title.value;
        scheduleSave(() => api("patch_section", { id: section.id, fields: { title: title.value } }));
        renderSectionList();
      });
    }
    const removeBtn = editorEl.querySelector("[data-rb-remove-section]");
    if (removeBtn) bindRemove(removeBtn);
  }

  /* ---------- Hybrid preview helpers ---------- */

  function previewDoc() {
    try {
      return iframe && iframe.contentDocument;
    } catch (e) {
      return null;
    }
  }

  function patchPreviewProfile(key, val) {
    const doc = previewDoc();
    if (!doc) return;
    if (key === "full_name") {
      const el = doc.querySelector(".resume-name, .profile-name, h1");
      if (el) el.textContent = val;
    }
    if (key === "title") {
      const el = doc.querySelector(".resume-title, .profile-title");
      if (el) el.textContent = val;
    }
  }

  function patchPreviewSectionText(key, text) {
    const doc = previewDoc();
    if (!doc) return;
        const section =
          doc.querySelector(`[data-section-key="${key}"] .resume-body`) ||
          doc.querySelector(`[data-section="${key}"] .resume-body`) ||
          doc.querySelector(`[data-preview-section="${key}"]`);
    if (section) {
      section.textContent = text;
    } else if (key === "summary") {
      const body = doc.querySelector(".resume-section-summary .resume-body, [data-section=\"summary\"] .resume-body");
      if (body) body.textContent = text;
    }
  }

  function patchPreviewSkills(categories) {
    const doc = previewDoc();
    if (!doc) return;
    const rootEl = doc.querySelector("[data-preview-skills]");
    if (!rootEl) {
      refreshIframe();
      return;
    }
    rootEl.innerHTML = categories
      .map((cat) => {
        const items = (cat.skills || [])
          .map((s) => s.name + (s.level ? " (" + s.level + ")" : ""))
          .join(" · ");
        return `<div class="skills-group">${cat.name ? `<h3 class="skills-heading">${escapeHtml(cat.name)}</h3>` : ""}<p class="skills-items">${escapeHtml(items)}</p></div>`;
      })
      .join("");
  }

  function patchPreviewExperience() {
    // Complex layout — debounce iframe refresh
    clearTimeout(patchPreviewExperience._t);
    patchPreviewExperience._t = setTimeout(refreshIframe, 1000);
  }

  function refreshIframe() {
    if (!iframe || !state) return;
    const meta = state.meta || {};
    const params = new URLSearchParams({
      embed: "1",
      pdf: "1",
      theme: meta.template || "modern_de",
      density: meta.density || "normal",
      _ts: String(Date.now()),
    });
    const vid = root.dataset.versionId;
    if (vid && vid !== "0") params.set("version", vid);
    iframe.src = (root.dataset.previewBase || "/resume") + "?" + params.toString();
  }

  function applyZoom() {
    if (!iframe) return;
    iframe.style.transform = "scale(" + zoom + ")";
    iframe.style.transformOrigin = "top center";
    if (zoomLabel) zoomLabel.textContent = Math.round(zoom * 100) + "%";
    updatePageCount();
  }

  function updatePageCount() {
    const doc = previewDoc();
    if (!doc || !pageCountEl) return;
    const article = doc.querySelector(".resume");
    if (!article) {
      pageCountEl.textContent = "Page —";
      return;
    }
    const h = article.scrollHeight || 0;
    const pageH = 1123; // approx A4 @ 96dpi
    const pages = Math.max(1, Math.ceil(h / pageH));
    pageCountEl.textContent = "Page 1 / " + pages;
  }

  async function reloadState() {
    const data = await api("get_state", {});
    state = data;
    if (data.active_version && data.active_version.id) {
      root.dataset.versionId = String(data.active_version.id);
    }
  }

  /* ---------- Events ---------- */

  sectionList.addEventListener("click", (e) => {
    const sel = e.target.closest("[data-rb-select]");
    if (sel) {
      selectedKey = sel.getAttribute("data-rb-select");
      renderSectionList();
      renderEditor();
      return;
    }
    const up = e.target.closest("[data-rb-move-up]");
    const down = e.target.closest("[data-rb-move-down]");
    if (up || down) {
      const id = parseInt((up || down).getAttribute(up ? "data-rb-move-up" : "data-rb-move-down"), 10);
      const ids = state.sections.map((s) => s.id);
      const i = ids.indexOf(id);
      const j = up ? i - 1 : i + 1;
      if (i < 0 || j < 0 || j >= ids.length) return;
      const tmp = ids[i];
      ids[i] = ids[j];
      ids[j] = tmp;
      scheduleSave(async () => {
        await api("reorder_sections", { ids: ids });
        await reloadState();
        renderSectionList();
        refreshIframe();
      });
    }
  });

  sectionList.addEventListener("dragstart", (e) => {
    const li = e.target.closest("[data-section-id]");
    if (!li || !li.getAttribute("data-section-id")) return;
    dragId = li.getAttribute("data-section-id");
    e.dataTransfer.effectAllowed = "move";
  });
  sectionList.addEventListener("dragover", (e) => {
    e.preventDefault();
  });
  sectionList.addEventListener("drop", (e) => {
    e.preventDefault();
    const li = e.target.closest("[data-section-id]");
    if (!li || !dragId) return;
    const targetId = li.getAttribute("data-section-id");
    if (!targetId || targetId === dragId) return;
    const ids = state.sections.map((s) => s.id);
    const from = ids.indexOf(parseInt(dragId, 10));
    const to = ids.indexOf(parseInt(targetId, 10));
    if (from < 0 || to < 0) return;
    ids.splice(to, 0, ids.splice(from, 1)[0]);
    scheduleSave(async () => {
      await api("reorder_sections", { ids: ids });
      await reloadState();
      renderSectionList();
      refreshIframe();
    });
    dragId = null;
  });

  // Design panel
  root.querySelectorAll("[data-rb-template]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const template = btn.getAttribute("data-rb-template");
      root.querySelectorAll("[data-rb-template]").forEach((b) => {
        b.classList.toggle("is-active", b === btn);
        b.setAttribute("aria-pressed", b === btn ? "true" : "false");
      });
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: { template: template } });
        state.meta = res.meta;
        refreshIframe();
      });
    });
  });

  root.querySelectorAll("[data-rb-accent]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const accent = btn.getAttribute("data-rb-accent");
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: { accent_color: accent } });
        state.meta = res.meta;
        refreshIframe();
      });
    });
  });
  const customAccent = root.querySelector("[data-rb-accent-custom]");
  if (customAccent) {
    customAccent.addEventListener("change", () => {
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: { accent_color: customAccent.value } });
        state.meta = res.meta;
        refreshIframe();
      });
    });
  }

  const fontSel = root.querySelector("[data-rb-font]");
  if (fontSel) {
    fontSel.addEventListener("change", () => {
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: { font_family: fontSel.value } });
        state.meta = res.meta;
        refreshIframe();
      });
    });
  }

  root.querySelectorAll("[data-rb-font-size]").forEach((btn) => {
    btn.addEventListener("click", () => {
      root.querySelectorAll("[data-rb-font-size]").forEach((b) => b.classList.toggle("active", b === btn));
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: { font_size: btn.getAttribute("data-rb-font-size") } });
        state.meta = res.meta;
        refreshIframe();
      });
    });
  });

  ["density", "page-format", "margin", "date-format", "photo-mode"].forEach((name) => {
    const el = root.querySelector("[data-rb-" + name + "]");
    if (!el) return;
    const fieldMap = {
      density: "density",
      "page-format": "page_format",
      margin: "margin",
      "date-format": "date_format",
      "photo-mode": "photo_mode",
    };
    el.addEventListener("change", () => {
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: { [fieldMap[name]]: el.value } });
        state.meta = res.meta;
        refreshIframe();
      });
    });
  });

  // Zoom
  root.querySelector("[data-rb-zoom='-']")?.addEventListener("click", () => {
    zoom = Math.max(0.5, zoom - 0.1);
    applyZoom();
  });
  root.querySelector("[data-rb-zoom='+']")?.addEventListener("click", () => {
    zoom = Math.min(1.4, zoom + 0.1);
    applyZoom();
  });
  root.querySelector("[data-rb-zoom-fit]")?.addEventListener("click", () => {
    zoom = 0.72;
    applyZoom();
  });
  iframe?.addEventListener("load", () => {
    applyZoom();
    updatePageCount();
  });

  // Mobile tabs
  function setMobileTab(tab) {
    root.querySelectorAll("[data-rb-panel]").forEach((p) => {
      p.classList.toggle("is-active", p.getAttribute("data-rb-panel") === tab);
    });
    root.querySelectorAll("[data-rb-mobile-tab]").forEach((b) => {
      const on = b.getAttribute("data-rb-mobile-tab") === tab;
      b.classList.toggle("is-active", on);
      b.setAttribute("aria-selected", on ? "true" : "false");
    });
  }
  root.querySelectorAll("[data-rb-mobile-tab]").forEach((btn) => {
    btn.addEventListener("click", () => setMobileTab(btn.getAttribute("data-rb-mobile-tab")));
  });

  // Add section modal
  const addForm = document.querySelector("[data-rb-add-section]");
  const typeSel = document.getElementById("rbAddType");
  const customWrap = document.querySelector("[data-rb-custom-title-wrap]");
  if (typeSel && customWrap) {
    typeSel.addEventListener("change", () => {
      customWrap.hidden = typeSel.value !== "custom";
    });
  }
  if (addForm) {
    addForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(addForm);
      try {
        await api("add_section", { type: fd.get("type"), title: fd.get("title") || "" });
        await reloadState();
        renderSectionList();
        const modal = bootstrap.Modal.getInstance(document.getElementById("rbAddSectionModal"));
        if (modal) modal.hide();
        refreshIframe();
      } catch (err) {
        alert(err.message || "Could not add section");
      }
    });
  }

  // Duplicate
  const dupForm = document.querySelector("[data-rb-duplicate]");
  if (dupForm) {
    dupForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(dupForm);
      try {
        const res = await api("duplicate_resume", {
          id: parseInt(root.dataset.versionId || "0", 10),
          title: fd.get("title"),
          copy_content: !!fd.get("copy_content"),
          copy_section_order: !!fd.get("copy_section_order"),
          copy_design: !!fd.get("copy_design"),
        });
        if (res.resume_id) {
          window.location.href = "/resume-edit?version=" + res.resume_id;
        }
      } catch (err) {
        alert(err.message || "Duplicate failed");
      }
    });
  }

  // Tailor
  const tailorForm = document.querySelector("[data-rb-tailor]");
  const analyzeBtn = document.querySelector("[data-rb-tailor-analyze]");
  if (analyzeBtn && tailorForm) {
    analyzeBtn.addEventListener("click", async () => {
      const fd = new FormData(tailorForm);
      try {
        const res = await api("tailor_preview", {
          company: fd.get("company"),
          role: fd.get("role"),
          jd: fd.get("jd"),
        });
        const box = tailorForm.querySelector("[data-rb-tailor-preview]");
        const kw = tailorForm.querySelector("[data-rb-tailor-keywords]");
        if (box) box.hidden = false;
        if (kw) {
          kw.textContent =
            "Keywords: " + ((res.keywords || []).join(", ") || "(none detected from known list)");
        }
        const skills = document.getElementById("rbTailorSkills");
        if (skills && res.suggestions && res.suggestions.skills) {
          skills.value = res.suggestions.skills.suggested || res.suggestions.skills.current || "";
        }
        const summary = document.getElementById("rbTailorSummary");
        if (summary && res.suggestions && res.suggestions.summary) {
          summary.value = res.suggestions.summary.current || "";
        }
      } catch (err) {
        alert(err.message || "Analyze failed");
      }
    });
  }
  if (tailorForm) {
    tailorForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(tailorForm);
      const overrides = {};
      if (fd.get("summary")) overrides.summary = fd.get("summary");
      if (fd.get("skills")) overrides.skills = fd.get("skills");
      try {
        const res = await api("tailor_apply", {
          company: fd.get("company"),
          role: fd.get("role"),
          location: fd.get("location"),
          jd: fd.get("jd"),
          link: fd.get("link") || "",
          overrides: overrides,
        });
        if (res.resume_id) {
          window.location.href = "/resume-edit?version=" + res.resume_id;
        }
      } catch (err) {
        alert(err.message || "Tailor failed");
      }
    });
  }

  window.addEventListener("beforeunload", (e) => {
    if (dirty) {
      e.preventDefault();
      e.returnValue = "";
    }
  });

  // Boot
  (async function init() {
    try {
      await reloadState();
      // Soft-upgrade: if plain education/skills/languages and empty structured, keep plain.
      renderSectionList();
      renderEditor();
      setSaveState("saved");
      applyZoom();
    } catch (err) {
      setSaveState("error", err.message || "Failed to load");
    }
  })();
})();
