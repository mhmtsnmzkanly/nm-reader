import test from "node:test";
import assert from "node:assert/strict";
import { paginationState, adjacentPage } from "../modules/pagination.js";
import { createPanelHandlers } from "../modules/handlers.js";
import { createPanelUi } from "../modules/ui.js";
import { createPanelTableRenderers } from "../modules/table-renderers.js";
import { createChaptersPageController } from "../modules/chapters-page.js";

test("pagination normalizes missing/invalid metadata without relabelling valid pages", () => {
  const empty = { page: 1, total_pages: 1, total: 0, has_previous: false, has_next: false };
  for (const meta of [undefined, null, {}, { page: -1, total_pages: 0, total: -8 },
    { page: "bad", total_pages: Infinity, total: NaN },
    { page: 1.5, total_pages: 2.5, total: 4.5 }]) {
    assert.deepEqual(paginationState(meta), empty);
  }
  assert.deepEqual(paginationState({ page: "2", total_pages: "3", total: "55" }), {
    page: 2, total_pages: 3, total: 55, has_previous: true, has_next: true,
  });
  assert.equal(paginationState({ page: 4, total_pages: 3 }).page, 4);
  assert.equal(adjacentPage({ page: 4, total_pages: 3 }, -1), 3);
  assert.equal(adjacentPage({ page: 4, total_pages: 3 }, 1), null);
  assert.equal(adjacentPage({ page: 2, total_pages: 3 }, 0), null);
});

const pages = {
  Logs: ["logsMeta", "loadLogsData"], Queue: ["queueMeta", "loadQueueJobsData"],
  Series: ["seriesMeta", "loadSeriesData"], Users: ["usersMeta", "loadUsersData"],
  Blogs: ["blogsMeta", "loadBlogsData"], Comments: ["commentsMeta", "loadCommentsData"],
  Reports: ["reportsMeta", "loadReportsData"], Finance: ["financeMeta", "loadFinanceData"],
  Uploads: ["uploadsMeta", "loadUploadsData"],
  UserComments: ["userCommentsMeta", "loadUserCommentsData", "userDetailId"],
  UserBlogs: ["userBlogsMeta", "loadUserBlogsData", "userDetailId"],
  UserViolations: ["userViolationsMeta", "loadUserViolationsData", "userDetailId"],
  UserWallet: ["userWalletMeta", "loadUserWalletPage", "userWalletId"],
  Likers: ["likersMeta", "loadLikersPage", "likersTarget"],
};

test("all 28 pagination handlers respect boundaries and preserve target arguments", async () => {
  for (const [name, [key, loader, idKey]] of Object.entries(pages)) {
    const state = { userDetailId: "user-a", userWalletId: "wallet-a",
      likersTarget: { targetType: "blog", targetId: "blog-a" } };
    const calls = [];
    const handlers = createPanelHandlers({
      store: { get: (key) => state[key] },
      [loader]: (...args) => { calls.push(args); return Promise.resolve("loaded"); },
    });
    for (const page of [1, 2, 3]) {
      state[key] = { page: String(page), total_pages: "3" };
      for (const [prefix, offset] of [["previous", -1], ["next", 1]]) {
        const next = page + offset;
        const result = await handlers[`${prefix}${name}Page`]();
        if (next < 1 || next > 3) {
          assert.equal(calls.length, 0, name);
          continue;
        }
        const expected = name === "Likers" ? ["blog", "blog-a", next]
          : idKey ? [state[idKey], next] : [next];
        assert.deepEqual(calls.shift(), expected, name);
        assert.equal(result, "loaded");
      }
    }
    if (idKey) {
      state[key] = { page: 1, total_pages: 2 };
      state[idKey] = name === "Likers" ? { targetType: "comment", targetId: "new-id" } : "new-id";
      await handlers[`next${name}Page`]();
      assert.deepEqual(calls.shift(), name === "Likers" ? ["comment", "new-id", 2] : ["new-id", 2]);
    }
  }
});

test("likers pagination does not request data without a target", () => {
  const handlers = createPanelHandlers({
    store: { get: (key) => key === "likersMeta" ? { page: 2, total_pages: 3 } : {} },
    loadLikersPage: () => assert.fail("Missing target must not trigger a request"),
  });
  handlers.previousLikersPage();
  handlers.nextLikersPage();
});

test("reports use the common pager while retaining status counts", () => {
  const mounts = [];
  const nodes = new Map();
  const documentRef = { getElementById: (id) => {
    if (!nodes.has(id)) nodes.set(id, { id });
    return nodes.get(id);
  } };
  const ui = createPanelUi({ documentRef, mountPartial: (...args) => mounts.push(args) });
  const meta = { page: "2", total_pages: "3", total: "45", counts: { pending: 9 } };
  const view = createPanelTableRenderers({
    store: { get: (key) => key === "reportsMeta" ? meta : [] },
    documentRef, setTableRows: () => {}, renderPager: ui.renderPager,
  });
  view.renderReportsTable();
  const [partial, target, data] = mounts[0];
  assert.equal(partial, "panel-pager");
  assert.equal(target.id, "panel-reports-pager");
  assert.deepEqual(data, { ...paginationState(meta),
    previous_handler: "previousReportsPage", next_handler: "nextReportsPage" });
  assert.equal(nodes.get("panel-report-count-pending").textContent, "9");
});

test("chapter pager preserves prev/next controls and refuses out-of-range clicks", async () => {
  let click;
  let requestedPage = 1;
  const requests = [];
  const controller = createChaptersPageController({
    store: { get: () => [{ id: "series-a", title: "Example" }] },
    api: async (path) => {
      requests.push(path);
      requestedPage = Number(new URL(path, "https://example.test").searchParams.get("page"));
      return {};
    },
    responseItems: () => [],
    responseMeta: () => ({ page: String(requestedPage), total_pages: "3", total: 60 }),
    getPageEpoch: () => 1, assertCurrentPage: () => {},
    mountPartial: () => {},
    mountEditorPage: () => ({
      querySelectorAll: () => [], querySelector: () => null,
      addEventListener: (type, handler) => { if (type === "click") click = handler; },
    }),
  });
  const go = (direction) => click({ target: { closest: () => ({
    disabled: false, dataset: { chapterPage: direction },
  }) } });
  await controller.loadChaptersPage("series-a", 2);
  await go("prev");
  assert.equal(requestedPage, 1);
  const count = requests.length;
  await go("prev");
  assert.equal(requests.length, count);
  await go("next");
  assert.equal(requestedPage, 2);
  await go("next");
  assert.equal(requestedPage, 3);
  await go("next");
  assert.equal(requests.length, count + 2);
});
