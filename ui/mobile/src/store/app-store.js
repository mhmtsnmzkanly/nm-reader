import { readJson, removeValue, writeJson } from '../utils/storage.js';

const SESSION_KEY = 'session';
const PREFERENCES_KEY = 'preferences';
const READER_KEY = 'reader-preferences';

/**
 * Defines the minimal global state required by the rebuilt mobile app.
 */
const defaultState = {
  auth: {
    status: 'guest',
    accessToken: null,
    refreshToken: null,
    user: null,
  },
  wallet: {
    balance: 0,
    totalPurchased: 0,
    totalSpent: 0,
  },
  preferences: {
    language: 'tr',
    theme: 'system',
  },
  readerPreferences: {
    theme: 'dark',
    fontSize: 18,
    imageMode: 'vertical',
  },
};

let state = structuredClone(defaultState);
const listeners = new Set();

/**
 * Notifies all subscribers after a state mutation.
 */
function notify() {
  listeners.forEach((listener) => listener(state));
}

/**
 * Persists only the slices that must survive app restarts.
 */
function persist() {
  writeJson(SESSION_KEY, {
    accessToken: state.auth.accessToken,
    refreshToken: state.auth.refreshToken,
    user: state.auth.user,
  });
  writeJson(PREFERENCES_KEY, state.preferences);
  writeJson(READER_KEY, state.readerPreferences);
}

/**
 * Deep-merges a partial payload into the top-level store.
 */
function mergeState(patch) {
  state = {
    ...state,
    ...patch,
  };
  persist();
  notify();
}

/**
 * Hydrates the store from storage on startup.
 */
function hydrate() {
  const session = readJson(SESSION_KEY, null);
  const preferences = readJson(PREFERENCES_KEY, null);
  const readerPreferences = readJson(READER_KEY, null);

  state = {
    ...structuredClone(defaultState),
    auth: session?.accessToken ? {
      status: 'restoring',
      accessToken: session.accessToken || null,
      refreshToken: session.refreshToken || null,
      user: session.user || null,
    } : structuredClone(defaultState.auth),
    preferences: {
      ...structuredClone(defaultState.preferences),
      ...(preferences || {}),
    },
    readerPreferences: {
      ...structuredClone(defaultState.readerPreferences),
      ...(readerPreferences || {}),
    },
    wallet: structuredClone(defaultState.wallet),
  };
  notify();
}

/**
 * Returns a readonly snapshot of the current store state.
 */
function getState() {
  return state;
}

/**
 * Registers a listener and returns an unsubscribe function.
 */
function subscribe(listener) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

/**
 * Stores a fresh authenticated session and moves auth state to `authenticated`.
 */
function setSession({ accessToken = null, refreshToken = null, user = null } = {}) {
  mergeState({
    auth: {
      status: accessToken ? 'authenticated' : 'guest',
      accessToken,
      refreshToken,
      user,
    },
  });
}

/**
 * Marks auth as being restored so startup and guarded screens can show a stable loading state.
 */
function setAuthRestoring() {
  mergeState({
    auth: {
      ...state.auth,
      status: 'restoring',
    },
  });
}

/**
 * Clears all user-specific mobile state on logout or unrecoverable auth expiry.
 */
function clearSession() {
  state = {
    ...state,
    auth: structuredClone(defaultState.auth),
    wallet: structuredClone(defaultState.wallet),
  };
  removeValue(SESSION_KEY);
  persist();
  notify();
}

/**
 * Updates the current user profile without touching tokens.
 */
function setUser(user) {
  mergeState({
    auth: {
      ...state.auth,
      user,
      status: state.auth.accessToken ? 'authenticated' : state.auth.status,
    },
  });
}

/**
 * Replaces the wallet summary after refresh or purchase flows.
 */
function setWalletSummary(wallet) {
  mergeState({
    wallet: {
      ...structuredClone(defaultState.wallet),
      ...(wallet || {}),
    },
  });
}

/**
 * Stores app-wide visual or locale preferences.
 */
function setPreferences(preferences) {
  mergeState({
    preferences: {
      ...state.preferences,
      ...(preferences || {}),
    },
  });
}

/**
 * Stores reader-specific preferences outside the page instance.
 */
function setReaderPreferences(preferences) {
  mergeState({
    readerPreferences: {
      ...state.readerPreferences,
      ...(preferences || {}),
    },
  });
}

/**
 * Returns true when the mobile app has a usable access token.
 */
function isAuthenticated() {
  return Boolean(state.auth.accessToken);
}

export const appStore = {
  hydrate,
  getState,
  subscribe,
  isAuthenticated,
  actions: {
    setSession,
    setAuthRestoring,
    clearSession,
    setUser,
    setWalletSummary,
    setPreferences,
    setReaderPreferences,
  },
};
