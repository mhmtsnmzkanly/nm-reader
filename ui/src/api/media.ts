/**
 * Media URL resolution helper for NM-Reader Frontend.
 * Strictly adheres to docs/api/MEDIA_CONTRACT.md
 */

const FALLBACK_PLACEHOLDER = '/assets/img/covers/placeholder.svg';

export type MediaType = 'auto' | 'public' | 'chapter';

/**
 * Resolves a media identifier or token into the canonical delivery URL.
 *
 * Examples:
 *  - "cover.a1b2c3d4.webp" -> "/media/public/cover.a1b2c3d4.webp"
 *  - "t_eyJjaWQiOi..."    -> "/media/chapter/t_eyJjaWQiOi..."
 *  - "https://..."         -> "https://..."
 */
export function resolveMediaUrl(
  identifier: string | null | undefined,
  type: MediaType = 'auto'
): string {
  if (!identifier || identifier.trim() === '') {
    return FALLBACK_PLACEHOLDER;
  }

  const clean = identifier.trim();

  // If already absolute HTTP(S) URL or starts with data:, return as-is
  if (clean.startsWith('http://') || clean.startsWith('https://') || clean.startsWith('data:')) {
    return clean;
  }

  // If already prefixed with /media/ or /assets/, return as-is
  if (clean.startsWith('/media/') || clean.startsWith('/assets/')) {
    return clean;
  }

  // If explicit or auto-detected chapter signed token
  if (type === 'chapter' || clean.startsWith('t_')) {
    const token = clean.replace(/^\/+/, '');
    return `/media/chapter/${token}`;
  }

  // Public media default
  const filename = clean.replace(/^\/+/, '');
  return `/media/public/${filename}`;
}

/**
 * Checks if a media URL / identifier represents a protected chapter token.
 */
export function isProtectedMedia(identifier: string | null | undefined): boolean {
  if (!identifier) return false;
  return identifier.trim().startsWith('t_') || identifier.includes('/media/chapter/');
}
