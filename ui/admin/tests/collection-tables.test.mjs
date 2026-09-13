import test from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { collectionTables, createCollectionTableRenderer } from "../modules/collection-tables.js";
import { createPanelUi } from "../modules/ui.js";
import { createCollectionDataController } from "../modules/collection-data.js";
import { createRequestGate } from "../modules/request-gate.js";

test("collection definitions match actual HTML targets, headers and partials", async () => {
  const html = await readFile(new URL("../admin.html", import.meta.url), "utf8");
  for (const [name, table] of Object.entries(collectionTables)) {
    const target = html.indexOf(`<tbody id="${table.id}">`);
    assert.notEqual(target, -1, `${name}: missing tbody`);
    const markup = html.slice(html.lastIndexOf("<table", target), target);
    const header = markup.match(/<thead\b[^>]*>([\s\S]*?)<\/thead>/)?.[1];
    assert.ok(header, `${name}: missing header`);
    assert.equal([...header.matchAll(/<th\b/g)].length, table.columns, name);
    assert.ok(html.includes(`id="tpl-${table.partial}"`), `${name}: missing partial`);
  }
});

test("loading, error, empty and data rows share target and column count", () => {
  const mounts = [];
  const ui = createPanelUi({
    documentRef: { getElementById: (id) => ({ id }) },
    mountPartial: (partial, target, data) => mounts.push({ partial, target, data }),
  });
  const render = createCollectionTableRenderer(ui.setTableRows);
  for (const [name, table] of Object.entries(collectionTables)) {
    render(name, [], { loading: true });
    render(name, [], { error_message: "Request failed" });
    render(name);
    render(name, [{ id: "example" }]);
    const [loading, error, empty, rows] = mounts.splice(0);
    for (const mount of [loading, error, empty, rows]) {
      assert.equal(mount.target.id, table.id);
      assert.equal(mount.data.colspan, table.columns);
    }
    assert.equal(loading.partial, "panel-table-loading");
    assert.equal(error.partial, "panel-table-error");
    assert.equal(error.data.error_message, "Request failed");
    assert.equal(empty.partial, table.partial);
    assert.equal(empty.data.has_items, false);
    assert.equal(rows.partial, table.partial);
    assert.equal(rows.data.has_items, true);
    assert.deepEqual(rows.data.items, [{ id: "example" }]);
  }
  assert.throws(() => render("missing"), /Unknown collection table/);
});

test("all collection loaders use their own target for loading and failure", async () => {
  const loaders = {
    series: "loadSeriesData", blogs: "loadBlogsData", comments: "loadCommentsData",
    reports: "loadReportsData", packages: "loadPackagesData", finance: "loadFinanceData",
    logs: "loadLogsData", uploads: "loadUploadsData",
  };
  for (const [name, method] of Object.entries(loaders)) {
    const calls = [];
    const toasts = [];
    const controller = createCollectionDataController({
      api: async () => { throw new Error("Request failed"); },
      getPageEpoch: () => 1,
      assertCurrentPage: () => {},
      seriesRequestGate: createRequestGate(),
      requestGates: { [name]: createRequestGate() },
      documentRef: { getElementById: () => null },
      setTableRows: (...args) => calls.push(args),
      showToast: (...args) => toasts.push(args),
    });
    await controller[method]();
    assert.equal(calls.length, 2, name);
    for (const [id, partial, items, columns] of calls) {
      assert.equal(id, collectionTables[name].id, name);
      assert.equal(partial, collectionTables[name].partial, name);
      assert.equal(columns, collectionTables[name].columns, name);
      assert.deepEqual(items, []);
    }
    assert.equal(calls[0][4].loading, true);
    assert.equal(calls[1][4].error_message, "Request failed");
    assert.equal(toasts.length, 1);
  }
});
