/**
 * admin.js - Comprehensive Controller for the Administrative Dashboard.
 *
 * This module manages:
 * - Admin API Wrapper: Secure 'api' function with automatic CSRF injection.
 * - Dashboard KPIs: Real-time system performance metrics.
 * - Interactive Charts: Data visualization for traffic, content, and blogs.
 * - Management Loaders: Populates tables for contents, users, blogs, and logs.
 * - Legacy Support: Bridges old metrics APIs into the new dashboard.
 */
(() => {
  const ctx = window.__NMR_CONTEXT || {};
  const csrfToken = (ctx.auth && ctx.auth.csrf_token) || sessionStorage.getItem('csrf_token') || null;

  /**
   * Secure Fetch wrapper for administrative API calls.
   */
  const api = async (path, options = {}) => {
    const method = (options.method || 'GET').toUpperCase();
    const headers = Object.assign({}, options.headers || {});
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';
    if (csrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(method)) headers['X-CSRF-Token'] = csrfToken;

    try {
      const res = await fetch(`/api/v1${path}`, { method, credentials: 'include', headers, body: options.body });
      const text = await res.text();
      let payload;
      try { payload = text ? JSON.parse(text) : { status: 'error', error: { message: 'Empty response' } }; }
      catch { payload = { status: 'error', error: { message: text || 'Invalid API response' } }; }
      if (!res.ok || payload.status === 'error') throw new Error(payload?.error?.message || `HTTP ${res.status}`);
      return payload;
    } catch (e) {
      console.error(`API Error [${path}]:`, e);
      throw e;
    }
  };

  const $ = (sel) => document.querySelector(sel);
  const setHtml = (sel, html) => { const el = $(sel); if (el) el.innerHTML = html; };
  const setText = (sel, val) => { const el = $(sel); if (el) el.textContent = String(val); };

  /** --- Global State --- **/
  let _ALL_GENRES = [];
  let _ALL_TAGS = [];
  const _CHARTS = {};

  const toNumber = (value) => { const n = Number(value); return Number.isFinite(n) ? n : 0; };

  const chartCtx = (id) => {
    const canvas = document.getElementById(id);
    if (!canvas || typeof canvas.getContext !== 'function') return null;
    return canvas.getContext('2d');
  };

  const destroyChart = (id) => {
    if (_CHARTS[id]) {
      _CHARTS[id].destroy();
      delete _CHARTS[id];
    }
  };

  /** --- Chart Rendering Helpers --- **/

  const renderBarChart = (id, title, labels, values, color, horizontal = false) => {
    if (typeof Chart === 'undefined') return;
    const ctx2d = chartCtx(id);
    if (!ctx2d) return;
    destroyChart(id);

    _CHARTS[id] = new Chart(ctx2d, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: title,
          data: values,
          backgroundColor: color,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  };

  const renderLineChart = (id, datasets, labels) => {
    if (typeof Chart === 'undefined') return;
    const ctx2d = chartCtx(id);
    if (!ctx2d) return;
    destroyChart(id);

    _CHARTS[id] = new Chart(ctx2d, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  };

  /** --- Data Loaders --- **/

  const loadOverview = async () => {
    try {
      const res = await api('/admin/overview');
      const data = res.data || {};
      const kpis = data.kpis || {};
      const m = data.metrics || {};

      setText('#kpi-users', kpis.users_total || 0);
      setText('#kpi-contents', kpis.contents_total || 0);
      setText('#kpi-chapters', kpis.chapters_total || 0);
      setText('#kpi-unread', kpis.blogs_pending_total || 0);

      const topContents = m.top_contents_7d || [];
      setHtml('#metrics-top-contents', topContents.map(c => `
        <tr>
          <td><a href="/${String(c.type || 'novel').replace(/_/g, '-')}/${c.slug}" class="text-decoration-none" target="_blank">${c.title}</a></td>
          <td><small class="badge bg-light text-dark">${c.type}</small></td>
          <td class="text-end fw-bold">${c.view_count_7d}</td>
          <td class="text-end text-muted">${c.comment_count_7d}</td>
        </tr>
      `).join('') || '<tr><td colspan="4" class="text-center">No data</td></tr>');

      // Health & Funnel
      const funnel = m.funnel || {};
      const health = m.performance_slo || {};
      setHtml('#metrics-funnel-health', `
        <div class="mb-2 small">Home-to-Content: ${funnel.home_to_content_pct}%</div>
        <div class="mb-2 small">Content-to-Chapter: ${funnel.content_to_chapter_pct}%</div>
        <hr class="my-2 opacity-10">
        <div class="small">Error Rate: ${health.server_error_rate_pct_24h}%</div>
        <div class="small">P95 Latency: ${health.p95_duration_ms_24h}ms</div>
      `);
    } catch (e) { console.error('Overview load error:', e); }
  };

  const loadViewStats = async () => {
    try {
      const res = await api('/admin/stats/views');
      const s = res.data || {};

      const prep = (arr, key) => ({ labels: (arr || []).map(x => x.name || x.title || x.type || x.slug || 'N/A'), values: (arr || []).map(x => toNumber(x[key])) });

      const tags = prep(s.series_tags, 'view_total');
      renderBarChart('chartTopTags', 'Views', tags.labels, tags.values, 'rgba(13,110,253,0.6)');

      const genres = prep(s.series_genres, 'view_total');
      renderBarChart('chartTopGenres', 'Views', genres.labels, genres.values, 'rgba(25,135,84,0.6)');

      const types = prep(s.types, 'view_total');
      renderBarChart('chartTopTypes', 'Views', types.labels, types.values, 'rgba(255,193,7,0.6)');

      const contents = prep(s.series, 'view_total');
      renderBarChart('chartTopContents', 'Views', contents.labels, contents.values, 'rgba(13,110,253,0.6)', true);

      const chapters = prep(s.chapters, 'view_total');
      const chapLabels = (s.chapters || []).map(x => `${x.content_slug} #${x.chapter_number}`);
      renderBarChart('chartTopChapters', 'Views', chapLabels, chapters.values, 'rgba(33,37,41,0.6)', true);
    } catch (e) { console.error('View stats error:', e); }
  };

  const loadBlogStats = async () => {
    try {
      const res = await api('/admin/stats/blogs');
      const s = res.data || {};
      const sum = s.summary || {};

      setText('#blog-stat-total', sum.total || 0);
      setText('#blog-stat-visible', sum.visible_total || 0);
      setText('#blog-stat-hidden', sum.hidden_total || 0);
      setText('#blog-stat-deleted', sum.deleted_total || 0);

      const createdMap = new Map((s.daily_created || []).map(x => [x.day, x.total]));
      const approvedMap = new Map((s.daily_approved || []).map(x => [x.day, x.total]));
      const labels = Array.from(new Set([...createdMap.keys(), ...approvedMap.keys()])).sort();

      renderLineChart('chartBlogDaily', [
        { label: 'Created', data: labels.map(l => createdMap.get(l) || 0), borderColor: '#0d6efd', fill: false },
        { label: 'Approved', data: labels.map(l => approvedMap.get(l) || 0), borderColor: '#198754', fill: false }
      ], labels);

      const authors = s.top_authors || [];
      renderBarChart('chartBlogAuthors', 'Blogs', authors.map(a => a.username), authors.map(a => a.blog_total), 'rgba(13,202,240,0.6)', true);
    } catch (e) { console.error('Blog stats error:', e); }
  };

  const fetchLegacyMetrics = async (path) => {
    try {
      const res = await api(path);
      const data = res.data || {};
      setHtml('#legacy-metrics-output', JSON.stringify(data, null, 2));

      if (path === '/admin/metrics') {
        const kHtml = Object.entries(data).filter(([k]) => typeof data[k] !== 'object').map(([k, v]) => `<div class="small"><b>${k}:</b> ${v}</div>`).join('');
        setHtml('#legacy-kpis', kHtml || 'No data');

        const top = data.top_contents_7d || [];
        renderBarChart('chartLegacyTopContents', 'Views', top.map(x => x.slug), top.map(x => x.view_count_7d), 'rgba(13,110,253,0.5)');
      }
    } catch (e) { setHtml('#legacy-metrics-output', `Error: ${e.message}`); }
  };

  const loadContents = async () => {
    if (!$('#contents-list-body')) return;
    try {
      const res = await api('/admin/series');
      const items = res.data || [];
      window._NMR_CONTENTS = items;
      setHtml('#contents-list-body', items.map(c => `<tr><td>${c.id}</td><td>${c.type}</td><td>${c.title}</td><td><code>${c.slug}</code></td><td>${c.status}</td><td><button class="btn btn-xs btn-info" onclick="NMR_ADMIN.editContent('${c.id}')">Edit</button></td></tr>`).join(''));
    } catch (e) { }
  };

  const loadTaxonomy = async () => {
    if (!$('#genres-list-body')) return;
    try {
      const g = await api('/series_genres');
      setHtml('#genres-list-body', (g.data || []).map(x => `<tr><td>${x.id}</td><td>${x.name}</td></tr>`).join(''));
      const t = await api('/series_tags');
      setHtml('#tags-list-body', (t.data || []).map(x => `<tr><td>${x.id}</td><td>${x.name}</td></tr>`).join(''));
    } catch (e) { }
  };

  const loadSiteVisits = async () => {
    try {
      const res = await api('/admin/stats/visits');
      const d = res.data || {};
      setText('#visits-daily', d.daily || 0);
      setText('#visits-weekly', d.weekly || 0);
      setText('#visits-monthly', d.monthly || 0);
    } catch (e) { }
  };

  const loadReputation = async () => {
    if (!$('#reputation-body')) return;
    try {
      const res = await api('/admin/stats/reputation');
      const items = res.data || [];
      setHtml('#reputation-body', items.map(u => `
        <tr>
          <td><a href="/${window.NMR.getLangPrefix()}/profile/${u.username}" class="fw-bold text-decoration-none">${u.username}</a></td>
          <td class="text-end fw-bold text-primary">${Number(u.score).toFixed(1)}</td>
          <td class="text-end">${u.comment_count}</td>
          <td class="text-end">${u.votes_given}</td>
          <td class="text-end text-success">${u.up_votes}</td>
          <td class="text-end text-danger">${u.down_votes}</td>
          <td class="text-end text-muted small"><i class="bi bi-clock-history me-1"></i>${window.NMR.formatDuration(u.total_seconds)}</td>
        </tr>
      `).join('') || '<tr><td colspan="7" class="text-center">No data</td></tr>');
    } catch (e) {
      console.error('Reputation load error:', e);
      setHtml('#reputation-body', `<tr><td colspan="7" class="text-center text-danger">${e.message}</td></tr>`);
    }
  };

  const loadQueue = async () => {
    if (!$('#queue-jobs-list')) return;
    try {
      const res = await api('/admin/queue/jobs');
      const items = res.data || [];
      setHtml('#queue-jobs-list', items.map(j => `<div>[${j.id}] ${j.job_type || j.task} - <span class="text-info">${j.status}</span></div>`).join('') || 'Empty');
    } catch (e) {
      console.error('Queue load error:', e);
      setHtml('#queue-jobs-list', `<div class="text-danger">${e.message}</div>`);
    }
  };

  const runQueueOnce = async () => {
    const limit = Math.max(1, Math.min(100, parseInt($('#jobs-limit')?.value || '5', 10)));
    try {
      await api('/admin/queue/run-once', { method: 'POST', body: JSON.stringify({ limit }) });
      loadQueue();
    } catch (e) { alert(e.message); }
  };

  const loadAuditLogs = async () => {
    if (!$('#audit-logs-body')) return;
    try {
      const res = await api('/admin/audit-logs?per_page=10');
      const items = res.data || [];
      setHtml('#audit-logs-body', items.map(l => `
        <tr>
          <td><small class="${l.username ? 'fw-bold' : 'text-muted'}">${l.username || 'guest'}</small></td>
          <td><span class="badge ${l.status_code >= 400 ? 'bg-danger' : 'bg-secondary'}">${l.method}</span></td>
          <td class="text-truncate" style="max-width:150px" title="${l.path}">${l.path}</td>
          <td><small>${l.created_at.split(' ')[1]}</small></td>
        </tr>
      `).join('') || '<tr><td colspan="4" class="text-center">No audit logs</td></tr>');
    } catch (e) { console.error('Audit logs load error:', e); }
  };

  const loadLoginEvents = async () => {
    if (!$('#login-logs-body')) return;
    try {
      const res = await api('/admin/login-events?per_page=10');
      const items = res.data || [];
      setHtml('#login-logs-body', items.map(l => `
        <tr>
          <td class="text-truncate" style="max-width:120px">${l.email}</td>
          <td><small>${l.ip_hash.slice(0,8)}</small></td>
          <td><i class="bi ${l.success ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'}"></i></td>
          <td><small>${l.attempted_at.split(' ')[1]}</small></td>
        </tr>
      `).join('') || '<tr><td colspan="4" class="text-center">No events</td></tr>');
    } catch (e) { console.error('Login events load error:', e); }
  };

  const loadModActions = async () => {
    if (!$('#mod-actions-body')) return;
    try {
      const res = await api('/admin/moderation-actions?per_page=10');
      const items = res.data || [];
      setHtml('#mod-actions-body', items.map(l => `
        <tr>
          <td><small class="fw-bold">${l.username || 'system'}</small></td>
          <td><span class="badge bg-info">${l.action}</span></td>
          <td><small>${l.target_type}:${l.target_id}</small></td>
          <td class="text-truncate" style="max-width:150px" title="${l.reason || ''}">${l.reason || '-'}</td>
        </tr>
      `).join('') || '<tr><td colspan="4" class="text-center">No moderation actions</td></tr>');
    } catch (e) { console.error('Mod actions load error:', e); }
  };

  /** --- Namespace Export --- **/
  window.NMR_ADMIN = {
    approveBlog: async (id) => { try { await api(`/admin/blogs/${id}/approve`, { method: 'POST', body: '{}' }); location.reload(); } catch (e) { alert(e.message); } },
    promptCreateTaxonomy: async (type) => {
      const name = prompt(`New ${type} name:`);
      if (!name) return;
      try { await api(`/admin/series_${type}s`, { method: 'POST', body: JSON.stringify({ name }) }); loadTaxonomy(); } catch (e) { alert(e.message); }
    },
    editContent: (id) => {
      const c = (window._NMR_CONTENTS || []).find(x => x.id == id);
      if (!c) return;
      $('#edit-content-id').value = c.id;
      $('#edit-content-title').value = c.title;
      new bootstrap.Modal($('#modal-edit-content')).show();
    }
  };

  const init = () => {
    loadOverview();
    loadViewStats();
    loadBlogStats();
    loadContents();
    loadTaxonomy();
    loadSiteVisits();
    loadReputation();
    loadAuditLogs();
    loadLoginEvents();
    loadModActions();
    loadQueue();
    fetchLegacyMetrics('/admin/metrics');

    $('#btn-metrics-dashboard')?.addEventListener('click', () => fetchLegacyMetrics('/admin/dashboard'));
    $('#btn-metrics-snapshot')?.addEventListener('click', () => fetchLegacyMetrics('/admin/metrics'));
    $('#btn-metrics-insights')?.addEventListener('click', () => fetchLegacyMetrics('/admin/metrics/insights'));
    $('#btn-refresh-reputation')?.addEventListener('click', loadReputation);
    $('#btn-run-jobs')?.addEventListener('click', runQueueOnce);

    $('#form-create-mod-action')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const body = Object.fromEntries(fd.entries());
      try {
        await api('/admin/moderation-actions', { method: 'POST', body: JSON.stringify(body) });
        e.target.reset();
        loadModActions();
      } catch (err) { alert(err.message); }
    });
  };

  document.addEventListener('DOMContentLoaded', init);
})();