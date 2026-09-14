import { test } from "node:test";
import assert from "node:assert/strict";
import { createPanelHandlers } from "../modules/handlers.js";

for (const name of ["Series", "Users", "Blogs", "Comments", "Logs", "Uploads", "QueueJobs"]) {
  test(`${name} refresh waits for success and preserves pagination`, async () => {
    const messages = [];
    let finish, requestedPage;
    const handlers = createPanelHandlers({
      store: { get: () => ({ page: 3 }) },
      showToast: (...args) => messages.push(args),
      [`load${name}Data`]: (page) => {
        requestedPage = page;
        return new Promise((resolve) => { finish = resolve; });
      },
    });
    const request = handlers[`load${name}`]();
    assert.deepEqual(messages, []);
    finish(true);
    await request;
    assert.equal(requestedPage, 3);
    assert.equal(messages.length, 1);
    for (const result of [false, undefined]) {
      messages.length = 0;
      const retry = handlers[`load${name}`]();
      finish(result);
      await retry;
      assert.deepEqual(messages, []);
    }
  });
}

test("dashboard silently skips cancelled or duplicate refreshes", async () => {
  const messages = [];
  let result;
  const handlers = createPanelHandlers({
    loadDashboardData: async () => result,
    showToast: (...args) => messages.push(args),
  });
  await handlers.refreshDashboard();
  assert.deepEqual(messages, []);
  result = false;
  await handlers.refreshDashboard();
  assert.equal(messages[0][1], "danger");
  result = true;
  await handlers.refreshDashboard();
  assert.equal(messages[1][1], "success");
});
