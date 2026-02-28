/**
 * blog.js - Interactive Controller for the Blog Platform.
 *
 * This module manages:
 * - Blog Discovery: Fetches and renders a grid of approved blog posts.
 * - Post Details: Hydrates SSR content or AJAX-loads full posts with markdown.
 * - Voting: Handles upvotes/downvotes for both posts and individual comments.
 * - Social: Integrated comment system specifically for blog posts.
 */
$(function () {
  const ctx = window.__NMR_CONTEXT || {};
  const langPrefix = window.NMR.getLangPrefix();

  /**
   * Identifies the current blog slug from the URL.
   */
  const fromPath = () => {
    const parts = window.location.pathname.split('/').filter(Boolean);
    const offset = (parts[0] === 'tr' || parts[0] === 'en') ? 1 : 0;
    if (parts[offset] === 'blogs' && parts[offset + 1]) {
      return parts[offset + 1];
    }
    return '';
  };

  const slugFromRoute = ctx.slug || fromPath();

  /**
   * Utility to provide a fallback cover image for blog posts.
   */
  const withFallbackImage = (post) => {
    if (post.cover_image) return post.cover_image;
    return '/assets/img/covers/one-piece.jpg';
  };

  /**
   * Generates a short text excerpt for listing views.
   */
  const excerpt = (text) => {
    const raw = String(text || '');
    return raw.length > 220 ? `${raw.slice(0, 220)}...` : raw;
  };

  /**
   * Renders the main blog listing grid.
   * @param {Array} posts
   */
  const renderBlogList = (posts) => {
    const html = (posts || []).map((post) => {
      const date = String(post.approved_at || post.created_at || '').split(' ')[0];
      return `
          <div class="card p-0 overflow-hidden blog-post-card cursor-pointer" data-slug="${post.slug}">
            <div class="blog-post-img-wrapper">
              <img src="${withFallbackImage(post)}" onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';" class="blog-post-img" alt="${post.title}">
            </div>
            <div class="p-4">
              <div class="flex items-center gap-2 mb-2 text-xs font-bold uppercase tracking-wider text-muted">
                <span class="text-primary"><a href="/${langPrefix}/profile/${post.author_username || ''}" style="color:inherit; text-decoration:none;" onclick="event.stopPropagation();">@${post.author_username || '-'}</a></span>
                <span class="opacity-40">•</span>
                <span>${date}</span>
              </div>
              <h3 class="mb-2 text-xl font-bold">${post.title}</h3>
              <div class="text-muted text-sm line-clamp-3">${excerpt(post.body)}</div>
            </div>
          </div>
        `;
    }).join('');

    $('#blogGrid').html(html || `<div class="text-muted py-5 text-center w-100">${NMR.__t('no_blog_posts')}</div>`);

    $('.blog-post-card').click(function () {
      const slug = $(this).attr('data-slug');
      location.href = `/${langPrefix}/blogs/${slug}`;
    });
  };

  /**
   * Renders full details for a specific blog post.
   */
  const renderPostDetail = (post) => {
    const score = (Number(post.upvote_count) || 0) - (Number(post.downvote_count) || 0);
    const myVote = Number(post.my_vote) || 0;
    const date = String(post.approved_at || post.created_at || '').split(' ')[0];
    const image = withFallbackImage(post);

    const html = `
      <div class="blog-hero-wrapper">
        <div class="blog-hero-backdrop" style="background-image: url('${image}')"></div>
        <div class="blog-hero-overlay"></div>
        <div class="container blog-hero-content">
          <button class="blog-meta-pill btn-none mb-4 cursor-pointer hover-lift" id="backToBlog">← ${NMR.__t('back')}</button>
          <h1 class="blog-hero-title">${post.title}</h1>
          <div class="blog-hero-meta">
             <a href="/${langPrefix}/profile/${post.author_username || ''}" class="blog-meta-pill no-underline">
                 👤 @${post.author_username || '-'}
             </a>
             <div class="blog-meta-pill">
                 📅 ${date}
             </div>
             <div class="blog-meta-pill">
                 🔥 <span class="blog-score-val">${score}</span>
             </div>
          </div>
        </div>
      </div>

      <div class="container blog-post-container" data-slug="${post.slug}">
        <!-- Floating Vote for Desktop -->
        <div class="blog-vote-floating">
          <button class="btn-none blog-vote-btn upvote ${myVote === 1 ? 'text-primary' : ''}" data-vote="1" style="font-size:1.5rem">▲</button>
          <span class="font-bold blog-score-val">${score}</span>
          <button class="btn-none blog-vote-btn downvote ${myVote === -1 ? 'text-danger' : ''}" data-vote="-1" style="font-size:1.5rem">▼</button>
        </div>

        <div class="blog-content-card">
          <div class="blog-content-main markdown-body">
            ${NMR.parseMarkdown(post.body || '')}
          </div>
          
          <div class="mt-5 pt-5 border-t">
            <div id="blogCommentsArea">
              <h3 class="mb-4">💬 ${NMR.__t('comments')} <span id="blogCommentCount" class="badge bg-primary ml-2"></span></h3>
              
              <form id="blogCommentForm" class="mb-5 bg-surface-elevated p-4 rounded-xl border">
                <div class="flex flex-col gap-3">
                  <textarea id="blogCommentInput" class="form-item border-none focus-ring" placeholder="${NMR.__t('comments')}..." rows="4"></textarea>
                  <div class="text-xs text-muted font-bold uppercase tracking-wider">👁️ ${NMR.__t('preview')}</div>
                  <div id="blogCommentPreview" class="form-item bg-surface overflow-auto markdown-body p-3 min-h-80 border-dashed opacity-80">
                    <span class="text-muted italic">${NMR.__t('preview_will_appear')}</span>
                  </div>
                  <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-full">${NMR.__t('post_comment')}</button>
                  </div>
                </div>
              </form>
              
              <div id="blogCommentsList" class="flex flex-col gap-5">
                <div class="text-center py-5">
                    <div class="spinner-border animate-spin text-primary"></div>
                    <div class="mt-2 text-muted">${NMR.__t('loading')}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    $('#blogPostContent').html(html);
    $('#blogListArea').hide();
    $('#blogDetailArea').removeClass('hidden').fadeIn();
    window.scrollTo(0, 0);
    loadBlogComments(post.slug);
  };

  /**
   * Fetches comments for a specific blog post.
   */
  const loadBlogComments = async (slug) => {
    try {
      const res = await Connection.getBlogComments(slug);
      renderBlogComments(res.data || []);
    } catch (err) {
      $('#blogCommentsList').html(`<div class="text-danger">${err.message}</div>`);
    }
  };

  /**
   * Renders the social comment list for a blog post.
   */
  const renderBlogComments = (comments) => {
    $('#blogCommentCount').text(`(${comments.length})`);
    const html = comments.map(c => {
      const score = (Number(c.upvote_count) || 0) - (Number(c.downvote_count) || 0);
      const myVote = Number(c.my_vote) || 0;
      return `
        <div class="comment flex gap-3 pb-3 border-bottom" data-id="${c.id}" data-user-id="${c.user_id}">
          <div class="flex flex-col items-center gap-1" style="min-width:40px">
            <button class="btn-none blog-comment-vote-btn upvote ${myVote === 1 ? 'text-primary' : ''}" data-vote="1">▲</button>
            <span class="text-xs font-bold score-val">${score}</span>
            <button class="btn-none blog-comment-vote-btn downvote ${myVote === -1 ? 'text-danger' : ''}" data-vote="-1">▼</button>
          </div>
          <div class="flex-grow">
            <div class="flex justify-between items-center mb-1">
              <strong class="text-sm"><a href="/${langPrefix}/profile/${c.username || ''}" style="color:inherit; text-decoration:none;">@${c.username || 'User'}</a></strong>
              <span class="text-xs text-muted">${c.created_at || ''}</span>
            </div>
            <div class="text-sm text-muted leading-relaxed markdown-body">${NMR.parseMarkdown(c.body || '')}</div>
          </div>
        </div>
      `;
    }).join('');

    $('#blogCommentsList').html(html || `<div class="py-4 text-muted text-center">${NMR.__t('no_popular_posts')}</div>`);
  };

  // --- Global Event Delegation ---

  // Blog Voting logic.
  $('body').on('click', '.blog-vote-btn', async function () {
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
    const btn = $(this);
    const slug = btn.closest('.card').attr('data-slug');
    const vote = parseInt(btn.attr('data-vote'));
    try {
      const res = await Connection.voteBlog(slug, vote);
      const parent = btn.parent();
      parent.find('.blog-score-val').text(Number(res.data.upvote_count) - Number(res.data.downvote_count));
      parent.find('.blog-vote-btn').removeClass('text-primary text-danger');
      if (res.data.my_vote === 1) parent.find('.upvote').addClass('text-primary');
      if (res.data.my_vote === -1) parent.find('.downvote').addClass('text-danger');
    } catch (err) { showPopup(err.message, 'error'); }
  });

  // Blog Comment Voting logic.
  $('body').on('click', '.blog-comment-vote-btn', async function () {
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
    const btn = $(this);
    const commentEl = btn.closest('.comment');
    const commentId = commentEl.attr('data-id');
    const commentUserId = commentEl.attr('data-user-id');
    const vote = parseInt(btn.attr('data-vote'));
    const slug = ctx.slug || fromPath();

    const currentUserId = window.__NMR_CONTEXT?.auth?.user_id || null;
    if (currentUserId && String(commentUserId) === String(currentUserId)) {
      return showPopup(NMR.__t('msg_vote_self_error'), 'error');
    }

    try {
      const res = await Connection.voteBlogComment(slug, commentId, vote);
      commentEl.find('.score-val').text(Number(res.data.upvote_count) - Number(res.data.downvote_count));
      commentEl.find('.blog-comment-vote-btn').removeClass('text-primary text-danger');
      if (res.data.my_vote === 1) commentEl.find('.upvote').addClass('text-primary');
      if (res.data.my_vote === -1) commentEl.find('.downvote').addClass('text-danger');
    } catch (err) { showPopup(err.message, 'error'); }
  });

  // Comment Posting logic.
  $('body').on('submit', '#blogCommentForm', async function (e) {
    e.preventDefault();
    if (!window.NMR.currentUser) return showPopup(NMR.__t('msg_login_required'), 'info');
    const slug = ctx.slug || fromPath();
    const body = $('#blogCommentInput').val().trim();
    if (!body) return;
    try {
      await Connection.postBlogComment(slug, body);
      $('#blogCommentInput').val('');
      $('#blogCommentPreview').html(`<span class="text-muted italic">${NMR.__t('preview_will_appear')}</span>`);
      showPopup(NMR.__t('msg_comment_posted'), 'success');
      loadBlogComments(slug);
    } catch (err) { showPopup(err.message, 'error'); }
  });

  // Back button logic.
  $('body').on('click', '#backToBlog', function () {
    location.href = `/${langPrefix}/blogs`;
  });

  // Main Execution
  if (slugFromRoute) {
    const ssrBody = $('#blogDetailArea .markdown-body');
    const isSSRMatch = ssrBody.elements.length > 0 && ssrBody.text().trim().length > 0;

    if (isSSRMatch) {
      const raw = ssrBody.html();
      if (raw && !raw.includes('<p>')) { ssrBody.html(NMR.parseMarkdown(raw)); }
      $('#blogLoading').hide();
      loadBlogComments(slugFromRoute);
    } else {
      Connection.getBlog(slugFromRoute)
        .then((res) => { renderPostDetail(res.data); $('#blogLoading').hide(); });
    }
    return;
  }

  Connection.getBlogs().then((res) => {
    renderBlogList(res.data);
    $('#blogLoading').hide();
    $('#blogListArea').removeClass('hidden').fadeIn();
  });
});
