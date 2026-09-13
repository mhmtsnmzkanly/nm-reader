/** Queue and system health data controller. */
export function createOpsDataController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch,
  assertCurrentPage,
  hasPermission = () => false,
  renderQueueTable,
  setTableRows,
  showToast,
  translate = (_key, fallback) => fallback,
  registerPageCleanup,
  documentRef = globalThis.document,
} = {}) {
  const document = documentRef;
  const runtimeWindow = document?.defaultView || globalThis.window;
  let opsAutoRefreshTimer = null;
  let opsQueueFailuresMarkedEpoch = null;
  let cleanupRegisteredEpoch = null;
  function ensureOpsRefresh(epoch) {
    if (cleanupRegisteredEpoch === epoch) return;
    cleanupRegisteredEpoch = epoch;
    if (!opsAutoRefreshTimer && runtimeWindow?.setInterval) {
      opsAutoRefreshTimer = runtimeWindow.setInterval(() => {
        if (
          store.get("currentRoute") === "ops" &&
          document?.visibilityState === "visible"
        ) {
          void loadQueueJobsData(Number(store.get("queueMeta")?.page || 1));
        }
      }, 30000);
    }
    registerPageCleanup?.(() => {
      if (opsAutoRefreshTimer) {
        runtimeWindow?.clearInterval?.(opsAutoRefreshTimer);
        opsAutoRefreshTimer = null;
      }
      opsQueueFailuresMarkedEpoch = null;
      cleanupRegisteredEpoch = null;
    });
  }
  async function loadQueueJobsData(page = 1) {
    const requestEpoch = getPageEpoch();
    // Keep operations retrying after transient queue/health failures while
    // ensuring the interval belongs to this page epoch.
    ensureOpsRefresh(requestEpoch);
    setTableRows?.("panel-queue-jobs", "", [], 9, { loading: true });
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "25",
      });
      const query = document.getElementById("panel-queue-search")?.value || "";
      const status = document.getElementById("panel-queue-status")?.value || "";
      const jobType = document.getElementById("panel-queue-job-type")?.value ||
        "";
      if (query) params.set("q", query);
      if (status) params.set("status", status);
      if (jobType) params.set("job_type", jobType);
      const canViewHealth = hasPermission("admin.health.view");
      const [queueResult, healthResult] = await Promise.allSettled([
        api(`/queue/jobs?${params.toString()}`),
        canViewHealth ? api("/system/health") : Promise.resolve(null),
      ]);
      if (queueResult.status === "rejected") throw queueResult.reason;
      const response = queueResult.value;
      const health = healthResult.status === "fulfilled"
        ? healthResult.value
        : null;
      assertCurrentPage(requestEpoch);
      if (opsQueueFailuresMarkedEpoch !== requestEpoch) {
        try {
          await api("/queue/failures/seen", { method: "POST" });
        } catch {
          // A view marker must never prevent the operations page from loading.
        }
        // The marker is an asynchronous side effect of entering this page. A
        // navigation may happen while it is in flight, so never let its
        // completion continue rendering or install a timer for a new page.
        if (requestEpoch !== getPageEpoch()) return;
        opsQueueFailuresMarkedEpoch = requestEpoch;
      }
      store.batch(() => {
        store.set("queueJobsList", responseItems(response));
        store.set("queueMeta", responseMeta(response));
        store.set("systemHealth", health?.data || {});
        store.set(
          "systemHealthError",
          healthResult.status === "rejected"
            ? /HTTP 403/.test(healthResult.reason?.message || "")
              ? translate(
                "admin.ops.health_permission",
                "Sistem sağlık kartları için admin.health.view yetkisi gerekir.",
              )
              : healthResult.reason?.message ||
                translate("admin.ops.health_failed", "Sağlık bilgisi alınamadı.")
            : "",
        );
      });
      renderQueueTable();
      const healthError = store.get("systemHealthError");
      const healthNotice = document.getElementById("panel-health-error");
      if (healthNotice) {
        healthNotice.hidden = !healthError;
        healthNotice.textContent = healthError || "";
      }
    } catch (error) {
      if (error?.name === "AbortError") return;
      setTableRows?.("panel-queue-jobs", "", [], 9, {
        error_message: error.message,
      });
      showToast(
        translate("admin.toast.queue_failed", "Kuyruk alınamadı: {message}", {
          message: error.message,
        }),
        "danger",
      );
    }
  }

  return Object.freeze({ loadQueueJobsData });
}
