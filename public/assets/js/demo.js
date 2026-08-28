(() => {
  const root = document.querySelector("[data-demo-root]");
  if (!root) return;

  const toastEl = document.querySelector("[data-demo-toast]");
  let toastTimer = null;

  function showToast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toastEl.hidden = true;
    }, 2800);
  }

  function setTab(name) {
    root.querySelectorAll("[data-demo-tab]").forEach((btn) => {
      const on = btn.getAttribute("data-demo-tab") === name;
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-selected", on ? "true" : "false");
    });
    root.querySelectorAll("[data-demo-panel]").forEach((panel) => {
      const on = panel.getAttribute("data-demo-panel") === name;
      panel.classList.toggle("is-active", on);
      panel.hidden = !on;
    });
  }

  root.querySelectorAll("[data-demo-tab]").forEach((btn) => {
    btn.addEventListener("click", () => {
      setTab(btn.getAttribute("data-demo-tab") || "home");
    });
  });

  root.querySelectorAll("[data-demo-goto]").forEach((btn) => {
    btn.addEventListener("click", () => {
      setTab(btn.getAttribute("data-demo-goto") || "home");
    });
  });

  root.querySelectorAll(".demo-goto-apps").forEach((btn) => {
    btn.addEventListener("click", () => {
      const status = btn.getAttribute("data-demo-app-status") || "all";
      setTab("applications");
      filterApps(status);
    });
  });

  root.querySelectorAll(".demo-locked").forEach((btn) => {
    btn.addEventListener("click", () => {
      showToast("Available after you create an account.");
    });
  });

  // Jobs filters
  let jobFilter = "all";
  const jobCards = root.querySelectorAll("[data-demo-job-card]");
  const jobsCount = root.querySelector("[data-demo-jobs-count]");
  const jobsEmpty = root.querySelector("[data-demo-jobs-empty]");

  function applyJobFilter() {
    let visible = 0;
    jobCards.forEach((card) => {
      let show = true;
      if (jobFilter === "hamburg") {
        show = card.getAttribute("data-city") === "hamburg";
      } else if (jobFilter === "student") {
        show = card.getAttribute("data-student") === "1";
      } else if (jobFilter === "match") {
        show = card.getAttribute("data-match") === "1";
      }
      card.classList.toggle("d-none", !show);
      if (show) visible++;
    });
    if (jobsCount) {
      jobsCount.textContent = visible + " sample job" + (visible === 1 ? "" : "s");
    }
    if (jobsEmpty) {
      jobsEmpty.classList.toggle("d-none", visible > 0);
    }
  }

  root.querySelectorAll(".demo-job-filter").forEach((btn) => {
    btn.addEventListener("click", () => {
      jobFilter = btn.getAttribute("data-demo-job-filter") || "all";
      root.querySelectorAll(".demo-job-filter").forEach((b) => {
        b.classList.toggle("is-active", b === btn);
      });
      applyJobFilter();
    });
  });
  root.querySelector(".demo-job-filter[data-demo-job-filter='all']")?.classList.add("is-active");

  // Applications filters
  function filterApps(status) {
    root.querySelectorAll(".demo-app-filter").forEach((chip) => {
      chip.classList.toggle("is-active", chip.getAttribute("data-demo-app-filter") === status);
    });
    root.querySelectorAll("[data-demo-app-row]").forEach((row) => {
      const rowStatus = row.getAttribute("data-status") || "";
      const show = status === "all" || rowStatus === status;
      row.classList.toggle("d-none", !show);
    });
  }

  root.querySelectorAll(".demo-app-filter").forEach((chip) => {
    chip.addEventListener("click", () => {
      filterApps(chip.getAttribute("data-demo-app-filter") || "all");
    });
  });

  // Tailor demo
  const tailorBtn = root.querySelector("[data-demo-tailor-run]");
  const tailorResult = root.querySelector("[data-demo-tailor-result]");
  const tailorPreview = root.querySelector("[data-demo-tailor-preview]");
  tailorBtn?.addEventListener("click", () => {
    tailorResult?.classList.remove("d-none");
    tailorPreview?.classList.remove("d-none");
    tailorPreview?.scrollIntoView({ behavior: "smooth", block: "nearest" });
  });
})();
