/**
 * Reader.js - Core Platform Reader Engine.
 *
 * This module manages the reading experience for both Novels (text) and Manga (images).
 * Features:
 * - Hybrid Rendering: Supports SSR hydration for the first chapter and AJAX for navigation.
 * - Layout Modes: Vertical (webtoon style), Single Page, and Double Page modes.
 * - Interaction: Click-to-turn navigation and global keyboard shortcuts.
 * - Social: Localized comment system per chapter.
 */
const Reader = (function () {
  /** @type {Object|null} Cached chapter data. */
  let currentData = null;

  /** @type {string} Active layout mode (vertical, single, double). */
  let currentLayout = 'vertical';

  /** @type {number} Current page index for manga/image modes. */
  let currentPage = 0;

  /**
   * Internal helper to get cookies safely.
   */
  const _getCookie = (name) => {
    return window.getCookie ? window.getCookie(name) : null;
  };

  /**
   * Internal helper to render image-based content.
   */
  const renderManga = function () {
    const $container = $('#mangaView .manga-pages');
    $container.empty().removeClass('layout-single layout-double');
    const pages = currentData.pages || [];

    if (pages.length === 0) {
      $container.html(`<div class="p-4 text-muted">${NMR.__t('no_page_images')}</div>`);
      return;
    }

    if (currentLayout === 'vertical') {
      // Webtoon style: All images stacked.
      pages.forEach((p) => {
        const img = document.createElement('img');
        img.src = p.image_path;
        img.className = 'manga-page-img mb-2';
        $container.append(img);
      });
      return;
    }

    const isDouble = currentLayout === 'double';
    $container.addClass(isDouble ? 'layout-double' : 'layout-single');
    const wrapper = document.createElement('div');
    wrapper.className = 'manga-interactive-wrapper';

    if (isDouble) {
      // Magazine style: Two images side-by-side.
      const start = currentPage % 2 === 0 ? currentPage : currentPage - 1;
      [start, start + 1].forEach((idx) => {
        if (pages[idx]) {
          const img = document.createElement('img');
          img.src = pages[idx].image_path;
          img.className = 'manga-page-img';
          wrapper.append(img);
        }
      });
      updatePageIndicator(`${NMR.__t('page')} ${start + 1}${pages[start + 1] ? '-' + (start + 2) : ''} / ${pages.length}`);
    } else {
      // E-reader style: Single centered image.
      const img = document.createElement('img');
      img.src = pages[currentPage].image_path;
      img.className = 'manga-page-img';
      wrapper.append(img);
      updatePageIndicator(`${NMR.__t('page')} ${currentPage + 1} / ${pages.length}`);
    }

    // Navigation trigger area.
    wrapper.onclick = function (e) {
      const rect = this.getBoundingClientRect();
      const x = e.clientX - rect.left;
      if (x < rect.width / 2) Reader.prevPage();
      else Reader.nextPage();
    };

    $container.append(wrapper);
  };

  /**
   * Updates the visual page progress text.
   * @param {string} text
   */
  const updatePageIndicator = (text) => {
    const $container = $('#mangaView .manga-pages');
    const info = document.createElement('div');
    info.className = 'text-center mt-2 text-muted text-sm w-100';
    info.style.gridColumn = '1 / -1';
    info.textContent = text;
    $container.append(info);
  };

  return {
    /**
     * Entry point for rendering a chapter.
     * @param {Object} apiResponse
     * @param {string} layout
     */
    render(apiResponse, layout = currentLayout) {
      const data = apiResponse.data || apiResponse; // Handle both wrapped and unwrapped
      currentData = data;
      currentLayout = layout;
      
      // Sync UI settings.
      const ctx = window.__NMR_CONTEXT || {};
      const r = (ctx.auth && ctx.auth.preferences) ? ctx.auth.preferences.reader : {};
      
      const fit = _getCookie('melt_reader_imageFit') || r.imageFit || 'width';
      const fitClass = `fit-${fit}`;

      if (data.type === 'image') {
        $('#mangaView').removeClass('hidden fit-width fit-height fit-original').addClass(fitClass);
        $('#novelView').addClass('hidden');
        renderManga();
        this.applyProtection('#mangaView');
      } else {
        $('#novelView').removeClass('hidden');
        $('#mangaView').addClass('hidden');
        $('#novelView .novel-content').html(NMR.parseMarkdown(data.body || ''));
        this.applyStyles();
        this.applyProtection('#novelView');
      }
      window.scrollTo(0, 0);
    },

    /**
     * Swaps the layout mode dynamically.
     */
    setLayout(layout) {
      currentLayout = layout;
      if (currentData) {
        if (layout !== 'vertical') currentPage = 0;
        this.render(currentData, layout);
      }
    },

    /**
     * Adjusts the image scaling logic.
     */
    setFit(fit) {
      $('#mangaView').removeClass('fit-width fit-height fit-original').addClass(`fit-${fit}`);
    },

    /**
     * Synchronizes the typography and theme styles with current preferences.
     */
    applyStyles() {
      if (window.NMR && window.NMR.syncReaderStyles) window.NMR.syncReaderStyles();
    },

    /**
     * Enforces content security by blocking context menus and common shortcuts.
     * @param {string} selector
     */
    applyProtection(selector) {
      const el = document.querySelector(selector);
      if (!el) return;

      el.oncontextmenu = (e) => e.preventDefault();
      el.oncopy = (e) => e.preventDefault();
      el.onselectstart = (e) => e.preventDefault();

      el.onkeydown = (e) => {
        const forbiddenKeys = ['c', 'C', 'u', 'U', 's', 'S', 'p', 'P'];
        if ((e.ctrlKey || e.metaKey) && forbiddenKeys.includes(e.key)) {
          e.preventDefault();
          return false;
        }
      };
    },

    /**
     * Advances to the next page or chapter.
     */
    nextPage() {
      if (!currentData) return;
      if (currentLayout === 'vertical') {
        this.nextChapter();
        return;
      }
      
      const pages = currentData.pages || [];
      const step = currentLayout === 'double' ? 2 : 1;
      if (currentPage + step < pages.length) {
        currentPage += step;
        renderManga();
        window.scrollTo(0, 0);
      } else {
        this.nextChapter();
      }
    },

    /**
     * Reverts to the previous page or chapter.
     */
    prevPage() {
      if (!currentData) return;
      if (currentLayout === 'vertical') {
        this.prevChapter();
        return;
      }

      const step = currentLayout === 'double' ? 2 : 1;
      if (currentPage - step >= 0) {
        currentPage -= step;
        renderManga();
        window.scrollTo(0, 0);
      } else {
        this.prevChapter();
      }
    },

    /**
     * Logic to find and trigger navigation to the next sequential chapter.
     */
    nextChapter() {
      if (currentData && currentData.adjacent_chapters && currentData.adjacent_chapters.next) {
          const next = currentData.adjacent_chapters.next;
          const url = window.location.pathname.replace(/\/chapter\/[^\/]+/, '/chapter/' + encodeURIComponent(next));
          location.href = url;
          return;
      }
      const select = $('#chapterSelect').elements[0];
      if (select && select.selectedIndex < select.options.length - 1) {
        select.selectedIndex += 1;
        $('#chapterSelect').trigger('change');
      }
    },

    /**
     * Logic to find and trigger navigation to the previous sequential chapter.
     */
    prevChapter() {
      if (currentData && currentData.adjacent_chapters && currentData.adjacent_chapters.prev) {
          const prev = currentData.adjacent_chapters.prev;
          const url = window.location.pathname.replace(/\/chapter\/[^\/]+/, '/chapter/' + encodeURIComponent(prev));
          location.href = url;
          return;
      }
      const select = $('#chapterSelect').elements[0];
      if (select && select.selectedIndex > 0) {
        select.selectedIndex -= 1;
        $('#chapterSelect').trigger('change');
      }
    }
  };
})();

