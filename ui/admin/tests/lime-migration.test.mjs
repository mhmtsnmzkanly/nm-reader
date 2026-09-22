import test from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";

const expectedImport =
  "https://cdn.jsdelivr.net/gh/mhmtsnmzkanly/lime-csr-js@v0.6.4/dist/index.min.js";

test("admin source and generated asset use the same Lime v0.6.4 runtime", async () => {
  const source = await readFile(new URL("../admin.js", import.meta.url), "utf8");
  const generated = await readFile(
    new URL("../../../public/assets/js/admin/admin.js", import.meta.url),
    "utf8",
  );

  assert.match(source, new RegExp(expectedImport.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")));
  assert.match(generated, new RegExp(expectedImport.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")));
  assert.doesNotMatch(source, /lime-csr-js@v0\.6\.2/);
  assert.doesNotMatch(generated, /lime-csr-js@v0\.6\.2/);
});
