import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import { createTaxonomyPageController } from "../modules/taxonomy-page.js";

test("loadTaxonomyPage loads taxonomies and reorders via PUT /taxonomies/order", async () => {
  const dom = new JSDOM("<!DOCTYPE html><html><body><div id='root'></div></body></html>");
  const doc = dom.window.document;

  const mockGenres = [
    { id: 1, name: "Action", slug: "action", sort_order: 2, usage_count: 5, ui_config: null },
    { id: 2, name: "Comedy", slug: "comedy", sort_order: 1, usage_count: 3, ui_config: '{"description":"Funny"}' },
  ];
  const mockTags = [
    { id: 10, name: "Magic", slug: "magic", sort_order: 0, usage_count: 8, ui_config: null },
  ];

  let orderPayload = null;
  let saveCallback = null;
  let toastMessage = "";

  const pageEl = doc.createElement("div");
  pageEl.innerHTML = `
    <form data-editor-form>
      <table>
        <tbody id="panel-taxonomy-genres">
          <tr data-taxonomy-row="1">
            <td><input data-taxonomy-order value="5" /></td>
          </tr>
          <tr data-taxonomy-row="2">
            <td><input data-taxonomy-order value="10" /></td>
          </tr>
        </tbody>
        <tbody id="panel-taxonomy-tags">
          <tr data-taxonomy-row="10">
            <td><input data-taxonomy-order value="1" /></td>
          </tr>
        </tbody>
      </table>
      <div class="card-footer">
        <button type="submit"><span>Kaydet</span></button>
      </div>
    </form>
  `;

  const controller = createTaxonomyPageController({
    api: async (url, options = {}) => {
      if (url === "/taxonomies/order") {
        orderPayload = options.body;
        return { success: true };
      }
      return {};
    },
    getPageEpoch: () => 1,
    assertCurrentPage: () => {},
    loadTaxonomies: async () => ({ genres: mockGenres, tags: mockTags }),
    mountEditorPage: (_title, _data, onSubmit) => {
      saveCallback = onSubmit;
      return pageEl;
    },
    mountPartial: () => {},
    showToast: (msg) => { toastMessage = msg; },
    registerPageCleanup: () => {},
    translate: (_key, fallback) => fallback,
  });

  await controller.loadTaxonomyPage();

  assert.ok(saveCallback, "mountEditorPage should have received an onSubmit handler");
  const footerSpan = pageEl.querySelector('.card-footer button[type="submit"] span');
  assert.equal(footerSpan.textContent, "Sıralamayı Kaydet");

  // Trigger save order
  await saveCallback();

  assert.deepEqual(orderPayload, {
    items: [
      { id: 1, sort_order: 5 },
      { id: 2, sort_order: 10 },
      { id: 10, sort_order: 1 },
    ],
  });
  assert.equal(toastMessage, "Taksonomi sırası kaydedildi");
});

test("editing taxonomy opens modal dialog and sends PUT /taxonomies/{id}", async () => {
  const dom = new JSDOM("<!DOCTYPE html><html><body><div id='root'></div></body></html>");
  const doc = dom.window.document;

  const mockGenres = [
    { id: 1, name: "Action", slug: "action", sort_order: 0, usage_count: 5, ui_config: '{"description":"Fast-paced"}' },
  ];

  let putUrl = "";
  let putBody = null;
  let dialogConfig = null;
  let toastMessage = "";

  const pageEl = doc.createElement("div");
  pageEl.innerHTML = `
    <button data-edit-taxonomy="1" data-name="Action">
      <i class="bi bi-pencil"></i>
    </button>
  `;

  const controller = createTaxonomyPageController({
    api: async (url, options = {}) => {
      if (options.method === "PUT") {
        putUrl = url;
        putBody = options.body;
        return { success: true };
      }
      return {};
    },
    getPageEpoch: () => 1,
    assertCurrentPage: () => {},
    loadTaxonomies: async () => ({ genres: mockGenres, tags: [] }),
    mountEditorPage: () => pageEl,
    mountPartial: () => {},
    modalService: {
      dialog: async (cfg) => {
        dialogConfig = cfg;
        return { name: "Action Adventure", description: "Updated description" };
      },
    },
    showToast: (msg) => { toastMessage = msg; },
    registerPageCleanup: () => {},
    translate: (_key, fallback) => fallback,
  });

  await controller.loadTaxonomyPage();

  // Click edit button
  const editBtn = pageEl.querySelector("[data-edit-taxonomy]");
  editBtn.click();
  // Wait microtask
  await new Promise((resolve) => setTimeout(resolve, 10));

  assert.ok(dialogConfig, "modalService.dialog should have been called");
  assert.equal(dialogConfig.fields[0].value, "Action");
  assert.equal(dialogConfig.fields[1].value, "Fast-paced");
  assert.equal(putUrl, "/taxonomies/1");
  assert.deepEqual(putBody, {
    name: "Action Adventure",
    ui_config: { description: "Updated description" },
  });
  assert.equal(toastMessage, "Taksonomi güncellendi");
});

test("creating genre opens modal dialog and sends POST /series_genres with ui_config", async () => {
  const dom = new JSDOM("<!DOCTYPE html><html><body><div id='root'></div></body></html>");
  const doc = dom.window.document;

  let postUrl = "";
  let postBody = null;
  let toastMessage = "";

  const pageEl = doc.createElement("div");
  pageEl.innerHTML = `
    <button data-create-taxonomy="genre">Yeni Tür</button>
  `;

  const controller = createTaxonomyPageController({
    api: async (url, options = {}) => {
      if (options.method === "POST") {
        postUrl = url;
        postBody = options.body;
        return { success: true };
      }
      return {};
    },
    getPageEpoch: () => 1,
    assertCurrentPage: () => {},
    loadTaxonomies: async () => ({ genres: [], tags: [] }),
    mountEditorPage: () => pageEl,
    mountPartial: () => {},
    modalService: {
      dialog: async () => ({ name: "Isekai", description: "Other world adventure" }),
    },
    showToast: (msg) => { toastMessage = msg; },
    registerPageCleanup: () => {},
    translate: (_key, fallback) => fallback,
  });

  await controller.loadTaxonomyPage();

  const createBtn = pageEl.querySelector("[data-create-taxonomy]");
  createBtn.click();
  await new Promise((resolve) => setTimeout(resolve, 10));

  assert.equal(postUrl, "/series_genres");
  assert.deepEqual(postBody, {
    name: "Isekai",
    ui_config: { description: "Other world adventure" },
  });
  assert.equal(toastMessage, "Tür oluşturuldu");
});
