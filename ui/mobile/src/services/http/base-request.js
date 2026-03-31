import { getApiBaseUrl } from '../../utils/env.js';
import { debugError, debugTrace } from '../../utils/debug.js';

/**
 * Performs a raw HTTP request and returns the parsed JSON envelope plus status.
 */
export async function baseRequest(endpoint, options = {}) {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), options.timeout ?? 12000);
  const method = options.method || 'GET';
  const requestUrl = `${getApiBaseUrl()}${endpoint}`;

  debugTrace({
    scope: 'http',
    action: 'baseRequest:start',
    caller: 'ui/mobile/src/services/http/base-request.js#baseRequest',
    callee: requestUrl,
    next: 'perform fetch request',
    detail: {
      method,
    },
  });

  try {
    const response = await fetch(requestUrl, {
      method,
      headers: options.headers || {},
      body: options.body,
      signal: controller.signal,
      credentials: 'include',
    });

    const rawText = await response.text();
    let payload = null;

    try {
      payload = rawText ? JSON.parse(rawText) : null;
    } catch (error) {
      payload = rawText ? { message: rawText } : null;
    }

    debugTrace({
      scope: 'http',
      action: 'baseRequest:response',
      caller: 'fetch',
      callee: requestUrl,
      next: 'normalize parsed payload',
      detail: {
        method,
        status: response.status,
        ok: response.ok,
      },
    });

    return {
      ok: response.ok,
      status: response.status,
      payload,
    };
  } catch (error) {
    debugError({
      scope: 'http',
      action: 'baseRequest:error',
      caller: 'ui/mobile/src/services/http/base-request.js#baseRequest',
      callee: requestUrl,
      next: 'propagate fetch failure to caller',
      error,
      detail: {
        method,
      },
    });
    throw error;
  } finally {
    window.clearTimeout(timeout);
  }
}
