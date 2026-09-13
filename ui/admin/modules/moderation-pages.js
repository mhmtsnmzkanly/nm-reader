/** Liker and report detail page controller. */
export function createModerationPagesController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch,
  getPageParent,
  assertCurrentPage,
  safeLocalPath,
  mountEditorPage,
  mountPartial,
  renderPager,
  panelNavigate,
  showToast,
  hasPermission,
  requestGate,
  bindFormAction,
  registerPageCleanup,
  translate = (_key, fallback) => fallback,
  targetTypeLabel = (type) => String(type || "-") || "-",
  documentRef = globalThis.document,
} = {}) {
  const document = documentRef;
  async function loadLikersPage(targetType, targetId, pageNumber = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGate?.begin();
    const query = document.getElementById("panel-likers-search")?.value || "";
    const params = new URLSearchParams({
      target_type: targetType,
      target_id: targetId,
      page: String(Math.max(1, Number(pageNumber) || 1)),
      per_page: "20",
    });
    if (query.trim()) params.set("q", query.trim());

    const response = await api(`/votes/likers?${params.toString()}`);
    assertCurrentPage(requestEpoch);
    if (requestGate && !requestGate.isCurrent(requestToken)) return;
    const target = response?.meta?.target || {};
    const parentId = Number(target.parent_id || 0);
    const isReply = Number.isInteger(parentId) && parentId > 0;
    const parentReference = isReply
      ? `${
        target.parent_username ? `@${target.parent_username} · ` : ""
      }#${parentId}`
      : "";
    const fallbackAvatar = safeLocalPath(
      store.get("config")?.default_profile_image,
    );
    const items = responseItems(response).map((item) => ({
      ...item,
      display_name: item.display_name || "-",
      avatar_url: safeLocalPath(item.profile_image) !== "#"
        ? safeLocalPath(item.profile_image)
        : fallbackAvatar !== "#"
        ? fallbackAvatar
        : "/assets/img/default-profile.svg",
      profile_url: `/panel/user/${encodeURIComponent(String(item.user_id))}`,
    }));
    const targetTitle = String(target.title || `${targetType} #${targetId}`);
    const targetContext = targetType === "blog"
      ? `Blog / ${target.slug || targetId}`
      : `${target.target_label || "Yorum"} · #${targetId}${
        isReply ? ` · Yanıt: ${parentReference}` : ""
      }`;
    store.batch(() => {
      store.set("likersList", items);
      store.set("likersMeta", responseMeta(response));
      store.set("likersTarget", { targetType, targetId });
    });

    const page = mountEditorPage(
      translate("admin.moderation.likers_title", "Beğenen kullanıcılar"),
      {
      name: "panel-likers",
      context: {
        target_title: targetTitle,
        target_context: targetContext,
        target_type_label: targetType === "blog"
          ? translate("admin.target.blog", "Blog")
          : isReply
          ? translate("admin.target.comment_reply", "Yorum yanıtı")
          : translate("admin.target.comment", "Yorum"),
        total: Number(response?.meta?.total || 0),
        query,
      },
      },
    );
    mountPartial(
      "panel-rows-likers",
      page.querySelector("#panel-likers-list"),
      { items, has_items: items.length > 0 },
    );
    renderPager(
      "panel-likers-pager",
      responseMeta(response),
      "previousLikersPage",
      "nextLikersPage",
    );
  }

  async function loadReportDetailPage(id) {
    const requestEpoch = getPageEpoch();
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
        target_type: targetTypeLabel(report.target_type, translate),
        target_label: report.target_title || report.target_id || "-",
        reason: report.reason || "-",
        description: report.description || report.comment_body ||
          translate("admin.content.no_description", "Açıklama yok"),
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
      if (form && bindFormAction) registerPageCleanup?.(bindFormAction(form, async (data) => {
          await api(`/reports/${encodeURIComponent(id)}`, {
            method: "PUT",
            body: Object.fromEntries(data.entries()),
          });
          showToast(translate("admin.moderation.report_updated", "Rapor güncellendi"));
          panelNavigate("/panel/reports");
        }, { onError: (error) => showToast(error.message, "danger") }));
    } catch (error) {
      if (error?.name === "AbortError") return;
      mountPartial("panel-page-error", target, {
        parent_path: getPageParent(),
        error_message: error.message,
      });
    }
  }

  return Object.freeze({ loadLikersPage, loadReportDetailPage });
}
