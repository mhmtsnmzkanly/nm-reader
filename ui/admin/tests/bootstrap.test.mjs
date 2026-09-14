import { test } from "node:test";
import assert from "node:assert/strict";
import { resolvePanelContext } from "../modules/bootstrap.js";

const auth = { is_logged_in: true, user_id: "abc", username: "reader", roles: ["root"], permissions: ["admin.panel.access"], csrf_token: "session-token", preferences: { lang: "tr" } };
const config = Object.fromEntries([
  "site_name", "site_abbreviation", "site_slogan", "site_description", "default_language", "footer_text",
  "default_theme", "site_logo", "logo_url", "favicon_url", "default_profile_image", "default_content_cover_image",
].map((key) => [key, key === "default_language" ? "en" : "value"]));
const response = (data) => ({ ok: true, json: async () => ({ status: "success", data }) });

test("missing context loads authenticated startup data with cookies", async () => {
  const calls = [];
  const result = await resolvePanelContext(undefined, { fetchImpl: async (path, options) => {
    calls.push(path);
    assert.equal(options.credentials, "same-origin");
    assert.equal(options.cache, "no-store");
    return response(path.endsWith("/me") ? auth : config);
  } });
  assert.deepEqual(calls.sort(), ["/api/v1/me", "/api/v1/site-config"]);
  assert.deepEqual(result.auth, auth);
  assert.equal(result.lang_code, "tr");
  assert.equal(result.default_lang, "en");
});

test("complete context makes no fallback requests", async () => {
  const result = await resolvePanelContext({ auth, site_config: config }, { fetchImpl: assert.fail });
  assert.equal(result.auth.csrf_token, "session-token");
});

test("partial auth is replaced, including stale permission grants", async () => {
  const result = await resolvePanelContext({ auth: { permissions: ["*"] }, site_config: config }, {
    fetchImpl: async (path) => { assert.equal(path, "/api/v1/me"); return response(auth); },
  });
  assert.deepEqual(result.auth.permissions, ["admin.panel.access"]);
});

test("401 and malformed data stop startup instead of producing a guest admin", async () => {
  await assert.rejects(resolvePanelContext({ site_config: config }, {
    fetchImpl: async () => ({ ok: false, status: 401 }),
  }), (error) => error.status === 401);
  await assert.rejects(resolvePanelContext({ site_config: config }, {
    fetchImpl: async () => response({ permissions: ["*"] }),
  }), /eksik/);
});
