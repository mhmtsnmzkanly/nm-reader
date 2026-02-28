/**
 * NMR API Client - Mobile & CSR Application Bridge
 * 
 * This module provides a standardized way to interact with the NovelMangaReader API.
 * Features:
 * - Automatic Bearer Token injection.
 * - Centralized Base URL management.
 * - Standardized Error Handling.
 * - Simple JSON serialization.
 */

const API_BASE_URL = 'http://localhost:8080/api/v1'; // Update this to your production URL

const NMR_API = {
    /**
     * Internal token storage (e.g., SecureStore in React Native or localStorage in Web)
     */
    _token: null,

    /**
     * Sets the persistent API token after a successful login.
     */
    setToken(token) {
        this._token = token;
        // Optional: Persist to storage here
    },

    /**
     * Core request handler.
     */
    async request(endpoint, options = {}) {
        const url = `${API_BASE_URL}${endpoint}`;
        
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...(options.headers || {})
        };

        // Inject Bearer Token if available
        if (this._token) {
            headers['Authorization'] = `Bearer ${this._token}`;
        }

        const config = {
            method: (options.method || 'GET').toUpperCase(),
            headers,
            ...options
        };

        if (options.body && typeof options.body === 'object') {
            config.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, config);
            const payload = await response.json().catch(() => ({ status: 'error', error: { message: 'Network Response Error' } }));

            if (!response.ok || payload.status === 'error') {
                throw {
                    code: response.status,
                    message: payload.error?.message || `HTTP Error ${response.status}`,
                    params: payload.error?.params || []
                };
            }

            return payload; // Returns { status: "success", data: ..., meta: ... }
        } catch (error) {
            console.error(`[NMR API] Error at ${endpoint}:`, error);
            throw error;
        }
    },

    // --- AUTH MODULE ---
    auth: {
        async login(email, password) {
            const res = await NMR_API.request('/auth/login', {
                method: 'POST',
                body: { email, password }
            });
            if (res.data.api_token) {
                NMR_API.setToken(res.data.api_token);
            }
            return res.data;
        },
        async register(username, email, password) {
            return await NMR_API.request('/auth/register', {
                method: 'POST',
                body: { username, email, password }
            });
        }
    },

    // --- CONTENT MODULE ---
    content: {
        async getHome() {
            return await NMR_API.request('/home');
        },
        async getByType(type, page = 1) {
            return await NMR_API.request(`/content/type/${type}?page=${page}`);
        },
        async getDetails(type, slug) {
            return await NMR_API.request(`/content/${type}/${slug}`);
        },
        async getChapters(type, slug) {
            return await NMR_API.request(`/content/${type}/${slug}/chapters`);
        },
        async getChapter(chapterNumber) {
            return await NMR_API.request(`/chapter/${chapterNumber}`);
        },
        async search(query) {
            return await NMR_API.request(`/search?q=${encodeURIComponent(query)}`);
        }
    },

    // --- USER MODULE ---
    user: {
        async getProfile(username) {
            return await NMR_API.request(`/profile/${username}`);
        },
        async getHistory() {
            return await NMR_API.request('/user/history');
        },
        async toggleFollow(type, slug, isFollowing) {
            return await NMR_API.request(`/content/${type}/${slug}/follow`, {
                method: isFollowing ? 'DELETE' : 'POST'
            });
        }
    }
};

export default NMR_API;
