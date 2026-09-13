type AuthStateListener = (isAuthenticated: boolean) => void;
type UnauthorizedHandler = () => void;

let inMemoryCsrfToken: string | null = null;
const authStateListeners: Set<AuthStateListener> = new Set();
let onUnauthorizedCallback: UnauthorizedHandler | null = null;

let refreshPromise: Promise<string | null> | null = null;

/**
 * Gets the current memory-cached CSRF token.
 */
export function getCsrfToken(): string | null {
  return inMemoryCsrfToken;
}

/**
 * Updates the in-memory CSRF token from header or response payload.
 */
export function setCsrfToken(token: string | null): void {
  inMemoryCsrfToken = token && token.trim() !== '' ? token.trim() : null;
}

/**
 * Clears the stored CSRF token on logout.
 */
export function clearCsrfToken(): void {
  inMemoryCsrfToken = null;
}

/**
 * Registers a global callback for when an unrecoverable 401 Unauthorized occurs.
 */
export function setOnUnauthorized(handler: UnauthorizedHandler | null): void {
  onUnauthorizedCallback = handler;
}

export function notifyUnauthorized(): void {
  if (onUnauthorizedCallback) {
    onUnauthorizedCallback();
  }
}

/**
 * Subscribes to auth state changes.
 */
export function subscribeAuthState(listener: AuthStateListener): () => void {
  authStateListeners.add(listener);
  return () => authStateListeners.delete(listener);
}

export function notifyAuthState(isAuthenticated: boolean): void {
  authStateListeners.forEach((listener) => {
    try {
      listener(isAuthenticated);
    } catch (err) {
      console.error('[AuthListener Error]', err);
    }
  });
}

/**
 * Concurrency Lock for Auth Refresh & CSRF recovery.
 * Prevents multiple simultaneous refresh requests when multiple endpoints return 419/401.
 */
export async function withRefreshLock(
  refreshFn: () => Promise<string | null>
): Promise<string | null> {
  if (refreshPromise) {
    return refreshPromise;
  }

  try {
    refreshPromise = refreshFn();
    const token = await refreshPromise;
    if (token) {
      setCsrfToken(token);
    }
    return token;
  } finally {
    refreshPromise = null;
  }
}
