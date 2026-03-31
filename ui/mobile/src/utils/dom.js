/**
 * Resolves Framework7 component `$el` references into a real HTMLElement.
 */
export function resolveComponentElement($el) {
  if (!$el) {
    return null;
  }

  if (typeof HTMLElement !== 'undefined' && $el instanceof HTMLElement) {
    return $el;
  }

  if (typeof $el[0] !== 'undefined') {
    return $el[0] || null;
  }

  if ($el.el) {
    return $el.el;
  }

  return null;
}
