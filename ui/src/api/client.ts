import { getApiConfig } from './config';
import { ApiClientError, NetworkError, TimeoutError } from './errors';
import { getCsrfToken, setCsrfToken, withRefreshLock, notifyUnauthorized } from './auth';
import type { ApiResponse, ApiSuccess, HttpMethod, RequestOptions } from './types';

/**
 * Checks if the endpoint path is exempt from CSRF validation.
 */
function isCsrfExempt(url: string): boolean {
  const exemptPatterns = [
    '/auth/login',
    '/auth/register',
    '/auth/refresh',
    '/auth/logout',
    '/log/error',
  ];
  return exemptPatterns.some((path) => url.includes(path));
}

/**
 * Builds full URL with base path and query parameters.
 */
function buildUrl(
  endpoint: string,
  baseUrl: string,
  params?: Record<string, string | number | boolean | undefined | null>
): string {
  let fullPath = endpoint.startsWith('http://') || endpoint.startsWith('https://')
    ? endpoint
    : `${baseUrl.replace(/\/+$/, '')}/${endpoint.replace(/^\/+/, '')}`;

  if (params && Object.keys(params).length > 0) {
    const searchParams = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null) {
        searchParams.append(key, String(value));
      }
    }
    const queryString = searchParams.toString();
    if (queryString) {
      fullPath += (fullPath.includes('?') ? '&' : '?') + queryString;
    }
  }

  return fullPath;
}

export class HttpClient {
  /**
   * Dispatches an HTTP request with envelope parsing, CSRF injection, and error normalization.
   */
  public async request<T>(
    method: HttpMethod,
    endpoint: string,
    options: RequestOptions = {}
  ): Promise<ApiResponse<T>> {
    const config = getApiConfig();
    const url = buildUrl(endpoint, config.baseUrl, options.params);
    const isMutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase());

    const headers = new Headers({
      ...config.defaultHeaders,
      ...(options.headers as Record<string, string>),
    });

    // Auto-attach CSRF token for mutating requests unless exempt or explicitly skipped
    if (isMutating && !options.skipCsrf && !isCsrfExempt(url)) {
      const csrfToken = getCsrfToken();
      if (csrfToken) {
        headers.set('X-CSRF-Token', csrfToken);
      }
    }

    let requestBody: BodyInit | null = null;
    if (options.body !== undefined && options.body !== null) {
      if (options.body instanceof FormData || typeof options.body === 'string') {
        requestBody = options.body;
        if (options.body instanceof FormData) {
          headers.delete('Content-Type'); // Let browser generate boundary
        }
      } else {
        requestBody = JSON.stringify(options.body);
      }
    }

    const controller = new AbortController();
    const timeoutMs = options.timeout ?? config.timeoutMs;
    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

    try {
      const response = await fetch(url, {
        method,
        headers,
        body: requestBody,
        credentials: config.withCredentials ? 'include' : 'same-origin',
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      // Capture any new CSRF token provided in response headers
      const resCsrf = response.headers.get('X-CSRF-Token');
      if (resCsrf) {
        setCsrfToken(resCsrf);
      }

      // Parse JSON response envelope
      let json: any = null;
      const text = await response.text();
      if (text && text.trim() !== '') {
        try {
          json = JSON.parse(text);
        } catch {
          // If response is not JSON, construct error envelope
          throw new ApiClientError(response.status, {
            code: response.status,
            key: 'INVALID_JSON_RESPONSE',
            message: text.slice(0, 100) || response.statusText,
          });
        }
      }

      // Handle 419 CSRF Expiration / Mismatch (Single automatic retry)
      if (response.status === 419 && !options.skipCsrf) {
        return this.handleCsrfRetry<T>(method, endpoint, options);
      }

      // Handle 401 Unauthorized
      if (response.status === 401) {
        if (!options.skipAuthRetry && !url.includes('/auth/')) {
          notifyUnauthorized();
        }
      }

      // Validate standard envelope format or raise ApiClientError
      if (!response.ok) {
        const errorDetail = json?.error || {
          code: response.status,
          key: response.status === 401 ? 'UNAUTHORIZED' : response.status === 403 ? 'FORBIDDEN' : 'HTTP_ERROR',
          message: json?.message || response.statusText || 'Request failed',
          fields: json?.fields,
        };
        throw new ApiClientError(response.status, errorDetail);
      }

      // Extract CSRF token if returned in auth response payload
      if (json?.data?.csrf_token) {
        setCsrfToken(json.data.csrf_token);
      }

      return json as ApiResponse<T>;
    } catch (err: unknown) {
      clearTimeout(timeoutId);

      if (err instanceof ApiClientError) {
        throw err;
      }

      if (err instanceof DOMException && err.name === 'AbortError') {
        throw new TimeoutError(`Request to ${endpoint} timed out after ${timeoutMs}ms`);
      }

      if (err instanceof TypeError && err.message.includes('fetch')) {
        throw new NetworkError(`Network connection failed while calling ${endpoint}`);
      }

      throw new ApiClientError(0, {
        code: 0,
        key: 'NETWORK_OR_CLIENT_ERROR',
        message: err instanceof Error ? err.message : 'Unknown client error',
      });
    }
  }

  /**
   * Automatic recovery from HTTP 419 CSRF mismatch by re-fetching CSRF token and re-trying once.
   */
  private async handleCsrfRetry<T>(
    method: HttpMethod,
    endpoint: string,
    options: RequestOptions
  ): Promise<ApiResponse<T>> {
    try {
      const refreshedToken = await withRefreshLock(async () => {
        // Exchange session token via POST /api/v1/auth/refresh without CSRF
        const refreshRes = await this.post<{ csrf_token: string }>(
          '/auth/refresh',
          {},
          { skipCsrf: true, skipAuthRetry: true }
        );
        return (refreshRes as ApiSuccess<{ csrf_token: string }>).data?.csrf_token || null;
      });

      if (refreshedToken) {
        setCsrfToken(refreshedToken);
        // Re-execute mutating request with skipCsrf: true on the retry handler to prevent loop
        return await this.request<T>(method, endpoint, {
          ...options,
          skipCsrf: true,
          headers: {
            ...(options.headers as Record<string, string>),
            'X-CSRF-Token': refreshedToken,
          },
        });
      }
    } catch {
      // If refresh fails, fall through to default 419 error
    }

    throw new ApiClientError(419, {
      code: 419,
      key: 'CSRF_ERROR',
      message: 'CSRF token mismatch or expired session',
    });
  }

  public get<T>(endpoint: string, options?: RequestOptions): Promise<ApiResponse<T>> {
    return this.request<T>('GET', endpoint, options);
  }

  public post<T>(endpoint: string, body?: unknown, options?: RequestOptions): Promise<ApiResponse<T>> {
    return this.request<T>('POST', endpoint, { ...options, body });
  }

  public put<T>(endpoint: string, body?: unknown, options?: RequestOptions): Promise<ApiResponse<T>> {
    return this.request<T>('PUT', endpoint, { ...options, body });
  }

  public patch<T>(endpoint: string, body?: unknown, options?: RequestOptions): Promise<ApiResponse<T>> {
    return this.request<T>('PATCH', endpoint, { ...options, body });
  }

  public delete<T>(endpoint: string, options?: RequestOptions): Promise<ApiResponse<T>> {
    return this.request<T>('DELETE', endpoint, options);
  }
}

export const apiClient = new HttpClient();
