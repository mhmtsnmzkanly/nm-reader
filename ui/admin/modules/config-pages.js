/** Site configuration, environment and webhook page controller. */
export function createConfigPagesController({
  store,
  api,
  responseItems,
  getPageEpoch,
  assertCurrentPage,
  mountPartial,
  panelNavigate,
  confirmAction = () => false,
  showToast,
  translate = (_key, fallback) => fallback,
  bindFormAction,
  registerPageCleanup,
  documentRef = globalThis.document,
} = {}) {
  const document = documentRef;
  let configSaveInFlight = false;
  async function loadEnvPage() {
    const requestEpoch = getPageEpoch();
    const target = document.getElementById("panel-config-env-page");
    if (!target) return;
    try {
      const response = await api("/maintenance/env");
      assertCurrentPage(requestEpoch);
      const values = response?.data || {};
      const groups = [
        {
          title: translate("admin.env.group.app", "Uygulama ve Adres"),
          icon: "bi-gear",
          keys: [
            "APP_NAME",
            "APP_ENV",
            "APP_DEBUG",
            "APP_URL",
            "SITE_ADDRESS",
            "APP_TIMEZONE",
          ],
        },
        {
          title: translate("admin.env.group.session", "Oturum ve Güvenlik"),
          icon: "bi-shield-lock",
          keys: [
            "SESSION_LIFETIME",
            "SESSION_COOKIE_LIFETIME",
            "REFRESH_TOKEN_DAYS",
            "SESSION_COOKIE_SECURE",
            "SESSION_COOKIE_SAME_SITE",
            "REMEMBER_COOKIE_SECURE",
            "REMEMBER_COOKIE_SAME_SITE",
            "ENFORCE_HTTPS",
          ],
        },
        {
          title: translate("admin.env.group.network", "Cache, CORS ve Proxy"),
          icon: "bi-hdd-network",
          keys: ["CACHE_TTL", "CORS_ALLOWED_ORIGINS", "TRUSTED_PROXIES"],
        },
        {
          title: translate("admin.env.group.integrations", "Entegrasyonlar"),
          icon: "bi-plug",
          keys: [
            "RESEND_API_KEY",
            "MAIL_FROM_NAME",
            "MAIL_FROM_ADDRESS",
            "GOOGLE_ANALYTICS_ID",
            "GOOGLE_RECAPTCHA_SITE_KEY",
            "GOOGLE_RECAPTCHA_SECRET_KEY",
            "CLOUDFLARE_TURNSTILE_SITE_KEY",
            "CLOUDFLARE_TURNSTILE_SECRET_KEY",
          ],
        },
      ];
      const booleanKeys = new Set([
        "APP_DEBUG",
        "SESSION_COOKIE_SECURE",
        "REMEMBER_COOKIE_SECURE",
        "ENFORCE_HTTPS",
      ]);
      const numericKeys = new Set([
        "SESSION_LIFETIME",
        "SESSION_COOKIE_LIFETIME",
        "REFRESH_TOKEN_DAYS",
        "CACHE_TTL",
      ]);
      const sensitive = (key) => /(?:PASSWORD|SECRET|TOKEN|KEY)$/.test(key);
      const labels = {
        APP_NAME: translate("admin.env.APP_NAME", "Uygulama adı"),
        APP_ENV: translate("admin.env.APP_ENV", "Çalışma ortamı"),
        APP_DEBUG: translate("admin.env.APP_DEBUG", "Debug modu"),
        APP_URL: translate("admin.env.APP_URL", "Uygulama URL"),
        SITE_ADDRESS: translate("admin.env.SITE_ADDRESS", "Site adresi"),
        APP_TIMEZONE: translate("admin.env.APP_TIMEZONE", "Saat dilimi"),
        SESSION_LIFETIME: translate("admin.env.SESSION_LIFETIME", "Oturum süresi (sn)"),
        SESSION_COOKIE_LIFETIME: translate("admin.env.SESSION_COOKIE_LIFETIME", "Oturum cookie süresi (sn)"),
        REFRESH_TOKEN_DAYS: translate("admin.env.REFRESH_TOKEN_DAYS", "Refresh token süresi (gün)"),
        CACHE_TTL: translate("admin.env.CACHE_TTL", "Cache süresi (sn)"),
        SESSION_COOKIE_SECURE: translate("admin.env.SESSION_COOKIE_SECURE", "Oturum çerezi Secure"),
        SESSION_COOKIE_SAME_SITE: translate("admin.env.SESSION_COOKIE_SAME_SITE", "Oturum çerezi SameSite"),
        ENFORCE_HTTPS: translate("admin.env.ENFORCE_HTTPS", "HTTPS zorunlu"),
        REMEMBER_COOKIE_SECURE: translate("admin.env.REMEMBER_COOKIE_SECURE", "Remember çerezi Secure"),
        REMEMBER_COOKIE_SAME_SITE: translate("admin.env.REMEMBER_COOKIE_SAME_SITE", "Remember çerezi SameSite"),
        CORS_ALLOWED_ORIGINS: translate("admin.env.CORS_ALLOWED_ORIGINS", "CORS izinli adresler"),
        TRUSTED_PROXIES: translate("admin.env.TRUSTED_PROXIES", "Güvenilen proxy adresleri"),
        RESEND_API_KEY: translate("admin.env.RESEND_API_KEY", "Resend API anahtarı"),
        MAIL_FROM_NAME: translate("admin.env.MAIL_FROM_NAME", "Mail gönderici adı"),
        MAIL_FROM_ADDRESS: translate("admin.env.MAIL_FROM_ADDRESS", "Mail gönderici adresi"),
        GOOGLE_ANALYTICS_ID: translate("admin.env.GOOGLE_ANALYTICS_ID", "Google Analytics ID"),
        GOOGLE_RECAPTCHA_SITE_KEY: translate("admin.env.GOOGLE_RECAPTCHA_SITE_KEY", "reCAPTCHA site anahtarı"),
        GOOGLE_RECAPTCHA_SECRET_KEY: translate("admin.env.GOOGLE_RECAPTCHA_SECRET_KEY", "reCAPTCHA gizli anahtarı"),
        CLOUDFLARE_TURNSTILE_SITE_KEY: translate("admin.env.CLOUDFLARE_TURNSTILE_SITE_KEY", "Turnstile site anahtarı"),
        CLOUDFLARE_TURNSTILE_SECRET_KEY: translate("admin.env.CLOUDFLARE_TURNSTILE_SECRET_KEY", "Turnstile gizli anahtarı"),
      };
      const groupsWithFields = groups.map((group) => ({
        title: group.title,
        icon: group.icon,
        fields: group.keys.map((key) => {
          const secret = sensitive(key);
          return {
            key,
            label: labels[key] || key,
            value: String(values[key] ?? ""),
            boolean: booleanKeys.has(key),
            boolean_class: booleanKeys.has(key) ? "" : "d-none",
            text_class: booleanKeys.has(key) ? "d-none" : "",
            input_type: secret
              ? "password"
              : numericKeys.has(key)
              ? "number"
              : key === "MAIL_FROM_ADDRESS"
              ? "email"
              : key === "APP_URL" || key === "SITE_ADDRESS"
              ? "url"
              : "text",
            monospace: secret ? "font-monospace" : "",
            placeholder: secret
              ? translate("admin.env.secret_placeholder", "Değiştirmek istemiyorsanız boş bırakın")
              : "",
          };
        }),
      }));
      mountPartial("panel-env-page-content", target, {
        groups: groupsWithFields,
      });
      target.querySelectorAll("[data-env-key]").forEach((input) => {
        const key = input.dataset.envKey;
        const isBoolean = booleanKeys.has(key);
        input.disabled = (input.dataset.envType === "boolean") !== isBoolean;
        if (input.dataset.envType === "boolean") {
          const raw = String(values[key] ?? "").toLowerCase();
          input.checked = ["true", "1", "yes", "on"].includes(raw);
        }
      });
      const form = target.querySelector("#panel-config-env-form");
      if (form && bindFormAction) registerPageCleanup?.(bindFormAction(form, async () => {
        const payload = {};
        form.querySelectorAll("[data-env-key]").forEach((input) => {
          if (input.disabled) return;
          const key = input.dataset.envKey;
          const value = input.dataset.envType === "boolean"
            ? input.checked ? "true" : "false"
            : input.value;
          if (sensitive(key) && (value === "" || value === "********")) return;
          payload[key] = value;
        });
        await api("/maintenance/env", { method: "POST", body: payload });
        showToast(translate("admin.config.env_saved", ".env kaydedildi"));
        panelNavigate("/panel/config-env");
      }, { onError: (error) => showToast(error.message, "danger") }));
    } catch (error) {
      if (error?.name === "AbortError") return;
      mountPartial("panel-page-error", target, {
        parent_path: "/panel/config",
        error_message: error.message,
      });
    }
  }

  async function loadWebhookPage() {
    const requestEpoch = getPageEpoch();
    const target = document.getElementById("panel-webhook-page");
    if (!target) return;
    try {
      const response = await api("/webhooks");
      assertCurrentPage(requestEpoch);
      const items = responseItems(response).map((item) => ({
        ...item,
        status_label: Number(item.is_active) === 1
          ? translate("admin.status.active", "Aktif")
          : translate("admin.status.inactive", "Pasif"),
      }));
      mountPartial("panel-webhook-page-content", target, {
        items,
        has_items: items.length > 0,
      });
      mountPartial(
        "panel-rows-webhook",
        target.querySelector("#panel-webhook-rows"),
        { items, has_items: items.length > 0 },
      );
      const webhookForm = target.querySelector("#panel-webhook-form");
      if (webhookForm && bindFormAction) registerPageCleanup?.(bindFormAction(webhookForm, async (data) => {
            await api("/webhooks", {
              method: "POST",
              body: Object.fromEntries(data.entries()),
            });
            showToast(translate("admin.config.webhook_created", "Webhook oluşturuldu"));
            await loadWebhookPage();
          }, { onError: (error) => showToast(error.message, "danger") }));
      if (target.dataset.webhookBound !== "1") {
        target.dataset.webhookBound = "1";
        const onClick = async (event) => {
          const node = event.target instanceof Element ? event.target : null;
          const test = node?.closest("[data-test-webhook]");
          const remove = node?.closest("[data-delete-webhook]");
          try {
            if (test) {
              const result = await api(
                `/webhooks/${test.dataset.testWebhook}/test`,
                { method: "POST" },
              );
              showToast(
                result?.data?.success === false
                  ? translate("admin.config.webhook_test_failed", "Webhook testi başarısız")
                  : translate("admin.config.webhook_test_done", "Webhook testi tamamlandı"),
                result?.data?.success === false ? "danger" : "success",
              );
            }
            if (remove && (await confirmAction(translate("admin.confirm.webhook_delete", "Webhook silinsin mi?")))) {
              await api(`/webhooks/${remove.dataset.deleteWebhook}`, {
                method: "DELETE",
              });
              showToast(translate("admin.config.webhook_deleted", "Webhook silindi"));
              await loadWebhookPage();
            }
          } catch (error) {
            if (error?.name === "AbortError") return;
            showToast(error.message, "danger");
          }
        };
        target.addEventListener("click", onClick);
        registerPageCleanup?.(() => {
          target.removeEventListener("click", onClick);
          delete target.dataset.webhookBound;
        });
      }
    } catch (error) {
      if (error?.name === "AbortError") return;
      mountPartial("panel-page-error", target, {
        parent_path: "/panel",
        error_message: error.message,
      });
    }
  }

  async function loadConfigData() {
    const requestEpoch = getPageEpoch();
    store.set("configLoaded", false);
    store.set("configLoadError", "");
    try {
      const response = await api("/config/site");
      assertCurrentPage(requestEpoch);
      const config = response?.data || {};
      config.maintenance_whitelist_text = Array.isArray(
          config.maintenance_whitelist_ips,
        )
        ? config.maintenance_whitelist_ips.join("\n")
        : "";
      store.set("config", config);
      store.set("configLoaded", true);
      const form = document.getElementById("panel-config-form");
      if (form && bindFormAction) {
        registerPageCleanup?.(bindFormAction(form, () => saveConfig(), {
          onError: (error) => showToast(error.message, "danger"),
        }));
      }
      document.getElementById("panel-config-save")?.removeAttribute("disabled");
    } catch (error) {
      if (error?.name === "AbortError") return;
      store.set(
        "configLoadError",
        error.message || translate("admin.error.config_load", "Ayarlar alınamadı."),
      );
      showToast(
        translate("admin.error.config_load_detail", "Ayarlar alınamadı: {message}", {
          message: error.message,
        }),
        "danger",
      );
    }
  }

  async function saveConfig({ event } = {}) {
    event?.preventDefault();
    if (configSaveInFlight) return;
    if (store.get("configLoaded") !== true) {
      showToast(translate("admin.ops.config_not_loaded", "Ayarlar yüklenmeden kaydedilemez."), "warning");
      return;
    }
    configSaveInFlight = true;
    try {
      const payload = { ...store.get("config") };
      payload.maintenance_whitelist_ips = String(
        payload.maintenance_whitelist_text || "",
      )
        .split(/\r?\n|,/)
        .map((value) => value.trim())
        .filter(Boolean);
      delete payload.maintenance_whitelist_text;
      // These values are managed in the root-only .env editor.
      delete payload.site_address;
      delete payload.enforce_https;
      const res = await api("/config/site", {
        method: "POST",
        body: payload,
      });
      if (res?.data) {
        const updated = res.data;
        updated.maintenance_whitelist_text = Array.isArray(
            updated.maintenance_whitelist_ips,
          )
          ? updated.maintenance_whitelist_ips.join("\n")
          : "";
        store.set("config", updated);
      }
      showToast(translate("admin.ops.config_saved", "Site ayarları başarıyla kaydedildi!"));
    } catch (err) {
      if (err?.name === "AbortError") return;
      showToast(err.message, "danger");
    } finally {
      configSaveInFlight = false;
    }
  }

  return Object.freeze({ loadEnvPage, loadWebhookPage, loadConfigData, saveConfig });
}
