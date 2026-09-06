import { ApiResponse, BlogSummary, ContentDetail, ContentDetailChapter, ContentSummary, HomeData, MeData } from '../types/api';

declare global {
  interface Window {
    __NMR_CONTEXT?: {
      auth?: {
        csrf_token?: string;
        is_logged_in?: boolean;
        user_id?: string;
        username?: string;
        roles?: string[];
        permissions?: string[];
        profile?: MeData['profile'];
        preferences?: MeData['preferences'];
        wallet?: MeData['wallet'];
        notifications?: MeData['notifications'];
      };
      site_config?: Record<string, any>;
      current_page?: {
        route?: string;
        data?: {
          home?: HomeData;
          type?: string;
          slug?: string;
          content?: ContentDetail;
          chapters?: ContentDetailChapter[];
          related?: ContentSummary[];
          blog?: BlogSummary;
          [key: string]: unknown;
        };
        [key: string]: unknown;
      };
    };
  }
}

export function getCsrfToken(): string {
  if (typeof window !== 'undefined' && window.__NMR_CONTEXT?.auth?.csrf_token) {
    return window.__NMR_CONTEXT.auth.csrf_token;
  }
  if (typeof document === 'undefined') return '';
  const match = document.cookie.match(/csrf_token=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
}

type CachedGet = {
  expiresAt: number;
  value: ApiResponse<unknown>;
};

const inFlightGets = new Map<string, Promise<ApiResponse<unknown>>>();
const publicGetCache = new Map<string, CachedGet>();

function publicGetTtl(endpoint: string): number {
  const path = endpoint.split('?', 1)[0];
  if (typeof window !== 'undefined' && window.__NMR_CONTEXT?.auth?.is_logged_in) return 0;
  if (path === '/home' || path === '/blogs') return 30_000;
  if (path === '/genres' || path === '/tags' || path === '/series_genres' || path === '/series_tags') return 120_000;
  if (path.startsWith('/content/type/') || path.startsWith('/genre/') || path.startsWith('/tag/')) return 30_000;
  if (path.startsWith('/search/suggest')) return 5_000;
  return 0;
}

function clearPublicGetCache(): void {
  publicGetCache.clear();
}

function cachedGet<T>(endpoint: string): Promise<ApiResponse<T>> {
  const viewerKey = typeof window !== 'undefined' && window.__NMR_CONTEXT?.auth?.user_id
    ? String(window.__NMR_CONTEXT.auth.user_id)
    : 'guest';
  const cacheKey = `${viewerKey}:${endpoint}`;
  const ttl = publicGetTtl(endpoint);
  const now = Date.now();

  if (ttl > 0) {
    const cached = publicGetCache.get(cacheKey);
    if (cached && cached.expiresAt > now) {
      return Promise.resolve(cached.value as ApiResponse<T>);
    }
    if (cached) publicGetCache.delete(cacheKey);
  }

  const current = inFlightGets.get(cacheKey);
  if (current) return current as Promise<ApiResponse<T>>;

  const pending = request<T>(endpoint, { method: 'GET' }) as Promise<ApiResponse<unknown>>;
  inFlightGets.set(cacheKey, pending);
  pending.then((result) => {
    if (ttl > 0 && result.status === 'success') {
      publicGetCache.set(cacheKey, { expiresAt: Date.now() + ttl, value: result });
    }
  }).finally(() => {
    inFlightGets.delete(cacheKey);
  });

  return pending as Promise<ApiResponse<T>>;
}

export async function request<T>(
  endpoint: string,
  options: RequestInit = {},
  retryCsrf = true
): Promise<ApiResponse<T>> {
  const url = endpoint.startsWith('http') ? endpoint : `/api/v1${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.headers as Record<string, string> || {}),
  };

  const csrf = getCsrfToken();
  if (csrf) {
    headers['X-CSRF-Token'] = csrf;
  }

  if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
    headers['Content-Type'] = 'application/json';
  }

  try {
    const res = await fetch(url, {
      ...options,
      headers,
      credentials: 'same-origin',
    });

    const responseCsrf = res.headers.get('X-CSRF-Token');
    if (responseCsrf && typeof window !== 'undefined' && window.__NMR_CONTEXT?.auth) {
      window.__NMR_CONTEXT.auth.csrf_token = responseCsrf;
    }

    if (res.status === 419 && retryCsrf) {
      return request<T>(endpoint, options, false);
    }

    if (res.status === 204) {
      return { status: 'success', data: null as unknown as T, meta: {}, error: null };
    }

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
      return {
        status: 'error',
        data: null,
        meta: {},
        error: {
          code: Number(data?.error?.code) || res.status,
          key: data?.error?.key || 'error',
          message: data?.error?.message || data?.message || `Sunucu hatası (${res.status})`,
          params: data?.error?.params || {},
        },
      };
    }

    return data;
  } catch (err: any) {
    return {
      status: 'error',
      data: null,
      meta: {},
      error: {
        code: 0,
        key: 'network_error',
        message: err?.message || 'Ağ bağlantısı hatası',
        params: {},
      },
    };
  }
}

export const api = {
  get: <T>(url: string, params?: Record<string, any>) => {
    let query = '';
    if (params) {
      const sp = new URLSearchParams();
      Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') {
          sp.append(k, String(v));
        }
      });
      const str = sp.toString();
      if (str) query = `?${str}`;
    }
    return cachedGet<T>(`${url}${query}`);
  },
  post: <T>(url: string, body?: any) => {
    clearPublicGetCache();
    return request<T>(url, {
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body),
    });
  },
  put: <T>(url: string, body?: any) => {
    clearPublicGetCache();
    return request<T>(url, {
      method: 'PUT',
      body: body instanceof FormData ? body : JSON.stringify(body),
    });
  },
  delete: <T>(url: string, body?: any) => {
    clearPublicGetCache();
    return request<T>(url, {
      method: 'DELETE',
      body: body ? JSON.stringify(body) : undefined,
    });
  },
};
