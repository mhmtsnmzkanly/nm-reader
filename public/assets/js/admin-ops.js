/**
 * admin-ops.js - Administrative Operations and Configuration Dashboard.
 *
 * This module manages:
 * - Background Tasks: Provides UI to manually trigger job queue and cleanup retention.
 * - Platform Monitoring: Real-time visibility into the job queue status.
 * - Global Configuration: Fetches and updates site-wide settings (SEO, Integrations, Defaults).
 * - Maintenance: Handles legacy job and cleanup triggers for backward compatibility.
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
  const setValue = (id, value) => { const el = $(`#${id}`); if (el) el.value = value ?? ''; };
  const setChecked = (id, value) => { const el = $(`#${id}`); if (el) el.checked = !!value; };
  const status = (text, isError = false) => setHtml('#site-settings-status', `<span class=\"${isError ? 'text-danger' : 'text-success'}\">${text}</span>`);

  const loadQueue = async () => {
    try {
      const res = await api('/admin/queue/jobs');
      setHtml('#queue-jobs-list', (res.data || []).map(j => `<div>[${j.id}] ${j.job_type || j.task} - <span class="text-info">${j.status}</span></div>`).join('') || 'Empty');
    } catch (e) {
      setHtml('#queue-jobs-list', `<div class="text-danger">${e.message}</div>`);
    }
  };

  const runQueueOnce = async () => {
    const limit = Math.max(1, Math.min(100, parseInt($('#jobs-limit')?.value || '5', 10)));
    await api('/admin/queue/run-once', { method: 'POST', body: JSON.stringify({ limit }) });
    loadQueue();
  };

  const runLegacyJobs = async () => {
    const limit = Math.max(1, Math.min(100, parseInt($('#jobs-limit')?.value || '5', 10)));
    try { await api('/admin/jobs/run-once', { method: 'POST', body: JSON.stringify({ limit }) }); alert('Legacy jobs executed'); } catch (e) { alert(e.message); }
  };

  const runCleanup = async () => {
    await api('/admin/retention/cleanup', { method: 'POST', body: '{}' });
    alert('Cleanup done');
  };

  const runLegacyCleanup = async () => {
    await api('/admin/maintenance/cleanup', { method: 'POST', body: '{}' });
    alert('Legacy cleanup triggered');
  };

  const init = () => {
    loadQueue();
    $('#btn-run-jobs')?.addEventListener('click', runQueueOnce);
    $('#btn-run-jobs-legacy')?.addEventListener('click', runLegacyJobs);
    $('#btn-run-cleanup')?.addEventListener('click', runCleanup);
    $('#btn-run-cleanup-legacy')?.addEventListener('click', runLegacyCleanup);

    const handleMaintenance = async (btnId, path, successMsg) => {
      const btn = $(`#${btnId}`);
      const output = $('#maintenance-output');
      if (!btn || !output) return;

      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
      output.classList.remove('d-none');
      output.innerHTML = 'Starting operation...\n';

      try {
        const res = await api(path, { method: 'POST', body: '{}' });
        const data = res.data || {};
        const consoleOutput = Array.isArray(data.output) ? data.output.join('\n') : String(data.output || '');
        output.innerHTML += consoleOutput;
        if (data.success) {
          if (typeof showPopup === 'function') showPopup(successMsg, 'success');
        } else {
          output.innerHTML += '\nOperation failed.';
        }
      } catch (e) {
        output.innerHTML += `\nERROR: ${e.message}`;
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    };

    $('#btn-trigger-backup')?.addEventListener('click', () => 
      handleMaintenance('btn-trigger-backup', '/admin/maintenance/backup', 'System backup completed successfully!')
    );

    $('#btn-trigger-analytics')?.addEventListener('click', () => 
      handleMaintenance('btn-trigger-analytics', '/admin/maintenance/analytics', 'Daily analytics aggregated successfully!')
    );

    $('#btn-trigger-sitemap')?.addEventListener('click', () => 
      handleMaintenance('btn-trigger-sitemap', '/admin/maintenance/sitemap', 'Physical sitemap.xml updated!')
    );

    $('#btn-trigger-warmup')?.addEventListener('click', () => 
      handleMaintenance('btn-trigger-warmup', '/admin/maintenance/warmup', 'System cache warmed up!')
    );
  };

  document.addEventListener('DOMContentLoaded', init);
})();
