import { escapeHtml, formatChapterLabel, formatCoins, formatDate } from '../utils/format.js';

/**
 * Renders a reusable chapter row with lock state and metadata.
 */
export function renderChapterListItem({ type, slug, chapter }) {
  const href = `/reader/${type}/${slug}/${chapter.number}/`;
  const after = chapter.isLocked
    ? `<span class="badge color-orange">${escapeHtml(formatCoins(chapter.price))}</span>`
    : '<span class="badge color-green">Open</span>';

  return `
    <li>
      <a href="${href}" class="item-link item-content">
        <div class="item-inner">
          <div class="item-title-row">
            <div class="item-title">${escapeHtml(chapter.title || formatChapterLabel(chapter.number))}</div>
            <div class="item-after">${after}</div>
          </div>
          <div class="item-subtitle">${escapeHtml(formatChapterLabel(chapter.number))}</div>
          <div class="item-text">${escapeHtml(formatDate(chapter.createdAt) || (chapter.isPurchased ? 'Purchased' : 'Available'))}</div>
        </div>
      </a>
    </li>
  `;
}
