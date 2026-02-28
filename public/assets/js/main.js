/**
 * main.js - Primary UI Controller for NovelMangaReader.
 *
 * This script initializes the global platform interface. It handles:
 * - Auth Hydration: Syncs local UI state with server-side session.
 * - Global Modals: Management of Login, Register, Notifications, and Reader Settings.
 * - Popup System: A lightweight, non-blocking notification system.
 * - Preferences Sync: Applies themes and reader typography settings globally.
 * - Global Search: Manages the site-wide search input and redirection.
 */

window.NMR = window.NMR || {};

/**
 * Global helpers for Modals.
 * Moved outside DOMReady to be available for early onclick handlers.
 */
window.openModal = (id) => {
  $('.modal-overlay').removeClass('active');
  const ctx = window.__NMR_CONTEXT || {};

  // Pre-populate reader settings from context or cookies.
  if (id === 'readerSettingsModal') {
    const r = (ctx.auth && ctx.auth.preferences) ? ctx.auth.preferences.reader : {};
    const layout = window.getCookie('melt_reader_layout') || r.layout || 'vertical';
    $('#layoutButtonGroup .btn').removeClass('active');
    $(`#layoutButtonGroup .btn[data-val="${layout}"]`).addClass('active');
    $('[name="reader_image_fit"]').val(getCookie('melt_reader_imageFit') || r.imageFit || 'width');
    $('[name="reader_font_family"]').val(getCookie('melt_reader_fontFamily') || r.fontFamily || 'var(--font-sans)');
    const fSize = getCookie('melt_reader_fontSize') || r.fontSize || '18';
    $('[name="reader_font_size"]').val(fSize);
    $('#fontSizeVal').text(fSize);
  }

  if (id === 'userSettingsModal' && window.__NMR_CONTEXT?.user) {
    const u = window.__NMR_CONTEXT.user;
    const p = window.__NMR_CONTEXT.preferences || {};
    $('#userSettingsForm [name="bio"]').val(u.bio || '');
    $('#userSettingsForm [name="theme"]').val(p.theme || 'default');
    $('#userSettingsForm [name="lang"]').val(p.lang || 'tr');
  }

  setTimeout(() => {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
  }, 30);
};

window.closeModal = () => { $('.modal-overlay').removeClass('active'); };

