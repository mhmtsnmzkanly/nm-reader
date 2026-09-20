import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import { createCommandPalette } from "../modules/command-palette.js";

test("command palette filters items by query and respects permissions", () => {
  const dom = new JSDOM(`<!DOCTYPE html><html><body>
    <button id="panel-command-palette-trigger">Trigger</button>
    <div id="panel-command-palette-modal">
      <input id="panel-command-palette-input" />
      <div id="panel-command-palette-results"></div>
      <div id="panel-command-palette-empty" hidden></div>
    </div>
  </body></html>`);

  const palette = createCommandPalette({
    documentRef: dom.window.document,
    windowRef: dom.window,
    hasPermission: (perm) => perm !== "admin.env.manage", // deny env
    translate: (_k, fallback) => fallback,
  });

  const all = palette.filterCommands("");
  assert.ok(all.some((c) => c.id === "nav-dashboard"));
  assert.ok(!all.some((c) => c.id === "nav-env"), "Denied permissions must be filtered out");

  const searchResults = palette.filterCommands("kullanıcı");
  assert.ok(searchResults.some((c) => c.id === "nav-users"));
  assert.ok(!searchResults.some((c) => c.id === "nav-dashboard"));

  palette.cleanup();
});

test("command palette opens, navigates with keys, and executes selection", () => {
  const dom = new JSDOM(`<!DOCTYPE html><html><body>
    <button id="panel-command-palette-trigger">Trigger</button>
    <div id="panel-command-palette-modal">
      <input id="panel-command-palette-input" />
      <div id="panel-command-palette-results"></div>
      <div id="panel-command-palette-empty" hidden></div>
    </div>
  </body></html>`);

  let navigatedTo = null;
  let themeChanged = null;

  const palette = createCommandPalette({
    documentRef: dom.window.document,
    windowRef: dom.window,
    panelNavigate: (path) => {
      navigatedTo = path;
    },
    setTheme: (theme) => {
      themeChanged = theme;
    },
    hasPermission: () => true,
    translate: (_k, fallback) => fallback,
  });

  const modalEl = dom.window.document.getElementById("panel-command-palette-modal");
  const inputEl = dom.window.document.getElementById("panel-command-palette-input");
  const resultsEl = dom.window.document.getElementById("panel-command-palette-results");

  // Open via Ctrl+K
  dom.window.dispatchEvent(new dom.window.KeyboardEvent("keydown", {
    key: "k",
    ctrlKey: true,
    bubbles: true,
  }));

  assert.equal(modalEl.classList.contains("show"), true);
  assert.ok(resultsEl.children.length > 0);

  // ArrowDown to move to second item
  dom.window.dispatchEvent(new dom.window.KeyboardEvent("keydown", {
    key: "ArrowDown",
    bubbles: true,
  }));

  const activeItem = resultsEl.querySelector(".active");
  assert.equal(activeItem?.dataset.index, "1");

  // Filter input to search for theme dark
  inputEl.value = "koyu";
  inputEl.dispatchEvent(new dom.window.Event("input", { bubbles: true }));

  assert.equal(resultsEl.children.length, 1);

  // Press Enter on the filtered item
  dom.window.dispatchEvent(new dom.window.KeyboardEvent("keydown", {
    key: "Enter",
    bubbles: true,
  }));

  assert.equal(themeChanged, "dark");
  assert.equal(modalEl.classList.contains("show"), false);

  // Re-open and test navigation click
  palette.open();
  assert.equal(modalEl.classList.contains("show"), true);

  inputEl.value = "seriler";
  inputEl.dispatchEvent(new dom.window.Event("input", { bubbles: true }));

  const firstResult = resultsEl.querySelector("button");
  assert.ok(firstResult);
  firstResult.click();

  assert.equal(navigatedTo, "/panel/series");
  assert.equal(modalEl.classList.contains("show"), false);

  palette.cleanup();
});
