/**
 * app-bundle.js - Unified Frontend Controller for NovelMangaReader.
 *
 * This bundle consolidates core utilities, API bridges, and page-specific logic
 * into a single file with intelligent routing.
 */

// 1. Core Utilities & Global State (formerly utils.js, api.js, connection.js)
// --- Included via concatenation logic during build or manual merge ---

(function() {
  /**
   * App - Global Namespace for Platform Logic
   */
  window.App = {
    Modules: {},
    
    /**
     * Central Router: Initializes modules based on current URL path.
     */
    init: function() {
      const path = window.location.pathname;
      const lang = (path.split('/')[1] === 'tr' || path.split('/')[1] === 'en') ? '/' + path.split('/')[1] : '';
      const parts = path.replace(lang, '').split('/').filter(Boolean);
      
      console.log("[App] Route Detected:", parts.join('/') || 'home');

      // Always initialize Global (formerly main.js)
      if (this.Modules.Global && typeof this.Modules.Global.init === 'function') {
        this.Modules.Global.init();
      }

      // Page Specific Routing
      if (parts.length === 0) {
        if (this.Modules.Home) this.Modules.Home.init();
      } else if (parts[0] === 'blogs' || (parts[0] === 'blog' && parts.length > 1)) {
        if (this.Modules.Blog) this.Modules.Blog.init();
      } else if (parts.length >= 2 && ['manga','novel','webtoon','manhua','manhwa','light-novel','web-novel'].includes(parts[0])) {
        // Content or Chapter View
        if (parts.includes('chapter')) {
          if (this.Modules.Reader) this.Modules.Reader.init();
        } else {
          if (this.Modules.Content) this.Modules.Content.init();
        }
      } else if (parts[0] === 'search') {
        if (this.Modules.Search) this.Modules.Search.init();
      } else if (parts[0] === 'profile') {
        if (this.Modules.Profile) this.Modules.Profile.init();
      } else if (parts[0] === 'chat') {
        if (this.Modules.Chat) this.Modules.Chat.init();
      } else if (['manga','novel','webtoon','manhua','manhwa','light-novel','web-novel'].includes(parts[0]) && parts.length === 1) {
        if (this.Modules.SeriesList) this.Modules.SeriesList.init();
      }
    }
  };
})();

// --- The following would be the merged content of all JS files, wrapped in App.Modules ---
// Since I cannot practically merge 12 large files in one 'write_file' call without hitting context limits,
// I will use a strategy to wrap each existing file's logic.
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
'use strict';

const ApiClient = {
  csrfToken: null,

  async request(path, options = {}) {
    const headers = {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    };

    if (this.csrfToken) {
      headers['X-CSRF-Token'] = this.csrfToken;
    }

    const response = await fetch(path, {
      credentials: 'include',
      ...options,
      headers
    });

    const payload = await response.json();
    if (payload.status === 'error') {
      throw new Error(payload.error?.message || 'API error');
    }

    return payload;
  },

  setCsrfFromAuth(data) {
    if (data && data.csrf_token) {
      this.csrfToken = data.csrf_token;
      sessionStorage.setItem('csrf_token', data.csrf_token);
    }
  },

  hydrateToken() {
    const saved = sessionStorage.getItem('csrf_token');
    if (saved) {
      this.csrfToken = saved;
    }
  }
};

function writeJson(targetId, data) {
  const target = document.getElementById(targetId);
  if (!target) return;
  target.textContent = JSON.stringify(data, null, 2);
}

async function loadHome() {
  const payload = await ApiClient.request('/api/v1/home');
  writeJson('app', payload);
}

async function loadContent(type, slug) {
  if (!type || !slug) {
    throw new Error('type ve slug zorunlu');
  }

  const payload = await ApiClient.request(`/api/v1/content/${type}/${slug}`);
  writeJson('app', payload);
}

async function loadContentWithChapters(type, slug) {
  if (!type || !slug) {
    throw new Error('type ve slug zorunlu');
  }

  const contentPath = `/api/v1/content/${type}/${slug}`;
  const chaptersPath = `/api/v1/content/${type}/${slug}/chapters`;

  const [contentPayload, chaptersPayload] = await Promise.all([
    ApiClient.request(contentPath),
    ApiClient.request(chaptersPath)
  ]);

  writeJson('app', {
    status: 'success',
    data: {
      content: contentPayload.data,
      chapters: chaptersPayload.data
    },
    meta: {
      content_meta: contentPayload.meta || {},
      chapter_meta: chaptersPayload.meta || {}
    },
    error: null
  });
}

async function loadChapter(type, slug, number) {
  if (typeof number === 'undefined') {
    number = type;
    const payload = await ApiClient.request(`/api/v1/chapter/${number}`);
    writeJson('app', payload);
    return;
  }

  const payload = await ApiClient.request(`/api/v1/content/${type}/${slug}/chapter/${number}`);
  writeJson('app', payload);
}

async function login(email, password) {
  const payload = await ApiClient.request('/api/v1/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password })
  });

  ApiClient.setCsrfFromAuth(payload.data);
  writeJson('result', payload);
}

async function loadProfile() {
  const payload = await ApiClient.request('/api/v1/user/profile');
  writeJson('app', payload);
}

window.ApiClient = ApiClient;
window.loadHome = loadHome;
window.loadContent = loadContent;
window.loadContentWithChapters = loadContentWithChapters;
window.loadChapter = loadChapter;
window.login = login;
window.loadProfile = loadProfile;
/**
 * Connection.js - Central API Bridge for NovelMangaReader.
 *
 * This module handles all AJAX/Fetch interactions with the backend API.
 * Key features:
 * - Request Deduplication: In-flight requests are tracked to prevent duplicate calls.
 * - CSRF Management: Automatically attaches 'X-CSRF-Token' and updates it from responses.
 * - Error Normalization: Standardizes API errors into catchable JavaScript Errors.
 * - Modular Endpoints: Grouped methods for Auth, Content, Blogs, and User operations.
 */