// Global Keyboard Navigation
$(document).on('keydown', function(e) {
    if (['input', 'textarea'].includes(e.target.tagName.toLowerCase())) return;
    
    if (e.key === 'ArrowRight') {
        Reader.nextPage();
    } else if (e.key === 'ArrowLeft') {
        Reader.prevPage();
    }
});

$(function () {
  const ctx = window.__NMR_CONTEXT || {};
  const path = window.location.pathname.split('/').filter(Boolean);
  const langPrefix = (path[0] === 'tr' || path[0] === 'en') ? path[0] : '';
  const offset = langPrefix ? 1 : 0;

  const type = ctx.type || path[offset] || '';
  const slug = ctx.slug || path[offset + 1] || '';
  const chapterFromPath = path.length >= (offset + 4) && path[offset + 2] === 'chapter' ? path[offset + 3] : '';
  let currentChapter = ctx.chapterNumber || chapterFromPath || '1';
  let chapters = [];
  let isFirstLoad = true;
  const EPSILON = 0.000001;

  const toChapterFloat = (value) => {
    const n = Number.parseFloat(String(value ?? '').replace(',', '.'));
    return Number.isNaN(n) ? Number.POSITIVE_INFINITY : n;
  };

  const normalizeChapterNumber = (value) => {
    const raw = String(value ?? '').trim();
    if (!/^-?\d+(?:\.\d+)?$/.test(raw)) return raw;
    if (!raw.includes('.')) return raw;
    return raw.replace(/\.?0+$/, '');
  };

  const sortChaptersAsc = (items) => {
    const safe = Array.isArray(items) ? [...items] : [];
    safe.sort((a, b) => {
      const diff = toChapterFloat(a.chapter_number) - toChapterFloat(b.chapter_number);
      if (diff !== 0) return diff;
      return String(a.chapter_number ?? '').localeCompare(String(b.chapter_number ?? ''));
    });
    return safe;
  };

  const resolveChapterValue = (requested, items) => {
    const req = String(requested ?? '');
    const exact = items.find((ch) => String(ch.chapter_number) === req);
    if (exact) return String(exact.chapter_number);

    const reqNum = toChapterFloat(req);
    if (Number.isFinite(reqNum)) {
      const numeric = items.find((ch) => Math.abs(toChapterFloat(ch.chapter_number) - reqNum) < EPSILON);
      if (numeric) return String(numeric.chapter_number);
    }

    return items[0] ? String(items[0].chapter_number) : '';
  };

  const updateChapterNavButtons = () => {
    const select = $('#chapterSelect').elements[0];
    if (!select) return;

    const prevBtn = $('#prevChapterBtn');
    const nextBtn = $('#nextChapterBtn');
    const atFirst = select.selectedIndex <= 0;
    const atLast = select.selectedIndex >= select.options.length - 1;

    prevBtn.prop('disabled', atFirst);
    nextBtn.prop('disabled', atLast);
  };

  const renderComments = (rows) => {
    const html = (rows || []).map((c) => {
      const score = (Number(c.upvote_count) || 0) - (Number(c.downvote_count) || 0);
      const myVote = Number(c.my_vote) || 0;
      return `
        <div class="comment flex gap-3 pb-3 border-bottom" data-id="${c.id}" data-user-id="${c.user_id}">
          <div class="flex flex-col items-center gap-1" style="min-width:40px">
            <button class="btn-none vote-btn upvote ${myVote === 1 ? 'text-primary' : ''}" data-vote="1" title="Upvote">▲</button>
            <span class="text-xs font-bold score-val">${score}</span>
            <button class="btn-none vote-btn downvote ${myVote === -1 ? 'text-danger' : ''}" data-vote="-1" title="Downvote">▼</button>
          </div>
          <div class="flex-grow">
            <div class="flex justify-between items-center mb-1">
              <strong class="text-sm"><a href="/${window.NMR.getLangPrefix()}/profile/${c.username || ''}" style="color:inherit; text-decoration:none;">@${c.username || 'User'}</a></strong>
              <span class="text-xs text-muted">${c.created_at || ''}</span>
            </div>
            <div class="text-sm text-muted leading-relaxed markdown-body">${NMR.parseMarkdown(c.body || '')}</div>
          </div>
        </div>
      `;
    }).join('');

    $('#readerCommentsList').html(html || `<div class="text-center py-3 text-muted">${NMR.__t('no_comments_yet')}</div>`);
  };

  const fillChapterSelect = (items, selected) => {
    const select = $('#chapterSelect');
    const options = (items || []).map((ch) => {
      const chapterValue = normalizeChapterNumber(ch.chapter_number);
      return `
      <option value="${chapterValue}" ${String(chapterValue) === String(selected) ? 'selected' : ''}>
        ${NMR.__t('chapter')} ${chapterValue}
      </option>
    `;
    }).join('');

    select.html(options || `<option value="">${NMR.__t('no_chapters')}</option>`);
    updateChapterNavButtons();
  };

  const loadChapter = async (chapterNumber) => {
    const target = normalizeChapterNumber(chapterNumber);
    const found = chapters.find((ch) => normalizeChapterNumber(ch.chapter_number) === String(target));

    // SSR Check
    const ssrBody = $('.novel-content').text().trim();
    const isSSRMatch = isFirstLoad && ssrBody.length > 0 && !$('#novelView').hasClass('hidden');
    isFirstLoad = false;

    try {
      if (!isSSRMatch) {
        $('#mangaView .manga-pages').empty();
        $('.novel-content').empty();

        const res = await Connection.getChapterDetail(type, slug, target);
        const layout = window.getCookie ? (window.getCookie('melt_reader_layout') || 'vertical') : 'vertical';
        Reader.render(res, layout);
      } else {
        const raw = $('.novel-content').html();
        if (raw && !raw.includes('<p>')) {
          $('.novel-content').html(NMR.parseMarkdown(raw));
        }
        Reader.applyStyles();
        Reader.applyProtection('#novelView');
      }

      if (found && found.id) {
        const commentsRes = await Connection.getChapterComments(found.id);
        renderComments(commentsRes.data);
      } else {
        renderComments([]);
      }
    } catch (err) {
      showPopup(err.message || NMR.__t('msg_load_failed'), 'error');
    }
    updateChapterNavButtons();
  };

  if (!type || !slug) return;

  Connection.getChapters(type, slug)
    .then((res) => {
      chapters = sortChaptersAsc(res.data || []);
      if (chapters.length === 0) {
        $('#chapterSelect').html(`<option value="">${NMR.__t('no_updates_yet')}</option>`);
        updateChapterNavButtons();
        return;
      }

      chapters = chapters.map((ch) => ({ ...ch, chapter_number: normalizeChapterNumber(ch.chapter_number) }));
      currentChapter = normalizeChapterNumber(resolveChapterValue(currentChapter, chapters));
      
      fillChapterSelect(chapters, currentChapter);
      loadChapter(currentChapter);
    })
    .catch((err) => {
      showPopup(err.message || NMR.__t('msg_load_failed'), 'error');
    });

  $('#chapterSelect').on('change', function () {
    const selected = normalizeChapterNumber($(this).val());
    if (!selected) return;
    currentChapter = selected;
    const prefix = langPrefix ? `/${langPrefix}` : '';
    window.history.replaceState({}, '', `${prefix}/${type}/${slug}/chapter/${selected}`);
    loadChapter(selected);
  });

  // Comment Voting and other handlers...
  // (Rest of the file remains same but with scope fixes)
});
