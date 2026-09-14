import { test } from "node:test";
import assert from "node:assert/strict";
import { createUserPenaltyController } from "../modules/user-penalty.js";
import { createUserWalletController } from "../modules/user-wallet.js";

function memoryStore(initial = {}) {
  const values = new Map(Object.entries(initial));
  return { get: (key) => values.get(key), set: (key, value) => values.set(key, value), batch: (fn) => fn() };
}

test("penalty keeps canonical payload and returns to the user's profile", async () => {
  let submit, request, destination, context;
  const form = { elements: { auto_escalate: { checked: true, addEventListener() {}, removeEventListener() {} }, level: {} } };
  const controller = createUserPenaltyController({
    store: memoryStore({ userDetailId: "abc", userDetail: { user: { username: "reader" } } }),
    api: async (url, options) => { request = { url, options }; },
    getPageEpoch: () => 1, assertCurrentPage() {},
    mountPage: (_name, data) => { context = data; return { querySelector: () => form }; },
    bindFormAction: (_form, action) => { submit = action; return () => {}; },
    registerPageCleanup() {}, showToast() {}, panelNavigate: (path) => { destination = path; },
  });
  await controller.loadUserPenaltyPage("abc");
  assert.equal(context.header.parent_path, "/panel/user/abc");
  assert.equal(form.elements.level.disabled, true);
  await submit(new Map([["target_type", "blog"], ["level", "ban"], ["reason", "Spam"]]));
  assert.equal(request.url, "/users/abc/violations");
  assert.equal(request.options.method, "POST");
  assert.equal(request.options.body.scope, "blog");
  assert.equal(request.options.body.level, "warning");
  assert.equal(request.options.body.auto_escalate, true);
  assert.equal(destination, "/panel/user/abc");
});

test("read-only wallet paginates without requesting management-only data", async () => {
  const urls = [];
  const store = memoryStore();
  let context, pager;
  const controller = createUserWalletController({
    store, api: async (url) => { urls.push(url); return { data: { balance_coin: 42 }, meta: { page: 2 } }; },
    responseItems: () => [], responseMeta: (response) => response.meta,
    getPageEpoch: () => 1, assertCurrentPage() {}, hasPermission: () => false,
    mountPage: (_name, data) => { context = data; return { querySelector: () => null }; },
    mountPartial() {}, renderPager: (...args) => { pager = args; },
  });
  await controller.loadUserWalletPage("abc", 2);
  assert.deepEqual(urls, ["/wallets/abc", "/wallets/abc/transactions?page=2&per_page=25"]);
  assert.equal(context.can_manage_wallet, false);
  assert.equal(context.can_load_packages, false);
  assert.equal(context.header.parent_path, "/panel/user/abc");
  assert.equal(store.get("userWalletId"), "abc");
  assert.equal(pager[1].page, 2);
});
