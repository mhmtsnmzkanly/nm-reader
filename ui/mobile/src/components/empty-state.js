import { escapeHtml } from '../utils/format.js';

/**
 * Renders an empty state with an optional action link.
 */
export function renderEmptyState({ title, message, actionHref = '', actionLabel = '' }) {
  return `
    <div class="block block-strong mobile-feedback-block">
      <div class="mobile-feedback-title">${escapeHtml(title || 'No data')}</div>
      <p>${escapeHtml(message || 'Nothing to show yet.')}</p>
      ${actionHref && actionLabel ? `<a href="${actionHref}" class="button button-fill">${escapeHtml(actionLabel)}</a>` : ''}
    </div>
  `;
}
