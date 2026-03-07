/**
 * Reader.js - Core Platform Reader Engine.
 *
 * This module manages the reading experience for both Novels (text) and Manga (images).
 */
const Reader = (function () {
  let currentData = null;
  let currentLayout = 'vertical';
  let currentPage = 0;

  const _getCookie = (name) => window.getCookie ? window.getCookie(name) : null;

  const renderManga = function () {
    const $container = $('#mangaView .manga-pages');
    $container.empty().removeClass('layout-single layout-double');
    const pages = currentData.pages || [];

    if (pages.length === 0) {
      $container.html(`<div class="p-4 text-muted">${NMR.__t('no_page_images')}</div>`);
      return;
    }

    if (currentLayout === 'vertical') {
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
      const img = document.createElement('img');
      img.src = pages[currentPage].image_path;
      img.className = 'manga-page-img';
      wrapper.append(img);
      updatePageIndicator(`${NMR.__t('page')} ${currentPage + 1} / ${pages.length}`);
    }

    wrapper.onclick = function (e) {
      const rect = this.getBoundingClientRect();
      const x = e.clientX - rect.left;
      if (x < rect.width / 2) Reader.prevPage();
      else Reader.nextPage();
    };

    $container.append(wrapper);
  };

  const updatePageIndicator = (text) => {
    const $container = $('#mangaView .manga-pages');
    const info = document.createElement('div');
    info.className = 'text-center mt-2 text-muted text-sm w-100';
    info.style.gridColumn = '1 / -1';
    info.textContent = text;
    $container.append(info);
  };

  return {
    render(apiResponse, layout = currentLayout) {
      const data = apiResponse.data || apiResponse;
      currentData = data;
      currentLayout = layout;
      
      const ctx = window.__NMR_CONTEXT || {};
      const r = (ctx.auth && ctx.auth.preferences) ? ctx.auth.preferences.reader : {};
      const fit = _getCookie('melt_reader_imageFit') || r.imageFit || 'width';

      if (data.type === 'image') {
        $('#mangaView').removeClass('hidden fit-width fit-height fit-original').addClass(`fit-${fit}`);
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

    setLayout(layout) {
      currentLayout = layout;
      if (currentData) {
        if (layout !== 'vertical') currentPage = 0;
        this.render(currentData, layout);
      }
    },

    setFit(fit) {
      $('#mangaView').removeClass('fit-width fit-height fit-original').addClass(`fit-${fit}`);
    },

    applyStyles() {
      if (window.NMR && window.NMR.syncReaderStyles) window.NMR.syncReaderStyles();
    },

    applyProtection(selector) {
      const el = document.querySelector(selector);
      if (!el) return;
      el.oncontextmenu = (e) => e.preventDefault();
      el.oncopy = (e) => e.preventDefault();
      el.onselectstart = (e) => e.preventDefault();
    },

    nextPage() {
      if (!currentData) return;
      if (currentLayout === 'vertical') { this.nextChapter(); return; }
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

    prevPage() {
      if (!currentData) return;
      if (currentLayout === 'vertical') { this.prevChapter(); return; }
      const step = currentLayout === 'double' ? 2 : 1;
      if (currentPage - step >= 0) {
        currentPage -= step;
        renderManga();
        window.scrollTo(0, 0);
      } else {
        this.prevChapter();
      }
    },

    nextChapter() {
      const select = document.getElementById('chapterSelect');
      if (select && select.selectedIndex < select.options.length - 1) {
        select.selectedIndex += 1;
        $(select).trigger('change');
      }
    },

    prevChapter() {
      const select = document.getElementById('chapterSelect');
      if (select && select.selectedIndex > 0) {
        select.selectedIndex -= 1;
        $(select).trigger('change');
      }
    }
  };
})();

$(function () {
  const ctx = window.__NMR_CONTEXT || {};
  const path = window.location.pathname.split('/').filter(Boolean);
  const langPrefix = (path[0] === 'tr' || path[0] === 'en') ? path[0] : '';
  const offset = langPrefix ? 1 : 0;

  const type = ctx.type || path[offset] || '';
  const slug = ctx.slug || path[offset + 1] || '';
  let currentChapter = ctx.chapterNumber || (path.length >= (offset + 4) ? path[offset + 3] : '1');
  let chapters = [];
  let isFirstLoad = true;

  const normalizeChapterNumber = (v) => String(v ?? '').trim().replace(/\.?0+$/, '');

  const updateChapterNavButtons = () => {
    const select = document.getElementById('chapterSelect');
    if (!select) return;
    const isFirst = select.selectedIndex <= 0;
    const isLast = select.selectedIndex >= select.options.length - 1;

    $('#prevChapterBtn').toggleClass('disabled', isFirst).toggleClass('opacity-30', isFirst);
    $('#nextChapterBtn').toggleClass('disabled', isLast).toggleClass('opacity-30', isLast);
  };

  const loadChapter = async (num) => {
    const target = normalizeChapterNumber(num);
    const ssrBody = $('.novel-content').text().trim();
    const isSSRMatch = isFirstLoad && ssrBody.length > 0 && !$('#novelView').hasClass('hidden');
    isFirstLoad = false;

    try {
      if (!isSSRMatch) {
        const res = await Connection.getChapterDetail(type, slug, target);
        const layout = window.getCookie ? (window.getCookie('melt_reader_layout') || 'vertical') : 'vertical';
        Reader.render(res, layout);
      } else {
        const raw = $('.novel-content').html();
        if (raw && !raw.includes('<p>')) $('.novel-content').html(NMR.parseMarkdown(raw));
        Reader.applyStyles();
      }
      
      const found = chapters.find(ch => normalizeChapterNumber(ch.chapter_number) === target);
      if (found) {
        const cRes = await Connection.getChapterComments(found.id);
        renderComments(found.id, cRes.data);
      }
    } catch (err) { showPopup(err.message || NMR.__t('msg_load_failed'), 'error'); }
    updateChapterNavButtons();
  };

  const renderComments = (id, rows) => {
    const html = (rows || []).map(c => `
      <div class="comment flex gap-3 pb-3 border-bottom" data-id="${c.id}">
        <div class="flex-grow">
          <div class="flex justify-between items-center mb-1">
            <strong class="text-sm">@${c.username || 'User'}</strong>
            <span class="text-xs text-muted">${c.created_at || ''}</span>
          </div>
          <div class="text-sm text-muted leading-relaxed markdown-body">${NMR.parseMarkdown(c.body || '')}</div>
        </div>
      </div>
    `).join('');
    $('#readerCommentsList').html(html || `<div class="text-center py-3 text-muted">${NMR.__t('no_comments_yet')}</div>`);
  };

  Connection.getChapters(type, slug).then(res => {
    chapters = (res.data || []).sort((a, b) => parseFloat(a.chapter_number) - parseFloat(b.chapter_number));
    const select = $('#chapterSelect');
    select.empty();
    chapters.forEach(ch => {
      const n = normalizeChapterNumber(ch.chapter_number);
      const opt = document.createElement('option');
      opt.value = n;
      opt.textContent = `${NMR.__t('chapter')} ${n}`;
      opt.selected = n === normalizeChapterNumber(currentChapter);
      select.append(opt);
    });
    updateChapterNavButtons();
    loadChapter(currentChapter);
  });

  $('#chapterSelect').on('change', function () {
    const val = $(this).val();
    if (!val) return;
    currentChapter = val;
    const prefix = langPrefix ? `/${langPrefix}` : '';
    window.history.replaceState({}, '', `${prefix}/${type}/${slug}/chapter/${val}`);
    loadChapter(val);
  });

  $('#prevChapterBtn').on('click', function(e) { e.preventDefault(); Reader.prevChapter(); });
  $('#nextChapterBtn').on('click', function(e) { e.preventDefault(); Reader.nextChapter(); });

  $(document).on('keydown', (e) => {
    if (['input', 'textarea'].includes(e.target.tagName.toLowerCase())) return;
    if (e.key === 'ArrowRight') Reader.nextPage();
    if (e.key === 'ArrowLeft') Reader.prevPage();
  });
});
