(function($) {
  'use strict';

  const ctx = window.__NMR_CONTEXT || {};
  const AppApi = window.App || {};
  const request = AppApi.Connection ? AppApi.Connection.request : async () => ({ data: [] });
  const parseMarkdown = AppApi.Utils ? AppApi.Utils.parseMarkdown : (text) => text || '';
  const getLang = () => ctx.lang_code || 'tr';
  const isLoggedIn = () => Boolean(ctx.auth && ctx.auth.is_logged_in);
  const isMeltPath = () => window.location.pathname.split('/').filter(Boolean).includes('melt');
  const withMeltPrefix = (path) => {
    const clean = String(path || '').replace(/^\/+/, '');
    return `/${getLang()}/melt${clean ? `/${clean}` : ''}`;
  };
  const showPopup = (message, type) => {
    const popup = document.getElementById('mainPopup');
    const icon = document.getElementById('popupIcon');
    const body = document.getElementById('popupMessage');
    if (!popup || !body) return;
    body.textContent = message;
    icon.textContent = type === 'success' ? 'OK' : '!';
    popup.classList.remove('hidden');
    popup.classList.add('show');
    window.setTimeout(() => popup.classList.remove('show'), 2200);
  };

  window.showPopup = showPopup;

  const humanDate = (value) => {
    if (!value) return '';
    const dt = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return value;
    return dt.toLocaleDateString(getLang() === 'tr' ? 'tr-TR' : 'en-US', { day: 'numeric', month: 'short', year: 'numeric' });
  };

  const ensureAuth = () => {
    if (isLoggedIn()) return true;
    if (typeof window.openModal === 'function') window.openModal('loginModal');
    return false;
  };

  const commentCard = (item, blogMode) => {
    const score = (Number(item.upvote_count || 0) - Number(item.downvote_count || 0));
    const upActive = Number(item.my_vote || 0) === 1 ? ' btn-primary' : '';
    const downActive = Number(item.my_vote || 0) === -1 ? ' btn-primary' : '';
    return `
      <article class="melt-comment-card" data-comment-id="${item.id}">
        <div class="melt-comment-card__head">
          <strong>@${item.username || 'reader'}</strong>
          <span>${humanDate(item.created_at)}</span>
        </div>
        <div class="melt-comment-card__body markdown-body">${parseMarkdown(item.body || '')}</div>
        <div class="melt-comment-card__actions">
          <div class="flex items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline${upActive}" data-comment-vote="1"${blogMode ? ' data-blog-vote="1"' : ''}>+</button>
            <span>${score}</span>
            <button type="button" class="btn btn-sm btn-outline${downActive}" data-comment-vote="-1"${blogMode ? ' data-blog-vote="-1"' : ''}>-</button>
          </div>
        </div>
      </article>
    `;
  };

  const bindPreview = (inputSelector, previewSelector) => {
    const $input = $(inputSelector);
    const $preview = $(previewSelector);
    if (!$input.length || !$preview.length) return;
    $input.on('input', function() {
      const value = $(this).val().trim();
      $preview.html(value ? parseMarkdown(value) : '<span>Preview will appear here...</span>');
    });
  };

  const Suggestions = {
    timer: null,
    init() {
      const $input = $('#globalSearchInput');
      const $target = $('#searchSuggestions');
      if (!$input.length || !$target.length) return;
      $input.on('input', () => {
        window.clearTimeout(this.timer);
        const q = $input.val().trim();
        if (q.length < 2) {
          $target.addClass('hidden').empty();
          return;
        }
        this.timer = window.setTimeout(async () => {
          try {
            const res = await fetch(`/api/v1/search/suggest?q=${encodeURIComponent(q)}`);
            const payload = await res.json();
            const items = payload.data || [];
            if (!items.length) {
              $target.removeClass('hidden').html('<div class="p-3 text-muted">No suggestions</div>');
              return;
            }
            $target.removeClass('hidden').html(items.map((item) => {
              const href = withMeltPrefix(`/${item.type_path || item.type}/${item.slug}`);
              return `<a class="dropdown-item" href="${href}">${item.title}</a>`;
            }).join(''));
          } catch (err) {
            $target.addClass('hidden').empty();
          }
        }, 180);
      });
      $(document).on('click', (event) => {
        if (!$(event.target).closest('#globalSearchForm').length) $target.addClass('hidden');
      });
    }
  };

  const MobileNav = {
    init() {
      const $panel = $('#meltMobilePanel');
      $('#meltMobileToggle').on('click', () => $panel.toggleClass('is-open'));
      $('#meltMobileClose').on('click', () => $panel.removeClass('is-open'));
      $('#meltMobileSearchForm').on('submit', function(event) {
        event.preventDefault();
        const q = $('#meltMobileSearchInput').val().trim();
        if (q.length >= 2) window.location.href = `${withMeltPrefix('/search')}?q=${encodeURIComponent(q)}`;
      });
    }
  };

  const Hero = {
    init() {
      const rail = document.getElementById('meltHeroRail');
      const backdrop = document.querySelector('.melt-hero__backdrop');
      if (!rail || !backdrop) return;
      rail.querySelectorAll('[data-hero-card]').forEach((card) => {
        card.addEventListener('mouseenter', () => {
          rail.querySelectorAll('[data-hero-card]').forEach((node) => node.classList.remove('is-active'));
          card.classList.add('is-active');
          const img = card.querySelector('img');
          if (img) backdrop.style.backgroundImage = `url('${img.getAttribute('src')}')`;
        });
      });
    }
  };

  const ContentPage = {
    init() {
      const root = document.getElementById('meltContentPage');
      if (!root) return;
      const type = root.dataset.type;
      const slug = root.dataset.slug;
      bindPreview('#contentCommentInput', '#commentPreview');
      this.loadChapters(type, slug);
      this.loadComments(type, slug);
      this.bindFollow(type, slug);
      this.bindRating(type, slug);
      this.bindUnlock(type, slug);
      const description = document.getElementById('contentDescription');
      if (description) description.innerHTML = parseMarkdown(description.textContent || '');
      $('#contentCommentForm').on('submit', (event) => this.submitComment(event, type, slug));
      $('#contentCommentsList').on('click', '[data-comment-vote]', (event) => this.voteComment(event));
    },
    async loadChapters(type, slug) {
      const target = document.getElementById('chapterListTarget');
      if (!target) return;
      try {
        const res = await request(`/content/${type}/${slug}/chapters`);
        const items = res.data || [];
        target.innerHTML = items.map((chapter) => {
          const href = withMeltPrefix(`/${type}/${slug}/chapter/${encodeURIComponent(chapter.chapter_number)}`);
          const state = chapter.is_locked ? `<button type="button" class="btn btn-sm btn-outline" data-inline-unlock="${chapter.id}">Unlock ${Number(chapter.price_coin || 0)}c</button>` : `<a href="${href}" class="btn btn-sm btn-primary">Read</a>`;
          return `
            <article class="melt-comment-card">
              <div class="melt-comment-card__head">
                <strong>Chapter ${chapter.chapter_number}</strong>
                <span>${humanDate(chapter.created_at)}</span>
              </div>
              <div class="melt-comment-card__actions">
                <span>${chapter.title || ''}</span>
                ${state}
              </div>
            </article>
          `;
        }).join('');
        $(target).on('click', '[data-inline-unlock]', async (event) => {
          if (!ensureAuth()) return;
          const chapterId = $(event.currentTarget).data('inline-unlock');
          try {
            await request(`/chapter/${chapterId}/unlock`, { method: 'POST' });
            showPopup('Chapter unlocked', 'success');
            window.location.reload();
          } catch (err) {
            showPopup(err.message || 'Unlock failed', 'error');
          }
        });
      } catch (err) {
        target.innerHTML = '<div class="melt-loading-row">Chapter list could not be loaded.</div>';
      }
    },
    async loadComments(type, slug) {
      const target = document.getElementById('contentCommentsList');
      if (!target) return;
      try {
        const res = await fetch(`/api/v1/content/${type}/${slug}/comments`);
        const payload = await res.json();
        const items = payload.data || [];
        document.getElementById('commentsBadgeCount').textContent = String(items.length);
        target.innerHTML = items.length ? items.map((item) => commentCard(item, false)).join('') : '<div class="melt-loading-row">No comments yet.</div>';
      } catch (err) {
        target.innerHTML = '<div class="melt-loading-row">Comments could not be loaded.</div>';
      }
    },
    async submitComment(event, type, slug) {
      event.preventDefault();
      if (!ensureAuth()) return;
      const body = $('#contentCommentInput').val().trim();
      if (!body) return;
      try {
        await request(`/content/${type}/${slug}/comment`, { method: 'POST', body: JSON.stringify({ body }) });
        $('#contentCommentInput').val('');
        $('#commentPreview').html('<span>Preview will appear here...</span>');
        showPopup('Comment posted', 'success');
        this.loadComments(type, slug);
      } catch (err) {
        showPopup(err.message || 'Comment failed', 'error');
      }
    },
    bindFollow(type, slug) {
      $('#followBtn').on('click', async function() {
        if (!ensureAuth()) return;
        const isActive = $(this).data('active') === true || $(this).text().trim().toLowerCase().includes('unfollow') || $(this).text().trim().toLowerCase().includes('çık');
        try {
          await request(`/content/${type}/${slug}/follow`, { method: isActive ? 'DELETE' : 'POST' });
          showPopup(isActive ? 'Removed from library' : 'Added to library', 'success');
          window.location.reload();
        } catch (err) {
          showPopup(err.message || 'Follow action failed', 'error');
        }
      });
    },
    bindRating(type, slug) {
      $('.rate-opt').on('click', async function() {
        if (!ensureAuth()) return;
        try {
          await request(`/content/${type}/${slug}/rate`, { method: 'POST', body: JSON.stringify({ rating: Number($(this).data('val')) }) });
          showPopup('Rating saved', 'success');
        } catch (err) {
          showPopup(err.message || 'Rating failed', 'error');
        }
      });
    },
    bindUnlock(type, slug) {
      $('#meltUnlockSeriesBtn').on('click', async function() {
        if (!ensureAuth()) return;
        try {
          await request(`/content/${type}/${slug}/unlock`, { method: 'POST' });
          showPopup('Series unlocked', 'success');
          window.location.reload();
        } catch (err) {
          showPopup(err.message || 'Unlock failed', 'error');
        }
      });
    },
    async voteComment(event) {
      if (!ensureAuth()) return;
      const $button = $(event.currentTarget);
      const commentId = $button.closest('[data-comment-id]').data('comment-id');
      try {
        await request(`/comments/${commentId}/vote`, { method: 'POST', body: JSON.stringify({ vote: Number($button.data('comment-vote')) }) });
        const root = document.getElementById('meltContentPage');
        this.loadComments(root.dataset.type, root.dataset.slug);
      } catch (err) {
        showPopup(err.message || 'Vote failed', 'error');
      }
    }
  };

  const ChapterPage = {
    init() {
      const root = document.getElementById('readerApp');
      if (!root || !isMeltPath()) return;
      bindPreview('#readerCommentInput', '#commentPreview');
      this.loadComments(root.dataset.chapterId);
      $('#readerCommentForm').on('submit', (event) => this.submitComment(event, root.dataset.chapterId));
      $('#readerCommentsList').on('click', '[data-comment-vote]', (event) => this.voteComment(event, root.dataset.chapterId));
      $('#meltUnlockChapterBtn').on('click', async function() {
        if (!ensureAuth()) return;
        try {
          await request(`/chapter/${$(this).data('chapter-id')}/unlock`, { method: 'POST' });
          showPopup('Chapter unlocked', 'success');
          window.location.reload();
        } catch (err) {
          showPopup(err.message || 'Unlock failed', 'error');
        }
      });
    },
    async loadComments(chapterId) {
      const target = document.getElementById('readerCommentsList');
      if (!target || !chapterId) return;
      try {
        const res = await fetch(`/api/v1/chapter/${chapterId}/comments`);
        const payload = await res.json();
        const items = payload.data || [];
        target.innerHTML = items.length ? items.map((item) => commentCard(item, false)).join('') : '<div class="melt-loading-row">No comments yet.</div>';
      } catch (err) {
        target.innerHTML = '<div class="melt-loading-row">Comments could not be loaded.</div>';
      }
    },
    async submitComment(event, chapterId) {
      event.preventDefault();
      if (!ensureAuth()) return;
      const body = $('#readerCommentInput').val().trim();
      if (!body) return;
      try {
        await request(`/chapter/${chapterId}/comment`, { method: 'POST', body: JSON.stringify({ body }) });
        $('#readerCommentInput').val('');
        $('#commentPreview').html('<span>Preview will appear here...</span>');
        showPopup('Comment posted', 'success');
        this.loadComments(chapterId);
      } catch (err) {
        showPopup(err.message || 'Comment failed', 'error');
      }
    },
    async voteComment(event, chapterId) {
      if (!ensureAuth()) return;
      const $button = $(event.currentTarget);
      const commentId = $button.closest('[data-comment-id]').data('comment-id');
      try {
        await request(`/comments/${commentId}/vote`, { method: 'POST', body: JSON.stringify({ vote: Number($button.data('comment-vote')) }) });
        this.loadComments(chapterId);
      } catch (err) {
        showPopup(err.message || 'Vote failed', 'error');
      }
    }
  };

  $(function() {
    if (!isMeltPath()) return;
    MobileNav.init();
    Suggestions.init();
    Hero.init();
    ContentPage.init();
    ChapterPage.init();
  });
})(window.jQuery);
