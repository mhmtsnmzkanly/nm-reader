function setSidebarGroupState(item, toggle, open) {
  item.classList.toggle("menu-open", open);
  toggle.setAttribute("aria-expanded", String(open));
  const submenu = item.querySelector(":scope > .nav-treeview");
  if (submenu) submenu.style.display = open ? "block" : "none";
}

// AdminLTE sidebar/dropdown helpers.
// Treeview state is panel-owned so it remains deterministic even when the
// optional AdminLTE bundle is unavailable or loaded in a different order.
(() => {
  const body = document.body;
  const sidebarToggle = document.querySelector('[data-lte-toggle="sidebar"]');
  const sidebarOverlay = document.querySelector(".sidebar-overlay");
  const sidebarNav = document.querySelector("#panel-sidebar-nav");

  sidebarToggle?.addEventListener("click", (event) => {
    event.preventDefault();
    const wasOpen = body.classList.contains("sidebar-open");
    window.setTimeout(() => {
      if (body.classList.contains("sidebar-open") === wasOpen) {
        body.classList.toggle("sidebar-open", !wasOpen);
      }
    }, 0);
  });
  sidebarOverlay?.addEventListener("click", () =>
    body.classList.remove("sidebar-open"),
  );

  // Panel-owned treeview toggles. Route links and group buttons have separate
  // selectors, so opening a group never enters the panel router.
  sidebarNav?.addEventListener("click", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const toggle = target?.closest(
      '#panel-sidebar-nav > .nav-item > a[role="button"][href="#"]',
    );
    if (!toggle) return;
    event.preventDefault();
    const item = toggle.closest(".nav-item");
    if (!item) return;
    setSidebarGroupState(item, toggle, !item.classList.contains("menu-open"));
  });

  document.addEventListener("click", (event) => {
    if (window.bootstrap?.Dropdown) return;
    const target = event.target instanceof Element ? event.target : null;
    const toggle = target?.closest('[data-bs-toggle="dropdown"]');
    document.querySelectorAll(".dropdown-menu.show").forEach((menu) => {
      if (!toggle || !menu.closest(".dropdown")?.contains(toggle))
        menu.classList.remove("show");
    });
    if (!toggle) return;
    event.preventDefault();
    const menu = toggle.closest(".dropdown")?.querySelector(".dropdown-menu");
    menu?.classList.toggle("show");
  });
})();

// Lime-CSR admin panel application.
import {
  createStore,
  mount,
} from "https://cdn.jsdelivr.net/npm/lime-csr-js@0.2.8/dist/index.min.js";
const configuredNextYear =
  document
    .querySelector('meta[name="nmr-next-year"]')
    ?.getAttribute("content") || "";
const nextYear = /^\d{4}$/.test(configuredNextYear)
  ? configuredNextYear
  : String(new Date().getFullYear() + 1);

// 1. Initial State Store
const store = createStore({
  currentRoute: "dashboard",
  overview: {
    total_users: 0,
    total_contents: 0,
    total_chapters: 0,
    queue_pending: 0,
  },
  topContents: [],
  analytics: {
    visits_daily: 0,
    visits_weekly: 0,
    visits_monthly: 0,
    home_to_content: "0%",
    content_to_chapter: "0%",
    error_rate: "0%",
    p95: "0 ms",
    search_total: 0,
    zero_result_pct: "0%",
    d1_retention: "0%",
    new_users: 0,
    total_coins: 0,
    total_unlocks: 0,
    blog_total: 0,
    blog_visible: 0,
    blog_hidden: 0,
    blog_deleted: 0,
    blog_created: 0,
    blog_approved: 0,
  },
  dashboardGenres: [],
  dashboardTags: [],
  dashboardReputation: [],
  dashboardTypes: [],
  dashboardChapters: [],
  dashboardBlogAuthors: [],
  dashboardBlogDailyCreated: [],
  dashboardBlogDailyApproved: [],
  monetizationSeries: [],
  zeroResultSearches: [],
  seriesList: [],
  seriesMeta: { page: 1, total_pages: 1, total: 0 },
  usersList: [],
  usersMeta: { page: 1, total_pages: 1, total: 0 },
  userDetailId: null,
  userDetail: null,
  userCommentsList: [],
  userCommentsMeta: { page: 1, total_pages: 1, total: 0 },
  userBlogsList: [],
  userBlogsMeta: { page: 1, total_pages: 1, total: 0 },
  userViolationsList: [],
  userViolationsMeta: { page: 1, total_pages: 1, total: 0 },
  userWalletId: null,
  userWallet: null,
  userWalletMeta: { page: 1, total_pages: 1, total: 0 },
  blogsList: [],
  blogsMeta: { page: 1, total_pages: 1, total: 0 },
  commentsList: [],
  commentsMeta: { page: 1, total_pages: 1, total: 0 },
  reportsList: [],
  reportsMeta: { page: 1, total_pages: 1, total: 0, counts: {} },
  packagesList: [],
  financeList: [],
  financeMeta: { page: 1, total_pages: 1, total: 0 },
  financeSummary: {},
  logsList: [],
  logsMeta: { page: 1, total_pages: 1, total: 0 },
  uploadsList: [],
  uploadsMeta: { page: 1, total_pages: 1, total: 0 },
  uploadsStats: {},
  queueJobsList: [],
  queueMeta: { page: 1, total_pages: 1, total: 0 },
  systemHealth: {},
  config: (() => {
    const initial = window.__NMR_CONTEXT?.site_config || {};
    return {
      site_name: "NM Reader",
      site_abbreviation: "NMR",
      site_slogan: "En İyi Çevrimiçi Manga ve Novel Okuyucusu",
      site_description: "Read manga, manhwa, webtoon and novels.",
      default_language: "tr",
      footer_text: "© 2026 NM Reader. Tüm hakları saklıdır.",
      default_theme: "dark",
      site_logo: "/assets/img/logo-header.svg",
      logo_url: "/assets/img/logo-footer.svg",
      favicon_url: "/favicon.ico",
      default_profile_image: "/assets/img/default-profile.svg",
      default_content_cover_image: "/assets/img/covers/placeholder.svg",
      maintenance_mode: false,
      maintenance_whitelist_text: Array.isArray(
        initial.maintenance_whitelist_ips,
      )
        ? initial.maintenance_whitelist_ips.join("\n")
        : "127.0.0.1\n::1",
      mail_enabled: true,
      mail_send_on_register: true,
      email_verification_required: false,
      mail_from_name: "NM Reader",
      mail_from_address: "noreply@nmreader.com",
      password_reset_subject: "Şifre Sıfırlama Talebi - {{site_name}}",
      password_reset_body: "",
      email_verification_subject:
        "E-posta Adresinizi Doğrulayın - {{site_name}}",
      email_verification_body: "",
      ...initial,
    };
  })(),
});

const csrfToken = window.__NMR_CONTEXT?.auth?.csrf_token || "";
const grantedPermissions = new Set(
  window.__NMR_CONTEXT?.auth?.permissions || [],
);

function hasPermission(...permissions) {
  return (
    grantedPermissions.has("*") ||
    permissions.some((permission) => grantedPermissions.has(permission))
  );
}

function applyPermissionVisibility(root = document) {
  root.querySelectorAll("[data-requires-permission]").forEach((element) => {
    const required = String(element.dataset.requiresPermission || "")
      .split(",")
      .map((value) => value.trim())
      .filter(Boolean);
    element.hidden = required.length > 0 && !hasPermission(...required);
  });
}

// 2. Toast Notification Helper
function showToast(message, type = "success") {
  const container = document.getElementById("lime-toasts");
  if (!container) return;
  const toast = document.createElement("div");
  toast.className = `alert alert-${type} shadow-lg py-2 px-3 mb-0 rounded-3 d-flex align-items-center gap-2 text-dark`;
  const icon = document.createElement("i");
  icon.className = `bi bi-${type === "success" ? "check-circle-fill text-success" : "exclamation-circle-fill text-danger"}`;
  const text = document.createElement("span");
  text.textContent = String(message ?? "");
  toast.append(icon, text);
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

function setIconButtonLabel(button, iconName, label) {
  if (!button) return;
  const icon = document.createElement("i");
  icon.className = `bi ${iconName} me-1`;
  const text = document.createElement("span");
  text.textContent = label;
  button.replaceChildren(icon, text);
}

// 3. Authenticated Fetch Helper
let pageEpoch = 0;
let pageRequests = new AbortController();

function assertCurrentPage(epoch) {
  if (epoch !== pageEpoch)
    throw new DOMException("Sayfa değişti.", "AbortError");
}

async function api(path, options = {}) {
  const epoch = pageEpoch;
  const detached = options.detached === true;
  delete options.detached;
  if (!detached) options.signal = pageRequests.signal;
  const alreadyRetried = options._reauthAttempt === true;
  delete options._reauthAttempt;
  options.headers = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
    "X-CSRF-Token": csrfToken,
    ...(options.headers || {}),
  };
  if (
    options.body &&
    typeof options.body === "object" &&
    !(options.body instanceof FormData)
  ) {
    options.headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(options.body);
  }
  const res = await fetch("/api/v1/admin" + path, options);
  if (!detached) assertCurrentPage(epoch);
  if (res.status === 428 && !alreadyRetried && path !== "/auth/reauth") {
    const password = prompt(
      "Bu kritik işlem için yönetici parolanızı yeniden girin:",
    );
    if (!password) throw new Error("Kritik işlem iptal edildi.");
    const verification = await fetch("/api/v1/admin/auth/reauth", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify({ password }),
    });
    if (!verification.ok) {
      const error = await verification.json().catch(() => ({}));
      throw new Error(error?.error?.message || "Parola doğrulanamadı.");
    }
    return api(path, { ...options, _reauthAttempt: true });
  }
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err?.error?.message || `HTTP ${res.status}`);
  }
  const result = res.status === 204 ? null : await res.json();
  if (!detached) assertCurrentPage(epoch);
  return result;
}

function responseItems(response) {
  if (Array.isArray(response?.data)) return response.data;
  if (Array.isArray(response?.data?.items)) return response.data.items;
  return [];
}

const reloadTimers = new Map();
function scheduleReload(key, callback) {
  clearTimeout(reloadTimers.get(key));
  const epoch = pageEpoch;
  reloadTimers.set(
    key,
    setTimeout(() => {
      reloadTimers.delete(key);
      if (epoch === pageEpoch) callback();
    }, 300),
  );
}

// Lime templates assign attribute values through setAttribute, so they must
// receive the raw validated path (escaping here would display entities).
function safeLocalPath(value) {
  const url = String(value || "");
  return url.startsWith("/") && !url.startsWith("//") ? url : "#";
}

function mountPartial(name, target, context = {}) {
  if (!target) return null;
  // The page mount owns Lime's delegated event listeners on #panel-app.
  // Partials are nested inside that target, so attaching handlers here would
  // invoke the same data-on-* action more than once as the event bubbles.
  // They are also rendered as short-lived snapshots; the parent page or the
  // page controller explicitly remounts them when data changes, so they do not
  // need independent reactive subscriptions.
  return mount(name, { target, context });
}

function mountHeaderCells(target, labels, className = "") {
  if (!target) return;
  target.replaceChildren(
    ...labels.map((label) => {
      const cell = document.createElement("th");
      cell.className = className;
      cell.textContent = String(label ?? "");
      return cell;
    }),
  );
}

function setTableRows(id, partialName, items, colspan, extra = {}) {
  const target = document.getElementById(id);
  if (!target) return;
  if (extra.error_message) {
    mountPartial("panel-table-error", target, {
      colspan,
      error_message: extra.error_message,
    });
    return;
  }
  mountPartial(partialName, target, {
    items: Array.isArray(items) ? items : [],
    has_items: Array.isArray(items) && items.length > 0,
    colspan,
    ...extra,
  });
}

function responseMeta(response) {
  return {
    page: Number(response?.meta?.page || 1),
    total_pages: Math.max(1, Number(response?.meta?.total_pages || 1)),
    total: Number(response?.meta?.total || 0),
  };
}

function renderPager(id, meta, previousHandler, nextHandler) {
  const target = document.getElementById(id);
  if (!target) return;
  const page = Number(meta?.page || 1);
  const totalPages = Math.max(1, Number(meta?.total_pages || 1));
  mountPartial("panel-pager", target, {
    page,
    total_pages: totalPages,
    total: Number(meta?.total || 0),
    previous_handler: previousHandler,
    next_handler: nextHandler,
    has_previous: page > 1,
    has_next: page < totalPages,
  });
}

function renderDashboardTables() {
  const list = (key, transform = (item) => item) =>
    (store.get(key) || []).map(transform);
  setTableRows(
    "panel-top-contents",
    "panel-rows-dashboard-top",
    list("topContents", (item) => ({
      title: item.title,
      type: item.type,
      views: Number(item.view_count_7d || 0),
      comments: Number(item.comment_count_7d || 0),
    })),
    4,
  );
  setTableRows(
    "panel-monetization-series",
    "panel-rows-dashboard-monetization",
    list("monetizationSeries", (item) => ({
      title: item.title,
      unlocks: Number(item.unlock_count || 0),
      coins: Number(item.total_coins || 0),
    })),
    3,
  );
  setTableRows(
    "panel-zero-searches",
    "panel-rows-dashboard-zero-searches",
    list("zeroResultSearches", (item) => ({
      query: item.query,
      count: Number(item.search_count || 0),
      last_searched_at: item.last_searched_at,
    })),
    3,
  );
  setTableRows(
    "panel-dashboard-genres",
    "panel-rows-dashboard-views",
    list("dashboardGenres", (item) => ({
      name: item.name,
      views: Number(item.view_total || 0),
    })),
    2,
  );
  setTableRows(
    "panel-dashboard-tags",
    "panel-rows-dashboard-views",
    list("dashboardTags", (item) => ({
      name: item.name,
      views: Number(item.view_total || 0),
    })),
    2,
  );
  setTableRows(
    "panel-dashboard-reputation",
    "panel-rows-dashboard-reputation",
    list("dashboardReputation", (item) => ({
      username: item.username,
      comments: Number(item.comment_count || 0),
      score: Number(item.score || 0).toFixed(1),
    })),
    3,
  );
  setTableRows(
    "panel-dashboard-types",
    "panel-rows-dashboard-types",
    list("dashboardTypes", (item) => ({
      type: item.type,
      views: Number(item.view_total || 0),
    })),
    2,
  );
  setTableRows(
    "panel-dashboard-chapters",
    "panel-rows-dashboard-chapters",
    list("dashboardChapters", (item) => ({
      content_title: item.content_title,
      chapter_number: item.chapter_number,
      views: Number(item.view_total || 0),
    })),
    2,
  );
  setTableRows(
    "panel-dashboard-blog-authors",
    "panel-rows-dashboard-blog-authors",
    list("dashboardBlogAuthors", (item) => ({
      username: item.username,
      approved: Number(item.approved_total || 0),
      blogs: Number(item.blog_total || 0),
    })),
    3,
  );
  const dailyBlog = new Map();
  (store.get("dashboardBlogDailyCreated") || []).forEach((item) =>
    dailyBlog.set(item.day, {
      day: item.day,
      created: Number(item.total || 0),
      approved: 0,
    }),
  );
  (store.get("dashboardBlogDailyApproved") || []).forEach((item) => {
    const current = dailyBlog.get(item.day) || {
      day: item.day,
      created: 0,
      approved: 0,
    };
    current.approved = Number(item.total || 0);
    dailyBlog.set(item.day, current);
  });
  setTableRows(
    "panel-dashboard-blog-daily",
    "panel-rows-dashboard-blog-daily",
    Array.from(dailyBlog.values()).sort((a, b) =>
      String(b.day).localeCompare(String(a.day)),
    ),
    3,
  );
}

function renderSeriesTable() {
  const lifecycleLabel = {
    draft: "Taslak",
    scheduled: "Zamanlandı",
    published: "Yayında",
    archived: "Arşivlendi",
  };
  const lifecycleClass = {
    draft: "bg-secondary-subtle text-secondary",
    scheduled: "bg-info-subtle text-info",
    published: "bg-success-subtle text-success",
    archived: "bg-dark-subtle text-dark",
  };
  const items = (store.get("seriesList") || []).map((item) => {
    const lifecycle = item.lifecycle_status || "published";
    const lifecycleAction =
      lifecycle === "archived"
        ? "restore"
        : lifecycle === "published"
          ? "archive"
          : "publish";
    const lifecycleIcon =
      lifecycle === "archived"
        ? "arrow-counterclockwise"
        : lifecycle === "published"
          ? "archive"
          : "send-check";
    const canUpdate = hasPermission("admin.content.update");
    return {
      ...item,
      lifecycle_label: lifecycleLabel[lifecycle] || lifecycle,
      lifecycle_class: lifecycleClass[lifecycle] || "bg-light text-dark",
      lifecycle_action: lifecycleAction,
      lifecycle_icon: lifecycleIcon,
      scheduled_class: item.scheduled_at ? "" : "d-none",
      update_class: canUpdate ? "" : "d-none",
      can_update: canUpdate,
      edit_url: `/panel/series/${encodeURIComponent(item.id)}/edit`,
    };
  });
  setTableRows("panel-series-list", "panel-rows-series", items, 6);
  renderPager(
    "panel-series-pager",
    store.get("seriesMeta"),
    "previousSeriesPage",
    "nextSeriesPage",
  );
}

