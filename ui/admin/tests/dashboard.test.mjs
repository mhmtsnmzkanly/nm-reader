import test from "node:test";
import assert from "node:assert/strict";
import { mapDashboardData } from "../modules/dashboard-model.js";
import { createDashboardDataController } from "../modules/dashboard-data.js";

function fixture(api, options = {}) {
  const values = new Map([
    ["currentRoute", "dashboard"],
    ["dashboardPeriodDays", 30],
  ]);
  const intervals = new Map();
  const cleanups = [];
  const renders = { tables: 0, charts: 0, state: 0, destroyed: 0 };
  const controller = createDashboardDataController({
    store: {
      get: (key) => values.get(key),
      set: (key, value) => values.set(key, value),
      batch: (callback) => callback(),
    },
    api,
    getPageEpoch: () => 1,
    assertCurrentPage: () => {},
    renderDashboardTables: () => renders.tables++,
    renderDashboardCharts: () => renders.charts++,
    updateDashboardStateView: () => renders.state++,
    destroyDashboardCharts: () => renders.destroyed++,
    registerPageCleanup: (callback) => cleanups.push(callback),
    documentRef: {
      visibilityState: "visible",
      defaultView: {
        setInterval(callback, delay) {
          assert.equal(delay, 60000);
          intervals.set(1, callback);
          return 1;
        },
        clearInterval: (id) => intervals.delete(id),
      },
    },
    ...options,
  });
  return { ...controller, values, renders, intervals, cleanups };
}

test("missing optional dashboard fields have display defaults", () => {
  const result = mapDashboardData({});
  assert.equal(result["overview.total_users"], "0");
  assert.equal(result["analytics.p95"], "0 ms");
  assert.deepEqual(result.dashboardTrafficTrend, []);
  assert.equal(result.dashboardLoading, false);
  assert.equal(result.dashboardError, "");
});

test("injected formatter, queue zero and merged blog days are preserved", () => {
  const payload = {
    overview: { kpis: {
      users_total: "1234", queue_failed_unseen_total: 0, queue_failed_total: 99,
    } },
    insights: { blogs: {
      daily_created: [{ day: "2026-09-14", total: "2" }],
      daily_approved: [
        { day: "2026-09-14", total: "1" },
        { day: "2026-09-15", total: "3" },
      ],
    } },
  };
  const before = structuredClone(payload);
  const result = mapDashboardData(payload, {
    days: 1,
    formatNumber: (value) => `count:${Number(value || 0)}`,
    translate: (key) => key,
  });
  assert.equal(result["overview.total_users"], "count:1234");
  assert.equal(result["overview.queue_failed"], "count:0");
  assert.equal(result.dashboardPeriodLabel, "admin.period.hours_24");
  assert.deepEqual(result.dashboardBlogDaily, [
    { day: "2026-09-14", created: 2, approved: 1 },
    { day: "2026-09-15", created: 0, approved: 3 },
  ]);
  assert.deepEqual(payload, before);
});

test("successful load formats counts and renders the dashboard", async () => {
  const f = fixture(async (path) => {
    assert.equal(path, "/dashboard-data?days=30&limit=10");
    return { data: { overview: { kpis: { users_total: "1234" } } } };
  }, { formatNumber: (value) => Number(value || 0).toLocaleString("tr-TR") });
  assert.equal(await f.loadDashboardData(), true);
  assert.equal(f.values.get("overview.total_users"), "1.234");
  assert.equal(f.values.get("dashboardLoading"), false);
  assert.equal(f.renders.tables, 1);
  assert.equal(f.renders.charts, 1);
  assert.equal(f.intervals.size, 1);
  f.cleanups.forEach((cleanup) => cleanup());
  assert.equal(f.intervals.size, 0);
  assert.equal(f.renders.destroyed, 1);
});

test("overlapping requests for the same period are deduplicated", async () => {
  let resolve;
  let calls = 0;
  const f = fixture(() => {
    calls++;
    return new Promise((done) => { resolve = done; });
  });
  const first = f.loadDashboardData();
  assert.equal(await f.loadDashboardData(), undefined);
  resolve({ data: {} });
  assert.equal(await first, true);
  assert.equal(calls, 1);
});

test("late responses cannot overwrite a newer selected period", async () => {
  const pending = [];
  const f = fixture(() => new Promise((resolve) => pending.push(resolve)));
  const first = f.loadDashboardData();
  f.values.set("dashboardPeriodDays", 7);
  const second = f.loadDashboardData();
  pending[1]({ data: { overview: { kpis: { users_total: 20 } } } });
  assert.equal(await second, true);
  pending[0]({ data: { overview: { kpis: { users_total: 10 } } } });
  assert.equal(await first, undefined);
  assert.equal(f.values.get("overview.total_users"), "20");
  assert.equal(f.renders.tables, 1);
});

