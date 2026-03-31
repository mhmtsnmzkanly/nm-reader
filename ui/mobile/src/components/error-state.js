import { escapeHtml } from '../utils/format.js';

/**
 * Renders a reusable blocking or inline error state.
 */
export function renderErrorState($h, { title, message, retryLabel = 'Retry' }) {
  return $h`
    <div class="block block-strong mobile-feedback-block mobile-error-block">
      <div class="mobile-feedback-title">${escapeHtml(title || 'Something went wrong')}</div>
      <p>${escapeHtml(message || 'Please try again.')}</p>
      <button class="button button-fill color-red" data-action="retry-page">${escapeHtml(retryLabel)}</button>
    </div>
  `;
}
