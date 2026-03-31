import { escapeHtml } from '../utils/format.js';

/**
 * Renders the standard page header used by content and account screens.
 */
export function renderAppHeader($h, { title, subtitle = '', back = false, large = false } = {}) {
  return $h`
    <div class="navbar ${large ? 'navbar-large' : ''}">
      <div class="navbar-bg"></div>
      <div class="navbar-inner sliding">
        <div class="left">
          ${back ? $h`<a class="link back"><i class="icon f7-icons">chevron_left</i></a>` : ''}
        </div>
        <div class="title">${escapeHtml(title || 'NMR Mobile')}</div>
        <div class="right"></div>
        ${large ? $h`
          <div class="title-large">
            <div class="title-large-text">${escapeHtml(title || 'NMR Mobile')}</div>
            ${subtitle ? $h`<div class="mobile-page-subtitle">${escapeHtml(subtitle)}</div>` : ''}
          </div>
        ` : ''}
      </div>
    </div>
  `;
}