function renderUsersTable() {
  const canInspect = hasPermission("admin.users.manage");
  const items = (store.get("usersList") || []).map((user) => ({
    ...user,
    can_inspect: canInspect,
    inspect_class: canInspect ? "" : "d-none",
    profile_url: `/panel/user/${encodeURIComponent(user.id)}`,
  }));
  setTableRows("panel-users-list", "panel-rows-users", items, 6);
  renderPager(
    "panel-users-pager",
    store.get("usersMeta"),
    "previousUsersPage",
    "nextUsersPage",
  );
}

function userViolationLevel(level) {
  return (
    {
      warning: ["Uyarı", "bg-info-subtle text-info"],
      removal: ["İçerik kaldırma", "bg-warning-subtle text-warning"],
      temporary: ["Süreli engel", "bg-danger-subtle text-danger"],
      permanent: ["Kalıcı engel", "bg-dark text-white"],
    }[String(level || "")] || [
      String(level || "-"),
      "bg-secondary-subtle text-secondary",
    ]
  );
}

function userModerationStatus(status) {
  return (
    {
      pending: ["Bekliyor", "bg-warning-subtle text-warning"],
      approved: ["Onaylı", "bg-success-subtle text-success"],
      hidden: ["Gizli", "bg-secondary-subtle text-secondary"],
      deleted: ["Silindi", "bg-danger-subtle text-danger"],
    }[String(status || "")] || [
      String(status || "-"),
      "bg-light text-secondary",
    ]
  );
}

function renderUserCommentsTable() {
  const items = (store.get("userCommentsList") || []).map((comment) => {
    const status = userModerationStatus(
      comment.moderation_status || "approved",
    );
    const context = comment.blog_title
      ? `Blog: ${comment.blog_title}`
      : comment.content_title
        ? `${comment.target_type === "chapter" ? "Bölüm" : "İçerik"}: ${comment.content_title}${comment.chapter_number ? ` #${comment.chapter_number}` : ""}`
        : `${comment.target_type || "Hedef"}: ${comment.target_id || "-"}`;
    return {
      ...comment,
      context_label: context,
      status_label: status[0],
      status_class: status[1],
      upvotes: Number(comment.upvote_count || 0),
      downvotes: Number(comment.downvote_count || 0),
    };
  });
  setTableRows(
    "panel-user-comments-list",
    "panel-rows-user-comments",
    items,
    5,
  );
  renderPager(
    "panel-user-comments-pager",
    store.get("userCommentsMeta"),
    "previousUserCommentsPage",
    "nextUserCommentsPage",
  );
}

function renderUserBlogsTable() {
  const labels = {
    draft: "Taslak",
    pending: "Bekliyor",
    published: "Yayınlandı",
    rejected: "Reddedildi",
    hidden: "Gizli",
  };
  const items = (store.get("userBlogsList") || []).map((blog) => {
    const status =
      blog.status || (Number(blog.approved) === 1 ? "published" : "pending");
    const statusClass =
      Number(blog.approved) === 1
        ? "bg-success-subtle text-success"
        : "bg-warning-subtle text-warning";
    return {
      ...blog,
      slug_label: blog.slug || blog.id,
      status_class: statusClass,
      status_label: labels[status] || status,
    };
  });
  setTableRows("panel-user-blogs-list", "panel-rows-user-blogs", items, 4);
  renderPager(
    "panel-user-blogs-pager",
    store.get("userBlogsMeta"),
    "previousUserBlogsPage",
    "nextUserBlogsPage",
  );
}

function renderUserViolationsTable() {
  const items = (store.get("userViolationsList") || []).map((violation) => {
    const level = userViolationLevel(violation.level);
    const active = violation.revoked_at
      ? "İptal edildi"
      : violation.ends_at &&
          new Date(violation.ends_at.replace(" ", "T")) < new Date()
        ? "Süresi doldu"
        : "Aktif";
    return {
      ...violation,
      level_label: level[0],
      level_class: level[1],
      active_label: active,
      scope_label: violation.scope || "general",
      action_label: violation.action || "-",
      target_type_label: violation.target_type || "-",
      target_id_label: violation.target_id || "-",
      moderator_label:
        violation.moderator_username || violation.moderator_user_id || "-",
    };
  });
  setTableRows(
    "panel-user-violations-list",
    "panel-rows-user-violations",
    items,
    5,
  );
  renderPager(
    "panel-user-violations-pager",
    store.get("userViolationsMeta"),
    "previousUserViolationsPage",
    "nextUserViolationsPage",
  );
}

async function loadUserCommentsData(userId, page = 1) {
  const requestEpoch = pageEpoch;
  if (!userId) return;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "10" });
    const values = {
      q: document.getElementById("panel-user-comments-search")?.value || "",
      target_type:
        document.getElementById("panel-user-comments-target")?.value || "",
      moderation_status:
        document.getElementById("panel-user-comments-status")?.value || "",
      sort:
        document.getElementById("panel-user-comments-sort")?.value || "newest",
    };
    Object.entries(values).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const response = await api(
      `/users/${encodeURIComponent(userId)}/comments?${params.toString()}`,
    );
    assertCurrentPage(requestEpoch);
    if (String(store.get("userDetailId")) !== String(userId)) return;
    store.batch(() => {
      store.set("userCommentsList", responseItems(response));
      store.set("userCommentsMeta", responseMeta(response));
    });
    renderUserCommentsTable();
  } catch (error) {
    if (error?.name === "AbortError") return;
    const target = document.getElementById("panel-user-comments-list");
    if (target)
      setTableRows(
        "panel-user-comments-list",
        "panel-rows-user-comments",
        [],
        5,
        { error_message: error.message },
      );
  }
}

async function loadUserBlogsData(userId, page = 1) {
  const requestEpoch = pageEpoch;
  if (!userId) return;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "10" });
    const values = {
      q: document.getElementById("panel-user-blogs-search")?.value || "",
      status: document.getElementById("panel-user-blogs-status")?.value || "",
      sort: document.getElementById("panel-user-blogs-sort")?.value || "newest",
    };
    Object.entries(values).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const response = await api(
      `/users/${encodeURIComponent(userId)}/blogs?${params.toString()}`,
    );
    assertCurrentPage(requestEpoch);
    if (String(store.get("userDetailId")) !== String(userId)) return;
    store.batch(() => {
      store.set("userBlogsList", responseItems(response));
      store.set("userBlogsMeta", responseMeta(response));
    });
    renderUserBlogsTable();
  } catch (error) {
    if (error?.name === "AbortError") return;
    const target = document.getElementById("panel-user-blogs-list");
    if (target)
      setTableRows("panel-user-blogs-list", "panel-rows-user-blogs", [], 4, {
        error_message: error.message,
      });
  }
}

async function loadUserViolationsData(userId, page = 1) {
  const requestEpoch = pageEpoch;
  if (!userId) return;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "10" });
    const level =
      document.getElementById("panel-user-violations-level")?.value || "";
    const scope =
      document.getElementById("panel-user-violations-scope")?.value || "";
    if (level) params.set("level", level);
    if (scope) params.set("scope", scope);
    const response = await api(
      `/users/${encodeURIComponent(userId)}/violations?${params.toString()}`,
    );
    assertCurrentPage(requestEpoch);
    if (String(store.get("userDetailId")) !== String(userId)) return;
    store.batch(() => {
      store.set("userViolationsList", responseItems(response));
      store.set("userViolationsMeta", responseMeta(response));
    });
    renderUserViolationsTable();
  } catch (error) {
    if (error?.name === "AbortError") return;
    const target = document.getElementById("panel-user-violations-list");
    if (target)
      setTableRows(
        "panel-user-violations-list",
        "panel-rows-user-violations",
        [],
        5,
        { error_message: error.message },
      );
  }
}

function userDetailFilters(pageRoot, userId) {
  const bindReload = (selector, key, loader) => {
    pageRoot.querySelectorAll(selector).forEach((input) => {
      const eventName = input.tagName === "INPUT" ? "input" : "change";
      input.addEventListener(eventName, () =>
        scheduleReload(key, () => loader(userId, 1)),
      );
    });
  };
  bindReload(
    "#panel-user-comments-search, #panel-user-comments-target, #panel-user-comments-status, #panel-user-comments-sort",
    "user-comments",
    loadUserCommentsData,
  );
  bindReload(
    "#panel-user-blogs-search, #panel-user-blogs-status, #panel-user-blogs-sort",
    "user-blogs",
    loadUserBlogsData,
  );
  bindReload(
    "#panel-user-violations-level, #panel-user-violations-scope",
    "user-violations",
    loadUserViolationsData,
  );
  pageRoot.querySelectorAll("[data-user-tab]").forEach((button) =>
    button.addEventListener("click", () => {
      const tab = button.dataset.userTab;
      pageRoot
        .querySelectorAll("[data-user-tab]")
        .forEach((item) => item.classList.toggle("active", item === button));
      pageRoot.querySelectorAll("[data-user-section]").forEach((section) => {
        section.hidden = section.dataset.userSection !== tab;
      });
      if (tab === "blogs")
        loadUserBlogsData(
          userId,
          Number(store.get("userBlogsMeta")?.page || 1),
        );
      if (tab === "violations")
        loadUserViolationsData(
          userId,
          Number(store.get("userViolationsMeta")?.page || 1),
        );
    }),
  );
}

async function loadUserDetailPage(userId) {
  const requestEpoch = pageEpoch;
  if (!userId) throw new Error("Kullanıcı kimliği bulunamadı");
  const [response, rolesResponse, walletResponse] = await Promise.all([
    api(`/users/${encodeURIComponent(userId)}/overview`),
    hasPermission("admin.panel.access")
      ? api("/rbac/roles").catch(() => null)
      : Promise.resolve(null),
    hasPermission("admin.wallet.view")
      ? api(`/wallets/${encodeURIComponent(userId)}`).catch(() => null)
      : Promise.resolve(null),
  ]);
  assertCurrentPage(requestEpoch);
  const overview = response?.data || {};
  const user = overview.user || {};
  const stats = overview.stats || {};
  const wallet = walletResponse?.data || {};
  const roles = responseItems(rolesResponse).map((role) => ({
    slug: role.slug,
    name: role.name || role.slug,
  }));
  const currentRole =
    String(user.role_names || "user")
      .split(",")[0]
      .trim() || "user";
  if (!roles.some((role) => role.slug === currentRole))
    roles.push({ slug: currentRole, name: currentRole });
  const restrictions = (overview.active_restrictions || []).map((item) => {
    const level = userViolationLevel(item.level);
    return {
      class_name: level[1],
      label: `${item.type || "general"}: ${level[0]}${item.ends_at ? ` · ${item.ends_at}` : ""}`,
    };
  });
  const profileImage = safeLocalPath(
    user.profile_image ||
      store.get("config")?.default_profile_image ||
      "/assets/img/default-profile.svg",
  );
  const userIdPath = encodeURIComponent(userId);
  const userName = user.username || userId;
  const context = {
    user_id: userIdPath,
    username_label: userName,
    display_name: user.display_name || user.username || userId,
    email: user.email || "-",
    bio: user.bio || "",
    role_label: user.role_names || "user",
    profile_image: profileImage,
    status_class: user.is_banned
      ? "bg-danger-subtle text-danger"
      : "bg-success-subtle text-success",
    status_icon: user.is_banned ? "bi-shield-exclamation" : "bi-shield-check",
    status_label: user.is_banned ? "Aktif kısıtlama" : "Etkileşim açık",
    restrictions,
    has_restrictions: restrictions.length > 0,
    roles,
    comments_total: Number(stats.comments_total || 0).toLocaleString("tr-TR"),
    blogs_total: Number(stats.blogs_total || 0).toLocaleString("tr-TR"),
    violations_total: Number(stats.violations_total || 0).toLocaleString(
      "tr-TR",
    ),
    balance_coin: Number(wallet.balance_coin || 0).toLocaleString("tr-TR"),
    can_penalty: hasPermission("admin.users.manage"),
    can_wallet: hasPermission("admin.wallet.view"),
  };
  store.batch(() => {
    store.set("userDetailId", userId);
    store.set("userDetail", { ...overview, wallet });
    store.set("userCommentsList", []);
    store.set("userBlogsList", []);
    store.set("userViolationsList", []);
    store.set("userCommentsMeta", { page: 1, total_pages: 1, total: 0 });
    store.set("userBlogsMeta", { page: 1, total_pages: 1, total: 0 });
    store.set("userViolationsMeta", { page: 1, total_pages: 1, total: 0 });
  });
  const page = mountPage("panel-user-detail-content", context);
  const roleSelect = page.querySelector("#panel-user-role");
  if (roleSelect) roleSelect.value = currentRole;
  const profileForm = page.querySelector("#panel-user-profile-form");
  profileForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = profileForm.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;
    try {
      await api(`/users/${encodeURIComponent(userId)}/profile`, {
        method: "PUT",
        body: Object.fromEntries(new FormData(profileForm).entries()),
      });
      showToast("Profil bilgileri güncellendi");
      await loadUserDetailPage(userId);
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
      if (submit) submit.disabled = false;
    }
  });
  userDetailFilters(page, userId);
  renderUserCommentsTable();
  renderUserBlogsTable();
  renderUserViolationsTable();
  await loadUserCommentsData(userId, 1);
  assertCurrentPage(requestEpoch);
}
function violationScopeForTarget(targetType) {
  return targetType === "comment"
    ? "comment"
    : targetType === "blog"
      ? "blog"
      : "general";
}

async function loadUserPenaltyPage(userId) {
  const requestEpoch = pageEpoch;
  if (!userId) throw new Error("Kullanıcı kimliği bulunamadı");
  let overview = store.get("userDetail");
  if (String(store.get("userDetailId")) !== String(userId) || !overview?.user) {
    overview =
      (await api(`/users/${encodeURIComponent(userId)}/overview`))?.data || {};
    assertCurrentPage(requestEpoch);
  }
  const user = overview.user || {};
  const page = mountPage("panel-user-penalty-content", {
    user_id: encodeURIComponent(userId),
    username: user.username || userId,
  });
  const form = page.querySelector("#panel-user-penalty-form");
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;
    try {
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.auto_escalate = form.elements.auto_escalate.checked;
      payload.scope = violationScopeForTarget(payload.target_type);
      if (payload.auto_escalate) payload.level = "warning";
      await api(`/users/${encodeURIComponent(userId)}/violations`, {
        method: "POST",
        body: payload,
      });
      showToast("Ceza kaydı oluşturuldu");
      panelNavigate(`/panel/user/${encodeURIComponent(userId)}`);
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
      if (submit) submit.disabled = false;
    }
  });
  const auto = form?.elements.auto_escalate;
  const level = form?.elements.level;
  const sync = () => {
    if (level) level.disabled = Boolean(auto?.checked);
  };
  auto?.addEventListener("change", sync);
  sync();
}

function renderBlogsTable() {
  const canModerate = hasPermission("admin.blog.hide");
  const items = (store.get("blogsList") || []).map((blog) => {
    const canApprove = canModerate && Boolean(blog.can_approve);
    const canHide = canModerate && Boolean(blog.can_hide);
    return {
      ...blog,
      can_approve: canApprove,
      can_hide: canHide,
      can_delete: canModerate,
      approve_class: canApprove ? "" : "d-none",
      hide_class: canHide ? "" : "d-none",
      delete_class: canModerate ? "" : "d-none",
    };
  });
  setTableRows("panel-blogs-list", "panel-rows-blogs", items, 5);
  renderPager(
    "panel-blogs-pager",
    store.get("blogsMeta"),
    "previousBlogsPage",
    "nextBlogsPage",
  );
}

function renderCommentsTable() {
  const statusLabel = {
    pending: "Bekliyor",
    approved: "Onaylı",
    hidden: "Gizli",
    deleted: "Silindi",
  };
  const statusClass = {
    pending: "bg-warning-subtle text-warning",
    approved: "bg-success-subtle text-success",
    hidden: "bg-secondary-subtle text-secondary",
    deleted: "bg-danger-subtle text-danger",
  };
  const canModerate = hasPermission("admin.comment.delete");
  const items = (store.get("commentsList") || []).map((comment) => {
    const status = comment.moderation_status || "approved";
    const nextStatus = status === "approved" ? "hidden" : "approved";
    return {
      ...comment,
      status_label: statusLabel[status] || status,
      status_class: statusClass[status] || "bg-light text-secondary",
      upvotes: Number(comment.upvote_count || 0),
      downvotes: Number(comment.downvote_count || 0),
      can_moderate: canModerate,
      moderate_class: canModerate ? "" : "d-none",
      next_status: nextStatus,
      next_label: nextStatus === "approved" ? "Onayla" : "Gizle",
      next_class: nextStatus === "approved" ? "success" : "warning",
      next_icon: nextStatus === "approved" ? "check-circle" : "eye-slash",
    };
  });
  setTableRows("panel-comments-list", "panel-rows-comments", items, 7);
  renderPager(
    "panel-comments-pager",
    store.get("commentsMeta"),
    "previousCommentsPage",
    "nextCommentsPage",
  );
}

function reportStatus(status) {
  return (
    {
      pending: ["Bekleyen", "bg-warning-subtle text-warning"],
      reviewing: ["İncelenen", "bg-info-subtle text-info"],
      resolved: ["Çözüldü", "bg-success-subtle text-success"],
      rejected: ["Reddedildi", "bg-secondary-subtle text-secondary"],
    }[status] || [status || "-", "bg-light text-secondary"]
  );
}

