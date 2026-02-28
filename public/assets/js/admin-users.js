/**
 * admin-users.js - specialized logic for Administrative User Management.
 *
 * This module manages:
 * - User Discovery: Fetches and renders the primary user table with roles.
 * - Role Management: Dynamically loads and assigns system roles to accounts.
 * - Moderation: Provides UI for banning and updating user profile data.
 * - Form Handling: Securely submits updates to the User administrative API.
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
  const firstRole = (u) => {
    if (u.role_names) {
      const parts = u.role_names.split(',');
      return parts[0].trim();
    }
    if (u.role) return u.role;
    if (Array.isArray(u.roles) && u.roles.length > 0) return u.roles[0];
    if (typeof u.roles === 'string' && u.roles.trim() !== '') return u.roles.split(',')[0].trim();
    return 'user';
  };

  const formatDuration = (s) => {
    if (!s || s <= 0) return '0s';
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m`;
    return `${s}s`;
  };

  const loadUsers = async () => {
    const body = $('#users-list-body');
    if (!body) return;
    try {
      const res = await api('/admin/users');
      const items = res.data || [];
      window._NMR_USERS = items;
      setHtml('#users-list-body', items.map(u => `
        <tr>
          <td>${u.id}</td>
          <td><a href="/${window.NMR.getLangPrefix()}/profile/${u.username}" style="color:inherit; text-decoration:none;" class="fw-bold">${u.username}</a></td>
          <td>${u.email || ''}</td>
          <td><span class="badge bg-light text-dark">${firstRole(u)}</span></td>
          <td class="text-muted"><i class="bi bi-clock-history me-1"></i>${formatDuration(u.total_seconds)}</td>
          <td><button class="btn btn-xs btn-outline-secondary" data-action="edit" data-id="${u.id}"><i class="bi bi-person-gear"></i></button></td>
        </tr>
      `).join('') || '<tr><td colspan="6" class="text-center">No users</td></tr>');
    } catch (e) {
      setHtml('#users-list-body', `<tr><td colspan="6" class="text-center text-danger">${e.message}</td></tr>`);
    }
  };

  const loadRoles = async () => {
    const roleSelect = $('#edit-user-role');
    if (!roleSelect) return;
    try {
      const res = await api('/admin/rbac/roles');
      const items = res.data.items || res.data || [];
      roleSelect.innerHTML = items.map(r => `<option value="${r.slug}">${r.name || r.slug}</option>`).join('');
    } catch (e) {
      console.error('Failed to load roles:', e);
    }
  };

  const openEditModal = (id) => {
    const u = (window._NMR_USERS || []).find(x => x.id == id);
    if (!u) return;
    $('#edit-user-id').value = u.id;
    $('#edit-user-username').value = u.username;
    $('#edit-user-email').value = u.email || '';
    $('#edit-user-bio').value = u.bio || '';
    $('#edit-user-role').value = firstRole(u);
    $('#edit-user-banned').checked = !!u.is_banned;
    new bootstrap.Modal($('#modal-edit-user')).show();
  };

  const saveUser = async (fd) => {
    try {
      await api(`/admin/users/${fd.get('id')}`, {
        method: 'PUT',
        body: JSON.stringify({
          role: fd.get('role'),
          is_banned: !!fd.get('is_banned'),
          email: fd.get('email'),
          bio: fd.get('bio')
        })
      });
      bootstrap.Modal.getInstance($('#modal-edit-user')).hide();
      loadUsers();
    } catch (err) {
      alert(err.message);
    }
  };

  const init = () => {
    loadUsers();
    loadRoles();
    $('#btn-refresh-users')?.addEventListener('click', loadUsers);
    $('#users-list-body')?.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action=\"edit\"]');
      if (btn) openEditModal(btn.dataset.id);
    });
    $('#form-edit-user')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      await saveUser(new FormData(e.target));
    });
  };

  document.addEventListener('DOMContentLoaded', init);
})();
