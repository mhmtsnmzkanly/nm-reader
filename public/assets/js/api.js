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
