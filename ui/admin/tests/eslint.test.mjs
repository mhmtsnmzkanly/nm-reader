import { test } from "node:test";
import assert from "node:assert/strict";
import { fileURLToPath } from "node:url";
import { ESLint } from "eslint";

const eslint = new ESLint({ cwd: fileURLToPath(new URL("..", import.meta.url)) });

test("missing formatter import is rejected while browser globals are accepted", async () => {
  const [result] = await eslint.lintText(
    'document.title = formatCount(42); window.addEventListener("load", () => {});',
    { filePath: "modules/check.js" },
  );
  assert.equal(result.errorCount, 1);
  assert.equal(result.messages[0].ruleId, "no-undef");
  assert.match(result.messages[0].message, /formatCount/);
});

test("Node globals are limited to tooling and tests", async () => {
  const source = 'process.exitCode = 0;';
  const [browser] = await eslint.lintText(source, { filePath: "modules/check.js" });
  const [tool] = await eslint.lintText(source, { filePath: "scripts/check.mjs" });
  assert.equal(browser.errorCount, 1);
  assert.equal(tool.errorCount, 0);
});
