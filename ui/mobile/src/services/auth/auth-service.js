import { baseRequest } from '../http/base-request.js';
import { appStore } from '../../store/app-store.js';
import { normalizeProfile } from '../../utils/normalize.js';
import { debugError, debugTrace } from '../../utils/debug.js';

let refreshPromise = null;

/**
 * Executes login with bearer-token-first mobile semantics.
 */
export async function login(credentials) {
  debugTrace({
    scope: 'auth',
    action: 'login:start',
    caller: 'ui/mobile/src/services/auth/auth-service.js#login',
    callee: '/auth/login',
    next: 'request access and refresh tokens',
    detail: {
      email: credentials.email || '',
    },
  });

  const result = await baseRequest('/auth/login', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      email: credentials.email,
      password: credentials.password,
      remember: true,
      turnstile_token: credentials.turnstileToken || '',
    }),
  });

  if (!result.ok || result.payload?.status === 'error') {
    const error = new Error(result.payload?.error?.message || result.payload?.message || 'Login failed');
    error.status = result.status;
    debugError({
      scope: 'auth',
      action: 'login:error',
      caller: 'login',
      callee: '/auth/login',
      next: 'return login error to page',
      error,
    });
    throw error;
  }

  const data = result.payload?.data || {};
  appStore.actions.setSession({
    accessToken: data.api_token || null,
    refreshToken: data.refresh_token || null,
    user: normalizeProfile(data),
  });

  debugTrace({
    scope: 'auth',
    action: 'login:success',
    caller: 'login',
    callee: 'appStore.actions.setSession',
    next: 'return normalized profile to page',
    detail: {
      hasAccessToken: Boolean(data.api_token),
      hasRefreshToken: Boolean(data.refresh_token),
    },
  });

  return normalizeProfile(data);
}

/**
 * Registers a user and immediately logs them in when the backend supports it.
 */
export async function register(payload) {
  debugTrace({
    scope: 'auth',
    action: 'register:start',
    caller: 'ui/mobile/src/services/auth/auth-service.js#register',
    callee: '/auth/register',
    next: 'create backend account',
    detail: {
      email: payload.email || '',
      username: payload.username || '',
    },
  });

  const result = await baseRequest('/auth/register', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      username: payload.username,
      email: payload.email,
      password: payload.password,
      turnstile_token: payload.turnstileToken || '',
    }),
  });

  if (!result.ok || result.payload?.status === 'error') {
    const error = new Error(result.payload?.error?.message || result.payload?.message || 'Registration failed');
    error.status = result.status;
    debugError({
      scope: 'auth',
      action: 'register:error',
      caller: 'register',
      callee: '/auth/register',
      next: 'return registration error to page',
      error,
    });
    throw error;
  }

  debugTrace({
    scope: 'auth',
    action: 'register:success',
    caller: 'register',
    callee: '/auth/register',
    next: 'return registration payload to page',
  });

  return result.payload?.data || {};
}

/**
 * Logs the current user out and clears local session state.
 */
export async function logout() {
  debugTrace({
    scope: 'auth',
    action: 'logout:start',
    caller: 'ui/mobile/src/services/auth/auth-service.js#logout',
    callee: '/auth/logout',
    next: 'clear local mobile session',
  });

  try {
    await baseRequest('/auth/logout', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
      },
    });
  } finally {
    appStore.actions.clearSession();
    debugTrace({
      scope: 'auth',
      action: 'logout:cleared',
      caller: 'logout',
      callee: 'appStore.actions.clearSession',
      next: 'return logged-out state to page',
    });
  }
}

/**
 * Attempts to recover an expired access token with the persisted refresh token.
 */
export async function tryRecoverSession() {
  if (refreshPromise) {
    debugTrace({
      scope: 'auth',
      action: 'tryRecoverSession:reusePromise',
      caller: 'ui/mobile/src/services/auth/auth-service.js#tryRecoverSession',
      callee: 'existing refreshPromise',
      next: 'await active refresh attempt',
    });
    return refreshPromise;
  }

  const refreshToken = appStore.getState().auth.refreshToken;
  if (!refreshToken) {
    debugTrace({
      scope: 'auth',
      action: 'tryRecoverSession:noRefreshToken',
      caller: 'tryRecoverSession',
      callee: 'appStore.actions.clearSession',
      next: 'abort auth recovery',
    });
    appStore.actions.clearSession();
    return false;
  }

  refreshPromise = (async () => {
    debugTrace({
      scope: 'auth',
      action: 'tryRecoverSession:start',
      caller: 'tryRecoverSession',
      callee: '/auth/refresh',
      next: 'request refreshed auth tokens',
    });

    const result = await baseRequest('/auth/refresh', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ refresh_token: refreshToken }),
    });

    if (!result.ok || result.payload?.status === 'error') {
      debugTrace({
        scope: 'auth',
        action: 'tryRecoverSession:failed',
        caller: 'tryRecoverSession',
        callee: 'appStore.actions.clearSession',
        next: 'drop invalid auth state',
        detail: {
          status: result.status,
        },
      });
      appStore.actions.clearSession();
      return false;
    }

    const data = result.payload?.data || {};
    appStore.actions.setSession({
      accessToken: data.api_token || appStore.getState().auth.accessToken,
      refreshToken: data.refresh_token || refreshToken,
      user: normalizeProfile({
        ...appStore.getState().auth.user,
        ...data,
      }),
    });

    debugTrace({
      scope: 'auth',
      action: 'tryRecoverSession:success',
      caller: 'tryRecoverSession',
      callee: 'appStore.actions.setSession',
      next: 'return recovered auth state',
      detail: {
        hasApiToken: Boolean(data.api_token),
        hasRefreshToken: Boolean(data.refresh_token || refreshToken),
      },
    });
    return true;
  })();

  try {
    return await refreshPromise;
  } finally {
    debugTrace({
      scope: 'auth',
      action: 'tryRecoverSession:finally',
      caller: 'tryRecoverSession',
      callee: 'refreshPromise reset',
      next: 'allow future recovery attempts',
    });
    refreshPromise = null;
  }
}
