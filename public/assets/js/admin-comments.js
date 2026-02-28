/**
 * admin-comments.js - specialized logic for Administrative Comment Moderation.
 *
 * This module manages:
 * - Global Moderation: Fetches and renders all user comments across the platform.
 * - Contextual UI: Displays comments with links to user profiles and parent content.
 * - Interaction: Permanent deletion of inappropriate or flagged comments.
 */
(() => {
  const ctx = window.__NMR_CONTEXT || {};
  const csrfToken = (ctx.auth && ctx.auth.csrf_token) || sessionStorage.getItem('csrf_token') || null;

  const api = async (path, options = {}) => {
    const method = (options.method || 'GET').toUpperCase();
    const headers = Object.assign({}, options.headers || {});
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';
    if (csrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(method)) headers['X-CSRF-Token'] = csrfToken;

    const res = await fetch(`/api/v1${path}`, { method, credentials: 'include', headers, body: options.body });
    const payload = await res.json().catch(() => ({ status: 'error', error: { message: 'Invalid API response' } }));
    if (!res.ok || payload.status === 'error') throw new Error(payload?.error?.message || `HTTP ${res.status}`);
    return payload;
  };

  const $ = (sel) => document.querySelector(sel);
  const setHtml = (sel, html) => { const el = $(sel); if (el) el.innerHTML = html; };

  const loadComments = async () => {
    const body = $('#comments-list-body');
    if (!body) return;
    try {
      const res = await api('/admin/comments');
      const items = res.data || [];
      setHtml('#comments-list-body', items.map(c => `
        <tr>
          <td><a href="/${window.NMR.getLangPrefix()}/profile/${c.username}" style="color:inherit; text-decoration:none;" class="fw-bold">@${c.username}</a></td>
          <td><div class="text-truncate" style="max-width: 260px;" title="${c.body}">${c.body}</div></td>
          <td><small>${c.content_title || c.blog_title || (c.chapter_number ? 'Chapter ' + c.chapter_number : 'N/A')}</small></td>
          <td><small class="text-muted">${(c.created_at || '').split(' ')[0]}</small></td>
          <td>
            <button class="btn btn-xs btn-outline-danger" data-id="${c.id}" data-action="delete"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      `).join('') || '<tr><td colspan="5" class="text-center">No comments found</td></tr>');
    } catch (e) {
      setHtml('#comments-list-body', `<tr><td colspan=\"5\" class=\"text-center text-danger\">${e.message}</td></tr>`);
    }
  };

  const deleteComment = async (id) => {
    if (!confirm('Delete this comment permanently?')) return;
    await api(`/admin/comments/${id}`, { method: 'DELETE' });
    await loadComments();
  };

  const init = () => {
    loadComments();
    $('#btn-refresh-comments')?.addEventListener('click', loadComments);
    $('#comments-list-body')?.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action=\"delete\"]');
      if (btn) deleteComment(btn.dataset.id);
    });
  };

  document.addEventListener('DOMContentLoaded', init);
})();
