/** Pure API-to-view mapping. Keys are existing Lime store paths. */
export function mapDashboardData(payload, {
  days = 30,
  translate = (_key, fallback) => fallback,
  formatNumber: formatCount = (value) => String(Number(value || 0)),
} = {}) {
  const values = {};
  const set = (key, value) => { values[key] = value; };
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
  set(
    "overview.total_users",
    formatCount(overviewData.kpis?.users_total),
  );
  set(
    "overview.total_contents",
    formatCount(overviewData.kpis?.contents_total),
  );
  set(
    "overview.total_chapters",
    formatCount(overviewData.kpis?.chapters_total),
  );
  set(
    "overview.blogs_total",
    formatCount(overviewData.kpis?.blogs_total),
  );
  set(
    "overview.blogs_pending",
    formatCount(overviewData.kpis?.blogs_pending_total),
  );
  set(
    "overview.blogs_closed",
    formatCount(overviewData.kpis?.blogs_closed_total),
  );
  set(
    "overview.reports_total",
    formatCount(overviewData.kpis?.reports_total),
  );
  set(
    "overview.reports_pending",
    formatCount(overviewData.kpis?.reports_pending_total),
  );
  set(
    "overview.reports_closed",
    formatCount(overviewData.kpis?.reports_closed_total),
  );
  set(
    "overview.queue_pending",
    formatCount(overviewData.kpis?.queue_pending_total),
  );
  set(
    "overview.queue_failed",
    formatCount(
      overviewData.kpis?.queue_failed_unseen_total ??
        overviewData.kpis?.queue_failed_total,
    ),
  );
  set("overview.funnel", metrics.funnel || {});
  set("topContents", metrics.top_contents_7d || []);
  set("analytics.visits_daily", formatCount(visits.daily));
  set("analytics.visits_weekly", formatCount(visits.weekly));
  set("analytics.visits_monthly", formatCount(visits.monthly));
  set(
    "analytics.unique_visitors_daily",
    formatCount(visits.unique_daily),
  );
  set(
    "analytics.unique_visitors_weekly",
    formatCount(visits.unique_weekly),
  );
  set(
    "analytics.unique_visitors_monthly",
    formatCount(visits.unique_monthly),
  );
  set(
    "analytics.home_to_content",
    `${metrics.funnel?.home_to_content_pct || 0}%`,
  );
  set(
    "analytics.content_to_chapter",
    `${metrics.funnel?.content_to_chapter_pct || 0}%`,
  );
  set(
    "analytics.error_rate",
    `${metrics.performance_slo?.server_error_rate_pct_24h || 0}%`,
  );
  set(
    "analytics.p95",
    `${metrics.performance_slo?.p95_duration_ms_24h || 0} ms`,
  );
  set(
    "analytics.search_total",
    formatCount(metrics.retention_search?.search_total_7d),
  );
  set(
    "analytics.zero_result_pct",
    `${metrics.retention_search?.zero_result_pct_7d || 0}%`,
  );
  set(
    "analytics.d1_retention",
    `${metrics.retention_search?.d1_retention_pct || 0}%`,
  );
  set(
    "analytics.new_users",
    formatCount(metrics.retention_search?.new_users_7d),
  );
  set(
    "analytics.d1_eligible_users",
    formatCount(metrics.retention_search?.d1_eligible_users_7d),
  );
  set(
    "analytics.d7_retention",
    `${metrics.retention_search?.d7_retention_pct || 0}%`,
  );
  set(
    "analytics.d7_eligible_users",
    formatCount(metrics.retention_search?.d7_eligible_users_30d),
  );
  set(
    "analytics.total_coins",
    formatCount(money.total_coins_spent),
  );
  set(
    "analytics.total_unlocks",
    formatCount(money.total_unlocks),
  );
  set("analytics.blog_total", formatCount(blogSummary.total));
  set(
    "analytics.blog_visible",
    formatCount(blogSummary.visible_total),
  );
  set(
    "analytics.blog_hidden",
    formatCount(blogSummary.hidden_total),
  );
  set(
    "analytics.blog_deleted",
    formatCount(blogSummary.deleted_total),
  );
  set(
    "analytics.blog_created",
    formatCount(blogSummary.created_last_days),
  );
  set(
    "analytics.blog_approved",
    formatCount(blogSummary.approved_last_days),
  );
  set("dashboardGenres", views.series_genres || []);
  set("dashboardTags", views.series_tags || []);
  set("dashboardReputation", insightData.reputation || []);
  set("dashboardTypes", views.types || []);
  set("dashboardChapters", views.chapters || []);
  set(
    "dashboardBlogAuthors",
    insightData.blogs?.top_authors || [],
  );
  set("dashboardBlogDaily", Array.from(blogDaily.values()));
  set("dashboardTrafficTrend", visits.trend || []);
  set("dashboardRevenueTrend", money.daily_trend || []);
  set("monetizationSeries", money.top_series || []);
  set(
    "zeroResultSearches",
    searchData.zero_result_searches || [],
  );
  set("dashboardGeneratedAt", payload.meta?.generated_at || "");
  set(
    "dashboardPeriodLabel",
    days === 1
      ? translate("admin.period.hours_24", "24 saat")
      : translate("admin.period.days", "{days} gün", { days }),
  );
  set("dashboardLoading", false);
  set("dashboardError", "");
  return values;
}
