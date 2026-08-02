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
    ["theme", "font", "accent"].forEach((key) => {
      const value = params[key] || new URLSearchParams(window.location.search).get(key);
      if (value) url.searchParams.set(key, value);
    });
    return url.pathname + url.search;
  }

  function downloadCleanPdf(doc = null, params = {}) {
    let kind = doc;
    if (!kind) {
      if (window.location.pathname.includes("cover")) kind = "cover";
      else kind = "resume";
    }
    window.location.href = buildPdfDownloadUrl(kind, params, false);
  }

  function printCleanPdf(doc = null, params = {}) {
    let kind = doc;
    if (!kind) {
      if (window.location.pathname.includes("cover")) kind = "cover";
      else kind = "resume";
    }
    const pdfUrl = buildPdfDownloadUrl(kind, params, true);
    const win = window.open(pdfUrl, "_blank", "noopener,noreferrer");
    if (!win) {
      window.location.href = pdfUrl;
      return;
    }
    // PDF viewers: user prints from there — no HTML title/URL chrome.
  }

  function printNow() {
    if (window.location.pathname.includes("resume") || window.location.pathname.includes("cover")) {
      printCleanPdf();
      return;
    }
    printDocument(window);
  }

  document.querySelectorAll("[data-print]").forEach((btn) => {
    btn.addEventListener("click", printNow);
  });

  document.querySelectorAll("[data-download-pdf]").forEach((btn) => {
    btn.addEventListener("click", () => downloadCleanPdf());
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
  const doc = studio.dataset.doc || "resume";
  const basePath = doc === "cover" ? "/cover-letter.php" : "/resume.php";

  function previewUrl() {
    const q = new URLSearchParams({
      embed: "1",
      theme,
      accent,
      font,
      pdf: "1",
    });
    return `${basePath}?${q.toString()}`;
  }

  function fullUrl() {
    const q = new URLSearchParams({ theme, accent, font, pdf: "1" });
    return `${basePath}?${q.toString()}`;
  }

  function syncUi() {
    if (themeInput) themeInput.value = theme;
    if (accentInput) accentInput.value = accent;
    if (fontInput) fontInput.value = font;
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

    document.documentElement.style.setProperty("--accent", accent);
    const url = new URL(window.location.href);
    url.searchParams.set("doc", doc);
    url.searchParams.set("theme", theme);
    url.searchParams.set("accent", accent);
    url.searchParams.set("font", font);
    window.history.replaceState({}, "", url);
  }

  studio.querySelectorAll("[data-theme-pick]").forEach((btn) => {
    btn.addEventListener("click", () => {
      theme = btn.getAttribute("data-theme-pick") || theme;
      syncUi();
    });
  });

  studio.querySelectorAll("[data-font-pick]").forEach((btn) => {
    btn.addEventListener("click", () => {
      font = btn.getAttribute("data-font-pick") || font;
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
      printCleanPdf(doc === "cover" ? "cover" : "resume", { theme, font, accent });
    });
  });

  studio.querySelectorAll("[data-studio-pdf]").forEach((btn) => {
    btn.addEventListener("click", () => {
      downloadCleanPdf(doc === "cover" ? "cover" : "resume", { theme, font, accent });
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