function renderReportsTable() {
  const items = (store.get("reportsList") || []).map((report) => {
    const status = reportStatus(report.status);
    const targetUrl = safeLocalPath(report.target_url);
    return {
      ...report,
      status_label: status[0],
      status_class: status[1],
      target_label:
        report.target_title || report.comment_snippet || report.target_id,
      has_target_url: targetUrl !== "#",
      target_url_safe: targetUrl,
      target_link_class: targetUrl === "#" ? "d-none" : "",
      detail_url: `/panel/reports/${Number(report.id)}`,
    };
  });
  setTableRows("panel-reports-list", "panel-rows-reports", items, 7);
  const meta = store.get("reportsMeta") || {};
  const counts = meta.counts || {};
  ["pending", "reviewing", "resolved", "rejected"].forEach((status) => {
    const element = document.getElementById(`panel-report-count-${status}`);
    if (element) element.textContent = String(Number(counts[status] || 0));
  });
  const page = Number(meta.page || 1);
  const totalPages = Math.max(1, Number(meta.total_pages || 1));
  const label = document.getElementById("panel-reports-page");
  if (label)
    label.textContent = `Sayfa ${page} / ${totalPages} · ${Number(meta.total || 0)} kayıt`;
  const previous = document.getElementById("panel-reports-prev");
  const next = document.getElementById("panel-reports-next");
  if (previous) previous.disabled = page <= 1;
  if (next) next.disabled = page >= totalPages;
}

function renderPackagesTable() {
  setTableRows(
    "panel-packages-list",
    "panel-rows-packages",
    (store.get("packagesList") || []).map((item) => ({ ...item })),
    6,
  );
}

function renderFinanceTable() {
  const eligible = new Set([
    "chapter_unlock",
    "series_unlock",
    "feature_unlock",
    "manual_debit",
  ]);
  const items = (store.get("financeList") || []).map((item) => {
    const canRefund =
      Number(item.coin_delta) < 0 &&
      eligible.has(item.type) &&
      Number(item.refunded_coin || 0) < Math.abs(Number(item.coin_delta));
    const canRefundAction = canRefund && hasPermission("admin.finance.refund");
    const refunded = Number(item.refunded_coin || 0) > 0;
    return {
      ...item,
      delta: Number(item.coin_delta || 0),
      delta_prefix: Number(item.coin_delta) > 0 ? "+" : "",
      delta_class:
        Number(item.coin_delta) >= 0 ? "text-success" : "text-danger",
      can_refund: canRefundAction,
      refund_class: canRefundAction ? "" : "d-none",
      refunded,
      refunded_class: refunded ? "" : "d-none",
      reference_label: `${item.reference_type || "-"} / ${item.reference_id || "-"}`,
      description_label: item.description || "-",
    };
  });
  setTableRows("panel-finance-list", "panel-rows-finance", items, 8);
  const summary = store.get("financeSummary") || {};
  const values = {
    circulating: summary.circulating_coin,
    credited: summary.credited_coin,
    spent: summary.spent_coin,
    refunded: summary.refunded_coin,
  };
  Object.entries(values).forEach(([key, value]) => {
    const element = document.getElementById(`panel-finance-${key}`);
    if (element)
      element.textContent = Number(value || 0).toLocaleString("tr-TR");
  });
  renderPager(
    "panel-finance-pager",
    store.get("financeMeta"),
    "previousFinancePage",
    "nextFinancePage",
  );
}

function renderQueueTable() {
  const canManage = hasPermission("admin.jobs.run");
  const items = (store.get("queueJobsList") || []).map((job) => ({
    ...job,
    can_retry: canManage && ["failed", "cancelled"].includes(job.status),
    can_cancel: canManage && job.status === "pending",
    retry_class:
      canManage && ["failed", "cancelled"].includes(job.status) ? "" : "d-none",
    cancel_class: canManage && job.status === "pending" ? "" : "d-none",
  }));
  setTableRows("panel-queue-jobs", "panel-rows-queue", items, 7);
  renderPager(
    "panel-queue-pager",
    store.get("queueMeta"),
    "previousQueuePage",
    "nextQueuePage",
  );
  const health = store.get("systemHealth") || {};
  const db = document.getElementById("panel-health-database");
  if (db) {
    db.textContent = health.database?.ok
      ? `Çalışıyor · ${health.database.version || ""}`
      : "Hata";
    db.className = `fw-bold ${health.database?.ok ? "text-success" : "text-danger"}`;
  }
  const storage = document.getElementById("panel-health-storage");
  if (storage) {
    storage.textContent = health.storage?.ok
      ? `${(Number(health.storage.free_bytes || 0) / 1073741824).toFixed(1)} GB boş`
      : "Yazma hatası";
    storage.className = `fw-bold ${health.storage?.ok ? "text-success" : "text-danger"}`;
  }
  const queue = document.getElementById("panel-health-queue");
  if (queue)
    queue.textContent = `${Number(health.queue?.pending || 0)} bekleyen · ${Number(health.queue?.failed || 0)} hata`;
  const backup = document.getElementById("panel-health-backup");
  if (backup)
    backup.textContent = health.backup
      ? `${health.backup.file} · ${health.backup.created_at}`
      : "Yedek bulunamadı";
}

function renderLogsTable() {
  const items = (store.get("logsList") || []).map((log) => ({
    ...log,
    status_class:
      Number(log.status_code) >= 500
        ? "bg-danger-subtle text-danger"
        : Number(log.status_code) >= 400
          ? "bg-warning-subtle text-warning"
          : "bg-success-subtle text-success",
    actor_label: log.username || log.user_id || "-",
    duration_label: `${Number(log.duration_ms || 0)}ms`,
  }));
  setTableRows("panel-audit-logs", "panel-rows-logs", items, 7);
  renderPager(
    "panel-logs-pager",
    store.get("logsMeta"),
    "previousLogsPage",
    "nextLogsPage",
  );
}

function renderUploadsTable() {
  const canDelete = hasPermission("admin.uploads.delete");
  const canOptimize = hasPermission("admin.uploads.optimize");
  const items = (store.get("uploadsList") || []).map((item) => {
    const references = Array.isArray(item.references) ? item.references : [];
    const refs = references.map((reference) => ({
      ...reference,
      label: `${reference.entity_type || "kayıt"} · ${reference.label || reference.entity_id || "-"}`,
      relation_label: reference.relation ? `(${reference.relation})` : "",
      has_url: Boolean(reference.url && reference.url !== "#"),
      url_safe:
        reference.url &&
        reference.url.startsWith("/") &&
        !reference.url.startsWith("//")
          ? reference.url
          : "#",
    }));
    return {
      ...item,
      id_number: Number(item.id),
      file_url:
        item.file_path &&
        item.file_path.startsWith("/") &&
        !item.file_path.startsWith("//")
          ? item.file_path
          : "#",
      references: refs.map((reference) => ({
        ...reference,
        url_class: reference.has_url ? "" : "d-none",
      })),
      references_class: refs.length > 0 ? "" : "d-none",
      select_class: canDelete ? "" : "d-none",
      optimize_class: canOptimize ? "" : "d-none",
      delete_class: canDelete ? "" : "d-none",
      has_references: refs.length > 0,
      reference_count: refs.length,
      can_select: canDelete,
      can_optimize: canOptimize,
      can_delete: canDelete,
    };
  });
  setTableRows("panel-uploads-list", "panel-rows-uploads", items, 9);
  renderPager(
    "panel-uploads-pager",
    store.get("uploadsMeta"),
    "previousUploadsPage",
    "nextUploadsPage",
  );
  const stats = store.get("uploadsStats") || {};
  const count = document.getElementById("panel-upload-count");
  if (count)
    count.textContent = Number(stats.total_files || 0).toLocaleString("tr-TR");
  const size = document.getElementById("panel-upload-size");
  if (size)
    size.textContent = `${(Number(stats.total_bytes || 0) / 1048576).toFixed(1)} MB`;
  const types = document.getElementById("panel-upload-types");
  if (types)
    types.textContent = `JPEG ${Number(stats.jpeg_files || 0)} · PNG ${Number(stats.png_files || 0)} · WebP ${Number(stats.webp_files || 0)} · GIF ${Number(stats.gif_files || 0)}`;
}

const routeTableRenderers = {
  dashboard: renderDashboardTables,
  series: renderSeriesTable,
  user: renderUsersTable,
  blogs: renderBlogsTable,
  comments: renderCommentsTable,
  reports: renderReportsTable,
  monetization: renderPackagesTable,
  finance: renderFinanceTable,
  ops: renderQueueTable,
  logs: renderLogsTable,
  uploads: renderUploadsTable,
};

function renderRouteTables(route) {
  routeTableRenderers[route]?.();
}

let pageCleanup = null;
let pageParent = "/panel";

function disposePage() {
  const cleanup = pageCleanup;
  pageCleanup = null;
  if (cleanup)
    Promise.resolve(cleanup()).catch((error) =>
      showToast(error.message, "danger"),
    );
}

function returnToParent() {
  panelNavigate(pageParent);
}

function mountPage(name, context = {}) {
  if (!target) return null;
  mount(name, { target, context, store, handlers });
  // A route may contain sibling regions (header + content), so normalize the
  // mounted fragment into one disposable scope. `display: contents` keeps the
  // AdminLTE layout unchanged while allowing page-local listeners to be
  // removed with the route on the next navigation.
  const page = document.createElement("div");
  page.className = "panel-page-root";
  page.append(...Array.from(target.childNodes));
  target.append(page);
  applyPermissionVisibility(page);
  return page;
}

function mountEditorPage(title, body, onSubmit) {
  const page = mountPage(body.name + "-page", {
    header: { title, parent_path: pageParent },
    fields: body.context || {},
  });
  const form = page.querySelector("[data-editor-form]");
  if (!form || typeof onSubmit !== "function") return page;
  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = event.submitter;
    if (submit) submit.disabled = true;
    try {
      await onSubmit(new FormData(form), form);
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    } finally {
      if (submit) submit.disabled = false;
    }
  });
  return page;
}

async function loadTaxonomies() {
  const requestEpoch = pageEpoch;
  const [genreResponse, tagResponse] = await Promise.all([
    api("/genres"),
    api("/tags"),
  ]);
  assertCurrentPage(requestEpoch);
  return {
    genres: responseItems(genreResponse),
    tags: responseItems(tagResponse),
  };
}

async function uploadImages(files, type) {
  const requestEpoch = pageEpoch;
  if (!files?.length) return [];
  const body = new FormData();
  Array.from(files).forEach((file) => body.append("images[]", file));
  const response = await api(
    `/upload-images?type=${encodeURIComponent(type)}`,
    { method: "POST", detached: true, body },
  );
  const paths = response?.data?.paths || [];
  if (requestEpoch !== pageEpoch) {
    if (paths.length)
      await api("/uploads/cleanup", {
        method: "POST",
        detached: true,
        body: { paths },
      });
    assertCurrentPage(requestEpoch);
  }
  return paths;
}

function bindTaxonomyButtons(pageRoot) {
  pageRoot
    .querySelectorAll("[data-taxonomy-choices] label")
    .forEach((label) => {
      label.addEventListener("click", () => {
        setTimeout(() => {
          const checked = label.querySelector("input").checked;
          label.classList.toggle("btn-primary", checked);
          label.classList.toggle("btn-outline-secondary", !checked);
        });
      });
    });
}

function selectedValues(formData, name) {
  return formData.getAll(name).map(String);
}

function chapterPayload(formData, form) {
  const payload = Object.fromEntries(formData.entries());
  delete payload.page_files;
  if (Object.prototype.hasOwnProperty.call(payload, "price_amount")) {
    payload.price_amount = Number(payload.price_amount || 0);
  }
  payload.is_members_only = form.elements.is_members_only?.checked ? 1 : 0;
  if (payload.type === "image") {
    payload.pages = String(payload.pages || "")
      .split("\n")
      .map((value) => value.trim())
      .filter(Boolean);
    delete payload.body;
  } else {
    delete payload.pages;
  }
  return payload;
}

