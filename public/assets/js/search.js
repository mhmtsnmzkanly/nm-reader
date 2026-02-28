/**
 * search.js - Controller for Platform-wide Content Search.
 *
 * This module executes AJAX-based full-text searches across the database.
 * It handles:
 * - Dynamic Result Rendering: Displays matched series in a responsive grid.
 * - Routing: Directs users to the specific content page based on its type.
 * - UX: Manages loading states and "no results found" messaging.
 */
$(function() {
  const ctx = window.__NMR_CONTEXT || {};
  const query = ctx.q || '';

  /**
   * Renders the search result cards.
   * @param {Array} items List of series matching the query.
   */
  const renderResults = (items) => {
    const html = (items || []).map((item) => {
      const typePath = String(item.type || 'novel').replace(/_/g, '-');
      return `
      <div class="card p-0 overflow-hidden hover-lift cursor-pointer content-card" onclick="location.href='/${typePath}/${item.slug}'">
        <div class="position-relative">
          <img src="${item.cover_image || ''}" onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';" class="w-100" alt="${item.title}">
          <span class="badge position-absolute top-0 right-0 m-2 text-xs bg-primary">${String(item.type || '').toUpperCase()}</span>
        </div>
        <div class="p-3">
          <h4 class="mb-1 truncate" title="${item.title}">${item.title}</h4>
          <div class="flex justify-between items-center text-xs text-muted">
            <span>⭐ ${item.rating_avg ?? '-'}</span>
            <span>${item.chapter_count ?? 0} ${NMR.__t('chapters')}</span>
          </div>
        </div>
      </div>
    `;
    }).join('');

    $('#searchResultsGrid').addClass('content-grid').html(html || `<div class="col-span-full text-center py-5 text-muted">${NMR.__t('no_content_found')}</div>`);
  };

  // Main Execution: Trigger search if query exists in context.
  if (query) {
    $('#searchTitle').text(NMR.__t('results_for').replace(':query', query));
    Connection.search(query)
      .then((res) => {
        renderResults(res.data);
        $('#searchLoading').hide();
        $('#searchApp').removeClass('hidden').fadeIn();
      })
      .catch((err) => {
        $('#searchLoading').html(`<div class="text-danger">${err.message || NMR.__t('search_failed')}</div>`);
      });
  } else {
    $('#searchLoading').html(`<div class="text-muted text-center py-5">${NMR.__t('please_enter_search_term')}</div>`);
  }
});
