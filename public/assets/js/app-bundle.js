/**
 * app-bundle.js - Unified Frontend Engine for NovelMangaReader
 * Handles Home, Content, Reader, Search, and Listing pages.
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
    hasMeltPrefix: () => {
      const parts = window.location.pathname.split('/').filter(Boolean);
      const offset = (parts[0] === 'tr' || parts[0] === 'en') ? 1 : 0;
      return parts[offset] === 'melt';
    },
    parseMarkdown: (text) => {
      if (typeof marked === 'undefined') return text || '';
      try {
        const clean = String(text || '').trim();
        return typeof marked.parse === 'function' ? marked.parse(clean, { async: false }) : marked(clean);
      } catch (e) { return text || ''; }
    },
    humanDate: (value) => {
      if (!value) return '';
      const dt = new Date(value.replace(' ', 'T'));
      if (Number.isNaN(dt.getTime())) return value;
      return dt.toLocaleDateString(Utils.getLangPrefix() === 'tr' ? 'tr-TR' : 'en-US', { day: 'numeric', month: 'short', year: 'numeric' });
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
      getHome: () => request('/home'),
      getChapters: (t, s) => request(`/content/${t}/${s}/chapters`),
      getChapterDetail: (t, s, n) => request(`/content/${t}/${s}/chapter/${n}`),
      getSeriesComments: (t, s) => request(`/content/${t}/${s}/comments`),
      getChapterComments: (id) => request(`/chapter/${id}/comments`),
      postSeriesComment: (t, s, body) => request(`/content/${t}/${s}/comment`, { method: 'POST', body: JSON.stringify({ body }) }),
      postChapterComment: (id, body) => request(`/chapter/${id}/comment`, { method: 'POST', body: JSON.stringify({ body }) }),
      voteComment: (id, vote) => request(`/comments/${id}/vote`, { method: 'POST', body: JSON.stringify({ vote }) }),
      followSeries: (t, s, isActive) => request(`/content/${t}/${s}/follow`, { method: isActive ? 'DELETE' : 'POST' }),
      rateSeries: (t, s, rating) => request(`/content/${t}/${s}/rate`, { method: 'POST', body: JSON.stringify({ rating }) }),
      updatePreferences: (d) => request('/user/preferences', { method: 'PUT', body: JSON.stringify(d) }),
      getSuggest: (q) => request(`/search/suggest?q=${encodeURIComponent(q)}`),
      getSearch: (q) => request(`/search?q=${encodeURIComponent(q)}`),
      getListing: (type, value) => {
        const typePattern = /^(light-novel|web-novel|novel|manga|manhua|manhwa|webtoon)$/;
        if (typePattern.test(type)) return request(`/content/type/${type}`);
        return request(`/${type}/${value}`);
      }
    };
  })();

  const UI = {
    renderCard: (item) => {
      const lang = Utils.getLangPrefix();
      const href = `/${lang}/${item.type}/${item.slug}`;
      return `
        <div class="card content-card skeleton-container">
          <a href="${href}" class="card-image-link">
            <img src="${item.cover_image || '/assets/img/covers/one-piece.jpg'}" class="card-img" alt="${item.title}" loading="lazy">
          </a>
          <div class="card-body">
            <h5 class="card-title text-truncate"><a href="${href}">${item.title}</a></h5>
            <div class="card-meta">
              <span class="badge badge-sm bg-primary">${item.type}</span>
              ${item.rating_avg ? `<span class="meta-item">⭐ ${item.rating_avg}</span>` : ''}
            </div>
          </div>
        </div>
      `;
    },
    renderBlogRow: (item) => {
      const lang = Utils.getLangPrefix();
      return `
        <a href="/${lang}/blogs/${item.slug}" class="blog-row-item">
          <div class="blog-row-content">
            <div class="blog-row-title">${item.title}</div>
            <div class="blog-row-meta">@${item.author_username} • ${Utils.humanDate(item.created_at)}</div>
          </div>
        </a>
      `;
    },
    renderComment: (item) => {
      const score = (Number(item.upvote_count || 0) - Number(item.downvote_count || 0));
      const myVote = Number(item.my_vote || 0);
      return `
        <div class="comment-item" data-comment-id="${item.id}">
          <div class="comment-header">
            <img src="${item.user_image || '/assets/img/default-profile.png'}" class="comment-avatar">
            <div class="comment-user-info">
              <div class="comment-author">@${item.username}</div>
              <div class="comment-date">${Utils.humanDate(item.created_at)}</div>
            </div>
          </div>
          <div class="comment-body markdown-body">${Utils.parseMarkdown(item.body)}</div>
          <div class="comment-actions">
            <button class="btn btn-sm btn-none vote-btn ${myVote === 1 ? 'active' : ''}" data-vote="1">👍</button>
            <span class="vote-count">${score}</span>
            <button class="btn btn-sm btn-none vote-btn ${myVote === -1 ? 'active' : ''}" data-vote="-1">👎</button>
          </div>
        </div>
      `;
    },
    showPopup: (msg, type = 'success') => {
      const $popup = $('#mainPopup');
      if (!$popup.length) return;
      $popup.find('#popupMessage').text(msg);
      $popup.find('#popupIcon').text(type === 'success' ? '✅' : '❌');
      $popup.removeClass('hidden').addClass('show');
      setTimeout(() => $popup.removeClass('show').addClass('hidden'), 3000);
    }
  };

  const Modules = {
    Home: {
      init: async function() {
        if (!$('#homeApp').length) return;
        try {
          const res = await Connection.getHome();
          const d = res.data || {};
          
          if (d.explore) $('#homeExploreGrid').html(d.explore.map(UI.renderCard).join(''));
          if (d.recent_chapters) $('#homeUpdatedGrid').html(d.recent_chapters.map(UI.renderCard).join(''));
          if (d.recently_added) $('#homeAddedGrid').html(d.recently_added.map(UI.renderCard).join(''));
          
          if (d.popular_blogs) $('#popularBlogsList').html(d.popular_blogs.map(UI.renderBlogRow).join(''));
          if (d.recent_blogs) $('#latestBlogsList').html(d.recent_blogs.map(UI.renderBlogRow).join(''));
        } catch (e) { console.error(e); }
      }
    },
    Content: {
      init: function() {
        if (!$('#contentDetailTarget').length) return;
        const lang = Utils.getLangPrefix();
        const parts = window.location.pathname.replace(`/${lang}`, '').split('/').filter(Boolean);
        const type = parts[0], slug = parts[1];
        if (!type || !slug) return;

        this.loadChapters(type, slug);
        this.loadComments(type, slug);
        this.bindActions(type, slug);
        
        // Description Markdown
        const $desc = $('#contentDescription');
        if ($desc.length) $desc.html(Utils.parseMarkdown($desc.text()));
      },
      loadChapters: async function(type, slug) {
        const $target = $('#chapterListTarget');
        if (!$target.length) return;
        try {
          const res = await Connection.getChapters(type, slug);
          const chapters = res.data || [];
          if (!chapters.length) { $target.html('<div class="card p-4 text-center text-muted">No chapters found</div>'); return; }
          
          const lang = Utils.getLangPrefix();
          const html = chapters.map(ch => {
            const num = String(ch.chapter_number).replace(/\.?0+$/, '');
            const href = `/${lang}/${type}/${slug}/chapter/${encodeURIComponent(num)}`;
            return `
              <div class="chapter-row card mb-2 p-3">
                <div class="flex justify-between items-center">
                  <div class="chapter-info">
                    <a href="${href}" class="chapter-title font-bold">Chapter ${num}${ch.title ? ': ' + ch.title : ''}</a>
                    <div class="text-xs text-muted">@${ch.username || 'admin'} • ${Utils.humanDate(ch.created_at)}</div>
                  </div>
                  <a href="${href}" class="btn btn-sm btn-primary px-4">Read</a>
                </div>
              </div>
            `;
          }).join('');
          $target.html(`<div class="card p-0 shadow-sm overflow-hidden mb-4"><div class="card-header bg-transparent border-bottom px-4 py-3 font-bold">📖 Chapter List</div><div class="p-3 bg-surface">${html}</div></div>`);
        } catch (e) { $target.html('<div class="alert alert-danger">Error loading chapters</div>'); }
      },
      loadComments: async function(type, slug) {
        const $list = $('#contentCommentsList');
        if (!$list.length) return;
        try {
          const res = await Connection.getSeriesComments(type, slug);
          const comments = res.data || [];
          $('#commentsBadgeCount').text(comments.length);
          if (!comments.length) { $list.html('<div class="text-center py-5 text-muted">No comments yet. Be the first!</div>'); return; }
          $list.html(comments.map(UI.renderComment).join(''));
        } catch (e) { $list.html('<div class="text-center py-4 text-danger">Error loading comments</div>'); }
      },
      bindActions: function(type, slug) {
        // Follow
        $('#followBtn').on('click', async function() {
          const isActive = $(this).text().includes('Unfollow') || $(this).text().includes('Çık');
          try {
            await Connection.followSeries(type, slug, isActive);
            UI.showPopup(isActive ? 'Removed from library' : 'Added to library');
            location.reload();
          } catch (e) { UI.showPopup(e.message, 'error'); }
        });

        // Rate
        $('.rate-opt').on('click', async function() {
          const val = $(this).data('val');
          try {
            await Connection.rateSeries(type, slug, val);
            UI.showPopup('Rating saved');
          } catch (e) { UI.showPopup(e.message, 'error'); }
        });

        // Comment Submit
        $('#contentCommentForm').on('submit', async function(e) {
          e.preventDefault();
          const body = $('#contentCommentInput').val().trim();
          if (!body) return;
          try {
            await Connection.postSeriesComment(type, slug, body);
            $('#contentCommentInput').val('');
            UI.showPopup('Comment posted');
            Modules.Content.loadComments(type, slug);
          } catch (e) { UI.showPopup(e.message, 'error'); }
        });

        // Comment Vote
        $('#contentCommentsList').on('click', '.vote-btn', async function() {
          const id = $(this).closest('.comment-item').data('comment-id');
          const vote = $(this).data('vote');
          try {
            await Connection.voteComment(id, vote);
            Modules.Content.loadComments(type, slug);
          } catch (e) { UI.showPopup(e.message, 'error'); }
        });

        // Preview
        $('#contentCommentInput').on('input', function() {
          const val = $(this).val();
          $('#commentPreview').html(val ? Utils.parseMarkdown(val) : '<span class="text-muted italic">Preview will appear here...</span>');
        });
      }
    },
    Listing: {
      init: async function() {
        if (!$('#listingApp').length) return;
        const $grid = $('#listingGrid');
        const $loading = $('#listingLoading');
        
        const path = window.location.pathname.split('/').filter(Boolean);
        const lang = (path[0] === 'tr' || path[0] === 'en') ? path[0] : '';
        const offset = lang ? 1 : 0;
        const lType = path[offset], lValue = path[offset+1];

        try {
          const res = await Connection.getListing(lType, lValue);
          const items = res.data || [];
          $loading.addClass('hidden');
          $('#listingApp').removeClass('hidden');
          if (!items.length) { $grid.html('<div class="text-center py-5 text-muted">No items found</div>'); return; }
          $grid.addClass('grid grid-4 gap-4').html(items.map(UI.renderCard).join(''));
        } catch (e) { $loading.html(`<div class="text-danger">Error: ${e.message}</div>`); }
      }
    },
    Search: {
      init: async function() {
        if (!$('#searchApp').length) return;
        const q = new URLSearchParams(window.location.search).get('q');
        if (!q) { $('#searchLoading').html('<div class="text-muted">Enter search term</div>'); return; }
        
        try {
          const res = await Connection.getSearch(q);
          const items = res.data || [];
          $('#searchLoading').addClass('hidden');
          $('#searchApp').removeClass('hidden');
          $('#searchTitle').text(`Results for: "${q}"`);
          if (!items.length) { $('#searchResultsGrid').html('<div class="text-center py-5 text-muted">No results found</div>'); return; }
          $('#searchResultsGrid').addClass('grid grid-4 gap-4').html(items.map(UI.renderCard).join(''));
        } catch (e) { $('#searchLoading').html(`<div class="text-danger">Error: ${e.message}</div>`); }
      }
    },
    Reader: {
      init: function() {
        console.log("[App] Initializing Reader...");
        const path = window.location.pathname.split('/').filter(Boolean);
        const lang = (path[0] === 'tr' || path[0] === 'en') ? path[0] : '';
        const offset = lang ? 1 : 0;
        const meltOffset = path[offset] === 'melt' ? 1 : 0;
        const type = path[offset + meltOffset], slug = path[offset + meltOffset + 1], num = path[offset + meltOffset + 3];

        Connection.getChapters(type, slug).then(res => {
          const chapters = (res.data || []).sort((a,b) => parseFloat(a.chapter_number) - parseFloat(b.chapter_number));
          const select = $('#chapterSelect');
          if (!select.length) return;
          select.empty();
          chapters.forEach(ch => {
            const n = String(ch.chapter_number).replace(/\.?0+$/, '');
            select.append(`<option value="${n}" ${n === num ? 'selected' : ''}>${window.NMR.__t('chapter')} ${n}</option>`);
          });
          
          select.on('change', function() {
            const val = $(this).val();
            const meltSegment = meltOffset ? 'melt/' : '';
            window.history.replaceState({}, '', `/${lang ? lang+'/' : ''}${meltSegment}${type}/${slug}/chapter/${val}`);
            location.reload();
          });

          Connection.getChapterDetail(type, slug, num).then(res => {
            const d = res.data;
            const layout = Utils.getCookie('melt_reader_layout') || ctx.auth?.preferences?.reader?.layout || 'vertical';
            const fit = Utils.getCookie('melt_reader_imageFit') || ctx.auth?.preferences?.reader?.imageFit || 'width';
            
            if (d.type === 'image') {
              $('#mangaView').removeClass('hidden').addClass(`fit-${fit}`);
              const pages = d.pages || [];
              const $cont = $('#mangaView .manga-pages');
              $cont.empty();
              pages.forEach(p => {
                const img = document.createElement('img'); img.src = p.image_path;
                img.className = 'manga-page-img mb-2'; $cont.append(img);
              });
            } else {
              $('#novelView').removeClass('hidden');
              $('#novelView .novel-content').html(Utils.parseMarkdown(d.body || d.data));
            }
          });
        });
      }
    }
  };

  const Global = {
    init: function() {
      this.bindModals();
      this.setupSearch();
      this.setupSettings();
      this.loadAuthUI();
    },
    bindModals: function() {
      window.openModal = (id) => {
        $('.modal-overlay').removeClass('active');
        if (id === 'readerSettingsModal') {
          const r = ctx.auth?.preferences?.reader || {};
          $('#readerLayoutSelect').val(Utils.getCookie('melt_reader_layout') || r.layout || 'vertical');
        }
        $(`#${id}`).addClass('active');
      };
      window.closeModal = () => $('.modal-overlay').removeClass('active');
      $(document).on('click', '.modal-overlay', function(e) { if (e.target === this) window.closeModal(); });
    },
    setupSearch: function() {
      $('#globalSearchForm').on('submit', (e) => {
        e.preventDefault();
        const q = $('#globalSearchInput').val().trim();
        if (q.length >= 2) {
          const lang = Utils.getLangPrefix();
          location.href = `/${lang}/search?q=${encodeURIComponent(q)}`;
        }
      });

      // Suggest
      const $input = $('#globalSearchInput');
      const $target = $('#searchSuggestions');
      let timer;
      $input.on('input', () => {
        clearTimeout(timer);
        const q = $input.val().trim();
        if (q.length < 2) { $target.addClass('hidden'); return; }
        timer = setTimeout(async () => {
          try {
            const res = await Connection.getSuggest(q);
            const items = res.data || [];
            if (!items.length) { $target.addClass('hidden'); return; }
            const lang = Utils.getLangPrefix();
            $target.removeClass('hidden').html(items.map(i => `<a href="/${lang}/${i.type}/${i.slug}" class="dropdown-item">${i.title}</a>`).join(''));
          } catch(e) {}
        }, 200);
      });
    },
    setupSettings: function() {
      $('#saveAllSettingsBtn').on('click', async () => {
        const layout = $('#readerLayoutSelect').val();
        Utils.setCookie('melt_reader_layout', layout, 30);
        location.reload();
      });
    },
    loadAuthUI: function() {
      const $container = $('#headerAuthLinks');
      if (!$container.length) return;
      if (ctx.auth?.is_logged_in) {
        const lang = Utils.getLangPrefix();
        $container.html(`
          <div class="dropdown">
            <button class="nav-link dropdown-toggle btn-none">👤 ${ctx.auth.username}</button>
            <div class="dropdown-menu card p-2">
              <a href="/${lang}/profile" class="dropdown-item">Profile</a>
              ${ctx.auth.is_admin ? `<a href="/${lang}/admin" class="dropdown-item text-warning">Admin Panel</a>` : ''}
              <hr class="my-1 border-0 border-t opacity-10">
              <a href="/logout" class="dropdown-item text-danger">Logout</a>
            </div>
          </div>
        `);
      }
    }
  };

  const init = async () => {
    const lang = ctx.lang_code || 'tr';
    try {
      const res = await fetch(`/api/v1/i18n/${lang}`);
      const payload = await res.json();
      dictionary = payload.data || {};
    } catch(e) { dictionary = ctx.lang || {}; }

    window.NMR = Object.assign(window.NMR || {}, {
      __t: (key) => dictionary[key] || dictionary[`ui.${key}`] || key,
      getLangPrefix: Utils.getLangPrefix,
      parseMarkdown: Utils.parseMarkdown
    });

    Global.init();
    Modules.Home.init();
    Modules.Content.init();
    Modules.Listing.init();
    Modules.Search.init();

    const path = window.location.pathname;
    const langPrefix = (path.split('/')[1] === 'tr' || path.split('/')[1] === 'en') ? '/' + path.split('/')[1] : '';
    if (path.replace(langPrefix, '').includes('/chapter/')) Modules.Reader.init();
  };

  return { init, Modules, Utils, Connection, UI };

})(window.jQuery);

$(function() { App.init(); });
