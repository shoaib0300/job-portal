(() => {
  const themeLabels = {
    midnight: "Midnight",
    sage: "Sage",
    classic: "Classic",
    modern: "Modern",
    compact: "Compact",
    sidebar: "Sidebar",
    executive: "Executive",
    company: "Company tint",
    banner: "Banner",
    split: "Split",
    minimal: "Minimal",
    slate: "Slate",
    serif: "Editorial",
    cards: "Cards",
    timeline: "Timeline",
  };

  /** Match PdfExport::personSlug() — German digraphs first, then strip remaining accents. */
  function personSlug(name) {
    const map = {
      Ä: "Ae",
      Ö: "Oe",
      Ü: "Ue",
      ä: "ae",
      ö: "oe",
      ü: "ue",
      ß: "ss",
      æ: "ae",
      œ: "oe",
      Æ: "Ae",
      Œ: "Oe",
    };
    let s = String(name || "").trim();
    if (!s) return "document";
    s = s.replace(/[ÄÖÜäöüßæœÆŒ]/g, (ch) => map[ch] || ch);
    s = s
      .normalize("NFKD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_|_$/g, "");
    return s || "document";
  }

  function cleanTitleForPrint(win) {
    const doc = win.document;
    const fromBody = doc.body && doc.body.getAttribute("data-pdf-title");
    if (fromBody && fromBody.trim()) {
      doc.title = fromBody.trim();
      return;
    }
    const heading = doc.querySelector(".resume-header h1, .letter-from strong");
    if (heading && heading.textContent.trim()) {
      const kind = win.location.pathname.includes("cover") ? "cover_letter" : "resume";
      doc.title = personSlug(heading.textContent) + "_" + kind;
    }
  }

  function openPrintWindow(path) {
    const url = new URL(path, window.location.origin);
    url.searchParams.set("embed", "1");
    url.searchParams.set("pdf", "1");
    const win = window.open(url.pathname + url.search, "_blank", "noopener,noreferrer,width=900,height=1200");
    if (!win) {
      printDocument(window);
      return;
    }
    const tryPrint = () => {
      try {
        cleanTitleForPrint(win);
        win.focus();
        win.print();
      } catch (_err) {
        // wait for load
      }
    };
    win.addEventListener("load", () => {
      window.setTimeout(tryPrint, 300);
    });
    window.setTimeout(tryPrint, 900);
  }

  function printDocument(win = window) {
    cleanTitleForPrint(win);
    win.document.body.classList.add("pdf-mode", "is-printing");
    const restore = () => {
      win.document.body.classList.remove("is-printing");
    };
    win.addEventListener("afterprint", restore, { once: true });
    win.focus();
    win.print();
    window.setTimeout(restore, 1000);
  }

  function documentLang() {
    return document.documentElement.dataset.documentLang || "en";
  }

  function translateTargetLang() {
    return document.documentElement.dataset.translateTargetLang || "de";
  }

  function translateLanguageOptions() {
    if (Array.isArray(window.kmTranslateLangs) && window.kmTranslateLangs.length) {
      return window.kmTranslateLangs;
    }
    return [
      { code: "de", label: "German" },
      { code: "en-gb", label: "English (UK)" },
      { code: "fr", label: "French" },
    ];
  }

  async function saveTranslatePreference(code) {
    try {
      await fetch("/translate-preference.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ translate_target_lang: code }),
        credentials: "same-origin",
      });
      document.documentElement.dataset.translateTargetLang = code;
    } catch (_err) {
      // preference save is best-effort
    }
  }

  function buildPdfDownloadUrl(doc, params = {}, inline = false, options = {}) {
    const url = new URL("/pdf.php", window.location.origin);
    url.searchParams.set("doc", doc);
    if (inline) url.searchParams.set("inline", "1");
    const search = new URLSearchParams(window.location.search);
    const translated = options.translate === true;
    const targetLang = options.target ? String(options.target) : "";
    ["theme", "font", "accent", "version", "id", "font_size", "name_size", "spacing"].forEach((key) => {
      let value;
      if (Object.prototype.hasOwnProperty.call(params, key)) {
        value = params[key];
      } else if (
        key === "theme" ||
        key === "font" ||
        key === "accent" ||
        key === "font_size" ||
        key === "name_size" ||
        key === "spacing"
      ) {
        value = search.get(key);
      } else {
        value = null;
      }
      if (value !== undefined && value !== null && String(value) !== "") {
        url.searchParams.set(key, String(value));
      }
    });
    if (translated && targetLang) {
      url.searchParams.set("translate", "1");
      url.searchParams.set("target", targetLang);
      url.searchParams.set("lang", targetLang);
    } else if (!url.searchParams.has("lang")) {
      url.searchParams.set("lang", documentLang());
    }
    return url.pathname + url.search;
  }

  function parseExportOptions(el) {
    if (!el) return [];
    const raw = el.getAttribute("data-export-options");
    if (!raw) {
      const studio = document.querySelector("[data-design-studio]");
      if (studio) return parseExportOptions(studio);
      return [];
    }
    try {
      const list = JSON.parse(raw);
      return Array.isArray(list) ? list : [];
    } catch (_err) {
      return [];
    }
  }

  function closeExportPicker() {
    const existing = document.querySelector("[data-export-picker]");
    if (existing) existing.remove();
  }

  function closeTranslatePicker() {
    const existing = document.querySelector("[data-translate-picker]");
    if (existing) existing.remove();
  }

  function chooseTranslateTarget(doc) {
    const options = translateLanguageOptions();
    const defaultCode = translateTargetLang();
    return new Promise((resolve) => {
      closeTranslatePicker();
      const overlay = document.createElement("div");
      overlay.className = "export-picker-overlay";
      overlay.setAttribute("data-translate-picker", "1");
      overlay.innerHTML =
        '<div class="export-picker export-picker-translate" role="dialog" aria-modal="true" aria-labelledby="translate-picker-title">' +
        '<div class="export-picker-head">' +
        '<h2 id="translate-picker-title">Translate ' +
        (doc === "cover" ? "cover letter" : "resume") +
        " PDF</h2>" +
        '<button type="button" class="btn-close" data-translate-cancel aria-label="Cancel"></button>' +
        "</div>" +
        '<p class="export-picker-lead">Choose a language (' +
        (String(options.length)) +
        ' available via DeepL). Billed per character. <a href="/settings#deepl-languages">View all</a> · <a href="/settings">Usage</a></p>' +
        '<label class="form-label small mb-1" for="translate-target-search">Search languages</label>' +
        '<input type="search" class="form-control mb-2" id="translate-target-search" placeholder="e.g. Urdu, German, French…" autocomplete="off">' +
        '<label class="form-label small mb-1" for="translate-target-select">Translate to</label>' +
        '<select class="form-select mb-3" id="translate-target-select" data-translate-select size="8"></select>' +
        '<div class="export-picker-foot d-flex gap-2 justify-content-end">' +
        '<button type="button" class="btn btn-outline-secondary" data-translate-cancel>Cancel</button>' +
        '<button type="button" class="btn btn-primary" data-translate-confirm>Translate PDF</button>' +
        "</div>" +
        "</div>";
      const select = overlay.querySelector("[data-translate-select]");
      const search = overlay.querySelector("#translate-target-search");

      function renderOptions(filter) {
        const q = (filter || "").trim().toLowerCase();
        select.innerHTML = "";
        const filtered = options.filter((opt) => {
          if (!q) return true;
          return opt.label.toLowerCase().includes(q) || opt.code.toLowerCase().includes(q);
        });
        filtered.forEach((opt) => {
          const option = document.createElement("option");
          option.value = opt.code;
          option.textContent = opt.label;
          if (opt.code === defaultCode) {
            option.selected = true;
          }
          select.appendChild(option);
        });
        if (!select.value && filtered[0]) {
          select.value = filtered[0].code;
        }
      }

      renderOptions("");
      search.addEventListener("input", () => renderOptions(search.value));
      overlay.addEventListener("click", (event) => {
        if (event.target === overlay) {
          closeTranslatePicker();
          resolve(null);
        }
      });
      overlay.querySelectorAll("[data-translate-cancel]").forEach((btn) => {
        btn.addEventListener("click", () => {
          closeTranslatePicker();
          resolve(null);
        });
      });
      overlay.querySelector("[data-translate-confirm]").addEventListener("click", async () => {
        const code = select.value;
        if (!code) {
          return;
        }
        closeTranslatePicker();
        await saveTranslatePreference(code);
        resolve(code);
      });
      document.body.appendChild(overlay);
      search.focus();
    });
  }

  function chooseExportOption(doc, options) {
    return new Promise((resolve) => {
      if (!options || options.length <= 1) {
        resolve(options && options[0] ? options[0] : null);
        return;
      }
      closeExportPicker();
      const overlay = document.createElement("div");
      overlay.className = "export-picker-overlay";
      overlay.setAttribute("data-export-picker", "1");
      overlay.innerHTML =
        '<div class="export-picker" role="dialog" aria-modal="true" aria-labelledby="export-picker-title">' +
        '<div class="export-picker-head">' +
        '<h2 id="export-picker-title">Which ' +
        (doc === "cover" ? "cover letter" : "resume") +
        " do you want?</h2>" +
        '<button type="button" class="btn-close" data-export-cancel aria-label="Cancel"></button>' +
        "</div>" +
        '<p class="export-picker-lead">Pick Main, or a copy you made for a company.</p>' +
        '<ul class="export-picker-list"></ul>' +
        '<div class="export-picker-foot"><button type="button" class="btn btn-outline-secondary" data-export-cancel>Cancel</button></div>' +
        "</div>";
      const list = overlay.querySelector(".export-picker-list");
      options.forEach((opt) => {
        const li = document.createElement("li");
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "export-picker-option";
        const tags = [];
        if (opt.base) tags.push("Main");
        if (opt.active && !opt.base) tags.push(doc === "cover" ? "Active" : "Loaded");
        btn.innerHTML =
          "<strong>" +
          (opt.label || "Untitled") +
          "</strong>" +
          (tags.length ? '<span class="export-tags">' + tags.join(" · ") + "</span>" : "") +
          (opt.company ? '<span class="export-company">' + opt.company + "</span>" : "");
        btn.addEventListener("click", () => {
          closeExportPicker();
          resolve(opt);
        });
        li.appendChild(btn);
        list.appendChild(li);
      });
      overlay.addEventListener("click", (event) => {
        if (event.target === overlay) {
          closeExportPicker();
          resolve(null);
        }
      });
      overlay.querySelectorAll("[data-export-cancel]").forEach((btn) => {
        btn.addEventListener("click", () => {
          closeExportPicker();
          resolve(null);
        });
      });
      document.body.appendChild(overlay);
      const first = overlay.querySelector(".export-picker-option");
      if (first) first.focus();
    });
  }

  async function resolveExportParams(doc, params = {}, triggerEl = null) {
    const options = parseExportOptions(triggerEl) || parseExportOptions(document.querySelector("[data-design-studio]"));
    const chosen = await chooseExportOption(doc, options);
    if (chosen === null && options.length > 1) {
      return null;
    }
    const next = { ...params };
    if (doc === "resume") {
      if (chosen && chosen.id) next.version = String(chosen.id);
      else delete next.version;
    } else if (doc === "cover") {
      if (chosen && chosen.id) next.id = String(chosen.id);
      else delete next.id;
    }
    return next;
  }

  async function downloadCleanPdf(doc = null, params = {}, triggerEl = null) {
    let kind = doc;
    if (!kind) {
      if (window.location.pathname.includes("cover")) kind = "cover";
      else kind = "resume";
    }
    const resolved = await resolveExportParams(kind, params, triggerEl);
    if (resolved === null) return;
    window.location.href = buildPdfDownloadUrl(kind, resolved, false);
  }

  async function translateCleanPdf(doc = null, params = {}, triggerEl = null) {
    let kind = doc;
    if (!kind) {
      if (window.location.pathname.includes("cover")) kind = "cover";
      else kind = "resume";
    }
    const target = await chooseTranslateTarget(kind);
    if (!target) return;
    const resolved = await resolveExportParams(kind, params, triggerEl);
    if (resolved === null) return;
    window.location.href = buildPdfDownloadUrl(kind, resolved, false, { translate: true, target });
  }

  async function printCleanPdf(doc = null, params = {}, triggerEl = null) {
    let kind = doc;
    if (!kind) {
      if (window.location.pathname.includes("cover")) kind = "cover";
      else kind = "resume";
    }
    const resolved = await resolveExportParams(kind, params, triggerEl);
    if (resolved === null) return;
    const pdfUrl = buildPdfDownloadUrl(kind, resolved, true);
    const win = window.open(pdfUrl, "_blank", "noopener,noreferrer");
    if (!win) {
      window.location.href = pdfUrl;
    }
  }

  function printNow(triggerEl = null) {
    if (window.location.pathname.includes("resume") || window.location.pathname.includes("cover")) {
      printCleanPdf(null, {}, triggerEl);
      return;
    }
    printDocument(window);
  }

  document.querySelectorAll("[data-print]").forEach((btn) => {
    btn.addEventListener("click", () => printNow(btn));
  });

  document.querySelectorAll("[data-download-pdf]").forEach((btn) => {
    btn.addEventListener("click", (event) => {
      if (btn.getAttribute("href")) return;
      event.preventDefault();
      const doc = btn.getAttribute("data-doc") || null;
      downloadCleanPdf(doc, {}, btn);
    });
  });

  document.querySelectorAll("[data-translate-pdf]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const doc = btn.getAttribute("data-doc") || null;
      translateCleanPdf(doc, {}, btn);
    });
  });

  document.querySelectorAll("[data-add-link]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const fieldset = btn.closest("fieldset");
      if (!fieldset) return;
      const row = document.createElement("div");
      row.className = "link-row";
      row.innerHTML =
        '<input type="text" name="link_label[]" placeholder="Label">' +
        '<input type="url" name="link_url[]" placeholder="https://">';
      fieldset.insertBefore(row, btn);
    });
  });

  // Section / experience reorder (arrows + drag)
  document.querySelectorAll("[data-section-sorter]").forEach((sorter) => {
    const list = sorter.querySelector("[data-sort-list]");
    if (!list) return;

    function items() {
      return Array.from(list.querySelectorAll("[data-sort-item]"));
    }

    function refreshButtons() {
      const rows = items();
      rows.forEach((row, index) => {
        const up = row.querySelector("[data-move-up]");
        const down = row.querySelector("[data-move-down]");
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === rows.length - 1;
      });
    }

    list.addEventListener("click", (event) => {
      const up = event.target.closest("[data-move-up]");
      const down = event.target.closest("[data-move-down]");
      if (!up && !down) return;
      const row = event.target.closest("[data-sort-item]");
      if (!row || !list.contains(row)) return;
      if (up && row.previousElementSibling) {
        list.insertBefore(row, row.previousElementSibling);
      }
      if (down && row.nextElementSibling) {
        list.insertBefore(row.nextElementSibling, row);
      }
      refreshButtons();
    });

    let dragItem = null;
    list.querySelectorAll("[data-sort-item]").forEach((row) => {
      row.addEventListener("dragstart", (event) => {
        dragItem = row;
        row.classList.add("is-dragging");
        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = "move";
          event.dataTransfer.setData("text/plain", row.id || "item");
        }
      });
      row.addEventListener("dragend", () => {
        row.classList.remove("is-dragging");
        list.querySelectorAll(".is-drag-over").forEach((el) => el.classList.remove("is-drag-over"));
        dragItem = null;
        refreshButtons();
      });
      row.addEventListener("dragover", (event) => {
        event.preventDefault();
        const target = event.currentTarget;
        if (!dragItem || target === dragItem || !list.contains(dragItem)) return;
        target.classList.add("is-drag-over");
        const rect = target.getBoundingClientRect();
        const before = event.clientY < rect.top + rect.height / 2;
        if (before) {
          list.insertBefore(dragItem, target);
        } else {
          list.insertBefore(dragItem, target.nextElementSibling);
        }
      });
      row.addEventListener("dragleave", (event) => {
        event.currentTarget.classList.remove("is-drag-over");
      });
      row.addEventListener("drop", (event) => {
        event.preventDefault();
        event.currentTarget.classList.remove("is-drag-over");
        refreshButtons();
      });
    });

    refreshButtons();
  });

  // Applications: Show / Hide job text
    document.querySelectorAll("[data-toggle-jd]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const targetId = btn.getAttribute("data-jd-target");
      const row = targetId ? document.getElementById(targetId) : null;
      if (!row) return;
      const open = row.hasAttribute("hidden");
      if (open) {
        row.removeAttribute("hidden");
        btn.setAttribute("aria-expanded", "true");
        btn.textContent = "Hide";
      } else {
        row.setAttribute("hidden", "");
        btn.setAttribute("aria-expanded", "false");
        btn.textContent = "Job";
      }
    });
  });

  const studio = document.querySelector("[data-design-studio]");
  if (!studio) return;

  const fontLabels = {
    arial: "Arial",
    aptos: "Aptos",
    candara: "Candara",
    helvetica: "Helvetica",
    georgia: "Georgia",
    times: "Times New Roman",
    garamond: "Garamond",
    palatino: "Palatino",
    playfair: "Playfair Display",
    lora: "Lora",
    cormorant: "Cormorant",
    baskerville: "Baskerville",
    source_serif: "Source Serif",
    montserrat: "Montserrat",
    calibri: "Calibri",
    cosmo: "Cosmo",
    didot: "Didot",
    verdana: "Verdana",
  };

  const frame = studio.querySelector("[data-preview-frame]");
  const themeInput = studio.querySelector("[data-theme-input]");
  const accentInput = studio.querySelector("[data-accent-input]");
  const fontInput = studio.querySelector("[data-font-input]");
  const labelEl = studio.querySelector("[data-preview-label]");
  const openFull = studio.querySelector("[data-open-full]");
  const form = studio.querySelector("[data-design-form]");
  const customColor = studio.querySelector("[data-accent-custom]");

  let theme = studio.dataset.theme || "classic";
  let accent = studio.dataset.accent || "#1a5f4a";
  let font = studio.dataset.font || "georgia";
  let fontSize = studio.dataset.fontSize || "md";
  let spacing = studio.dataset.spacing || "md";
  const doc = studio.dataset.doc || "resume";
  const basePath = doc === "cover" ? "/cover-letter.php" : "/resume.php";
  const nameInput = studio.querySelector("[data-name-size-input]");
  const fontSizeInput = studio.querySelector("[data-font-size-input]");
  const spacingInput = studio.querySelector("[data-spacing-input]");

  function previewUrl() {
    const q = new URLSearchParams({
      embed: "1",
      theme,
      accent,
      font,
      pdf: "1",
      name_size: "md",
      font_size: fontSize,
      spacing,
    });
    return `${basePath}?${q.toString()}`;
  }

  function fullUrl() {
    const q = new URLSearchParams({
      theme,
      accent,
      font,
      pdf: "1",
      name_size: "md",
      font_size: fontSize,
      spacing,
    });
    return `${basePath}?${q.toString()}`;
  }

  const fontSizeLabels = { sm: "Small", md: "Medium", lg: "Large" };
  const spacingLabels = { tight: "Tight", md: "Medium", loose: "Loose" };

  function syncUi({ reloadPreview = true } = {}) {
    if (themeInput) themeInput.value = theme;
    if (accentInput) accentInput.value = accent;
    if (fontInput) fontInput.value = font;
    if (nameInput) nameInput.value = "md";
    if (fontSizeInput) fontSizeInput.value = fontSize;
    if (spacingInput) spacingInput.value = spacing;
    if (customColor) customColor.value = accent;
    if (reloadPreview && frame) frame.src = previewUrl();
    if (openFull) openFull.href = fullUrl();
    if (labelEl) {
      labelEl.textContent =
        `Preview · ${themeLabels[theme] || theme} · ${fontLabels[font] || font}` +
        ` · ${fontSizeLabels[fontSize] || fontSize} · ${spacingLabels[spacing] || spacing}`;
    }

    studio.querySelectorAll("[data-theme-pick]").forEach((btn) => {
      const on = btn.getAttribute("data-theme-pick") === theme;
      btn.classList.toggle("is-selected", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });

    studio.querySelectorAll("[data-font-pick]").forEach((btn) => {
      const on = btn.getAttribute("data-font-pick") === font;
      btn.classList.toggle("is-selected", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });

    studio.querySelectorAll("[data-accent-pick]").forEach((btn) => {
      const on = (btn.getAttribute("data-accent-pick") || "").toLowerCase() === accent.toLowerCase();
      btn.classList.toggle("is-selected", on);
    });

    studio.querySelectorAll("[data-font-size-pick]").forEach((btn) => {
      const on = btn.getAttribute("data-font-size-pick") === fontSize;
      btn.classList.toggle("is-selected", on);
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });

    studio.querySelectorAll("[data-spacing-pick]").forEach((btn) => {
      const on = btn.getAttribute("data-spacing-pick") === spacing;
      btn.classList.toggle("is-selected", on);
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });

    const url = new URL(window.location.href);
    url.searchParams.set("doc", doc);
    url.searchParams.set("theme", theme);
    url.searchParams.set("accent", accent);
    url.searchParams.set("font", font);
    url.searchParams.set("name_size", "md");
    url.searchParams.set("font_size", fontSize);
    url.searchParams.set("spacing", spacing);
    window.history.replaceState({}, "", url);
  }

  studio.querySelectorAll("[data-theme-pick]").forEach((btn) => {
    btn.addEventListener("click", () => {
      theme = btn.getAttribute("data-theme-pick") || theme;
      if (theme === "timeline" && (!accent || accent === "#4E6351" || accent === "#4e6351")) {
        accent = "#8B1A1A";
        if (customColor) customColor.value = accent;
      }
      syncUi();
    });
  });

  studio.querySelectorAll("[data-font-pick]").forEach((btn) => {
    btn.addEventListener("click", () => {
      font = btn.getAttribute("data-font-pick") || font;
      syncUi();
    });
  });

  studio.querySelectorAll("[data-font-size-pick]").forEach((btn) => {
    btn.addEventListener("click", (event) => {
      event.preventDefault();
      fontSize = btn.getAttribute("data-font-size-pick") || fontSize;
      syncUi();
    });
  });

  studio.querySelectorAll("[data-spacing-pick]").forEach((btn) => {
    btn.addEventListener("click", (event) => {
      event.preventDefault();
      spacing = btn.getAttribute("data-spacing-pick") || spacing;
      syncUi();
    });
  });

  studio.querySelectorAll("[data-accent-pick]").forEach((btn) => {
    btn.addEventListener("click", () => {
      accent = btn.getAttribute("data-accent-pick") || accent;
      syncUi();
    });
  });

  if (customColor) {
    customColor.addEventListener("input", () => {
      accent = customColor.value;
      syncUi();
    });
  }

  const studioStyleParams = () => ({
    theme,
    font,
    accent,
    font_size: fontSize,
    name_size: "md",
    spacing,
  });

  studio.querySelectorAll("[data-studio-print]").forEach((btn) => {
    btn.addEventListener("click", () => {
      printCleanPdf(doc === "cover" ? "cover" : "resume", studioStyleParams(), btn);
    });
  });

  studio.querySelectorAll("[data-studio-pdf]").forEach((btn) => {
    btn.addEventListener("click", () => {
      downloadCleanPdf(doc === "cover" ? "cover" : "resume", studioStyleParams(), btn);
    });
  });

  if (form) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const data = new FormData(form);
      data.set("ajax", "1");
        data.set("theme", theme);
        data.set("accent_color", accent);
        data.set("font_family", font);
        data.set("name_size", "md");
        data.set("font_size", fontSize);
        data.set("section_spacing", spacing);
      try {
        const res = await fetch(form.action || window.location.pathname, {
          method: "POST",
          headers: { Accept: "application/json" },
          body: data,
        });
        const json = await res.json();
        if (json.ok) {
          const flash = document.createElement("div");
          flash.className = "alert alert-success";
          flash.textContent = `Style applied: ${json.label} · ${json.font_label || font}. You can print or download PDF now.`;
          studio.prepend(flash);
          window.setTimeout(() => flash.remove(), 3500);
        }
      } catch (_err) {
        form.submit();
      }
    });
  }

  syncUi({ reloadPreview: false });
})();

