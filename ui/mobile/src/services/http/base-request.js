import { getApiBaseUrl } from '../../utils/env.js';

/**
 * Performs a raw HTTP request and returns the parsed JSON envelope plus status.
 */
export async function baseRequest(endpoint, options = {}) {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), options.timeout ?? 12000);

  try {
    const response = await fetch(`${getApiBaseUrl()}${endpoint}`, {
      method: options.method || 'GET',
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

    return {
      ok: response.ok,
      status: response.status,
      payload,
    };
  } finally {
    window.clearTimeout(timeout);
  }
}
