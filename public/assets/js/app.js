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

  function cleanTitleForPrint(win) {
    const doc = win.document;
    const heading = doc.querySelector(".resume-header h1, .letter-from strong");
    if (heading && heading.textContent.trim()) {
      doc.title = heading.textContent.trim();
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

  function buildPdfDownloadUrl(doc, params = {}, inline = false) {
    const url = new URL("/pdf.php", window.location.origin);
    url.searchParams.set("doc", doc);
    if (inline) url.searchParams.set("inline", "1");
    const search = new URLSearchParams(window.location.search);
    ["theme", "font", "accent", "version", "id"].forEach((key) => {
      let value;
      if (Object.prototype.hasOwnProperty.call(params, key)) {
        value = params[key];
      } else if (key === "theme" || key === "font" || key === "accent") {
        value = search.get(key);
      } else {
        value = null;
      }
      if (value !== undefined && value !== null && String(value) !== "") {
        url.searchParams.set(key, String(value));
      }
    });
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
        '<button type="button" class="btn btn-small" data-export-cancel aria-label="Cancel">×</button>' +
        "</div>" +
        '<p class="export-picker-lead">Pick Main, or a copy you made for a company.</p>' +
        '<ul class="export-picker-list"></ul>' +
        '<div class="export-picker-foot"><button type="button" class="btn btn-secondary" data-export-cancel>Cancel</button></div>' +
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
    btn.addEventListener("click", () => {
      const doc = btn.getAttribute("data-doc") || null;
      downloadCleanPdf(doc, {}, btn);
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

  // Applications: Show / Hide JD
    document.querySelectorAll("[data-toggle-jd]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const targetId = btn.getAttribute("data-jd-target");
      const row = targetId ? document.getElementById(targetId) : null;
      if (!row) return;
      const open = row.hasAttribute("hidden");
      if (open) {
        row.removeAttribute("hidden");
        btn.setAttribute("aria-expanded", "true");
        btn.textContent = "Hide JD";
      } else {
        row.setAttribute("hidden", "");
        btn.setAttribute("aria-expanded", "false");
        btn.textContent = "Show JD";
      }
    });
  });

  const menuBtn = document.querySelector("[data-sidebar-toggle]");
  if (menuBtn) {
    menuBtn.addEventListener("click", (event) => {
      event.stopPropagation();
      document.body.classList.toggle("sidebar-open");
    });
    document.addEventListener("click", (event) => {
      if (!document.body.classList.contains("sidebar-open")) return;
      const side = document.querySelector("[data-sidebar-panel]");
      if (side && !side.contains(event.target) && event.target !== menuBtn && !menuBtn.contains(event.target)) {
        document.body.classList.remove("sidebar-open");
      }
    });
  }

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
  let nameSize = studio.dataset.nameSize || "md";
  let spacing = studio.dataset.spacing || "md";
  const doc = studio.dataset.doc || "resume";
  const basePath = doc === "cover" ? "/cover-letter.php" : "/resume.php";
  const nameInput = studio.querySelector("[data-name-size-input]");
  const spacingInput = studio.querySelector("[data-spacing-input]");

  function previewUrl() {
    const q = new URLSearchParams({
      embed: "1",
      theme,
      accent,
      font,
      pdf: "1",
      name_size: nameSize,
      spacing,
    });
    return `${basePath}?${q.toString()}`;
  }

  function fullUrl() {
    const q = new URLSearchParams({ theme, accent, font, pdf: "1", name_size: nameSize, spacing });
    return `${basePath}?${q.toString()}`;
  }

  function syncUi() {
    if (themeInput) themeInput.value = theme;
    if (accentInput) accentInput.value = accent;
    if (fontInput) fontInput.value = font;
    if (nameInput) nameInput.value = nameSize;
    if (spacingInput) spacingInput.value = spacing;
    if (customColor) customColor.value = accent;
    if (frame) frame.src = previewUrl();
    if (openFull) openFull.href = fullUrl();
    if (labelEl) {
      labelEl.textContent = `Preview · ${themeLabels[theme] || theme} · ${fontLabels[font] || font}`;
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

    studio.querySelectorAll("[data-name-size-pick]").forEach((btn) => {
      const on = btn.getAttribute("data-name-size-pick") === nameSize;
      btn.classList.toggle("is-selected", on);
    });

    studio.querySelectorAll("[data-spacing-pick]").forEach((btn) => {
      const on = btn.getAttribute("data-spacing-pick") === spacing;
      btn.classList.toggle("is-selected", on);
    });

    document.documentElement.style.setProperty("--accent", accent);
    const url = new URL(window.location.href);
    url.searchParams.set("doc", doc);
    url.searchParams.set("theme", theme);
    url.searchParams.set("accent", accent);
    url.searchParams.set("font", font);
    url.searchParams.set("name_size", nameSize);
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

  studio.querySelectorAll("[data-name-size-pick]").forEach((btn) => {
    btn.addEventListener("click", () => {
      nameSize = btn.getAttribute("data-name-size-pick") || nameSize;
      syncUi();
    });
  });

  studio.querySelectorAll("[data-spacing-pick]").forEach((btn) => {
    btn.addEventListener("click", () => {
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

  studio.querySelectorAll("[data-studio-print]").forEach((btn) => {
    btn.addEventListener("click", () => {
      printCleanPdf(doc === "cover" ? "cover" : "resume", { theme, font, accent }, btn);
    });
  });

  studio.querySelectorAll("[data-studio-pdf]").forEach((btn) => {
    btn.addEventListener("click", () => {
      downloadCleanPdf(doc === "cover" ? "cover" : "resume", { theme, font, accent }, btn);
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
        data.set("name_size", nameSize);
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
          flash.className = "flash";
          flash.textContent = `Style applied: ${json.label} · ${json.font_label || font}. You can print or download PDF now.`;
          studio.prepend(flash);
          window.setTimeout(() => flash.remove(), 3500);
        }
      } catch (_err) {
        form.submit();
      }
    });
  }
})();
