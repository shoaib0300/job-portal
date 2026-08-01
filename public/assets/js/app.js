(() => {
  const themeLabels = {
    classic: "Classic",
    modern: "Modern",
    compact: "Compact",
    sidebar: "Sidebar",
    executive: "Executive",
    company: "Company tint",
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
