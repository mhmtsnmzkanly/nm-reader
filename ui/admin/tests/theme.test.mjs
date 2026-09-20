import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import { createThemeController, THEME_STORAGE_KEY } from "../modules/theme.js";

test("theme controller respects stored theme or falls back to auto", () => {
  const dom = new JSDOM(`<!DOCTYPE html><html><body></body></html>`);
  const storage = new Map();
  const mockStorage = {
    getItem: (k) => storage.get(k) ?? null,
    setItem: (k, v) => storage.set(k, v),
  };
  const controller = createThemeController({
    storage: mockStorage,
    documentRef: dom.window.document,
    windowRef: dom.window,
  });

  assert.equal(controller.getStoredTheme(), "auto");
  controller.setTheme("dark");
  assert.equal(mockStorage.getItem(THEME_STORAGE_KEY), "dark");
  assert.equal(controller.getStoredTheme(), "dark");
  assert.equal(dom.window.document.documentElement.getAttribute("data-bs-theme"), "dark");

  controller.setTheme("light");
  assert.equal(controller.getStoredTheme(), "light");
  assert.equal(dom.window.document.documentElement.getAttribute("data-bs-theme"), "light");
});

test("theme controller handles clicks on data-bs-theme-value buttons and invokes callback", () => {
  const dom = new JSDOM(`<!DOCTYPE html><html><body>
    <button id="theme-btn" data-bs-theme-value="dark">Dark</button>
    <i id="theme-icon-active" class="bi"></i>
  </body></html>`);
  const storage = new Map();
  const mockStorage = {
    getItem: (k) => storage.get(k) ?? null,
    setItem: (k, v) => storage.set(k, v),
  };
  let changedTo = null;
  const controller = createThemeController({
    storage: mockStorage,
    documentRef: dom.window.document,
    windowRef: dom.window,
    onThemeChange: (effective) => {
      changedTo = effective;
    },
  });

  const cleanup = controller.init();
  const btn = dom.window.document.getElementById("theme-btn");
  btn.click();

  assert.equal(controller.getStoredTheme(), "dark");
  assert.equal(changedTo, "dark");
  assert.equal(dom.window.document.documentElement.getAttribute("data-bs-theme"), "dark");
  assert.ok(btn.classList.contains("active"));
  assert.equal(btn.getAttribute("aria-pressed"), "true");

  cleanup();
});
