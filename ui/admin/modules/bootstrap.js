const object = (value) => value && typeof value === "object" && !Array.isArray(value);
const authReady = (auth) => object(auth) && auth.is_logged_in === true &&
  typeof auth.user_id === "string" && auth.user_id.length > 0 &&
  typeof auth.username === "string" && Array.isArray(auth.roles) &&
  Array.isArray(auth.permissions) && typeof auth.csrf_token === "string" && auth.csrf_token.length > 0;
const configKeys = [
  "site_name", "site_abbreviation", "site_slogan", "site_description", "default_language",
  "footer_text", "default_theme", "site_logo", "logo_url", "favicon_url",
  "default_profile_image", "default_content_cover_image",
];
const configReady = (config) => object(config) && configKeys.every((key) => typeof config[key] === "string");

/** Complete missing startup data before creating permissions, CSRF or stores. */
export async function resolvePanelContext(embedded, { fetchImpl = globalThis.fetch } = {}) {
  const context = object(embedded) ? embedded : {};
  async function get(path) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetchImpl(`/api/v1${path}`, {
        credentials: "same-origin", cache: "no-store", signal: controller.signal,
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      });
      if (!response.ok) {
        const error = new Error(`Panel başlangıç verisi alınamadı (HTTP ${response.status}).`);
        error.status = response.status;
        throw error;
      }
      const payload = await response.json();
      if (payload?.status !== "success" || !object(payload.data)) throw new Error("Geçersiz panel başlangıç verisi.");
      return payload.data;
    } finally { clearTimeout(timeout); }
  }
  const [auth, siteConfig] = await Promise.all([
    authReady(context.auth) ? context.auth : get("/me"),
    configReady(context.site_config) ? context.site_config : get("/site-config"),
  ]);
  if (!authReady(auth) || !configReady(siteConfig)) throw new Error("Panel başlangıç verisi eksik.");
  return {
    ...context,
    auth,
    site_config: siteConfig,
    lang_code: context.lang_code || auth.preferences?.lang || siteConfig.default_language,
    default_lang: context.default_lang || siteConfig.default_language,
    supported_langs: context.supported_langs?.length ? context.supported_langs : ["tr", "en"],
  };
}
