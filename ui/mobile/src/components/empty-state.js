import { escapeHtml } from '../utils/format.js';

/**
 * Renders an empty state with an optional action link.
 */
export function renderEmptyState($h, { title, message, actionHref = '', actionLabel = '' }) {
  return $h`
    <div class="block block-strong mobile-feedback-block">
      <div class="mobile-feedback-title">${escapeHtml(title || 'No data')}</div>
      <p>${escapeHtml(message || 'Nothing to show yet.')}</p>
      ${actionHref && actionLabel ? $h`<a href="${actionHref}" class="button button-fill">${escapeHtml(actionLabel)}</a>` : ''}
    </div>
  `;
}
