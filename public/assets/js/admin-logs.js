/**
 * admin-logs.js - Administrative Logging and Audit Dashboard.
 *
 * This module manages:
 * - HTTP Audit Logs: Tracks method, path, and user identity for platform requests.
 * - Login Events: Monitors successful and failed authentication attempts with IP/UA data.
 * - Server Access Logs: Visualizes raw web server access records.
 * - Error Logs: Displays critical application and PHP error reports.
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

  const loadAuditLogs = async () => {
    try {
      const res = await api('/admin/audit-logs');
      const items = res.data || [];
      setHtml('#logs-body', items.map(l => `
        <tr>
          <td><small>${l.created_at.split(' ')[1]}</small></td>
          <td><span class="badge ${l.status_code >= 400 ? 'bg-danger' : 'bg-secondary'}">${l.method}</span></td>
          <td class="text-truncate" style="max-width:150px" title="${l.path}">${l.path}</td>
          <td><small title="${l.ip_hash}">${l.ip_hash.slice(0,12)}...</small></td>
          <td><small class="${l.username ? 'fw-bold' : 'text-muted'}" title="ID: ${l.user_id || 'N/A'}">
            ${l.username ? `<a href="/${window.NMR.getLangPrefix()}/profile/${l.username}" style="color:inherit; text-decoration:none;">${l.username}</a>` : 'guest'}
          </small></td>
        </tr>
      `).join('') || '<tr><td colspan="5" class="text-center">No audit logs</td></tr>');
    } catch (e) { setHtml('#logs-body', `<tr><td colspan="5" class="text-center text-danger">${e.message}</td></tr>`); }
  };

  const loadLoginEvents = async () => {
    try {
      const res = await api('/admin/login-events');
      const items = res.data || [];
      setHtml('#logins-body', items.map(l => `
        <tr>
          <td class="text-truncate" style="max-width:120px">${l.email}</td>
          <td><small>${l.ip_hash.slice(0,8)}</small></td>
          <td title="${l.user_agent}"><i class="bi bi-info-circle"></i></td>
          <td><i class="bi ${l.success ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'}"></i></td>
          <td><small>${l.attempted_at.split(' ')[1]}</small></td>
        </tr>
      `).join('') || '<tr><td colspan="5" class="text-center">No events</td></tr>');
    } catch (e) { setHtml('#logins-body', `<tr><td colspan="5" class="text-center text-danger">${e.message}</td></tr>`); }
  };

  const renderAccessLogCard = (l) => `
    <div class="card mb-2 shadow-none border-left-3" style="border-left: 4px solid var(--bs-primary); border-radius: 8px;">
      <div class="card-body p-2 px-3">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <span class="badge bg-primary text-uppercase" style="font-size: 0.7rem;">${l.method}</span>
          <small class="text-muted font-monospace" style="font-size: 0.75rem;">${l.date}</small>
        </div>
        <div class="mb-1 text-dark fw-bold text-truncate" title="${l.path}">${l.path}</div>
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted"><i class="bi bi-pc-display me-1"></i> ${l.ip}</small>
          <span class="badge ${l.status >= 400 ? 'bg-warning' : 'bg-light text-dark'} border">${l.status}</span>
        </div>
      </div>
    </div>
  `;

  const renderErrorLogCard = (l) => `
    <div class="card mb-2 shadow-none border-left-3" style="border-left: 4px solid var(--bs-danger); border-radius: 8px;">
      <div class="card-body p-2 px-3">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <span class="badge bg-danger text-uppercase" style="font-size: 0.7rem;">${l.level}</span>
          <small class="text-muted font-monospace" style="font-size: 0.75rem;">${l.date}</small>
        </div>
        <div class="text-danger small font-monospace" style="word-break: break-all;">${l.message}</div>
      </div>
    </div>
  `;

  const loadAccessLogs = async () => {
    const container = $('#access-logs-container');
    if (!container) return;
    try {
      const res = await api('/admin/logs/access?limit=50');
      const items = res.data || [];
      const html = items.map(renderAccessLogCard).join('') || '<div class="text-center py-4 text-muted">No access logs found.</div>';
      container.innerHTML = html;
    } catch (e) { container.innerHTML = `<div class="alert alert-danger p-2 small">${e.message}</div>`; }
  };

  const loadErrorLogs = async () => {
    const container = $('#error-logs-container');
    if (!container) return;
    try {
      const res = await api('/admin/logs/error?limit=50');
      const items = res.data || [];
      const html = items.map(renderErrorLogCard).join('') || '<div class="text-center py-4 text-muted">No errors found.</div>';
      container.innerHTML = html;
    } catch (e) { container.innerHTML = `<div class="alert alert-danger p-2 small">${e.message}</div>`; }
  };

  const init = () => {
    loadAuditLogs();
    loadLoginEvents();
    loadAccessLogs();
    loadErrorLogs();

    $('#btn-refresh-logs')?.addEventListener('click', loadAuditLogs);
    $('#btn-refresh-logins')?.addEventListener('click', loadLoginEvents);
    $('#btn-refresh-access')?.addEventListener('click', loadAccessLogs);
    $('#btn-refresh-error')?.addEventListener('click', loadErrorLogs);
  };

  document.addEventListener('DOMContentLoaded', init);
})();
