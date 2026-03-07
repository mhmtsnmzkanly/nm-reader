/**
 * app-bundle.js - Unified Frontend Engine for NovelMangaReader
 * 
 * This bundle manages the entire frontend lifecycle, including state hydration,
 * API communication, and page-specific interactivity.
 */

window.App = (function($) {
  'use strict';

  // --- CORE STATE & CONSTANTS ---
  const ctx = window.__NMR_CONTEXT || {};
  const BASE_API_URL = '/api/v1';
  let dictionary = {};

  /**
   * CORE UTILITIES (formerly utils.js)
   */
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
    },
    formatDuration: (s) => {
      if (!s || s <= 0) return '0s';
      const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
      return h > 0 ? `${h}h ${m}m` : (m > 0 ? `${m}m` : `${s}s`);
    }
  };

  /**
   * API & CONNECTION BRIDGE (formerly api.js & connection.js)
   */
  const Connection = (function() {
    let csrfToken = ctx.auth?.csrf_token || sessionStorage.getItem('csrf_token') || null;
    const inFlight = new Map();

    const request = async (path, options = {}) => {
      const cacheKey = `${options.method || 'GET'}:${path}`;
      if (inFlight.has(cacheKey)) return inFlight.get(cacheKey);

      const promise = (async () => {
        try {
          const headers = { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) };
          if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
          if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

          const res = await fetch(`${BASE_API_URL}${path}`, { credentials: 'include', ...options, headers });
          const ct = res.headers.get('content-type');
          const payload = (ct && ct.includes('json')) ? await res.json() : { status: 'error', message: await res.text() };

          if (payload.data?.csrf_token) {
            csrfToken = payload.data.csrf_token;
            sessionStorage.setItem('csrf_token', csrfToken);
          }

          if (!res.ok || payload.status === 'error') {
            const err = new Error(payload.error?.message || payload.message || 'API Error');
            err.status = res.status;
            throw err;
          }
          return payload;
        } finally { inFlight.delete(cacheKey); }
      })();

      inFlight.set(cacheKey, promise);
      return promise;
    };

    return {
      request,
      getHome: () => request('/home'),
      getChapters: (t, s) => request(`/content/${t}/${s}/chapters`),
      getChapterDetail: (t, s, n) => request(`/content/${t}/${s}/chapter/${n}`),
      getChapterComments: (id) => request(`/chapter/${id}/comments`),
      postChapterComment: (id, body) => request(`/chapter/${id}/comment`, { method: 'POST', body: JSON.stringify({ body }) }),
      login: (e, p, r, t) => request('/auth/login', { method: 'POST', body: JSON.stringify({ email:e, password:p, remember:r, turnstile_token:t }) }),
      register: (u, e, p, t) => request('/auth/register', { method: 'POST', body: JSON.stringify({ username:u, email:e, password:p, turnstile_token:t }) }),
      updatePreferences: (d) => request('/user/preferences', { method: 'PUT', body: JSON.stringify(d) }),
      getNotifications: () => request('/user/notifications'),
      markNotificationsRead: () => request('/user/notifications/read', { method: 'POST' }),
      trackActivity: (tid, dur) => {
        const fd = new FormData(); fd.append('tab_id', tid); fd.append('duration', dur);
        navigator.sendBeacon(`${BASE_API_URL}/user/activity`, fd);
      }
    };
  })();

  /**
   * GLOBAL UI MODULE (formerly main.js)
   */
  const Global = {
    init: function() {
      this.hydrateAuth();
      this.setupSearch();
      this.bindModals();
      this.setupSettings();
      this.startActivityTracker();
    },
    hydrateAuth: function() {
      window.NMR.currentUser = ctx.auth?.is_logged_in ? (ctx.auth.username || 'User') : null;
      this.syncHeader();
    },
    syncHeader: function() {
      const target = $('#headerAuthLinks'); if (!target.elements.length) return;
      if (window.NMR.currentUser) {
        const lang = Utils.getLangPrefix();
        const adminLink = ctx.auth?.is_admin ? `<a href="/${lang}/admin" class="dropdown-item text-warning">🛠 ${window.NMR.__t('admin_panel')}</a>` : '';
        target.html(`
          <div class="dropdown">
            <button class="nav-link dropdown-toggle btn-none">👤 ${window.NMR.currentUser} <span id="headerNotifBadge" class="badge bg-danger hidden">0</span></button>
            <div class="dropdown-menu card p-2" style="right: 0; left: auto; min-width: 200px">
              <a href="/${lang}/profile" class="dropdown-item">👤 ${window.NMR.__t('my_profile')}</a>
              <a href="#" class="dropdown-item" id="headerNotifBtn">🔔 ${window.NMR.__t('notifications')}</a>
              <a href="#" class="dropdown-item" onclick="openModal('readerSettingsModal');return false;">⚙️ ${window.NMR.__t('reader_settings')}</a>
              ${adminLink}
              <hr class="my-1 border-0 border-t opacity-10">
              <a class="dropdown-item text-danger" href="/logout">🚪 ${window.NMR.__t('logout')}</a>
            </div>
          </div>
        `);
      }
    },
    setupSearch: function() {
      $('#globalSearchForm')?.on('submit', (e) => {
        e.preventDefault();
        const q = $('#globalSearchInput').val().trim();
        if (q.length < 2) return;
        location.href = `/${Utils.getLangPrefix()}/search?q=${encodeURIComponent(q)}`;
      });
    },
    bindModals: function() {
      window.openModal = (id) => {
        $('.modal-overlay').removeClass('active');
        if (id === 'readerSettingsModal') this.syncReaderModal();
        setTimeout(() => { const el = document.getElementById(id); if (el) el.classList.add('active'); }, 30);
      };
      window.closeModal = () => $('.modal-overlay').removeClass('active');
      $('body').on('click', '.modal-overlay', function(e) { if (e.target === this) window.closeModal(); });
    },
    syncReaderModal: function() {
      const r = ctx.auth?.preferences?.reader || {};
      const layout = Utils.getCookie('melt_reader_layout') || r.layout || 'vertical';
      $('#readerLayoutSelect').val(layout);
      $('[name="reader_image_fit"]').val(Utils.getCookie('melt_reader_imageFit') || r.imageFit || 'width');
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
          window.showPopup(window.NMR.__t('msg_settings_saved'), 'success');
          setTimeout(() => location.reload(), 800);
        } catch (e) { window.showPopup(e.message, 'error'); }
      });
    },
    startActivityTracker: function() {
      if (!ctx.auth?.is_logged_in) return;
      const tid = Math.random().toString(36).substring(2, 15);
      let start = Date.now();
      window.addEventListener('pagehide', () => {
        const dur = Math.floor((Date.now() - start) / 1000);
        if (dur > 0) Connection.trackActivity(tid, dur);
      });
    }
  };

  /**
   * READER MODULE (formerly reader.js)
   */
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
      const wrapper = document.createElement('div'); wrapper.className = 'manga-interactive-wrapper';

      if (isDouble) {
        const start = currentPage % 2 === 0 ? currentPage : currentPage - 1;
        [start, start+1].forEach(idx => {
          if (pages[idx]) {
            const img = document.createElement('img'); img.src = pages[idx].image_path;
            img.className = 'manga-page-img'; wrapper.append(img);
          }
        });
      } else {
        const img = document.createElement('img'); img.src = pages[currentPage].image_path;
        img.className = 'manga-page-img'; wrapper.append(img);
      }

      wrapper.onclick = (e) => {
        const rect = wrapper.getBoundingClientRect();
        if ((e.clientX - rect.left) < rect.width / 2) prevP(); else nextP();
      };
      $container.append(wrapper);
    };

    const nextP = () => {
      if (currentLayout === 'vertical') { nextC(); return; }
      const step = currentLayout === 'double' ? 2 : 1;
      if (currentPage + step < currentData.pages.length) { currentPage += step; renderManga(); window.scrollTo(0,0); } else nextC();
    };
    const prevP = () => {
      if (currentLayout === 'vertical') { prevC(); return; }
      const step = currentLayout === 'double' ? 2 : 1;
      if (currentPage - step >= 0) { currentPage -= step; renderManga(); window.scrollTo(0,0); } else prevC();
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
            const opt = document.createElement('option'); opt.value = n;
            opt.textContent = `${window.NMR.__t('chapter')} ${n}`; opt.selected = (n === num);
            select.append(opt);
          });
          
          select.on('change', function() {
            const val = $(this).val();
            window.history.replaceState({}, '', `/${lang ? lang+'/' : ''}${type}/${slug}/chapter/${val}`);
            location.reload(); // Simple reload for stability
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

  // --- INITIALIZATION ---
  const init = async () => {
    // 1. Wait for i18n
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

    // 2. Run Global Logic
    Global.init();

    // 3. Page Routing
    const path = window.location.pathname;
    const langPrefix = (path.split('/')[1] === 'tr' || path.split('/')[1] === 'en') ? '/' + path.split('/')[1] : '';
    const cleanPath = path.replace(langPrefix, '');

    if (cleanPath.includes('/chapter/')) Reader.init();
    // Add other module initializers here as needed
  };

  return { init, Modules: { Global, Reader }, Utils, Connection };

})(window.$ || window.melt);

document.addEventListener('DOMContentLoaded', () => App.init());
