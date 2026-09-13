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
    loadSeries() {
      loadSeriesData(Number(store.get("seriesMeta")?.page || 1));
      showToast(translate("admin.toast.series_refreshed", "İçerik listesi yenilendi"));
    },
    loadUsers() {
      loadUsersData(Number(store.get("usersMeta")?.page || 1));
      showToast(translate("admin.toast.users_refreshed", "Kullanıcı listesi yenilendi"));
    },
    loadBlogs() {
      loadBlogsData(Number(store.get("blogsMeta")?.page || 1));
      showToast(translate("admin.toast.blogs_refreshed", "Blog listesi yenilendi"));
    },
    loadComments() {
      loadCommentsData(Number(store.get("commentsMeta")?.page || 1));
      showToast(translate("admin.toast.comments_refreshed", "Yorum listesi yenilendi"));
    },
    loadReports() {
      loadReportsData(Number(store.get("reportsMeta")?.page || 1));
    },
    loadLogs() {
      loadLogsData(Number(store.get("logsMeta")?.page || 1));
      showToast(translate("admin.toast.logs_refreshed", "Loglar yenilendi"));
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
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadLogsData(Number(meta.page) + 1);
      }
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
    loadUploads() {
      loadUploadsData();
      showToast(translate("admin.toast.uploads_refreshed", "Yüklemeler yenilendi"));
    },
    async loadQueueJobs() {
      await loadQueueJobsData();
      showToast(translate("admin.toast.queue_refreshed", "Kuyruk yenilendi"));
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
      const page = Number(store.get("queueMeta")?.page || 1);
      if (page > 1) loadQueueJobsData(page - 1);
    },
    nextQueuePage() {
      const meta = store.get("queueMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadQueueJobsData(Number(meta.page) + 1);
      }
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
      const page = Number(store.get("seriesMeta")?.page || 1);
      if (page > 1) loadSeriesData(page - 1);
    },
    nextSeriesPage() {
      const meta = store.get("seriesMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadSeriesData(Number(meta.page) + 1);
      }
    },
    previousUsersPage() {
      const page = Number(store.get("usersMeta")?.page || 1);
      if (page > 1) loadUsersData(page - 1);
    },
    nextUsersPage() {
      const meta = store.get("usersMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadUsersData(Number(meta.page) + 1);
      }
    },

    previousUserCommentsPage() {
      const page = Number(store.get("userCommentsMeta")?.page || 1);
      if (page > 1) loadUserCommentsData(store.get("userDetailId"), page - 1);
    },
    nextUserCommentsPage() {
      const meta = store.get("userCommentsMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadUserCommentsData(store.get("userDetailId"), Number(meta.page) + 1);
      }
    },

    previousUserBlogsPage() {
      const page = Number(store.get("userBlogsMeta")?.page || 1);
      if (page > 1) loadUserBlogsData(store.get("userDetailId"), page - 1);
    },
    nextUserBlogsPage() {
      const meta = store.get("userBlogsMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadUserBlogsData(store.get("userDetailId"), Number(meta.page) + 1);
      }
    },

    previousUserViolationsPage() {
      const page = Number(store.get("userViolationsMeta")?.page || 1);
      if (page > 1) loadUserViolationsData(store.get("userDetailId"), page - 1);
    },
    nextUserViolationsPage() {
      const meta = store.get("userViolationsMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadUserViolationsData(
          store.get("userDetailId"),
          Number(meta.page) + 1,
        );
      }
    },
    previousUserWalletPage() {
      const page = Number(store.get("userWalletMeta")?.page || 1);
      if (page > 1) loadUserWalletPage(store.get("userWalletId"), page - 1);
    },
    nextUserWalletPage() {
      const meta = store.get("userWalletMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadUserWalletPage(store.get("userWalletId"), Number(meta.page) + 1);
      }
    },
    previousBlogsPage() {
      const page = Number(store.get("blogsMeta")?.page || 1);
      if (page > 1) loadBlogsData(page - 1);
    },
    nextBlogsPage() {
      const meta = store.get("blogsMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadBlogsData(Number(meta.page) + 1);
      }
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
      const page = Number(store.get("likersMeta")?.page || 1);
      if (page > 1 && target.targetType && target.targetId) {
        loadLikersPage(target.targetType, target.targetId, page - 1);
      }
    },
    nextLikersPage() {
      const target = store.get("likersTarget") || {};
      const meta = store.get("likersMeta") || {};
      if (
        Number(meta.page) < Number(meta.total_pages) &&
        target.targetType &&
        target.targetId
      ) {
        loadLikersPage(
          target.targetType,
          target.targetId,
          Number(meta.page) + 1,
        );
      }
    },
    previousCommentsPage() {
      const page = Number(store.get("commentsMeta")?.page || 1);
      if (page > 1) loadCommentsData(page - 1);
    },
    nextCommentsPage() {
      const meta = store.get("commentsMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadCommentsData(Number(meta.page) + 1);
      }
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
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadFinanceData(Number(meta.page) + 1);
      }
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
      const page = Number(store.get("uploadsMeta")?.page || 1);
      if (page > 1) loadUploadsData(page - 1);
    },
    nextUploadsPage() {
      const meta = store.get("uploadsMeta") || {};
      if (Number(meta.page) < Number(meta.total_pages)) {
        loadUploadsData(Number(meta.page) + 1);
      }
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
