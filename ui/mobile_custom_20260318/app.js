(() => {
  const API_BASE = window.NMR_MOBILE_API_BASE || '/api/v1';
  const AUTH_STORAGE_KEY = 'nmr_mobile_auth_v1';

  const state = {
    auth: loadAuth(),
    wallet: null,
    pendingUnlock: null,
  };

  function loadAuth() {
    try {
      const raw = localStorage.getItem(AUTH_STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (err) {
      return null;
    }
  }

  function saveAuth(auth) {
    state.auth = auth;
    if (auth) {
      localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(auth));
    } else {
      localStorage.removeItem(AUTH_STORAGE_KEY);
    }
    updateAccountPanel();
  }

  function updateAccountPanel() {
    const nameEl = document.getElementById('panel-account-name');
    const emailEl = document.getElementById('panel-account-email');
    const actionsEl = document.getElementById('panel-account-actions');
    if (!nameEl || !emailEl || !actionsEl) return;

    if (state.auth && state.auth.username) {
      nameEl.textContent = state.auth.username;
      emailEl.textContent = state.auth.email || 'Signed in';
      actionsEl.innerHTML = '<a href="#" class="button button-fill button-small" data-action="logout">Logout</a>';
    } else {
      nameEl.textContent = 'Guest';
      emailEl.textContent = 'Sign in to unlock chapters.';
      actionsEl.innerHTML = '<a href="#" class="button button-fill button-small" data-action="open-login">Login</a>';
    }
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function extractData(payload) {
    if (!payload) return null;
    if (payload.data !== undefined) return payload.data;
    return payload;
  }

  async function apiFetch(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = Object.assign({
      'Accept': 'application/json',
    }, options.headers || {});

    if (method !== 'GET' && method !== 'HEAD') {
      headers['Content-Type'] = 'application/json';
      if (state.auth && state.auth.csrf_token) {
        headers['X-CSRF-Token'] = state.auth.csrf_token;
      }
    }

    if (state.auth && state.auth.api_token) {
      headers['Authorization'] = `Bearer ${state.auth.api_token}`;
    }

    const response = await fetch(API_BASE + path, {
      credentials: 'include',
      ...options,
      headers,
    });

    let payload = null;
    const text = await response.text();
    if (text) {
      try {
        payload = JSON.parse(text);
      } catch (err) {
        payload = { message: text };
      }
    }

    if (response.status === 401) {
      app.popup.open('#auth-popup');
    }

    if (!response.ok || (payload && payload.status === 'error')) {
      const message = payload?.error?.message || payload?.message || response.statusText;
      throw new Error(message || 'Request failed');
    }

    return payload;
  }

  function formatChapterPrice(chapter) {
    const price = Number(chapter?.price_coin || chapter?.access?.chapter_unlock_price || 0);
    if (price > 0 && (chapter?.is_locked ?? true)) {
      return `<div class="chapter-price">${price} coins</div>`;
    }
    return '<div class="chapter-price">Free</div>';
  }

  function renderChapterList(chapters, type, slug) {
    if (!Array.isArray(chapters) || chapters.length === 0) {
      return '<div class="block">No chapters found.</div>';
    }

    const items = chapters.map((chapter) => {
      const chapterNumber = escapeHtml(chapter.chapter_number || chapter.number || chapter.chapterNumber || '');
      const title = escapeHtml(chapter.title || `Chapter ${chapterNumber}`);
      const isLocked = chapter.is_locked ?? false;
      const status = isLocked ? '<span class="badge color-orange">Locked</span>' : '<span class="badge color-green">Open</span>';
      return `
        <li>
          <a class="item-link item-content" href="/reader/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/${encodeURIComponent(chapterNumber)}/">
            <div class="item-inner">
              <div class="item-title-row">
                <div class="item-title">${title}</div>
                <div class="item-after">${status}</div>
              </div>
              ${formatChapterPrice(chapter)}
            </div>
          </a>
        </li>
      `;
    }).join('');

    return `<div class="list media-list"><ul>${items}</ul></div>`;
  }

  function renderHome(data) {
    const latest = data?.recent_chapters || data?.latest_chapters || [];
    const recent = data?.recently_added || [];

    const latestItems = latest.map((chapter) => {
      const title = escapeHtml(chapter.title || chapter.series_title || 'Chapter');
      const chapterNumber = escapeHtml(chapter.chapter_number || chapter.chapterNumber || '');
      const slug = encodeURIComponent(chapter.slug || chapter.series_slug || '');
      const type = encodeURIComponent(chapter.type || chapter.series_type || '');
      return `
        <li>
          <a class="item-link item-content" href="/reader/${type}/${slug}/${chapterNumber}/">
            <div class="item-inner">
              <div class="item-title-row">
                <div class="item-title">${title}</div>
                <div class="item-after">#${chapterNumber}</div>
              </div>
              ${formatChapterPrice(chapter)}
            </div>
          </a>
        </li>
      `;
    }).join('');

    const recentItems = recent.map((item) => {
      const title = escapeHtml(item.title || 'Series');
      const slug = encodeURIComponent(item.slug || '');
      const type = encodeURIComponent(item.type || '');
      return `
        <li>
          <a class="item-link item-content" href="/content/${type}/${slug}/">
            <div class="item-inner">
              <div class="item-title">${title}</div>
            </div>
          </a>
        </li>
      `;
    }).join('');

    return {
      latestHtml: latestItems || '<li class="skeleton-text">No chapters yet.</li>',
      recentHtml: recentItems || '<li class="skeleton-text">No recent series.</li>',
    };
  }

  function renderReader(chapter, type, slug) {
    if (!chapter) {
      return '<div class="block">Chapter not found.</div>';
    }

    if (chapter.is_locked) {
      const price = Number(chapter.price_coin || chapter?.access?.chapter_unlock_price || 0);
      return `
        <div class="block locked-banner">
          <div>This chapter is locked.</div>
          <div>${price} coins required.</div>
          <div class="purchase-actions">
            <a href="#" class="button button-fill" data-action="unlock-chapter" data-chapter-id="${escapeHtml(chapter.id || '')}" data-price="${price}">Unlock</a>
            <a href="/wallet/" class="button button-outline">Go to Wallet</a>
          </div>
        </div>
      `;
    }

    if (chapter.type === 'image') {
      const pages = Array.isArray(chapter.pages) ? chapter.pages : [];
      const images = pages.map((url) => {
        const safeUrl = escapeHtml(url);
        return `<img class="reader-image" src="${safeUrl}" alt="" loading="lazy" />`;
      }).join('');
      return `<div class="block">${images || 'No pages available.'}</div>`;
    }

    const body = String(chapter.body || '');
    const paragraphs = escapeHtml(body).split('\n').filter(Boolean).map((line) => `<p>${line}</p>`).join('');
    return `<div class="block reader-text">${paragraphs || 'No text available.'}</div>`;
  }

  const app = new Framework7({
    root: '#app',
    name: 'NMR Mobile',
    theme: 'auto',
    view: {
      pushState: true,
      pushStateRoot: '/mobile/',
    },
    routes: [
      {
        path: '/',
        async: async (routeTo, routeFrom, resolve, reject) => {
          try {
            app.preloader.show();
            const payload = await apiFetch('/home');
            const data = extractData(payload) || {};
            const lists = renderHome(data);
            const content = `
              <div class="page" data-name="home">
                <div class="navbar">
                  <div class="navbar-bg"></div>
                  <div class="navbar-inner">
                    <div class="title">NMR Mobile</div>
                    <div class="right">
                      <a class="link icon-only panel-open" data-panel="right" href="#">
                        <i class="f7-icons">menu</i>
                      </a>
                    </div>
                  </div>
                </div>
                <div class="page-content">
                  <div class="block block-strong">
                    <p class="intro">Mobile-first reader experience for NovelMangaReader.</p>
                    <div class="intro-actions">
                      <a href="/types/manga/" class="button button-fill">Browse Manga</a>
                      <a href="/types/novel/" class="button button-outline">Browse Novels</a>
                    </div>
                  </div>
                  <div class="block-title">Latest Chapters</div>
                  <div class="list media-list"><ul>${lists.latestHtml}</ul></div>
                  <div class="block-title">Recently Added</div>
                  <div class="list media-list"><ul>${lists.recentHtml}</ul></div>
                </div>
              </div>
            `;
            resolve({ content });
          } catch (err) {
            reject();
            app.dialog.alert(err.message || 'Failed to load home');
          } finally {
            app.preloader.hide();
          }
        },
      },
      {
        path: '/types/:type/',
        async: async (routeTo, routeFrom, resolve, reject) => {
          const type = routeTo.params.type;
          try {
            app.preloader.show();
            const payload = await apiFetch(`/content/type/${encodeURIComponent(type)}?page=1&per_page=20`);
            const items = extractData(payload) || [];
            const listItems = items.map((item) => {
              const title = escapeHtml(item.title || 'Series');
              const slug = encodeURIComponent(item.slug || '');
              return `
                <li>
                  <a class="item-link item-content" href="/content/${encodeURIComponent(type)}/${slug}/">
                    <div class="item-inner">
                      <div class="item-title">${title}</div>
                    </div>
                  </a>
                </li>
              `;
            }).join('');

            const content = `
              <div class="page" data-name="type-list">
                <div class="navbar">
                  <div class="navbar-bg"></div>
                  <div class="navbar-inner">
                    <div class="left"><a class="link back">Back</a></div>
                    <div class="title">${escapeHtml(type)}</div>
                  </div>
                </div>
                <div class="page-content">
                  <div class="list media-list">
                    <ul>${listItems || '<li class="skeleton-text">No content found.</li>'}</ul>
                  </div>
                </div>
              </div>
            `;
            resolve({ content });
          } catch (err) {
            reject();
            app.dialog.alert(err.message || 'Failed to load list');
          } finally {
            app.preloader.hide();
          }
        },
      },
      {
        path: '/content/:type/:slug/',
        async: async (routeTo, routeFrom, resolve, reject) => {
          const type = routeTo.params.type;
          const slug = routeTo.params.slug;
          try {
            app.preloader.show();
            const payload = await apiFetch(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}`);
            const contentData = extractData(payload) || {};
            const chaptersPayload = await apiFetch(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/chapters`);
            const chapters = extractData(chaptersPayload) || [];

            const content = `
              <div class="page" data-name="content-detail">
                <div class="navbar">
                  <div class="navbar-bg"></div>
                  <div class="navbar-inner">
                    <div class="left"><a class="link back">Back</a></div>
                    <div class="title">${escapeHtml(contentData.title || 'Series')}</div>
                  </div>
                </div>
                <div class="page-content">
                  <div class="block block-strong">
                    <p class="intro">${escapeHtml(contentData.description || 'No description.')}</p>
                  </div>
                  <div class="block-title">Chapters</div>
                  ${renderChapterList(chapters, type, slug)}
                </div>
              </div>
            `;
            resolve({ content });
          } catch (err) {
            reject();
            app.dialog.alert(err.message || 'Failed to load content');
          } finally {
            app.preloader.hide();
          }
        },
      },
      {
        path: '/reader/:type/:slug/:chapterNumber/',
        async: async (routeTo, routeFrom, resolve, reject) => {
          const type = routeTo.params.type;
          const slug = routeTo.params.slug;
          const chapterNumber = routeTo.params.chapterNumber;
          try {
            app.preloader.show();
            const payload = await apiFetch(`/content/${encodeURIComponent(type)}/${encodeURIComponent(slug)}/chapter/${encodeURIComponent(chapterNumber)}`);
            const chapter = extractData(payload) || payload;
            const title = escapeHtml(chapter?.title || `Chapter ${chapterNumber}`);

            const content = `
              <div class="page" data-name="reader">
                <div class="navbar">
                  <div class="navbar-bg"></div>
                  <div class="navbar-inner">
                    <div class="left"><a class="link back">Back</a></div>
                    <div class="title">${title}</div>
                  </div>
                </div>
                <div class="page-content">
                  ${renderReader(chapter, type, slug)}
                </div>
              </div>
            `;
            resolve({ content });
          } catch (err) {
            reject();
            app.dialog.alert(err.message || 'Failed to load chapter');
          } finally {
            app.preloader.hide();
          }
        },
      },
      {
        path: '/wallet/',
        async: async (routeTo, routeFrom, resolve, reject) => {
          try {
            app.preloader.show();
            const payload = await apiFetch('/user/wallet');
            const wallet = extractData(payload) || {};
            state.wallet = wallet;
            const balance = Number(wallet.balance || 0);
            const content = `
              <div class="page" data-name="wallet">
                <div class="navbar">
                  <div class="navbar-bg"></div>
                  <div class="navbar-inner">
                    <div class="left"><a class="link back">Back</a></div>
                    <div class="title">Wallet</div>
                  </div>
                </div>
                <div class="page-content">
                  <div class="block block-strong">
                    <div>Balance</div>
                    <h2>${balance} coins</h2>
                  </div>
                  <div class="block">
                    <p class="intro">Top up your wallet from the web admin until payment providers are integrated.</p>
                  </div>
                </div>
              </div>
            `;
            resolve({ content });
          } catch (err) {
            reject();
            app.dialog.alert(err.message || 'Failed to load wallet');
          } finally {
            app.preloader.hide();
          }
        },
      },
    ],
  });

  const mainView = app.views.create('.view-main', { url: '/' });

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (form && form.id === 'login-form') {
      event.preventDefault();
      const formData = new FormData(form);
      const payload = {
        email: formData.get('email'),
        password: formData.get('password'),
        remember: !!formData.get('remember'),
      };

      try {
        app.preloader.show();
        const response = await apiFetch('/auth/login', {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        const data = extractData(response) || response;
        saveAuth({
          id: data.id,
          username: data.username,
          email: data.email,
          api_token: data.api_token,
          csrf_token: data.csrf_token,
          refresh_token: data.refresh_token || null,
        });
        app.popup.close('#auth-popup');
        app.dialog.alert('Login successful.');
      } catch (err) {
        app.dialog.alert(err.message || 'Login failed');
      } finally {
        app.preloader.hide();
      }
    }
  });

  document.addEventListener('click', async (event) => {
    const target = event.target.closest('[data-action]');
    if (!target) return;
    const action = target.getAttribute('data-action');

    if (action === 'open-login') {
      event.preventDefault();
      app.popup.open('#auth-popup');
      return;
    }

    if (action === 'logout') {
      event.preventDefault();
      try {
        await apiFetch('/auth/logout');
      } catch (err) {
        // ignore logout errors
      }
      saveAuth(null);
      app.dialog.alert('Logged out.');
      return;
    }

    if (action === 'unlock-chapter') {
      event.preventDefault();
      const chapterId = target.getAttribute('data-chapter-id');
      const price = target.getAttribute('data-price');
      state.pendingUnlock = { chapterId };
      const desc = document.getElementById('purchase-description');
      if (desc) {
        desc.textContent = `Unlock this chapter for ${price} coins?`;
      }
      app.popup.open('#purchase-popup');
      return;
    }

    if (action === 'confirm-unlock') {
      event.preventDefault();
      if (!state.pendingUnlock || !state.pendingUnlock.chapterId) return;
      try {
        app.preloader.show();
        await apiFetch(`/chapter/${encodeURIComponent(state.pendingUnlock.chapterId)}/unlock`, {
          method: 'POST',
          body: JSON.stringify({}),
        });
        app.popup.close('#purchase-popup');
        app.dialog.alert('Chapter unlocked.');
        state.pendingUnlock = null;
        mainView.router.refreshPage();
      } catch (err) {
        app.dialog.alert(err.message || 'Unlock failed');
      } finally {
        app.preloader.hide();
      }
    }
  });

  updateAccountPanel();
})();