async function renderChapterPage(content, chapterId = null) {
  const requestEpoch = pageEpoch;
  const chapter = chapterId
    ? (await api(`/chapters/${chapterId}`))?.data || {}
    : {};
  assertCurrentPage(requestEpoch);
  const uploadedPaths = [];
  const originalPrice = chapterId
    ? Number(chapter.pricing?.base_price ?? chapter.price_amount ?? 0)
    : null;
  const dateValue = (value) =>
    value ? String(value).replace(" ", "T").slice(0, 16) : "";
  const page = mountEditorPage(
    chapterId
      ? `Bölümü Düzenle: ${chapter.chapter_number}`
      : `${content.title} — Yeni Bölüm`,
    {
      name: "panel-chapter-form",
      context: {
        chapter_number: chapter.chapter_number || "",
        title: chapter.title || "",
        price_amount: chapter.pricing?.base_price ?? chapter.price_amount ?? 0,
        published_at: dateValue(
          chapter.pricing?.published_at ?? chapter.published_at,
        ),
        is_free_after: dateValue(
          chapter.pricing?.is_free_after ?? chapter.is_free_after,
        ),
        translator_note: chapter.translator_note || "",
        body: chapter.body || "",
        pages: Array.isArray(chapter.pages) ? chapter.pages.join("\n") : "",
      },
    },
    async (formData, form) => {
      const payload = chapterPayload(formData, form);
      const requestedPrice =
        chapterId &&
        !Object.prototype.hasOwnProperty.call(payload, "price_amount")
          ? originalPrice
          : Number(payload.price_amount || 0);
      if (chapterId) {
        if (
          requestedPrice !== originalPrice &&
          !hasPermission("admin.shop.manage")
        ) {
          throw new Error(
            "Bölüm fiyatını değiştirmek için admin.shop.manage izni gerekir.",
          );
        }
        // Price changes use the dedicated pricing endpoint so its audit trail
        // and price_last_update semantics remain consistent with other shop
        // operations. The content update keeps the existing price otherwise.
        delete payload.price_amount;
      }
      await api(
        chapterId
          ? `/chapters/${chapterId}`
          : `/content/${content.id}/chapters`,
        {
          method: chapterId ? "PUT" : "POST",
          body: payload,
        },
      );
      if (chapterId && requestedPrice !== originalPrice) {
        await api(`/chapters/${chapterId}/pricing`, {
          method: "PUT",
          body: { price_coin: requestedPrice, is_active: requestedPrice > 0 },
        });
      }
      showToast(chapterId ? "Bölüm güncellendi" : "Bölüm oluşturuldu");
      panelNavigate(
        "/panel/series/" + encodeURIComponent(content.id) + "/chapters",
      );
    },
  );
  const typeInput = page.querySelector("#panel-chapter-type");
  if (typeInput) typeInput.value = chapter.type === "image" ? "image" : "text";
  const membersInput = page.querySelector('[name="is_members_only"]');
  if (membersInput)
    membersInput.checked = Number(chapter.is_members_only) === 1;
  const priceInput = page.querySelector('[name="price_amount"]');
  if (chapterId && priceInput && !hasPermission("admin.shop.manage")) {
    priceInput.disabled = true;
    priceInput.title = "Fiyat değiştirmek için admin.shop.manage izni gerekir.";
  }
  const pagesInput = page.querySelector('[name="pages"]');
  const pagePreview = page.querySelector("[data-page-preview]");
  const pagePaths = () =>
    pagesInput.value
      .split(/\r?\n/)
      .map((value) => value.trim())
      .filter(Boolean);
  const renderPagePreview = () => {
    const paths = pagePaths();
    const items = paths.map((path, index) => ({
      path,
      url: safeLocalPath(path),
      index,
      number: index + 1,
      first_disabled: index === 0 ? "disabled" : "",
      last_disabled: index === paths.length - 1 ? "disabled" : "",
    }));
    mountPartial("panel-chapter-pages-preview", pagePreview, {
      items,
      has_items: items.length > 0,
    });
    pagePreview
      .querySelectorAll('[data-page-disabled="disabled"]')
      .forEach((button) => {
        button.disabled = true;
      });
    pagePreview.querySelectorAll("[data-page-image]").forEach((image) => {
      image.addEventListener(
        "error",
        () => {
          const fallback = document.createElement("div");
          fallback.className =
            "d-flex align-items-center justify-content-center text-secondary small p-2";
          fallback.textContent = "Önizleme yok";
          image.replaceWith(fallback);
        },
        { once: true },
      );
    });
  };
  pagePreview.addEventListener("click", (event) => {
    const button = event.target.closest("[data-page-move], [data-page-remove]");
    if (!button) return;
    const card = button.closest("[data-page-card]");
    const index = Number(card?.dataset.pageIndex);
    const paths = pagePaths();
    if (!Number.isInteger(index) || !paths[index]) return;
    if (button.hasAttribute("data-page-remove")) {
      paths.splice(index, 1);
    } else {
      const direction = button.dataset.pageMove === "up" ? -1 : 1;
      const targetIndex = index + direction;
      if (targetIndex < 0 || targetIndex >= paths.length) return;
      [paths[index], paths[targetIndex]] = [paths[targetIndex], paths[index]];
    }
    pagesInput.value = paths.join("\n");
    renderPagePreview();
  });
  pagesInput.addEventListener("input", renderPagePreview);
  let previousType = typeInput.value;
  const syncChapterFields = () => {
    const image = typeInput.value === "image";
    page.querySelector("[data-chapter-body]").hidden = image;
    page.querySelector("[data-chapter-pages]").hidden = !image;
  };
  typeInput.addEventListener("change", () => {
    const nextType = typeInput.value;
    if (nextType !== previousType) {
      const warning =
        nextType === "image"
          ? "Metin içeriği görsel bölüme çevrilecek. Kaydederseniz metin içeriği kaldırılır. Devam edilsin mi?"
          : "Görsel sayfaları metin bölüme çevrilecek. Kaydederseniz görsel sayfaları kaldırılır. Devam edilsin mi?";
      if (!confirm(warning)) {
        typeInput.value = previousType;
        return;
      }
      previousType = nextType;
    }
    syncChapterFields();
  });
  page
    .querySelector('[name="page_files"]')
    .addEventListener("change", async (event) => {
      const files = Array.from(event.target.files || []);
      const zipFiles = files.filter((file) => /\.zip$/i.test(file.name));
      if (zipFiles.length > 0 && files.length > 1) {
        showToast(
          "ZIP ile diğer görselleri aynı anda seçmeyin; önce ZIP veya görsellerden birini yükleyin.",
          "danger",
        );
        event.target.value = "";
        return;
      }
      try {
        const paths = await uploadImages(files, "chapters");
        uploadedPaths.push(...paths);
        const textarea = page.querySelector('[name="pages"]');
        const existing = textarea.value
          .split("\n")
          .map((value) => value.trim())
          .filter(Boolean);
        textarea.value = [...existing, ...paths].join("\n");
        renderPagePreview();
        showToast(`${paths.length} görsel yüklendi`);
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
      event.target.value = "";
    });
  pageCleanup = async () => {
    if (uploadedPaths.length > 0) {
      await api("/uploads/cleanup", {
        method: "POST",
        detached: true,
        body: { paths: uploadedPaths },
      });
    }
  };
  syncChapterFields();
  renderPagePreview();
}

async function loadSeriesPreviewPage(contentId) {
  const requestEpoch = pageEpoch;
  const response = await api(`/content/${contentId}/preview`);
  assertCurrentPage(requestEpoch);
  const content = response?.data || {};
  const urlPath = safeLocalPath(content.url_path);
  const page = mountEditorPage(`Önizleme: ${content.title || contentId}`, {
    name: "panel-series-preview",
    context: {
      has_cover: Boolean(content.cover_image),
      cover_image: safeLocalPath(content.cover_image),
      type: content.type || "-",
      status: content.status || "-",
      lifecycle_status: content.lifecycle_status || "-",
      title: content.title || "-",
      alternative_titles: content.alternative_titles || "-",
      description: content.description || "Açıklama yok",
      author: content.author || "-",
      artist: content.artist || "-",
      scheduled_at: content.scheduled_at || "-",
      is_published: content.lifecycle_status === "published" && urlPath !== "#",
      url_path: urlPath,
    },
  });
}

async function loadSeriesRevisionsPage(contentId) {
  const requestEpoch = pageEpoch;
  const response = await api(`/content/${contentId}/revisions?limit=50`);
  assertCurrentPage(requestEpoch);
  const items = responseItems(response).map((revision) => ({
    created_at: revision.created_at || "-",
    moderator: revision.moderator_username || revision.moderator_user_id || "-",
    action: revision.action || "-",
    title: revision.snapshot?.title || "-",
    status: revision.snapshot?.status || "-",
    lifecycle_status: revision.snapshot?.lifecycle_status || "published",
  }));
  const page = mountEditorPage("İçerik Revizyon Geçmişi", {
    name: "panel-series-revisions",
    context: { items, has_items: items.length > 0 },
  });
  mountPartial(
    "panel-rows-series-revisions",
    page.querySelector("#panel-series-revisions-rows"),
    { items, has_items: items.length > 0 },
  );
}

async function loadTaxonomyPage() {
  const requestEpoch = pageEpoch;
  const { genres, tags } = await loadTaxonomies();
  assertCurrentPage(requestEpoch);
  const normalize = (items) =>
    items.map((item) => ({
      id: item.id,
      name: item.name || "-",
      slug: item.slug || "",
      usage_count: Number(item.usage_count || 0),
      sort_order: Number(item.sort_order || 0),
    }));
  const page = mountEditorPage(
    "Tür ve Etiket Yönetimi",
    { name: "panel-taxonomy", context: {} },
    async () => {
      const items = Array.from(
        page.querySelectorAll("[data-taxonomy-row]"),
      ).map((row) => ({
        id: Number(row.dataset.taxonomyRow),
        sort_order: Number(
          row.querySelector("[data-taxonomy-order]").value || 0,
        ),
      }));
      await api("/taxonomies/order", { method: "PUT", body: { items } });
      showToast("Taksonomi sırası kaydedildi");
      await loadTaxonomyPage();
    },
  );
  mountPartial(
    "panel-rows-taxonomy",
    page.querySelector("#panel-taxonomy-genres"),
    { items: normalize(genres), has_items: genres.length > 0 },
  );
  mountPartial(
    "panel-rows-taxonomy",
    page.querySelector("#panel-taxonomy-tags"),
    { items: normalize(tags), has_items: tags.length > 0 },
  );
  const submit = page.querySelector('button[type="submit"]');
  if (submit) submit.textContent = "Sıralamayı Kaydet";
  page.addEventListener("click", async (event) => {
    try {
      const createButton = event.target.closest("[data-create-taxonomy]");
      if (createButton) {
        const kind = createButton.dataset.createTaxonomy;
        const name = prompt(
          kind === "genre" ? "Yeni tür adı:" : "Yeni etiket adı:",
        );
        if (!name?.trim()) return;
        await api(kind === "genre" ? "/series_genres" : "/series_tags", {
          method: "POST",
          body: { name: name.trim() },
        });
        showToast(kind === "genre" ? "Tür oluşturuldu" : "Etiket oluşturuldu");
        await loadTaxonomyPage();
        return;
      }
      const editButton = event.target.closest("[data-edit-taxonomy]");
      if (editButton) {
        const name = prompt("Yeni ad:", editButton.dataset.name || "");
        if (!name?.trim() || name.trim() === editButton.dataset.name) return;
        await api(`/taxonomies/${editButton.dataset.editTaxonomy}`, {
          method: "PUT",
          body: { name: name.trim() },
        });
        showToast("Taksonomi güncellendi");
        await loadTaxonomyPage();
        return;
      }
      const mergeButton = event.target.closest("[data-merge-taxonomy]");
      if (mergeButton) {
        const targetId = Number(
          prompt("Bu kaydın birleştirileceği hedef taksonomi ID:"),
        );
        if (!targetId) return;
        await api("/taxonomies/merge", {
          method: "POST",
          body: {
            source_id: Number(mergeButton.dataset.mergeTaxonomy),
            target_id: targetId,
          },
        });
        showToast("Taksonomiler birleştirildi");
        await loadTaxonomyPage();
        return;
      }
      const deleteButton = event.target.closest("[data-delete-taxonomy]");
      if (deleteButton) {
        if (Number(deleteButton.dataset.usage || 0) > 0)
          throw new Error(
            "Kullanılan bir kayıt silinemez; önce başka bir kayda birleştirin.",
          );
        if (!confirm(`“${deleteButton.dataset.name}” silinsin mi?`)) return;
        await api(`/taxonomies/${deleteButton.dataset.deleteTaxonomy}`, {
          method: "DELETE",
        });
        showToast("Taksonomi silindi");
        await loadTaxonomyPage();
      }
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  });
}

async function renderTeamPage(content) {
  const requestEpoch = pageEpoch;
  const response = await api(`/series/${content.id}/team`);
  assertCurrentPage(requestEpoch);
  const members = responseItems(response).map((member) => ({
    id: member.id,
    username: member.username || "-",
    user_id: member.user_id || "-",
    role: member.role || "-",
    created_at: member.created_at || "-",
  }));
  const page = mountEditorPage(
    `${content.title} — Ekip Yönetimi`,
    { name: "panel-team", context: {} },
    async (formData) => {
      await api(`/series/${content.id}/team`, {
        method: "POST",
        body: Object.fromEntries(formData.entries()),
      });
      showToast("Ekip üyesi atandı");
      await renderTeamPage(content);
    },
  );
  mountPartial("panel-rows-team", page.querySelector("#panel-team-rows"), {
    items: members,
    has_items: members.length > 0,
  });
  page.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-remove-team]");
    if (!button || !confirm("Ekip üyesini çıkarmak istediğinize emin misiniz?"))
      return;
    try {
      await api(`/series/team/${button.dataset.removeTeam}`, {
        method: "DELETE",
      });
      showToast("Ekip üyesi çıkarıldı");
      await renderTeamPage(content);
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  });
}

async function loadRbacPage() {
  const requestEpoch = pageEpoch;
  const response = await api("/rbac/matrix");
  assertCurrentPage(requestEpoch);
  const roles = response?.data?.roles || [];
  const permissionGroups = response?.data?.permissions || {};
  const rows = [];
  Object.entries(permissionGroups).forEach(([group, permissions]) => {
    rows.push({
      group,
      group_class: "",
      permission_class: "d-none",
      role_colspan: roles.length + 1,
      cells: [],
    });
    Object.entries(permissions).forEach(([code, label]) => {
      const cells = roles.map((role) => {
        const rolePermissions = String(role.permissions || "").split(",");
        const granted =
          role.slug === "superadmin" ||
          rolePermissions.includes("*") ||
          rolePermissions.includes(code);
        const canChange = granted
          ? hasPermission("admin.permissions.revoke")
          : hasPermission("admin.permissions.grant");
        const locked = role.slug === "admin" && code === "admin.panel.access";
        const interactive = canChange && !locked;
        return {
          role: role.slug,
          permission: code,
          granted_value: granted ? "1" : "0",
          interactive,
          button_class: interactive ? "" : "d-none",
          icon_class_hidden: interactive ? "d-none" : "",
          title: granted ? "İzni kaldır" : "İzni ver",
          icon_class: granted
            ? "bi-check-circle-fill text-success"
            : "bi-x-circle text-secondary",
        };
      });
      rows.push({
        code,
        label,
        group_class: "d-none",
        permission_class: "",
        cells,
      });
    });
  });
  const page = mountEditorPage("Yetki ve Rol Matrisi", {
    name: "panel-rbac",
    context: { roles: roles.map((role) => ({ name: role.name || role.slug })) },
  });
  mountHeaderCells(
    page.querySelector("#panel-rbac-head"),
    ["İzin", ...roles.map((role) => role.name || role.slug)],
    "text-center",
  );
  mountPartial("panel-rows-rbac", page.querySelector("#panel-rbac-rows"), {
    rows,
    has_items: rows.length > 0,
    role_colspan: roles.length + 1,
  });
  page.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-toggle-permission]");
    if (!button) return;
    const granted = button.dataset.granted === "1";
    if (
      !confirm(
        `${button.dataset.permission} izni ${button.dataset.role} rolü için ${granted ? "kaldırılsın" : "verilsin"} mi?`,
      )
    )
      return;
    try {
      await api(granted ? "/rbac/permissions" : "/rbac/permissions/assign", {
        method: granted ? "DELETE" : "POST",
        body: {
          role: button.dataset.role,
          permission: button.dataset.permission,
        },
      });
      showToast(granted ? "İzin kaldırıldı" : "İzin verildi");
      await loadRbacPage();
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  });
}

async function loadOwnershipPage() {
  const requestEpoch = pageEpoch;
  const [matrixResponse, ownershipResponse] = await Promise.all([
    api("/rbac/matrix"),
    api("/rbac/ownership"),
  ]);
  assertCurrentPage(requestEpoch);
  const roles = matrixResponse?.data?.roles || [];
  const capabilities = ownershipResponse?.data?.capabilities || [];
  const records = ownershipResponse?.data?.records || [];
  const rolePermissions = (role) =>
    String(role.permissions || "")
      .split(",")
      .map((value) => value.trim())
      .filter(Boolean);
  const canRole = (role, capability) => {
    if (capability.scope === "owner") return "Sahibi";
    if (capability.scope === "authenticated") return "Giriş yapmış kullanıcı";
    const permissions = rolePermissions(role);
    if (role.slug === "superadmin" || permissions.includes("*")) return "Evet";
    if (capability.scope === "role_any")
      return String(capability.permission || "")
        .split(" veya ")
        .some((permission) => permissions.includes(permission))
        ? "Evet"
        : "Hayır";
    return permissions.includes(capability.permission) ? "Evet" : "Hayır";
  };
  const capabilityItems = capabilities.map((capability) => ({
    entity_label: capability.entity_label || capability.entity_type || "-",
    action: capability.action || "-",
    permission: capability.permission || "kayıt sahibi",
    cells: roles.map((role) => {
      const value = canRole(role, capability);
      return {
        value,
        class_name:
          value === "Evet"
            ? "text-success"
            : value === "Sahibi"
              ? "text-info"
              : "text-secondary",
      };
    }),
  }));
  const recordItems = records.map((record) => ({
    entity_type: record.entity_type || "-",
    label: record.label || record.entity_id || "-",
    entity_id: record.entity_id || "",
    owner: record.owner_username || record.owner_id || "-",
    created_at: record.created_at || "-",
  }));
  const page = mountEditorPage("İçerik Sahipliği ve İşlem Yetkileri", {
    name: "panel-ownership",
    context: { roles: roles.map((role) => ({ name: role.name || role.slug })) },
  });
  mountHeaderCells(
    page.querySelector("#panel-ownership-head"),
    [
      "Varlık",
      "İşlem",
      "Gerekli izin / kapsam",
      ...roles.map((role) => role.name || role.slug),
    ],
    "text-center",
  );
  mountPartial(
    "panel-rows-ownership-capabilities",
    page.querySelector("#panel-ownership-capability-rows"),
    {
      items: capabilityItems,
      has_items: capabilityItems.length > 0,
      colspan: roles.length + 3,
    },
  );
  mountPartial(
    "panel-rows-ownership-records",
    page.querySelector("#panel-ownership-record-rows"),
    { items: recordItems, has_items: recordItems.length > 0 },
  );
}

function renderPackagePage(packageItem = null) {
  const item = packageItem || {};
  const page = mountEditorPage(
    packageItem ? "Paketi Düzenle" : "Yeni Coin Paketi",
    {
      name: "panel-package-form",
      context: {
        name: item.name || "",
        coin_amount: item.coin_amount || "",
        bonus_coin: item.bonus_coin || 0,
        display_price: item.display_price || "0.00",
        currency: item.currency || "TRY",
        sort_order: item.sort_order || 0,
      },
    },
    async (formData) => {
      const payload = Object.fromEntries(formData.entries());
      payload.coin_amount = Number(payload.coin_amount);
      payload.bonus_coin = Number(payload.bonus_coin || 0);
      payload.sort_order = Number(payload.sort_order || 0);
      payload.is_active = payload.is_active === "1";
      await api(
        packageItem ? `/shop/packages/${packageItem.id}` : "/shop/packages",
        { method: packageItem ? "PUT" : "POST", body: payload },
      );
      returnToParent();
      showToast(packageItem ? "Paket güncellendi" : "Paket oluşturuldu");
    },
  );
  const active = page.querySelector('[name="is_active"]');
  if (active) active.value = Number(item.is_active ?? 1) === 1 ? "1" : "0";
}

async function loadUserWalletPage(userId, pageNumber = 1) {
  const requestEpoch = pageEpoch;
  if (!userId) throw new Error("Kullanıcı kimliği bulunamadı");
  const canManageWallet = hasPermission("admin.wallet.manage");
  const canLoadPackages = canManageWallet && hasPermission("admin.shop.manage");
  const [walletResponse, transactionResponse, packageResponse, userResponse] =
    await Promise.all([
      api(`/wallets/${encodeURIComponent(userId)}`),
      api(
        `/wallets/${encodeURIComponent(userId)}/transactions?page=${Math.max(1, Number(pageNumber))}&per_page=25`,
      ),
      canLoadPackages
        ? api("/shop/packages?per_page=100")
        : Promise.resolve(null),
      hasPermission("admin.users.manage")
        ? api(`/users/${encodeURIComponent(userId)}/overview`).catch(() => null)
        : Promise.resolve(null),
    ]);
  assertCurrentPage(requestEpoch);
  const wallet = walletResponse?.data || {};
  const user = userResponse?.data?.user || {};
  const transactions = responseItems(transactionResponse).map((item) => ({
    ...item,
    coin_delta: Number(item.coin_delta || 0),
    delta_prefix: Number(item.coin_delta) >= 0 ? "+" : "",
    delta_class: Number(item.coin_delta) >= 0 ? "text-success" : "text-danger",
    balance_after: Number(item.balance_after || 0).toLocaleString("tr-TR"),
    reference_label:
      [item.reference_type, item.reference_id].filter(Boolean).join(":") || "-",
  }));
  const packages = responseItems(packageResponse).map((item) => ({
    ...item,
    total_coin: Number(item.total_coin || item.coin_amount || 0),
  }));
  store.batch(() => {
    store.set("userWalletId", userId);
    store.set("userWallet", wallet);
    store.set("userWalletMeta", responseMeta(transactionResponse));
  });
  const page = mountPage("panel-user-wallet-content", {
    user_id: encodeURIComponent(userId),
    username: user.username || userId,
    balance_coin: Number(wallet.balance_coin || 0).toLocaleString("tr-TR"),
    total_coin_purchased: Number(
      wallet.total_coin_purchased || 0,
    ).toLocaleString("tr-TR"),
    total_coin_spent: Number(wallet.total_coin_spent || 0).toLocaleString(
      "tr-TR",
    ),
    can_manage_wallet: canManageWallet,
    can_load_packages: canLoadPackages,
    packages,
    transactions,
    has_transactions: transactions.length > 0,
  });
  mountPartial(
    "panel-rows-wallet-transactions",
    page.querySelector("#panel-user-wallet-transactions"),
    { items: transactions, has_items: transactions.length > 0 },
  );
  const walletForm = page.querySelector("#panel-user-wallet-form");
  walletForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = walletForm.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;
    try {
      const data = new FormData(walletForm);
      const action =
        String(data.get("wallet_action")) === "debit" ? "debit" : "credit";
      await api(`/wallets/${encodeURIComponent(userId)}/${action}`, {
        method: "POST",
        body: {
          amount: Number(data.get("amount")),
          reason: String(data.get("reason") || ""),
        },
      });
      showToast("Cüzdan güncellendi");
      await loadUserWalletPage(
        userId,
        Number(store.get("userWalletMeta")?.page || 1),
      );
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
      if (submit) submit.disabled = false;
    }
  });
  const grantPackageForm = page.querySelector(
    "#panel-user-wallet-package-form",
  );
  grantPackageForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = grantPackageForm.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;
    try {
      const data = new FormData(grantPackageForm);
      await api(`/wallets/${encodeURIComponent(userId)}/grant-package`, {
        method: "POST",
        body: {
          package_id: Number(data.get("package_id")),
          cash_amount: String(data.get("cash_amount") || ""),
          reason: String(data.get("grant_reason") || ""),
        },
      });
      showToast("Paket kullanıcıya tanımlandı");
      await loadUserWalletPage(
        userId,
        Number(store.get("userWalletMeta")?.page || 1),
      );
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
      if (submit) submit.disabled = false;
    }
  });
  renderPager(
    "panel-user-wallet-pager",
    store.get("userWalletMeta"),
    "previousUserWalletPage",
    "nextUserWalletPage",
  );
}

