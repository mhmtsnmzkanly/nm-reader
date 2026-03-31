const STORAGE_PREFIX = 'nmr_mobile_v2';

/**
 * Builds a namespaced storage key so mobile values do not collide with other app data.
 */
function buildKey(key) {
  return `${STORAGE_PREFIX}:${key}`;
}

/**
 * Safely reads a JSON value from local storage.
 */
export function readJson(key, fallback = null) {
  if (typeof localStorage === 'undefined') {
    return fallback;
  }

  try {
    const rawValue = localStorage.getItem(buildKey(key));
    return rawValue ? JSON.parse(rawValue) : fallback;
  } catch (error) {
    return fallback;
  }
}

/**
 * Safely writes a JSON value to local storage.
 */
export function writeJson(key, value) {
  if (typeof localStorage === 'undefined') {
    return;
  }

  try {
    localStorage.setItem(buildKey(key), JSON.stringify(value));
  } catch (error) {
    // Storage failures are non-fatal on mobile and should not break navigation.
  }
}

/**
 * Removes a namespaced key from local storage.
 */
export function removeValue(key) {
  if (typeof localStorage === 'undefined') {
    return;
  }

  try {
    localStorage.removeItem(buildKey(key));
  } catch (error) {
    // Removal failures are non-fatal and can be ignored safely.
  }
}
