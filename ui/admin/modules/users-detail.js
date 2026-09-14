/** Profile editing, user content and violation history. */
export function createUsersDetailController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch,
  assertCurrentPage,
  hasPermission,
  safeLocalPath,
  setTableRows,
  renderPager,
  mountPage,
  scheduleReload,
  showToast,
  userModerationStatus,
  userViolationLevel,
  commentThreadFields,
  targetTypeLabel = (type) => String(type || "-") || "-",
  moderationScopeLabel = (scope) => String(scope || "-") || "-",
  moderationActionLabel = (action) => String(action || "-") || "-",
  translate = (_key, fallback) => fallback,
  formatNumber = (value) => String(Number(value || 0)),
  bindFormAction,
  documentRef = globalThis.document,
  requestGates = {},
  registerPageCleanup,
} = {}) {
  const document = documentRef;

  function renderUserCommentsTable() {
    const items = (store.get("userCommentsList") || []).map((comment) => {
      const status = userModerationStatus(
        comment.moderation_status || "approved",
        translate,
      );
      const context = comment.blog_title || comment.content_title
        ? `${targetTypeLabel(comment.target_type, translate)}: ${
          comment.blog_title || comment.content_title
        }${comment.chapter_number ? ` #${comment.chapter_number}` : ""}`
        : `${targetTypeLabel(comment.target_type, translate)}: ${comment.target_id || "-"}`;
      return {
        ...comment,
        ...commentThreadFields(comment),
        context_label: context,
        status_label: status[0],
        status_class: status[1],
        upvotes: Number(comment.upvote_count || 0),
        downvotes: Number(comment.downvote_count || 0),
        likers_url: `/panel/comments/${
          encodeURIComponent(String(comment.id))
        }/likers`,
      };
    });
    setTableRows(
      "panel-user-comments-list",
      "panel-rows-user-comments",
      items,
      6,
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
      draft: translate("admin.status.draft", "Taslak"),
      pending: translate("admin.status.pending", "Bekliyor"),
      published: translate("admin.status.published", "Yayınlandı"),
      rejected: translate("admin.status.rejected", "Reddedildi"),
      hidden: translate("admin.status.hidden", "Gizli"),
    };
    const items = (store.get("userBlogsList") || []).map((blog) => {
      const status = blog.status ||
        (Number(blog.approved) === 1 ? "published" : "pending");
      const statusClass = Number(blog.approved) === 1
        ? "bg-success-subtle text-success"
        : "bg-warning-subtle text-warning";
      return {
        ...blog,
        preview_url: `/panel/blogs/${
          encodeURIComponent(String(blog.id))
        }/preview`,
        likers_url: `/panel/blogs/${
          encodeURIComponent(String(blog.id))
        }/likers`,
        upvotes: Number(blog.upvote_count || blog.likes || 0),
        slug_label: blog.slug || blog.id,
        status_class: statusClass,
        status_label: labels[status] || status,
      };
    });
    setTableRows("panel-user-blogs-list", "panel-rows-user-blogs", items, 6);
    renderPager(
      "panel-user-blogs-pager",
      store.get("userBlogsMeta"),
      "previousUserBlogsPage",
      "nextUserBlogsPage",
    );
  }

  function renderUserViolationsTable() {
    const items = (store.get("userViolationsList") || []).map((violation) => {
      const level = userViolationLevel(violation.level, translate);
      const active = violation.revoked_at
        ? translate("admin.violation.revoked", "İptal edildi")
        : violation.ends_at &&
            new Date(violation.ends_at.replace(" ", "T")) < new Date()
        ? translate("admin.violation.expired", "Süresi doldu")
        : translate("admin.status.active", "Aktif");
      return {
        ...violation,
        level_label: level[0],
        level_class: level[1],
        active_label: active,
        scope_label: moderationScopeLabel(violation.scope, translate),
        action_label: moderationActionLabel(violation.action, translate),
        target_type_label: targetTypeLabel(violation.target_type, translate),
        target_id_label: violation.target_id || "-",
        moderator_label: violation.moderator_username ||
          violation.moderator_user_id || "-",
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
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.comments?.begin();
    if (!userId) return;
    setTableRows?.("panel-user-comments-list", "", [], 6, { loading: true });
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "10",
      });
      const values = {
        q: document.getElementById("panel-user-comments-search")?.value || "",
        target_type:
          document.getElementById("panel-user-comments-target")?.value || "",
        moderation_status:
          document.getElementById("panel-user-comments-status")?.value || "",
        sort: document.getElementById("panel-user-comments-sort")?.value ||
          "newest",
      };
      Object.entries(values).forEach(([key, value]) => {
        if (value) params.set(key, value);
      });
      const response = await api(
        `/users/${encodeURIComponent(userId)}/comments?${params.toString()}`,
      );
      assertCurrentPage(requestEpoch);
      if (requestGates.comments && !requestGates.comments.isCurrent(requestToken)) return;
      if (String(store.get("userDetailId")) !== String(userId)) return;
      store.batch(() => {
        store.set("userCommentsList", responseItems(response));
        store.set("userCommentsMeta", responseMeta(response));
      });
      renderUserCommentsTable();
    } catch (error) {
      if (error?.name === "AbortError") return;
      if (requestGates.comments && !requestGates.comments.isCurrent(requestToken)) return;
      const target = document.getElementById("panel-user-comments-list");
      if (target) {
        setTableRows(
          "panel-user-comments-list",
          "panel-rows-user-comments",
          [],
          6,
          { error_message: error.message },
        );
      }
    }
  }

  async function loadUserBlogsData(userId, page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.blogs?.begin();
    if (!userId) return;
    setTableRows?.("panel-user-blogs-list", "", [], 6, { loading: true });
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "10",
      });
      const values = {
        q: document.getElementById("panel-user-blogs-search")?.value || "",
        status: document.getElementById("panel-user-blogs-status")?.value || "",
        sort: document.getElementById("panel-user-blogs-sort")?.value ||
          "newest",
      };
      Object.entries(values).forEach(([key, value]) => {
        if (value) params.set(key, value);
      });
      const response = await api(
        `/users/${encodeURIComponent(userId)}/blogs?${params.toString()}`,
      );
      assertCurrentPage(requestEpoch);
      if (requestGates.blogs && !requestGates.blogs.isCurrent(requestToken)) return;
      if (String(store.get("userDetailId")) !== String(userId)) return;
      store.batch(() => {
        store.set("userBlogsList", responseItems(response));
        store.set("userBlogsMeta", responseMeta(response));
      });
      renderUserBlogsTable();
    } catch (error) {
      if (error?.name === "AbortError") return;
      if (requestGates.blogs && !requestGates.blogs.isCurrent(requestToken)) return;
      const target = document.getElementById("panel-user-blogs-list");
      if (target) {
        setTableRows("panel-user-blogs-list", "panel-rows-user-blogs", [], 6, {
          error_message: error.message,
        });
      }
    }
  }

  async function loadUserViolationsData(userId, page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.violations?.begin();
    if (!userId) return;
    setTableRows?.("panel-user-violations-list", "", [], 5, { loading: true });
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "10",
      });
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
      if (requestGates.violations && !requestGates.violations.isCurrent(requestToken)) return;
      if (String(store.get("userDetailId")) !== String(userId)) return;
      store.batch(() => {
        store.set("userViolationsList", responseItems(response));
        store.set("userViolationsMeta", responseMeta(response));
      });
      renderUserViolationsTable();
    } catch (error) {
      if (error?.name === "AbortError") return;
      if (requestGates.violations && !requestGates.violations.isCurrent(requestToken)) return;
      const target = document.getElementById("panel-user-violations-list");
      if (target) {
        setTableRows(
          "panel-user-violations-list",
          "panel-rows-user-violations",
          [],
          5,
          { error_message: error.message },
        );
      }
    }
  }

  function userDetailFilters(pageRoot, userId) {
    const bindReload = (selector, key, loader) => {
      pageRoot.querySelectorAll(selector).forEach((input) => {
        const eventName = input.tagName === "INPUT" ? "input" : "change";
        const onInput = () => scheduleReload(key, () => loader(userId, 1));
        input.addEventListener(eventName, onInput);
        registerPageCleanup?.(() => input.removeEventListener(eventName, onInput));
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
    pageRoot.querySelectorAll("[data-user-tab]").forEach((button) => {
      const onClick = () => {
        const tab = button.dataset.userTab;
        pageRoot
          .querySelectorAll("[data-user-tab]")
          .forEach((item) => item.classList.toggle("active", item === button));
        pageRoot.querySelectorAll("[data-user-section]").forEach((section) => {
          section.hidden = section.dataset.userSection !== tab;
        });
        if (tab === "blogs") {
          loadUserBlogsData(
            userId,
            Number(store.get("userBlogsMeta")?.page || 1),
          );
        }
        if (tab === "violations") {
          loadUserViolationsData(
            userId,
            Number(store.get("userViolationsMeta")?.page || 1),
          );
        }
      };
      button.addEventListener("click", onClick);
      registerPageCleanup?.(() => button.removeEventListener("click", onClick));
    });
  }

  async function loadUserDetailPage(userId) {
    const requestEpoch = getPageEpoch();
    if (!userId) {
      throw new Error(
        translate("admin.user.id_missing", "Kullanıcı kimliği bulunamadı"),
      );
    }
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
    const currentRole = String(user.role_names || "user")
      .split(",")[0]
      .trim() || "user";
    if (!roles.some((role) => role.slug === currentRole)) {
      roles.push({ slug: currentRole, name: currentRole });
    }
    const restrictions = (overview.active_restrictions || []).map((item) => {
      const level = userViolationLevel(item.level, translate);
      return {
        class_name: level[1],
        label: `${moderationScopeLabel(item.type || "general", translate)}: ${level[0]}${
          item.ends_at ? ` · ${item.ends_at}` : ""
        }`,
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
      status_label: user.is_banned
        ? translate("admin.status.interaction_restricted", "Aktif kısıtlama")
        : translate("admin.status.interaction_open", "Etkileşim açık"),
      restrictions,
      has_restrictions: restrictions.length > 0,
      roles,
      comments_total: formatNumber(stats.comments_total),
      blogs_total: formatNumber(stats.blogs_total),
      violations_total: formatNumber(stats.violations_total),
      balance_coin: formatNumber(wallet.balance_coin),
      can_penalty: hasPermission("admin.users.manage"),
      can_wallet: hasPermission("admin.wallet.view"),
      header: {
        title: translate("admin.user.detail_title", "Kullanıcı detayı"),
        description: translate(
          "admin.user.detail_description",
          "Profil, içerik, cüzdan ve moderasyon geçmişi",
        ),
        parent_path: "/panel/user",
        show_back: true,
      },
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
    const profileImageElement = page.querySelector("[data-user-profile]");
    if (profileImageElement) {
      profileImageElement.setAttribute(
        "src",
        profileImage === "#" ? "/assets/img/default-profile.svg" : profileImage,
      );
    }
    const roleSelect = page.querySelector("#panel-user-role");
    if (roleSelect) roleSelect.value = currentRole;
    const profileForm = page.querySelector("#panel-user-profile-form");
    if (profileForm && bindFormAction) registerPageCleanup?.(bindFormAction(profileForm, async (data) => {
        await api(`/users/${encodeURIComponent(userId)}/profile`, {
          method: "PUT",
          body: Object.fromEntries(data.entries()),
        });
        showToast(translate("admin.user.profile_updated", "Profil bilgileri güncellendi"));
        await loadUserDetailPage(userId);
      }, { onError: (error) => showToast(error.message, "danger") }));
    userDetailFilters(page, userId);
    renderUserCommentsTable();
    renderUserBlogsTable();
    renderUserViolationsTable();
    await loadUserCommentsData(userId, 1);
    assertCurrentPage(requestEpoch);
  }

  return Object.freeze({
    renderUserCommentsTable,
    renderUserBlogsTable,
    renderUserViolationsTable,
    loadUserCommentsData,
    loadUserBlogsData,
    loadUserViolationsData,
    userDetailFilters,
    loadUserDetailPage,
  });
}