$(function () {
  const ctx = window.__NMR_CONTEXT || {};

  /**
   * Activity Tracker - Monitors focused time and reports via Beacon.
   */
  const ActivityTracker = (() => {
    if (!(ctx.auth && ctx.auth.is_logged_in)) return null;

    const tabId = Math.random().toString(36).substring(2, 15);
    const userId = ctx.auth.user_id;
    let accumulatedTime = 0;
    let lastStartTime = Date.now();
    let isVisible = true;

    const flush = () => {
      const activeNow = isVisible ? Math.floor((Date.now() - lastStartTime) / 1000) : 0;
      const total = accumulatedTime + activeNow;
      if (total <= 0) return;

      if (window.Connection && window.Connection.trackActivity) {
        window.Connection.trackActivity(tabId, total);
      }

      // Reset after flush (e.g. if page remains open)
      accumulatedTime = 0;
      lastStartTime = Date.now();
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        lastStartTime = Date.now();
        isVisible = true;
      } else {
        accumulatedTime += Math.floor((Date.now() - lastStartTime) / 1000);
        isVisible = false;
        flush(); // Flush on hide to ensure data is caught if session ends abruptly
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    // pagehide is the most reliable event for both desktop and mobile session ending.
    window.addEventListener('pagehide', flush);

    return { tabId };
  })();

  // Sync CSRF token from server context to the API bridge.
  if (ctx.auth && ctx.auth.csrf_token) {
    Connection.setCsrfToken(ctx.auth.csrf_token);
  }

  /** @type {string|null} Current logged-in username for UI display. */
  window.NMR.currentUser = (ctx.auth && ctx.auth.is_logged_in)
    ? (ctx.auth.username || ctx.auth.user_id || 'User')
    : null;

  const FALLBACK_IMG = '/adminlte/assets/img/user1-128x128.jpg';

  /**
   * Updates the site theme and persists it to the user account.
   */
  window.setTheme = function (themeName) {
    $('body').attr('theme', themeName);
    if (window.NMR.currentUser) {
      Connection.updatePreferences({ theme: themeName })
        .catch(err => console.error('Failed to save theme:', err));
    }
  };

  /**
   * Synchronizes typography styles for novel/text content based on user preferences.
   */
  window.NMR.syncReaderStyles = () => {
    const context = window.__NMR_CONTEXT || {};
    if (context.auth && context.auth.preferences) {
      const r = context.auth.preferences.reader || {};
      const novelContent = $('.novel-content');
      if (novelContent.elements.length) {
        novelContent.css({
          'font-family': r.fontFamily || 'var(--font-sans)',
          'font-size': (r.fontSize || 18) + 'px',
          'font-weight': r.fontWeight || 400,
          'line-height': r.lineHeight || 1.8
        });
      }
    }
  };

  // NMR Global Helpers (Extended)
  window.NMR = Object.assign(window.NMR || {}, {
    /**
     * Dynamically renders popular and latest content in the global footer.
     */
    renderFooterData: async () => {
      const popularTarget = $('#footerPopular');
      const latestTarget = $('#footerLatest');
      if (!popularTarget.elements.length) return;
      try {
        const [chaptersRes, homeRes] = await Promise.all([
          Connection.getLatestChapters(1, 5),
          Connection.getHome()
        ]);

        const getTypeColor = (type) => {
          if (type.includes('novel')) return 'var(--success)';
          if (type === 'manga') return 'var(--primary)';
          if (type === 'webtoon') return 'var(--info)';
          return 'var(--warning)';
        };

        const langPrefix = window.NMR.getLangPrefix();
        const popularHtml = (homeRes.data.explore || []).slice(0, 8).map(c => {
          const typeLabel = c.type_path.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
          const localizedUrl = `/${langPrefix}/${c.type_path}/${c.slug}`;
          return `
            <a href="${localizedUrl}" class="nav-link footer-link-small">
              <span class="text-muted" style="font-size: 0.7rem; font-weight: 700; min-width: 70px">${typeLabel}</span>
              <span class="type-dot" style="--dot-color: ${getTypeColor(c.type_path)}"></span>
              <span>${c.title}</span>
            </a>
          `;
        }).join('');
        popularTarget.html(popularHtml || '<span class="text-muted">Yüklenemedi.</span>');

        const latestHtml = (chaptersRes.data || []).map(ch => {
          const localizedUrl = `/${langPrefix}/${ch.type_path}/${ch.series_slug}/chapter/${ch.chapter_number}`;
          return `
            <a href="${localizedUrl}" class="nav-link footer-link-small truncate">
              <span>📖 ${ch.series_title} - Ch. ${ch.chapter_number}</span>
            </a>
          `;
        }).join('');
        latestTarget.html(latestHtml || '<span class="text-muted">Yüklenemedi.</span>');
      } catch (err) { console.error('Footer data error:', err); }
    }
  });

  /**
   * Updates the header authentication UI (dropdown vs buttons).
   */
  const syncHeaderAuth = () => {
    const authTarget = $('#headerAuthLinks');
    const lang = window.NMR.getLangPrefix();
    if (!authTarget.elements.length) return;
    if (window.NMR.currentUser) {
      const adminLink = (ctx.auth && ctx.auth.is_admin) ? `<a href="/${lang}/admin" class="dropdown-item text-warning">🛠 ${NMR.__t('admin_panel')}</a>` : '';
      authTarget.html(`
        <div class="dropdown">
          <button class="nav-link dropdown-toggle btn-none">👤 ${window.NMR.currentUser} <span id="headerNotifBadge" class="badge bg-danger hidden">0</span></button>
          <div class="dropdown-menu card p-2" style="right: 0; left: auto; min-width: 200px">
            <a href="/${lang}/profile" class="dropdown-item">👤 ${NMR.__t('my_profile')}</a>
            <a href="#" class="dropdown-item" id="headerNotifBtn">🔔 ${NMR.__t('notifications')}</a>
            <a href="#" class="dropdown-item" onclick="openModal('readerSettingsModal');return false;">⚙️ ${NMR.__t('reader_settings')}</a>
            ${adminLink}
            <hr class="my-1 border-0 border-t opacity-10">
            <a class="dropdown-item text-danger w-100 text-left" href="/logout">🚪 ${NMR.__t('logout')}</a>
          </div>
        </div>
      `);
      Connection.getNotifications().then(res => {
        const unread = (res.data || []).filter(n => !n.is_read).length;
        if (unread > 0) $('#headerNotifBadge').text(unread).removeClass('hidden');
      }).catch(() => { });
    } else {
      authTarget.html(`
        <a href="#" class="nav-link" onclick="openModal('loginModal');return false;">${NMR.__t('login')}</a>
        <a href="#" class="btn btn-sm btn-primary" onclick="openModal('registerModal');return false;">${NMR.__t('signup')}</a>
      `);
    }
  };

  /**
   * Hydrates current user info from the API if SSR context is missing.
   */
  const hydrateAuthFromApi = async () => {
    if (window.NMR.currentUser) return;
    try {
      const res = await Connection.getMyProfile();
      if (res && res.data && !res.data.is_guest) {
        window.NMR.currentUser = res.data.username || 'User';
        syncHeaderAuth();
      }
    } catch (err) {
      if (err && err.status === 401) {
        document.cookie = 'nm_reader_session=; Max-Age=0; path=/';
        sessionStorage.removeItem('csrf_token');
      }
    }
  };

  /**
   * Initializes global UI event listeners and hydration.
   */
  const init = async () => {
    // Wait for translations to be ready.
    if (window.NMR.waitForI18n) await window.NMR.waitForI18n();

    syncHeaderAuth();
    hydrateAuthFromApi();
    NMR.renderFooterData();

    // Event Delegation: Auth Forms
    $('body').on('submit', '#loginForm', async function (e) {
      e.preventDefault();
      const fd = new FormData(this);
      try {
        await Connection.login(
          $(this).find('input[type="email"]').val(), 
          $(this).find('input[type="password"]').val(),
          $('#loginRemember').is(':checked'),
          fd.get('cf-turnstile-response')
        );
        showPopup(NMR.__t('msg_login_success'), 'success');
        setTimeout(() => location.reload(), 1000);
      } catch (err) { 
        showPopup(err.message || NMR.__t('msg_generic_error'), 'error'); 
        if (window.turnstile) turnstile.reset('#loginForm .cf-turnstile');
      }
    });

    $('body').on('submit', '#registerForm', async function (e) {
      e.preventDefault();
      const fd = new FormData(this);
      try {
        await Connection.register(
          $(this).find('input[type="text"]').val(), 
          $(this).find('input[type="email"]').val(), 
          $(this).find('input[type="password"]').val(),
          fd.get('cf-turnstile-response')
        );
        showPopup(NMR.__t('msg_register_success'), 'success');
        openModal('loginModal');
      } catch (err) { 
        showPopup(err.message || NMR.__t('msg_generic_error'), 'error'); 
        if (window.turnstile) turnstile.reset('#registerForm .cf-turnstile');
      }
    });

    // Event Delegation: Global UI Actions
    $('body').on('click', '#headerNotifBtn', async function (e) {
      e.preventDefault();
      openModal('notifModal');
      $('#notifModalList').html(`<div class="p-4 text-center text-muted">${NMR.__t('loading')}</div>`);
      try {
        const res = await Connection.getNotifications();
        const html = (res.data || []).map(n => `
          <div class="p-3 border-bottom ${n.is_read ? 'opacity-60' : 'bg-surface'}" style="border-left: 3px solid ${n.is_read ? 'transparent' : 'var(--primary)'}">
            <div class="flex justify-between items-start mb-1"><span class="font-bold text-sm">${n.title}</span><span class="text-xs text-muted">${n.created_at}</span></div>
            <p class="text-xs m-0">${n.body}</p>
          </div>
        `).join('') || `<div class="p-5 text-center text-muted">${NMR.__t('no_updates_yet')}</div>`;
        $('#notifModalList').html(html);
        $('#headerNotifBadge').addClass('hidden');
      } catch (err) { $('#notifModalList').html(NMR.__t('msg_load_failed')); }
    });

    $('body').on('click', '#markAllReadBtn', async function () {
      try {
        await Connection.markNotificationsRead();
        $('#headerNotifBadge').addClass('hidden');
        $('#notifModalList > div').addClass('opacity-60').css('border-left-color', 'transparent');
        showPopup(NMR.__t('msg_notifications_read'), 'success');
      } catch (err) { showPopup(err.message || NMR.__t('msg_generic_error'), 'error'); }
    });

    $('body').on('click', '.theme-btn', function () {
      const theme = $(this).attr('data-theme');
      $('.theme-btn').removeClass('active');
      $(this).addClass('active');
      setTheme(theme);
    });

    $('body').on('click', '#saveAllSettingsBtn', async function () {
      const payload = {
        theme: $('body').attr('theme'),
        reader: {
          layout: $('#layoutButtonGroup .btn.active').attr('data-val'),
          imageFit: $('[name="reader_image_fit"]').val(),
          fontFamily: $('[name="reader_font_family"]').val(),
          fontSize: parseInt($('[name="reader_font_size"]').val()),
          fontWeight: 400,
          lineHeight: 1.8
        }
      };
      try {
        await Connection.updatePreferences(payload);
        showPopup(NMR.__t('msg_settings_saved'), 'success');
        setTimeout(() => location.reload(), 800);
      } catch (err) { showPopup(err.message || NMR.__t('msg_generic_error'), 'error'); }
    });

    $('body').on('click', '#readerTabSidebar .btn', function () {
      const tab = $(this).attr('data-tab');
      $('#readerTabSidebar .btn').removeClass('active');
      $(this).addClass('active');
      $('.settings-tab').addClass('hidden');
      $(`#tab-${tab}`).removeClass('hidden');
    });

    $('body').on('click', '#layoutButtonGroup .btn', function () {
      $('#layoutButtonGroup .btn').removeClass('active');
      $(this).addClass('active');
    });

    $('body').on('input', '[name="reader_font_size"]', function () { $('#fontSizeVal').text($(this).val()); });

    $('body').on('click', '#openReaderSettings, #openSettingsInMenu, .open-reader-settings', function (e) {
      e.preventDefault();
      openModal('readerSettingsModal');
    });

    $('#globalSearchForm').on('submit', function (e) {
      e.preventDefault();
      const query = $('#globalSearchInput').val().trim();
      const lang = window.NMR.getLangPrefix();
      if (query.length < 2) {
        showPopup(NMR.__t('msg_search_min_chars'), 'info');
        return;
      }
      location.href = `/${lang}/search?q=${encodeURIComponent(query)}`;
    });

    $('body').on('click', '.modal-overlay', function (e) { if (e.target === this) closeModal(); });
  };

  init();
});
