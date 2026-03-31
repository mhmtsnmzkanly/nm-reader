import { escapeHtml, formatChapterLabel } from '../utils/format.js';

/**
 * Renders a reusable content card for home and listing screens.
 */
export function renderContentCard(item) {
  return `
    <a class="card mobile-content-card" href="/content/${item.type}/${item.slug}/">
      <div class="card-content card-content-padding">
        <div class="mobile-content-card__media">
          <img src="${item.cover}" alt="${escapeHtml(item.title)}" />
        </div>
        <div class="mobile-content-card__body">
          <div class="mobile-content-card__title">${escapeHtml(item.title)}</div>
          <div class="mobile-content-card__meta">${escapeHtml(item.type || 'content')}</div>
          <div class="mobile-content-card__description">${escapeHtml(item.description || 'No description available yet.')}</div>
          <div class="mobile-content-card__footer">
            <span>${formatChapterLabel(item.chapterCount || '')}</span>
            <span>${Number(item.rating || 0).toFixed(1)}</span>
          </div>
        </div>
      </div>
    </a>
  `;
}