(() => {
  const root = document.querySelector("[data-keyword-chips]");
  if (!root) return;
  const list = root.querySelector("[data-keyword-list]");
  const input = root.querySelector("[data-keyword-input]");
  const addBtn = root.querySelector("[data-keyword-add]");
  const form = document.querySelector("[data-jobs-form]");
  if (!list || !input) return;

  function existingValues() {
    return Array.from(list.querySelectorAll('input[name="q[]"]')).map((el) =>
      String(el.value || "").trim().toLowerCase()
    );
  }

  function addKeyword(raw) {
    const parts = String(raw || "")
      .split(",")
      .map((p) => p.trim())
      .filter(Boolean);
    const seen = new Set(existingValues());
    parts.forEach((part) => {
      const key = part.toLowerCase();
      if (!key || seen.has(key) || seen.size >= 12) return;
      seen.add(key);
      const chip = document.createElement("span");
      chip.className = "keyword-chip";
      const hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = "q[]";
      hidden.value = part;
      const label = document.createElement("span");
      label.className = "keyword-chip-label";
      label.textContent = part;
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "keyword-chip-remove";
      remove.setAttribute("data-keyword-remove", "1");
      remove.setAttribute("aria-label", "Remove " + part);
      remove.innerHTML = "&times;";
      chip.appendChild(hidden);
      chip.appendChild(label);
      chip.appendChild(remove);
      list.appendChild(chip);
    });
    input.value = "";
    input.focus();
  }

  list.addEventListener("click", (event) => {
    const btn = event.target.closest("[data-keyword-remove]");
    if (!btn) return;
    const chip = btn.closest(".keyword-chip");
    if (chip) chip.remove();
  });

  if (addBtn) {
    addBtn.addEventListener("click", () => addKeyword(input.value));
  }

  input.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === ",") {
      event.preventDefault();
      addKeyword(input.value.replace(/,$/, ""));
    }
  });

  if (form) {
    form.addEventListener("submit", () => {
      if (input.value.trim()) {
        addKeyword(input.value);
      }
      input.value = "";
      input.removeAttribute("name");
    });
  }
})();

