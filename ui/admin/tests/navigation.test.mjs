import { test } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";
import { createPanelRoutes } from "../modules/routes.js";
import { createPanelRouter, bindPanelNavigation, navigatePanelPath, isRouteLink } from "../modules/router.js";
import { createPanelNavigation, capturePanelGroupState, restorePanelGroupState } from "../modules/navigation.js";
import { createAccess } from "../modules/access.js";
import { createPermissionUi } from "../modules/permission-ui.js";

const html = fs.readFileSync(new URL("../admin.html", import.meta.url), "utf8");
function fixture(t, path = "/panel") {
  // Parse the real shell without executing scripts or fetching CDN assets.
  const dom = new JSDOM(html, { url: `https://panel.example${path}` });
  t.after(() => dom.window.close());
  return dom.window;
}
const routes = createPanelRoutes({ loaders: {} });

test("real route map resolves direct URLs and nested parameters", () => {
  const router = createPanelRouter({ routeTree: routes });
  for (const section of ["dashboard", "series", "blogs", "comments", "reports", "user", "uploads", "finance", "ops", "taxonomies", "config", "webhook", "config-env"]) {
    assert.equal(router.resolve(`/panel/${section}`).section, section);
  }
  assert.equal(router.resolve("/panel").route, "dashboard");
  for (const [suffix, route] of [["", "user-detail"], ["/penalty", "user-penalty"], ["/wallet", "user-wallet"]]) {
    const result = router.resolve(`/panel/user/abc_123${suffix}`);
    assert.equal(result.route, route);
    assert.equal(result.params.userId, "abc_123");
  }
  assert.equal(router.resolve("/panel/user/roles").route, "user-roles");
  assert.equal(router.resolve("/panel/series/abc/preview").params.seriesId, "abc");
  for (const path of ["/panel/unknown", "/panel/action/foo", "/panel/users", "/panel/user/abc/unknown"]) {
    assert.ok(router.resolve(path).redirect, path);
  }
});

test("group controls open without routing, route links work through child icons", (t) => {
  const window = fixture(t);
  const document = window.document;
  const navigation = createPanelNavigation({ documentRef: document });
  t.after(navigation.cleanup);
  const paths = [];
  const unbind = bindPanelNavigation({ documentRef: document, onNavigate: (path) => paths.push(path) });
  t.after(unbind);
  const group = document.querySelector('[data-panel-group="content"]');
  group.querySelector("i").click();
  assert.equal(group.getAttribute("aria-expanded"), "true");
  assert.deepEqual(paths, []);
  assert.equal(window.location.pathname, "/panel");
  group.click();
  assert.equal(group.getAttribute("aria-expanded"), "false");
  const link = document.querySelector('a[data-route="series"]');
  link.querySelector("i").click();
  assert.deepEqual(paths, ["/panel/series"]);
  unbind();
  const click = new window.MouseEvent("click", { bubbles: true, cancelable: true });
  // Avoid jsdom's native navigation while verifying listener teardown.
  click.preventDefault();
  link.dispatchEvent(click);
  assert.equal(paths.length, 1);
});

test("active nested routes open the matching group and preserve group choices", (t) => {
  const window = fixture(t);
  const document = window.document;
  const navigation = createPanelNavigation({ documentRef: document });
  t.after(navigation.cleanup);
  const root = document.getElementById("panel-sidebar-nav");
  navigation.setActiveRoute("user-wallet", "user");
  assert.equal(root.querySelector('[aria-current="page"]').dataset.route, "user");
  const group = root.querySelector('[data-panel-group="users"]');
  assert.equal(group.getAttribute("aria-expanded"), "true");
  const state = capturePanelGroupState(root);
  group.click();
  restorePanelGroupState(root, state);
  assert.equal(group.getAttribute("aria-expanded"), "true");
  navigation.setActiveRoute("series-preview", "series");
  assert.equal(root.querySelectorAll('[aria-current="page"]').length, 1);
  assert.equal(root.querySelector('[aria-current="page"]').dataset.route, "series");
});

