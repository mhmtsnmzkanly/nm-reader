const DEFAULT_API_BASE_URL = '/api/v1';
const API_BASE_URL =
  (typeof window !== 'undefined' && (window.NMR_MOBILE_API_BASE || window.NMR_API_BASE))
    ? (window.NMR_MOBILE_API_BASE || window.NMR_API_BASE)
    : DEFAULT_API_BASE_URL;

const NMR_API = {
  _token: null,
  _csrfToken: null,
  _refreshToken: null,

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
    const payload = text ? JSON.parse(text) : null;

    if (!response.ok || payload?.status === 'error') {
      const message = payload?.error?.message || payload?.message || `HTTP Error ${response.status}`;
      const err = new Error(message);
      err.code = response.status;
      err.data = payload?.data || null;
      throw err;
    }

    return payload;
  },

  auth: {
    async login(email, password, remember = false) {
      const res = await NMR_API.request('/auth/login', {
        method: 'POST',
        body: { email, password, remember },
      });
      if (res.data?.api_token) NMR_API.setToken(res.data.api_token);
      if (res.data?.csrf_token) NMR_API.setCsrfToken(res.data.csrf_token);
      if (res.data?.refresh_token) NMR_API.setRefreshToken(res.data.refresh_token);
      return res.data;
    },
    async register(username, email, password) {
      return await NMR_API.request('/auth/register', { method: 'POST', body: { username, email, password } });
    },
    async refresh(refreshToken = null) {
      const token = refreshToken || NMR_API._refreshToken;
      return await NMR_API.request('/auth/refresh', { method: 'POST', body: { refresh_token: token } });
    },
    async logout() {
      const res = await NMR_API.request('/auth/logout', { method: 'POST' });
      NMR_API.setSession({ apiToken: null, csrfToken: null, refreshToken: null });
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
    async unlockChapter(chapterId) { return await NMR_API.request(`/chapter/${chapterId}/unlock`, { method: 'POST' }); },
  },
};

export default NMR_API;
