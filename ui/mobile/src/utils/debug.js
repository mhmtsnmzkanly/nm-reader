/**
 * Returns true when mobile debug tracing is enabled.
 *
 * Debug is enabled by default in this rebuild because the current task is to
 * make route and runtime transitions observable on the web `/mobile` route.
 */
export function isDebugEnabled() {
  if (typeof window === 'undefined') {
    return false;
  }

  if (typeof window.NMR_MOBILE_DEBUG === 'boolean') {
    return window.NMR_MOBILE_DEBUG;
  }

  return true;
}

/**
 * Emits a structured mobile trace entry.
 */
export function debugTrace({
  scope = 'mobile',
  action = 'unknown',
  caller = 'unknown',
  callee = 'unknown',
  next = 'unknown',
  detail = {},
  level = 'info',
} = {}) {
  if (!isDebugEnabled() || typeof console === 'undefined') {
    return;
  }

  const payload = {
    timestamp: new Date().toISOString(),
    scope,
    action,
    caller,
    callee,
    next,
    detail,
  };

  const prefix = `[NMR Mobile][${scope}] ${action}`;
  const logger = typeof console[level] === 'function' ? console[level] : console.log;
  logger(prefix, payload);
}

/**
 * Emits a standard error trace while preserving caller/callee context.
 */
export function debugError({
  scope = 'mobile',
  action = 'error',
  caller = 'unknown',
  callee = 'unknown',
  next = 'inspect error',
  error,
  detail = {},
} = {}) {
  debugTrace({
    scope,
    action,
    caller,
    callee,
    next,
    level: 'error',
    detail: {
      ...detail,
      errorMessage: error?.message || String(error || 'Unknown error'),
      errorStatus: error?.status || null,
    },
  });
}
