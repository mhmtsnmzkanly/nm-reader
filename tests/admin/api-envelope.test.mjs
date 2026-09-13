import test from "node:test";
import assert from "node:assert/strict";
import { createAdminApi, createAdminReauth } from "../../public/assets/js/admin/api.js";

const jsonResponse = (payload, status = 200) => new Response(JSON.stringify(payload), {
  status,
  headers: { "Content-Type": "application/json" },
});

for (const payload of [{}, [], null, true, 1, "success", { status: "" }, { status: "unexpected" }]) {
  test(`both transports reject malformed envelope ${JSON.stringify(payload)}`, async () => {
    const fetchImpl = async () => jsonResponse(payload);
    const api = createAdminApi({ fetchImpl });
    const reauth = createAdminReauth({ fetchImpl, promptImpl: () => "test-password" });
    for (const request of [() => api.get("/health"), () => reauth()]) {
      await assert.rejects(request, { name: "ApiError", key: "MALFORMED_RESPONSE" });
    }
  });
}

test("both transports accept explicit success", async () => {
  const payload = { status: "success", data: {}, meta: [], error: null };
  const fetchImpl = async () => jsonResponse(payload);
  assert.deepEqual(await createAdminApi({ fetchImpl }).get("/health"), payload);
  assert.equal(await createAdminReauth({ fetchImpl, promptImpl: () => "test-password" })(), true);
});

test("both transports preserve errors returned with HTTP 200", async () => {
  const payload = { status: "error", error: { code: 422, key: "VALIDATION_ERROR", message: "Invalid input" } };
  const fetchImpl = async () => jsonResponse(payload);
  const api = createAdminApi({ fetchImpl });
  const reauth = createAdminReauth({ fetchImpl, promptImpl: () => "test-password" });
  for (const request of [() => api.post("/content", {}), () => reauth()]) {
    await assert.rejects(request, { key: "VALIDATION_ERROR", status: 422, message: "Invalid input" });
  }
});

test("204 remains valid for mutations but cannot confirm reauthentication", async () => {
  const fetchImpl = async () => new Response(null, { status: 204 });
  assert.equal(await createAdminApi({ fetchImpl }).delete("/uploads/1"), null);
  await assert.rejects(
    () => createAdminReauth({ fetchImpl, promptImpl: () => "test-password" })(),
    { key: "MALFORMED_RESPONSE" },
  );
});

test("invalid reauthentication response does not retry a protected mutation", async () => {
  const paths = [];
  const fetchImpl = async (path) => {
    paths.push(path);
    return path.endsWith("/auth/reauth") ? jsonResponse({}) : jsonResponse({ status: "error" }, 428);
  };
  const api = createAdminApi({
    fetchImpl,
    reauthenticate: createAdminReauth({ fetchImpl, promptImpl: () => "test-password" }),
  });
  await assert.rejects(() => api.post("/protected", {}), { key: "MALFORMED_RESPONSE" });
  assert.deepEqual(paths, ["/api/v1/admin/protected", "/api/v1/admin/auth/reauth"]);
});
