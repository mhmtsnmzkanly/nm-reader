/**
 * Theme switcher controller (light / dark / auto) supporting Bootstrap 5.3+ and AdminLTE 4.9+.
 */

export const THEME_STORAGE_KEY = "nm_admin_theme";
export const VALID_THEMES = Object.freeze(["light", "dark", "auto"]);

export function createThemeController({
  storage = globalThis.localStorage,
  documentRef = globalThis.document,
  windowRef = globalThis.window,
  onThemeChange = null,
} = {}) {
  const doc = documentRef;
  const win = windowRef;

  function getStoredTheme() {
    try {
      const stored = storage?.getItem(THEME_STORAGE_KEY);
      return VALID_THEMES.includes(stored) ? stored : "auto";
    } catch {
      return "auto";
    }
  }

  function getSystemTheme() {
    try {
      return win?.matchMedia?.("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    } catch {
      return "light";
    }
  }

  function resolveEffectiveTheme(theme) {
    return theme === "auto" ? getSystemTheme() : theme;
  }

  function updateUi(selectedTheme) {
    if (!doc) return;
    const effective = resolveEffectiveTheme(selectedTheme);
    doc.documentElement?.setAttribute("data-bs-theme", effective);

    // Update active icon
    const activeIcon = doc.getElementById("theme-icon-active");
    if (activeIcon) {
      activeIcon.className = selectedTheme === "light"
        ? "bi bi-sun-fill my-1 theme-icon-active"
        : selectedTheme === "dark"
        ? "bi bi-moon-stars-fill my-1 theme-icon-active"
        : "bi bi-circle-half my-1 theme-icon-active";
    }

    // Update active dropdown item
    doc.querySelectorAll("[data-bs-theme-value]").forEach((button) => {
      const value = button.getAttribute("data-bs-theme-value");
      const isActive = value === selectedTheme;
      button.classList.toggle("active", isActive);
      button.setAttribute("aria-pressed", String(isActive));
    });
  }

  function setTheme(theme) {
    const validTheme = VALID_THEMES.includes(theme) ? theme : "auto";
    try {
      storage?.setItem(THEME_STORAGE_KEY, validTheme);
    } catch {
      // Storage unavailable or quota exceeded
    }
    updateUi(validTheme);
    onThemeChange?.(resolveEffectiveTheme(validTheme), validTheme);
  }

  function init() {
    const initialTheme = getStoredTheme();
    updateUi(initialTheme);

    const onStorageClick = (event) => {
      const button = event.target?.closest?.("[data-bs-theme-value]");
      if (!button) return;
      event.preventDefault();
      const theme = button.getAttribute("data-bs-theme-value");
      if (theme) setTheme(theme);
    };

    doc?.addEventListener("click", onStorageClick);

    // Listen to system color scheme changes when theme is auto
    const mediaQuery = win?.matchMedia?.("(prefers-color-scheme: dark)");
    const onMediaChange = () => {
      if (getStoredTheme() === "auto") {
        updateUi("auto");
        onThemeChange?.(getSystemTheme(), "auto");
      }
    };
    mediaQuery?.addEventListener?.("change", onMediaChange);

    return () => {
      doc?.removeEventListener("click", onStorageClick);
      mediaQuery?.removeEventListener?.("change", onMediaChange);
    };
  }

  return Object.freeze({
    getStoredTheme,
    getSystemTheme,
    resolveEffectiveTheme,
    setTheme,
    init,
  });
}
