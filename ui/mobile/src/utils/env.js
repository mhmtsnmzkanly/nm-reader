/**
 * Returns true when the app runs inside a Cordova shell.
 */
export function isCordovaRuntime() {
  return typeof window !== 'undefined' && typeof window.cordova !== 'undefined';
}

/**
 * Resolves the API base URL in a way that works on both web and Cordova.
 *
 * Order of precedence:
 * 1. runtime override injected into `window`
 * 2. runtime override stored in local storage
 * 3. default relative web API path
 */
export function getApiBaseUrl() {
  if (typeof window !== 'undefined') {
    const runtimeValue = window.NMR_MOBILE_API_BASE || window.NMR_API_BASE;
    if (typeof runtimeValue === 'string' && runtimeValue.trim() !== '') {
      return runtimeValue.trim().replace(/\/+$/, '');
    }
  }

  if (typeof localStorage !== 'undefined') {
    const storedValue = localStorage.getItem('nmr_mobile_api_base');
    if (typeof storedValue === 'string' && storedValue.trim() !== '') {
      return storedValue.trim().replace(/\/+$/, '');
    }
  }

  return '/api/v1';
}

/**
 * Returns the application title used by the shell.
 */
export function getAppTitle() {
  return 'NMR Mobile';
}
