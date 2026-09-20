import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import { createChapterEditorController } from "../modules/chapter-editor.js";

test("chapter editor detects saved draft and allows restore or discard", async () => {
  const dom = new JSDOM(`<!DOCTYPE html><html><body>
    <div id="page">
      <div id="panel-chapter-draft-alert" hidden>
        <button id="panel-chapter-draft-restore">Geri Yükle</button>
        <button id="panel-chapter-draft-discard">Sil</button>
      </div>
      <select id="panel-chapter-type"><option value="text">Metin</option></select>
      <input name="chapter_number" value="1" />
      <input name="title" value="Bölüm 1" />
      <textarea name="body">Initial body</textarea>
      <textarea name="translator_note">Initial note</textarea>
      <textarea name="pages"></textarea>
      <div data-chapter-body></div>
      <div data-chapter-pages hidden></div>
      <div data-page-preview></div>
    </div>
  </body></html>`);

  const mockStorage = new Map();
  const storage = {
    getItem: (key) => mockStorage.get(key) || null,
    setItem: (key, val) => mockStorage.set(key, String(val)),
    removeItem: (key) => mockStorage.delete(key),
  };

  // Pre-seed a draft
  storage.setItem(
    "nm_chapter_draft_10_new",
    JSON.stringify({ body: "Draft body from previous session", translator_note: "Draft note" }),
  );

  let submitHandler;
  const cleanups = [];
  const controller = createChapterEditorController({
    api: async () => ({ data: {} }),
    getPageEpoch: () => 1,
    assertCurrentPage: () => {},
    mountEditorPage: (_title, _data, onSubmit) => {
      submitHandler = onSubmit;
      return dom.window.document.getElementById("page");
    },
    mountPartial: () => {},
    registerPageCleanup: (fn) => cleanups.push(fn),
    safeLocalPath: (p) => p,
    hasPermission: () => true,
    showToast: () => {},
    panelNavigate: () => {},
    documentRef: dom.window.document,
    storage,
  });

  await controller.renderChapterPage({ id: 10, title: "Series Title" }, null);

  const draftAlert = dom.window.document.getElementById("panel-chapter-draft-alert");
  assert.equal(draftAlert.hidden, false, "Draft alert should be visible when draft differs");

  const restoreBtn = dom.window.document.getElementById("panel-chapter-draft-restore");
  restoreBtn.click();

  const bodyEl = dom.window.document.querySelector('[name="body"]');
  const noteEl = dom.window.document.querySelector('[name="translator_note"]');
  assert.equal(bodyEl.value, "Draft body from previous session");
  assert.equal(noteEl.value, "Draft note");
  assert.equal(draftAlert.hidden, true, "Draft alert should hide after restore");

  // Form submit should clear draft
  const formData = new dom.window.FormData();
  formData.append("chapter_number", "1");
  formData.append("title", "Test");
  formData.append("body", bodyEl.value);
  formData.append("translator_note", noteEl.value);
  formData.append("type", "text");

  const form = dom.window.document.createElement("form");
  await submitHandler(formData, form);

  assert.equal(storage.getItem("nm_chapter_draft_10_new"), null, "Draft should be cleared on form submit");

  cleanups.forEach((fn) => fn());
});
