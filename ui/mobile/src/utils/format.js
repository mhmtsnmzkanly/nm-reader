/**
 * Escapes text before injecting it into HTML strings.
 */
export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Formats a date-like input for short mobile displays.
 */
export function formatDate(value) {
  if (!value) {
    return '';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return new Intl.DateTimeFormat('tr-TR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date);
}

/**
 * Formats wallet amounts with a stable coin suffix.
 */
export function formatCoins(value) {
  return `${Number(value || 0).toLocaleString('tr-TR')} coin`;
}

/**
 * Formats chapter labels consistently across list and reader pages.
 */
export function formatChapterLabel(value) {
  if (value === null || value === undefined || value === '') {
    return 'Chapter';
  }

  return `Chapter ${value}`;
}
