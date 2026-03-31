const FALLBACK_COVER = '/assets/img/covers/placeholder.svg';

/**
 * Normalizes media paths coming from the backend so the mobile app can render them safely.
 */
export function resolveMediaUrl(value) {
  if (typeof value !== 'string' || value.trim() === '') {
    return null;
  }

  if (/^https?:\/\//i.test(value)) {
    return value;
  }

  return value.startsWith('/') ? value : `/${value}`;
}

/**
 * Resolves the best available cover image for a content item.
 */
export function resolveCoverUrl(value) {
  return resolveMediaUrl(value) || FALLBACK_COVER;
}
