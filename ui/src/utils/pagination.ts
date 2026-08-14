import { PaginationMeta } from '../types/api';

/**
 * Helper to determine if there is a next page.
 * If backend returns total_pages or total, computes explicitly.
 * Fallback heuristic: If itemsCount === perPage, assume next page exists.
 * Note: Page pagination is separate from cursor-based navigation.
 */
export function calculateHasNext(
  itemsCount: number,
  perPage: number,
  page: number = 1,
  totalPages?: number,
  total?: number
): boolean {
  if (typeof totalPages === 'number' && totalPages > 0) {
    return page < totalPages;
  }
  if (typeof total === 'number' && total >= 0) {
    return page * perPage < total;
  }
  // Heuristic when total/total_pages is missing from response
  return itemsCount === perPage;
}

/**
 * Helper to determine if there is a previous page.
 */
export function calculateHasPrev(page: number = 1): boolean {
  return page > 1;
}

/**
 * Formats a standardized PaginationMeta object.
 */
export function formatPaginationMeta(
  page: number,
  perPage: number,
  total?: number,
  nextCursor?: string | null,
  extra?: Record<string, unknown>
): PaginationMeta {
  const safePage = Math.max(1, page);
  const safePerPage = Math.max(1, perPage);
  const totalPages = typeof total === 'number' ? Math.max(1, Math.ceil(total / safePerPage)) : undefined;

  return {
    page: safePage,
    per_page: safePerPage,
    total,
    total_pages: totalPages,
    next_cursor: nextCursor ?? null,
    ...extra,
  };
}
