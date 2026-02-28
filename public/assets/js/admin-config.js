/**
 * admin-config.js - Secure .env management for ROOT_USER.
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
  const container = $('#env-inputs-container');

  const createRow = (key = '', value = '') => {
    const div = document.createElement('div');
    div.className = 'col-md-6 mb-3 env-row p-2 rounded';
    div.innerHTML = `
      <div class="input-group input-group-sm">
        <input type="text" class="form-control fw-bold border-danger-subtle env-key" value="${key}" placeholder="VARIABLE_NAME" style="max-width: 40%;">
        <input type="text" class="form-control env-value" value="${value}" placeholder="Value">
        <button class="btn btn-outline-danger btn-remove" type="button"><i class="bi bi-x"></i></button>
      </div>
    `;
    div.querySelector('.btn-remove').addEventListener('click', () => div.remove());
    return div;
  };

  const loadEnv = async () => {
    if (!container) return;
    try {
      const res = await api('/admin/maintenance/env');
      container.innerHTML = '';
      const data = res.data || {};
      Object.entries(data).forEach(([k, v]) => {
        container.appendChild(createRow(k, v));
      });
      if (Object.keys(data).length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted">.env file is empty or unreadable.</div>';
      }
    } catch (e) {
      container.innerHTML = `<div class="col-12 alert alert-danger">${e.message}</div>`;
    }
  };

  const saveEnv = async (e) => {
    e.preventDefault();
    const btn = $('#btn-save-env');
    const originalText = btn.innerHTML;
    
    if (!confirm('Are you absolutely sure? This will overwrite the system configuration and may require a restart.')) {
      return;
    }

    const payload = {};
    document.querySelectorAll('.env-row').forEach(row => {
      const k = row.querySelector('.env-key').value.trim();
      const v = row.querySelector('.env-value').value;
      if (k !== '') payload[k] = v;
    });

    try {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
      await api('/admin/maintenance/env', { method: 'POST', body: JSON.stringify(payload) });
      if (typeof showPopup === 'function') showPopup('Configuration saved successfully!', 'success');
      loadEnv();
    } catch (e) {
      alert('Save failed: ' + e.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  };

  const init = () => {
    loadEnv();
    $('#form-env-config')?.addEventListener('submit', saveEnv);
    $('#btn-reload-env')?.addEventListener('click', loadEnv);
    $('#btn-add-var')?.addEventListener('click', () => {
      container.appendChild(createRow());
    });
  };

  document.addEventListener('DOMContentLoaded', init);
})();
