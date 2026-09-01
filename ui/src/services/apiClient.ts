import { ApiResponse } from '../types/api';

declare global {
  interface Window {
    __NMR_CONTEXT?: {
      auth?: {
        csrf_token?: string;
        is_logged_in?: boolean;
        is_admin?: boolean;
        user_id?: string;
        username?: string;
      };
      site_config?: Record<string, any>;
    };
  }
}

export function getCsrfToken(): string {
  if (typeof window !== 'undefined' && window.__NMR_CONTEXT?.auth?.csrf_token) {
    return window.__NMR_CONTEXT.auth.csrf_token;
  }
  const match = document.cookie.match(/csrf_token=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
}

export async function request<T>(
  endpoint: string,
  options: RequestInit = {}
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

    if (res.status === 204) {
      return { status: 'success', data: null as unknown as T };
    }

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
      return {
        status: 'error',
        error: {
          code: data?.error?.code || `HTTP_${res.status}`,
          key: data?.error?.key || 'error',
          message: data?.error?.message || data?.message || `Sunucu hatası (${res.status})`,
        },
      };
    }

    return data;
  } catch (err: any) {
    return {
      status: 'error',
      error: {
        code: 'NETWORK_ERROR',
        key: 'network_error',
        message: err?.message || 'Ağ bağlantısı hatası',
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
    return request<T>(`${url}${query}`, { method: 'GET' });
  },
  post: <T>(url: string, body?: any) =>
    request<T>(url, {
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body),
    }),
  put: <T>(url: string, body?: any) =>
    request<T>(url, {
      method: 'PUT',
      body: body instanceof FormData ? body : JSON.stringify(body),
    }),
  delete: <T>(url: string, body?: any) =>
    request<T>(url, {
      method: 'DELETE',
      body: body ? JSON.stringify(body) : undefined,
    }),
};