async function loadAdFreePage() {
  const requestEpoch = pageEpoch;
  const response = await api("/features");
  assertCurrentPage(requestEpoch);
  const item =
    responseItems(response).find(
      (feature) => feature.feature_key === "ad_free",
    ) || {};
  const page = mountEditorPage(
    "Reklamsız Ürün Ayarı",
    {
      name: "panel-ad-free-form",
      context: {
        name: item.name || "",
        coin_price: Number(item.coin_price || 0),
        duration_days: Number(item.duration_days || 30),
      },
    },
    async (formData) => {
      const payload = Object.fromEntries(formData.entries());
      payload.coin_price = Number(payload.coin_price || 0);
      payload.duration_days = Number(payload.duration_days || 30);
      payload.is_active = payload.is_active === "1";
      await api("/features/ad-free", { method: "PUT", body: payload });
      returnToParent();
      showToast("Reklamsız ürün ayarı kaydedildi");
    },
  );
  const active = page.querySelector('[name="is_active"]');
  if (active) active.value = Number(item.is_active ?? 1) === 1 ? "1" : "0";
}

function loadPricingPage() {
  mountEditorPage(
    "Seri / Bölüm Fiyatlandırması",
    { name: "panel-pricing-form", context: {} },
    async (formData) => {
      const targetType = String(formData.get("target_type"));
      const targetId = String(formData.get("target_id"));
      await api(`/${targetType}/${targetId}/pricing`, {
        method: "PUT",
        body: {
          price_coin: Number(formData.get("price_coin") || 0),
          is_active: formData.get("is_active") === "1",
        },
      });
      returnToParent();
      showToast("Fiyatlandırma kaydedildi");
    },
  );
}

async function loadLogPage(path) {
  const requestEpoch = pageEpoch;
  const response = await api(`/${path}?per_page=100`);
  assertCurrentPage(requestEpoch);
  const items = responseItems(response);
  const columns = items.length ? Object.keys(items[0]).slice(0, 8) : [];
  const rows = items.map((item) => ({
    cells: columns.map((column) => ({
      value:
        typeof item[column] === "object"
          ? JSON.stringify(item[column])
          : item[column],
    })),
  }));
  const page = mountEditorPage("Log Görüntüleyici", {
    name: "panel-log",
    context: { columns, rows, has_rows: rows.length > 0 },
  });
  mountHeaderCells(page.querySelector("#panel-log-head"), columns);
  mountPartial("panel-rows-log", page.querySelector("#panel-log-body"), {
    rows,
    has_rows: rows.length > 0,
  });
}

function panelNavigate(path) {
  if (!path) return;
  history.pushState({}, "", path);
  navigate();
}

async function loadEnvPage() {
  const requestEpoch = pageEpoch;
  const target = document.getElementById("panel-config-env-page");
  if (!target) return;
  try {
    const response = await api("/maintenance/env");
    assertCurrentPage(requestEpoch);
    const values = response?.data || {};
    const groups = [
      {
        title: "Uygulama ve Adres",
        icon: "bi-gear",
        keys: [
          "APP_NAME",
          "APP_ENV",
          "APP_DEBUG",
          "APP_URL",
          "SITE_ADDRESS",
          "APP_TIMEZONE",
        ],
      },
      {
        title: "Oturum ve Güvenlik",
        icon: "bi-shield-lock",
        keys: [
          "SESSION_LIFETIME",
          "SESSION_COOKIE_LIFETIME",
          "REFRESH_TOKEN_DAYS",
          "SESSION_COOKIE_SECURE",
          "SESSION_COOKIE_SAME_SITE",
          "REMEMBER_COOKIE_SECURE",
          "REMEMBER_COOKIE_SAME_SITE",
          "ENFORCE_HTTPS",
        ],
      },
      {
        title: "Cache, CORS ve Proxy",
        icon: "bi-hdd-network",
        keys: ["CACHE_TTL", "CORS_ALLOWED_ORIGINS", "TRUSTED_PROXIES"],
      },
      {
        title: "Entegrasyonlar",
        icon: "bi-plug",
        keys: [
          "RESEND_API_KEY",
          "MAIL_FROM_NAME",
          "MAIL_FROM_ADDRESS",
          "GOOGLE_ANALYTICS_ID",
          "GOOGLE_RECAPTCHA_SITE_KEY",
          "GOOGLE_RECAPTCHA_SECRET_KEY",
          "CLOUDFLARE_TURNSTILE_SITE_KEY",
          "CLOUDFLARE_TURNSTILE_SECRET_KEY",
        ],
      },
    ];
    const booleanKeys = new Set([
      "APP_DEBUG",
      "SESSION_COOKIE_SECURE",
      "REMEMBER_COOKIE_SECURE",
      "ENFORCE_HTTPS",
    ]);
    const numericKeys = new Set([
      "SESSION_LIFETIME",
      "SESSION_COOKIE_LIFETIME",
      "REFRESH_TOKEN_DAYS",
      "CACHE_TTL",
    ]);
    const sensitive = (key) => /(?:PASSWORD|SECRET|TOKEN|KEY)$/.test(key);
    const labels = {
      APP_NAME: "Uygulama adı",
      APP_ENV: "Çalışma ortamı",
      APP_DEBUG: "Debug modu",
      APP_URL: "Uygulama URL",
      SITE_ADDRESS: "Site adresi",
      APP_TIMEZONE: "Saat dilimi",
      SESSION_LIFETIME: "Oturum süresi (sn)",
      SESSION_COOKIE_LIFETIME: "Oturum cookie süresi (sn)",
      REFRESH_TOKEN_DAYS: "Refresh token süresi (gün)",
      CACHE_TTL: "Cache süresi (sn)",
      SESSION_COOKIE_SECURE: "Oturum çerezi Secure",
      SESSION_COOKIE_SAME_SITE: "Oturum çerezi SameSite",
      ENFORCE_HTTPS: "HTTPS zorunlu",
      REMEMBER_COOKIE_SECURE: "Remember çerezi Secure",
      REMEMBER_COOKIE_SAME_SITE: "Remember çerezi SameSite",
      CORS_ALLOWED_ORIGINS: "CORS izinli adresler",
      TRUSTED_PROXIES: "Güvenilen proxy adresleri",
      RESEND_API_KEY: "Resend API anahtarı",
      MAIL_FROM_NAME: "Mail gönderici adı",
      MAIL_FROM_ADDRESS: "Mail gönderici adresi",
      GOOGLE_ANALYTICS_ID: "Google Analytics ID",
      GOOGLE_RECAPTCHA_SITE_KEY: "reCAPTCHA site anahtarı",
      GOOGLE_RECAPTCHA_SECRET_KEY: "reCAPTCHA gizli anahtarı",
      CLOUDFLARE_TURNSTILE_SITE_KEY: "Turnstile site anahtarı",
      CLOUDFLARE_TURNSTILE_SECRET_KEY: "Turnstile gizli anahtarı",
    };
    const groupsWithFields = groups.map((group) => ({
      title: group.title,
      icon: group.icon,
      fields: group.keys.map((key) => {
        const secret = sensitive(key);
        return {
          key,
          label: labels[key] || key,
          value: String(values[key] ?? ""),
          boolean: booleanKeys.has(key),
          boolean_class: booleanKeys.has(key) ? "" : "d-none",
          text_class: booleanKeys.has(key) ? "d-none" : "",
          input_type: secret
            ? "password"
            : numericKeys.has(key)
              ? "number"
              : key === "MAIL_FROM_ADDRESS"
                ? "email"
                : key === "APP_URL" || key === "SITE_ADDRESS"
                  ? "url"
                  : "text",
          monospace: secret ? "font-monospace" : "",
          placeholder: secret ? "Değiştirmek istemiyorsanız boş bırakın" : "",
        };
      }),
    }));
    mountPartial("panel-env-page-content", target, {
      groups: groupsWithFields,
    });
    target.querySelectorAll("[data-env-key]").forEach((input) => {
      const key = input.dataset.envKey;
      const isBoolean = booleanKeys.has(key);
      input.disabled = (input.dataset.envType === "boolean") !== isBoolean;
      if (input.dataset.envType === "boolean") {
        const raw = String(values[key] ?? "").toLowerCase();
        input.checked = ["true", "1", "yes", "on"].includes(raw);
      }
    });
    const form = target.querySelector("#panel-config-env-form");
    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = {};
      form.querySelectorAll("[data-env-key]").forEach((input) => {
        if (input.disabled) return;
        const key = input.dataset.envKey;
        const value =
          input.dataset.envType === "boolean"
            ? input.checked
              ? "true"
              : "false"
            : input.value;
        if (sensitive(key) && (value === "" || value === "********")) return;
        payload[key] = value;
      });
      try {
        await api("/maintenance/env", { method: "POST", body: payload });
        showToast(".env kaydedildi");
        panelNavigate("/panel/config-env");
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    });
  } catch (error) {
    if (error?.name === "AbortError") return;
    mountPartial("panel-page-error", target, {
      parent_path: "/panel/config",
      error_message: error.message,
    });
  }
}

async function loadWebhookPage() {
  const requestEpoch = pageEpoch;
  const target = document.getElementById("panel-webhook-page");
  if (!target) return;
  try {
    const response = await api("/webhooks");
    assertCurrentPage(requestEpoch);
    const items = responseItems(response).map((item) => ({
      ...item,
      status_label: Number(item.is_active) === 1 ? "Aktif" : "Pasif",
    }));
    mountPartial("panel-webhook-page-content", target, {
      items,
      has_items: items.length > 0,
    });
    mountPartial(
      "panel-rows-webhook",
      target.querySelector("#panel-webhook-rows"),
      { items, has_items: items.length > 0 },
    );
    target
      .querySelector("#panel-webhook-form")
      ?.addEventListener("submit", async (event) => {
        event.preventDefault();
        try {
          await api("/webhooks", {
            method: "POST",
            body: Object.fromEntries(
              new FormData(event.currentTarget).entries(),
            ),
          });
          showToast("Webhook oluşturuldu");
          await loadWebhookPage();
        } catch (error) {
          if (error?.name === "AbortError") return;
          showToast(error.message, "danger");
        }
      });
    target.onclick = async (event) => {
      const test = event.target.closest("[data-test-webhook]");
      const remove = event.target.closest("[data-delete-webhook]");
      try {
        if (test) {
          const result = await api(
            `/webhooks/${test.dataset.testWebhook}/test`,
            { method: "POST" },
          );
          showToast(
            result?.data?.success === false
              ? "Webhook testi başarısız"
              : "Webhook testi tamamlandı",
            result?.data?.success === false ? "danger" : "success",
          );
        }
        if (remove && confirm("Webhook silinsin mi?")) {
          await api(`/webhooks/${remove.dataset.deleteWebhook}`, {
            method: "DELETE",
          });
          showToast("Webhook silindi");
          await loadWebhookPage();
        }
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    };
  } catch (error) {
    if (error?.name === "AbortError") return;
    mountPartial("panel-page-error", target, {
      parent_path: "/panel",
      error_message: error.message,
    });
  }
}

async function loadReportDetailPage(id) {
  const requestEpoch = pageEpoch;
  const target = document.getElementById("panel-report-detail-page");
  if (!target) return;
  try {
    const response = await api(`/reports/${encodeURIComponent(id)}`);
    assertCurrentPage(requestEpoch);
    const report = response?.data || {};
    mountPartial(
      "panel-report-breadcrumb",
      document.getElementById("panel-report-breadcrumb"),
      { report_id: Number(report.id || id) },
    );
    mountPartial("panel-report-detail-content", target, {
      reporter_username: report.reporter_username || "-",
      target_type: report.target_type || "-",
      target_label: report.target_title || report.target_id || "-",
      reason: report.reason || "-",
      description: report.description || report.comment_body || "Açıklama yok",
      has_target_url: safeLocalPath(report.target_url) !== "#",
      target_url: safeLocalPath(report.target_url),
      admin_note: report.admin_note || "",
    });
    const form = target.querySelector("#panel-report-detail-form");
    const status = target.querySelector("#report-status");
    if (status) status.value = report.status || "pending";
    if (!hasPermission("admin.reports.manage")) {
      form.querySelectorAll("select, textarea").forEach((input) => {
        input.disabled = true;
      });
      form.querySelector('button[type="submit"]')?.remove();
    }
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        await api(`/reports/${encodeURIComponent(id)}`, {
          method: "PUT",
          body: Object.fromEntries(new FormData(form).entries()),
        });
        showToast("Rapor güncellendi");
        panelNavigate("/panel/reports");
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    });
  } catch (error) {
    if (error?.name === "AbortError") return;
    mountPartial("panel-page-error", target, {
      parent_path: pageParent,
      error_message: error.message,
    });
  }
}

async function loadSeriesEditorPage(mode, id = null) {
  const requestEpoch = pageEpoch;
  const target = document.getElementById("panel-series-editor-fields");
  const form = document.getElementById("panel-series-editor-form");
  if (!target || !form) return;
  try {
    let content = {};
    if (id) {
      let found = (store.get("seriesList") || []).find(
        (item) => String(item.id) === String(id),
      );
      if (!found)
        found = responseItems(
          await api(`/series?q=${encodeURIComponent(id)}&per_page=100`),
        ).find((item) => String(item.id) === String(id));
      if (!found) throw new Error("İçerik bulunamadı");
      content = found;
    }
    const { genres, tags } = await loadTaxonomies();
    assertCurrentPage(requestEpoch);
    const dateValue = (value) =>
      value ? String(value).replace(" ", "T").slice(0, 16) : "";
    document.getElementById("panel-series-editor-title").textContent =
      mode === "edit" ? "Seriyi düzenle" : "Yeni seri oluştur";
    mountPartial(
      "panel-series-breadcrumb",
      document.getElementById("panel-series-breadcrumb"),
      { label: mode === "edit" ? "Düzenle" : "Yeni" },
    );
    mountPartial("panel-content-form", target, {
      title: content.title || "",
      slug: content.slug || "",
      type: String(content.type || "novel").replace("_", "-"),
      status: content.status || "ongoing",
      lifecycle_status: content.lifecycle_status || "published",
      scheduled_at: dateValue(content.scheduled_at),
      alternative_titles: content.alternative_titles || "",
      description: content.description || "",
      cover_image: content.cover_image || "",
      author: content.author || "",
      artist: content.artist || "",
      country: content.country || "",
      release_year: content.release_year || "",
      next_year: nextYear,
      genres,
      tags,
    });
    const setValue = (name, value) => {
      const input = form.elements[name];
      if (input && value != null) input.value = value;
    };
    setValue("type", String(content.type || "novel").replace("_", "-"));
    setValue("status", content.status || "ongoing");
    setValue("lifecycle_status", content.lifecycle_status || "published");
    if (id) {
      form.elements.type.disabled = true;
      form.elements.slug.disabled = true;
    }
    form.elements.is_adult.checked = Number(content.is_adult) === 1;
    form.elements.is_members_only.checked =
      Number(content.is_members_only) === 1;
    form.elements.disable_comments.checked =
      Number(content.disable_comments) === 1;
    const selectedGenres = new Set(
      String(content.genre_ids || "")
        .split(",")
        .filter(Boolean),
    );
    const selectedTags = new Set(
      String(content.tag_ids || "")
        .split(",")
        .filter(Boolean),
    );
    form
      .querySelectorAll('input[name="genres"], input[name="tags"]')
      .forEach((input) => {
        const selected =
          input.name === "genres"
            ? selectedGenres.has(String(input.value))
            : selectedTags.has(String(input.value));
        input.checked = selected;
        input.closest("label")?.classList.toggle("btn-primary", selected);
        input
          .closest("label")
          ?.classList.toggle("btn-outline-secondary", !selected);
      });
    bindTaxonomyButtons(form);
    const uploadedPaths = [];
    pageCleanup = () =>
      uploadedPaths.length
        ? api("/uploads/cleanup", {
            method: "POST",
            detached: true,
            body: { paths: uploadedPaths },
          })
        : undefined;
    form
      .querySelector('[name="cover_file"]')
      ?.addEventListener("change", async (event) => {
        try {
          const paths = await uploadImages(event.target.files, "series_cover");
          uploadedPaths.push(...paths);
          if (paths[0])
            form.querySelector('[name="cover_image"]').value = paths[0];
          showToast("Kapak görseli yüklendi");
        } catch (error) {
          if (error?.name === "AbortError") return;
          showToast(error.message, "danger");
        }
      });
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        const data = new FormData(form);
        const selectedGenres = selectedValues(data, "genres");
        const selectedTags = selectedValues(data, "tags");
        const payload = Object.fromEntries(data.entries());
        delete payload.genres;
        delete payload.tags;
        delete payload.cover_file;
        payload.is_adult = form.elements.is_adult.checked ? 1 : 0;
        payload.is_members_only = form.elements.is_members_only.checked ? 1 : 0;
        payload.disable_comments = form.elements.disable_comments.checked
          ? 1
          : 0;
        const response =
          mode === "edit"
            ? await api(`/content/${encodeURIComponent(id)}`, {
                method: "PUT",
                body: payload,
              })
            : await api("/content", { method: "POST", body: payload });
        const contentId = id || response?.data?.id;
        if (contentId)
          await api(`/contents/${encodeURIComponent(contentId)}/taxonomy`, {
            method: "PUT",
            body: { genres: selectedGenres, tags: selectedTags },
          });
        showToast(
          mode === "edit" ? "İçerik güncellendi" : "İçerik oluşturuldu",
        );
        panelNavigate("/panel/series");
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    });
  } catch (error) {
    if (error?.name === "AbortError") return;
    mountPartial("panel-page-error", target, {
      parent_path: "/panel/series",
      error_message: error.message,
    });
  }
}

