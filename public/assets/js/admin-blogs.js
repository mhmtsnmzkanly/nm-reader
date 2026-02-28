/**
 * admin-blogs.js - specialized logic for Administrative Blog Management.
 *
 * This module manages:
 * - Approval Workflow: Lists and manages pending user blog posts.
 * - Global Moderation: Provides visibility into all approved posts with hide/delete options.
 * - Social Integration: Links authors to their public profiles.
 * - Action Orchestration: Centralized handler for approval, hiding, and removal.
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

  const loadBlogs = async () => {
    try {
      const res = await api('/admin/blogs/pending');
      const items = res.data || [];
      setHtml('#pending-blogs-body', items.map(b => `
        <tr>
          <td>${b.id}</td>
          <td>${b.title}</td>
          <td><a href="/${window.NMR.getLangPrefix()}/profile/${b.author_username}" style="color:inherit; text-decoration:none;" class="fw-bold">@${b.author_username}</a></td>
          <td><span class="badge bg-warning">Pending</span></td>
          <td><button class="btn btn-xs btn-success" data-action="approve" data-id="${b.id}">Approve</button></td>
        </tr>
      `).join('') || '<tr><td colspan="5" class="text-center">No pending blogs</td></tr>');
    } catch (e) {
      setHtml('#pending-blogs-body', `<tr><td colspan="5" class="text-center text-danger">${e.message}</td></tr>`);
    }
  };

  const loadAllBlogs = async () => {
    try {
      const res = await api('/admin/blogs');
      const items = res.data || [];
      setHtml('#all-blogs-body', items.map(b => `
        <tr>
          <td>${b.id}</td>
          <td>${b.title}</td>
          <td><a href="/${window.NMR.getLangPrefix()}/profile/${b.author_username}" style="color:inherit; text-decoration:none;" class="fw-bold">@${b.author_username}</a></td>
          <td>${b.approved ? 'Yes' : 'No'}</td>
          <td>${(b.created_at || '').split(' ')[0]}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-success" data-action="approve" data-id="${b.id}">Approve</button>
              <button class="btn btn-outline-secondary" data-action="hide" data-id="${b.id}">Hide</button>
              <button class="btn btn-outline-danger" data-action="delete" data-id="${b.id}">Delete</button>
            </div>
          </td>
        </tr>
      `).join('') || '<tr><td colspan="6" class="text-center">No blogs found</td></tr>');
    } catch (e) {
      setHtml('#all-blogs-body', `<tr><td colspan="6" class="text-center text-danger">${e.message}</td></tr>`);
    }
  };

  const action = async (id, kind) => {
    if (kind === 'approve') await api(`/admin/blogs/${id}/approve`, { method: 'POST', body: '{}' });
    if (kind === 'hide') await api(`/admin/blogs/${id}/hide`, { method: 'POST', body: '{}' });
    if (kind === 'delete') {
      if (!confirm('Delete blog?')) return;
      await api(`/admin/blogs/${id}`, { method: 'DELETE' });
    }
    await loadBlogs();
    await loadAllBlogs();
  };

  const init = () => {
    loadBlogs();
    loadAllBlogs();
    $('#btn-refresh-blogs')?.addEventListener('click', loadBlogs);
    $('#btn-refresh-blogs-all')?.addEventListener('click', loadAllBlogs);
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const id = btn.dataset.id;
      const kind = btn.dataset.action;
      action(id, kind);
    });
  };

  document.addEventListener('DOMContentLoaded', init);
})();
