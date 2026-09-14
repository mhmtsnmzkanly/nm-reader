const preferenceKey = "nmr.admin.locale";
const languages = { tr: "Türkçe", en: "English" };

export function savedPanelLocale(supported, fallback, storage) {
  try {
    const saved = (storage ?? globalThis.localStorage)?.getItem(preferenceKey);
    return supported.includes(saved) ? saved : fallback;
  } catch {
    return fallback;
  }
}

export function bindLanguageSelector({
  select, supported, i18n, onChange, onError,
  documentRef = globalThis.document,
}) {
  if (!select) return () => {};
  select.replaceChildren(...supported.map((code) => {
    const option = documentRef.createElement("option");
    option.value = code;
    option.textContent = languages[code] || code;
    return option;
  }));
  select.value = i18n.locale();
  select.disabled = supported.length < 2;
  let active = true;
  let pending = false;
  const change = async () => {
    if (pending) return;
    const next = select.value;
    if (!supported.includes(next)) { select.value = i18n.locale(); return; }
    pending = true;
    select.disabled = true;
    select.setAttribute("aria-busy", "true");
    try {
      await i18n.load(next);
      if (!active) return;
      i18n.setLocale(next);
      documentRef.documentElement.lang = i18n.locale();
      try { documentRef.defaultView?.localStorage.setItem(preferenceKey, i18n.locale()); } catch { /* Storage may be disabled. */ }
      onChange();
    } catch (error) {
      if (active) onError(error);
    } finally {
      pending = false;
      if (active) {
        select.value = i18n.locale();
        select.disabled = supported.length < 2;
        select.removeAttribute("aria-busy");
      }
    }
  };
  select.addEventListener("change", change);
  return () => { active = false; select.removeEventListener("change", change); };
}
