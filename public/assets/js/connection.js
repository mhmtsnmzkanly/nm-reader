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
