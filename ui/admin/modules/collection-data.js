/** Collection data loaders for panel list pages. */
export function createCollectionDataController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch,
  assertCurrentPage,
  seriesRequestGate,
  requestGates = {},
  renderSeriesTable,
  renderBlogsTable,
  renderCommentsTable,
  renderReportsTable,
  renderPackagesTable,
  renderFinanceTable,
  renderLogsTable,
  renderUploadsTable,
  setTableRows,
  showToast,
  targetTypeLabel = (type) => String(type || "-") || "-",
  translate = (_key, fallback) => fallback,
  documentRef = globalThis.document,
} = {}) {
  const document = documentRef;
  const showTableError = (id, colspan, error) => {
    setTableRows?.(id, "", [], colspan, {
      error_message: error?.message ||
        translate("admin.state.data_failed", "Veriler yüklenemedi."),
    });
  };
  const showTableLoading = (id, colspan) => {
    setTableRows?.(id, "", [], colspan, { loading: true });
  };
  async function loadSeriesData(page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = seriesRequestGate.begin();
    showTableLoading("panel-series-list", 6);
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "20",
      });
      const values = {
        q: document.getElementById("panel-series-search")?.value || "",
        status: document.getElementById("panel-series-status")?.value || "",
        type: document.getElementById("panel-series-type")?.value || "",
        lifecycle: document.getElementById("panel-series-lifecycle")?.value ||
          "",
        sort: document.getElementById("panel-series-sort")?.value || "newest",
      };
      Object.entries(values).forEach(([key, value]) => {
        if (value) params.set(key, value);
      });
      const res = await api(`/series?${params.toString()}`);
      assertCurrentPage(requestEpoch);
      if (!seriesRequestGate.isCurrent(requestToken)) return;
      const items = responseItems(res);
      store.batch(() => {
        store.set("seriesList", items);
        store.set("seriesMeta", responseMeta(res));
      });
      renderSeriesTable();
    } catch (e) {
      if (e?.name === "AbortError") return;
      if (!seriesRequestGate.isCurrent(requestToken)) return;
      showTableError("panel-series-list", 6, e);
      showToast(
        translate("admin.error.series_load", "İçerikler yüklenemedi: {message}", { message: e.message }),
        "danger",
      );
    }
  }

  async function loadBlogsData(page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.blogs?.begin();
    showTableLoading("panel-blogs-list", 5);
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "20",
      });
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
      if (requestGates.blogs && !requestGates.blogs.isCurrent(requestToken)) return;
      const labels = {
        draft: translate("admin.status.draft", "Taslak"),
        pending: translate("admin.status.pending", "Bekliyor"),
        published: translate("admin.status.published", "Yayınlandı"),
        rejected: translate("admin.status.rejected", "Reddedildi"),
        hidden: translate("admin.status.hidden", "Gizli"),
      };
      store.batch(() => {
        store.set(
          "blogsList",
          responseItems(res).map((blog) => {
            const approved = Number(blog.approved) === 1;
            return {
              ...blog,
              status_label: labels[blog.status] ||
                (approved
                  ? translate("admin.status.approved", "Onaylı")
                  : translate(
                    "admin.status.approved_or_hidden",
                    "Bekliyor / Gizli",
                  )),
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
      if (requestGates.blogs && !requestGates.blogs.isCurrent(requestToken)) return;
      showTableError("panel-blogs-list", 5, e);
      showToast(
        translate("admin.error.blog_load", "Bloglar yüklenemedi: {message}", { message: e.message }),
        "danger",
      );
    }
  }

  async function loadCommentsData(page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.comments?.begin();
    showTableLoading("panel-comments-list", 6);
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "20",
      });
      const values = {
        q: document.getElementById("panel-comments-search")?.value || "",
        target_type: document.getElementById("panel-comments-target")?.value ||
          "",
        status: document.getElementById("panel-comments-status")?.value || "",
        sort: document.getElementById("panel-comments-sort")?.value || "newest",
      };
      Object.entries(values).forEach(([key, value]) => {
        if (value) params.set(key, value);
      });
      const res = await api(`/comments?${params.toString()}`);
      assertCurrentPage(requestEpoch);
      if (requestGates.comments && !requestGates.comments.isCurrent(requestToken)) return;
      store.batch(() => {
        store.set(
          "commentsList",
          responseItems(res).map((comment) => ({
            ...comment,
            context_label: comment.blog_title || comment.content_title
              ? `${targetTypeLabel(comment.target_type, translate)}: ${
                comment.blog_title || comment.content_title
              }${comment.chapter_number ? ` #${comment.chapter_number}` : ""}`
              : `${targetTypeLabel(comment.target_type, translate)}: ${
                comment.target_id || "-"
              }`,
          })),
        );
        store.set("commentsMeta", responseMeta(res));
      });
      renderCommentsTable();
    } catch (e) {
      if (e?.name === "AbortError") return;
      if (requestGates.comments && !requestGates.comments.isCurrent(requestToken)) return;
      showTableError("panel-comments-list", 6, e);
      showToast(
        translate("admin.error.comment_load", "Yorumlar yüklenemedi: {message}", { message: e.message }),
        "danger",
      );
    }
  }

  async function loadReportsData(page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.reports?.begin();
    showTableLoading("panel-reports-list", 6);
    try {
      const params = new URLSearchParams({
        page: String(Math.max(1, Number(page) || 1)),
        per_page: "20",
      });
      const status = document.getElementById("panel-report-status")?.value ||
        "";
      const targetType =
        document.getElementById("panel-report-target")?.value ||
        "";
      if (status) params.set("status", status);
      if (targetType) params.set("target_type", targetType);
      const response = await api(`/reports?${params.toString()}`);
      assertCurrentPage(requestEpoch);
      if (requestGates.reports && !requestGates.reports.isCurrent(requestToken)) return;
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
      if (requestGates.reports && !requestGates.reports.isCurrent(requestToken)) return;
      showTableError("panel-reports-list", 6, error);
      showToast(
        translate("admin.error.report_load", "Raporlar yüklenemedi: {message}", { message: error.message }),
        "danger",
      );
    }
  }

  async function loadPackagesData() {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.packages?.begin();
    showTableLoading("panel-packages-list", 5);
    try {
      const res = await api("/shop/packages");
      assertCurrentPage(requestEpoch);
      if (requestGates.packages && !requestGates.packages.isCurrent(requestToken)) return;
      store.set(
        "packagesList",
        responseItems(res).map((item) => ({
          ...item,
          status_label: Number(item.is_active) === 1
            ? translate("admin.status.active", "Aktif")
            : translate("admin.status.inactive", "Pasif"),
          status_badge: Number(item.is_active) === 1
            ? "bg-success-subtle text-success"
            : "bg-secondary-subtle text-secondary",
        })),
      );
      renderPackagesTable();
    } catch (e) {
      if (e?.name === "AbortError") return;
      if (requestGates.packages && !requestGates.packages.isCurrent(requestToken)) return;
      showTableError("panel-packages-list", 5, e);
      showToast(
        translate("admin.error.package_load", "Paketler yüklenemedi: {message}", { message: e.message }),
        "danger",
      );
    }
  }

  async function loadFinanceData(page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.finance?.begin();
    showTableLoading("panel-finance-list", 7);
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "25",
      });
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
      if (requestGates.finance && !requestGates.finance.isCurrent(requestToken)) return;
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
      if (requestGates.finance && !requestGates.finance.isCurrent(requestToken)) return;
      showTableError("panel-finance-list", 7, error);
      showToast(
        translate("admin.error.finance_load", "Finans hareketleri alınamadı: {message}", { message: error.message }),
        "danger",
      );
    }
  }

  async function loadLogsData(page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.logs?.begin();
    showTableLoading("panel-log-body", 9);
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "50",
      });
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
      if (requestGates.logs && !requestGates.logs.isCurrent(requestToken)) return;
      store.batch(() => {
        store.set("logsList", responseItems(res));
        store.set("logsMeta", responseMeta(res));
      });
      renderLogsTable();
    } catch (e) {
      if (e?.name === "AbortError") return;
      if (requestGates.logs && !requestGates.logs.isCurrent(requestToken)) return;
      showTableError("panel-log-body", 9, e);
      showToast(
        translate("admin.error.logs_load", "Loglar yüklenemedi: {message}", { message: e.message }),
        "danger",
      );
    }
  }

  async function loadUploadsData(page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGates.uploads?.begin();
    showTableLoading("panel-uploads-list", 7);
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "30",
      });
      const query = document.getElementById("panel-uploads-search")?.value ||
        "";
      const mime = document.getElementById("panel-uploads-mime")?.value || "";
      const orphans =
        document.getElementById("panel-uploads-orphans")?.checked ||
        false;
      if (query) params.set("q", query);
      if (mime) params.set("mime", mime);
      if (orphans) params.set("orphans", "1");
      const response = await api(`/uploads?${params.toString()}`);
      assertCurrentPage(requestEpoch);
      if (requestGates.uploads && !requestGates.uploads.isCurrent(requestToken)) return;
      store.batch(() => {
        store.set(
          "uploadsList",
          responseItems(response).map((item) => ({
            ...item,
            original_name: item.original_name || item.file_path || "Dosya",
            username: item.username || item.user_id || "-",
            size_label: `${
              Math.max(0, Number(item.file_size || 0) / 1024).toFixed(1)
            } KB`,
          })),
        );
        store.set("uploadsMeta", responseMeta(response));
        store.set("uploadsStats", response?.meta?.stats || {});
      });
      renderUploadsTable();
    } catch (error) {
      if (error?.name === "AbortError") return;
      if (requestGates.uploads && !requestGates.uploads.isCurrent(requestToken)) return;
      showTableError("panel-uploads-list", 7, error);
      showToast(
        translate("admin.error.upload_load", "Yüklemeler alınamadı: {message}", { message: error.message }),
        "danger",
      );
    }
  }

  return Object.freeze({
    loadSeriesData,
    loadBlogsData,
    loadCommentsData,
    loadReportsData,
    loadPackagesData,
    loadFinanceData,
    loadLogsData,
    loadUploadsData,
  });
}
