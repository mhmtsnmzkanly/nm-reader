import { test } from "node:test";
import assert from "node:assert/strict";
import { auditTemplates } from "../scripts/template-audit.mjs";

test("reports missing text and accessible label translations", () => {
  const html = '<button data-i18n="save" data-i18n-attr="title:save,aria-label:save_label">Save</button>';
  const result = auditTemplates(html, { en: { save: "Save" }, tr: {} });
  assert.equal(result.errors.length, 3);
  assert.ok(result.errors.some((error) => error.includes("en: missing or empty translation save_label")));
});

test("rejects duplicate attributes regardless of casing", () => {
  const result = auditTemplates('<a title="a > b" TITLE="other">Link</a>', {});
  assert.match(result.errors[0], /duplicate title/);
});

test("ignores comments, scripts and runtime keys, but audits nested templates", () => {
  const html = '<!-- <b title="a" title="b"> --><script>"<b title=x title=y>"</script>' +
    '<template><span data-i18n="${header.title_key}"></span><span data-i18n="title">Title</span></template>';
  assert.deepEqual(auditTemplates(html, { en: { title: "Title" } }).errors, []);
});
