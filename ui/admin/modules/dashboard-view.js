/** Dashboard presentation adapter: tables, charts and loading state. */
export function createDashboardView({
  store,
  documentRef = globalThis.document,
  chartManager,
  setTableRows,
  safeLocalPath,
  translate = (_key, fallback) => fallback,
  formatDateTime = (value) => String(value || "-"),
} = {}) {
  const document = documentRef;
  const window = documentRef?.defaultView || globalThis.window || {};
  const destroyDashboardCharts = chartManager?.destroy || (() => {});
  const createDashboardChart = chartManager?.create || (() => false);
  const setDashboardChartState = chartManager?.setState || (() => false);

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
        detail_url: item.id
          ? safeLocalPath(
            `/panel/series/${encodeURIComponent(String(item.id))}/preview`,
          )
          : "#",
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
    setTableRows(
      "panel-dashboard-blog-daily",
      "panel-rows-dashboard-blog-daily",
      (store.get("dashboardBlogDaily") || []).slice().sort((a, b) =>
        String(b.day).localeCompare(String(a.day))
      ),
      3,
    );
  }
  function chartTheme() {
    const styles = getComputedStyle(document.documentElement);
    return {
      text: styles.getPropertyValue("--bs-body-color").trim() || "#adb5bd",
      grid: styles.getPropertyValue("--bs-border-color").trim() || "#495057",
    };
  }

  function renderDashboardCharts() {
    destroyDashboardCharts();
    const ChartCtor = window.Chart;
    if (typeof ChartCtor !== "function") {
      [
        "panel-dashboard-traffic-chart",
        "panel-dashboard-funnel-chart",
        "panel-dashboard-blog-chart",
        "panel-dashboard-revenue-chart",
      ].forEach((id) =>
        setDashboardChartState(
          id,
          false,
          translate("admin.dashboard.chart_library_missing", "Grafik kütüphanesi yüklenemedi."),
        )
      );
      document.querySelectorAll("[data-chart-fallback]").forEach((node) => {
        node.textContent =
          translate(
            "admin.dashboard.charts_unavailable",
            "Grafikler yüklenemedi. Tablo verileri kullanılabilir.",
          );
      });
      return;
    }

    const theme = chartTheme();
    const trend = store.get("dashboardTrafficTrend") || [];
    if (
      setDashboardChartState("panel-dashboard-traffic-chart", trend.length > 0)
    ) {
      createDashboardChart("panel-dashboard-traffic-chart", {
        type: "line",
        data: {
          labels: trend.map((item) => String(item.day || "").slice(5)),
          datasets: [
            {
              label: translate("admin.dashboard.views", "Görüntülenme"),
              data: trend.map((item) => Number(item.views || 0)),
              borderColor: "#0d6efd",
              backgroundColor: "rgba(13,110,253,.15)",
              fill: true,
              tension: 0.35,
            },
            {
              label: translate(
                "admin.dashboard.unique_visitors",
                "Benzersiz ziyaretçi",
              ),
              data: trend.map((item) => Number(item.unique_visitors || 0)),
              borderColor: "#20c997",
              backgroundColor: "transparent",
              tension: 0.35,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: "index", intersect: false },
          plugins: { legend: { labels: { color: theme.text } } },
          scales: {
            x: { ticks: { color: theme.text }, grid: { color: theme.grid } },
            y: {
              beginAtZero: true,
              ticks: { color: theme.text },
              grid: { color: theme.grid },
            },
          },
        },
      });
    }

    const overview = store.get("overview") || {};
    const funnel = overview.funnel || {};
    const funnelValues = [
      Number(String(funnel.home_to_content_pct || 0).replace("%", "")),
      Number(String(funnel.content_to_chapter_pct || 0).replace("%", "")),
    ];
    if (
      setDashboardChartState(
        "panel-dashboard-funnel-chart",
        funnelValues.some((value) => value > 0),
      )
    ) {
      createDashboardChart("panel-dashboard-funnel-chart", {
        type: "bar",
        data: {
          labels: [
            translate("admin.dashboard.home_to_content", "Ana sayfa → içerik"),
            translate("admin.dashboard.content_to_chapter", "İçerik → bölüm"),
          ],
          datasets: [{
            label: translate("admin.dashboard.conversion", "Dönüşüm %"),
            data: funnelValues,
            backgroundColor: ["#6f42c1", "#fd7e14"],
            borderRadius: 6,
          }],
        },
        options: {
          indexAxis: "y",
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: {
              beginAtZero: true,
              max: 100,
              ticks: { color: theme.text, callback: (value) => `${value}%` },
              grid: { color: theme.grid },
            },
            y: { ticks: { color: theme.text }, grid: { display: false } },
          },
        },
      });
    }

    const blogDaily = store.get("dashboardBlogDaily") || [];
    if (
      setDashboardChartState("panel-dashboard-blog-chart", blogDaily.length > 0)
    ) {
      createDashboardChart("panel-dashboard-blog-chart", {
        type: "bar",
        data: {
          labels: blogDaily.map((item) => String(item.day || "").slice(5)),
          datasets: [
            {
              label: translate("admin.dashboard.created", "Oluşturulan"),
              data: blogDaily.map((item) => Number(item.created || 0)),
              backgroundColor: "#ffc107",
            },
            {
              label: translate("admin.dashboard.approved", "Onaylanan"),
              data: blogDaily.map((item) => Number(item.approved || 0)),
              backgroundColor: "#198754",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { labels: { color: theme.text } } },
          scales: {
            x: {
              stacked: false,
              ticks: { color: theme.text },
              grid: { color: theme.grid },
            },
            y: {
              beginAtZero: true,
              ticks: { color: theme.text },
              grid: { color: theme.grid },
            },
          },
        },
      });
    }

    const revenue = store.get("dashboardRevenueTrend") || [];
    if (
      setDashboardChartState(
        "panel-dashboard-revenue-chart",
        revenue.length > 0,
      )
    ) {
      createDashboardChart("panel-dashboard-revenue-chart", {
        type: "line",
        data: {
          labels: revenue.map((item) => String(item.stat_date || "").slice(5)),
          datasets: [{
            label: translate("admin.dashboard.coins_spent", "Harcanan coin"),
            data: revenue.map((item) => Number(item.coin_total || 0)),
            borderColor: "#dc3545",
            backgroundColor: "rgba(220,53,69,.15)",
            fill: true,
            tension: 0.35,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { labels: { color: theme.text } } },
          scales: {
            x: { ticks: { color: theme.text }, grid: { color: theme.grid } },
            y: {
              beginAtZero: true,
              ticks: { color: theme.text },
              grid: { color: theme.grid },
            },
          },
        },
      });
    }
  }

  function updateDashboardStateView() {
    const loading = document.getElementById("panel-dashboard-loading");
    const error = document.getElementById("panel-dashboard-error");
    const updated = document.getElementById("panel-dashboard-updated");
    const period = document.getElementById("panel-dashboard-period");
    const refresh = document.querySelector(
      '[data-on-click="refreshDashboard"]',
    );
    const isLoading = Boolean(store.get("dashboardLoading"));
    const message = String(store.get("dashboardError") || "");
    if (loading) loading.hidden = !isLoading;
    if (error) {
      error.hidden = !message;
      error.textContent = message;
    }
    if (updated) {
      const generatedAt = store.get("dashboardGeneratedAt");
      updated.textContent = generatedAt
        ? `${translate("admin.dashboard.last_update", "Son güncelleme")}: ${formatDateTime(generatedAt)}`
        : translate("admin.dashboard.no_data", "Henüz veri alınmadı");
    }
    if (period) period.value = String(store.get("dashboardPeriodDays") || 30);
    if (refresh) {
      refresh.disabled = isLoading;
      refresh.setAttribute("aria-busy", String(isLoading));
    }
  }

  return Object.freeze({
    renderDashboardTables,
    renderDashboardCharts,
    updateDashboardStateView,
  });
}
