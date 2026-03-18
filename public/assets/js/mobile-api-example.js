/**
 * NMR API Client - Full Frontend API Bridge (Updated 2026-03-09)
 * 
 * This module provides a comprehensive technical implementation for all 
 * frontend-facing endpoints of the NovelMangaReader API.
 */

const API_BASE_URL = 'http://localhost:8080/api/v1'; // Update this to your production URL

const NMR_API = {
    _token: null,

    setToken(token) {
        this._token = token;
    },

    /**
     * Core request handler with automatic token injection.
     */
    async request(endpoint, options = {}) {
        const url = `${API_BASE_URL}${endpoint}`;
        
        // Extract to avoid spread overwriting
        const { headers: customHeaders, body, method, ...restOptions } = options;

        const headers = {
            'Accept': 'application/json',
            ...(customHeaders || {})
        };

        // Automatic Content-Type for JSON (if not FormData)
        if (body && !(body instanceof FormData) && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }

        if (this._token) {
            headers['Authorization'] = `Bearer ${this._token}`;
        }

        const config = {
            method: (method || 'GET').toUpperCase(),
            headers,
            ...restOptions
        };

        if (body) {
            config.body = (body instanceof FormData) ? body : JSON.stringify(body);
        }

        try {
            const response = await fetch(url, config);
            const payload = await response.json().catch(() => ({ status: 'error', message: 'Network Response Error' }));

            if (!response.ok || payload.status === 'error') {
                throw {
                    code: response.status,
                    message: payload.message || `HTTP Error ${response.status}`,
                    data: payload.data || null
                };
            }

            return payload; // { status: "success", data: ..., meta: ... }
        } catch (error) {
            console.error(`[NMR API] Error at ${endpoint}:`, error);
            throw error;
        }
    },

    // --- AUTH & IDENTITY ---
    auth: {
        async login(email, password) {
            const res = await NMR_API.request('/auth/login', {
                method: 'POST',
                body: { email, password }
            });
            if (res.data?.api_token) NMR_API.setToken(res.data.api_token);
            return res.data;
        },
        async register(username, email, password) {
            return await NMR_API.request('/auth/register', { method: 'POST', body: { username, email, password } });
        },
        async refresh() {
            return await NMR_API.request('/auth/refresh', { method: 'POST' });
        },
        async logout() {
            const res = await NMR_API.request('/auth/logout', { method: 'POST' });
            NMR_API.setToken(null);
            return res;
        },
        async getSessions() {
            return await NMR_API.request('/auth/sessions');
        },
        async revokeSession(sessionKey) {
            return await NMR_API.request(`/auth/sessions/${sessionKey}`, { method: 'DELETE' });
        }
    },

    // --- CONTENT DISCOVERY (PUBLIC) ---
    content: {
        async getHome() { return await NMR_API.request('/home'); },
        async getByType(type, page = 1) { return await NMR_API.request(`/content/type/${type}?page=${page}`); },
        async getDetails(type, slug) { return await NMR_API.request(`/content/${type}/${slug}`); },
        async getChapters(type, slug) { return await NMR_API.request(`/content/${type}/${slug}/chapters`); },
        
        /**
         * Fetches raw reading data (Markdown or Image URLs).
         * Target: Public discover reader logic.
         */
        async getChapterData(chapterNumber, slug, type) { 
            return await NMR_API.request(`/chapter/${chapterNumber}?slug=${slug}&type=${type}`); 
        },

        /**
         * Fetches full chapter detail payload (Metadata + Access + Data).
         * Target: Premium/Dynamic reader logic with lock checking.
         */
        async getChapterFull(type, slug, chapterNumber) { 
            return await NMR_API.request(`/content/${type}/${slug}/chapter/${chapterNumber}`); 
        },

        async getLatestChapters(page = 1) { return await NMR_API.request(`/latest-chapters?page=${page}`); },
        async getLatestByType(type, page = 1) { return await NMR_API.request(`/content/${type}/chapters?page=${page}`); },
        async search(query) { return await NMR_API.request(`/search?q=${encodeURIComponent(query)}`); },
        async suggest(query) { return await NMR_API.request(`/search/suggest?q=${encodeURIComponent(query)}`); }
    },

    // --- TAXONOMY (GENRES & TAGS) ---
    taxonomy: {
        async getGenres() { return await NMR_API.request('/series_genres'); },
        async getTags() { return await NMR_API.request('/series_tags'); },
        async getByGenre(slug, page = 1) { return await NMR_API.request(`/genre/${slug}?page=${page}`); },
        async getByTag(slug, page = 1) { return await NMR_API.request(`/tag/${slug}?page=${page}`); }
    },

    // --- SOCIAL (COMMENTS, VOTES, BLOGS) ---
    social: {
        async getBlogs(page = 1) { return await NMR_API.request(`/blogs?page=${page}`); },
        async getBlog(slug) { return await NMR_API.request(`/blogs/${slug}`); },
        async getBlogComments(slug, cursor = null) { return await NMR_API.request(cursor ? `/blogs/${slug}/comments?cursor=${encodeURIComponent(cursor)}` : `/blogs/${slug}/comments`); },
        async getChapterComments(chapterId, cursor = null) { return await NMR_API.request(cursor ? `/chapter/${chapterId}/comments?cursor=${encodeURIComponent(cursor)}` : `/chapter/${chapterId}/comments`); },
        async getSeriesComments(type, slug, cursor = null) { return await NMR_API.request(cursor ? `/content/${type}/${slug}/comments?cursor=${encodeURIComponent(cursor)}` : `/content/${type}/${slug}/comments`); },
        
        // Protected Social Actions
        async postBlog(title, body, coverImage = null) {
            return await NMR_API.request('/blogs', { method: 'POST', body: { title, body, cover_image: coverImage } });
        },
        async uploadBlogImage(formData) {
            // Must be FormData instance
            return await NMR_API.request('/blogs/image', { method: 'POST', body: formData });
        },
        async voteBlog(slug, vote) { // vote: 1 or -1
            return await NMR_API.request(`/blogs/${slug}/vote`, { method: 'POST', body: { vote } });
        },
        async postComment(targetType, targetId, body, extraParams = {}) { 
            // targetType: 'series', 'chapter', 'blog'
            let endpoint;
            if (targetType === 'series') {
                const type = extraParams.type || 'manga'; // Default to manga if not provided
                endpoint = `/content/${type}/${targetId}/comment`;
            } else if (targetType === 'chapter') {
                endpoint = `/chapter/${targetId}/comment`;
            } else if (targetType === 'blog') {
                endpoint = `/blogs/${targetId}/comments`;
            } else {
                throw new Error('Invalid comment target type');
            }
            return await NMR_API.request(endpoint, { method: 'POST', body: { body } });
        },
        async voteComment(commentId, vote) {
            return await NMR_API.request(`/comments/${commentId}/vote`, { method: 'POST', body: { vote } });
        },
        async voteBlogComment(blogSlug, commentId, vote) {
            return await NMR_API.request(`/blogs/${blogSlug}/comments/${commentId}/vote`, { method: 'POST', body: { vote } });
        }
    },

    // --- USER PROFILE & INTERACTION ---
    user: {
        async getPublicProfile(person) { return await NMR_API.request(`/profile/${person}`); },
        async getMyProfile() { return await NMR_API.request('/user/profile'); },
        async updateProfile(data) { return await NMR_API.request('/user/profile', { method: 'POST', body: data }); },
        async getHistory(page = 1) { return await NMR_API.request(`/user/history?page=${page}`); },
        async getLibrary(page = 1) { return await NMR_API.request(`/user/follows?page=${page}`); },
        async getMyBlogs() { return await NMR_API.request('/user/blogs'); },
        async getPreferences() { return await NMR_API.request('/user/preferences'); },
        async updatePreferences(prefs) { return await NMR_API.request('/user/preferences', { method: 'PUT', body: prefs }); },
        
        // Notifications
        async getNotifications(cursor = null) { return await NMR_API.request(cursor ? `/user/notifications?cursor=${encodeURIComponent(cursor)}` : `/user/notifications`); },
        async markNotificationsRead() { return await NMR_API.request('/user/notifications/read', { method: 'POST' }); },

        // Social Relations
        async getFollowedUsers() { return await NMR_API.request('/user/follows/users'); },
        async followUser(person) { return await NMR_API.request(`/user/follows/${person}`, { method: 'POST' }); },
        async unfollowUser(person) { return await NMR_API.request(`/user/follows/${person}`, { method: 'DELETE' }); },

        // Content Interaction
        async followSeries(type, slug) { return await NMR_API.request(`/content/${type}/${slug}/follow`, { method: 'POST' }); },
        async unfollowSeries(type, slug) { return await NMR_API.request(`/content/${type}/${slug}/follow`, { method: 'DELETE' }); },
        async rateSeries(type, slug, rating) { 
            return await NMR_API.request(`/content/${type}/${slug}/rate`, { method: 'POST', body: { rating } }); 
        },
        async logActivity(chapterId, duration) {
            return await NMR_API.request('/user/activity', { method: 'POST', body: { chapter_id: chapterId, duration } });
        }
    },

    // --- MONETIZATION & WALLET ---
    wallet: {
        async getSummary() { return await NMR_API.request('/user/wallet'); },
        async getTransactions(page = 1) { return await NMR_API.request(`/user/wallet/transactions?page=${page}`); },
        async getSeriesUnlocks() { return await NMR_API.request('/user/unlocks/series'); },
        async getChapterUnlocks() { return await NMR_API.request('/user/unlocks/chapters'); },
        async unlockSeries(type, slug) { return await NMR_API.request(`/content/${type}/${slug}/unlock`, { method: 'POST' }); },
        async unlockChapter(chapterId) { return await NMR_API.request(`/chapter/${chapterId}/unlock`, { method: 'POST' }); },
        
        // Shop
        async getPackages() { return await NMR_API.request('/shop/packages'); },
        async getFeatures() { return await NMR_API.request('/shop/features'); },
        async getMyFeatures() { return await NMR_API.request('/user/features'); },
        async getEntitlements(page = 1) { return await NMR_API.request(`/user/features/entitlements?page=${page}`); },
        async purchaseAdFree() { return await NMR_API.request('/user/features/ad-free/purchase', { method: 'POST' }); }
    },

    // --- SYSTEM ---
    system: {
        async getI18n(lang) { return await NMR_API.request(`/i18n/${lang}`); },
        async logError(errorData) { return await NMR_API.request('/log/error', { method: 'POST', body: errorData }); }
    }
};

export default NMR_API;
