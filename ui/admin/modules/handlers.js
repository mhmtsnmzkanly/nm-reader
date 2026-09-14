import { adjacentPage } from "./pagination.js";

/** Thin event-handler registry. Domain operations are injected by the panel bootstrap. */
export function createPanelHandlers({
  store,
  api,
  showToast,
  confirmAction = () => false,
  promptValue = () => null,
  scheduleReload,
  setIconButtonLabel,
  loadDashboardData,
  loadSeriesData,
  loadUsersData,
  loadBlogsData,
  loadCommentsData,
  loadReportsData,
  loadLogsData,
  loadUploadsData,
  loadQueueJobsData,
  loadLikersPage,
  loadFinanceData,
  loadUserCommentsData,
  loadUserBlogsData,
  loadUserViolationsData,
  loadUserWalletPage,
  registerPageCleanup,
  translate = (_key, fallback) => fallback,
  documentRef = globalThis.document,
} = {}) {
  const document = documentRef;
  const changePage = (metaKey, direction, load) => {
    const page = adjacentPage(store.get(metaKey), direction);
    if (page !== null) return load(page);
  };
  let logAutoRefreshTimer = null;
  let logAutoRefreshCleanupRegistered = false;
  const stopLogAutoRefresh = () => {
    if (!logAutoRefreshTimer) return;
    clearInterval(logAutoRefreshTimer);
    logAutoRefreshTimer = null;
  };
  const handlers = {
    async refreshDashboard() {
      const success = await loadDashboardData();
      if (success === undefined) return;
      showToast(
        success
          ? translate("admin.toast.dashboard_updated", "İstatistikler güncellendi")
          : translate("admin.toast.dashboard_failed", "Dashboard verileri alınamadı"),
        success ? "success" : "danger",
      );
    },
    changeDashboardPeriod({ event, element } = {}) {
      const value = Number(element?.value || event?.target?.value || 30);
      store.set("dashboardPeriodDays", Math.max(1, Math.min(90, value)));
      loadDashboardData();
    },
    async loadSeries() {
      if (await loadSeriesData(Number(store.get("seriesMeta")?.page || 1)) === true) {
        showToast(translate("admin.toast.series_refreshed", "İçerik listesi yenilendi"));
      }
    },
    async loadUsers() {
      if (await loadUsersData(Number(store.get("usersMeta")?.page || 1)) === true) {
        showToast(translate("admin.toast.users_refreshed", "Kullanıcı listesi yenilendi"));
      }
    },
    async loadBlogs() {
      if (await loadBlogsData(Number(store.get("blogsMeta")?.page || 1)) === true) {
        showToast(translate("admin.toast.blogs_refreshed", "Blog listesi yenilendi"));
      }
    },
    async loadComments() {
      if (await loadCommentsData(Number(store.get("commentsMeta")?.page || 1)) === true) {
        showToast(translate("admin.toast.comments_refreshed", "Yorum listesi yenilendi"));
      }
    },
    loadReports() {
      loadReportsData(Number(store.get("reportsMeta")?.page || 1));
    },
    async loadLogs() {
      if (await loadLogsData(Number(store.get("logsMeta")?.page || 1)) === true) {
        showToast(translate("admin.toast.logs_refreshed", "Loglar yenilendi"));
      }
    },
    filterLogs() {
      scheduleReload("logs", () => loadLogsData(1));
    },
    previousLogsPage() {
      return changePage("logsMeta", -1, loadLogsData);
    },
    nextLogsPage() {
      return changePage("logsMeta", 1, loadLogsData);
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
          columns.map((column) => csvCell(row[column])).join(",")
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
        stopLogAutoRefresh();
        setIconButtonLabel(
          button,
          "bi-broadcast",
          translate("admin.logs.auto_off", "Otomatik: Kapalı"),
        );
        return;
      }
      logAutoRefreshTimer = setInterval(() => loadLogsData(1), 15000);
      if (!logAutoRefreshCleanupRegistered && registerPageCleanup) {
        logAutoRefreshCleanupRegistered = true;
        registerPageCleanup(() => {
          stopLogAutoRefresh();
          logAutoRefreshCleanupRegistered = false;
        });
      }
      setIconButtonLabel(
        button,
        "bi-broadcast",
        translate("admin.logs.auto_15s", "Otomatik: 15 sn"),
      );
    },
    async loadUploads() {
      if (await loadUploadsData(Number(store.get("uploadsMeta")?.page || 1)) === true) {
        showToast(translate("admin.toast.uploads_refreshed", "Yüklemeler yenilendi"));
      }
    },
    async loadQueueJobs() {
      if (await loadQueueJobsData(Number(store.get("queueMeta")?.page || 1)) === true) {
        showToast(translate("admin.toast.queue_refreshed", "Kuyruk yenilendi"));
      }
    },
    switchOpsView({ element } = {}) {
      const view = element?.dataset.opsView || "all";
      const sections = {
        health: document.getElementById("panel-ops-health"),
        queue: document.getElementById("panel-ops-queue"),
        maintenance: document.getElementById("panel-ops-maintenance"),
      };
      if (view === "all") {
        Object.values(sections).forEach((section) => {
          if (section) section.hidden = false;
        });
      } else {
        Object.entries(sections).forEach(([key, section]) => {
          if (section) section.hidden = key !== view;
        });
      }
      document
        .querySelectorAll("[data-ops-view]")
        .forEach((button) => {
          const active = button.dataset.opsView === view;
          button.classList.toggle("btn-primary", active);
          button.classList.toggle("btn-outline-secondary", !active);
          button.setAttribute("aria-selected", String(active));
        });
    },
    filterQueue() {
      scheduleReload("queue", () => loadQueueJobsData(1));
    },
    previousQueuePage() {
      return changePage("queueMeta", -1, loadQueueJobsData);
    },
    nextQueuePage() {
      return changePage("queueMeta", 1, loadQueueJobsData);
    },
    async retryQueueJob({ element: el }) {
      try {
        await api(`/queue/jobs/${el.dataset.id}/retry`, { method: "POST" });
        showToast(translate("admin.toast.job_requeued", "İş yeniden kuyruğa alındı"));
        await loadQueueJobsData(Number(store.get("queueMeta")?.page || 1));
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    },
    async cancelQueueJob({ element: el }) {
      if (!confirmAction(translate("admin.confirm.queue_cancel", "Kuyruk işi #{id} iptal edilsin mi?", { id: el.dataset.id }))) return;
      try {
        await api(`/queue/jobs/${el.dataset.id}/cancel`, { method: "POST" });
        showToast(translate("admin.toast.job_cancelled", "İş iptal edildi"));
        await loadQueueJobsData(Number(store.get("queueMeta")?.page || 1));
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    },

    async changeSeriesLifecycle({ element: el }) {
      const action = el.dataset.action;
      if (!confirmAction(translate("admin.confirm.content_lifecycle", "İçerik için {action} işlemi uygulansın mı?", { action }))) return;
      try {
        await api(`/content/${el.dataset.id}/lifecycle`, {
          method: "POST",
          body: { action },
        });
        showToast(translate("admin.toast.publication_updated", "Yayın durumu güncellendi"));
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
      return changePage("seriesMeta", -1, loadSeriesData);
    },
    nextSeriesPage() {
      return changePage("seriesMeta", 1, loadSeriesData);
    },
    previousUsersPage() {
      return changePage("usersMeta", -1, loadUsersData);
    },
    nextUsersPage() {
      return changePage("usersMeta", 1, loadUsersData);
    },

    previousUserCommentsPage() {
      return changePage("userCommentsMeta", -1, (page) =>
        loadUserCommentsData(store.get("userDetailId"), page)
      );
    },
    nextUserCommentsPage() {
      return changePage("userCommentsMeta", 1, (page) =>
        loadUserCommentsData(store.get("userDetailId"), page)
      );
    },

    previousUserBlogsPage() {
      return changePage("userBlogsMeta", -1, (page) =>
        loadUserBlogsData(store.get("userDetailId"), page)
      );
    },
    nextUserBlogsPage() {
      return changePage("userBlogsMeta", 1, (page) =>
        loadUserBlogsData(store.get("userDetailId"), page)
      );
    },

    previousUserViolationsPage() {
      return changePage("userViolationsMeta", -1, (page) =>
        loadUserViolationsData(store.get("userDetailId"), page)
      );
    },
    nextUserViolationsPage() {
      return changePage("userViolationsMeta", 1, (page) =>
        loadUserViolationsData(store.get("userDetailId"), page)
      );
    },
    previousUserWalletPage() {
      return changePage("userWalletMeta", -1, (page) =>
        loadUserWalletPage(store.get("userWalletId"), page)
      );
    },
    nextUserWalletPage() {
      return changePage("userWalletMeta", 1, (page) =>
        loadUserWalletPage(store.get("userWalletId"), page)
      );
    },
    previousBlogsPage() {
      return changePage("blogsMeta", -1, loadBlogsData);
    },
    nextBlogsPage() {
      return changePage("blogsMeta", 1, loadBlogsData);
    },
    filterLikers() {
      const target = store.get("likersTarget") || {};
      if (!target.targetType || !target.targetId) return;
      scheduleReload(
        "likers",
        () => loadLikersPage(target.targetType, target.targetId, 1),
      );
    },
    previousLikersPage() {
      const target = store.get("likersTarget") || {};
      if (!target.targetType || !target.targetId) return;
      return changePage("likersMeta", -1, (page) =>
        loadLikersPage(target.targetType, target.targetId, page)
      );
    },
    nextLikersPage() {
      const target = store.get("likersTarget") || {};
      if (!target.targetType || !target.targetId) return;
      return changePage("likersMeta", 1, (page) =>
        loadLikersPage(target.targetType, target.targetId, page)
      );
    },
    previousCommentsPage() {
      return changePage("commentsMeta", -1, loadCommentsData);
    },
    nextCommentsPage() {
      return changePage("commentsMeta", 1, loadCommentsData);
    },
    filterReports() {
      loadReportsData(1);
    },
    previousReportsPage() {
      return changePage("reportsMeta", -1, loadReportsData);
    },
    nextReportsPage() {
      return changePage("reportsMeta", 1, loadReportsData);
    },

    filterFinance() {
      scheduleReload("finance", () => loadFinanceData(1));
    },
    previousFinancePage() {
      return changePage("financeMeta", -1, loadFinanceData);
    },
    nextFinancePage() {
      return changePage("financeMeta", 1, loadFinanceData);
    },
    async refundFinanceTransaction({ element: el }) {
      const reason = promptValue(translate("admin.prompt.refund_reason", "İade nedeni:"));
      if (!reason?.trim()) return;
      if (
        !confirmAction(
          translate(
            "admin.confirm.refund",
            "İşlem #{id} için coin iadesi yapılsın ve ilgili erişim geri alınsın mı?",
            { id: el.dataset.id },
          ),
        )
      ) {
        return;
      }
      try {
        await api(`/finance/transactions/${el.dataset.id}/refund`, {
          method: "POST",
          body: { reason: reason.trim() },
        });
        showToast(translate("admin.toast.refund_done", "İade işlemi tamamlandı"));
        await loadFinanceData(Number(store.get("financeMeta")?.page || 1));
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    },

    async deleteBlog({ element: el }) {
      const id = el.dataset.id;
      if (!confirmAction(translate("admin.confirm.blog_delete", "Bu blog yazısını silmek istediğinize emin misiniz?"))) {
        return;
      }
      try {
        await api(`/blogs/${id}`, { method: "DELETE" });
        showToast(translate("admin.toast.blog_deleted", "Blog yazısı silindi"));
        loadBlogsData();
      } catch (err) {
        if (err?.name === "AbortError") return;
        showToast(err.message, "danger");
      }
    },

    async approveBlog({ element: el }) {
      try {
        await api(`/blogs/${el.dataset.id}/approve`, { method: "POST" });
        showToast(translate("admin.toast.blog_approved", "Blog yazısı onaylandı"));
        loadBlogsData();
      } catch (err) {
        if (err?.name === "AbortError") return;
        showToast(err.message, "danger");
      }
    },

    async hideBlog({ element: el }) {
      if (!confirmAction(translate("admin.confirm.blog_hide", "Bu blog yazısını gizlemek istediğinize emin misiniz?"))) {
        return;
      }
      try {
        await api(`/blogs/${el.dataset.id}/hide`, { method: "POST" });
        showToast(translate("admin.toast.blog_hidden", "Blog yazısı gizlendi"));
        loadBlogsData();
      } catch (err) {
        if (err?.name === "AbortError") return;
        showToast(err.message, "danger");
      }
    },

    async moderateComment({ element: el }) {
      try {
        await api(`/comments/${el.dataset.id}/moderation`, {
          method: "PUT",
          body: { status: el.dataset.status },
        });
        showToast(
          el.dataset.status === "approved"
            ? translate("admin.toast.comment_approved", "Yorum onaylandı")
            : translate("admin.toast.comment_hidden", "Yorum gizlendi"),
        );
        loadCommentsData(Number(store.get("commentsMeta")?.page || 1));
      } catch (err) {
        if (err?.name === "AbortError") return;
        showToast(err.message, "danger");
      }
    },

    async deleteUpload({ element: el }) {
      if (!confirmAction(translate("admin.confirm.upload_delete", "Bu yükleme kaydı ve bağlı dosya silinsin mi?"))) return;
      try {
        await api(`/uploads/${el.dataset.id}`, { method: "DELETE" });
        showToast(translate("admin.toast.upload_deleted", "Yükleme silindi"));
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
      return changePage("uploadsMeta", -1, loadUploadsData);
    },
    nextUploadsPage() {
      return changePage("uploadsMeta", 1, loadUploadsData);
    },
    toggleAllUploads({ element: el }) {
      document.querySelectorAll("[data-upload-select]").forEach((input) => {
        input.checked = el.checked;
      });
    },
    async bulkDeleteUploads() {
      const ids = Array.from(
        document.querySelectorAll("[data-upload-select]:checked"),
      ).map((input) => Number(input.value));
      if (ids.length === 0) {
        return showToast(translate("admin.validation.select_upload", "Önce en az bir dosya seçin"), "danger");
      }
      if (
        !confirmAction(
          translate(
            "admin.confirm.upload_bulk_delete",
            "{count} yükleme kaydı ve fiziksel dosyaları silinsin mi?",
            { count: ids.length },
          ),
        )
      ) {
        return;
      }
      try {
        const response = await api("/uploads/bulk-delete", {
          method: "POST",
          body: { ids },
        });
        showToast(translate("admin.toast.uploads_deleted", "{count} dosya silindi", {
          count: Number(response?.data?.deleted || 0),
        }));
        await loadUploadsData(1);
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    },
    async optimizeUpload({ element: el }) {
      try {
        const response = await api(`/uploads/${el.dataset.id}/optimize`, {
          method: "POST",
        });
        showToast(translate("admin.toast.upload_optimized", "{bytes} bayt kazanıldı", {
          bytes: Number(response?.data?.saved_bytes || 0),
        }));
        await loadUploadsData(Number(store.get("uploadsMeta")?.page || 1));
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    },
  };
  return Object.freeze(handlers);
}
