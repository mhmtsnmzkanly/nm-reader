import { appStore } from '../../store/app-store.js';
import { baseRequest } from './base-request.js';
import { debugError, debugTrace } from '../../utils/debug.js';

let unauthorizedHandler = null;

/**
 * Registers a single unauthorized handler used by the bootstrap flow.
 */
export function setUnauthorizedHandler(handler) {
  unauthorizedHandler = handler;

  debugTrace({
    scope: 'http',
    action: 'setUnauthorizedHandler',
    caller: 'ui/mobile/src/services/http/client.js#setUnauthorizedHandler',
    callee: 'unauthorizedHandler registry',
    next: 'use handler on next 401 response',
  });
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
  debugTrace({
    scope: 'http',
    action: 'request:start',
    caller: 'ui/mobile/src/services/http/client.js#request',
    callee: endpoint,
    next: 'build headers and dispatch baseRequest',
    detail: {
      method: options.method || 'GET',
      skipAuth: Boolean(options.skipAuth),
      skipAuthRecovery: Boolean(options.skipAuthRecovery),
    },
  });

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
    debugTrace({
      scope: 'http',
      action: 'request:401',
      caller: endpoint,
      callee: 'unauthorizedHandler',
      next: 'attempt auth recovery and retry request',
    });
    const recovered = await unauthorizedHandler();
    if (recovered) {
      debugTrace({
        scope: 'http',
        action: 'request:retryAfterRecovery',
        caller: endpoint,
        callee: endpoint,
        next: 'retry original request once',
      });
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
    debugError({
      scope: 'http',
      action: 'request:error',
      caller: 'ui/mobile/src/services/http/client.js#request',
      callee: endpoint,
      next: 'throw normalized request error',
      error,
      detail: {
        method: options.method || 'GET',
      },
    });
    throw error;
  }

  debugTrace({
    scope: 'http',
    action: 'request:success',
    caller: 'ui/mobile/src/services/http/client.js#request',
    callee: endpoint,
    next: 'return normalized data/meta payload',
    detail: {
      method: options.method || 'GET',
      metaKeys: Object.keys(result.payload?.meta || {}),
    },
  });

  return {
    data: result.payload?.data ?? result.payload ?? null,
    meta: result.payload?.meta ?? {},
  };
}
