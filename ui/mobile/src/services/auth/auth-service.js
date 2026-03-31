import { baseRequest } from '../http/base-request.js';
import { appStore } from '../../store/app-store.js';
import { normalizeProfile } from '../../utils/normalize.js';

let refreshPromise = null;

/**
 * Executes login with bearer-token-first mobile semantics.
 */
export async function login(credentials) {
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
    throw error;
  }

  const data = result.payload?.data || {};
  appStore.actions.setSession({
    accessToken: data.api_token || null,
    refreshToken: data.refresh_token || null,
    user: normalizeProfile(data),
  });

  return normalizeProfile(data);
}

/**
 * Registers a user and immediately logs them in when the backend supports it.
 */
export async function register(payload) {
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
    throw error;
  }

  return result.payload?.data || {};
}

/**
 * Logs the current user out and clears local session state.
 */
export async function logout() {
  try {
    await baseRequest('/auth/logout', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
      },
    });
  } finally {
    appStore.actions.clearSession();
  }
}

/**
 * Attempts to recover an expired access token with the persisted refresh token.
 */
export async function tryRecoverSession() {
  if (refreshPromise) {
    return refreshPromise;
  }

  const refreshToken = appStore.getState().auth.refreshToken;
  if (!refreshToken) {
    appStore.actions.clearSession();
    return false;
  }

  refreshPromise = (async () => {
    const result = await baseRequest('/auth/refresh', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ refresh_token: refreshToken }),
    });

    if (!result.ok || result.payload?.status === 'error') {
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
    return true;
  })();

  try {
    return await refreshPromise;
  } finally {
    refreshPromise = null;
  }
}