(() => {
  const toggle = document.querySelector("[data-match-resume]");
  const zone = document.querySelector("[data-match-resume-disable]");
  const hint = document.querySelector("[data-match-resume-hint]");
  if (!toggle || !zone) return;

  function sync() {
    const on = !!toggle.checked;
    zone.querySelectorAll("input, select").forEach((el) => {
      el.disabled = on;
      if (on && el.type === "checkbox") {
        el.checked = false;
      }
      if (on && el.tagName === "SELECT") {
        el.value = "";
      }
    });
    if (hint) hint.hidden = !on;
  }

  toggle.addEventListener("change", sync);
  sync();
})();

(() => {
  const root = document.querySelector("[data-sources-picker]");
  if (!root) return;
  const boxes = () => root.querySelectorAll("[data-source-input]");
  root.querySelector("[data-sources-select-all]")?.addEventListener("click", () => {
    boxes().forEach((el) => {
      el.checked = true;
    });
  });
  root.querySelector("[data-sources-clear]")?.addEventListener("click", () => {
    boxes().forEach((el) => {
      el.checked = false;
    });
  });
})();

(() => {
  const root = document.querySelector("[data-company-picker]");
  if (!root) return;
  const filter = root.querySelector("[data-company-filter]");
  const toggleLabel = root.querySelector("[data-company-toggle-label]");
  const chips = Array.from(root.querySelectorAll(".jobs-company-chip"));

  function refresh() {
    let selected = 0;
    chips.forEach((chip) => {
      const input = chip.querySelector("[data-company-input]");
      const on = !!(input && input.checked);
      chip.classList.toggle("is-checked", on);
      if (on) selected += 1;
    });
    if (toggleLabel) {
      toggleLabel.textContent =
        selected > 0 ? `Filter by company (${selected})` : "Filter by company";
    }
  }

  root.addEventListener("change", (event) => {
    if (event.target && event.target.matches("[data-company-input]")) {
      refresh();
    }
  });

  if (filter) {
    filter.addEventListener("input", () => {
      const q = String(filter.value || "").trim().toLowerCase();
      chips.forEach((chip) => {
        const label = chip.getAttribute("data-company-label") || "";
        chip.classList.toggle("is-hidden", q !== "" && !label.includes(q));
      });
    });
  }

  refresh();
})();