async function loadTeamPage(id) {
  const requestEpoch = pageEpoch;
  let content = (store.get("seriesList") || []).find(
    (item) => String(item.id) === String(id),
  );
  if (!content)
    content = responseItems(
      await api("/series?q=" + encodeURIComponent(id) + "&per_page=100"),
    ).find((item) => String(item.id) === String(id));
  if (!content) throw new Error("İçerik bulunamadı");
  await renderTeamPage(content);
  assertCurrentPage(requestEpoch);
}

async function loadChapterPage(seriesId, chapterId = null) {
  const requestEpoch = pageEpoch;
  let content = (store.get("seriesList") || []).find(
    (item) => String(item.id) === String(seriesId),
  );
  if (!content)
    content = responseItems(
      await api("/series?q=" + encodeURIComponent(seriesId) + "&per_page=100"),
    ).find((item) => String(item.id) === String(seriesId));
  if (!content) throw new Error("İçerik bulunamadı");
  await renderChapterPage(content, chapterId);
  assertCurrentPage(requestEpoch);
}

async function loadPackagePage(id) {
  let item = (store.get("packagesList") || []).find(
    (item) => String(item.id) === String(id),
  );
  if (!item)
    item = responseItems(await api("/shop/packages?per_page=100")).find(
      (item) => String(item.id) === String(id),
    );
  if (!item) throw new Error("Paket bulunamadı");
  renderPackagePage(item);
}

async function loadAuditPage(id) {
  let item = (store.get("logsList") || []).find(
    (item) => String(item.id) === String(id),
  );
  if (!item)
    item = responseItems(await api("/audit-logs?per_page=100")).find(
      (item) => String(item.id) === String(id),
    );
  if (!item) throw new Error("Denetim kaydı bulunamadı");
  mountEditorPage("Denetim Kaydı #" + item.id, {
    name: "panel-audit-json",
    context: { json: JSON.stringify(item, null, 2) },
  });
}

function loadModerationPage() {
  mountEditorPage(
    "Yeni Moderasyon Kaydı",
    { name: "panel-moderation-form", context: {} },
    async (formData) => {
      await api("/moderation-actions", {
        method: "POST",
        body: Object.fromEntries(formData.entries()),
      });
      returnToParent();
      showToast("Moderasyon kaydı oluşturuldu");
    },
  );
}

async function loadChaptersPage(contentId, pageNumber = 1) {
  const requestEpoch = pageEpoch;
  let content = (store.get("seriesList") || []).find(
    (item) => String(item.id) === String(contentId),
  );
  if (!content)
    content = responseItems(
      await api(`/series?q=${encodeURIComponent(contentId)}&per_page=100`),
    ).find((item) => String(item.id) === String(contentId));
  if (!content) throw new Error("İçerik bulunamadı");
  const response = await api(
    `/content/${content.id}/chapters?page=${Math.max(1, Number(pageNumber))}&per_page=25`,
  );
  assertCurrentPage(requestEpoch);
  const chapters = responseItems(response).map((chapter) => ({
    id: chapter.id,
    chapter_number: chapter.chapter_number || "-",
    title: chapter.title || "-",
    type: chapter.type || "-",
    price_amount: Number(chapter.price_amount || 0),
    published_at: chapter.published_at || "-",
  }));
  const chapterMeta = responseMeta(response);
  const page = mountEditorPage(
    `${content.title} — Bölümler (Sayfa ${chapterMeta.page}/${chapterMeta.total_pages})`,
    {
      name: "panel-chapters",
      context: {
        total: Number(chapterMeta.total || chapters.length),
        previous_disabled: chapterMeta.page <= 1 ? "disabled" : "",
        next_disabled:
          chapterMeta.page >= chapterMeta.total_pages ? "disabled" : "",
      },
    },
  );
  page
    .querySelectorAll('[data-chapter-page-disabled="disabled"]')
    .forEach((button) => {
      button.disabled = true;
    });
  mountPartial(
    "panel-rows-chapters",
    page.querySelector("#panel-chapter-rows"),
    { items: chapters, has_items: chapters.length > 0 },
  );
  page.addEventListener("click", async (event) => {
    const pageButton = event.target.closest("[data-chapter-page]");
    if (pageButton && !pageButton.disabled) {
      const nextPage =
        chapterMeta.page + (pageButton.dataset.chapterPage === "next" ? 1 : -1);
      await loadChaptersPage(content.id, nextPage);
      return;
    }
    const createButton = event.target.closest("[data-create-chapter]");
    const teamButton = event.target.closest("[data-manage-team]");
    const editButton = event.target.closest("[data-edit-chapter]");
    const deleteButton = event.target.closest("[data-delete-chapter]");
    const bulkButton = event.target.closest("[data-bulk-chapter]");
    if (createButton)
      panelNavigate(
        "/panel/series/" + encodeURIComponent(content.id) + "/chapters/new",
      );
    if (teamButton)
      panelNavigate(
        "/panel/series/" + encodeURIComponent(content.id) + "/team",
      );
    if (editButton)
      panelNavigate(
        "/panel/series/" +
          encodeURIComponent(content.id) +
          "/chapters/" +
          encodeURIComponent(editButton.dataset.editChapter) +
          "/edit",
      );
    if (
      deleteButton &&
      confirm("Bu bölümü silmek istediğinize emin misiniz?")
    ) {
      try {
        await api(`/chapters/${deleteButton.dataset.deleteChapter}`, {
          method: "DELETE",
        });
        showToast("Bölüm silindi");
        await loadSeriesData();
        await loadChaptersPage(content.id);
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    }
    if (bulkButton) {
      const ids = Array.from(
        page.querySelectorAll("[data-chapter-select]:checked"),
      ).map((input) => input.value);
      if (!ids.length) return showToast("Önce en az bir bölüm seçin", "danger");
      const action = bulkButton.dataset.bulkChapter;
      const params = {};
      if (action === "schedule") {
        const publishedAt = prompt("Yayın tarihi (YYYY-MM-DD HH:MM):");
        if (!publishedAt) return;
        params.published_at = publishedAt;
      }
      if (action === "set_price") {
        const price = prompt("Coin fiyatı:", "0");
        if (price === null) return;
        params.price_amount = Number(price);
        const freeAfter = prompt("Ücretsiz olma tarihi (isteğe bağlı):", "");
        if (freeAfter) params.is_free_after = freeAfter;
      }
      if (
        action === "delete" &&
        !confirm(`${ids.length} bölümü silmek istediğinize emin misiniz?`)
      )
        return;
      try {
        const result = await api("/chapters/bulk", {
          method: "POST",
          body: { ids, action, params },
        });
        showToast(`${result?.data?.affected || ids.length} bölüm güncellendi`);
        await loadSeriesData();
        await loadChaptersPage(content.id);
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    }
  });
  page
    .querySelector("[data-select-all-chapters]")
    ?.addEventListener("change", (event) => {
      page.querySelectorAll("[data-chapter-select]").forEach((input) => {
        input.checked = event.target.checked;
      });
    });
}

// 4. Data Fetchers
async function loadDashboardData() {
  const requestEpoch = pageEpoch;
  try {
    const [data, insights, monetization, searches] = await Promise.all([
      api("/overview"),
      api("/metrics/insights?days=30&limit=10").catch(() => null),
      api("/analytics/monetization?days=30").catch(() => null),
      api("/analytics/search-insights?days=30&limit=10").catch(() => null),
    ]);
    assertCurrentPage(requestEpoch);
    if (data?.data) {
      const metrics = data.data.metrics || {};
      const insightData = insights?.data || {};
      const visits = insightData.visits || {};
      const views = insightData.views || {};
      const blogSummary = insightData.blogs?.summary || {};
      const money = monetization?.data || {};
      const searchData = searches?.data || {};
      store.batch(() => {
        store.set("overview.total_users", data.data.kpis?.users_total || 0);
        store.set(
          "overview.total_contents",
          data.data.kpis?.contents_total || 0,
        );
        store.set(
          "overview.total_chapters",
          data.data.kpis?.chapters_total || 0,
        );
        store.set(
          "overview.queue_pending",
          data.data.kpis?.blogs_pending_total || 0,
        );
        store.set("topContents", metrics.top_contents_7d || []);
        store.set("analytics.visits_daily", visits.daily || 0);
        store.set("analytics.visits_weekly", visits.weekly || 0);
        store.set("analytics.visits_monthly", visits.monthly || 0);
        store.set(
          "analytics.home_to_content",
          `${metrics.funnel?.home_to_content_pct || 0}%`,
        );
        store.set(
          "analytics.content_to_chapter",
          `${metrics.funnel?.content_to_chapter_pct || 0}%`,
        );
        store.set(
          "analytics.error_rate",
          `${metrics.performance_slo?.server_error_rate_pct_24h || 0}%`,
        );
        store.set(
          "analytics.p95",
          `${metrics.performance_slo?.p95_duration_ms_24h || 0} ms`,
        );
        store.set(
          "analytics.search_total",
          metrics.retention_search?.search_total_7d || 0,
        );
        store.set(
          "analytics.zero_result_pct",
          `${metrics.retention_search?.zero_result_pct_7d || 0}%`,
        );
        store.set(
          "analytics.d1_retention",
          `${metrics.retention_search?.d1_retention_pct || 0}%`,
        );
        store.set(
          "analytics.new_users",
          metrics.retention_search?.new_users_7d || 0,
        );
        store.set("analytics.total_coins", money.total_coins_spent || 0);
        store.set("analytics.total_unlocks", money.total_unlocks || 0);
        store.set("analytics.blog_total", blogSummary.total || 0);
        store.set("analytics.blog_visible", blogSummary.visible_total || 0);
        store.set("analytics.blog_hidden", blogSummary.hidden_total || 0);
        store.set("analytics.blog_deleted", blogSummary.deleted_total || 0);
        store.set("analytics.blog_created", blogSummary.created_last_days || 0);
        store.set(
          "analytics.blog_approved",
          blogSummary.approved_last_days || 0,
        );
        store.set("dashboardGenres", views.series_genres || []);
        store.set("dashboardTags", views.series_tags || []);
        store.set("dashboardReputation", insightData.reputation || []);
        store.set("dashboardTypes", views.types || []);
        store.set("dashboardChapters", views.chapters || []);
        store.set("dashboardBlogAuthors", insightData.blogs?.top_authors || []);
        store.set(
          "dashboardBlogDailyCreated",
          insightData.blogs?.daily_created || [],
        );
        store.set(
          "dashboardBlogDailyApproved",
          insightData.blogs?.daily_approved || [],
        );
        store.set("monetizationSeries", money.top_series || []);
        store.set("zeroResultSearches", searchData.zero_result_searches || []);
      });
      renderDashboardTables();
    }
  } catch (e) {
    if (e?.name === "AbortError") return;
    console.error("Dashboard load error:", e);
  }
}

async function loadSeriesData(page = 1) {
  const requestEpoch = pageEpoch;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "20" });
    const values = {
      q: document.getElementById("panel-series-search")?.value || "",
      status: document.getElementById("panel-series-status")?.value || "",
      type: document.getElementById("panel-series-type")?.value || "",
      lifecycle: document.getElementById("panel-series-lifecycle")?.value || "",
      sort: document.getElementById("panel-series-sort")?.value || "newest",
    };
    Object.entries(values).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const res = await api(`/series?${params.toString()}`);
    assertCurrentPage(requestEpoch);
    const items = responseItems(res);
    store.batch(() => {
      store.set("seriesList", items);
      store.set("seriesMeta", responseMeta(res));
    });
    renderSeriesTable();
  } catch (e) {
    if (e?.name === "AbortError") return;
    showToast("İçerikler yüklenemedi: " + e.message, "danger");
  }
}

async function loadUsersData(page = 1) {
  const requestEpoch = pageEpoch;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "20" });
    const values = {
      q: document.getElementById("panel-users-search")?.value || "",
      status: document.getElementById("panel-users-status")?.value || "",
      role: document.getElementById("panel-users-role")?.value || "",
      sort: document.getElementById("panel-users-sort")?.value || "newest",
    };
    Object.entries(values).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const res = await api(`/users?${params.toString()}`);
    assertCurrentPage(requestEpoch);
    const items = responseItems(res).map((user) => ({
      ...user,
      role_names: user.role_names || "user",
      account_status: Number(user.is_banned) === 1 ? "Yasaklı" : "Aktif",
      account_badge:
        Number(user.is_banned) === 1
          ? "bg-danger-subtle text-danger border border-danger-subtle"
          : "bg-success-subtle text-success border border-success-subtle",
    }));
    store.batch(() => {
      store.set("usersList", items);
      store.set("usersMeta", responseMeta(res));
    });
    renderUsersTable();
  } catch (e) {
    if (e?.name === "AbortError") return;
    showToast("Kullanıcılar yüklenemedi: " + e.message, "danger");
  }
}

async function loadBlogsData(page = 1) {
  const requestEpoch = pageEpoch;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "20" });
    const values = {
      q: document.getElementById("panel-blogs-search")?.value || "",
      status: document.getElementById("panel-blogs-status")?.value || "",
      sort: document.getElementById("panel-blogs-sort")?.value || "newest",
    };
    Object.entries(values).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const res = await api(`/blogs?${params.toString()}`);
    assertCurrentPage(requestEpoch);
    const labels = {
      draft: "Taslak",
      pending: "Bekliyor",
      published: "Yayınlandı",
      rejected: "Reddedildi",
      hidden: "Gizli",
    };
    store.batch(() => {
      store.set(
        "blogsList",
        responseItems(res).map((blog) => {
          const approved = Number(blog.approved) === 1;
          return {
            ...blog,
            status_label:
              labels[blog.status] || (approved ? "Onaylı" : "Bekliyor / Gizli"),
            status_badge: approved
              ? "bg-success-subtle text-success border border-success-subtle"
              : "bg-warning-subtle text-warning border border-warning-subtle",
            can_approve: approved ? false : true,
            can_hide: approved ? true : false,
          };
        }),
      );
      store.set("blogsMeta", responseMeta(res));
    });
    renderBlogsTable();
  } catch (e) {
    if (e?.name === "AbortError") return;
    showToast("Bloglar yüklenemedi: " + e.message, "danger");
  }
}

async function loadCommentsData(page = 1) {
  const requestEpoch = pageEpoch;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "20" });
    const values = {
      q: document.getElementById("panel-comments-search")?.value || "",
      target_type:
        document.getElementById("panel-comments-target")?.value || "",
      status: document.getElementById("panel-comments-status")?.value || "",
      sort: document.getElementById("panel-comments-sort")?.value || "newest",
    };
    Object.entries(values).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const res = await api(`/comments?${params.toString()}`);
    assertCurrentPage(requestEpoch);
    store.batch(() => {
      store.set(
        "commentsList",
        responseItems(res).map((comment) => ({
          ...comment,
          context_label: comment.blog_title
            ? `Blog: ${comment.blog_title}`
            : comment.content_title
              ? `${comment.target_type === "chapter" ? "Bölüm" : "İçerik"}: ${comment.content_title}${comment.chapter_number ? ` #${comment.chapter_number}` : ""}`
              : `${comment.target_type || "Hedef"}: ${comment.target_id || "-"}`,
        })),
      );
      store.set("commentsMeta", responseMeta(res));
    });
    renderCommentsTable();
  } catch (e) {
    if (e?.name === "AbortError") return;
    showToast("Yorumlar yüklenemedi: " + e.message, "danger");
  }
}

async function loadReportsData(page = 1) {
  const requestEpoch = pageEpoch;
  try {
    const params = new URLSearchParams({
      page: String(Math.max(1, Number(page) || 1)),
      per_page: "20",
    });
    const status = document.getElementById("panel-report-status")?.value || "";
    const targetType =
      document.getElementById("panel-report-target")?.value || "";
    if (status) params.set("status", status);
    if (targetType) params.set("target_type", targetType);
    const response = await api(`/reports?${params.toString()}`);
    assertCurrentPage(requestEpoch);
    store.batch(() => {
      store.set("reportsList", responseItems(response));
      store.set("reportsMeta", {
        page: Number(response?.meta?.page || 1),
        total_pages: Number(response?.meta?.total_pages || 1),
        total: Number(response?.meta?.total || 0),
        counts: response?.meta?.counts || {},
      });
    });
    renderReportsTable();
  } catch (error) {
    if (error?.name === "AbortError") return;
    showToast("Raporlar yüklenemedi: " + error.message, "danger");
  }
}

