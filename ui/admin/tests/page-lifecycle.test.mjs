import { test } from "node:test";
import assert from "node:assert/strict";
import { createPageSession } from "../modules/page-session.js";
import { createPageView } from "../modules/page-view.js";
import { createPanelNavigationController } from "../modules/navigation-controller.js";

test("replacing pages and disposing a session unmounts each partial exactly once", async () => {
  const counts = [];
  const mount = () => {
    const entry = { unmounts: 0 };
    counts.push(entry);
    return { unmount() { entry.unmounts++; } };
  };
  const view = createPageView({
    target: {}, pageSession: createPageSession(), mount,
    mountPartialEngine: mount, applyPermissionVisibility() {},
  });
  view.mountPage("loading");
  const target = {};
  view.mountPartial("rows", target);
  view.mountPartial("rows", target);
  view.mountPage("editor");
  await view.disposePage();
  await view.disposePage();
  assert.deepEqual(counts.map((entry) => entry.unmounts), [1, 1, 1, 1]);
});

function harness(resolve, can = () => true) {
  const mounted = [];
  const browser = { location: { pathname: "/panel/user/abc/wallet" }, history: {
    replaceState(_state, _title, path) { browser.location.pathname = path; },
  } };
  const controller = createPanelNavigationController({
    panelRouter: { resolve: () => resolve(browser.location.pathname) },
    panelNavigation: { setActiveRoute() {} }, store: { set() {} },
    hasPermission: can, showToast() {}, panelTranslate: (_key, fallback) => fallback,
    disposePage: async () => {}, beginPage() {},
    mountPage: (name) => mounted.push(name), renderRouteTables() {}, browser,
  });
  return { controller, mounted, browser };
}

test("wallet return path points to its user profile", async () => {
  const { controller } = harness(() => ({ route: "user-wallet", section: "user", params: { userId: "abc" }, view: "wallet" }));
  await controller.navigate();
  assert.equal(controller.parentPath(), "/panel/user/abc");
});

test("denied route and denied fallback render the access error", async () => {
  const { controller, mounted, browser } = harness(() => ({ route: "dashboard", params: {}, permissions: ["admin.panel.access"] }), () => false);
  await controller.navigate();
  assert.equal(browser.location.pathname, "/panel");
  assert.deepEqual(mounted, ["panel-page-error-shell"]);
});

test("an old loader failure cannot replace a newer page", async () => {
  let reject;
  const oldRequest = new Promise((_resolve, fail) => { reject = fail; });
  let route = { route: "user", section: "user", params: {}, view: "users", load: () => oldRequest };
  const { controller, mounted } = harness(() => route);
  await controller.navigate();
  route = { route: "blogs", section: "blogs", params: {}, view: "blogs" };
  await controller.navigate();
  reject(new Error("Old request failed"));
  await new Promise((resolve) => setImmediate(resolve));
  assert.deepEqual(mounted, ["users", "blogs"]);
});