const Connection = (function () {
  const BASE_URL = '/api/v1';

  /**
   * Extracts CSRF token from the globally injected SSR context.
   */
  const getContextToken = () => {
    try {
      return window.__NMR_CONTEXT?.auth?.csrf_token || null;
    } catch (e) { return null; }
  };

  /** @type {string|null} Current active CSRF token. */
  let csrfToken = getContextToken() || sessionStorage.getItem('csrf_token') || null;

  /** @type {Map<string, Promise>} Map of active requests to prevent redundancy. */
  const inFlight = new Map();

  /**
   * Updates the internal and persisted CSRF token.
   * @param {string} token
   */
  const setCsrfToken = (token) => {
    if (!token) return;
    csrfToken = token;
    sessionStorage.setItem('csrf_token', token);
  };

  /**
   * Generic request wrapper around window.fetch.
   * 
   * @param {string} path API endpoint path.
   * @param {Object} options Fetch options (method, body, headers).
   * @returns {Promise<Object>} Standardized JSON response.
   */
  const request = async (path, options = {}) => {
    // Always check for a fresh token from the context first, fallback to current or session
    csrfToken = getContextToken() || csrfToken || sessionStorage.getItem('csrf_token') || null;

    const cacheKey = `${options.method || 'GET'}:${path}`;
    if (inFlight.has(cacheKey)) return inFlight.get(cacheKey);

    const promise = (async () => {
      try {
        const headers = { ...(options.headers || {}) };
        if (options.body !== undefined && !(options.body instanceof FormData)) {
          if (!headers['Content-Type']) headers['Content-Type'] = 'application/json';
        }
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

        const response = await fetch(`${BASE_URL}${path}`, {
          credentials: 'include',
          ...options,
          headers
        });

        // Always try to sync CSRF token from response headers if available
        const respCsrf = response.headers.get('X-CSRF-Token');
        if (respCsrf) {
          setCsrfToken(respCsrf);
        }

        let payload;
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
          payload = await response.json();
        } else {
          const text = await response.text();
          payload = { status: 'error', error: { message: text || `Request failed (${response.status})` } };
        }

        // Auto-update CSRF token if returned in response data.
        if (payload?.data?.csrf_token) setCsrfToken(payload.data.csrf_token);

        if (!response.ok || payload.status === 'error') {
          const msg = payload?.error?.message || payload?.message || "Bir hata oluştu";
          const error = new Error(msg);
          error.status = response.status;
          error.payload = payload;
          throw error;
        }

        return payload;
      } finally {
        inFlight.delete(cacheKey);
      }
    })();

    inFlight.set(cacheKey, promise);
    return promise;
  };

  return {
    request,
    setCsrfToken,

    // --- Public / Discovery ---
    getHome: () => request('/home'),
    getLatestChapters: (page = 1, perPage = 5) => request(`/latest-chapters?page=${page}&per_page=${perPage}`),
    search: (query, page = 1) => request(`/search?q=${encodeURIComponent(query)}&page=${page}`),

    // --- Content (Series & Chapters) ---
    getByType: (type, page = 1) => request(`/content/type/${encodeURIComponent(type)}?page=${page}`),
    getContentDetail: (type, slug) => request(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}`),
    getChapters: (type, slug) => request(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/chapters`),
    getChapterDetail: (type, slug, chapterNumber) => request(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/chapter/${encodeURIComponent(chapterNumber)}`),

    // --- Taxonomy (Genres & Tags) ---
    getGenres: (page = 1, perPage = 15) => request(`/series_genres?page=${page}&per_page=${perPage}`),
    getByGenre: (slug, page = 1) => request(`/genre/${encodeURIComponent(slug)}?page=${page}`),
    getTags: (page = 1, perPage = 15) => request(`/series_tags?page=${page}&per_page=${perPage}`),
    getByTag: (slug, page = 1) => request(`/tag/${encodeURIComponent(slug)}?page=${page}`),

    // --- Blog Platform ---
    getBlogs: (page = 1, perPage = 20) => request(`/blogs?page=${page}&per_page=${perPage}`),
    getBlog: (slug) => request(`/blogs/${encodeURIComponent(slug)}`),
    createBlog: (data) => request('/blogs', { method: 'POST', body: JSON.stringify(data) }),
    voteBlog: (slug, vote) => request(`/blogs/${encodeURIComponent(slug)}/vote`, {
      method: 'POST',
      body: JSON.stringify({ vote })
    }),
    getMyBlogs: () => request('/user/blogs'),
    uploadBlogImage: (formData) => request('/blogs/image', { method: 'POST', body: formData }),

    // --- Social (Comments) ---
    getChapterComments: (chapterId, page = 1, perPage = 20) => request(`/chapter/${encodeURIComponent(chapterId)}/comments?page=${page}&per_page=${perPage}`),
    postChapterComment: (chapterId, body, parentId = null) => request(`/chapter/${encodeURIComponent(chapterId)}/comment`, {
      method: 'POST',
      body: JSON.stringify(parentId ? { body, parent_id: parentId } : { body })
    }),
    getContentComments: (type, slug, page = 1, perPage = 20) => request(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/comments?page=${page}&per_page=${perPage}`),
    postContentComment: (type, slug, body, parentId = null) => request(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/comment`, {
      method: 'POST',
      body: JSON.stringify(parentId ? { body, parent_id: parentId } : { body })
    }),
    getBlogComments: (slug) => request(`/blogs/${encodeURIComponent(slug)}/comments`),
    postBlogComment: (slug, body, parentId = null) => request(`/blogs/${encodeURIComponent(slug)}/comments`, {
      method: 'POST',
      body: JSON.stringify(parentId ? { body, parent_id: parentId } : { body })
    }),
    voteBlogComment: (slug, commentId, vote) => request(`/blogs/${encodeURIComponent(slug)}/comments/${encodeURIComponent(commentId)}/vote`, {
      method: 'POST',
      body: JSON.stringify({ vote })
    }),
    voteComment: (commentId, vote) => request(`/comments/${encodeURIComponent(commentId)}/vote`, {
      method: 'POST',
      body: JSON.stringify({ vote })
    }),

    // --- Authentication ---
    login: (email, password, remember = false, turnstileToken = null) => request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password, remember, turnstile_token: turnstileToken })
    }),
    register: (username, email, password, turnstileToken = null) => request('/auth/register', {
      method: 'POST',
      body: JSON.stringify({ username, email, password, turnstile_token: turnstileToken })
    }),
    logout: () => request('/auth/logout', { method: 'POST' }),
    refresh: (refreshToken) => request('/auth/refresh', { method: 'POST', body: JSON.stringify({ refresh_token: refreshToken }) }),
    getSessions: () => request('/auth/sessions'),
    revokeSession: (sessionKey) => request(`/auth/sessions/${encodeURIComponent(sessionKey)}`, { method: 'DELETE' }),

    // --- User Profile & Preferences ---
    getPublicProfile: (person) => request(`/profile/${encodeURIComponent(person)}`),
    getMyProfile: () => request('/user/profile'),
    updateProfile: (data) => request('/user/profile', {
      method: 'POST',
      body: data instanceof FormData ? data : JSON.stringify(data),
      // If it's FormData, let the browser set the Content-Type header with the boundary
      headers: data instanceof FormData ? { 'X-CSRF-Token': csrfToken } : { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }
    }),
    getHistory: () => request('/user/history'),
    getPreferences: () => request('/user/preferences'),
    updatePreferences: (data) => request('/user/preferences', { method: 'PUT', body: JSON.stringify(data) }),
    getFollowedContent: () => request('/user/follows'),
    getFollowedUsers: () => request('/user/follows/users'),
    followUser: (person) => request(`/user/follows/${encodeURIComponent(person)}`, { method: 'POST' }),
    unfollowUser: (person) => request(`/user/follows/${encodeURIComponent(person)}`, { method: 'DELETE' }),
    followContent: (type, slug) => request(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/follow`, { method: 'POST', body: '{}' }),
    unfollowContent: (type, slug) => request(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/follow`, { method: 'DELETE' }),
    rateContent: (type, slug, rating) => request(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/rate`, {
      method: 'POST',
      body: JSON.stringify({ rating })
    }),
    getNotifications: () => request('/user/notifications'),
    markNotificationsRead: () => request('/user/notifications/read', { method: 'POST' }),

    // --- Activity Tracking ---
    trackActivity: (tabId, durationSeconds) => {
      const fd = new FormData();
      fd.append('tab_id', tabId);
      fd.append('duration', durationSeconds);
      // We use sendBeacon directly here rather than the fetch wrapper for reliability on page unload
      return navigator.sendBeacon('/api/v1/user/activity', fd);
    }
  };
})();
App.Modules.Global = { init: function() {
/**
 * main.js - Primary UI Controller for NovelMangaReader.
 *
 * This script initializes the global platform interface. It handles:
 * - Auth Hydration: Syncs local UI state with server-side session.
 * - Global Modals: Management of Login, Register, Notifications, and Reader Settings.
 * - Popup System: A lightweight, non-blocking notification system.
 * - Preferences Sync: Applies themes and reader typography settings globally.
 * - Global Search: Manages the site-wide search input and redirection.
 */

window.NMR = window.NMR || {};

/**
 * Global helpers for Modals.
 * Moved outside DOMReady to be available for early onclick handlers.
 */
window.openModal = (id) => {
  $('.modal-overlay').removeClass('active');
  const ctx = window.__NMR_CONTEXT || {};

  // Pre-populate reader settings from context or cookies.
  if (id === 'readerSettingsModal') {
    const r = (ctx.auth && ctx.auth.preferences) ? ctx.auth.preferences.reader : {};
    const layout = window.getCookie('melt_reader_layout') || r.layout || 'vertical';
    $('#readerLayoutSelect').val(layout);
    $('[name="reader_image_fit"]').val(getCookie('melt_reader_imageFit') || r.imageFit || 'width');
    $('[name="reader_font_family"]').val(getCookie('melt_reader_fontFamily') || r.fontFamily || 'var(--font-sans)');
    const fSize = getCookie('melt_reader_fontSize') || r.fontSize || '18';
    $('[name="reader_font_size"]').val(fSize);
    $('#fontSizeVal').text(fSize);
  }

  if (id === 'userSettingsModal' && window.__NMR_CONTEXT?.user) {
    const u = window.__NMR_CONTEXT.user;
    const p = window.__NMR_CONTEXT.preferences || {};
    $('#userSettingsForm [name="bio"]').val(u.bio || '');
    $('#userSettingsForm [name="theme"]').val(p.theme || 'default');
    $('#userSettingsForm [name="lang"]').val(p.lang || 'tr');
  }

  setTimeout(() => {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
  }, 30);
};

window.closeModal = () => { $('.modal-overlay').removeClass('active'); };

$(function () {
  const ctx = window.__NMR_CONTEXT || {};

  /**
   * Activity Tracker - Monitors focused time and reports via Beacon.
   */
  const ActivityTracker = (() => {
    if (!(ctx.auth && ctx.auth.is_logged_in)) return null;

    const tabId = Math.random().toString(36).substring(2, 15);
    const userId = ctx.auth.user_id;
    let accumulatedTime = 0;
    let lastStartTime = Date.now();
    let isVisible = true;

    const flush = () => {
      const activeNow = isVisible ? Math.floor((Date.now() - lastStartTime) / 1000) : 0;
      const total = accumulatedTime + activeNow;
      if (total <= 0) return;

      if (window.Connection && window.Connection.trackActivity) {
        window.Connection.trackActivity(tabId, total);
      }

      // Reset after flush (e.g. if page remains open)
      accumulatedTime = 0;
      lastStartTime = Date.now();
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        lastStartTime = Date.now();
        isVisible = true;
      } else {
        accumulatedTime += Math.floor((Date.now() - lastStartTime) / 1000);
        isVisible = false;
        flush(); // Flush on hide to ensure data is caught if session ends abruptly
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    // pagehide is the most reliable event for both desktop and mobile session ending.
    window.addEventListener('pagehide', flush);

    return { tabId };
  })();

  // Sync CSRF token from server context to the API bridge.
  if (ctx.auth && ctx.auth.csrf_token) {
    Connection.setCsrfToken(ctx.auth.csrf_token);
  }

  /** @type {string|null} Current logged-in username for UI display. */
  window.NMR.currentUser = (ctx.auth && ctx.auth.is_logged_in)
    ? (ctx.auth.username || ctx.auth.user_id || 'User')
    : null;

  const FALLBACK_IMG = '/adminlte/assets/img/user1-128x128.jpg';

  /**
   * Updates the site theme and persists it to the user account.
   */
  window.setTheme = function (themeName) {
    $('body').attr('theme', themeName);
    if (window.NMR.currentUser) {
      Connection.updatePreferences({ theme: themeName })
        .catch(err => console.error('Failed to save theme:', err));
    }
  };

  /**
   * Synchronizes typography styles for novel/text content based on user preferences.
   */
  window.NMR.syncReaderStyles = () => {
    const context = window.__NMR_CONTEXT || {};
    if (context.auth && context.auth.preferences) {
      const r = context.auth.preferences.reader || {};
      const novelContent = $('.novel-content');
      if (novelContent.elements.length) {
        novelContent.css({
          'font-family': r.fontFamily || 'var(--font-sans)',
          'font-size': (r.fontSize || 18) + 'px',
          'font-weight': r.fontWeight || 400,
          'line-height': r.lineHeight || 1.8
        });
      }
    }
  };

  // NMR Global Helpers (Extended)
  window.NMR = Object.assign(window.NMR || {}, {
    /**
     * Dynamically renders popular and latest content in the global footer.
     */
    renderFooterData: async () => {
      const popularTarget = $('#footerPopular');
      const latestTarget = $('#footerLatest');
      if (!popularTarget.elements.length) return;
      try {
        const [chaptersRes, homeRes] = await Promise.all([
          Connection.getLatestChapters(1, 5),
          Connection.getHome()
        ]);

        const getTypeColor = (type) => {
          if (type.includes('novel')) return 'var(--success)';
          if (type === 'manga') return 'var(--primary)';
          if (type === 'webtoon') return 'var(--info)';
          return 'var(--warning)';
        };

        const langPrefix = window.NMR.getLangPrefix();
        const popularHtml = (homeRes.data.explore || []).slice(0, 8).map(c => {
          const typeLabel = c.type_path.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
          const localizedUrl = `/${langPrefix}/${c.type_path}/${c.slug}`;
          return `
            <a href="${localizedUrl}" class="nav-link footer-link-small">
              <span class="text-muted" style="font-size: 0.7rem; font-weight: 700; min-width: 70px">${typeLabel}</span>
              <span class="type-dot" style="--dot-color: ${getTypeColor(c.type_path)}"></span>
              <span>${c.title}</span>
            </a>
          `;
        }).join('');
        popularTarget.html(popularHtml || '<span class="text-muted">Yüklenemedi.</span>');

        const latestHtml = (chaptersRes.data || []).map(ch => {
          const localizedUrl = `/${langPrefix}/${ch.type_path}/${ch.series_slug}/chapter/${ch.chapter_number}`;
          return `
            <a href="${localizedUrl}" class="nav-link footer-link-small truncate">
              <span>📖 ${ch.series_title} - Ch. ${ch.chapter_number}</span>
            </a>
          `;
        }).join('');
        latestTarget.html(latestHtml || '<span class="text-muted">Yüklenemedi.</span>');
      } catch (err) { console.error('Footer data error:', err); }
    }
  });

  /**
   * Updates the header authentication UI (dropdown vs buttons).
   */
  const syncHeaderAuth = () => {
    const authTarget = $('#headerAuthLinks');
    const lang = window.NMR.getLangPrefix();
    if (!authTarget.elements.length) return;
    if (window.NMR.currentUser) {
      const adminLink = (ctx.auth && ctx.auth.is_admin) ? `<a href="/${lang}/admin" class="dropdown-item text-warning">🛠 ${NMR.__t('admin_panel')}</a>` : '';
      authTarget.html(`
        <div class="dropdown">
          <button class="nav-link dropdown-toggle btn-none">👤 ${window.NMR.currentUser} <span id="headerNotifBadge" class="badge bg-danger hidden">0</span></button>
          <div class="dropdown-menu card p-2" style="right: 0; left: auto; min-width: 200px">
            <a href="/${lang}/profile" class="dropdown-item">👤 ${NMR.__t('my_profile')}</a>
            <a href="#" class="dropdown-item" id="headerNotifBtn">🔔 ${NMR.__t('notifications')}</a>
            <a href="#" class="dropdown-item" onclick="openModal('readerSettingsModal');return false;">⚙️ ${NMR.__t('reader_settings')}</a>
            ${adminLink}
            <hr class="my-1 border-0 border-t opacity-10">
            <a class="dropdown-item text-danger w-100 text-left" href="/logout">🚪 ${NMR.__t('logout')}</a>
          </div>
        </div>
      `);
      Connection.getNotifications().then(res => {
        const unread = (res.data || []).filter(n => !n.is_read).length;
        if (unread > 0) $('#headerNotifBadge').text(unread).removeClass('hidden');
      }).catch(() => { });
    } else {
      authTarget.html(`
        <a href="#" class="nav-link" onclick="openModal('loginModal');return false;">${NMR.__t('login')}</a>
        <a href="#" class="btn btn-sm btn-primary" onclick="openModal('registerModal');return false;">${NMR.__t('signup')}</a>
      `);
    }
  };

  /**
   * Hydrates current user info from the API if SSR context is missing.
   */
  const hydrateAuthFromApi = async () => {
    if (window.NMR.currentUser) return;
    try {
      const res = await Connection.getMyProfile();
      if (res && res.data && !res.data.is_guest) {
        window.NMR.currentUser = res.data.username || 'User';
        syncHeaderAuth();
      }
    } catch (err) {
      if (err && err.status === 401) {
        document.cookie = 'nm_reader_session=; Max-Age=0; path=/';
        sessionStorage.removeItem('csrf_token');
      }
    }
  };

  /**
   * Initializes global UI event listeners and hydration.
   */
  const init = async () => {
    // Wait for translations to be ready.
    if (window.NMR.waitForI18n) await window.NMR.waitForI18n();

    syncHeaderAuth();
    hydrateAuthFromApi();
    NMR.renderFooterData();

    // Event Delegation: Auth Forms
    $('body').on('submit', '#loginForm', async function (e) {
      e.preventDefault();
      const fd = new FormData(this);
      const data = Object.fromEntries(fd);
      
      try {
        await Connection.login(
          data.email, 
          data.password,
          !!data.remember,
          data['cf-turnstile-response']
        );
        showPopup(NMR.__t('msg_login_success'), 'success');
        setTimeout(() => location.reload(), 1000);
      } catch (err) { 
        showPopup(err.message || NMR.__t('msg_generic_error'), 'error'); 
        if (window.turnstile) turnstile.reset('#loginForm .cf-turnstile');
      }
    });

    $('body').on('submit', '#registerForm', async function (e) {
      e.preventDefault();
      const fd = new FormData(this);
      const data = Object.fromEntries(fd);

      try {
        await Connection.register(
          data.username, 
          data.email, 
          data.password,
          data['cf-turnstile-response']
        );
        showPopup(NMR.__t('msg_register_success'), 'success');
        openModal('loginModal');
      } catch (err) { 
        showPopup(err.message || NMR.__t('msg_generic_error'), 'error'); 
        if (window.turnstile) turnstile.reset('#registerForm .cf-turnstile');
      }
    });

    // Event Delegation: Global UI Actions
    $('body').on('click', '#headerNotifBtn', async function (e) {
      e.preventDefault();
      openModal('notifModal');
      $('#notifModalList').html(`<div class="p-4 text-center text-muted">${NMR.__t('loading')}</div>`);
      try {
        const res = await Connection.getNotifications();
        const html = (res.data || []).map(n => `
          <div class="p-3 border-bottom ${n.is_read ? 'opacity-60' : 'bg-surface'}" style="border-left: 3px solid ${n.is_read ? 'transparent' : 'var(--primary)'}">
            <div class="flex justify-between items-start mb-1"><span class="font-bold text-sm">${n.title}</span><span class="text-xs text-muted">${n.created_at}</span></div>
            <p class="text-xs m-0">${n.body}</p>
          </div>
        `).join('') || `<div class="p-5 text-center text-muted">${NMR.__t('no_updates_yet')}</div>`;
        $('#notifModalList').html(html);
        $('#headerNotifBadge').addClass('hidden');
      } catch (err) { $('#notifModalList').html(NMR.__t('msg_load_failed')); }
    });

    $('body').on('click', '#markAllReadBtn', async function () {
      try {
        await Connection.markNotificationsRead();
        $('#headerNotifBadge').addClass('hidden');
        $('#notifModalList > div').addClass('opacity-60').css('border-left-color', 'transparent');
        showPopup(NMR.__t('msg_notifications_read'), 'success');
      } catch (err) { showPopup(err.message || NMR.__t('msg_generic_error'), 'error'); }
    });

    $('body').on('click', '.theme-btn', function () {
      const theme = $(this).attr('data-theme');
      $('.theme-btn').removeClass('active');
      $(this).addClass('active');
      setTheme(theme);
    });

    $('body').on('click', '#saveAllSettingsBtn', async function () {
      const layout = $('#readerLayoutSelect').val();
      const payload = {
        theme: $('body').attr('theme'),
        reader: {
          layout: layout,
          imageFit: $('[name="reader_image_fit"]').val(),
          fontFamily: $('[name="reader_font_family"]').val(),
          fontSize: parseInt($('[name="reader_font_size"]').val()),
          fontWeight: 400,
          lineHeight: 1.8
        }
      };
      try {
        // Also update cookie for immediate client-side effect
        setCookie('melt_reader_layout', layout, 30);
        await Connection.updatePreferences(payload);
        showPopup(NMR.__t('msg_settings_saved'), 'success');
        setTimeout(() => location.reload(), 800);
      } catch (err) { showPopup(err.message || NMR.__t('msg_generic_error'), 'error'); }
    });

    $('body').on('click', '#readerTabSidebar .btn', function () {
      const tab = $(this).attr('data-tab');
      $('#readerTabSidebar .btn').removeClass('active');
      $(this).addClass('active');
      $('.settings-tab').addClass('hidden');
      $(`#tab-${tab}`).removeClass('hidden');
    });

    $('body').on('input', '[name="reader_font_size"]', function () { $('#fontSizeVal').text($(this).val()); });

    $('body').on('click', '#openReaderSettings, #openSettingsInMenu, .open-reader-settings', function (e) {
      e.preventDefault();
      openModal('readerSettingsModal');
    });

    $('#globalSearchForm').on('submit', function (e) {
      e.preventDefault();
      const query = $('#globalSearchInput').val().trim();
      const lang = window.NMR.getLangPrefix();
      if (query.length < 2) {
        showPopup(NMR.__t('msg_search_min_chars'), 'info');
        return;
      }
      location.href = `/${lang}/search?q=${encodeURIComponent(query)}`;
    });

    let searchDebounce = null;
    $('#globalSearchInput').on('input', function () {
      clearTimeout(searchDebounce);
      const query = $(this).val().trim();
      const $suggestions = $('#searchSuggestions');
      const lang = window.NMR.getLangPrefix();

      if (query.length < 2) {
        $suggestions.hide().html('');
        return;
      }

      searchDebounce = setTimeout(() => {
        const langPrefix = window.NMR.getLangPrefix();
        const apiPath = `/api/v1/search/suggest?q=${encodeURIComponent(query)}`;
        
        // Use the native melt.js or jQuery ajax
        fetch(apiPath)
          .then(res => res.json())
          .then(res => {
            const items = res.data || [];
            if (items.length === 0) {
              $suggestions.hide();
              return;
            }

            const html = items.map(item => {
              const typePath = String(item.type || 'novel').replace(/_/g, '-');
              const url = `/${langPrefix}/${typePath}/${item.slug}`;
              return `
                <div class="suggestion-item" onclick="location.href='${url}'">
                  <img src="${item.cover_image || '/assets/img/covers/one-piece.jpg'}" class="suggestion-img" alt="">
                  <div class="suggestion-info">
                    <div class="suggestion-title">${item.title}</div>
                    <div class="suggestion-type">${item.type}</div>
                  </div>
                </div>
              `;
            }).join('');

            $suggestions.html(html).fadeIn(150);
          })
          .catch(() => $suggestions.hide());
      }, 300);
    });

    // Consolidated Dropdown Handler (Toggle & Click-Outside)
    $(document).on('click', function (e) {
      const $target = $(e.target);
      const $toggle = $target.closest('.dropdown-toggle');
      const $dropdown = $target.closest('.dropdown');

      // 1. Handle clicking a toggle button
      if ($toggle.elements.length > 0) {
        e.preventDefault();
        const $parent = $toggle.parent();
        const isActive = $parent.hasClass('active');
        
        // Close all other dropdowns
        $('.dropdown').removeClass('active');
        
        // Toggle this one
        if (!isActive) {
          $parent.addClass('active');
        }
        return;
      }

      // 2. Handle clicking outside any dropdown
      if ($dropdown.elements.length === 0) {
        $('.dropdown').removeClass('active');
      }
      
      // 3. Close search suggestions if clicking outside search form
      if (!$target.closest('#globalSearchForm').elements.length) {
        $('#searchSuggestions').hide();
      }
    });

    $('body').on('click', '.modal-overlay', function (e) { if (e.target === this) closeModal(); });
  };

  init();
});
} };
App.Modules.Home = { init: function() {
/**
 * Home.js - Logic for rendering the Platform Homepage.
 *
 * This module fetches aggregated homepage data and populates various UI sections:
 * - Explore Grid: Popular series across the platform.
 * - Recently Updated: Latest chapter releases.
 * - Recently Added: Newest series entries.
 * - Blog Lists: Trending and latest moderator-approved posts.
 */
$(function () {
  const ctx = window.__NMR_CONTEXT || {};

  /**
   * Derives the URL segment from a content item.
   * @param {Object} item
   * @returns {string} e.g. 'light-novel'
   */
  const typeSegment = (item) => {
    if (item.type_path) return item.type_path;
    return String(item.type || "novel").replace(/_/g, "-");
  };

  /**
   * Returns the CSS color variable for a specific content type.
   * @param {string} type
   * @returns {string}
   */
  const getTypeColor = (type) => {
    switch (String(type || "").toLowerCase()) {
      case "manga":
        return "var(--primary)";
      case "novel":
      case "light_novel":
      case "web_novel":
        return "var(--success)";
      case "webtoon":
        return "var(--info)";
      case "manhwa":
        return "var(--warning)";
      default:
        return "var(--secondary)";
    }
  };

  /**
   * Renders a grid of series cards.
   * @param {Array} items
   * @param {string} targetId CSS selector.
   */
  const renderGrid = (items, targetId) => {
    const lang = window.NMR.getLangPrefix();
    const html = (items || []).map((item) => {
      const type = typeSegment(item);
      return `
      <div class="card p-0 overflow-hidden hover-lift cursor-pointer content-card" onclick="location.href='/${lang}/${type}/${item.slug}'">
        <div class="position-relative img-placeholder" style="background-color: ${item.accent_color || '#2a2a2a'}; min-height: 200px;">
          <img src="${item.cover_image || "/assets/img/covers/one-piece.jpg"}" 
               onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';" 
               onload="this.classList.add('loaded')"
               class="w-100" alt="${item.title}" loading="lazy">
          <span class="badge position-absolute top-0 right-0 m-2 text-xs" style="background:${getTypeColor(item.type)}">${String(item.type || "").toUpperCase()}</span>
        </div>
        <div class="p-3">
          <h4 class="mb-1 truncate" title="${item.title}">${item.title}</h4>
          <p class="text-xs text-muted mb-2 truncate author-text">
            👤 ${item.author || window.NMR.__t('unknown')}
          </p>
          <div class="flex justify-between items-center text-xs text-muted">
            <span>⭐ ${item.rating_avg ?? "-"}</span>
            <span>${item.chapter_count ?? 0} Chapters</span>
          </div>
        </div>
      </div>
    `;
    }).join('');

    $(targetId).addClass('content-grid').html(html || `<div class="text-muted">${window.NMR.__t('no_content_found')}</div>`);
  };

  /**
   * Renders a grid specifically for recently updated chapters.
   */
  const renderChapterGrid = (items, targetId) => {
    const lang = window.NMR.getLangPrefix();
    const html = (items || []).map((ch) => {
      const type = ch.type_path;
      const url = `/${lang}/${type}/${ch.series_slug}/chapter/${ch.chapter_number}`;
      return `
      <div class="card p-0 overflow-hidden hover-lift cursor-pointer content-card chapter-card" onclick="location.href='${url}'">
        <div class="position-relative">
          <img src="${ch.cover_image}" onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';" class="w-100" alt="${ch.series_title}" loading="lazy">
          <span class="badge position-absolute top-0 right-0 m-2 text-sm badge-chapter" style="background:var(--primary); padding: 0.4rem 0.8rem;">BÖLÜM ${ch.chapter_number}</span>
        </div>
        <div class="p-3">
          <h4 class="mb-1 text-md" title="${ch.series_title}">${ch.series_title}</h4>
          <div class="flex flex-col gap-1 mt-2">
            <span class="text-sm font-bold text-primary">${ch.chapter_title || 'Yeni Bölüm Yayında!'}</span>
            <span class="text-xs text-muted">${String(ch.created_at || '').split(' ')[0]}</span>
          </div>
        </div>
      </div>
    `;
    }).join('');

    $(targetId).addClass('content-grid').html(html || `<div class="text-muted">${window.NMR.__t('no_chapters')}</div>`);
  };

  /**
   * Renders trending and latest blog post lists.
   */
  const renderBlogs = (popular, latest) => {
    const lang = window.NMR.getLangPrefix();
    const renderItem = (b) => `
      <div class="blog-list-item cursor-pointer" onclick="location.href='/${lang}/blogs/${b.slug}'">
        <div class="blog-title">${b.title}</div>
        <div class="blog-meta mt-1">
          <span class="blog-author"><a href="/${lang}/profile/${b.author_username || "admin"}" style="color:inherit; text-decoration:none;" onclick="event.stopPropagation();">@${b.author_username || "admin"}</a></span>
          <span class="ms-2 opacity-75" style="font-size:0.75rem">${String(b.approved_at || b.created_at || "").split(" ")[0]}</span>
        </div>
      </div>
    `;

    $("#popularBlogsList").html(
      (popular || []).map(renderItem).join("") ||
        `<div class="p-4 text-muted text-sm">${window.NMR.__t('no_popular_posts')}</div>`,
    );
    $("#latestBlogsList").html(
      (latest || []).map(renderItem).join("") ||
        `<div class="p-4 text-muted text-sm">${window.NMR.__t('no_updates_yet')}</div>`,
    );
  };

  // Main Execution
  const init = async () => {
    // Ensure translations are ready
    if (window.NMR.waitForI18n) await window.NMR.waitForI18n();

    Connection.getHome()
      .then((res) => {
        renderGrid(res.data.explore, '#homeExploreGrid');
        renderChapterGrid(res.data.recent_chapters, '#homeUpdatedGrid');
        renderGrid(res.data.recently_added, '#homeAddedGrid');
        renderBlogs(res.data.popular_blogs, res.data.latest_blogs);
        $("#homeLoading").hide();
        $("#homeApp").removeClass("hidden").fadeIn();
      })
      .catch((err) => {
        $("#homeLoading").html(
          `<div class="text-danger">${err.message || window.NMR.__t('msg_load_failed')}</div>`,
        );
      });
  };

  init();
});
} };
App.Modules.Reader = { init: function() {
/**
 * Reader.js - Core Platform Reader Engine.
 *
 * This module manages the reading experience for both Novels (text) and Manga (images).
 */
const Reader = (function () {
  let currentData = null;
  let currentLayout = 'vertical';
  let currentPage = 0;

  const _getCookie = (name) => window.getCookie ? window.getCookie(name) : null;

  const renderManga = function () {
    const $container = $('#mangaView .manga-pages');
    $container.empty().removeClass('layout-single layout-double');
    const pages = currentData.pages || [];

    if (pages.length === 0) {
      $container.html(`<div class="p-4 text-muted">${NMR.__t('no_page_images')}</div>`);
      return;
    }

    if (currentLayout === 'vertical') {
      pages.forEach((p) => {
        const img = document.createElement('img');
        img.src = p.image_path;
        img.className = 'manga-page-img mb-2';
        $container.append(img);
      });
      return;
    }

    const isDouble = currentLayout === 'double';
    $container.addClass(isDouble ? 'layout-double' : 'layout-single');
    const wrapper = document.createElement('div');
    wrapper.className = 'manga-interactive-wrapper';

    if (isDouble) {
      const start = currentPage % 2 === 0 ? currentPage : currentPage - 1;
      [start, start + 1].forEach((idx) => {
        if (pages[idx]) {
          const img = document.createElement('img');
          img.src = pages[idx].image_path;
          img.className = 'manga-page-img';
          wrapper.append(img);
        }
      });
      updatePageIndicator(`${NMR.__t('page')} ${start + 1}${pages[start + 1] ? '-' + (start + 2) : ''} / ${pages.length}`);
    } else {
      const img = document.createElement('img');
      img.src = pages[currentPage].image_path;
      img.className = 'manga-page-img';
      wrapper.append(img);
      updatePageIndicator(`${NMR.__t('page')} ${currentPage + 1} / ${pages.length}`);
    }

    wrapper.onclick = function (e) {
      const rect = this.getBoundingClientRect();
      const x = e.clientX - rect.left;
      if (x < rect.width / 2) Reader.prevPage();
      else Reader.nextPage();
    };

    $container.append(wrapper);
  };

  const updatePageIndicator = (text) => {
    const $container = $('#mangaView .manga-pages');
    const info = document.createElement('div');
    info.className = 'text-center mt-2 text-muted text-sm w-100';
    info.style.gridColumn = '1 / -1';
    info.textContent = text;
    $container.append(info);
  };

  return {
    render(apiResponse, layout = currentLayout) {
      const data = apiResponse.data || apiResponse;
      currentData = data;
      currentLayout = layout;
      
      const ctx = window.__NMR_CONTEXT || {};
      const r = (ctx.auth && ctx.auth.preferences) ? ctx.auth.preferences.reader : {};
      const fit = _getCookie('melt_reader_imageFit') || r.imageFit || 'width';

      if (data.type === 'image') {
        $('#mangaView').removeClass('hidden fit-width fit-height fit-original').addClass(`fit-${fit}`);
        $('#novelView').addClass('hidden');
        renderManga();
        this.applyProtection('#mangaView');
      } else {
        $('#novelView').removeClass('hidden');
        $('#mangaView').addClass('hidden');
        $('#novelView .novel-content').html(NMR.parseMarkdown(data.body || ''));
        this.applyStyles();
        this.applyProtection('#novelView');
      }
      window.scrollTo(0, 0);
    },

    setLayout(layout) {
      currentLayout = layout;
      if (currentData) {
        if (layout !== 'vertical') currentPage = 0;
        this.render(currentData, layout);
      }
    },

    setFit(fit) {
      $('#mangaView').removeClass('fit-width fit-height fit-original').addClass(`fit-${fit}`);
    },

    applyStyles() {
      if (window.NMR && window.NMR.syncReaderStyles) window.NMR.syncReaderStyles();
    },

    applyProtection(selector) {
      const el = document.querySelector(selector);
      if (!el) return;
      el.oncontextmenu = (e) => e.preventDefault();
      el.oncopy = (e) => e.preventDefault();
      el.onselectstart = (e) => e.preventDefault();
    },

    nextPage() {
      if (!currentData) return;
      if (currentLayout === 'vertical') { this.nextChapter(); return; }
      const pages = currentData.pages || [];
      const step = currentLayout === 'double' ? 2 : 1;
      if (currentPage + step < pages.length) {
        currentPage += step;
        renderManga();
        window.scrollTo(0, 0);
      } else {
        this.nextChapter();
      }
    },

    prevPage() {
      if (!currentData) return;
      if (currentLayout === 'vertical') { this.prevChapter(); return; }
      const step = currentLayout === 'double' ? 2 : 1;
      if (currentPage - step >= 0) {
        currentPage -= step;
        renderManga();
        window.scrollTo(0, 0);
      } else {
        this.prevChapter();
      }
    },

    nextChapter() {
      const select = document.getElementById('chapterSelect');
      if (select && select.selectedIndex < select.options.length - 1) {
        select.selectedIndex += 1;
        $(select).trigger('change');
      }
    },

    prevChapter() {
      const select = document.getElementById('chapterSelect');
      if (select && select.selectedIndex > 0) {
        select.selectedIndex -= 1;
        $(select).trigger('change');
      }
    }
  };
})();

$(function () {
  const ctx = window.__NMR_CONTEXT || {};
  const path = window.location.pathname.split('/').filter(Boolean);
  const langPrefix = (path[0] === 'tr' || path[0] === 'en') ? path[0] : '';
  const offset = langPrefix ? 1 : 0;

  const type = ctx.type || path[offset] || '';
  const slug = ctx.slug || path[offset + 1] || '';
  let currentChapter = ctx.chapterNumber || (path.length >= (offset + 4) ? path[offset + 3] : '1');
  let chapters = [];
  let isFirstLoad = true;

  const normalizeChapterNumber = (v) => String(v ?? '').trim().replace(/\.?0+$/, '');

  const updateChapterNavButtons = () => {
    const select = document.getElementById('chapterSelect');
    if (!select) return;
    const isFirst = select.selectedIndex <= 0;
    const isLast = select.selectedIndex >= select.options.length - 1;

    $('#prevChapterBtn').toggleClass('disabled', isFirst).toggleClass('opacity-30', isFirst);
    $('#nextChapterBtn').toggleClass('disabled', isLast).toggleClass('opacity-30', isLast);
  };

  const loadChapter = async (num) => {
    const target = normalizeChapterNumber(num);
    const ssrBody = $('.novel-content').text().trim();
    const isSSRMatch = isFirstLoad && ssrBody.length > 0 && !$('#novelView').hasClass('hidden');
    isFirstLoad = false;

    try {
      if (!isSSRMatch) {
        const res = await Connection.getChapterDetail(type, slug, target);
        const layout = window.getCookie ? (window.getCookie('melt_reader_layout') || 'vertical') : 'vertical';
        Reader.render(res, layout);
      } else {
        const raw = $('.novel-content').html();
        if (raw && !raw.includes('<p>')) $('.novel-content').html(NMR.parseMarkdown(raw));
        Reader.applyStyles();
      }
      
      const found = chapters.find(ch => normalizeChapterNumber(ch.chapter_number) === target);
      if (found) {
        const cRes = await Connection.getChapterComments(found.id);
        renderComments(found.id, cRes.data);
      }
    } catch (err) { showPopup(err.message || NMR.__t('msg_load_failed'), 'error'); }
    updateChapterNavButtons();
  };

  const renderComments = (id, rows) => {
    const html = (rows || []).map(c => `
      <div class="comment flex gap-3 pb-3 border-bottom" data-id="${c.id}">
        <div class="flex-grow">
          <div class="flex justify-between items-center mb-1">
            <strong class="text-sm">@${c.username || 'User'}</strong>
            <span class="text-xs text-muted">${c.created_at || ''}</span>
          </div>
          <div class="text-sm text-muted leading-relaxed markdown-body">${NMR.parseMarkdown(c.body || '')}</div>
        </div>
      </div>
    `).join('');
    $('#readerCommentsList').html(html || `<div class="text-center py-3 text-muted">${NMR.__t('no_comments_yet')}</div>`);
  };

  Connection.getChapters(type, slug).then(res => {
    chapters = (res.data || []).sort((a, b) => parseFloat(a.chapter_number) - parseFloat(b.chapter_number));
    const select = $('#chapterSelect');
    select.empty();
    chapters.forEach(ch => {
      const n = normalizeChapterNumber(ch.chapter_number);
      const opt = document.createElement('option');
      opt.value = n;
      opt.textContent = `${NMR.__t('chapter')} ${n}`;
      opt.selected = n === normalizeChapterNumber(currentChapter);
      select.append(opt);
    });
    updateChapterNavButtons();
    loadChapter(currentChapter);
  });

  $('#chapterSelect').on('change', function () {
    const val = $(this).val();
    if (!val) return;
    currentChapter = val;
    const prefix = langPrefix ? `/${langPrefix}` : '';
    window.history.replaceState({}, '', `${prefix}/${type}/${slug}/chapter/${val}`);
    loadChapter(val);
  });

  $('#prevChapterBtn').on('click', function(e) { e.preventDefault(); Reader.prevChapter(); });
  $('#nextChapterBtn').on('click', function(e) { e.preventDefault(); Reader.nextChapter(); });

  $(document).on('keydown', (e) => {
    if (['input', 'textarea'].includes(e.target.tagName.toLowerCase())) return;
    if (e.key === 'ArrowRight') Reader.nextPage();
    if (e.key === 'ArrowLeft') Reader.prevPage();
  });
});
} };
App.Modules.Blog = { init: function() {
/**
 * blog.js - Interactive Controller for the Blog Platform.
 *
 * This module manages:
 * - Blog Discovery: Fetches and renders a grid of approved blog posts.
 * - Post Details: Hydrates SSR content or AJAX-loads full posts with markdown.
 * - Voting: Handles upvotes/downvotes for both posts and individual comments.
 * - Social: Integrated comment system specifically for blog posts.
 */
$(function () {
  const ctx = window.__NMR_CONTEXT || {};
  const langPrefix = window.NMR.getLangPrefix();

  /**
   * Identifies the current blog slug from the URL.
   */
  const fromPath = () => {
    const parts = window.location.pathname.split('/').filter(Boolean);
    const offset = (parts[0] === 'tr' || parts[0] === 'en') ? 1 : 0;
    if (parts[offset] === 'blogs' && parts[offset + 1]) {
      return parts[offset + 1];
    }
    return '';
  };

  const slugFromRoute = ctx.slug || fromPath();

  /**
   * Utility to provide a fallback cover image for blog posts.
   */
  const withFallbackImage = (post) => {
    if (post.cover_image) return post.cover_image;
    return '/assets/img/covers/one-piece.jpg';
  };

  /**
   * Generates a short text excerpt for listing views.
   */
  const excerpt = (text) => {
    const raw = String(text || '');
    return raw.length > 220 ? `${raw.slice(0, 220)}...` : raw;
  };

  /**
   * Renders the main blog listing grid.
   * @param {Array} posts
   */
  const renderBlogList = (posts) => {
    const html = (posts || []).map((post) => {
      const date = String(post.approved_at || post.created_at || '').split(' ')[0];
      return `
          <div class="card p-0 overflow-hidden blog-post-card cursor-pointer" data-slug="${post.slug}">
            <div class="blog-post-img-wrapper">
              <img src="${withFallbackImage(post)}" onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';" class="blog-post-img" alt="${post.title}">
            </div>
            <div class="p-4">
              <div class="flex items-center gap-2 mb-2 text-xs font-bold uppercase tracking-wider text-muted">
                <span class="text-primary"><a href="/${langPrefix}/profile/${post.author_username || ''}" style="color:inherit; text-decoration:none;" onclick="event.stopPropagation();">@${post.author_username || '-'}</a></span>
                <span class="opacity-40">•</span>
                <span>${date}</span>
              </div>
              <h3 class="mb-2 text-xl font-bold">${post.title}</h3>
              <div class="text-muted text-sm line-clamp-3">${excerpt(post.body)}</div>
            </div>
          </div>
        `;
    }).join('');

    $('#blogGrid').html(html || `<div class="text-muted py-5 text-center w-100">${NMR.__t('no_blog_posts')}</div>`);

    $('.blog-post-card').click(function () {
      const slug = $(this).attr('data-slug');
      location.href = `/${langPrefix}/blogs/${slug}`;
    });
  };

  /**
   * Renders full details for a specific blog post.
   */
  const renderPostDetail = (post) => {
    const score = (Number(post.upvote_count) || 0) - (Number(post.downvote_count) || 0);
    const myVote = Number(post.my_vote) || 0;
    const date = String(post.approved_at || post.created_at || '').split(' ')[0];
    const image = withFallbackImage(post);

    const html = `
      <div class="blog-hero-wrapper">
        <div class="blog-hero-backdrop" style="background-image: url('${image}')"></div>
        <div class="blog-hero-overlay"></div>
        <div class="container blog-hero-content">
          <button class="blog-meta-pill btn-none mb-4 cursor-pointer hover-lift" id="backToBlog">← ${NMR.__t('back')}</button>
          <h1 class="blog-hero-title">${post.title}</h1>
          <div class="blog-hero-meta">
             <a href="/${langPrefix}/profile/${post.author_username || ''}" class="blog-meta-pill no-underline">
                 👤 @${post.author_username || '-'}
             </a>
             <div class="blog-meta-pill">
                 📅 ${date}
             </div>
             <div class="blog-meta-pill">
                 🔥 <span class="blog-score-val">${score}</span>
             </div>
          </div>
        </div>
      </div>

      <div class="container blog-post-container" data-slug="${post.slug}">
        <!-- Floating Vote for Desktop -->
        <div class="blog-vote-floating">
          <button class="btn-none blog-vote-btn upvote ${myVote === 1 ? 'text-primary' : ''}" data-vote="1" style="font-size:1.5rem">▲</button>
          <span class="font-bold blog-score-val">${score}</span>
          <button class="btn-none blog-vote-btn downvote ${myVote === -1 ? 'text-danger' : ''}" data-vote="-1" style="font-size:1.5rem">▼</button>
        </div>

        <div class="blog-content-card">
          <div class="blog-content-main markdown-body">
            ${NMR.parseMarkdown(post.body || '')}
          </div>
          
          <div class="mt-5 pt-5 border-t">
            <div id="blogCommentsArea">
              <h3 class="mb-4">💬 ${NMR.__t('comments')} <span id="blogCommentCount" class="badge bg-primary ml-2"></span></h3>
              
              <form id="blogCommentForm" class="mb-5 bg-surface-elevated p-4 rounded-xl border">
                <div class="flex flex-col gap-3">
                  <textarea id="blogCommentInput" class="form-item border-none focus-ring" placeholder="${NMR.__t('comments')}..." rows="4"></textarea>
                  <div class="text-xs text-muted font-bold uppercase tracking-wider">👁️ ${NMR.__t('preview')}</div>
                  <div id="blogCommentPreview" class="form-item bg-surface overflow-auto markdown-body p-3 min-h-80 border-dashed opacity-80">
                    <span class="text-muted italic">${NMR.__t('preview_will_appear')}</span>
                  </div>
                  <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-full">${NMR.__t('post_comment')}</button>
                  </div>
                </div>
              </form>
              
              <div id="blogCommentsList" class="flex flex-col gap-5">
                <div class="text-center py-5">
                    <div class="spinner-border animate-spin text-primary"></div>
                    <div class="mt-2 text-muted">${NMR.__t('loading')}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    $('#blogPostContent').html(html);
    $('#blogListArea').hide();
    $('#blogDetailArea').removeClass('hidden').fadeIn();
    window.scrollTo(0, 0);
    loadBlogComments(post.slug);
  };

  /**
   * Fetches comments for a specific blog post.
   */
  const loadBlogComments = async (slug) => {
    try {
      const res = await Connection.getBlogComments(slug);
      renderBlogComments(res.data || []);
    } catch (err) {
      $('#blogCommentsList').html(`<div class="text-danger">${err.message}</div>`);
    }
  };

  /**
   * Renders the social comment list for a blog post.
   */
  const renderBlogComments = (comments) => {
    $('#blogCommentCount').text(`(${comments.length})`);
    const html = comments.map(c => {
      const score = (Number(c.upvote_count) || 0) - (Number(c.downvote_count) || 0);
      const myVote = Number(c.my_vote) || 0;
      return `
        <div class="comment flex gap-3 pb-3 border-bottom" data-id="${c.id}" data-user-id="${c.user_id}">
          <div class="flex flex-col items-center gap-1" style="min-width:40px">
            <button class="btn-none blog-comment-vote-btn upvote ${myVote === 1 ? 'text-primary' : ''}" data-vote="1">▲</button>
            <span class="text-xs font-bold score-val">${score}</span>
            <button class="btn-none blog-comment-vote-btn downvote ${myVote === -1 ? 'text-danger' : ''}" data-vote="-1">▼</button>
          </div>
          <div class="flex-grow">
            <div class="flex justify-between items-center mb-1">
              <strong class="text-sm"><a href="/${langPrefix}/profile/${c.username || ''}" style="color:inherit; text-decoration:none;">@${c.username || 'User'}</a></strong>
              <span class="text-xs text-muted">${c.created_at || ''}</span>
            </div>
            <div class="text-sm text-muted leading-relaxed markdown-body">${NMR.parseMarkdown(c.body || '')}</div>
          </div>
        </div>
      `;
    }).join('');

    $('#blogCommentsList').html(html || `<div class="py-4 text-muted text-center">${NMR.__t('no_popular_posts')}</div>`);
  };

  // --- Global Event Delegation ---

  // Blog Voting logic.
  $('body').on('click', '.blog-vote-btn', async function () {
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
    const btn = $(this);
    const slug = btn.closest('.card').attr('data-slug');
    const vote = parseInt(btn.attr('data-vote'));
    try {
      const res = await Connection.voteBlog(slug, vote);
      const parent = btn.parent();
      parent.find('.blog-score-val').text(Number(res.data.upvote_count) - Number(res.data.downvote_count));
      parent.find('.blog-vote-btn').removeClass('text-primary text-danger');
      if (res.data.my_vote === 1) parent.find('.upvote').addClass('text-primary');
      if (res.data.my_vote === -1) parent.find('.downvote').addClass('text-danger');
    } catch (err) { showPopup(err.message, 'error'); }
  });

  // Blog Comment Voting logic.
  $('body').on('click', '.blog-comment-vote-btn', async function () {
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
    const btn = $(this);
    const commentEl = btn.closest('.comment');
    const commentId = commentEl.attr('data-id');
    const commentUserId = commentEl.attr('data-user-id');
    const vote = parseInt(btn.attr('data-vote'));
    const slug = ctx.slug || fromPath();

    const currentUserId = window.__NMR_CONTEXT?.auth?.user_id || null;
    if (currentUserId && String(commentUserId) === String(currentUserId)) {
      return showPopup(NMR.__t('msg_vote_self_error'), 'error');
    }

    try {
      const res = await Connection.voteBlogComment(slug, commentId, vote);
      commentEl.find('.score-val').text(Number(res.data.upvote_count) - Number(res.data.downvote_count));
      commentEl.find('.blog-comment-vote-btn').removeClass('text-primary text-danger');
      if (res.data.my_vote === 1) commentEl.find('.upvote').addClass('text-primary');
      if (res.data.my_vote === -1) commentEl.find('.downvote').addClass('text-danger');
    } catch (err) { showPopup(err.message, 'error'); }
  });

  // Comment Posting logic.
  $('body').on('submit', '#blogCommentForm', async function (e) {
    e.preventDefault();
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
    const slug = ctx.slug || fromPath();
    const body = $('#blogCommentInput').val().trim();
    if (!body) return;
    try {
      await Connection.postBlogComment(slug, body);
      $('#blogCommentInput').val('');
      $('#blogCommentPreview').html(`<span class="text-muted italic">${NMR.__t('preview_will_appear')}</span>`);
      showPopup(NMR.__t('msg_comment_posted'), 'success');
      loadBlogComments(slug);
    } catch (err) { showPopup(err.message, 'error'); }
  });

  // Back button logic.
  $('body').on('click', '#backToBlog', function () {
    location.href = `/${langPrefix}/blogs`;
  });

  // Main Execution
  if (slugFromRoute) {
    const ssrBody = $('#blogDetailArea .markdown-body');
    const isSSRMatch = ssrBody.elements.length > 0 && ssrBody.text().trim().length > 0;

    if (isSSRMatch) {
      const raw = ssrBody.html();
      if (raw && !raw.includes('<p>')) { ssrBody.html(NMR.parseMarkdown(raw)); }
      $('#blogLoading').hide();
      loadBlogComments(slugFromRoute);
    } else {
      Connection.getBlog(slugFromRoute)
        .then((res) => { renderPostDetail(res.data); $('#blogLoading').hide(); });
    }
    return;
  }

  Connection.getBlogs().then((res) => {
    renderBlogList(res.data);
    $('#blogLoading').hide();
    $('#blogListArea').removeClass('hidden').fadeIn();
  });
});
} };
App.Modules.Content = { init: function() {
/**
 * Content.js - Interactive Controller for Series Detail Pages.
 *
 * This module manages:
 * - Chapter Listing: Renders a scrollable list of chapters with deep links.
 * - Navigation: Automatically finds the first chapter for the "Start Reading" button.
 * - Comments: Handles fetching, rendering, and posting comments with markdown support.
 * - User Actions: Managing follows/unfollows and rating submissions.
 * - CSRF/Auth: Protects social actions by verifying authentication state.
 */
$(function() {
  const ctx = window.__NMR_CONTEXT || {};

  /**
   * Extracts content type and slug from the URL path.
   * @returns {{type: string, slug: string}}
   */
  const fromPath = () => {
    const parts = window.location.pathname.split('/').filter(Boolean);
    const offset = (parts[0] === 'tr' || parts[0] === 'en') ? 1 : 0;
    return {
      type: parts[offset] || '',
      slug: parts[offset + 1] || ''
    };
  };

  const pathState = fromPath();
  const type = ctx.type || pathState.type || 'manga';
  const slug = ctx.slug || pathState.slug || '';

  /**
   * Normalizes chapter numbers for display (e.g., "1.00" -> "1").
   */
  const normalizeChapterNumber = (value) => {
    const raw = String(value ?? '').trim();
    if (!/^-?\d+(?:\.\d+)?$/.test(raw)) return raw;
    if (!raw.includes('.')) return raw;
    return raw.replace(/\.?0+$/, '');
  };

  /**
   * Renders the chapter list card.
   * @param {Array} chapters
   */
  const renderChapters = (chapters) => {
    const safeChapters = chapters || [];
    const lang = window.NMR.getLangPrefix();
    const listHtml = safeChapters.map((ch) => `
      <div class="chapter-row d-flex justify-between items-center p-3 border-bottom hover-bg cursor-pointer"
           onclick="location.href='/${lang}/${type}/${slug}/chapter/${normalizeChapterNumber(ch.chapter_number)}'">
        <div class="flex flex-col">
          <strong>${NMR.__t('chapter')} ${normalizeChapterNumber(ch.chapter_number)}${ch.title ? `: ${ch.title}` : ''}</strong>
          <span class="text-xs text-muted">${ch.created_at || ''}</span>
        </div>
        <span class="text-xs text-muted">${NMR.__t('read')} »</span>
      </div>
    `).join('');

    const first = safeChapters[safeChapters.length - 1];
    const latest = safeChapters[0];

    const html = `
      <div class="card p-0 overflow-hidden">
        <div class="card-header border-bottom flex items-center justify-between p-3 bg-surface">
          <h3 class="m-0">${NMR.__t('chapters')}</h3>
          <div class="flex gap-2">
            <button class="btn btn-sm btn-outline" ${first ? `onclick="location.href='/${lang}/${type}/${slug}/chapter/${normalizeChapterNumber(first.chapter_number)}'"` : 'disabled'}>${NMR.__t('first')}</button>
            <button class="btn btn-sm btn-outline" ${latest ? `onclick="location.href='/${lang}/${type}/${slug}/chapter/${normalizeChapterNumber(latest.chapter_number)}'"` : 'disabled'}>${NMR.__t('latest')}</button>
          </div>
        </div>
        <div class="chapter-list-scroll scrollbar-5" style="max-height: 500px; overflow-y: auto;">
          ${listHtml || `<div class="p-3 text-muted">${NMR.__t('no_updates_yet')}</div>`}
        </div>
      </div>
    `;

    $('#chapterListTarget').html(html);
  };

  /**
   * Updates the "Start Reading" button to point to the actual first chapter.
   */
  const updateStartReadingLink = (chapters) => {
    const btn = $('#startReadingBtn');
    if (!btn.elements.length) return;

    const items = Array.isArray(chapters) ? [...chapters] : [];
    if (items.length === 0) {
      btn.addClass('disabled').attr('aria-disabled', 'true').attr('href', '#');
      return;
    }

    // Sort ascending to find the true beginning.
    items.sort((a, b) => {
      const na = Number.parseFloat(String(a.chapter_number).replace(',', '.'));
      const nb = Number.parseFloat(String(b.chapter_number).replace(',', '.'));
      return na - nb;
    });

    const first = items[0];
    btn.removeClass('disabled')
      .attr('aria-disabled', 'false')
      .attr('href', `/${type}/${slug}/chapter/${encodeURIComponent(normalizeChapterNumber(first.chapter_number))}`);
  };

  /**
   * Renders the social comment section.
   */
  const renderComments = (rows) => {
    const html = (rows || []).map((c) => {
      const score = (Number(c.upvote_count) || 0) - (Number(c.downvote_count) || 0);
      const myVote = Number(c.my_vote) || 0;
      return `
        <div class="comment flex gap-3 pb-3 border-bottom mb-3" data-id="${c.id}" data-user-id="${c.user_id}">
          <div class="flex flex-col items-center gap-1" style="min-width:40px">
            <button class="btn-none vote-btn upvote ${myVote === 1 ? 'text-primary' : ''}" data-vote="1">▲</button>
            <span class="text-xs font-bold score-val">${score}</span>
            <button class="btn-none vote-btn downvote ${myVote === -1 ? 'text-danger' : ''}" data-vote="-1">▼</button>
          </div>
          <div class="flex-grow">
            <div class="flex justify-between items-center mb-1">
              <strong class="text-sm"><a href="/${window.NMR.getLangPrefix()}/profile/${c.username || ''}" style="color:inherit; text-decoration:none;">@${c.username || 'User'}</a></strong>
              <span class="text-xs text-muted">${c.created_at || ''}</span>
            </div>
            <div class="text-sm text-muted leading-relaxed markdown-body">${NMR.parseMarkdown(c.body || '')}</div>
          </div>
        </div>
      `;
    }).join('');

    const count = (rows || []).length;
    $('#contentCommentsList').html(html || `<div class="text-muted text-center py-4">${NMR.__t('no_popular_posts')}</div>`);
    $('#commentsBadgeCount').text(`(${count})`);
  };

  // --- UI Event Listeners ---

  // Live Markdown Preview for comments.
  $('#contentCommentInput').on('input', function(e) {
    const val = e.target.value;
    $('#commentPreview').html(val ? NMR.parseMarkdown(val) : '<span class="text-muted italic">...</span>');
  });

  // Comment Submission.
  $('#contentCommentForm').on('submit', async function(e) {
    e.preventDefault();
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
    
    const body = $('#contentCommentInput').val().trim();
    if (!body) return;

    try {
      await Connection.postContentComment(type, slug, body);
      $('#contentCommentInput').val('');
      $('#commentPreview').html('<span class="text-muted italic">...</span>');
      showPopup(NMR.__t('msg_comment_posted'), 'success');
      const commentsRes = await Connection.getContentComments(type, slug);
      renderComments(commentsRes.data);
    } catch (err) { showPopup(err.message, 'error'); }
  });

  // Vote Delegation.
  $('body').on('click', '.vote-btn', async function() {
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');

    const btn = $(this);
    const commentEl = btn.closest('.comment');
    const commentId = parseInt(commentEl.attr('data-id'));
    const commentUserId = commentEl.attr('data-user-id');
    const vote = parseInt(btn.attr('data-vote'));

    const currentUserId = window.__NMR_CONTEXT.auth ? window.__NMR_CONTEXT.auth.user_id : null;
    if (currentUserId && String(commentUserId) === String(currentUserId)) {
      return showPopup(NMR.__t('msg_vote_self_error'), 'error');
    }

    try {
      const res = await Connection.voteComment(commentId, vote);
      commentEl.find('.score-val').text(res.data.upvote_count - res.data.downvote_count);
      commentEl.find('.vote-btn').removeClass('text-primary text-danger');
      if (res.data.my_vote === 1) commentEl.find('.upvote').addClass('text-primary');
      if (res.data.my_vote === -1) commentEl.find('.downvote').addClass('text-danger');
    } catch (err) { showPopup(err.message, 'error'); }
  });

  /**
   * Initializes follow and rate buttons.
   */
  const initActions = () => {
    $('#followBtn').on('click', async function() {
      if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
      const isFollowing = $(this).hasClass('btn-secondary');
      try {
        if (isFollowing) {
          await Connection.unfollowContent(type, slug);
          $(this).removeClass('btn-secondary').addClass('btn-outline').text(`🤍 ${NMR.__t('follow')}`);
        } else {
          await Connection.followContent(type, slug);
          $(this).removeClass('btn-outline').addClass('btn-secondary').text(`💖 ${NMR.__t('following')}`);
        }
        showPopup(NMR.__t(isFollowing ? 'msg_removed_library' : 'msg_added_library'), 'success');
      } catch (err) { showPopup(err.message, 'error'); }
    });

    $('.rate-opt').on('click', async function() {
      if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
      try {
        await Connection.rateContent(type, slug, $(this).attr('data-val'));
        showPopup(NMR.__t('msg_rate_success'), 'success');
        location.reload();
      } catch (err) { showPopup(err.message, 'error'); }
    });
  };

  // Main Execution
  initActions();

  // Render description markdown
  const descEl = $('#contentDescription');
  if (descEl.elements.length && descEl.text().trim()) {
    descEl.html(NMR.parseMarkdown(descEl.text()));
  }

  Connection.getChapters(type, slug).then((res) => {
    const chapters = Array.isArray(res.data) ? res.data : [];
    renderChapters(chapters);
    updateStartReadingLink(chapters);
  });

  Connection.getContentComments(type, slug).then(c => renderComments(c.data));
});
} };
App.Modules.Search = { init: function() {
/**
 * search.js - Controller for Platform-wide Content Search.
 *
 * This module executes AJAX-based full-text searches across the database.
 * It handles:
 * - Dynamic Result Rendering: Displays matched series in a responsive grid.
 * - Routing: Directs users to the specific content page based on its type.
 * - UX: Manages loading states and "no results found" messaging.
 */
$(function() {
  const ctx = window.__NMR_CONTEXT || {};
  const query = ctx.q || '';

  /**
   * Renders the search result cards.
   * @param {Array} items List of series matching the query.
   */
  const renderResults = (items) => {
    const html = (items || []).map((item) => {
      const typePath = String(item.type || 'novel').replace(/_/g, '-');
      return `
      <div class="card p-0 overflow-hidden hover-lift cursor-pointer content-card" onclick="location.href='/${typePath}/${item.slug}'">
        <div class="position-relative">
          <img src="${item.cover_image || ''}" onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';" class="w-100" alt="${item.title}">
          <span class="badge position-absolute top-0 right-0 m-2 text-xs bg-primary">${String(item.type || '').toUpperCase()}</span>
        </div>
        <div class="p-3">
          <h4 class="mb-1 truncate" title="${item.title}">${item.title}</h4>
          <div class="flex justify-between items-center text-xs text-muted">
            <span>⭐ ${item.rating_avg ?? '-'}</span>
            <span>${item.chapter_count ?? 0} ${NMR.__t('chapters')}</span>
          </div>
        </div>
      </div>
    `;
    }).join('');

    $('#searchResultsGrid').addClass('content-grid').html(html || `<div class="col-span-full text-center py-5 text-muted">${NMR.__t('no_content_found')}</div>`);
  };

  // Main Execution: Trigger search if query exists in context.
  if (query) {
    $('#searchTitle').text(NMR.__t('results_for').replace(':query', query));
    Connection.search(query)
      .then((res) => {
        renderResults(res.data);
        $('#searchLoading').hide();
        $('#searchApp').removeClass('hidden').fadeIn();
      })
      .catch((err) => {
        $('#searchLoading').html(`<div class="text-danger">${err.message || NMR.__t('search_failed')}</div>`);
      });
  } else {
    $('#searchLoading').html(`<div class="text-muted text-center py-5">${NMR.__t('please_enter_search_term')}</div>`);
  }
});
} };
App.Modules.Profile = { init: function() {
/**
 * Profile.js - Logic for the User Profile and Settings page.
 *
 * This module handles:
 * - Tab Navigation: Switches between Reading History, Library, and Account Settings.
 * - Social Actions: follow/unfollow other users with immediate UI feedback.
 * - Form Management: Updates user biography, profile/cover images, and site preferences.
 */
$(function () {
    /**
     * Switches the active UI tab.
     * @param {string} tabId ID of the pane to show (history, library, settings).
     */
    const switchTab = (tabId) => {
        $('#pTabs .profile-tab-btn').removeClass('active');
        $(`#pTabs .profile-tab-btn[data-tab="${tabId}"]`).addClass('active');
        $('.tab-pane').addClass('hidden');
        $(`#pane-${tabId}`).removeClass('hidden');
    };

    /**
     * Binds interaction events for the profile page.
     */
    const bindEvents = () => {
        // --- Tab Management ---
        $('#pTabs .profile-tab-btn').on('click', function () {
            switchTab($(this).attr('data-tab'));
        });

        // --- User-to-User Follow Action ---
        $('body').on('click', '#followBtn', async function () {
            if (!window.NMR.currentUser) return window.showPopup(window.NMR.__t('msg_login_required'), 'error');

            const btn = $(this);
            const person = window.__NMR_CONTEXT?.person || '';
            const isFollowing = btn.attr('data-status') === 'following';

            try {
                btn.prop('disabled', true);
                if (isFollowing) {
                    await Connection.unfollowUser(person);
                    btn.text(window.NMR.__t('follow')).removeClass('btn-secondary').addClass('btn-primary').attr('data-status', 'none');

                    // Optimistic counter update.
                    const count = parseInt($('#sFollowers').text().replace(/[^0-9]/g, '')) - 1;
                    $('#sFollowers').text(new Intl.NumberFormat().format(Math.max(0, count)));
                } else {
                    await Connection.followUser(person);
                    btn.text(window.NMR.__t('following')).removeClass('btn-primary').addClass('btn-secondary').attr('data-status', 'following');

                    const count = parseInt($('#sFollowers').text().replace(/[^0-9]/g, '')) + 1;
                    $('#sFollowers').text(new Intl.NumberFormat().format(count));
                }
            } catch (e) { window.showPopup(e.message, 'error'); }
            finally { btn.prop('disabled', false); }
        });

        // --- Account & Settings Update (Modal) ---
        $('body').on('submit', '#userSettingsForm', async function (e) {
            e.preventDefault();
            const btn = $(this).find('button[type="submit"]');
            const fd = new FormData(this);
            const prefData = {
                theme: fd.get('theme'),
                lang: fd.get('lang')
            };

            try {
                btn.prop('disabled', true).text(window.NMR.__t('updating'));
                // Sequential update to prevent concurrent session writes/CSRF race conditions.
                await Connection.updateProfile(fd);
                await Connection.updatePreferences(prefData);
                window.showPopup(window.NMR.__t('profile_updated'), 'success');
                setTimeout(() => location.reload(), 800);
            } catch (e) {
                window.showPopup(e.message, 'error');
                btn.prop('disabled', false).text(window.NMR.__t('update_profile'));
            }
        });

        // --- Legacy Account & Settings Update (if still in DOM) ---
        $('#settingsForm').on('submit', async function (e) {
            e.preventDefault();
            const profileData = {
                bio: $(this).find('[name="bio"]').val(),
                profile_image: $(this).find('[name="profile_image"]').val(),
                cover_image: $(this).find('[name="cover_image"]').val()
            };
            const prefData = {
                theme: $(this).find('[name="theme"]').val(),
                lang: $(this).find('[name="lang"]').val()
            };

            try {
                await Promise.all([
                    Connection.updateProfile(profileData),
                    Connection.updatePreferences(prefData)
                ]);
                window.showPopup(window.NMR.__t('profile_updated'), 'success');
                setTimeout(() => location.reload(), 800);
            } catch (e) { window.showPopup(e.message, 'error'); }
        });
    };

    bindEvents();
});
} };
App.Modules.Chat = { init: function() {
/**
 * chat.js - Logic for the Modular Global Chat System.
 *
 * This module handles:
 * - Real-time Interaction: Mock API for fetching and sending chat messages.
 * - Social UI: Renders incoming and outgoing message bubbles with avatars.
 * - UX: Implements automated "scroll-to-bottom" behavior for new messages.
 * - Online Status: Tracks and displays the current number of active users.
 */
$(function() {
  /**
   * Internal Mock API for Chat Data.
   */
  const API = {
    getMessages: () => {
      return new Promise(resolve => {
        setTimeout(() => {
          resolve([
            { type: 'incoming', user: 'Jane Smith', initial: 'JS', time: '10:45 AM', text: 'Melt CSS is amazing!' },
            { type: 'outgoing', user: 'You', initial: 'YO', time: '10:46 AM', text: 'Thanks! We worked hard on it.' },
            { type: 'incoming', user: 'Alex Kumar', initial: 'AK', time: '10:48 AM', text: 'How can I implement modular pages?' }
          ]);
        }, 500);
      });
    },
    onlineCount: () => Promise.resolve(42)
  };

  /**
   * Appends a message bubble to the chat container.
   * @param {Object} m Message data object.
   */
  const renderMessage = (m) => {
    const html = `
      <div class="message ${m.type}">
        ${m.type === 'incoming' ? `<div class="message-avatar">${m.initial}</div>` : ''}
        <div class="message-content">
          <div class="message-info">${m.user} <span class="time">${m.time}</span></div>
          <div class="message-bubble">${m.text}</div>
        </div>
      </div>
    `;
    $('#chatMessages').append(html);
  };

  /**
   * Forces the chat window to scroll to the latest message.
   */
  const scrollToBottom = () => {
    const el = $('#chatMessages').elements[0];
    if (el) el.scrollTop = el.scrollHeight;
  };

  // --- Initial Hydration ---
  Promise.all([API.getMessages(), API.onlineCount()]).then(([messages, count]) => {
    $('#onlineCount').text(`👥 ${count} online`);
    $('.chat-header h3').html('💬 Global Chat');
    $('#chatMessages').empty();
    messages.forEach(renderMessage);
    scrollToBottom();
  });

  // --- Send Message Handler ---
  $('#chatFormPage').submit(function(e) {
    e.preventDefault();
    const input = $('#chatInput');
    const text = input.val().trim();
    if(!text) return;

    // Optimistic UI update.
    renderMessage({
      type: 'outgoing',
      user: 'You',
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      text: text
    });

    input.val('');
    scrollToBottom();
    showPopup("Message sent", "success");
  });
});
} };
App.Modules.Series_list = { init: function() {
/**
 * series_list.js - Controller for Discovery and Categorized Listings.
 *
 * This module handles:
 * - Dynamic Discovery: Resolves the 'list_type' (genre, tag, category) from context.
 * - API Routing: Calls the correct Connection method based on the listing criteria.
 * - Localized UI: Renders translated headers and content badges.
 * - Result Grid: Generates a responsive grid of content cards.
 */
$(function() {
  const ctx = window.__NMR_CONTEXT || {};
  const listType = ctx.list_type || 'category';
  const value = ctx.value || '';
  const langPrefix = window.NMR.getLangPrefix();

  /**
   * Renders the collection of series cards.
   * @param {Array} items
   */
  const renderList = (items) => {
    const html = (items || []).map((item) => {
      const typePath = String(item.type || 'novel').replace(/_/g, '-');
      return `
      <div class="card p-0 overflow-hidden hover-lift cursor-pointer content-card" onclick="location.href='/${langPrefix}/${typePath}/${item.slug}'">
        <div class="position-relative img-placeholder" style="background-color: ${item.accent_color || '#2a2a2a'}; min-height: 200px;">
          <img src="${item.cover_image || ''}" 
               onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';" 
               onload="this.classList.add('loaded')"
               class="w-100" alt="${item.title}">
          <span class="badge position-absolute top-0 right-0 m-2 text-xs bg-primary">${String(item.type || '').toUpperCase()}</span>
        </div>
        <div class="p-3">
          <h4 class="mb-1 truncate" title="${item.title}">${item.title}</h4>
          <p class="text-xs text-muted mb-2 truncate author-text">
            👤 ${item.author || NMR.__t('unknown')}
          </p>
          <div class="flex justify-between items-center text-xs text-muted">
            <span>⭐ ${item.rating_avg ?? '-'}</span>
            <span>${item.chapter_count ?? 0} ${NMR.__t('chapters')}</span>
          </div>
        </div>
      </div>
    `;
    }).join('');

    $('#listingGrid').addClass('content-grid').html(html || `<div class="col-span-full text-center py-5 text-muted">${NMR.__t('no_content_found')}</div>`);
  };

  // Main Execution: Resolve API call based on listing type.
  if (value) {
    let titleText = value.toUpperCase();
    let apiCall = null;

    if (listType === 'category') {
      const titleMap = {
        'light-novel': NMR.__t('light-novels'), 
        'web-novel': NMR.__t('web-novels'), 
        'novel': NMR.__t('novels'),
        'manga': NMR.__t('manga'), 
        'manhua': NMR.__t('manhua'), 
        'manhwa': NMR.__t('manhwa'), 
        'webtoon': NMR.__t('webtoons')
      };
      titleText = titleMap[value] || value.toUpperCase();
      apiCall = Connection.getByType(value);
    } else if (listType === 'genre') {
      titleText = `${NMR.__t('genre')}: ${value.charAt(0).toUpperCase() + value.slice(1)}`;
      apiCall = Connection.getByGenre(value);
    } else if (listType === 'tag') {
      titleText = `${NMR.__t('tag')}: ${value.charAt(0).toUpperCase() + value.slice(1)}`;
      apiCall = Connection.getByTag(value);
    }

    $('#listingTitle').text(titleText);

    if (apiCall) {
      apiCall.then((res) => {
        renderList(res.data);
        $('#listingLoading').hide();
        $('#listingApp').removeClass('hidden').fadeIn();
      }).catch((err) => {
        $('#listingLoading').html(`<div class="text-danger">${err.message || NMR.__t('msg_load_failed')}</div>`);
      });
    }
  }
});
} };
document.addEventListener('DOMContentLoaded', () => App.init());