async function loadPackagesData() {
  const requestEpoch = pageEpoch;
  try {
    const res = await api("/shop/packages");
    assertCurrentPage(requestEpoch);
    store.set(
      "packagesList",
      responseItems(res).map((item) => ({
        ...item,
        status_label: Number(item.is_active) === 1 ? "Aktif" : "Pasif",
        status_badge:
          Number(item.is_active) === 1
            ? "bg-success-subtle text-success"
            : "bg-secondary-subtle text-secondary",
      })),
    );
    renderPackagesTable();
  } catch (e) {
    if (e?.name === "AbortError") return;
    showToast("Paketler yüklenemedi: " + e.message, "danger");
  }
}

async function loadFinanceData(page = 1) {
  const requestEpoch = pageEpoch;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "25" });
    const values = {
      q: document.getElementById("panel-finance-search")?.value || "",
      type: document.getElementById("panel-finance-type")?.value || "",
      sort: document.getElementById("panel-finance-sort")?.value || "newest",
    };
    Object.entries(values).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const response = await api(`/finance/transactions?${params.toString()}`);
    assertCurrentPage(requestEpoch);
    const data = response?.data || {};
    store.batch(() => {
      store.set("financeList", Array.isArray(data.items) ? data.items : []);
      store.set("financeSummary", data.summary || {});
      store.set(
        "financeMeta",
        data.meta || { page: 1, total_pages: 1, total: 0 },
      );
    });
    renderFinanceTable();
  } catch (error) {
    if (error?.name === "AbortError") return;
    showToast("Finans hareketleri alınamadı: " + error.message, "danger");
  }
}

async function loadLogsData(page = 1) {
  const requestEpoch = pageEpoch;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "50" });
    const values = {
      q: document.getElementById("panel-logs-search")?.value || "",
      method: document.getElementById("panel-logs-method")?.value || "",
      status: document.getElementById("panel-logs-status")?.value || "",
      sort: document.getElementById("panel-logs-sort")?.value || "newest",
      date_from: document.getElementById("panel-logs-from")?.value || "",
      date_to: document.getElementById("panel-logs-to")?.value || "",
    };
    Object.entries(values).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const res = await api(`/audit-logs?${params.toString()}`);
    assertCurrentPage(requestEpoch);
    store.batch(() => {
      store.set("logsList", responseItems(res));
      store.set("logsMeta", responseMeta(res));
    });
    renderLogsTable();
  } catch (e) {
    if (e?.name === "AbortError") return;
    showToast("Loglar yüklenemedi: " + e.message, "danger");
  }
}

async function loadUploadsData(page = 1) {
  const requestEpoch = pageEpoch;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "30" });
    const query = document.getElementById("panel-uploads-search")?.value || "";
    const mime = document.getElementById("panel-uploads-mime")?.value || "";
    const orphans =
      document.getElementById("panel-uploads-orphans")?.checked || false;
    if (query) params.set("q", query);
    if (mime) params.set("mime", mime);
    if (orphans) params.set("orphans", "1");
    const response = await api(`/uploads?${params.toString()}`);
    assertCurrentPage(requestEpoch);
    store.batch(() => {
      store.set(
        "uploadsList",
        responseItems(response).map((item) => ({
          ...item,
          original_name: item.original_name || item.file_path || "Dosya",
          username: item.username || item.user_id || "-",
          size_label: `${Math.max(0, Number(item.file_size || 0) / 1024).toFixed(1)} KB`,
        })),
      );
      store.set("uploadsMeta", responseMeta(response));
      store.set("uploadsStats", response?.meta?.stats || {});
    });
    renderUploadsTable();
  } catch (error) {
    if (error?.name === "AbortError") return;
    showToast("Yüklemeler alınamadı: " + error.message, "danger");
  }
}

async function loadQueueJobsData(page = 1) {
  const requestEpoch = pageEpoch;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "25" });
    const query = document.getElementById("panel-queue-search")?.value || "";
    const status = document.getElementById("panel-queue-status")?.value || "";
    if (query) params.set("q", query);
    if (status) params.set("status", status);
    const [response, health] = await Promise.all([
      api(`/queue/jobs?${params.toString()}`),
      api("/system/health"),
    ]);
    assertCurrentPage(requestEpoch);
    store.batch(() => {
      store.set("queueJobsList", responseItems(response));
      store.set("queueMeta", responseMeta(response));
      store.set("systemHealth", health?.data || {});
    });
    renderQueueTable();
  } catch (error) {
    if (error?.name === "AbortError") return;
    showToast("Kuyruk alınamadı: " + error.message, "danger");
  }
}

async function loadConfigData() {
  const requestEpoch = pageEpoch;
  try {
    const response = await api("/config/site");
    assertCurrentPage(requestEpoch);
    const config = response?.data || {};
    config.maintenance_whitelist_text = Array.isArray(
      config.maintenance_whitelist_ips,
    )
      ? config.maintenance_whitelist_ips.join("\n")
      : "";
    store.set("config", config);
  } catch (error) {
    if (error?.name === "AbortError") return;
    showToast("Ayarlar alınamadı: " + error.message, "danger");
  }
}

let logAutoRefreshTimer = null;

