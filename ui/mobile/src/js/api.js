const DEFAULT_API_BASE_URL = '/api/v1';
const STORAGE_KEY = 'nmr_mobile_session_v1';
const API_BASE_URL =
  (typeof window !== 'undefined' && (window.NMR_MOBILE_API_BASE || window.NMR_API_BASE))
    ? (window.NMR_MOBILE_API_BASE || window.NMR_API_BASE)
    : DEFAULT_API_BASE_URL;

const NMR_API = {
  _token: null,
  _csrfToken: null,
  _refreshToken: null,
  _refreshing: null,

  setToken(token) {
    this._token = token || null;
  },

  setCsrfToken(token) {
    this._csrfToken = token || null;
  },

  setRefreshToken(token) {
    this._refreshToken = token || null;
  },

  setSession({ apiToken = null, csrfToken = null, refreshToken = null } = {}) {
    this.setToken(apiToken);
    this.setCsrfToken(csrfToken);
    this.setRefreshToken(refreshToken);
    if (typeof document !== 'undefined') {
      document.dispatchEvent(new CustomEvent('auth:updated'));
    }
  },

  loadSession() {
    if (typeof localStorage === 'undefined') return null;
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (err) {
      return null;
    }
  },

  saveSession(session) {
    if (typeof localStorage === 'undefined') return;
    if (!session) {
      localStorage.removeItem(STORAGE_KEY);
      return;
    }
    localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
  },

  clearSession() {
    this.setSession({ apiToken: null, csrfToken: null, refreshToken: null });
    this.saveSession(null);
  },

  _safeJsonParse(text) {
    if (!text) return null;
    try {
      return JSON.parse(text);
    } catch (err) {
      const trimmed = text.trim();
      const objStart = trimmed.indexOf('{');
      const objEnd = trimmed.lastIndexOf('}');
      if (objStart !== -1 && objEnd > objStart) {
        try {
          return JSON.parse(trimmed.slice(objStart, objEnd + 1));
        } catch (innerErr) {
          // fall through
        }
      }
      const arrStart = trimmed.indexOf('[');
      const arrEnd = trimmed.lastIndexOf(']');
      if (arrStart !== -1 && arrEnd > arrStart) {
        try {
          return JSON.parse(trimmed.slice(arrStart, arrEnd + 1));
        } catch (innerErr) {
          // fall through
        }
      }
      return null;
    }
  },

  async request(endpoint, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;

    const { headers: customHeaders, body, method, ...restOptions } = options;

    const headers = {
      Accept: 'application/json',
      ...(customHeaders || {}),
    };

    if (body && !(body instanceof FormData) && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    const resolvedMethod = (method || 'GET').toUpperCase();
    if (resolvedMethod !== 'GET' && resolvedMethod !== 'HEAD' && this._csrfToken) {
      headers['X-CSRF-Token'] = this._csrfToken;
    }

    if (this._token) {
      headers['Authorization'] = `Bearer ${this._token}`;
    }

    const config = {
      method: resolvedMethod,
      headers,
      credentials: 'include',
      ...restOptions,
    };

    if (body) {
      config.body = (body instanceof FormData) ? body : JSON.stringify(body);
    }

    const response = await fetch(url, config);
    const text = await response.text();
    let payload = null;
    if (text) {
      payload = this._safeJsonParse(text);
      if (!payload) {
        payload = { message: text };
      }
    }

    if (!response.ok || payload?.status === 'error') {
      if (response.status === 401 && !options._retried && this._refreshToken) {
        await this._refreshSession();
        return this.request(endpoint, { ...options, _retried: true });
      }

      const message = payload?.error?.message || payload?.message || `HTTP Error ${response.status}`;
      if (!options.silent && typeof document !== 'undefined') {
        document.dispatchEvent(new CustomEvent('api:error', { detail: { message } }));
      }
      const err = new Error(message);
      err.code = response.status;
      err.data = payload?.data || null;
      throw err;
    }

    return payload;
  },

  async _refreshSession() {
    if (this._refreshing) return this._refreshing;
    this._refreshing = (async () => {
      const token = this._refreshToken;
      if (!token) throw new Error('Missing refresh token');
      const res = await this.request('/auth/refresh', {
        method: 'POST',
        body: { refresh_token: token },
        _retried: true,
      });
      if (res?.data?.csrf_token) this.setCsrfToken(res.data.csrf_token);
      if (res?.data?.refresh_token) this.setRefreshToken(res.data.refresh_token);
      const current = this.loadSession() || {};
      const session = {
        ...current,
        apiToken: current.apiToken || this._token,
        csrfToken: this._csrfToken,
        refreshToken: this._refreshToken,
      };
      this.setSession(session);
      this.saveSession(session);
      return res;
    })();
    try {
      return await this._refreshing;
    } finally {
      this._refreshing = null;
    }
  },

  auth: {
    async login(email, password, remember = false, turnstileToken = '') {
      const res = await NMR_API.request('/auth/login', {
        method: 'POST',
        body: { email, password, remember, turnstile_token: turnstileToken },
      });
      if (res.data?.api_token) NMR_API.setToken(res.data.api_token);
      if (res.data?.csrf_token) NMR_API.setCsrfToken(res.data.csrf_token);
      if (res.data?.refresh_token) NMR_API.setRefreshToken(res.data.refresh_token);
      return res.data;
    },
    async register(username, email, password, turnstileToken = '') {
      return await NMR_API.request('/auth/register', {
        method: 'POST',
        body: { username, email, password, turnstile_token: turnstileToken },
      });
    },
    async refresh(refreshToken = null) {
      const token = refreshToken || NMR_API._refreshToken;
      return await NMR_API.request('/auth/refresh', { method: 'POST', body: { refresh_token: token } });
    },
    async logout() {
      const res = await NMR_API.request('/auth/logout', { method: 'POST' });
      NMR_API.clearSession();
      return res;
    },
  },

  content: {
    async getHome() { return await NMR_API.request('/home'); },
    async getByType(type, page = 1, perPage = 20) { return await NMR_API.request(`/content/type/${type}?page=${page}&per_page=${perPage}`); },
    async getDetails(type, slug) { return await NMR_API.request(`/content/${type}/${slug}`); },
    async getChapters(type, slug) { return await NMR_API.request(`/content/${type}/${slug}/chapters`); },
    async getChapterFull(type, slug, chapterNumber) { return await NMR_API.request(`/content/${type}/${slug}/chapter/${chapterNumber}`); },
    async getLatestChapters(page = 1, perPage = 20) { return await NMR_API.request(`/latest-chapters?page=${page}&per_page=${perPage}`); },
  },

  wallet: {
    async getSummary() { return await NMR_API.request('/user/wallet'); },
    async getTransactions(page = 1, perPage = 20) { return await NMR_API.request(`/user/wallet/transactions?page=${page}&per_page=${perPage}`); },
    async unlockChapter(chapterId) { return await NMR_API.request(`/chapter/${chapterId}/unlock`, { method: 'POST' }); },
  },

  shop: {
    async getPackages() { return await NMR_API.request('/shop/packages'); },
    async getFeatures() { return await NMR_API.request('/shop/features'); },
  },

  system: {
    async getI18n(lang) { return await NMR_API.request(`/i18n/${lang}`, { silent: true }); },
  },
};

export default NMR_API;
