/** Display and navigation use the same finite, integer pagination values. */
export function paginationState(meta = {}) {
  const integer = (value, fallback, minimum) => {
    const number = Number(value);
    return Number.isSafeInteger(number) && number >= minimum ? number : fallback;
  };
  // Preserve a server-reported page beyond the last page (e.g. after deletion).
  // Relabelling it would claim that the current rows came from another page.
  const page = integer(meta?.page, 1, 1);
  const total_pages = integer(meta?.total_pages, 1, 1);
  return {
    page,
    total_pages,
    total: integer(meta?.total, 0, 0),
    has_previous: page > 1,
    has_next: page < total_pages,
  };
}

export function adjacentPage(meta, direction) {
  const state = paginationState(meta);
  if (direction === -1 && state.has_previous) return state.page - 1;
  if (direction === 1 && state.has_next) return state.page + 1;
  return null;
}
