/**
 * Command Palette (Ctrl+K / Cmd+K) omnibox controller for fast navigation and actions.
 */

export function createCommandPalette({
  documentRef = globalThis.document,
  windowRef = globalThis.window,
  panelNavigate = () => {},
  hasPermission = () => true,
  setTheme = () => {},
  translate = (_key, fallback) => fallback,
} = {}) {
  const document = documentRef;
  const window = windowRef;

  const modalEl = document?.getElementById?.("panel-command-palette-modal");
  const inputEl = document?.getElementById?.("panel-command-palette-input");
  const resultsEl = document?.getElementById?.("panel-command-palette-results");
  const emptyEl = document?.getElementById?.("panel-command-palette-empty");
  const triggerBtn = document?.getElementById?.("panel-command-palette-trigger");

  let selectedIndex = 0;
  let currentResults = [];
  let isOpen = false;

  const getCommands = () => {
    const items = [
      {
        id: "nav-dashboard",
        type: "page",
        title: translate("admin.nav.dashboard", "Dashboard"),
        category: translate("admin.nav.main", "Ana Menü"),
        icon: "bi-speedometer2",
        path: "/panel/dashboard",
        permission: "admin.panel.access",
      },
      {
        id: "nav-series",
        type: "page",
        title: translate("admin.nav.series", "Seriler"),
        category: translate("admin.nav.content", "İçerik Yönetimi"),
        icon: "bi-collection",
        path: "/panel/series",
        permission: "admin.content.view",
      },
      {
        id: "nav-series-new",
        type: "action",
        title: translate("admin.series.new", "Yeni Seri Ekle"),
        category: translate("admin.nav.content", "İçerik Yönetimi"),
        icon: "bi-plus-circle",
        path: "/panel/series/new",
        permission: "admin.content.create",
      },
      {
        id: "nav-users",
        type: "page",
        title: translate("admin.nav.users", "Kullanıcılar"),
        category: translate("admin.nav.users", "Kullanıcılar"),
        icon: "bi-people",
        path: "/panel/user",
        permission: "admin.users.manage",
      },
      {
        id: "nav-blogs",
        type: "page",
        title: translate("admin.nav.blogs", "Bloglar"),
        category: translate("admin.nav.content", "İçerik Yönetimi"),
        icon: "bi-journal-text",
        path: "/panel/blog",
        permission: "admin.content.view",
      },
      {
        id: "nav-comments",
        type: "page",
        title: translate("admin.nav.comments", "Yorumlar"),
        category: translate("admin.nav.moderation", "Moderasyon"),
        icon: "bi-chat-dots",
        path: "/panel/comment",
        permission: "admin.comments.manage",
      },
      {
        id: "nav-reports",
        type: "page",
        title: translate("admin.nav.reports", "Raporlar"),
        category: translate("admin.nav.moderation", "Moderasyon"),
        icon: "bi-flag",
        path: "/panel/reports",
        permission: "admin.reports.manage",
      },
      {
        id: "nav-packages",
        type: "page",
        title: translate("admin.nav.packages", "Paketler"),
        category: translate("admin.nav.commerce", "Finans & Ticaret"),
        icon: "bi-box-seam",
        path: "/panel/package",
        permission: "admin.shop.manage",
      },
      {
        id: "nav-finance",
        type: "page",
        title: translate("admin.nav.finance", "Finans"),
        category: translate("admin.nav.commerce", "Finans & Ticaret"),
        icon: "bi-wallet2",
        path: "/panel/finance",
        permission: "admin.finance.view",
      },
      {
        id: "nav-queue",
        type: "page",
        title: translate("admin.ops.queue_jobs", "Kuyruk İşleri"),
        category: translate("admin.nav.operations", "Operasyon & Bakım"),
        icon: "bi-layers",
        path: "/panel/ops/queue",
        permission: "admin.panel.access",
      },
      {
        id: "nav-logs",
        type: "page",
        title: translate("admin.ops.system_logs", "Sistem Logları"),
        category: translate("admin.nav.operations", "Operasyon & Bakım"),
        icon: "bi-journal-code",
        path: "/panel/ops/logs",
        permission: "admin.logs.view",
      },
      {
        id: "nav-uploads",
        type: "page",
        title: translate("admin.ops.uploads", "Dosya Yüklemeleri"),
        category: translate("admin.nav.operations", "Operasyon & Bakım"),
        icon: "bi-cloud-arrow-up",
        path: "/panel/ops/uploads",
        permission: "admin.uploads.manage",
      },
      {
        id: "nav-config",
        type: "page",
        title: translate("admin.nav.config", "Genel Ayarlar"),
        category: translate("admin.nav.settings", "Sistem & Yapılandırma"),
        icon: "bi-gear",
        path: "/panel/config",
        permission: "admin.config.manage",
      },
      {
        id: "nav-webhook",
        type: "page",
        title: translate("admin.nav.webhook", "Webhooklar"),
        category: translate("admin.nav.settings", "Sistem & Yapılandırma"),
        icon: "bi-broadcast-pin",
        path: "/panel/webhook",
        permission: "admin.config.manage",
      },
      {
        id: "nav-taxonomy",
        type: "page",
        title: translate("admin.nav.taxonomy", "Tür ve Etiketler"),
        category: translate("admin.nav.content", "İçerik Yönetimi"),
        icon: "bi-tags",
        path: "/panel/taxonomy",
        permission: "admin.taxonomy.manage",
      },
      {
        id: "nav-team",
        type: "page",
        title: translate("admin.nav.team", "Çeviri Ekibi"),
        category: translate("admin.nav.content", "İçerik Yönetimi"),
        icon: "bi-people-fill",
        path: "/panel/team",
        permission: "admin.content.manage",
      },
      {
        id: "nav-rbac",
        type: "page",
        title: translate("admin.nav.rbac", "Rol ve Yetkiler"),
        category: translate("admin.nav.settings", "Sistem & Yapılandırma"),
        icon: "bi-shield-check",
        path: "/panel/rbac",
        permission: "admin.rbac.manage",
      },
      {
        id: "nav-env",
        type: "page",
        title: translate("admin.nav.env", "Ortam Değişkenleri"),
        category: translate("admin.nav.settings", "Sistem & Yapılandırma"),
        icon: "bi-sliders",
        path: "/panel/env",
        permission: "admin.env.manage",
      },
      // Theme Actions
      {
        id: "action-theme-light",
        type: "action",
        title: `${translate("admin.theme.toggle", "Tema Değiştir")}: ${translate("admin.theme.light", "Açık")}`,
        category: translate("admin.theme.toggle", "Tema"),
        icon: "bi-sun-fill",
        handler: () => setTheme("light"),
      },
      {
        id: "action-theme-dark",
        type: "action",
        title: `${translate("admin.theme.toggle", "Tema Değiştir")}: ${translate("admin.theme.dark", "Koyu")}`,
        category: translate("admin.theme.toggle", "Tema"),
        icon: "bi-moon-stars-fill",
        handler: () => setTheme("dark"),
      },
      {
        id: "action-theme-auto",
        type: "action",
        title: `${translate("admin.theme.toggle", "Tema Değiştir")}: ${translate("admin.theme.auto", "Otomatik")}`,
        category: translate("admin.theme.toggle", "Tema"),
        icon: "bi-circle-half",
        handler: () => setTheme("auto"),
      },
    ];

    return items.filter((item) => !item.permission || hasPermission(item.permission));
  };

  function filterCommands(query) {
    const q = (query || "").trim().toLowerCase();
    const all = getCommands();
    if (!q) return all;
    return all.filter((cmd) => {
      const matchTitle = cmd.title.toLowerCase().includes(q);
      const matchCat = cmd.category.toLowerCase().includes(q);
      const matchPath = cmd.path ? cmd.path.toLowerCase().includes(q) : false;
      return matchTitle || matchCat || matchPath;
    });
  }

  function renderResults() {
    if (!resultsEl) return;
    resultsEl.innerHTML = "";

    if (currentResults.length === 0) {
      if (emptyEl) emptyEl.hidden = false;
      return;
    }
    if (emptyEl) emptyEl.hidden = true;

    currentResults.forEach((cmd, idx) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = `list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3 border-0 rounded-2 mb-1 ${idx === selectedIndex ? "active" : ""}`;
      button.dataset.index = String(idx);

      const left = document.createElement("div");
      left.className = "d-flex align-items-center gap-3";

      const icon = document.createElement("i");
      icon.className = `bi ${cmd.icon} fs-5 ${idx === selectedIndex ? "text-white" : "text-primary"}`;
      left.appendChild(icon);

      const textDiv = document.createElement("div");
      const titleSpan = document.createElement("span");
      titleSpan.className = "fw-medium";
      titleSpan.textContent = cmd.title;
      textDiv.appendChild(titleSpan);

      if (cmd.path) {
        const pathSpan = document.createElement("span");
        pathSpan.className = `small ms-2 ${idx === selectedIndex ? "text-white-50" : "text-secondary"}`;
        pathSpan.textContent = cmd.path;
        textDiv.appendChild(pathSpan);
      }
      left.appendChild(textDiv);
      button.appendChild(left);

      const badge = document.createElement("span");
      badge.className = `badge rounded-pill ${idx === selectedIndex ? "bg-white text-primary" : "bg-body-secondary text-body"}`;
      badge.textContent = cmd.category;
      button.appendChild(badge);

      button.addEventListener("click", () => executeCommand(cmd));
      resultsEl.appendChild(button);
    });

    const activeItem = resultsEl.querySelector(".active");
    if (activeItem && typeof activeItem.scrollIntoView === "function") {
      activeItem.scrollIntoView({ block: "nearest" });
    }
  }

  function executeCommand(cmd) {
    if (!cmd) return;
    close();
    if (cmd.path) {
      panelNavigate(cmd.path);
    } else if (typeof cmd.handler === "function") {
      cmd.handler();
    }
  }

  function open() {
    isOpen = true;
    if (inputEl) inputEl.value = "";
    selectedIndex = 0;
    currentResults = filterCommands("");
    renderResults();

    if (modalEl) {
      const bs = window?.bootstrap;
      if (bs?.Modal) {
        bs.Modal.getOrCreateInstance(modalEl).show();
      } else {
        modalEl.classList.add("show");
        modalEl.style.display = "block";
        modalEl.removeAttribute("aria-hidden");
      }
    }
    setTimeout(() => inputEl?.focus?.(), 50);
  }

  function close() {
    isOpen = false;
    if (modalEl) {
      const bs = window?.bootstrap;
      if (bs?.Modal) {
        try {
          bs.Modal.getOrCreateInstance(modalEl).hide();
        } catch {}
      } else {
        modalEl.classList.remove("show");
        modalEl.style.display = "none";
        modalEl.setAttribute("aria-hidden", "true");
      }
    }
  }

  function toggle() {
    if (isOpen) {
      close();
    } else {
      open();
    }
  }

  const onGlobalKeydown = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key?.toLowerCase() === "k") {
      event.preventDefault();
      toggle();
      return;
    }
    if (!isOpen) return;

    if (event.key === "Escape") {
      event.preventDefault();
      close();
    } else if (event.key === "ArrowDown") {
      event.preventDefault();
      if (currentResults.length > 0) {
        selectedIndex = (selectedIndex + 1) % currentResults.length;
        renderResults();
      }
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      if (currentResults.length > 0) {
        selectedIndex = (selectedIndex - 1 + currentResults.length) % currentResults.length;
        renderResults();
      }
    } else if (event.key === "Enter") {
      event.preventDefault();
      if (currentResults[selectedIndex]) {
        executeCommand(currentResults[selectedIndex]);
      }
    }
  };

  const onInput = () => {
    selectedIndex = 0;
    currentResults = filterCommands(inputEl?.value || "");
    renderResults();
  };

  const onTriggerClick = (e) => {
    e.preventDefault();
    open();
  };

  window?.addEventListener?.("keydown", onGlobalKeydown);
  inputEl?.addEventListener?.("input", onInput);
  triggerBtn?.addEventListener?.("click", onTriggerClick);

  function cleanup() {
    window?.removeEventListener?.("keydown", onGlobalKeydown);
    inputEl?.removeEventListener?.("input", onInput);
    triggerBtn?.removeEventListener?.("click", onTriggerClick);
  }

  return Object.freeze({
    open,
    close,
    toggle,
    cleanup,
    filterCommands,
  });
}
