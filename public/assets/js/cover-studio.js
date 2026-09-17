/**
 * KaamFit Cover Letter Studio — autosave, structured/plain editors, hybrid preview.
 */
(function () {
  "use strict";

  const root = document.getElementById("coverStudio");
  if (!root) return;

  const apiUrl = root.dataset.api || "/cover-api.php";
  const csrf = root.dataset.csrf || "";
  const saveEl = root.querySelector("[data-cl-save-state]");
  const editorEl = root.querySelector("[data-cl-editor]");
  const iframe = root.querySelector("[data-cl-preview]");
  const zoomLabel = root.querySelector("[data-cl-zoom-label]");

  let state = null;
  let nav = "body";
  let saveTimer = null;
  let dirty = false;
  let zoom = 1;

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
      } catch (err) {
        setSaveState("error", err.message || "Unable to save");
      }
    }, 700);
  }

  function escapeHtml(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function structuredToPlain(s) {
    if (!s || typeof s !== "object") return "";
    const parts = [];
    const date = String(s.date || "").trim();
    if (date) parts.push(date);
    const subject = String(s.subject || "").trim();
    if (subject) parts.push("Subject: " + subject);
    const greeting = String(s.greeting || "").trim();
    if (greeting) parts.push(greeting);
    (s.paragraphs || []).forEach((p) => {
      const text = String((p && p.text) || "").trim();
      if (text) parts.push(text);
    });
    const closing = String(s.closing || "").trim();
    if (closing) parts.push(closing);
    const signoff = String(s.signoff || "").trim();
    const signature = String(s.signature || "").trim();
    if (signoff || signature) {
      parts.push([signoff, signature].filter(Boolean).join("\n"));
    }
    return parts.join("\n\n");
  }

  /** Human-readable letter text for the plain editor (never raw JSON). */
  function plainTextForEditor() {
    if (state.structured) {
      return structuredToPlain(state.structured);
    }
    const body = String(state.body || "");
    const trimmed = body.trim();
    if (trimmed.charAt(0) === "{") {
      try {
        const data = JSON.parse(trimmed);
        if (data && Number(data.schema_version) >= 1) {
          return structuredToPlain(data);
        }
      } catch (e) {
        /* keep as plain */
      }
    }
    return body;
  }

  function ensureStructured() {
    if (state.structured) return state.structured;
    return {
      schema_version: 1,
      sender: { name: "", address: "", email: "", phone: "" },
      recipient: { company: "", name: "", address: "" },
      date: "",
      subject: "",
      greeting: "",
      paragraphs: [{ id: "p1", text: plainTextForEditor() || "" }],
      closing: "",
      signoff: "",
      signature: "",
    };
  }

  function coverId() {
    return parseInt(root.dataset.coverId || "0", 10);
  }

  function patchLetter(fields) {
    scheduleSave(async () => {
      const res = await api("patch_letter", { id: coverId(), fields: fields });
      if (res.letter) {
        state.letter = Object.assign(state.letter || {}, res.letter);
        if (res.letter.body !== undefined) state.body = res.letter.body;
        if (res.letter.structured !== undefined) state.structured = res.letter.structured;
        if (res.letter.body_format) state.body_format = res.letter.body_format;
      }
    });
  }

  function persistStructured() {
    state.body_format = "structured";
    patchLetter({ structured: state.structured });
    clearTimeout(persistStructured._t);
    persistStructured._t = setTimeout(refreshIframe, 900);
  }

  function renderEditor() {
    if (!editorEl || !state) return;
    const letter = state.letter || {};

    if (nav === "meta") {
      editorEl.innerHTML = `
        <h2 class="rb-editor-title">Name &amp; company</h2>
        <label class="form-label">Letter name
          <input class="form-control" data-cl-field="title" value="${escapeHtml(letter.title || "")}" ${letter.is_main ? "readonly" : ""}>
        </label>
        <label class="form-label mt-2">Company line
          <input class="form-control" data-cl-field="company" value="${escapeHtml(letter.company || "")}" placeholder="Company · City">
        </label>
        <p class="small text-secondary mt-2">Shown under your header on the letter.</p>`;
      editorEl.querySelectorAll("[data-cl-field]").forEach((input) => {
        input.addEventListener("input", () => {
          const key = input.getAttribute("data-cl-field");
          letter[key] = input.value;
          if (key === "company") patchPreviewCompany(input.value);
          patchLetter({ [key]: input.value });
        });
      });
      return;
    }

    if (nav === "plain" || (state.body_format === "plain" && !state.structured)) {
      const wasStructured = state.body_format === "structured" || !!state.structured
        || (String(state.body || "").trim().charAt(0) === "{" && state.body_format !== "plain");
      const displayText = plainTextForEditor();
      editorEl.innerHTML = `
        <h2 class="rb-editor-title">Letter text</h2>
        <p class="small text-secondary">${
          wasStructured
            ? "Plain-text view of your letter. Saving edits here stores the letter as plain text (not JSON)."
            : "Plain-text mode (existing format preserved)."
        }</p>
        <textarea class="form-control" rows="16" data-cl-plain>${escapeHtml(displayText)}</textarea>
        ${
          wasStructured
            ? ""
            : `<button type="button" class="btn btn-sm btn-outline-primary mt-2" data-cl-upgrade>Upgrade to structured editor</button>`
        }
        ${
          wasStructured
            ? `<button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-cl-back-structured>Back to structured editor</button>`
            : ""
        }`;
      const ta = editorEl.querySelector("[data-cl-plain]");
      ta.addEventListener("input", () => {
        state.body = ta.value;
        state.body_format = "plain";
        state.structured = null;
        patchPreviewPlain(ta.value);
        patchLetter({ body: ta.value });
      });
      const upgradeBtn = editorEl.querySelector("[data-cl-upgrade]");
      if (upgradeBtn) {
        upgradeBtn.addEventListener("click", async () => {
          try {
            setSaveState("saving");
            const res = await api("upgrade_structured", { id: coverId() });
            state.body = res.letter.body;
            state.structured = res.letter.structured;
            state.body_format = "structured";
            nav = "body";
            setNavActive();
            renderEditor();
            refreshIframe();
            setSaveState("saved");
          } catch (err) {
            setSaveState("error", err.message);
          }
        });
      }
      const backBtn = editorEl.querySelector("[data-cl-back-structured]");
      if (backBtn) {
        backBtn.addEventListener("click", () => {
          nav = "body";
          setNavActive();
          renderEditor();
        });
      }
      return;
    }

    const s = ensureStructured();
    state.structured = s;

    if (nav === "recipient") {
      editorEl.innerHTML = `
        <h2 class="rb-editor-title">Recipient</h2>
        <div class="rb-form-grid">
          <label>Contact name<input class="form-control form-control-sm" data-cl-rec="name" value="${escapeHtml(s.recipient.name || "")}"></label>
          <label>Company<input class="form-control form-control-sm" data-cl-rec="company" value="${escapeHtml(s.recipient.company || "")}"></label>
        </div>
        <label class="form-label mt-2">Address
          <textarea class="form-control" rows="3" data-cl-rec="address">${escapeHtml(s.recipient.address || "")}</textarea>
        </label>`;
      editorEl.querySelectorAll("[data-cl-rec]").forEach((el) => {
        el.addEventListener("input", () => {
          s.recipient[el.getAttribute("data-cl-rec")] = el.value;
          persistStructured();
        });
      });
      return;
    }

    if (nav === "date") {
      editorEl.innerHTML = `
        <h2 class="rb-editor-title">Date &amp; subject</h2>
        <label class="form-label">Date
          <input class="form-control" data-cl-s="date" value="${escapeHtml(s.date || "")}" placeholder="17 September 2026">
        </label>
        <label class="form-label mt-2">Subject
          <input class="form-control" data-cl-s="subject" value="${escapeHtml(s.subject || "")}">
        </label>`;
      editorEl.querySelectorAll("[data-cl-s]").forEach((el) => {
        el.addEventListener("input", () => {
          s[el.getAttribute("data-cl-s")] = el.value;
          patchPreviewField(el.getAttribute("data-cl-s"), el.value);
          persistStructured();
        });
      });
      return;
    }

    if (nav === "greeting") {
      editorEl.innerHTML = `
        <h2 class="rb-editor-title">Greeting</h2>
        <input class="form-control" data-cl-s="greeting" value="${escapeHtml(s.greeting || "")}" placeholder="Dear Hiring Team,">`;
      const el = editorEl.querySelector("[data-cl-s]");
      el.addEventListener("input", () => {
        s.greeting = el.value;
        patchPreviewField("greeting", el.value);
        persistStructured();
      });
      return;
    }

    if (nav === "closing") {
      editorEl.innerHTML = `
        <h2 class="rb-editor-title">Closing &amp; sign-off</h2>
        <label class="form-label">Closing paragraph
          <textarea class="form-control" rows="4" data-cl-s="closing">${escapeHtml(s.closing || "")}</textarea>
        </label>
        <label class="form-label mt-2">Sign-off
          <input class="form-control" data-cl-s="signoff" value="${escapeHtml(s.signoff || "")}" placeholder="Kind regards,">
        </label>
        <label class="form-label mt-2">Signature
          <textarea class="form-control" rows="2" data-cl-s="signature">${escapeHtml(s.signature || "")}</textarea>
        </label>`;
      editorEl.querySelectorAll("[data-cl-s]").forEach((el) => {
        el.addEventListener("input", () => {
          s[el.getAttribute("data-cl-s")] = el.value;
          persistStructured();
        });
      });
      return;
    }

    // paragraphs (default body)
    const paras = s.paragraphs || [];
    editorEl.innerHTML = `
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="rb-editor-title mb-0">Paragraphs</h2>
        <button type="button" class="btn btn-sm btn-primary" data-cl-add-para>+ Paragraph</button>
      </div>
      <div data-cl-paras>
        ${paras
          .map(
            (p, i) => `
          <div class="rb-entry-card" data-para-i="${i}">
            <label class="form-label">Paragraph ${i + 1}
              <textarea class="form-control" rows="5" data-cl-para-text>${escapeHtml(p.text || "")}</textarea>
            </label>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-para-up="${i}" ${i === 0 ? "disabled" : ""}>↑</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-para-down="${i}" ${i === paras.length - 1 ? "disabled" : ""}>↓</button>
              <button type="button" class="btn btn-sm btn-outline-danger" data-para-del="${i}">Delete</button>
            </div>
          </div>`
          )
          .join("")}
      </div>`;

    const syncParas = () => {
      editorEl.querySelectorAll("[data-para-i]").forEach((card) => {
        const i = parseInt(card.getAttribute("data-para-i"), 10);
        const ta = card.querySelector("[data-cl-para-text]");
        if (s.paragraphs[i] && ta) s.paragraphs[i].text = ta.value;
      });
      persistStructured();
    };

    editorEl.querySelectorAll("[data-cl-para-text]").forEach((ta) => {
      ta.addEventListener("input", () => {
        syncParas();
        patchPreviewParas();
      });
    });
    editorEl.querySelector("[data-cl-add-para]").addEventListener("click", () => {
      s.paragraphs.push({ id: "p" + Date.now(), text: "" });
      renderEditor();
      persistStructured();
    });
    editorEl.querySelectorAll("[data-para-del]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const i = parseInt(btn.getAttribute("data-para-del"), 10);
        s.paragraphs.splice(i, 1);
        if (s.paragraphs.length === 0) s.paragraphs.push({ id: "p1", text: "" });
        renderEditor();
        persistStructured();
      });
    });
    editorEl.querySelectorAll("[data-para-up],[data-para-down]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const up = btn.hasAttribute("data-para-up");
        const i = parseInt(btn.getAttribute(up ? "data-para-up" : "data-para-down"), 10);
        const j = up ? i - 1 : i + 1;
        if (j < 0 || j >= s.paragraphs.length) return;
        const tmp = s.paragraphs[i];
        s.paragraphs[i] = s.paragraphs[j];
        s.paragraphs[j] = tmp;
        renderEditor();
        persistStructured();
      });
    });
  }

  function setNavActive() {
    root.querySelectorAll("[data-cl-nav]").forEach((btn) => {
      const li = btn.closest(".rb-section-item");
      if (li) li.classList.toggle("is-active", btn.getAttribute("data-cl-nav") === nav);
    });
  }

  /* Preview helpers */
  function previewDoc() {
    try {
      return iframe && iframe.contentDocument;
    } catch (e) {
      return null;
    }
  }

  function patchPreviewCompany(val) {
    const doc = previewDoc();
    const el = doc && doc.querySelector(".letter-company");
    if (el) el.textContent = val;
  }

  function patchPreviewField(field, val) {
    const doc = previewDoc();
    if (!doc) return;
    const el = doc.querySelector(`[data-cover-field="${field}"]`);
    if (el) el.textContent = val;
  }

  function patchPreviewPlain(text) {
    const doc = previewDoc();
    const body = doc && doc.querySelector(".letter-body");
    if (!body) return;
    body.textContent = text;
  }

  function patchPreviewParas() {
    clearTimeout(patchPreviewParas._t);
    patchPreviewParas._t = setTimeout(refreshIframe, 1000);
  }

  function refreshIframe() {
    if (!iframe || !state) return;
    const meta = state.meta || {};
    const params = new URLSearchParams({
      embed: "1",
      pdf: "1",
      id: String(coverId()),
      theme: meta.template || "modern_de",
      _ts: String(Date.now()),
    });
    iframe.src = (root.dataset.previewBase || "/cover-letter") + "?" + params.toString();
  }

  function applyZoom() {
    if (!iframe) return;
    iframe.style.transform = "scale(" + zoom + ")";
    iframe.style.transformOrigin = "top center";
    if (zoomLabel) zoomLabel.textContent = Math.round(zoom * 100) + "%";
  }

  async function reloadState() {
    const data = await api("get_state", { id: coverId() });
    state = data;
    if (data.letter && data.letter.id) {
      root.dataset.coverId = String(data.letter.id);
    }
  }

  // Nav
  root.querySelector("[data-cl-nav]")?.closest("ul")?.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-cl-nav]");
    if (!btn) return;
    nav = btn.getAttribute("data-cl-nav");
    setNavActive();
    renderEditor();
  });

  // Design
  root.querySelectorAll("[data-cl-template]").forEach((btn) => {
    btn.addEventListener("click", () => {
      root.querySelectorAll("[data-cl-template]").forEach((b) => {
        b.classList.toggle("is-active", b === btn);
        b.setAttribute("aria-pressed", b === btn ? "true" : "false");
      });
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: { template: btn.getAttribute("data-cl-template") } });
        state.meta = res.meta;
        refreshIframe();
      });
    });
  });
  root.querySelectorAll("[data-cl-accent]").forEach((btn) => {
    btn.addEventListener("click", () => {
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: { accent_color: btn.getAttribute("data-cl-accent") } });
        state.meta = res.meta;
        refreshIframe();
      });
    });
  });
  root.querySelector("[data-cl-accent-custom]")?.addEventListener("change", (e) => {
    scheduleSave(async () => {
      const res = await api("patch_design", { fields: { accent_color: e.target.value } });
      state.meta = res.meta;
      refreshIframe();
    });
  });
  root.querySelector("[data-cl-font]")?.addEventListener("change", (e) => {
    scheduleSave(async () => {
      const res = await api("patch_design", { fields: { font_family: e.target.value } });
      state.meta = res.meta;
      refreshIframe();
    });
  });
  root.querySelectorAll("[data-cl-font-size]").forEach((btn) => {
    btn.addEventListener("click", () => {
      root.querySelectorAll("[data-cl-font-size]").forEach((b) => b.classList.toggle("active", b === btn));
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: { font_size: btn.getAttribute("data-cl-font-size") } });
        state.meta = res.meta;
        refreshIframe();
      });
    });
  });
  root.querySelector("[data-cl-spacing]")?.addEventListener("change", (e) => {
    scheduleSave(async () => {
      const res = await api("patch_design", { fields: { section_spacing: e.target.value } });
      state.meta = res.meta;
      refreshIframe();
    });
  });
  root.querySelector("[data-cl-page-format]")?.addEventListener("change", (e) => {
    scheduleSave(async () => {
      const res = await api("patch_design", { fields: { page_format: e.target.value } });
      state.meta = res.meta;
      refreshIframe();
    });
  });
  root.querySelector("[data-cl-margin]")?.addEventListener("change", (e) => {
    scheduleSave(async () => {
      const res = await api("patch_design", { fields: { margin: e.target.value } });
      state.meta = res.meta;
      refreshIframe();
    });
  });
  root.querySelectorAll("[data-cl-header]").forEach((cb) => {
    cb.addEventListener("change", () => {
      const fields = {};
      root.querySelectorAll("[data-cl-header]").forEach((el) => {
        fields[el.getAttribute("data-cl-header")] = el.checked;
      });
      scheduleSave(async () => {
        const res = await api("patch_design", { fields: fields });
        state.header_visibility = res.header_visibility;
        refreshIframe();
      });
    });
  });

  // Zoom / mobile
  root.querySelector("[data-cl-zoom='-']")?.addEventListener("click", () => {
    zoom = Math.max(0.5, zoom - 0.1);
    applyZoom();
  });
  root.querySelector("[data-cl-zoom='+']")?.addEventListener("click", () => {
    zoom = Math.min(1.4, zoom + 0.1);
    applyZoom();
  });
  root.querySelector("[data-cl-zoom-fit]")?.addEventListener("click", () => {
    zoom = 0.72;
    applyZoom();
  });
  iframe?.addEventListener("load", applyZoom);

  function setMobileTab(tab) {
    root.querySelectorAll("[data-cl-panel]").forEach((p) => {
      p.classList.toggle("is-active", p.getAttribute("data-cl-panel") === tab);
    });
    root.querySelectorAll("[data-cl-mobile-tab]").forEach((b) => {
      const on = b.getAttribute("data-cl-mobile-tab") === tab;
      b.classList.toggle("is-active", on);
      b.setAttribute("aria-selected", on ? "true" : "false");
    });
  }
  root.querySelectorAll("[data-cl-mobile-tab]").forEach((btn) => {
    btn.addEventListener("click", () => setMobileTab(btn.getAttribute("data-cl-mobile-tab")));
  });

  // Duplicate / tailor
  document.querySelector("[data-cl-duplicate]")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
      const res = await api("duplicate", {
        id: coverId(),
        title: fd.get("title"),
        copy_content: !!fd.get("copy_content"),
      });
      if (res.cover_id) window.location.href = "/cover-edit?id=" + res.cover_id;
    } catch (err) {
      alert(err.message || "Duplicate failed");
    }
  });

  document.querySelector("[data-cl-tailor-analyze]")?.addEventListener("click", async () => {
    const form = document.querySelector("[data-cl-tailor]");
    const fd = new FormData(form);
    try {
      const res = await api("tailor_preview", {
        company: fd.get("company"),
        role: fd.get("role"),
        jd: fd.get("jd"),
      });
      const box = form.querySelector("[data-cl-tailor-preview]");
      if (box) box.hidden = false;
      const kw = form.querySelector("[data-cl-tailor-keywords]");
      if (kw) kw.textContent = "Keywords: " + ((res.keywords || []).join(", ") || "(none)");
      const body = document.getElementById("clTailorBody");
      if (body) body.value = res.current_body || "";
    } catch (err) {
      alert(err.message || "Analyze failed");
    }
  });

  document.querySelector("[data-cl-tailor]")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const overrides = {};
    if (fd.get("cover_body")) overrides.cover_body = fd.get("cover_body");
    try {
      const res = await api("tailor_apply", {
        company: fd.get("company"),
        role: fd.get("role"),
        location: fd.get("location"),
        jd: fd.get("jd"),
        link: fd.get("link") || "",
        overrides: overrides,
      });
      if (res.cover_id) {
        window.location.href = "/cover-edit?id=" + res.cover_id;
      } else if (res.resume_id) {
        window.location.href = "/cover";
      }
    } catch (err) {
      alert(err.message || "Tailor failed");
    }
  });

  window.addEventListener("beforeunload", (e) => {
    if (dirty) {
      e.preventDefault();
      e.returnValue = "";
    }
  });

  (async function init() {
    try {
      await reloadState();
      if (state.body_format === "plain") nav = "plain";
      else nav = "body";
      setNavActive();
      renderEditor();
      setSaveState("saved");
      applyZoom();
    } catch (err) {
      setSaveState("error", err.message || "Failed to load");
    }
  })();
})();
