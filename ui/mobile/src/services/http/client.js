import { appStore } from '../../store/app-store.js';
import { baseRequest } from './base-request.js';

let unauthorizedHandler = null;

/**
 * Registers a single unauthorized handler used by the bootstrap flow.
 */
export function setUnauthorizedHandler(handler) {
  unauthorizedHandler = handler;
}

/**
 * Builds request headers consistently for JSON and multipart payloads.
 */
function buildHeaders(options) {
  const headers = {
    Accept: 'application/json',
    ...(options.headers || {}),
  };

  const token = appStore.getState().auth.accessToken;
  if (token && !options.skipAuth) {
    headers.Authorization = `Bearer ${token}`;
  }

  if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
    headers['Content-Type'] = 'application/json';
  }

  return headers;
}

/**
 * Converts the response envelope into a stable `data/meta` object or throws a normalized error.
 */
export async function request(endpoint, options = {}) {
  const headers = buildHeaders(options);
  const body = options.body instanceof FormData
    ? options.body
    : options.body
      ? JSON.stringify(options.body)
      : undefined;

  const result = await baseRequest(endpoint, {
    method: options.method,
    headers,
    body,
    timeout: options.timeout,
  });

  if (result.status === 401 && typeof unauthorizedHandler === 'function' && !options.skipAuthRecovery) {
    const recovered = await unauthorizedHandler();
    if (recovered) {
      return request(endpoint, {
        ...options,
        skipAuthRecovery: true,
      });
    }
  }

  if (!result.ok || result.payload?.status === 'error') {
    const error = new Error(
      result.payload?.error?.message ||
      result.payload?.message ||
      `Request failed with status ${result.status}`
    );
    error.status = result.status;
    error.payload = result.payload;
    throw error;
  }

  return {
    data: result.payload?.data ?? result.payload ?? null,
    meta: result.payload?.meta ?? {},
  };
}