// 5. Global Action Handlers
const handlers = {
  refreshDashboard() {
    loadDashboardData();
    showToast("İstatistikler güncellendi");
  },
  loadSeries() {
    loadSeriesData(Number(store.get("seriesMeta")?.page || 1));
    showToast("İçerik listesi yenilendi");
  },
  loadUsers() {
    loadUsersData(Number(store.get("usersMeta")?.page || 1));
    showToast("Kullanıcı listesi yenilendi");
  },
  loadBlogs() {
    loadBlogsData(Number(store.get("blogsMeta")?.page || 1));
    showToast("Blog listesi yenilendi");
  },
  loadComments() {
    loadCommentsData(Number(store.get("commentsMeta")?.page || 1));
    showToast("Yorum listesi yenilendi");
  },
  loadReports() {
    loadReportsData(Number(store.get("reportsMeta")?.page || 1));
  },
  loadLogs() {
    loadLogsData(Number(store.get("logsMeta")?.page || 1));
    showToast("Loglar yenilendi");
  },
  filterLogs() {
    scheduleReload("logs", () => loadLogsData(1));
  },
  previousLogsPage() {
    const page = Number(store.get("logsMeta")?.page || 1);
    if (page > 1) loadLogsData(page - 1);
  },
  nextLogsPage() {
    const meta = store.get("logsMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadLogsData(Number(meta.page) + 1);
  },

  exportLogsCsv() {
    const columns = [
      "id",
      "method",
      "path",
      "status_code",
      "user_id",
      "username",
      "duration_ms",
      "created_at",
      "user_agent",
    ];
    const csvCell = (value) => {
      let textValue = String(value ?? "");
      if (/^[=+@-]/.test(textValue)) textValue = "'" + textValue;
      return `"${textValue.replaceAll('"', '""')}"`;
    };
    const csv = [
      columns.join(","),
      ...(store.get("logsList") || []).map((row) =>
        columns.map((column) => csvCell(row[column])).join(","),
      ),
    ].join("\n");
    const url = URL.createObjectURL(
      new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8" }),
    );
    const link = document.createElement("a");
    link.href = url;
    link.download = `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  },
  toggleLogAutoRefresh() {
    const button = document.getElementById("panel-log-auto");
    if (logAutoRefreshTimer) {
      clearInterval(logAutoRefreshTimer);
      logAutoRefreshTimer = null;
      setIconButtonLabel(button, "bi-broadcast", "Otomatik: Kapalı");
      return;
    }
    logAutoRefreshTimer = setInterval(() => loadLogsData(1), 15000);
    setIconButtonLabel(button, "bi-broadcast", "Otomatik: 15 sn");
  },
  loadUploads() {
    loadUploadsData();
    showToast("Yüklemeler yenilendi");
  },
  loadQueueJobs() {
    loadQueueJobsData();
    showToast("Kuyruk yenilendi");
  },
  filterQueue() {
    scheduleReload("queue", () => loadQueueJobsData(1));
  },
  previousQueuePage() {
    const page = Number(store.get("queueMeta")?.page || 1);
    if (page > 1) loadQueueJobsData(page - 1);
  },
  nextQueuePage() {
    const meta = store.get("queueMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadQueueJobsData(Number(meta.page) + 1);
  },
  async retryQueueJob(e, el) {
    try {
      await api(`/queue/jobs/${el.dataset.id}/retry`, { method: "POST" });
      showToast("İş yeniden kuyruğa alındı");
      await loadQueueJobsData(Number(store.get("queueMeta")?.page || 1));
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  },
  async cancelQueueJob(e, el) {
    if (!confirm(`Kuyruk işi #${el.dataset.id} iptal edilsin mi?`)) return;
    try {
      await api(`/queue/jobs/${el.dataset.id}/cancel`, { method: "POST" });
      showToast("İş iptal edildi");
      await loadQueueJobsData(Number(store.get("queueMeta")?.page || 1));
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  },

  async changeSeriesLifecycle(e, el) {
    const action = el.dataset.action;
    if (!confirm(`İçerik için ${action} işlemi uygulansın mı?`)) return;
    try {
      await api(`/content/${el.dataset.id}/lifecycle`, {
        method: "POST",
        body: { action },
      });
      showToast("Yayın durumu güncellendi");
      await loadSeriesData(Number(store.get("seriesMeta")?.page || 1));
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  },

  filterSeries() {
    scheduleReload("series", () => loadSeriesData(1));
  },
  filterUsers() {
    scheduleReload("users", () => loadUsersData(1));
  },
  filterBlogs() {
    scheduleReload("blogs", () => loadBlogsData(1));
  },
  filterComments() {
    scheduleReload("comments", () => loadCommentsData(1));
  },
  previousSeriesPage() {
    const page = Number(store.get("seriesMeta")?.page || 1);
    if (page > 1) loadSeriesData(page - 1);
  },
  nextSeriesPage() {
    const meta = store.get("seriesMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadSeriesData(Number(meta.page) + 1);
  },
  previousUsersPage() {
    const page = Number(store.get("usersMeta")?.page || 1);
    if (page > 1) loadUsersData(page - 1);
  },
  nextUsersPage() {
    const meta = store.get("usersMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadUsersData(Number(meta.page) + 1);
  },

  previousUserCommentsPage() {
    const page = Number(store.get("userCommentsMeta")?.page || 1);
    if (page > 1) loadUserCommentsData(store.get("userDetailId"), page - 1);
  },
  nextUserCommentsPage() {
    const meta = store.get("userCommentsMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadUserCommentsData(store.get("userDetailId"), Number(meta.page) + 1);
  },

  previousUserBlogsPage() {
    const page = Number(store.get("userBlogsMeta")?.page || 1);
    if (page > 1) loadUserBlogsData(store.get("userDetailId"), page - 1);
  },
  nextUserBlogsPage() {
    const meta = store.get("userBlogsMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadUserBlogsData(store.get("userDetailId"), Number(meta.page) + 1);
  },

  previousUserViolationsPage() {
    const page = Number(store.get("userViolationsMeta")?.page || 1);
    if (page > 1) loadUserViolationsData(store.get("userDetailId"), page - 1);
  },
  nextUserViolationsPage() {
    const meta = store.get("userViolationsMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadUserViolationsData(store.get("userDetailId"), Number(meta.page) + 1);
  },
  previousUserWalletPage() {
    const page = Number(store.get("userWalletMeta")?.page || 1);
    if (page > 1) loadUserWalletPage(store.get("userWalletId"), page - 1);
  },
  nextUserWalletPage() {
    const meta = store.get("userWalletMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadUserWalletPage(store.get("userWalletId"), Number(meta.page) + 1);
  },
  previousBlogsPage() {
    const page = Number(store.get("blogsMeta")?.page || 1);
    if (page > 1) loadBlogsData(page - 1);
  },
  nextBlogsPage() {
    const meta = store.get("blogsMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadBlogsData(Number(meta.page) + 1);
  },
  previousCommentsPage() {
    const page = Number(store.get("commentsMeta")?.page || 1);
    if (page > 1) loadCommentsData(page - 1);
  },
  nextCommentsPage() {
    const meta = store.get("commentsMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadCommentsData(Number(meta.page) + 1);
  },
  filterReports() {
    loadReportsData(1);
  },
  previousReportsPage() {
    const page = Number(store.get("reportsMeta")?.page || 1);
    if (page > 1) loadReportsData(page - 1);
  },
  nextReportsPage() {
    const meta = store.get("reportsMeta") || {};
    const page = Number(meta.page || 1);
    if (page < Number(meta.total_pages || 1)) loadReportsData(page + 1);
  },

  filterFinance() {
    scheduleReload("finance", () => loadFinanceData(1));
  },
  previousFinancePage() {
    const page = Number(store.get("financeMeta")?.page || 1);
    if (page > 1) loadFinanceData(page - 1);
  },
  nextFinancePage() {
    const meta = store.get("financeMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadFinanceData(Number(meta.page) + 1);
  },
  async refundFinanceTransaction(e, el) {
    const reason = prompt("İade nedeni:");
    if (!reason?.trim()) return;
    if (
      !confirm(
        `İşlem #${el.dataset.id} için coin iadesi yapılsın ve ilgili erişim geri alınsın mı?`,
      )
    )
      return;
    try {
      await api(`/finance/transactions/${el.dataset.id}/refund`, {
        method: "POST",
        body: { reason: reason.trim() },
      });
      showToast("İade işlemi tamamlandı");
      await loadFinanceData(Number(store.get("financeMeta")?.page || 1));
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  },

  async deleteBlog(e, el) {
    const id = el.dataset.id;
    if (!confirm(`Bu blog yazısını silmek istediğinize emin misiniz?`)) return;
    try {
      await api(`/blogs/${id}`, { method: "DELETE" });
      showToast("Blog yazısı silindi");
      loadBlogsData();
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },

  async approveBlog(e, el) {
    try {
      await api(`/blogs/${el.dataset.id}/approve`, { method: "POST" });
      showToast("Blog yazısı onaylandı");
      loadBlogsData();
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },

  async hideBlog(e, el) {
    if (!confirm("Bu blog yazısını gizlemek istediğinize emin misiniz?"))
      return;
    try {
      await api(`/blogs/${el.dataset.id}/hide`, { method: "POST" });
      showToast("Blog yazısı gizlendi");
      loadBlogsData();
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },

  async deleteComment(e, el) {
    const id = el.dataset.id;
    if (!confirm(`Bu yorumu silmek istediğinize emin misiniz?`)) return;
    try {
      await api(`/comments/${id}`, { method: "DELETE" });
      showToast("Yorum silindi");
      loadCommentsData();
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },

  async moderateComment(e, el) {
    try {
      await api(`/comments/${el.dataset.id}/moderation`, {
        method: "PUT",
        body: { status: el.dataset.status },
      });
      showToast(
        el.dataset.status === "approved" ? "Yorum onaylandı" : "Yorum gizlendi",
      );
      loadCommentsData(Number(store.get("commentsMeta")?.page || 1));
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },

  async deleteUpload(e, el) {
    if (!confirm("Bu yükleme kaydı ve bağlı dosya silinsin mi?")) return;
    try {
      await api(`/uploads/${el.dataset.id}`, { method: "DELETE" });
      showToast("Yükleme silindi");
      loadUploadsData(Number(store.get("uploadsMeta")?.page || 1));
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  },
  filterUploads() {
    scheduleReload("uploads", () => loadUploadsData(1));
  },
  previousUploadsPage() {
    const page = Number(store.get("uploadsMeta")?.page || 1);
    if (page > 1) loadUploadsData(page - 1);
  },
  nextUploadsPage() {
    const meta = store.get("uploadsMeta") || {};
    if (Number(meta.page) < Number(meta.total_pages))
      loadUploadsData(Number(meta.page) + 1);
  },
  toggleAllUploads(e, el) {
    document.querySelectorAll("[data-upload-select]").forEach((input) => {
      input.checked = el.checked;
    });
  },
  async bulkDeleteUploads() {
    const ids = Array.from(
      document.querySelectorAll("[data-upload-select]:checked"),
    ).map((input) => Number(input.value));
    if (ids.length === 0)
      return showToast("Önce en az bir dosya seçin", "danger");
    if (
      !confirm(`${ids.length} yükleme kaydı ve fiziksel dosyaları silinsin mi?`)
    )
      return;
    try {
      const response = await api("/uploads/bulk-delete", {
        method: "POST",
        body: { ids },
      });
      showToast(`${Number(response?.data?.deleted || 0)} dosya silindi`);
      await loadUploadsData(1);
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  },
  async optimizeUpload(e, el) {
    try {
      const response = await api(`/uploads/${el.dataset.id}/optimize`, {
        method: "POST",
      });
      showToast(`${Number(response?.data?.saved_bytes || 0)} bayt kazanıldı`);
      await loadUploadsData(Number(store.get("uploadsMeta")?.page || 1));
    } catch (error) {
      if (error?.name === "AbortError") return;
      showToast(error.message, "danger");
    }
  },

  async runQueueWorker() {
    try {
      const limit = Number(
        document.getElementById("panel-queue-limit")?.value || 20,
      );
      const res = await api("/queue/run-once", {
        method: "POST",
        body: { limit },
      });
      showToast(`Kuyruk çalıştırıldı (limit: ${res?.data?.limit || limit})`);
      loadQueueJobsData();
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },

  async runRetentionCleanup() {
    try {
      const days = Number(
        document.getElementById("panel-cleanup-days")?.value || 30,
      );
      await api("/retention/cleanup", { method: "POST", body: { days } });
      showToast("Sistem temizliği başarıyla tamamlandı");
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },

  async runCacheWarmup() {
    try {
      await api("/maintenance/warmup", { method: "POST" });
      showToast("Önbellek başarıyla ısıtıldı");
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },

  async generateSitemap() {
    try {
      await api("/maintenance/sitemap", { method: "POST" });
      showToast("Sitemap başarıyla üretildi");
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },

  async runMaintenance(e, el) {
    const output = document.getElementById("panel-maintenance-output");
    if (output) output.textContent = "İşlem çalışıyor...";
    try {
      const response = await api(`/maintenance/${el.dataset.task}`, {
        method: "POST",
      });
      const result = response?.data || {};
      if (output)
        output.textContent = Array.isArray(result.output)
          ? result.output.join("\n")
          : JSON.stringify(result, null, 2);
      if (result.success === false) {
        showToast("Bakım işlemi başarısız oldu", "danger");
      } else {
        showToast("Bakım işlemi tamamlandı");
      }
    } catch (error) {
      if (error?.name === "AbortError") return;
      if (output) output.textContent = error.message;
      showToast(error.message, "danger");
    }
  },

  async saveConfig(e) {
    if (e) e.preventDefault();
    try {
      const payload = { ...store.get("config") };
      payload.maintenance_whitelist_ips = String(
        payload.maintenance_whitelist_text || "",
      )
        .split(/\r?\n|,/)
        .map((value) => value.trim())
        .filter(Boolean);
      delete payload.maintenance_whitelist_text;
      // These values are managed in the root-only .env editor.
      delete payload.site_address;
      delete payload.enforce_https;
      const res = await api("/config/site", { method: "POST", body: payload });
      if (res?.data) {
        const updated = res.data;
        updated.maintenance_whitelist_text = Array.isArray(
          updated.maintenance_whitelist_ips,
        )
          ? updated.maintenance_whitelist_ips.join("\n")
          : "";
        store.set("config", updated);
      }
      showToast("Site ayarları başarıyla kaydedildi!");
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    }
  },
};

// 6. Router & View Mount
const target = document.getElementById("panel-app");

const panelPage = (view, options = {}) => ({
  view,
  permissions: [],
  ...options,
});

const panelRoutes = {
  segment: "",
  branches: [
    {
      segment: "series",
      index: panelPage("panel-series", {
        route: "series",
        section: "series",
        load: () => loadSeriesData(),
      }),
      branches: [
        {
          segment: "new",
          index: panelPage("panel-series-editor", {
            route: "series-new",
            section: "series",
            permissions: ["admin.content.create"],
            load: () => loadSeriesEditorPage("new"),
          }),
        },
        {
          segment: "ownership",
          index: panelPage(null, {
            route: "series-ownership",
            section: "series",
            permissions: ["admin.panel.access"],
            load: () => loadOwnershipPage(),
          }),
        },
        {
          segment: /^(?<seriesId>[a-zA-Z0-9_-]+)$/,
          param: "seriesId",
          branches: [
            {
              segment: "edit",
              index: panelPage("panel-series-editor", {
                route: "series-edit",
                section: "series",
                permissions: ["admin.content.update"],
                load: ({ seriesId }) => loadSeriesEditorPage("edit", seriesId),
              }),
            },
            {
              segment: "preview",
              index: panelPage(null, {
                route: "series-preview",
                section: "series",
                permissions: ["admin.panel.access"],
                load: ({ seriesId }) => loadSeriesPreviewPage(seriesId),
              }),
            },
            {
              segment: "revisions",
              index: panelPage(null, {
                route: "series-revisions",
                section: "series",
                permissions: ["admin.panel.access"],
                load: ({ seriesId }) => loadSeriesRevisionsPage(seriesId),
              }),
            },
            {
              segment: "chapters",
              index: panelPage(null, {
                route: "series-chapters",
                section: "series",
                permissions: ["admin.panel.access"],
                load: ({ seriesId }) => loadChaptersPage(seriesId),
              }),
              branches: [
                {
                  segment: "new",
                  index: panelPage(null, {
                    route: "chapter-new",
                    section: "series",
                    permissions: ["admin.chapter.create"],
                    load: ({ seriesId }) => loadChapterPage(seriesId),
                  }),
                },
                {
                  segment: /^(?<chapterId>[a-zA-Z0-9_-]+)$/,
                  param: "chapterId",
                  branches: [
                    {
                      segment: "edit",
                      index: panelPage(null, {
                        route: "chapter-edit",
                        section: "series",
                        permissions: ["admin.content.update"],
                        load: ({ seriesId, chapterId }) =>
                          loadChapterPage(seriesId, chapterId),
                      }),
                    },
                  ],
                },
              ],
            },
            {
              segment: "team",
              index: panelPage(null, {
                route: "series-team",
                section: "series",
                permissions: ["admin.panel.access"],
                load: ({ seriesId }) => loadTeamPage(seriesId),
              }),
            },
          ],
          fallback: panelPage("panel-series", {
            route: "series",
            section: "series",
            load: () => loadSeriesData(),
          }),
        },
      ],
      fallback: panelPage("panel-series", {
        route: "series",
        section: "series",
        load: () => loadSeriesData(),
      }),
    },
    {
      segment: "taxonomies",
      index: panelPage(null, {
        route: "taxonomies",
        section: "taxonomies",
        permissions: ["admin.content.create"],
        load: () => loadTaxonomyPage(),
      }),
    },
    {
      segment: "user",
      index: panelPage("panel-user", {
        route: "user",
        section: "user",
        load: () => loadUsersData(),
      }),
      branches: [
        {
          segment: "roles",
          index: panelPage(null, {
            route: "user-roles",
            section: "user",
            permissions: ["admin.panel.access"],
            load: () => loadRbacPage(),
          }),
        },
        {
          segment: /^(?<userId>[a-zA-Z0-9_-]+)$/,
          param: "userId",
          index: panelPage(null, {
            route: "user-detail",
            section: "user",
            permissions: ["admin.users.manage"],
            load: ({ userId }) => loadUserDetailPage(userId),
          }),
          branches: [
            {
              segment: "penalty",
              index: panelPage(null, {
                route: "user-penalty",
                section: "user",
                permissions: ["admin.users.manage"],
                load: ({ userId }) => loadUserPenaltyPage(userId),
              }),
            },
            {
              segment: "wallet",
              index: panelPage(null, {
                route: "user-wallet",
                section: "user",
                permissions: ["admin.wallet.view"],
                load: ({ userId }) => loadUserWalletPage(userId),
              }),
            },
          ],
          fallback: panelPage(null, {
            route: "user-detail",
            section: "user",
            permissions: ["admin.users.manage"],
            load: ({ userId }) => loadUserDetailPage(userId),
          }),
        },
      ],
      fallback: panelPage("panel-user", {
        route: "user",
        section: "user",
        load: () => loadUsersData(),
      }),
    },
    {
      segment: "blogs",
      index: panelPage("panel-blogs", {
        route: "blogs",
        section: "blogs",
        load: () => loadBlogsData(),
      }),
    },
    {
      segment: "comments",
      index: panelPage("panel-comments", {
        route: "comments",
        section: "comments",
        load: () => loadCommentsData(),
      }),
    },
    {
      segment: "reports",
      index: panelPage("panel-reports", {
        route: "reports",
        section: "reports",
        permissions: ["admin.reports.view"],
        load: () => loadReportsData(),
      }),
      branches: [
        {
          segment: /^(?<reportId>[0-9]+)$/,
          param: "reportId",
          index: panelPage("panel-report-detail", {
            route: "report-detail",
            section: "reports",
            permissions: ["admin.reports.view"],
            load: ({ reportId }) => loadReportDetailPage(reportId),
          }),
        },
      ],
      fallback: panelPage("panel-reports", {
        route: "reports",
        section: "reports",
        permissions: ["admin.reports.view"],
        load: () => loadReportsData(),
      }),
    },
    {
      segment: "monetization",
      index: panelPage("panel-monetization", {
        route: "monetization",
        section: "monetization",
        permissions: ["admin.shop.manage"],
        load: () => loadPackagesData(),
      }),
      branches: [
        {
          segment: "package",
          branches: [
            {
              segment: "new",
              index: panelPage(null, {
                route: "package-new",
                section: "monetization",
                permissions: ["admin.shop.manage"],
                load: () => renderPackagePage(),
              }),
            },
            {
              segment: /^(?<packageId>[a-zA-Z0-9_-]+)$/,
              param: "packageId",
              branches: [
                {
                  segment: "edit",
                  index: panelPage(null, {
                    route: "package-edit",
                    section: "monetization",
                    permissions: ["admin.shop.manage"],
                    load: ({ packageId }) => loadPackagePage(packageId),
                  }),
                },
              ],
            },
          ],
          fallback: panelPage("panel-monetization", {
            route: "monetization",
            section: "monetization",
            permissions: ["admin.shop.manage"],
            load: () => loadPackagesData(),
          }),
        },
        {
          segment: "ad-free",
          index: panelPage(null, {
            route: "ad-free",
            section: "monetization",
            permissions: ["admin.shop.manage"],
            load: () => loadAdFreePage(),
          }),
        },
        {
          segment: "pricing",
          index: panelPage(null, {
            route: "pricing",
            section: "monetization",
            permissions: ["admin.shop.manage"],
            load: () => loadPricingPage(),
          }),
        },
      ],
      fallback: panelPage("panel-monetization", {
        route: "monetization",
        section: "monetization",
        permissions: ["admin.shop.manage"],
        load: () => loadPackagesData(),
      }),
    },
    {
      segment: "finance",
      index: panelPage("panel-finance", {
        route: "finance",
        section: "finance",
        permissions: ["admin.finance.view"],
        load: () => loadFinanceData(),
      }),
    },
    {
      segment: "ops",
      index: panelPage("panel-ops", {
        route: "ops",
        section: "ops",
        permissions: ["admin.health.view"],
        load: () => loadQueueJobsData(),
      }),
    },
    {
      segment: "logs",
      index: panelPage("panel-logs", {
        route: "logs",
        section: "logs",
        permissions: ["admin.logs.view"],
        load: () => loadLogsData(),
      }),
      branches: [
        {
          segment: "moderation",
          index: panelPage(null, {
            route: "moderation",
            section: "logs",
            permissions: ["admin.logs.view"],
            load: () => loadModerationPage(),
          }),
        },
        {
          segment: "viewer",
          branches: [
            {
              segment: /^(?<logSlug>[a-zA-Z0-9_-]+)$/,
              param: "logSlug",
              index: panelPage(null, {
                route: "log-viewer",
                section: "logs",
                permissions: ["admin.logs.view"],
                load: ({ logSlug }) =>
                  loadLogPage(logSlug === "error" ? "logs/error" : logSlug),
              }),
            },
          ],
          fallback: panelPage("panel-logs", {
            route: "logs",
            section: "logs",
            permissions: ["admin.logs.view"],
            load: () => loadLogsData(),
          }),
        },
        {
          segment: "audit",
          branches: [
            {
              segment: /^(?<auditId>[0-9]+)$/,
              param: "auditId",
              index: panelPage(null, {
                route: "audit-log",
                section: "logs",
                permissions: ["admin.logs.view"],
                load: ({ auditId }) => loadAuditPage(auditId),
              }),
            },
          ],
          fallback: panelPage("panel-logs", {
            route: "logs",
            section: "logs",
            permissions: ["admin.logs.view"],
            load: () => loadLogsData(),
          }),
        },
      ],
      fallback: panelPage("panel-logs", {
        route: "logs",
        section: "logs",
        permissions: ["admin.logs.view"],
        load: () => loadLogsData(),
      }),
    },
    {
      segment: "uploads",
      index: panelPage("panel-uploads", {
        route: "uploads",
        section: "uploads",
        permissions: ["admin.uploads.view"],
        load: () => loadUploadsData(),
      }),
    },
    {
      segment: "config",
      index: panelPage("panel-config", {
        route: "config",
        section: "config",
        permissions: ["admin.settings.modify"],
        load: () => loadConfigData(),
      }),
    },
    {
      segment: "webhook",
      index: panelPage("panel-webhook", {
        route: "webhook",
        section: "webhook",
        permissions: ["admin.settings.modify"],
        load: () => loadWebhookPage(),
      }),
    },
    {
      segment: "config-env",
      index: panelPage("panel-config-env", {
        route: "config-env",
        section: "config-env",
        permissions: ["admin.settings.modify"],
        load: () => loadEnvPage(),
      }),
    },
    {
      segment: "help",
      index: panelPage("panel-help", { route: "help", section: "help" }),
    },
  ],
  index: panelPage("panel-dashboard", {
    route: "dashboard",
    section: "dashboard",
    load: () => loadDashboardData(),
  }),
  fallback: panelPage("panel-dashboard", {
    route: "dashboard",
    section: "dashboard",
    redirect: "/panel",
    load: () => loadDashboardData(),
  }),
};

function panelSegments() {
  // Panel navigation is pathname-only; legacy hash/action URLs are ignored.
  const path = window.location.pathname.replace(/^\/panel(?:\/|$)/, "");
  return path
    .split("/")
    .filter(Boolean)
    .map((segment) => {
      try {
        return decodeURIComponent(segment);
      } catch {
        return segment;
      }
    });
}

function matchPanelSegment(pattern, segment) {
  if (typeof pattern === "string") {
    return pattern === segment ? { groups: null } : null;
  }

  return String(segment).match(pattern);
}

function resolvePanelNode(node, segments, params = {}) {
  if (segments.length === 0) {
    if (node.index) return { ...node.index, params };
    if (node.fallback) return { ...node.fallback, params };
    return null;
  }

  const segment = segments[0];

  for (const branch of node.branches || []) {
    const matched = matchPanelSegment(branch.segment, segment);
    if (!matched) continue;

    const nextParams = { ...params };
    if (branch.param) {
      nextParams[branch.param] =
        matched.groups?.[branch.param] || matched[1] || segment;
    }

    const result = resolvePanelNode(branch, segments.slice(1), nextParams);
    if (result) return result;
  }

  return node.fallback ? { ...node.fallback, params } : null;
}

function resolvePanelRoute() {
  return resolvePanelNode(panelRoutes, panelSegments());
}

function navigate() {
  let resolved = resolvePanelRoute();
  if (!resolved) return;

  if (resolved.redirect && window.location.pathname !== resolved.redirect) {
    history.replaceState({}, "", resolved.redirect);
    resolved = resolvePanelRoute();
  }

  const required = resolved.permissions || [];
  if (required.length > 0 && !hasPermission(...required)) {
    history.replaceState({}, "", "/panel");
    resolved = resolvePanelRoute();
  }

  pageEpoch++;
  pageRequests.abort();
  pageRequests = new AbortController();
  for (const timer of reloadTimers.values()) clearTimeout(timer);
  reloadTimers.clear();
  disposePage();
  const sectionParent =
    {
      taxonomies: "series",
      webhook: "config",
      "config-env": "config",
    }[resolved.section] || resolved.section;
  pageParent =
    resolved.section === "user" && resolved.params.userId
      ? "/panel/user/" + encodeURIComponent(resolved.params.userId)
      : sectionParent && sectionParent !== "dashboard"
        ? "/panel/" + sectionParent
        : "/panel";
  if (["chapter-new", "chapter-edit", "series-team"].includes(resolved.route)) {
    pageParent =
      "/panel/series/" +
      encodeURIComponent(resolved.params.seriesId) +
      "/chapters";
  }
  store.set("currentRoute", resolved.route);

  let activeNavLink = null;
  const activeSection = resolved.section || resolved.route || "dashboard";
  document.querySelectorAll("#panel-sidebar-nav a[data-route]").forEach((a) => {
    if (a.getAttribute("data-route") === activeSection) {
      a.classList.add("active-nav-link");
      activeNavLink = a;
    } else {
      a.classList.remove("active-nav-link");
    }
  });
  const activeNavGroup = activeNavLink
    ?.closest(".nav-treeview")
    ?.closest(".nav-item");
  if (activeNavGroup) {
    const groupToggle = activeNavGroup.querySelector(
      ':scope > .nav-link[role="button"][href="#"]',
    );
    if (groupToggle) setSidebarGroupState(activeNavGroup, groupToggle, true);
  }

  if (resolved.route !== "logs" && logAutoRefreshTimer) {
    clearInterval(logAutoRefreshTimer);
    logAutoRefreshTimer = null;
  }

  if (resolved.view) {
    mountPage(resolved.view, resolved.params);
    renderRouteTables(resolved.route);
  } else {
    mountPage("panel-loading");
  }

  if (typeof resolved.load === "function") {
    Promise.resolve(resolved.load(resolved.params)).catch((error) => {
      if (error?.name === "AbortError") return;
      mountPage("panel-page-error", {
        parent_path: pageParent,
        error_message: error.message || "Sayfa verileri yüklenemedi.",
      });
    });
  }
}

applyPermissionVisibility(document);
document.addEventListener("click", (event) => {
  const link =
    event.target instanceof Element
      ? event.target.closest(
          "a[data-panel-link], #panel-sidebar-nav a[data-route]",
        )
      : null;
  if (
    !link ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  )
    return;
  event.preventDefault();
  panelNavigate(link.getAttribute("href"));
});
window.addEventListener("popstate", () => navigate());
navigate();
