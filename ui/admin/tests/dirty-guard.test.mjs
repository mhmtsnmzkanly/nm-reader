import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import { createDirtyGuard } from "../modules/dirty-guard.js";

test("dirty guard detects form modifications and warns on navigation", () => {
  const dom = new JSDOM(`<!DOCTYPE html><html><body>
    <form id="edit-form">
      <input name="title" value="Initial" />
    </form>
  </body></html>`);
  let confirmed = false;
  const guard = createDirtyGuard({
    windowRef: dom.window,
    confirmAction: () => {
      confirmed = true;
      return true;
    },
  });

  const form = dom.window.document.getElementById("edit-form");
  const input = form.querySelector("input");
  const unregister = guard.register(form);

  assert.equal(guard.isDirty(), false);
  assert.equal(guard.checkCanNavigate(), true);

  // Modify input
  input.value = "Changed";
  input.dispatchEvent(new dom.window.Event("input", { bubbles: true }));

  assert.equal(guard.isDirty(), true);
  assert.equal(guard.checkCanNavigate(), true);
  assert.equal(confirmed, true);
  assert.equal(guard.isDirty(), false); // Cleared after confirmation

  unregister();
  guard.cleanup();
});

test("dirty guard respects cancelled navigation", () => {
  const dom = new JSDOM(`<!DOCTYPE html><html><body>
    <form id="edit-form">
      <input name="title" value="Initial" />
    </form>
  </body></html>`);
  const guard = createDirtyGuard({
    windowRef: dom.window,
    confirmAction: () => false, // User declines
  });

  const form = dom.window.document.getElementById("edit-form");
  const input = form.querySelector("input");
  guard.register(form);

  input.value = "Changed";
  input.dispatchEvent(new dom.window.Event("input", { bubbles: true }));

  assert.equal(guard.isDirty(), true);
  assert.equal(guard.checkCanNavigate(), false);
  assert.equal(guard.isDirty(), true); // Remains dirty

  guard.markClean();
  assert.equal(guard.isDirty(), false);
  assert.equal(guard.checkCanNavigate(), true);

  guard.cleanup();
});
