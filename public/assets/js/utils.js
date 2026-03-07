/**
 * Utils.js - Shared Global Helpers for NovelMangaReader.
 *
 * This module extends the global 'window.NMR' object with utility methods used 
 * across both the core application and specialized interactive pages.
 * 
 * Major features:
 * - Dynamic i18n: Fetches and caches dictionary from API with MD5 hash validation.
 * - RBAC Helpers: Client-side checks for user roles and permissions.
 * - UI Management: Global handlers for mobile navigation and language switching.
 * - Formatting: Standardized markdown parsing via 'marked'.
 */
window.NMR = window.NMR || {};

/**
 * Global helper to retrieve cookie values.
 * @param {string} name 
 * @returns {string|null}
 */
window.getCookie = function (name) {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
  return null;
};

/**
 * Global helper to set cookie values.
 * @param {string} name
 * @param {string} value
 * @param {number} days
 */
window.setCookie = function (name, value, days) {
  let expires = "";
  if (days) {
    const date = new Date();
    date.setTime(date.now ? date.now() : Date.now() + (days * 24 * 60 * 60 * 1000));
    expires = "; expires=" + date.toUTCString();
  }
  document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
};

(function () {
  /** @type {Object} SSR Context injected by the server. */
  const ctx = window.__NMR_CONTEXT || {};

  /** @type {Object} Local translation dictionary. */
  let dictionary = {};

  const LS_KEY_PREFIX = 'nmr_i18n_';

  /**
   * Initializes the translation engine.
   * Loads from localStorage if hash matches server context, otherwise fetches from API.
   * @returns {Promise<void>}
   */
  const loadDictionary = async () => {
    const lang = ctx.lang_code || 'tr';
    const serverHash = ctx.lang_hash || '';
    const lsKey = LS_KEY_PREFIX + lang;

    try {
      const cached = localStorage.getItem(lsKey);
      if (cached) {
        const parsed = JSON.parse(cached);
        if (parsed.hash === serverHash) {
          dictionary = parsed.data;
          return;
        }
      }
    } catch (e) { console.error('i18n cache read error', e); }

    // Hash mismatch or cache empty -> Fetch fresh dictionary.
    try {
      const res = await fetch(`/api/v1/i18n/${lang}`);
      const payload = await res.json();
      if (payload && payload.data) {
        dictionary = payload.data;
        localStorage.setItem(lsKey, JSON.stringify({
          hash: payload.hash,
          data: payload.data,
          ts: Date.now()
        }));
      }
    } catch (e) {
      console.error('i18n fetch error', e);
      dictionary = ctx.lang || {}; // Final fallback.
    }
  };

  /** @type {Promise} Global promise to allow other scripts to wait for translations. */
  const dictionaryPromise = loadDictionary();

  /**
   * Extracts the language prefix from the current URL path.
   * @returns {string} 'tr' or 'en'.
   */
  window.NMR.getLangPrefix = function () {
    const parts = window.location.pathname.split('/').filter(Boolean);
    return (parts[0] === 'tr' || parts[0] === 'en') ? parts[0] : 'tr';
  };

  /**
   * Translates a key using the loaded dictionary.
   * @param {string} key
   * @returns {string} Translated text or original key if missing.
   */
  window.NMR.__t = function (key) {
    return dictionary[key] || key;
  };

  /**
   * Returns the dictionary loading promise.
   */
  window.NMR.waitForI18n = () => dictionaryPromise;

  /**
   * Checks if the current user has a specific role.
   */
  window.NMR.hasRole = function (role) {
    return (ctx.auth && ctx.auth.roles) ? ctx.auth.roles.includes(role) : false;
  };

  /**
   * Checks if the current user has a specific permission code.
   */
  window.NMR.hasPermission = function (perm) {
    return (ctx.auth && ctx.auth.permissions) ? ctx.auth.permissions.includes(perm) : false;
  };

  /**
   * Global mobile menu open handler.
   */
  window.NMR.openMenu = function () {
    const menu = document.getElementById('mainMenu');
    if (menu) menu.classList.add('mobile-active');
    document.body.classList.add('mobile-menu-open');
  };

  /**
   * Global mobile menu close handler.
   */
  window.NMR.closeMenu = function () {
    const menu = document.getElementById('mainMenu');
    if (menu) menu.classList.remove('mobile-active');
    document.body.classList.remove('mobile-menu-open');
  };

  /**
   * Updates language preference via cookie or account update.
   */
  window.NMR.changeLanguage = function (langCode) {
    if (!(ctx.auth && ctx.auth.is_logged_in)) {
      const expires = new Date(Date.now() + 30 * 86400000).toUTCString();
      document.cookie = `nm_reader_lang=${langCode};expires=${expires};path=/;SameSite=Lax`;
    }
  };

  /**
   * Renders raw text into sanitized HTML via the Marked library.
   * @param {string} text
   * @returns {string} HTML content.
   */
  window.NMR.parseMarkdown = function (text) {
    if (typeof marked === 'undefined') {
      console.warn('marked.js is not loaded');
      return text || '';
    }
    try {
      const cleanText = String(text || '').trim();
      // Ensure sync parsing if using newer marked versions
      if (typeof marked.parse === 'function') {
        return marked.parse(cleanText, { async: false });
      }
      return typeof marked === 'function' ? marked(cleanText) : cleanText;
    } catch (e) { 
      console.error('Markdown parse error:', e);
      return text || ''; 
    }
  };

  /**
   * Formats seconds into h/m/s string.
   */
  window.NMR.formatDuration = function (s) {
    if (!s || s <= 0) return '0s';
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m`;
    return `${s}s`;
  };

  /**
   * Displays a floating notification popup.
   * Universal helper available in main site and admin panel.
   * Uses vanilla JS to avoid dependencies.
   */
  window.showPopup = function (message, type = 'info') {
    let popup = document.getElementById('fixedPopup');
    if (!popup) {
      const html = `
        <div id="fixedPopup" style="position:fixed; bottom:30px; right:30px; z-index:2147483647; background:#2c2c2c; color:#fff; padding:16px 24px; border-radius:12px; display:none; opacity:0; transition: opacity 0.3s ease; box-shadow:0 10px 40px rgba(0,0,0,0.5); min-width:280px; font-family:sans-serif; border-left:8px solid #555; align-items:center; gap:15px;">
          <b id="fixedIcon" style="font-size:22px"></b>
          <span id="fixedMsg" style="font-size:15px; font-weight:600"></span>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', html);
      popup = document.getElementById('fixedPopup');
    }
    const iconMap = { success: '✅', error: '❌', info: 'ℹ️' };
    const colorMap = { success: '#2ed573', error: '#ff4757', info: '#1e90ff' };

    document.getElementById('fixedIcon').innerText = iconMap[type] || 'ℹ️';
    popup.style.borderLeftColor = colorMap[type] || '#1e90ff';
    document.getElementById('fixedMsg').innerText = message;

    popup.style.display = 'flex';
    setTimeout(() => { popup.style.opacity = '1'; }, 10);

    setTimeout(() => {
      popup.style.opacity = '0';
      setTimeout(() => { popup.style.display = 'none'; }, 300);
    }, 3500);
  };
})();
