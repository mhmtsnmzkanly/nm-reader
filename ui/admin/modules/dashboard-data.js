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
  formatNumber: formatCount = (value) => String(Number(value || 0)),
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
    if (dashboardRefreshInFlight?.key === requestKey) return false;
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
      if (dashboardRefreshInFlight?.id !== requestId) return false;
      if (!data?.data || typeof data.data !== "object") {
        throw new Error(
          translate(
            "admin.dashboard.invalid_response",
            "Dashboard yanıtı beklenen veri yapısını içermiyor.",
          ),
        );
      }
      {
        const payload = data.data;
        const overviewData = payload.overview || {};
        const metrics = overviewData.metrics || {};
        const insightData = payload.insights || {};
        const visits = insightData.visits || {};
        const views = insightData.views || {};
        const blogSummary = insightData.blogs?.summary || {};
        const money = payload.monetization || {};
        const searchData = payload.search || {};
        const blogDaily = new Map();
        (insightData.blogs?.daily_created || []).forEach((item) =>
          blogDaily.set(item.day, {
            day: item.day,
            created: Number(item.total || 0),
            approved: 0,
          })
        );
        (insightData.blogs?.daily_approved || []).forEach((item) => {
          const current = blogDaily.get(item.day) || {
            day: item.day,
            created: 0,
            approved: 0,
          };
          current.approved = Number(item.total || 0);
          blogDaily.set(item.day, current);
        });
        store.batch(() => {
          store.set(
            "overview.total_users",
            formatCount(overviewData.kpis?.users_total),
          );
          store.set(
            "overview.total_contents",
            formatCount(overviewData.kpis?.contents_total),
          );
          store.set(
            "overview.total_chapters",
            formatCount(overviewData.kpis?.chapters_total),
          );
          store.set(
            "overview.blogs_total",
            formatCount(overviewData.kpis?.blogs_total),
          );
          store.set(
            "overview.blogs_pending",
            formatCount(overviewData.kpis?.blogs_pending_total),
          );
          store.set(
            "overview.blogs_closed",
            formatCount(overviewData.kpis?.blogs_closed_total),
          );
          store.set(
            "overview.reports_total",
            formatCount(overviewData.kpis?.reports_total),
          );
          store.set(
            "overview.reports_pending",
            formatCount(overviewData.kpis?.reports_pending_total),
          );
          store.set(
            "overview.reports_closed",
            formatCount(overviewData.kpis?.reports_closed_total),
          );
          store.set(
            "overview.queue_pending",
            formatCount(overviewData.kpis?.queue_pending_total),
          );
          store.set(
            "overview.queue_failed",
            formatCount(
              overviewData.kpis?.queue_failed_unseen_total ??
                overviewData.kpis?.queue_failed_total,
            ),
          );
          store.set("overview.funnel", metrics.funnel || {});
          store.set("topContents", metrics.top_contents_7d || []);
          store.set("analytics.visits_daily", formatCount(visits.daily));
          store.set("analytics.visits_weekly", formatCount(visits.weekly));
          store.set("analytics.visits_monthly", formatCount(visits.monthly));
          store.set(
            "analytics.unique_visitors_daily",
            formatCount(visits.unique_daily),
          );
          store.set(
            "analytics.unique_visitors_weekly",
            formatCount(visits.unique_weekly),
          );
          store.set(
            "analytics.unique_visitors_monthly",
            formatCount(visits.unique_monthly),
          );
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
            formatCount(metrics.retention_search?.search_total_7d),
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
            formatCount(metrics.retention_search?.new_users_7d),
          );
          store.set(
            "analytics.d1_eligible_users",
            formatCount(metrics.retention_search?.d1_eligible_users_7d),
          );
          store.set(
            "analytics.d7_retention",
            `${metrics.retention_search?.d7_retention_pct || 0}%`,
          );
          store.set(
            "analytics.d7_eligible_users",
            formatCount(metrics.retention_search?.d7_eligible_users_30d),
          );
          store.set(
            "analytics.total_coins",
            formatCount(money.total_coins_spent),
          );
          store.set(
            "analytics.total_unlocks",
            formatCount(money.total_unlocks),
          );
          store.set("analytics.blog_total", formatCount(blogSummary.total));
          store.set(
            "analytics.blog_visible",
            formatCount(blogSummary.visible_total),
          );
          store.set(
            "analytics.blog_hidden",
            formatCount(blogSummary.hidden_total),
          );
          store.set(
            "analytics.blog_deleted",
            formatCount(blogSummary.deleted_total),
          );
          store.set(
            "analytics.blog_created",
            formatCount(blogSummary.created_last_days),
          );
          store.set(
            "analytics.blog_approved",
            formatCount(blogSummary.approved_last_days),
          );
          store.set("dashboardGenres", views.series_genres || []);
          store.set("dashboardTags", views.series_tags || []);
          store.set("dashboardReputation", insightData.reputation || []);
          store.set("dashboardTypes", views.types || []);
          store.set("dashboardChapters", views.chapters || []);
          store.set(
            "dashboardBlogAuthors",
            insightData.blogs?.top_authors || [],
          );
          store.set("dashboardBlogDaily", Array.from(blogDaily.values()));
          store.set("dashboardTrafficTrend", visits.trend || []);
          store.set("dashboardRevenueTrend", money.daily_trend || []);
          store.set("monetizationSeries", money.top_series || []);
          store.set(
            "zeroResultSearches",
            searchData.zero_result_searches || [],
          );
          store.set("dashboardGeneratedAt", payload.meta?.generated_at || "");
          store.set(
            "dashboardPeriodLabel",
            days === 1
              ? translate("admin.period.hours_24", "24 saat")
              : translate("admin.period.days", "{days} gün", { days }),
          );
          store.set("dashboardLoading", false);
          store.set("dashboardError", "");
        });
        renderDashboardTables();
        renderDashboardCharts();
        updateDashboardStateView();
        return true;
      }
    } catch (e) {
      if (e?.name === "AbortError") return;
      if (dashboardRefreshInFlight?.id !== requestId) return false;
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
