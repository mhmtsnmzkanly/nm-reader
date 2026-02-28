/**
 * series_list.js - Controller for Discovery and Categorized Listings.
 *
 * This module handles:
 * - Dynamic Discovery: Resolves the 'list_type' (genre, tag, category) from context.
 * - API Routing: Calls the correct Connection method based on the listing criteria.
 * - Localized UI: Renders translated headers and content badges.
 * - Result Grid: Generates a responsive grid of content cards.
 */
$(function() {
  const ctx = window.__NMR_CONTEXT || {};
  const listType = ctx.list_type || 'category';
  const value = ctx.value || '';
  const langPrefix = window.NMR.getLangPrefix();

  /**
   * Renders the collection of series cards.
   * @param {Array} items
   */
  const renderList = (items) => {
    const html = (items || []).map((item) => {
      const typePath = String(item.type || 'novel').replace(/_/g, '-');
      return `
      <div class="card p-0 overflow-hidden hover-lift cursor-pointer content-card" onclick="location.href='/${langPrefix}/${typePath}/${item.slug}'">
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

    $('#listingGrid').addClass('content-grid').html(html || `<div class="col-span-full text-center py-5 text-muted">${NMR.__t('no_content_found')}</div>`);
  };

  // Main Execution: Resolve API call based on listing type.
  if (value) {
    let titleText = value.toUpperCase();
    let apiCall = null;

    if (listType === 'category') {
      const titleMap = {
        'light-novel': NMR.__t('light-novels'), 
        'web-novel': NMR.__t('web-novels'), 
        'novel': NMR.__t('novels'),
        'manga': NMR.__t('manga'), 
        'manhua': NMR.__t('manhua'), 
        'manhwa': NMR.__t('manhwa'), 
        'webtoon': NMR.__t('webtoons')
      };
      titleText = titleMap[value] || value.toUpperCase();
      apiCall = Connection.getByType(value);
    } else if (listType === 'genre') {
      titleText = `${NMR.__t('genre')}: ${value.charAt(0).toUpperCase() + value.slice(1)}`;
      apiCall = Connection.getByGenre(value);
    } else if (listType === 'tag') {
      titleText = `${NMR.__t('tag')}: ${value.charAt(0).toUpperCase() + value.slice(1)}`;
      apiCall = Connection.getByTag(value);
    }

    $('#listingTitle').text(titleText);

    if (apiCall) {
      apiCall.then((res) => {
        renderList(res.data);
        $('#listingLoading').hide();
        $('#listingApp').removeClass('hidden').fadeIn();
      }).catch((err) => {
        $('#listingLoading').html(`<div class="text-danger">${err.message || NMR.__t('msg_load_failed')}</div>`);
      });
    }
  }
});
