import { mapDashboardData } from "./dashboard-model.js";

/** Dashboard data orchestration and refresh lifecycle. */
export function createDashboardDataController({
  store,
  api,
  getPageEpoch,
  assertCurrentPage,
  renderDashboardTables,
  renderDashboardCharts,
  updateDashboardStateView,
  destroyDashboardCharts,
  registerPageCleanup,
  translate = (_key, fallback) => fallback,
  formatNumber,
  documentRef = globalThis.document,
} = {}) {
  const document = documentRef;
  const runtimeWindow = document?.defaultView || globalThis.window;
  let dashboardRefreshInFlight = null;
  let dashboardRefreshSequence = 0;
  let dashboardAutoRefreshTimer = null;
  let cleanupRegisteredEpoch = null;
  function ensureDashboardRefresh(epoch) {
    if (cleanupRegisteredEpoch === epoch) return;
    cleanupRegisteredEpoch = epoch;
    if (!dashboardAutoRefreshTimer && runtimeWindow?.setInterval) {
      dashboardAutoRefreshTimer = runtimeWindow.setInterval(() => {
        if (
          store.get("currentRoute") === "dashboard" &&
          document?.visibilityState === "visible"
        ) {
          void loadDashboardData();
        }
      }, 60000);
    }
    registerPageCleanup?.(() => {
      destroyDashboardCharts();
      if (dashboardAutoRefreshTimer) {
        runtimeWindow?.clearInterval?.(dashboardAutoRefreshTimer);
        dashboardAutoRefreshTimer = null;
      }
      cleanupRegisteredEpoch = null;
    });
  }
  async function loadDashboardData() {
    const requestId = ++dashboardRefreshSequence;
    const requestEpoch = getPageEpoch();
    const days = Math.max(
      1,
      Math.min(90, Number(store.get("dashboardPeriodDays") || 30)),
    );
    const requestKey = `${requestEpoch}:${days}`;
    if (dashboardRefreshInFlight?.key === requestKey) return;
    // Install the 60-second refresh lifecycle before the first request. A
    // transient initial failure must still retry automatically, and cleanup
    // must be registered even when the request rejects.
    ensureDashboardRefresh(requestEpoch);
    dashboardRefreshInFlight = { id: requestId, key: requestKey };
    store.batch(() => {
      store.set("dashboardLoading", true);
      store.set("dashboardError", "");
      store.set("dashboardPeriodDays", days);
    });
    updateDashboardStateView();
    try {
      const data = await api(`/dashboard-data?days=${days}&limit=10`);
      assertCurrentPage(requestEpoch);
      if (dashboardRefreshInFlight?.id !== requestId) return;
      if (!data?.data || typeof data.data !== "object") {
        throw new Error(
          translate(
            "admin.dashboard.invalid_response",
            "Dashboard yanıtı beklenen veri yapısını içermiyor.",
          ),
        );
      }
      const values = mapDashboardData(data.data, {
        days,
        translate,
        formatNumber,
      });
      store.batch(() => {
        for (const [key, value] of Object.entries(values)) {
          store.set(key, value);
        }
      });
      renderDashboardTables();
      renderDashboardCharts();
      updateDashboardStateView();
      return true;
    } catch (e) {
      if (e?.name === "AbortError") return;
      if (dashboardRefreshInFlight?.id !== requestId) return;
      console.error("Dashboard load error:", e);
      store.batch(() => {
        store.set("dashboardLoading", false);
        store.set(
          "dashboardError",
          e?.message ||
            translate("admin.dashboard.load_failed", "Dashboard verileri yüklenemedi."),
        );
      });
      updateDashboardStateView();
      return false;
    } finally {
      if (dashboardRefreshInFlight?.id === requestId) {
        dashboardRefreshInFlight = null;
      }
    }
  }

  return Object.freeze({ loadDashboardData });
}
