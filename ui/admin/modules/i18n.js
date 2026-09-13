export function createI18n(
  {
    locale = "en",
    fallbackLocale = "en",
    supported = [],
    initial = {},
    initialDictionaries = {},
    fetchImpl = globalThis.fetch,
    basePath = "/api/v1/i18n",
  } = {},
) {
  const dictionaries = new Map();
  const pending = new Map();
  const allowed = new Set(supported);
  const canonical = (value) => String(value || "").toLowerCase().split("-")[0];
  const normalize = (value) => {
    const candidate = canonical(value);
    return !allowed.size || allowed.has(candidate) ? candidate : fallbackLocale;
  };
  locale = normalize(locale);
  fallbackLocale = normalize(fallbackLocale);
  if (initialDictionaries && typeof initialDictionaries === "object") {
    for (const [language, dictionary] of Object.entries(initialDictionaries)) {
      if (dictionary && typeof dictionary === "object" && !Array.isArray(dictionary)) {
        const candidate = canonical(language);
        // An embedded dictionary for an unsupported locale must not silently
        // overwrite the fallback dictionary during normalization.
        if (!allowed.size || allowed.has(candidate)) {
          dictionaries.set(candidate, { ...dictionary });
        }
      }
    }
  }
  if (Object.keys(initial).length) dictionaries.set(locale, { ...initial });
  function load(value = locale) {
    const language = normalize(value);
    if (dictionaries.has(language)) return dictionaries.get(language);
    if (pending.has(language)) return pending.get(language);
    if (typeof fetchImpl !== "function") return {};
    const request = fetchImpl(`${basePath}/${encodeURIComponent(language)}`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    }).then(async (response) => {
      if (!response.ok) {
        throw new Error(`Translation request failed (${response.status}).`);
      }
      let payload;
      try {
        payload = await response.json();
      } catch {
        throw new Error("Translation response was not valid JSON.");
      }
      const dictionary = payload?.data && typeof payload.data === "object" &&
          !Array.isArray(payload.data)
        ? payload.data
        : null;
      if (!dictionary) {
        throw new Error("Translation response did not contain a dictionary.");
      }
      dictionaries.set(language, { ...dictionary });
      return dictionaries.get(language);
    }).finally(() => pending.delete(language));
    pending.set(language, request);
    return request;
  }
  function translate(key, params = {}) {
    const name = String(key || "");
    let message = (dictionaries.get(locale) || {})[name] ??
      (dictionaries.get(fallbackLocale) || {})[name] ?? name;
    // Translation payloads are external data; normalize non-string values so
    // one malformed dictionary entry cannot break an entire panel render.
    message = String(message);
    for (const [param, value] of Object.entries(params)) {
      message = message.replaceAll(`{${param}}`, String(value)).replaceAll(
        `:${param}`,
        String(value),
      );
    }
    return message;
  }
  function has(key) {
    const name = String(key);
    return Object.hasOwn(dictionaries.get(locale) || {}, name) ||
      Object.hasOwn(dictionaries.get(fallbackLocale) || {}, name);
  }
  function refresh(root = globalThis.document) {
    if (!root?.querySelectorAll) return;
    root.querySelectorAll("[data-i18n]").forEach((element) => {
      const key = element.getAttribute("data-i18n");
      if (key && has(key)) element.textContent = translate(key);
    });
    root.querySelectorAll("[data-i18n-attr]").forEach((element) => {
      for (const pair of String(element.getAttribute("data-i18n-attr") || "").split(",")) {
        const [attribute, key] = pair.split(":").map((part) => part.trim());
        if (attribute && key && has(key)) element.setAttribute(attribute, translate(key));
      }
    });
  }
  return Object.freeze({
    load,
    t: translate,
    refresh,
    locale: () => locale,
    fallbackLocale: () => fallbackLocale,
    setLocale: (value) => {
      locale = normalize(value);
      return locale;
    },
    has,
  });
}
