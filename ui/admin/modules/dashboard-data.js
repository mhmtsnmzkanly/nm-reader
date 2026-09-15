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
  hasPermission = () => false,
  panelNavigate,
} = {}) {
  const document = documentRef;
  const runtimeWindow = document?.defaultView || globalThis.window;
  let dashboardRefreshInFlight = null;
  let dashboardRefreshSequence = 0;
  let dashboardAutoRefreshTimer = null;
  let cleanupRegisteredEpoch = null;
  let queueAutoRunTriggered = false;
  let queueErrorModalDismissed = false;

  function isSessionFlagActive(key) {
    try {
      return runtimeWindow?.sessionStorage?.getItem(key) === "1";
    } catch {
      return false;
    }
  }

  function setSessionFlag(key) {
    try {
      runtimeWindow?.sessionStorage?.setItem(key, "1");
    } catch {
      // Ignore storage access errors.
    }
  }

  function showQueueErrorModal(failedCount) {
    const modalEl = document?.getElementById("panel-queue-error-modal");
    if (!modalEl) return;
    const messageEl = document?.getElementById("panel-queue-error-modal-message");
    if (messageEl) {
      messageEl.textContent = translate(
        "admin.queue.error_modal_message",
        "{count} Hatalı Adet Queue işlemi var, lütfen kuyruğu inceleyin.",
        { count: failedCount },
      );
    }
    const dismissModal = () => {
      queueErrorModalDismissed = true;
      setSessionFlag("nm_queue_error_modal_dismissed");
      if (globalThis.bootstrap?.Modal) {
        const instance = globalThis.bootstrap.Modal.getInstance(modalEl);
        instance?.hide();
      } else {
        modalEl.classList.remove("show");
        modalEl.style.display = "none";
      }
    };

    const inspectBtn = document?.getElementById("panel-queue-error-modal-inspect");
    if (inspectBtn) {
      inspectBtn.onclick = () => {
        dismissModal();
        panelNavigate?.("/panel/ops");
      };
    }

    const closeBtn = document?.getElementById("panel-queue-error-modal-close");
    if (closeBtn) {
      closeBtn.onclick = () => {
        dismissModal();
      };
    }

    modalEl.addEventListener?.("hidden.bs.modal", () => {
      queueErrorModalDismissed = true;
      setSessionFlag("nm_queue_error_modal_dismissed");
    }, { once: true });

    if (globalThis.bootstrap?.Modal) {
      const modal = globalThis.bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    } else {
      modalEl.classList.add("show");
      modalEl.style.display = "block";
    }
  }
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
      if (hasPermission("admin.jobs.run")) {
        const kpis = data.data?.overview?.kpis || {};
        const pendingCount = Number(kpis.queue_pending_total || 0);
        const failedCount = Number(kpis.queue_failed_total || 0);

        if (
          pendingCount > 0 &&
          !queueAutoRunTriggered &&
          !isSessionFlagActive("nm_queue_auto_run_triggered")
        ) {
          queueAutoRunTriggered = true;
          setSessionFlag("nm_queue_auto_run_triggered");
          void api("/queue/run-once", { method: "POST", detached: true }).catch(() => {});
        }

        if (
          failedCount > 0 &&
          !queueErrorModalDismissed &&
          !isSessionFlagActive("nm_queue_error_modal_dismissed")
        ) {
          queueErrorModalDismissed = true;
          setSessionFlag("nm_queue_error_modal_dismissed");
          showQueueErrorModal(failedCount);
        }
      }
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