test("link classification excludes controls, downloads and other origins", (t) => {
  const window = fixture(t);
  for (const attributes of [
    'href="#" data-lte-toggle="treeview"',
    'href="#series" data-route="series"',
    'href="/panel/series" data-panel-link target="_blank"',
    'href="/panel/series" data-panel-link download',
    'href="https://other.example/panel" data-panel-link',
    'href="/panel-other" data-panel-link',
  ]) {
    const container = window.document.createElement("div");
    container.innerHTML = `<a ${attributes}>link</a>`;
    assert.equal(isRouteLink(container.firstChild, "/panel", window.location), false, attributes);
  }
});

test("history back and forward resolve the restored path", async (t) => {
  const window = fixture(t, "/panel/user/abc/wallet");
  const router = createPanelRouter({ routeTree: routes, location: window.location });
  const resolved = [];
  const onNavigate = () => resolved.push(router.resolve().route);
  window.addEventListener("popstate", onNavigate);
  assert.equal(router.resolve().route, "user-wallet");
  assert.equal(navigatePanelPath("/panel/blogs", {
    locationRef: window.location, historyRef: window.history, onNavigate,
  }), true);
  const move = (direction) => new Promise((resolve) => {
    window.addEventListener("popstate", resolve, { once: true });
    window.history[direction]();
  });
  await move("back");
  await move("forward");
  assert.deepEqual(resolved, ["blogs", "user-wallet", "blogs"]);
});

test("sidebar permission visibility follows explicit grants", (t) => {
  const window = fixture(t);
  const access = createAccess([]);
  const permissions = createPermissionUi({ documentRef: window.document, can: (...required) => access.any(required) });
  permissions.apply();
  const protectedNodes = [...window.document.querySelectorAll("[data-requires-permission]")];
  assert.ok(protectedNodes.length > 0);
  assert.ok(protectedNodes.every((node) => node.classList.contains("permission-hidden")));
  access.setPermissions(["*"]);
  permissions.apply();
  assert.ok(protectedNodes.every((node) => !node.classList.contains("permission-hidden")));
  access.setPermissions(["admin.reports.view"]);
  permissions.apply();
  const report = protectedNodes.find((node) => node.dataset.requiresPermission === "admin.reports.view");
  assert.ok(report);
  assert.equal(report.classList.contains("permission-hidden"), false);
});

test("keyboard toggles groups and modified clicks do not trigger CSR navigation", (t) => {
  const window = fixture(t);
  const navigation = createPanelNavigation({ documentRef: window.document });
  t.after(navigation.cleanup);
  let calls = 0;
  t.after(bindPanelNavigation({ documentRef: window.document, onNavigate: () => calls++ }));
  const group = window.document.querySelector("[data-panel-group]");
  for (const key of ["Enter", " "]) {
    const before = group.getAttribute("aria-expanded") === "true";
    group.dispatchEvent(new window.KeyboardEvent("keydown", { key, bubbles: true, cancelable: true }));
    assert.equal(group.getAttribute("aria-expanded"), String(!before));
  }
  const link = window.document.querySelector('a[data-route="series"]');
  // Stop native jsdom navigation only after the CSR document handler runs.
  window.addEventListener("click", (event) => event.preventDefault());
  for (const modifier of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }]) {
    link.dispatchEvent(new window.MouseEvent("click", { bubbles: true, cancelable: true, ...modifier }));
  }
  assert.equal(calls, 0);
});

test("hamburger fallback opens the sidebar and overlay closes it", async (t) => {
  const window = fixture(t);
  const navigation = createPanelNavigation({ documentRef: window.document });
  t.after(navigation.cleanup);
  window.document.querySelector('[data-lte-toggle="sidebar"]').click();
  await new Promise((resolve) => window.setTimeout(resolve, 10));
  assert.equal(window.document.body.classList.contains("sidebar-open"), true);
  window.document.querySelector(".sidebar-overlay").click();
  assert.equal(window.document.body.classList.contains("sidebar-open"), false);
});
