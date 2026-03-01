/**
 * Content.js - Interactive Controller for Series Detail Pages.
 *
 * This module manages:
 * - Chapter Listing: Renders a scrollable list of chapters with deep links.
 * - Navigation: Automatically finds the first chapter for the "Start Reading" button.
 * - Comments: Handles fetching, rendering, and posting comments with markdown support.
 * - User Actions: Managing follows/unfollows and rating submissions.
 * - CSRF/Auth: Protects social actions by verifying authentication state.
 */
$(function() {
  const ctx = window.__NMR_CONTEXT || {};

  /**
   * Extracts content type and slug from the URL path.
   * @returns {{type: string, slug: string}}
   */
  const fromPath = () => {
    const parts = window.location.pathname.split('/').filter(Boolean);
    const offset = (parts[0] === 'tr' || parts[0] === 'en') ? 1 : 0;
    return {
      type: parts[offset] || '',
      slug: parts[offset + 1] || ''
    };
  };

  const pathState = fromPath();
  const type = ctx.type || pathState.type || 'manga';
  const slug = ctx.slug || pathState.slug || '';

  /**
   * Normalizes chapter numbers for display (e.g., "1.00" -> "1").
   */
  const normalizeChapterNumber = (value) => {
    const raw = String(value ?? '').trim();
    if (!/^-?\d+(?:\.\d+)?$/.test(raw)) return raw;
    if (!raw.includes('.')) return raw;
    return raw.replace(/\.?0+$/, '');
  };

  /**
   * Renders the chapter list card.
   * @param {Array} chapters
   */
  const renderChapters = (chapters) => {
    const safeChapters = chapters || [];
    const lang = window.NMR.getLangPrefix();
    const listHtml = safeChapters.map((ch) => `
      <div class="chapter-row d-flex justify-between items-center p-3 border-bottom hover-bg cursor-pointer"
           onclick="location.href='/${lang}/${type}/${slug}/chapter/${normalizeChapterNumber(ch.chapter_number)}'">
        <div class="flex flex-col">
          <strong>${NMR.__t('chapter')} ${normalizeChapterNumber(ch.chapter_number)}${ch.title ? `: ${ch.title}` : ''}</strong>
          <span class="text-xs text-muted">${ch.created_at || ''}</span>
        </div>
        <span class="text-xs text-muted">${NMR.__t('read')} »</span>
      </div>
    `).join('');

    const first = safeChapters[safeChapters.length - 1];
    const latest = safeChapters[0];

    const html = `
      <div class="card p-0 overflow-hidden">
        <div class="card-header border-bottom flex items-center justify-between p-3 bg-surface">
          <h3 class="m-0">${NMR.__t('chapters')}</h3>
          <div class="flex gap-2">
            <button class="btn btn-sm btn-outline" ${first ? `onclick="location.href='/${lang}/${type}/${slug}/chapter/${normalizeChapterNumber(first.chapter_number)}'"` : 'disabled'}>${NMR.__t('first')}</button>
            <button class="btn btn-sm btn-outline" ${latest ? `onclick="location.href='/${lang}/${type}/${slug}/chapter/${normalizeChapterNumber(latest.chapter_number)}'"` : 'disabled'}>${NMR.__t('latest')}</button>
          </div>
        </div>
        <div class="chapter-list-scroll scrollbar-5" style="max-height: 500px; overflow-y: auto;">
          ${listHtml || `<div class="p-3 text-muted">${NMR.__t('no_updates_yet')}</div>`}
        </div>
      </div>
    `;

    $('#chapterListTarget').html(html);
  };

  /**
   * Updates the "Start Reading" button to point to the actual first chapter.
   */
  const updateStartReadingLink = (chapters) => {
    const btn = $('#startReadingBtn');
    if (!btn.elements.length) return;

    const items = Array.isArray(chapters) ? [...chapters] : [];
    if (items.length === 0) {
      btn.addClass('disabled').attr('aria-disabled', 'true').attr('href', '#');
      return;
    }

    // Sort ascending to find the true beginning.
    items.sort((a, b) => {
      const na = Number.parseFloat(String(a.chapter_number).replace(',', '.'));
      const nb = Number.parseFloat(String(b.chapter_number).replace(',', '.'));
      return na - nb;
    });

    const first = items[0];
    btn.removeClass('disabled')
      .attr('aria-disabled', 'false')
      .attr('href', `/${type}/${slug}/chapter/${encodeURIComponent(normalizeChapterNumber(first.chapter_number))}`);
  };

  /**
   * Renders the social comment section.
   */
  const renderComments = (rows) => {
    const html = (rows || []).map((c) => {
      const score = (Number(c.upvote_count) || 0) - (Number(c.downvote_count) || 0);
      const myVote = Number(c.my_vote) || 0;
      return `
        <div class="comment flex gap-3 pb-3 border-bottom mb-3" data-id="${c.id}" data-user-id="${c.user_id}">
          <div class="flex flex-col items-center gap-1" style="min-width:40px">
            <button class="btn-none vote-btn upvote ${myVote === 1 ? 'text-primary' : ''}" data-vote="1">▲</button>
            <span class="text-xs font-bold score-val">${score}</span>
            <button class="btn-none vote-btn downvote ${myVote === -1 ? 'text-danger' : ''}" data-vote="-1">▼</button>
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

    const count = (rows || []).length;
    $('#contentCommentsList').html(html || `<div class="text-muted text-center py-4">${NMR.__t('no_popular_posts')}</div>`);
    $('#commentsBadgeCount').text(`(${count})`);
  };

  // --- UI Event Listeners ---

  // Live Markdown Preview for comments.
  $('#contentCommentInput').on('input', function(e) {
    const val = e.target.value;
    $('#commentPreview').html(val ? NMR.parseMarkdown(val) : '<span class="text-muted italic">...</span>');
  });

  // Comment Submission.
  $('#contentCommentForm').on('submit', async function(e) {
    e.preventDefault();
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
    
    const body = $('#contentCommentInput').val().trim();
    if (!body) return;

    try {
      await Connection.postContentComment(type, slug, body);
      $('#contentCommentInput').val('');
      $('#commentPreview').html('<span class="text-muted italic">...</span>');
      showPopup(NMR.__t('msg_comment_posted'), 'success');
      const commentsRes = await Connection.getContentComments(type, slug);
      renderComments(commentsRes.data);
    } catch (err) { showPopup(err.message, 'error'); }
  });

  // Vote Delegation.
  $('body').on('click', '.vote-btn', async function() {
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');

    const btn = $(this);
    const commentEl = btn.closest('.comment');
    const commentId = parseInt(commentEl.attr('data-id'));
    const commentUserId = commentEl.attr('data-user-id');
    const vote = parseInt(btn.attr('data-vote'));

    const currentUserId = window.__NMR_CONTEXT.auth ? window.__NMR_CONTEXT.auth.user_id : null;
    if (currentUserId && String(commentUserId) === String(currentUserId)) {
      return showPopup(NMR.__t('msg_vote_self_error'), 'error');
    }

    try {
      const res = await Connection.voteComment(commentId, vote);
      commentEl.find('.score-val').text(res.data.upvote_count - res.data.downvote_count);
      commentEl.find('.vote-btn').removeClass('text-primary text-danger');
      if (res.data.my_vote === 1) commentEl.find('.upvote').addClass('text-primary');
      if (res.data.my_vote === -1) commentEl.find('.downvote').addClass('text-danger');
    } catch (err) { showPopup(err.message, 'error'); }
  });

  /**
   * Initializes follow and rate buttons.
   */
  const initActions = () => {
    $('#followBtn').on('click', async function() {
      if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
      const isFollowing = $(this).hasClass('btn-secondary');
      try {
        if (isFollowing) {
          await Connection.unfollowContent(type, slug);
          $(this).removeClass('btn-secondary').addClass('btn-outline').text(`🤍 ${NMR.__t('follow')}`);
        } else {
          await Connection.followContent(type, slug);
          $(this).removeClass('btn-outline').addClass('btn-secondary').text(`💖 ${NMR.__t('following')}`);
        }
        showPopup(NMR.__t(isFollowing ? 'msg_removed_library' : 'msg_added_library'), 'success');
      } catch (err) { showPopup(err.message, 'error'); }
    });

    $('.rate-opt').on('click', async function() {
      if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
      try {
        await Connection.rateContent(type, slug, $(this).attr('data-val'));
        showPopup(NMR.__t('msg_rate_success'), 'success');
        location.reload();
      } catch (err) { showPopup(err.message, 'error'); }
    });
  };

  // Main Execution
  initActions();

  // Render description markdown
  const descEl = $('#contentDescription');
  if (descEl.elements.length && descEl.text().trim()) {
    descEl.html(NMR.parseMarkdown(descEl.text()));
  }

  Connection.getChapters(type, slug).then((res) => {
    const chapters = Array.isArray(res.data) ? res.data : [];
    renderChapters(chapters);
    updateStartReadingLink(chapters);
  });

  Connection.getContentComments(type, slug).then(c => renderComments(c.data));
});
