(() => {
  const themeLabels = {
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

  function printNow() {
    window.print();
  }

  function downloadPdfHint() {
    document.body.classList.add("pdf-mode");
    window.print();
    window.setTimeout(() => document.body.classList.remove("pdf-mode"), 500);
  }

  document.querySelectorAll("[data-print]").forEach((btn) => {
    btn.addEventListener("click", printNow);
  });

  document.querySelectorAll("[data-download-pdf]").forEach((btn) => {
    btn.addEventListener("click", downloadPdfHint);
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

  // Section reorder (arrows + drag)
  const sorter = document.querySelector("[data-section-sorter]");
  if (sorter) {
    const list = sorter.querySelector("[data-sort-list]");

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
      if (!row) return;
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
          event.dataTransfer.setData("text/plain", row.id || "section");
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
        if (!dragItem || target === dragItem) return;
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
  }

  const studio = document.querySelector("[data-design-studio]");
  if (!studio) return;

  const frame = studio.querySelector("[data-preview-frame]");
  const themeInput = studio.querySelector("[data-theme-input]");
  const accentInput = studio.querySelector("[data-accent-input]");
  const labelEl = studio.querySelector("[data-preview-label]");
  const openFull = studio.querySelector("[data-open-full]");
  const form = studio.querySelector("[data-design-form]");
  const customColor = studio.querySelector("[data-accent-custom]");

  let theme = studio.dataset.theme || "classic";
  let accent = studio.dataset.accent || "#1a5f4a";
  const doc = studio.dataset.doc || "resume";
  const basePath = doc === "cover" ? "/cover-letter.php" : "/resume.php";

  function previewUrl() {
    const q = new URLSearchParams({
      embed: "1",
      theme,
      accent,
      pdf: "1",
    });
    return `${basePath}?${q.toString()}`;
  }

  function fullUrl() {
    const q = new URLSearchParams({ theme, accent, pdf: "1" });
    return `${basePath}?${q.toString()}`;
  }

  function syncUi() {
    if (themeInput) themeInput.value = theme;
    if (accentInput) accentInput.value = accent;
    if (customColor) customColor.value = accent;
    if (frame) frame.src = previewUrl();
    if (openFull) openFull.href = fullUrl();
    if (labelEl) {
      labelEl.textContent = `Preview · ${themeLabels[theme] || theme}`;
    }

    studio.querySelectorAll("[data-theme-pick]").forEach((btn) => {
      const on = btn.getAttribute("data-theme-pick") === theme;
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
    window.history.replaceState({}, "", url);
  }

  studio.querySelectorAll("[data-theme-pick]").forEach((btn) => {
    btn.addEventListener("click", () => {
      theme = btn.getAttribute("data-theme-pick") || theme;
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

  function printFrame() {
    if (!frame || !frame.contentWindow) {
      printNow();
      return;
    }
    frame.contentWindow.focus();
    frame.contentWindow.print();
  }

  studio.querySelectorAll("[data-studio-print]").forEach((btn) => {
    btn.addEventListener("click", printFrame);
  });

  studio.querySelectorAll("[data-studio-pdf]").forEach((btn) => {
    btn.addEventListener("click", () => {
      printFrame();
    });
  });

  if (form) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const data = new FormData(form);
      data.set("ajax", "1");
      data.set("theme", theme);
      data.set("accent_color", accent);
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
          flash.textContent = `Style applied: ${json.label}. You can print or download PDF now.`;
          studio.prepend(flash);
          window.setTimeout(() => flash.remove(), 3500);
        }
      } catch (_err) {
        form.submit();
      }
    });
  }
})();
