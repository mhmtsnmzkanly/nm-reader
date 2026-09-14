import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import { createI18n } from "../modules/i18n.js";
import { bindLanguageSelector, savedPanelLocale } from "../modules/language-selector.js";

test("language switch preserves form input and stores the selected locale", async (t) => {
  const dom = new JSDOM('<select></select><input value="unsaved"><span data-i18n="label"></span>', { url: "https://panel.example" });
  t.after(() => dom.window.close());
  const document = dom.window.document;
  const i18n = createI18n({ locale: "tr", supported: ["tr", "en"], initialDictionaries: { tr: { label: "Başlık" }, en: { label: "Title" } } });
  const select = document.querySelector("select");
  t.after(bindLanguageSelector({ select, supported: ["tr", "en"], i18n, documentRef: document, onChange: () => i18n.refresh(document), onError: assert.fail }));
  select.value = "en";
  select.dispatchEvent(new dom.window.Event("change"));
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(document.querySelector("span").textContent, "Title");
  assert.equal(document.querySelector("input").value, "unsaved");
  assert.equal(document.documentElement.lang, "en");
  assert.equal(savedPanelLocale(["tr", "en"], "tr", dom.window.localStorage), "en");
});

test("failed dictionary request restores the selector and keeps the old locale", async (t) => {
  const dom = new JSDOM('<select></select>', { url: "https://panel.example" });
  t.after(() => dom.window.close());
  const i18n = createI18n({ locale: "tr", supported: ["tr", "en"], fetchImpl: async () => { throw new Error("offline"); } });
  let errors = 0;
  const select = dom.window.document.querySelector("select");
  t.after(bindLanguageSelector({ select, supported: ["tr", "en"], i18n, documentRef: dom.window.document, onChange: assert.fail, onError: () => errors++ }));
  select.value = "en";
  select.dispatchEvent(new dom.window.Event("change"));
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(i18n.locale(), "tr");
  assert.equal(select.value, "tr");
  assert.equal(select.disabled, false);
  assert.equal(errors, 1);
});
