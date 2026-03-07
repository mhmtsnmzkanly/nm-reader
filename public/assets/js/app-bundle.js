/**
 * app-bundle.js - Unified Frontend Engine for NovelMangaReader
 * Fully migrated to jQuery 3.7+
 */

window.App = (function($) {
  'use strict';

  const ctx = window.__NMR_CONTEXT || {};
  const BASE_API_URL = '/api/v1';
  let dictionary = {};

  const Utils = {
    getCookie: (name) => {
      const value = `; ${document.cookie}`;
      const parts = value.split(`; ${name}=`);
      return parts.length === 2 ? parts.pop().split(';').shift() : null;
    },
    setCookie: (name, value, days) => {
      let expires = "";
      if (days) {
        const date = new Date();
        date.setTime(Date.now() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
      }
      document.cookie = `${name}=${value || ""}${expires}; path=/; SameSite=Lax`;
    },
    getLangPrefix: () => {
      const parts = window.location.pathname.split('/').filter(Boolean);
      return (parts[0] === 'tr' || parts[0] === 'en') ? parts[0] : 'tr';
    },
    parseMarkdown: (text) => {
      if (typeof marked === 'undefined') return text || '';
      try {
        const clean = String(text || '').trim();
        return typeof marked.parse === 'function' ? marked.parse(clean, { async: false }) : marked(clean);
      } catch (e) { return text || ''; }
    }
  };

  const Connection = (function() {
    let csrfToken = ctx.auth?.csrf_token || sessionStorage.getItem('csrf_token') || null;
    
    const request = async (path, options = {}) => {
      try {
        const headers = { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) };
        if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

        const res = await fetch(`${BASE_API_URL}${path}`, { credentials: 'include', ...options, headers });
        const ct = res.headers.get('content-type');
        const payload = (ct && ct.includes('json')) ? await res.json() : { status: 'success' };

        if (payload.data?.csrf_token) {
          csrfToken = payload.data.csrf_token;
          sessionStorage.setItem('csrf_token', csrfToken);
        }

        if (!res.ok || payload.status === 'error') throw new Error(payload.message || 'API Error');
        return payload;
      } catch (e) { throw e; }
    };

    return {
      request,
      getChapters: (t, s) => request(`/content/${t}/${s}/chapters`),
      getChapterDetail: (t, s, n) => request(`/content/${t}/${s}/chapter/${n}`),
      getChapterComments: (id) => request(`/chapter/${id}/comments`),
      postChapterComment: (id, body) => request(`/chapter/${id}/comment`, { method: 'POST', body: JSON.stringify({ body }) }),
      updatePreferences: (d) => request('/user/preferences', { method: 'PUT', body: JSON.stringify(d) })
    };
  })();

  const Global = {
    init: function() {
      this.bindModals();
      this.setupSearch();
      this.setupSettings();
    },
    bindModals: function() {
      window.openModal = (id) => {
        $('.modal-overlay').removeClass('active');
        if (id === 'readerSettingsModal') this.syncReaderModal();
        setTimeout(() => { const el = document.getElementById(id); if (el) el.classList.add('active'); }, 10);
      };
      window.closeModal = () => $('.modal-overlay').removeClass('active');
      $('body').on('click', '.modal-overlay', function(e) { if (e.target === this) window.closeModal(); });
    },
    syncReaderModal: function() {
      const r = ctx.auth?.preferences?.reader || {};
      $('#readerLayoutSelect').val(Utils.getCookie('melt_reader_layout') || r.layout || 'vertical');
      $('[name="reader_image_fit"]').val(Utils.getCookie('melt_reader_imageFit') || r.imageFit || 'width');
    },
    setupSearch: function() {
      $('#globalSearchForm').on('submit', (e) => {
        e.preventDefault();
        const q = $('#globalSearchInput').val().trim();
        if (q.length >= 2) location.href = `/${Utils.getLangPrefix()}/search?q=${encodeURIComponent(q)}`;
      });
    },
    setupSettings: function() {
      $('body').on('click', '#saveAllSettingsBtn', async () => {
        const layout = $('#readerLayoutSelect').val();
        const payload = {
          theme: $('body').attr('theme'),
          reader: {
            layout: layout,
            imageFit: $('[name="reader_image_fit"]').val(),
            fontSize: parseInt($('[name="reader_font_size"]').val()) || 18,
            fontFamily: $('[name="reader_font_family"]').val() || 'var(--font-sans)'
          }
        };
        try {
          Utils.setCookie('melt_reader_layout', layout, 30);
          await Connection.updatePreferences(payload);
          if (window.showPopup) window.showPopup(window.NMR.__t('msg_settings_saved'), 'success');
          setTimeout(() => location.reload(), 800);
        } catch (e) { if (window.showPopup) window.showPopup(e.message, 'error'); }
      });
    }
  };

  const Reader = (function() {
    let currentData = null, currentLayout = 'vertical', currentPage = 0;

    const renderManga = () => {
      const $container = $('#mangaView .manga-pages');
      $container.empty().removeClass('layout-single layout-double');
      const pages = currentData.pages || [];
      if (!pages.length) { $container.html('<div class="p-4 text-muted">No images</div>'); return; }

      if (currentLayout === 'vertical') {
        pages.forEach(p => {
          const img = document.createElement('img'); img.src = p.image_path;
          img.className = 'manga-page-img mb-2'; $container.append(img);
        });
        return;
      }

      const isDouble = currentLayout === 'double';
      $container.addClass(isDouble ? 'layout-double' : 'layout-single');
      const wrapper = $('<div class="manga-interactive-wrapper"></div>');

      if (isDouble) {
        const start = currentPage % 2 === 0 ? currentPage : currentPage - 1;
        [start, start+1].forEach(idx => {
          if (pages[idx]) wrapper.append(`<img src="${pages[idx].image_path}" class="manga-page-img">`);
        });
      } else {
        wrapper.append(`<img src="${pages[currentPage].image_path}" class="manga-page-img">`);
      }

      wrapper.on('click', (e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        if ((e.clientX - rect.left) < rect.width / 2) prevP(); else nextP();
      });
      $container.append(wrapper);
    };

    const nextP = () => {
      if (currentLayout === 'vertical') { nextC(); return; }
      if (currentPage + (currentLayout === 'double' ? 2 : 1) < currentData.pages.length) { 
        currentPage += (currentLayout === 'double' ? 2 : 1); renderManga(); window.scrollTo(0,0); 
      } else nextC();
    };
    const prevP = () => {
      if (currentLayout === 'vertical') { prevC(); return; }
      if (currentPage - (currentLayout === 'double' ? 2 : 1) >= 0) { 
        currentPage -= (currentLayout === 'double' ? 2 : 1); renderManga(); window.scrollTo(0,0); 
      } else prevC();
    };
    const nextC = () => {
      const s = document.getElementById('chapterSelect');
      if (s && s.selectedIndex < s.options.length - 1) { s.selectedIndex++; $(s).trigger('change'); }
    };
    const prevC = () => {
      const s = document.getElementById('chapterSelect');
      if (s && s.selectedIndex > 0) { s.selectedIndex--; $(s).trigger('change'); }
    };

    return {
      init: function() {
        console.log("[App] Initializing Reader...");
        const path = window.location.pathname.split('/').filter(Boolean);
        const lang = (path[0] === 'tr' || path[0] === 'en') ? path[0] : '';
        const offset = lang ? 1 : 0;
        const type = path[offset], slug = path[offset+1], num = path[offset+3];

        Connection.getChapters(type, slug).then(res => {
          const chapters = (res.data || []).sort((a,b) => parseFloat(a.chapter_number) - parseFloat(b.chapter_number));
          const select = $('#chapterSelect'); select.empty();
          chapters.forEach(ch => {
            const n = String(ch.chapter_number).replace(/\.?0+$/, '');
            select.append(`<option value="${n}" ${n === num ? 'selected' : ''}>${window.NMR.__t('chapter')} ${n}</option>`);
          });
          
          select.on('change', function() {
            const val = $(this).val();
            window.history.replaceState({}, '', `/${lang ? lang+'/' : ''}${type}/${slug}/chapter/${val}`);
            location.reload();
          });

          Connection.getChapterDetail(type, slug, num).then(res => {
            currentData = res.data;
            currentLayout = Utils.getCookie('melt_reader_layout') || ctx.auth?.preferences?.reader?.layout || 'vertical';
            const fit = Utils.getCookie('melt_reader_imageFit') || ctx.auth?.preferences?.reader?.imageFit || 'width';
            
            if (currentData.type === 'image') {
              $('#mangaView').removeClass('hidden').addClass(`fit-${fit}`);
              renderManga();
            } else {
              $('#novelView').removeClass('hidden');
              $('#novelView .novel-content').html(Utils.parseMarkdown(currentData.body));
            }
          });
        });

        $(document).on('keydown', (e) => {
          if (['input','textarea'].includes(e.target.tagName.toLowerCase())) return;
          if (e.key === 'ArrowRight') nextP();
          if (e.key === 'ArrowLeft') prevP();
        });
        
        $('#prevChapterBtn').on('click', (e) => { e.preventDefault(); prevC(); });
        $('#nextChapterBtn').on('click', (e) => { e.preventDefault(); nextC(); });
      }
    };
  })();

  const init = async () => {
    const lang = ctx.lang_code || 'tr';
    try {
      const res = await fetch(`/api/v1/i18n/${lang}`);
      const payload = await res.json();
      dictionary = payload.data || {};
    } catch(e) { dictionary = ctx.lang || {}; }

    window.NMR = Object.assign(window.NMR || {}, {
      __t: (key) => dictionary[key] || key,
      getLangPrefix: Utils.getLangPrefix,
      parseMarkdown: Utils.parseMarkdown
    });

    Global.init();
    const path = window.location.pathname;
    const langPrefix = (path.split('/')[1] === 'tr' || path.split('/')[1] === 'en') ? '/' + path.split('/')[1] : '';
    if (path.replace(langPrefix, '').includes('/chapter/')) Reader.init();
  };

  return { init, Modules: { Global, Reader }, Utils, Connection };

})(window.jQuery);

$(function() { App.init(); });