(() => {
  const form = document.querySelector("[data-jobs-ajax]");
  const results = document.querySelector("[data-jobs-results]");
  if (!form || !results) return;

  const panel = results.querySelector("[data-jobs-panel]");
  const loading = results.querySelector("[data-jobs-loading]");
  let abort = null;
  let seq = 0;

  function setLoading(on) {
    if (!loading) return;
    if (on) {
      loading.removeAttribute("hidden");
      loading.setAttribute("aria-hidden", "false");
      results.classList.add("is-loading");
    } else {
      loading.setAttribute("hidden", "");
      loading.setAttribute("aria-hidden", "true");
      results.classList.remove("is-loading");
    }
  }

  function syncPostedHidden(days) {
    const hidden = form.querySelector("[data-jobs-posted]");
    if (hidden) hidden.value = String(days);
  }

  async function loadJobs(params, { push = true } = {}) {
    const mySeq = ++seq;
    if (abort) abort.abort();
    abort = new AbortController();
    setLoading(true);
    params.set("format", "json");
    if (!params.has("search")) params.set("search", "1");
    const url = "/jobs?" + params.toString();
    try {
      const res = await fetch(url, {
        headers: { Accept: "application/json" },
        signal: abort.signal,
        credentials: "same-origin",
      });
      const json = await res.json();
      if (mySeq !== seq) return;
      if (!json || !json.ok || typeof json.html !== "string") {
        throw new Error("bad response");
      }
      if (panel) panel.innerHTML = json.html;
      if (push) {
        const clean = new URLSearchParams(params);
        clean.delete("format");
        const next = "/jobs?" + clean.toString();
        window.history.pushState({ jobsAjax: true }, "", next);
      }
      results.scrollIntoView({ behavior: "smooth", block: "start" });
    } catch (err) {
      if (err && err.name === "AbortError") return;
      // Fallback: full navigation
      const clean = new URLSearchParams(params);
      clean.delete("format");
      window.location.href = "/jobs?" + clean.toString();
    } finally {
      if (mySeq === seq) setLoading(false);
    }
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const kwInput = form.querySelector("[data-keyword-input]");
    if (kwInput && kwInput.value.trim()) {
      const addBtn = form.querySelector("[data-keyword-add]");
      if (addBtn) addBtn.click();
      else {
        // ensure chip path ran; keyword IIFE may have emptied name
      }
    }
    if (kwInput) {
      kwInput.value = "";
      kwInput.removeAttribute("name");
    }
    const params = new URLSearchParams(new FormData(form));
    params.set("page", "1");
    loadJobs(params);
  });

  form.addEventListener("change", (event) => {
    const sort = event.target.closest("[data-jobs-sort]");
    if (!sort) return;
    const params = new URLSearchParams(new FormData(form));
    params.set("page", "1");
    loadJobs(params);
  });

  results.addEventListener("click", (event) => {
    const link = event.target.closest("[data-jobs-page]");
    if (!link || !results.contains(link)) return;
    event.preventDefault();
    const page = link.getAttribute("data-jobs-page") || "1";
    const params = new URLSearchParams(new FormData(form));
    params.set("page", page);
    loadJobs(params);
  });

  form.addEventListener("click", (event) => {
    const chip = event.target.closest("[data-jobs-posted-chip]");
    if (!chip || !form.contains(chip)) return;
    event.preventDefault();
    const days = chip.getAttribute("data-jobs-posted-chip") || "7";
    syncPostedHidden(days);
    form.querySelectorAll("[data-jobs-posted-chip]").forEach((el) => {
      el.classList.toggle("is-active", el === chip);
    });
    const params = new URLSearchParams(new FormData(form));
    params.set("posted", days);
    params.set("page", "1");
    loadJobs(params);
  });

  window.addEventListener("popstate", () => {
    const params = new URLSearchParams(window.location.search);
    if (!params.has("search") && !params.toString()) {
      window.location.reload();
      return;
    }
    loadJobs(params, { push: false });
  });
})();

