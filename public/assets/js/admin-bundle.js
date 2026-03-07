/**
 * admin-bundle.js - Unified Administrative Controller for NovelMangaReader.
 *
 * This bundle consolidates all administrative logic into a single file with
 * page-specific routing to prevent execution conflicts.
 * Uses native openModal/closeModal system (no Bootstrap).
 */

window.AdminApp = (function() {
  const ctx = window.__NMR_CONTEXT || {};
  const csrfToken = (ctx.auth && ctx.auth.csrf_token) || sessionStorage.getItem('csrf_token') || null;

  /**
   * Central API Bridge for Admin Panel.
   */
  const api = async (path, options = {}) => {
    const method = (options.method || 'GET').toUpperCase();
    const headers = Object.assign({}, options.headers || {});
    if (options.body !== undefined && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }
    if (csrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
      headers['X-CSRF-Token'] = csrfToken;
    }

    try {
      const res = await fetch(`/api/v1${path}`, { method, credentials: 'include', headers, body: options.body });
      const text = await res.text();
      let payload;
      try {
        payload = text ? JSON.parse(text) : { status: 'success', data: {} };
      } catch {
        payload = { status: 'error', error: { message: text || 'Invalid API response' } };
      }
      if (!res.ok || payload.status === 'error') {
        throw new Error(payload?.error?.message || payload?.message || `HTTP ${res.status}`);
      }
      return payload;
    } catch (e) {
      console.error(`Admin API Error [${path}]:`, e);
      throw e;
    }
  };

  const $ = (sel) => document.querySelector(sel);
  const setHtml = (sel, html) => { const el = $(sel); if (el) el.innerHTML = html; };
  const setText = (sel, val) => { const el = $(sel); if (el) el.textContent = String(val); };

  // --- MODULES ---

  const Dashboard = {
    charts: {},
    init: function() {
      console.log("[AdminApp] Initializing Dashboard...");
      this.loadOverview();
      this.loadViewStats();
      this.loadBlogStats();
      this.loadSiteVisits();
      this.loadReputation();
      this.loadAuditLogs();
      this.loadLoginEvents();
      this.loadModActions();
      this.loadQueue();
      this.fetchLegacyMetrics('/admin/metrics');

      $('#btn-metrics-dashboard')?.addEventListener('click', () => this.fetchLegacyMetrics('/admin/dashboard'));
      $('#btn-metrics-snapshot')?.addEventListener('click', () => this.fetchLegacyMetrics('/admin/metrics'));
      $('#btn-metrics-insights')?.addEventListener('click', () => this.fetchLegacyMetrics('/admin/metrics/insights'));
      $('#btn-refresh-reputation')?.addEventListener('click', () => this.loadReputation());
      $('#btn-run-jobs')?.addEventListener('click', () => this.runQueueOnce());
    },
    loadOverview: async function() {
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
        const funnel = m.funnel || {};
        const health = m.performance_slo || {};
        setHtml('#metrics-funnel-health', `
          <div class="mb-2 small">Home-to-Content: ${funnel.home_to_content_pct}%</div>
          <div class="mb-2 small">Content-to-Chapter: ${funnel.content_to_chapter_pct}%</div>
          <hr class="my-2 opacity-10">
          <div class="small">Error Rate: ${health.server_error_rate_pct_24h}%</div>
          <div class="small">P95 Latency: ${health.p95_duration_ms_24h}ms</div>
        `);
        const rs = m.retention_search || {};
        setHtml('#metrics-retention-search', `
          <div class="mb-2 small">Searches (7d): ${rs.search_total_7d}</div>
          <div class="mb-2 small">Zero Results: ${rs.zero_result_pct_7d}%</div>
          <hr class="my-2 opacity-10">
          <div class="small">D1 Retention: ${rs.d1_retention_pct}%</div>
          <div class="small">New Users (7d): ${rs.new_users_7d}</div>
        `);
      } catch (e) {}
    },
    loadViewStats: async function() {
      try {
        const res = await api('/admin/stats/views');
        const s = res.data || {};
        const prep = (arr, key) => ({ labels: (arr || []).map(x => x.name || x.title || x.type || x.slug || 'N/A'), values: (arr || []).map(x => Number(x[key]) || 0) });
        this.renderBarChart('chartTopTags', 'Views', prep(s.series_tags, 'view_total'), 'rgba(13,110,253,0.6)');
        this.renderBarChart('chartTopGenres', 'Views', prep(s.series_genres, 'view_total'), 'rgba(25,135,84,0.6)');
        this.renderBarChart('chartTopTypes', 'Views', prep(s.types, 'view_total'), 'rgba(255,193,7,0.6)');
        this.renderBarChart('chartTopContents', 'Views', prep(s.series, 'view_total'), 'rgba(13,110,253,0.6)', true);
        const chapLabels = (s.chapters || []).map(x => `${x.content_slug} #${x.chapter_number}`);
        this.renderBarChart('chartTopChapters', 'Views', { labels: chapLabels, values: (s.chapters || []).map(x => Number(x.view_total) || 0) }, 'rgba(33,37,41,0.6)', true);
      } catch (e) {}
    },
    loadBlogStats: async function() {
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
        this.renderLineChart('chartBlogDaily', [
          { label: 'Created', data: labels.map(l => createdMap.get(l) || 0), borderColor: '#0d6efd', fill: false },
          { label: 'Approved', data: labels.map(l => approvedMap.get(l) || 0), borderColor: '#198754', fill: false }
        ], labels);
        const authors = s.top_authors || [];
        this.renderBarChart('chartBlogAuthors', 'Blogs', { labels: authors.map(a => a.username), values: authors.map(a => a.blog_total) }, 'rgba(13,202,240,0.6)', true);
      } catch (e) {}
    },
    loadSiteVisits: async function() {
      try {
        const res = await api('/admin/stats/visits');
        const d = res.data || {};
        setText('#visits-daily', d.daily || 0);
        setText('#visits-weekly', d.weekly || 0);
        setText('#visits-monthly', d.monthly || 0);
      } catch (e) {}
    },
    loadReputation: async function() {
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
            <td class="text-end text-muted small"><i class="bi bi-clock-history me-1"></i>${window.AdminApp.formatDuration(u.total_seconds)}</td>
          </tr>
        `).join('') || '<tr><td colspan="7" class="text-center">No data</td></tr>');
      } catch (e) {}
    },
    loadAuditLogs: async function() {
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
      } catch (e) {}
    },
    loadLoginEvents: async function() {
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
      } catch (e) {}
    },
    loadModActions: async function() {
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
      } catch (e) {}
    },
    loadQueue: async function() {
      if (!$('#queue-jobs-list')) return;
      try {
        const res = await api('/admin/queue/jobs');
        const items = res.data || [];
        setHtml('#queue-jobs-list', items.map(j => `<div>[${j.id}] ${j.job_type || j.task} - <span class="text-info">${j.status}</span></div>`).join('') || 'Empty');
      } catch (e) {}
    },
    runQueueOnce: async function() {
      const limit = Math.max(1, Math.min(100, parseInt($('#jobs-limit')?.value || '5', 10)));
      try {
        await api('/admin/queue/run-once', { method: 'POST', body: JSON.stringify({ limit }) });
        this.loadQueue();
      } catch (e) { alert(e.message); }
    },
    fetchLegacyMetrics: async function(path) {
      try {
        const res = await api(path);
        const data = res.data || {};
        setHtml('#legacy-metrics-output', JSON.stringify(data, null, 2));
        if (path === '/admin/metrics') {
          const kHtml = Object.entries(data).filter(([k]) => typeof data[k] !== 'object').map(([k, v]) => `<div class="small"><b>${k}:</b> ${v}</div>`).join('');
          setHtml('#legacy-kpis', kHtml || 'No data');
          const top = data.top_contents_7d || [];
          this.renderBarChart('chartLegacyTopContents', 'Views', { labels: top.map(x => x.slug), values: top.map(x => x.view_count_7d) }, 'rgba(13,110,253,0.5)');
        }
      } catch (e) { setHtml('#legacy-metrics-output', `Error: ${e.message}`); }
    },
    renderBarChart: function(id, title, data, color, horizontal = false) {
      if (typeof Chart === 'undefined') return;
      const canvas = document.getElementById(id);
      if (!canvas) return;
      if (this.charts[id]) this.charts[id].destroy();
      this.charts[id] = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: { labels: data.labels, datasets: [{ label: title, data: data.values, backgroundColor: color, borderRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, indexAxis: horizontal ? 'y' : 'x', plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
      });
    },
    renderLineChart: function(id, datasets, labels) {
      if (typeof Chart === 'undefined') return;
      const canvas = document.getElementById(id);
      if (!canvas) return;
      if (this.charts[id]) this.charts[id].destroy();
      this.charts[id] = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
      });
    }
  };

  const Blogs = {
    init: function() {
      console.log("[AdminApp] Initializing Blogs...");
      this.loadBlogs();
      this.loadAllBlogs();
      $('#btn-refresh-blogs')?.addEventListener('click', () => this.loadBlogs());
      $('#btn-refresh-blogs-all')?.addEventListener('click', () => this.loadAllBlogs());
      document.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn || !document.getElementById('pending-blogs-body')) return;
        const id = btn.dataset.id;
        const kind = btn.dataset.action;
        this.action(id, kind);
      });
    },
    loadBlogs: async function() {
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
      } catch (e) { setHtml('#pending-blogs-body', `<tr><td colspan="5" class="text-center text-danger">${e.message}</td></tr>`); }
    },
    loadAllBlogs: async function() {
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
      } catch (e) { setHtml('#all-blogs-body', `<tr><td colspan="6" class="text-center text-danger">${e.message}</td></tr>`); }
    },
    action: async function(id, kind) {
      try {
        if (kind === 'approve') await api(`/admin/blogs/${id}/approve`, { method: 'POST', body: '{}' });
        if (kind === 'hide') await api(`/admin/blogs/${id}/hide`, { method: 'POST', body: '{}' });
        if (kind === 'delete') { if (!confirm('Delete blog?')) return; await api(`/admin/blogs/${id}`, { method: 'DELETE' }); }
        this.loadBlogs();
        this.loadAllBlogs();
      } catch (e) { alert(e.message); }
    }
  };

  const Content = {
    _ALL_GENRES: [],
    _ALL_TAGS: [],
    _CONTENTS: [],
    _SELECTED_GENRES: new Set(),
    _SELECTED_TAGS: new Set(),
    _CREATE_GENRES: new Set(),
    _CREATE_TAGS: new Set(),

    init: function() {
      console.log("[AdminApp] Initializing Content...");
      this.loadContents();
      this.loadTaxonomy();
      $('#btn-refresh-contents')?.addEventListener('click', () => { this.loadContents(); this.loadTaxonomy(); });

      $('#contents-list-body')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const id = btn.dataset.id;
        const action = btn.dataset.action;
        const c = this._CONTENTS.find(x => x.id == id);
        if (!c) return;
        if (action === 'edit') this.openEditContent(id);
        if (action === 'chapter' || action === 'add-chapter') {
          const detail = { id: c.id, title: c.title, slug: c.slug, type: c.type };
          
          // Add to select box if missing
          const sel = $('#chapters-content-id');
          if (sel) {
            let opt = Array.from(sel.options).find(o => o.value == c.id);
            if (!opt) {
              opt = document.createElement('option');
              opt.value = c.id;
              opt.textContent = c.title;
              sel.appendChild(opt);
            }
            sel.value = c.id;
          }

          document.dispatchEvent(new CustomEvent('nmr:admin-content:selected', { detail }));
          if (action === 'add-chapter') document.dispatchEvent(new CustomEvent('nmr:admin-chapter:create', { detail }));
        }
      });

      // Taxonomy button events
      $('#edit-content-genres-btns')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action="toggle-genre"]');
        if (btn) this.toggleTax('genre', btn.dataset.id);
      });
      $('#edit-content-tags-btns')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action="toggle-tag"]');
        if (btn) this.toggleTax('tag', btn.dataset.id);
      });
      $('#create-content-genres-btns')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action="c-toggle-genre"]');
        if (btn) this.toggleCreateTax('genre', btn.dataset.id);
      });
      $('#create-content-tags-btns')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action="c-toggle-tag"]');
        if (btn) this.toggleCreateTax('tag', btn.dataset.id);
      });

      $('#form-create-content')?.addEventListener('submit', (e) => this.handleCreate(e));
      $('#form-edit-content')?.addEventListener('submit', (e) => this.handleEdit(e));

      // Slug Auto-fill
      const titleIn = $('#create-content-title');
      const slugIn = $('#create-content-slug');
      if (titleIn && slugIn) {
        let userEdited = false;
        slugIn.addEventListener('input', () => { userEdited = slugIn.value.trim() !== ''; });
        titleIn.addEventListener('input', () => { if (!userEdited) slugIn.value = this.slugify(titleIn.value); });
      }

      // Bulk Upload Helpers
      window.NMR_ADMIN_CONTENT = {
        promptCreateTaxonomy: (type) => this.promptCreateTaxonomy(type),
        uploadSpecificImage: (input, targetId, type) => this.uploadSpecificImage(input, targetId, type),
        handleBulkUpload: (input, type) => this.handleBulkUpload(input, type)
      };
    },
    loadContents: async function() {
      try {
        const res = await api('/admin/content');
        this._CONTENTS = res.data || [];
        setHtml('#contents-list-body', this._CONTENTS.map(c => `
          <tr>
            <td>${c.id}</td>
            <td><span class="badge bg-light text-dark">${c.type}</span></td>
            <td>${c.title}</td>
            <td><code>${c.slug}</code></td>
            <td>${c.status}</td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" data-action="edit" data-id="${c.id}"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-primary" data-action="chapter" data-id="${c.id}"><i class="bi bi-list-ul"></i></button>
                <button class="btn btn-outline-success" data-action="add-chapter" data-id="${c.id}"><i class="bi bi-plus-lg"></i></button>
              </div>
            </td>
          </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No contents found</td></tr>');
      } catch (e) { setHtml('#contents-list-body', `<tr><td colspan="6" class="text-center text-danger">${e.message}</td></tr>`); }
    },
    loadTaxonomy: async function() {
      try {
        const [g, t] = await Promise.all([api('/admin/genres'), api('/admin/tags')]);
        this._ALL_GENRES = g.data || [];
        this._ALL_TAGS = t.data || [];
        if ($('#genres-list-body')) setHtml('#genres-list-body', this._ALL_GENRES.map(x => `<tr><td style="width:40px">${x.id}</td><td>${x.name}</td></tr>`).join(''));
        if ($('#tags-list-body')) setHtml('#tags-list-body', this._ALL_TAGS.map(x => `<tr><td style="width:40px">${x.id}</td><td>${x.name}</td></tr>`).join(''));
        this.renderCreateTaxonomyButtons();
      } catch (e) {}
    },
    renderTaxonomyButtons: function() {
      setHtml('#edit-content-genres-btns', this._ALL_GENRES.map(g => {
        const active = this._SELECTED_GENRES.has(String(g.id));
        return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'}" data-action="toggle-genre" data-id="${g.id}">${g.name}</button>`;
      }).join(''));
      setHtml('#edit-content-tags-btns', this._ALL_TAGS.map(t => {
        const active = this._SELECTED_TAGS.has(String(t.id));
        return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'}" data-action="toggle-tag" data-id="${t.id}">${t.name}</button>`;
      }).join(''));
    },
    renderCreateTaxonomyButtons: function() {
      setHtml('#create-content-genres-btns', this._ALL_GENRES.map(g => {
        const active = this._CREATE_GENRES.has(String(g.id));
        return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'}" data-action="c-toggle-genre" data-id="${g.id}">${g.name}</button>`;
      }).join(''));
      setHtml('#create-content-tags-btns', this._ALL_TAGS.map(t => {
        const active = this._CREATE_TAGS.has(String(t.id));
        return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'}" data-action="c-toggle-tag" data-id="${t.id}">${t.name}</button>`;
      }).join(''));
    },
    toggleTax: function(type, id) {
      const set = type === 'genre' ? this._SELECTED_GENRES : this._SELECTED_TAGS;
      if (set.has(String(id))) set.delete(String(id)); else set.add(String(id));
      this.renderTaxonomyButtons();
    },
    toggleCreateTax: function(type, id) {
      const set = type === 'genre' ? this._CREATE_GENRES : this._CREATE_TAGS;
      if (set.has(String(id))) set.delete(String(id)); else set.add(String(id));
      this.renderCreateTaxonomyButtons();
    },
    openEditContent: function(id) {
      const c = this._CONTENTS.find(x => x.id == id);
      if (!c) return;
      $('#edit-content-id').value = c.id;
      $('#edit-content-title').value = c.title;
      $('#edit-content-alt-titles').value = c.alternative_titles || '';
      $('#edit-content-desc').value = c.description || '';
      $('#edit-content-status').value = c.status;
      $('#edit-content-cover').value = c.cover_image || '';
      $('#edit-content-author').value = c.author || '';
      $('#edit-content-artist').value = c.artist || '';
      $('#edit-content-country').value = c.country || '';
      $('#edit-content-release-year').value = c.release_year || '';
      this._SELECTED_GENRES = new Set((String(c.genre_ids || '')).split(',').map(x => x.trim()).filter(Boolean));
      this._SELECTED_TAGS = new Set((String(c.tag_ids || '')).split(',').map(x => x.trim()).filter(Boolean));
      this.renderTaxonomyButtons();
      if (window.openModal) window.openModal('modal-edit-content');
    },
    handleCreate: async function(e) {
      e.preventDefault();
      try {
        const payload = Object.fromEntries(new FormData(e.target));
        const res = await api('/admin/content', { method: 'POST', body: JSON.stringify(payload) });
        if (res?.data?.id) await api(`/admin/contents/${res.data.id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(this._CREATE_GENRES), tags: Array.from(this._CREATE_TAGS) }) });
        if (window.closeModal) window.closeModal();
        e.target.reset();
        this._CREATE_GENRES.clear(); this._CREATE_TAGS.clear();
        this.renderCreateTaxonomyButtons(); this.loadContents();
      } catch (err) { alert(err.message); }
    },
    handleEdit: async function(e) {
      e.preventDefault();
      const fd = new FormData(e.target);
      const id = fd.get('id');
      try {
        await api(`/admin/content/${id}`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(fd)) });
        await api(`/admin/contents/${id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(this._SELECTED_GENRES), tags: Array.from(this._SELECTED_TAGS) }) });
        if (window.closeModal) window.closeModal();
        this.loadContents();
        if (window.showPopup) window.showPopup('Content saved', 'success');
      } catch (err) { alert(err.message); }
    },
    slugify: (text) => text.toString().toLowerCase().trim().replace(/\s+/g, '-').replace(/[^\w-]+/g, '').replace(/--+/g, '-'),
    promptCreateTaxonomy: async function(type) {
      const name = prompt(`New ${type} name:`);
      if (!name) return;
      try { await api(`/admin/${type}s`, { method: 'POST', body: JSON.stringify({ name }) }); this.loadTaxonomy(); } catch (e) { alert(e.message); }
    },
    uploadSpecificImage: async function(input, targetId, type = 'chapters') {
      const file = input.files[0]; if (!file) return;
      const fd = new FormData(); fd.append('type', type); fd.append('images[]', file);
      try {
        const res = await api(`/admin/upload-images?type=${type}`, { method: 'POST', body: fd });
        if (res.data?.paths?.length > 0) document.getElementById(targetId).value = res.data.paths[0];
      } catch (e) { alert('Upload failed: ' + e.message); }
      input.value = '';
    },
    handleBulkUpload: async function(input, type = 'chapters') {
      let files = Array.from(input.files);
      if (files.length === 0) return;
      files.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));
      const area = document.getElementById('create-chapter-pages'); if (!area) return;
      let ok = 0, err = 0;
      for (const file of files) {
        const fd = new FormData(); fd.append('type', type); fd.append('images[]', file);
        try {
          const res = await api(`/admin/upload-images?type=${type}`, { method: 'POST', body: fd });
          if (res.data?.paths?.length > 0) { area.value = (area.value.trim() ? area.value + '\n' : '') + res.data.paths[0]; ok++; }
        } catch (e) { err++; }
      }
      alert(`Upload complete! Success: ${ok}, Fail: ${err}`);
      input.value = '';
    }
  };

  const Chapters = {
    init: function() {
      console.log("[AdminApp] Initializing Chapters...");
      document.addEventListener('nmr:admin-content:selected', (e) => this.handleContentSelected(e.detail));
      document.addEventListener('nmr:admin-chapter:create', (e) => this.handleChapterCreate(e.detail));
      $('#btn-refresh-chapters')?.addEventListener('click', () => this.loadChapters());
      $('#chapters-content-id')?.addEventListener('change', () => this.loadChapters());
      $('#create-chapter-type')?.addEventListener('change', (e) => this.toggleEditor(e.target.value, 'create'));
      $('#edit-chapter-type')?.addEventListener('change', (e) => this.toggleEditor(e.target.value, 'edit'));
      $('#form-create-chapter')?.addEventListener('submit', (e) => this.handleCreate(e));
      $('#form-edit-chapter')?.addEventListener('submit', (e) => this.handleEdit(e));
      
      $('#chapters-list-body')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const id = btn.dataset.id;
        if (btn.dataset.action === 'edit') this.openEdit(btn.dataset);
        if (btn.dataset.action === 'delete') this.handleDelete(id);
      });
    },
    handleContentSelected: function(detail) {
      setText('#chapters-card-title', `Chapters: ${detail.title}`);
      this.loadChapters();
    },
    handleChapterCreate: function(detail) {
      if (window.openModal) window.openModal('modal-create-chapter');
    },
    loadChapters: async function() {
      const contentId = $('#chapters-content-id')?.value;
      if (!contentId) return;
      try {
        const res = await api(`/admin/content/${contentId}/chapters`);
        const items = res.data?.items || res.data || [];
        setHtml('#chapters-list-body', items.map(ch => `
          <tr>
            <td>${ch.chapter_number}</td>
            <td>${ch.title || ''}</td>
            <td>${ch.type}</td>
            <td><span class="badge bg-light text-dark">${ch.username || 'System'}</span></td>
            <td><small>${(ch.created_at || '').split(' ')[0]}</small></td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" data-action="edit" data-id="${ch.id}" data-num="${ch.chapter_number}" data-title="${ch.title || ''}" data-type="${ch.type}"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-danger" data-action="delete" data-id="${ch.id}"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No chapters</td></tr>');
      } catch (e) { setHtml('#chapters-list-body', `<tr><td colspan="6" class="text-center text-danger">${e.message}</td></tr>`); }
    },
    toggleEditor: function(type, prefix) {
      const bodyWrap = $(`#${prefix}-chapter-body-wrap`);
      const pagesWrap = $(`#${prefix}-chapter-pages-wrap`);
      if (bodyWrap) bodyWrap.classList.toggle('d-none', type === 'image');
      if (pagesWrap) pagesWrap.classList.toggle('d-none', type !== 'image');
    },
    openEdit: function(data) {
      $('#edit-chapter-id').value = data.id;
      $('#edit-chapter-number').value = data.num;
      $('#edit-chapter-title').value = data.title;
      $('#edit-chapter-type').value = data.type;
      this.toggleEditor(data.type, 'edit');
      if (window.openModal) window.openModal('modal-edit-chapter');
    },
    handleCreate: async function(e) {
      e.preventDefault();
      const fd = new FormData(e.target);
      const contentId = $('#chapters-content-id').value;
      const type = fd.get('type');
      const payload = Object.fromEntries(fd);
      if (type === 'image') payload.data = fd.get('pages').split('\n').map(l => l.trim()).filter(Boolean).join('|');
      try {
        await api(`/admin/content/${contentId}/chapters`, { method: 'POST', body: JSON.stringify(payload) });
        if (window.closeModal) window.closeModal();
        e.target.reset(); this.loadChapters();
      } catch (e) { alert(e.message); }
    },
    handleEdit: async function(e) {
      e.preventDefault();
      const fd = new FormData(e.target);
      const id = fd.get('id');
      try {
        await api(`/admin/chapters/${id}`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(fd)) });
        if (window.closeModal) window.closeModal();
        this.loadChapters();
      } catch (e) { alert(e.message); }
    },
    handleDelete: async function(id) {
      if (!confirm('Delete chapter?')) return;
      try { await api(`/admin/chapters/${id}`, { method: 'DELETE' }); this.loadChapters(); } catch (e) { alert(e.message); }
    }
  };

  const Users = {
    _USERS: [],
    init: function() {
      console.log("[AdminApp] Initializing Users...");
      this.loadUsers();
      this.loadRoles();
      $('#btn-refresh-users')?.addEventListener('click', () => this.loadUsers());
      $('#users-list-body')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action="edit"]');
        if (btn) this.openEdit(btn.dataset.id);
      });
      $('#form-edit-user')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
          await api(`/admin/users/${fd.get('id')}`, { method: 'PUT', body: JSON.stringify({ role: fd.get('role'), is_banned: !!fd.get('is_banned'), email: fd.get('email'), bio: fd.get('bio') }) });
          if (window.closeModal) window.closeModal();
          this.loadUsers();
        } catch (e) { alert(e.message); }
      });
    },
    loadUsers: async function() {
      if (!$('#users-list-body')) return;
      try {
        const res = await api('/admin/users');
        this._USERS = res.data || [];
        setHtml('#users-list-body', this._USERS.map(u => `
          <tr>
            <td>${u.id}</td>
            <td><a href="/${window.NMR.getLangPrefix()}/profile/${u.username}" style="color:inherit; text-decoration:none;" class="fw-bold">${u.username}</a></td>
            <td>${u.email || ''}</td>
            <td><span class="badge bg-light text-dark">${this.firstRole(u)}</span></td>
            <td class="text-muted"><i class="bi bi-clock-history me-1"></i>${window.AdminApp.formatDuration(u.total_seconds)}</td>
            <td><button class="btn btn-xs btn-outline-secondary" data-action="edit" data-id="${u.id}"><i class="bi bi-person-gear"></i></button></td>
          </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No users</td></tr>');
      } catch (e) {}
    },
    loadRoles: async function() {
      const sel = $('#edit-user-role'); if (!sel) return;
      try {
        const res = await api('/admin/rbac/roles');
        const items = res.data.items || res.data || [];
        sel.innerHTML = items.map(r => `<option value="${r.slug}">${r.name || r.slug}</option>`).join('');
      } catch (e) {}
    },
    firstRole: (u) => (u.role_names ? u.role_names.split(',')[0].trim() : (u.role || (Array.isArray(u.roles) ? u.roles[0] : (typeof u.roles === 'string' ? u.roles.split(',')[0] : 'user')))),
    openEdit: function(id) {
      const u = this._USERS.find(x => x.id == id); if (!u) return;
      $('#edit-user-id').value = u.id;
      $('#edit-user-username').value = u.username;
      $('#edit-user-email').value = u.email || '';
      $('#edit-user-bio').value = u.bio || '';
      $('#edit-user-role').value = this.firstRole(u);
      $('#edit-user-banned').checked = !!u.is_banned;
      if (window.openModal) window.openModal('modal-edit-user');
    }
  };

  const Uploads = {
    currentPage: 1,
    perPage: 20,
    init: function() {
      if (!$('#uploads-list')) return;
      console.log("[AdminApp] Initializing Uploads...");
      this.load(1);
      $('#refresh-uploads')?.addEventListener('click', () => this.load(this.currentPage));
      $('#uploads-list')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.delete-upload');
        if (btn) this.delete(btn.dataset.id);
      });
      window.previewImage = (url) => { 
        const img = document.getElementById('full-preview');
        if (img) img.src = url; 
        if (window.openModal) window.openModal('preview-modal');
      };
    },
    load: async function(page = 1) {
      this.currentPage = page;
      try {
        const res = await api(`/admin/uploads?page=${page}&per_page=${this.perPage}`);
        const data = res.data || [];
        setHtml('#uploads-list', data.map(item => `
          <tr>
            <td><img src="${item.file_path}" class="img-thumbnail" style="height: 50px; cursor: pointer;" onclick="window.previewImage('${item.file_path}')"></td>
            <td><small class="text-muted">${item.original_name}</small></td>
            <td><span class="badge badge-info">${item.mime_type.split('/')[1]}</span></td>
            <td>${(item.file_size / 1024).toFixed(1)} KB</td>
            <td>${item.username || 'System'}</td>
            <td>${new Date(item.created_at).toLocaleString()}</td>
            <td><button class="btn btn-sm btn-danger delete-upload" data-id="${item.id}"><i class="fas fa-trash"></i></button></td>
          </tr>
        `).join('') || '<tr><td colspan="7" class="text-center py-4">No uploads found.</td></tr>');
        this.renderPager(res.meta || { page: 1, total_pages: 1 });
      } catch (e) {}
    },
    renderPager: function(meta) {
      const container = $('#uploads-pagination'); if (!container || meta.total_pages <= 1) { if(container) container.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= meta.total_pages; i++) html += `<li class="page-item ${i === meta.page ? 'active' : ''}"><a class="page-link" href="#" onclick="AdminApp.Modules.Uploads.load(${i});return false;">${i}</a></li>`;
      container.innerHTML = html;
    },
    delete: async function(id) {
      if (!confirm('Delete upload record?')) return;
      try { await api(`/admin/uploads/${id}`, { method: 'DELETE' }); this.load(this.currentPage); } catch (e) { alert(e.message); }
    }
  };

  const Ops = {
    init: function() {
      if (!$('#btn-run-jobs')) return;
      console.log("[AdminApp] Initializing System Ops...");
      this.loadQueue();
      $('#btn-run-jobs')?.addEventListener('click', () => this.runQueueOnce());
      $('#btn-run-cleanup')?.addEventListener('click', () => this.runCleanup());
      $('#btn-trigger-backup')?.addEventListener('click', () => this.handleMaint('btn-trigger-backup', '/admin/maintenance/backup', 'Backup complete'));
      $('#btn-trigger-analytics')?.addEventListener('click', () => this.handleMaint('btn-trigger-analytics', '/admin/maintenance/analytics', 'Analytics updated'));
      $('#btn-trigger-sitemap')?.addEventListener('click', () => this.handleMaint('btn-trigger-sitemap', '/admin/maintenance/sitemap', 'Sitemap updated'));
      $('#btn-trigger-warmup')?.addEventListener('click', () => this.handleMaint('btn-trigger-warmup', '/admin/maintenance/warmup', 'Cache warmed up'));
    },
    loadQueue: async function() {
      try {
        const res = await api('/admin/queue/jobs');
        setHtml('#queue-jobs-list', (res.data || []).map(j => `<div>[${j.id}] ${j.job_type} - <span class="text-info">${j.status}</span></div>`).join('') || 'Empty');
      } catch (e) {}
    },
    runQueueOnce: async function() {
      const limit = $('#jobs-limit')?.value || 5;
      await api('/admin/queue/run-once', { method: 'POST', body: JSON.stringify({ limit }) });
      this.loadQueue();
    },
    runCleanup: async function() {
      const days = $('#cleanup-days')?.value || 30;
      try { await api('/admin/retention/cleanup', { method: 'POST', body: JSON.stringify({ days }) }); alert('Done'); } catch (e) {}
    },
    handleMaint: async function(id, path, msg) {
      const btn = $(`#${id}`); const out = $('#maintenance-output'); if (!btn || !out) return;
      const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '...'; out.classList.remove('d-none');
      try {
        const res = await api(path, { method: 'POST', body: '{}' });
        out.innerHTML = (Array.isArray(res.data?.output) ? res.data.output.join('\n') : res.data?.output) || 'Done';
        if (window.showPopup) window.showPopup(msg, 'success');
      } catch (e) { out.innerHTML = e.message; } finally { btn.disabled = false; btn.innerHTML = old; }
    }
  };

  const Logs = {
    init: function() {
      if (!$('#logs-body')) return;
      console.log("[AdminApp] Initializing Logs...");
      this.loadAll();
      $('#btn-refresh-logs')?.addEventListener('click', () => this.loadAudit());
      $('#btn-refresh-logins')?.addEventListener('click', () => this.loadLogins());
      $('#btn-refresh-access')?.addEventListener('click', () => this.loadAccess());
      $('#btn-refresh-error')?.addEventListener('click', () => this.loadError());
    },
    loadAll: function() { this.loadAudit(); this.loadLogins(); this.loadAccess(); this.loadError(); },
    loadAudit: async function() {
      try {
        const res = await api('/admin/audit-logs');
        setHtml('#logs-body', (res.data || []).map(l => `<tr><td><small>${l.created_at.split(' ')[1]}</small></td><td><span class="badge ${l.status_code >= 400 ? 'bg-danger' : 'bg-secondary'}">${l.method}</span></td><td class="truncate">${l.path}</td><td><small>${l.ip_hash.slice(0,8)}</small></td><td>@${l.username || 'guest'}</td></tr>`).join('') || 'Empty');
      } catch (e) {}
    },
    loadLogins: async function() {
      try {
        const res = await api('/admin/login-events');
        setHtml('#logins-body', (res.data || []).map(l => `<tr><td>${l.email}</td><td>${l.ip_hash.slice(0,8)}</td><td><i class="bi ${l.success ? 'bi-check-circle text-success' : 'bi-x-circle text-danger'}"></i></td><td><small>${l.attempted_at.split(' ')[1]}</small></td></tr>`).join('') || 'Empty');
      } catch (e) {}
    },
    loadAccess: async function() {
      const c = $('#access-logs-container'); if (!c) return;
      try {
        const res = await api('/admin/logs/access');
        c.innerHTML = (res.data || []).map(l => `<div class="card mb-1 p-2 small border-l-3"><b>${l.method}</b> ${l.path} <span class="badge">${l.status}</span></div>`).join('');
      } catch (e) {}
    },
    loadError: async function() {
      const c = $('#error-logs-container'); if (!c) return;
      try {
        const res = await api('/admin/logs/error');
        c.innerHTML = (res.data || []).map(l => `<div class="card mb-1 p-2 small border-l-3 text-danger"><b>${l.level}</b> ${l.message}</div>`).join('');
      } catch (e) {}
    }
  };

  const Config = {
    init: function() {
      if (!$('#site-settings-form')) return;
      console.log("[AdminApp] Initializing Config...");
      this.load();
      $('#site-settings-form')?.addEventListener('submit', (e) => this.save(e));
    },
    load: async function() {
      try {
        const res = await api('/admin/maintenance/env'); const d = res.data || {};
        const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.value = v ?? ''; };
        setVal('site_name', d.SITE_NAME); setVal('site_abbreviation', d.SITE_ABBREVIATION);
        setVal('site_description', d.SITE_DESCRIPTION); setVal('site_logo', d.SITE_LOGO);
      } catch (e) {}
    },
    save: async function(e) {
      e.preventDefault();
      try {
        await api('/admin/maintenance/env', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(e.target))) });
        alert('Config saved');
      } catch (e) { alert(e.message); }
    }
  };

  const Comments = {
    init: function() {
      if (!$('#comments-list-body')) return;
      console.log("[AdminApp] Initializing Comments...");
      this.load();
      $('#btn-refresh-comments')?.addEventListener('click', () => this.load());
      $('#comments-list-body')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action="delete"]');
        if (btn) this.delete(btn.dataset.id);
      });
    },
    load: async function() {
      try {
        const res = await api('/admin/comments');
        setHtml('#comments-list-body', (res.data || []).map(c => `<tr><td>${c.id}</td><td>@${c.username}</td><td class="truncate">${c.body}</td><td><button class="btn btn-xs btn-danger" data-action="delete" data-id="${c.id}"><i class="bi bi-trash"></i></button></td></tr>`).join('') || 'Empty');
      } catch (e) {}
    },
    delete: async function(id) {
      if (!confirm('Delete comment?')) return;
      try { await api(`/admin/comments/${id}`, { method: 'DELETE' }); this.load(); } catch (e) {}
    }
  };

  return {
    Modules: { Dashboard, Blogs, Content, Chapters, Users, Uploads, Ops, Logs, Config, Comments },
    formatDuration: (s) => { if (!s || s <= 0) return '0s'; const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60); return h > 0 ? `${h}h ${m}m` : (m > 0 ? `${m}m` : `${s}s`); },
    init: function() {
      const path = window.location.pathname;
      const lang = (path.split('/')[1] === 'tr' || path.split('/')[1] === 'en') ? '/' + path.split('/')[1] : '';
      
      if (path === lang + '/admin') this.Modules.Dashboard.init();
      if (path.includes('/admin/blogs')) this.Modules.Blogs.init();
      if (path.includes('/admin/content')) { this.Modules.Content.init(); this.Modules.Chapters.init(); }
      if (path.includes('/admin/users')) this.Modules.Users.init();
      if (path.includes('/admin/uploads')) this.Modules.Uploads.init();
      if (path.includes('/admin/ops')) this.Modules.Ops.init();
      if (path.includes('/admin/logs')) this.Modules.Logs.init();
      if (path.includes('/admin/config')) this.Modules.Config.init();
      if (path.includes('/admin/comments')) this.Modules.Comments.init();
    }
  };
})();

document.addEventListener('DOMContentLoaded', () => AdminApp.init());