test("invalid initial response shows an error and keeps automatic retry", async (t) => {
  t.mock.method(console, "error", () => {});
  const f = fixture(async () => ({ data: null }));
  assert.equal(await f.loadDashboardData(), false);
  assert.equal(f.values.get("dashboardLoading"), false);
  assert.match(f.values.get("dashboardError"), /Dashboard/);
  assert.equal(f.renders.tables, 0);
  assert.equal(f.intervals.size, 1);
});

test("auto-run triggers once per session when pending queue jobs exist and permission is granted", async () => {
  const apiCalls = [];
  const sessionData = new Map();
  const f = fixture(async (path, options) => {
    apiCalls.push({ path, options });
    if (path.startsWith("/dashboard-data")) {
      return {
        data: {
          overview: {
            kpis: { queue_pending_total: 5, queue_failed_total: 0 },
          },
        },
      };
    }
    return { data: {} };
  }, {
    hasPermission: (perm) => perm === "admin.jobs.run",
    documentRef: {
      visibilityState: "visible",
      defaultView: {
        setInterval: () => 1,
        clearInterval: () => {},
        sessionStorage: {
          getItem: (key) => sessionData.get(key) || null,
          setItem: (key, val) => sessionData.set(key, String(val)),
        },
      },
    },
  });

  await f.loadDashboardData();
  const queueRuns = apiCalls.filter((call) => call.path === "/queue/run-once");
  assert.equal(queueRuns.length, 1);
  assert.equal(queueRuns[0].options?.method, "POST");
  assert.equal(queueRuns[0].options?.detached, true);
  assert.equal(sessionData.get("nm_queue_auto_run_triggered"), "1");

  // Second load should not trigger again
  await f.loadDashboardData();
  const secondRuns = apiCalls.filter((call) => call.path === "/queue/run-once");
  assert.equal(secondRuns.length, 1);
});

test("queue error modal triggers once per session when failed jobs exist and permission is granted", async () => {
  const sessionData = new Map();
  let modalShown = false;
  let modalMessage = "";
  const modalEl = {
    classList: {
      add: (cls) => { if (cls === "show") modalShown = true; },
      remove: (cls) => { if (cls === "show") modalShown = false; },
    },
    style: {},
    addEventListener: () => {},
  };
  const messageEl = {
    set textContent(val) { modalMessage = val; },
    get textContent() { return modalMessage; },
  };

  const f = fixture(async () => {
    return {
      data: {
        overview: {
          kpis: { queue_pending_total: 0, queue_failed_total: 3 },
        },
      },
    };
  }, {
    hasPermission: (perm) => perm === "admin.jobs.run",
    translate: (key, fallback, params) => `${params?.count || 0} failed jobs`,
    documentRef: {
      visibilityState: "visible",
      getElementById: (id) => {
        if (id === "panel-queue-error-modal") return modalEl;
        if (id === "panel-queue-error-modal-message") return messageEl;
        return null;
      },
      defaultView: {
        setInterval: () => 1,
        clearInterval: () => {},
        sessionStorage: {
          getItem: (key) => sessionData.get(key) || null,
          setItem: (key, val) => sessionData.set(key, String(val)),
        },
      },
    },
  });

  await f.loadDashboardData();
  assert.equal(modalShown, true);
  assert.equal(modalMessage, "3 failed jobs");
  assert.equal(sessionData.get("nm_queue_error_modal_dismissed"), "1");
});

test("queue auto-run and modal are suppressed when user lacks admin.jobs.run", async () => {
  const apiCalls = [];
  let modalShown = false;
  const modalEl = {
    classList: { add: () => { modalShown = true; }, remove: () => {} },
    style: {},
    addEventListener: () => {},
  };

  const f = fixture(async (path, options) => {
    apiCalls.push({ path, options });
    return {
      data: {
        overview: {
          kpis: { queue_pending_total: 5, queue_failed_total: 3 },
        },
      },
    };
  }, {
    hasPermission: () => false,
    documentRef: {
      visibilityState: "visible",
      getElementById: (id) => (id === "panel-queue-error-modal" ? modalEl : null),
      defaultView: {
        setInterval: () => 1,
        clearInterval: () => {},
        sessionStorage: {
          getItem: () => null,
          setItem: () => {},
        },
      },
    },
  });

  await f.loadDashboardData();
  const queueRuns = apiCalls.filter((call) => call.path === "/queue/run-once");
  assert.equal(queueRuns.length, 0);
  assert.equal(modalShown, false);
});