(() => {
  const form = document.querySelector("[data-settings-look-form]");
  const preview = document.querySelector("[data-settings-preview]");
  if (!form || !preview) return;

  function syncPreview() {
    const palette = form.querySelector('[name="dashboard_palette"]:checked')?.value;
    const density = form.querySelector('[name="ui_density"]:checked')?.value;
    const sidebar = form.querySelector('[name="sidebar_mode"]:checked')?.value;
    if (palette) preview.dataset.palette = palette;
    if (density) preview.dataset.density = density;
    if (sidebar) preview.dataset.sidebar = sidebar;
  }

  form.addEventListener("change", syncPreview);
  syncPreview();
})();

(() => {
  const heroes = document.querySelectorAll("[data-km-flow-hero]");
  if (!heroes.length) return;

  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const dotPositions = [60, 180, 300, 420, 540, 660];

  heroes.forEach((wrap) => {
    const hero = wrap.querySelector(".km-flow-hero");
    const dotWrap = wrap.querySelector("[data-flow-dot-wrap]");
    const tabs = wrap.querySelectorAll("[data-flow-tab]");
    const panels = wrap.querySelectorAll("[data-flow-panel]");
    const nodes = wrap.querySelectorAll("[data-flow-step]");
    const live = wrap.querySelector("[data-flow-live]");
    if (!hero || !tabs.length) return;

    const stepIds = Array.from(tabs).map((t) => t.getAttribute("data-flow-tab") || "");
    let index = 0;
    let timer = null;

    function setStep(i) {
      index = ((i % stepIds.length) + stepIds.length) % stepIds.length;
      const id = stepIds[index];
      hero.setAttribute("data-active-step", String(index));
      tabs.forEach((tab, ti) => {
        const on = ti === index;
        tab.classList.toggle("is-active", on);
        tab.setAttribute("aria-selected", on ? "true" : "false");
      });
      panels.forEach((panel) => {
        const on = panel.getAttribute("data-flow-panel") === id;
        panel.classList.toggle("is-active", on);
        panel.hidden = !on;
      });
      nodes.forEach((node) => {
        node.classList.toggle("is-active", node.getAttribute("data-flow-step") === id);
      });
      if (dotWrap && dotPositions[index] !== undefined) {
        dotWrap.setAttribute("transform", `translate(${dotPositions[index]}, 0)`);
      }
      const activeTab = tabs[index];
      if (live && activeTab) {
        live.textContent = activeTab.textContent?.trim() || "";
      }
    }

    function startAutoplay() {
      if (reducedMotion) return;
      stopAutoplay();
      hero.setAttribute("data-km-autoplay", "1");
      timer = window.setInterval(() => {
        setStep(index + 1);
      }, 3500);
    }

    function stopAutoplay() {
      hero.removeAttribute("data-km-autoplay");
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
    }

    tabs.forEach((tab, ti) => {
      tab.addEventListener("click", () => {
        setStep(ti);
        stopAutoplay();
      });
    });

    wrap.addEventListener("mouseenter", stopAutoplay);
    wrap.addEventListener("mouseleave", startAutoplay);
    wrap.addEventListener("focusin", stopAutoplay);
    wrap.addEventListener("focusout", (e) => {
      if (!wrap.contains(e.relatedTarget)) startAutoplay();
    });

    if ("IntersectionObserver" in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) startAutoplay();
          else stopAutoplay();
        });
      }, { threshold: 0.2 });
      io.observe(wrap);
    } else {
      startAutoplay();
    }

    setStep(0);
  });
})();

(function initDeeplLanguageFilter() {
  const input = document.getElementById("deepl-lang-filter");
  const list = document.getElementById("deepl-lang-list");
  if (!input || !list) return;
  input.addEventListener("input", () => {
    const q = input.value.trim().toLowerCase();
    list.querySelectorAll("li").forEach((li) => {
      const label = li.getAttribute("data-lang-label") || "";
      const code = li.getAttribute("data-lang-code") || "";
      const show = !q || label.includes(q) || code.includes(q);
      li.style.display = show ? "" : "none";
    });
  });
})();
