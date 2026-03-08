/**
 * admin-bundle.js - COMPLETE REVISION
 * Unified Controller for AdminLTE, jQuery 3.7+, and NM-Reader API v1.
 */

window.AdminApp = (function($) {
  const ctx = window.__NMR_CONTEXT || {};
  const csrfToken = ctx.auth?.csrf_token || null;

  // --- CORE UI ---
  window.openModal = (id) => {
    const el = document.getElementById(id); if (!el) return;
    $('.modal-overlay, .modal').removeClass('active show');
    $(el).css('display', 'block').addClass('active show');
    if ($(el).hasClass('modal') && !$('.modal-backdrop').length) {
      $('body').append('<div class="modal-backdrop fade show"></div>').addClass('modal-open');
    }
  };
  window.closeModal = () => {
    $('.modal-overlay, .modal').removeClass('active show').css('display', 'none');
    $('.modal-backdrop').remove(); $('body').removeClass('modal-open');
  };
  $(document).on('click', '.modal-overlay, .modal, .btn-close, [data-bs-dismiss="modal"]', function(e) {
    if (e.target === this || $(this).hasClass('btn-close') || $(this).attr('data-bs-dismiss') === 'modal') window.closeModal();
  });

  const api = async (p, o = {}) => {
    const m = (o.method || 'GET').toUpperCase();
    const h = { 'X-Requested-With': 'XMLHttpRequest', ...(o.headers || {}) };
    if (o.body && !(o.body instanceof FormData)) h['Content-Type'] = 'application/json';
    if (csrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(m)) h['X-CSRF-Token'] = csrfToken;
    try {
      const res = await fetch(`/api/v1${p}`, { method: m, credentials: 'include', headers: h, body: o.body });
      const d = await res.json();
      if (!res.ok || d.status === 'error') throw new Error(d?.error?.message || d?.message || `HTTP ${res.status}`);
      return d;
    } catch (e) { console.error(`Admin API Error [${p}]:`, e); throw e; }
  };

  const setH = (s, h) => { const el = $(s); if (el.length) el.html(h); };
  const setT = (s, v) => { const el = $(s); if (el.length) el.text(String(v)); };
  const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
  const fmtDate = (v) => v ? String(v).split(' ')[0] : '';
  const fmtTime = (v) => v ? (String(v).split(' ')[1] || String(v)) : '';
  const lineList = (items) => Array.isArray(items) ? items.map(x => esc(x)).join('<br>') : '';

  // --- MODULES ---

  const Dashboard = {
    init: function() {
      console.log("[AdminApp] Dashboard Active");
      this.load();
      $('#btn-refresh-reputation')?.on('click', () => this.load());
      $('#btn-refresh-legacy-metrics, #btn-metrics-dashboard, #btn-metrics-snapshot, #btn-metrics-insights, #btn-metrics-genre')?.on('click', () => this.load());
      $('#btn-refresh-rbac')?.on('click', () => this.loadRbac());
      $('#form-assign-permission')?.on('submit', async (e) => {
        e.preventDefault();
        try {
          await api('/admin/rbac/permissions/assign', { method: 'POST', body: JSON.stringify({ role: $('#role-slug-input').val(), permission: $('#perm-code-input').val() }) });
          e.target.reset();
          this.loadRbac();
        } catch (err) { alert(err.message); }
      });
      $('#form-create-mod-action')?.on('submit', async (e) => {
        e.preventDefault();
        try {
          await api('/admin/moderation-actions', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(e.target))) });
          e.target.reset();
          this.loadModerationActions();
        } catch (err) { alert(err.message); }
      });
    },
    load: async function() {
      try {
        const [overviewRes, viewsRes, visitsRes, blogsRes, repRes] = await Promise.all([
          api('/admin/overview'),
          api('/admin/stats/views'),
          api('/admin/stats/visits'),
          api('/admin/stats/blogs'),
          api('/admin/stats/reputation')
        ]);
        const d = overviewRes.data || {};
        setT('#kpi-users', d.kpis?.users_total || 0); setT('#kpi-contents', d.kpis?.contents_total || 0);
        setT('#kpi-chapters', d.kpis?.chapters_total || 0); setT('#kpi-unread', d.kpis?.blogs_pending_total || 0);
        const m = d.metrics || {};
        setH('#metrics-top-contents', (m.top_contents_7d || []).map(c => `<tr><td><a href="/admin/content" class="text-decoration-none">${c.title}</a></td><td><small class="badge bg-light text-dark">${c.type}</small></td><td class="text-end fw-bold">${c.view_count_7d}</td><td class="text-end text-muted">${c.comment_count_7d || 0}</td></tr>`).join('') || '<tr><td colspan="4" class="text-center">No data</td></tr>');
        setH('#metrics-funnel-health', `<div class="mb-2 small">Home-to-Content: ${m.funnel?.home_to_content_pct || 0}%</div><div class="mb-2 small">Content-to-Chapter: ${m.funnel?.content_to_chapter_pct || 0}%</div><hr class="my-2 opacity-10"><div class="small">Error Rate: ${m.performance_slo?.server_error_rate_pct_24h || 0}%</div><div class="small">P95 Latency: ${m.performance_slo?.p95_duration_ms_24h || 0}ms</div>`);
        setH('#metrics-retention-search', `<div class="mb-2 small">Searches (7d): ${m.retention_search?.search_total_7d || 0}</div><div class="mb-2 small">Zero Results: ${m.retention_search?.zero_result_pct_7d || 0}%</div><hr class="my-2 opacity-10"><div class="small">D1 Retention: ${m.retention_search?.d1_retention_pct || 0}%</div><div class="small">New Users (7d): ${m.retention_search?.new_users_7d || 0}</div>`);
        const visits = visitsRes.data || {};
        setT('#visits-daily', visits.daily || 0);
        setT('#visits-weekly', visits.weekly || 0);
        setT('#visits-monthly', visits.monthly || 0);

        const blogStats = blogsRes.data || {};
        const summary = blogStats.summary || {};
        setT('#blog-stat-total', summary.total || 0);
        setT('#blog-stat-visible', summary.visible_total || 0);
        setT('#blog-stat-hidden', summary.hidden_total || 0);
        setT('#blog-stat-deleted', summary.deleted_total || 0);
        setT('#blog-stat-created-period', summary.created_last_days || 0);
        setT('#blog-stat-approved-period', summary.approved_last_days || 0);

        const rep = repRes.data || [];
        setH('#reputation-body', rep.map(u => `<tr><td>@${esc(u.username)}</td><td class="text-end fw-bold">${Number(u.score || 0).toFixed(1)}</td><td class="text-end">${u.comment_count || 0}</td><td class="text-end">${u.votes_given || 0}</td><td class="text-end">${u.up_votes || 0}</td><td class="text-end">${u.down_votes || 0}</td><td class="text-end">${Math.round((u.total_seconds || 0) / 60)}m</td></tr>`).join('') || '<tr><td colspan="7">No data</td></tr>');

        this.loadCharts(viewsRes.data || {}, blogStats);
        setH('#legacy-kpis', `Users: ${d.kpis?.users_total || 0}<br>Contents: ${d.kpis?.contents_total || 0}<br>Chapters: ${d.kpis?.chapters_total || 0}<br>Pending Blogs: ${d.kpis?.blogs_pending_total || 0}`);
        setH('#legacy-metrics-output', esc(JSON.stringify({ overview: d, views: viewsRes.data || {}, visits, blogs: blogStats }, null, 2)));
        this.loadRbac();
        this.loadModerationActions();
      } catch (e) {}
    },
    loadRbac: async function() {
      try {
        const [rolesRes, assignmentsRes] = await Promise.all([api('/admin/rbac/roles'), api('/admin/rbac/assignments')]);
        const roles = rolesRes.data?.items || rolesRes.data || [];
        const assignments = assignmentsRes.data?.items || assignmentsRes.data || [];
        setH('#rbac-roles-body', roles.map(r => `<tr><td><strong>${esc(r.slug)}</strong></td><td>${esc(r.name || r.description || '')}<div class="small text-muted">${esc(r.permissions || '')}</div></td></tr>`).join('') || '<tr><td colspan="2">No data</td></tr>');
        setH('#rbac-assignments-body', assignments.map(a => `<tr><td>@${esc(a.username)}</td><td>${esc(a.roles || '-')}</td><td>${esc(a.roles || '-')}</td></tr>`).join('') || '<tr><td colspan="3">No data</td></tr>');
      } catch (e) {}
    },
    loadModerationActions: async function() {
      try {
        const r = await api('/admin/moderation-actions');
        const items = r.data?.items || r.data || [];
        setH('#mod-actions-body', items.map(m => `<tr><td>@${esc(m.moderator_username || m.username || 'system')}</td><td>${esc(m.action || '-')}</td><td>${esc(`${m.target_type || '-'}:${m.target_id || '-'}`)}</td><td>${esc(m.reason || '')}</td></tr>`).join('') || '<tr><td colspan="4">No data</td></tr>');
      } catch (e) {}
    },
    loadCharts: function(s, b) {
      this.chart('chartTopTags', s.series_tags, 'view_total');
      this.chart('chartTopGenres', s.series_genres, 'view_total');
      this.chart('chartTopTypes', s.types, 'view_total');
      this.chart('chartTopContents', s.series, 'view_total');
      this.chart('chartTopChapters', s.chapters, 'view_total');
      this.chart('chartLegacyTopContents', s.series, 'view_total');
      this.chart('chartLegacyTopGenres', s.series_genres, 'view_total');
      this.chart('chartLegacyGenreInterest', s.series_tags, 'view_total');
      this.chart('chartBlogAuthors', b.top_authors, 'blog_total');
      this.chart('chartBlogDaily', (b.daily_created || []).map((x, idx) => ({ name: x.day || `Day ${idx + 1}`, total: x.total || 0 })), 'total');
    },
    chart: function(id, data, key) {
      if (typeof Chart === 'undefined') return; const el = document.getElementById(id); if (!el) return;
      if (el._nmrChart) el._nmrChart.destroy();
      new Chart(el.getContext('2d'), { type: 'bar', data: { labels: (data || []).map(x => x.name || x.title || 'N/A'), datasets: [{ data: (data || []).map(x => x[key]), backgroundColor: 'rgba(13,110,253,0.6)' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });
      el._nmrChart = Chart.getChart(el);
    }
  };

  const Content = {
    _DATA: [], _GENRES: [], _TAGS: [], _SEL_G: new Set(), _SEL_T: new Set(),
    init: function() {
      console.log("[AdminApp] Content Active");
      this.load(); this.loadTax();
      $('#btn-refresh-contents').on('click', () => this.load());
      $('#create-content-title').on('input', function() { $('#create-content-slug').val($(this).val().toLowerCase().trim().replace(/\s+/g, '-').replace(/[^\w-]+/g, '')); });
      $('#contents-list-body').on('click', 'button[data-action]', (e) => {
        const b = e.currentTarget; const c = this._DATA.find(x => x.id == b.dataset.id); if (!c) return;
        if (b.dataset.action === 'edit') this.openEdit(c);
        if (b.dataset.action === 'chapter' || b.dataset.action === 'add-chapter') {
          const s = $('#chapters-content-id'); if (s.length) { if (!s.find(`option[value="${c.id}"]`).length) s.append(new Option(c.title, c.id)); s.val(c.id).trigger('change'); }
          $('#create-chapter-content-id').val(c.id); $('#create-chapter-content-type').val(c.type); $('#create-chapter-content-slug').val(c.slug); $('#create-chapter-content').val(c.title);
          if (b.dataset.action === 'add-chapter') window.openModal('modal-create-chapter');
        }
      });
      $('#form-create-content').on('submit', async (e) => {
        e.preventDefault(); try { const d = Object.fromEntries(new FormData(e.target)); const r = await api('/admin/content', { method: 'POST', body: JSON.stringify(d) });
        if (r.data?.id) await api(`/admin/contents/${r.data.id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(this._CRE_G || []), tags: Array.from(this._CRE_T || []) }) });
        this._CRE_G = new Set(); this._CRE_T = new Set(); this.renderTax('create'); window.closeModal(); e.target.reset(); this.load(); } catch (e) { alert(e.message); }
      });
      $('#form-edit-content').on('submit', async (e) => {
        e.preventDefault(); const fd = new FormData(e.target); const id = fd.get('id'); try {
        await api(`/admin/content/${id}`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(fd)) });
        await api(`/admin/contents/${id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(this._SEL_G), tags: Array.from(this._SEL_T) }) });
        window.closeModal(); this.load(); } catch (e) { alert(e.message); }
      });
      $('#edit-content-genres-btns, #create-content-genres-btns').on('click', 'button', (e) => { const id = String(e.currentTarget.dataset.id); const isE = e.delegateTarget.id.includes('edit'); const localSet = isE ? this._SEL_G : (this._CRE_G = this._CRE_G || new Set()); if (localSet.has(id)) localSet.delete(id); else localSet.add(id); this.renderTax(isE ? 'edit' : 'create'); });
      $('#edit-content-tags-btns, #create-content-tags-btns').on('click', 'button', (e) => { const id = String(e.currentTarget.dataset.id); const isE = e.delegateTarget.id.includes('edit'); const localSet = isE ? this._SEL_T : (this._CRE_T = this._CRE_T || new Set()); if (localSet.has(id)) localSet.delete(id); else localSet.add(id); this.renderTax(isE ? 'edit' : 'create'); });
    },
    load: async function() {
      try {
        const res = await api('/admin/content'); this._DATA = res.data?.items || res.data || [];
        setH('#contents-list-body', this._DATA.map(c => `<tr><td>${c.id}</td><td><span class="badge bg-light text-dark">${c.type}</span></td><td>${c.title}</td><td><code>${c.slug}</code></td><td>${c.status}</td><td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-info" data-action="edit" data-id="${c.id}"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-primary" data-action="chapter" data-id="${c.id}"><i class="bi bi-list-ul"></i></button><button class="btn btn-outline-success" data-action="add-chapter" data-id="${c.id}"><i class="bi bi-plus-lg"></i></button></div></td></tr>`).join('') || '<tr><td colspan="6">No data</td></tr>');
        const s = $('#chapters-content-id'); if (s.length) { const cur = s.val(); s.html('<option value="">-- Select Series --</option>' + this._DATA.map(c => `<option value="${c.id}" ${c.id == cur ? 'selected' : ''}>${c.title}</option>`).join('')); }
      } catch (e) {}
    },
    loadTax: async function() {
      try {
        const [g, t] = await Promise.all([api('/admin/genres'), api('/admin/tags')]);
        this._GENRES = g.data || []; this._TAGS = t.data || [];
        setH('#genres-list-body', this._GENRES.map(x => `<tr><td class="w-40">${x.id}</td><td>${esc(x.name)}</td></tr>`).join('') || '<tr><td colspan="2">No data</td></tr>');
        setH('#tags-list-body', this._TAGS.map(x => `<tr><td class="w-40">${x.id}</td><td>${esc(x.name)}</td></tr>`).join('') || '<tr><td colspan="2">No data</td></tr>');
        this.renderTax('create'); this.renderTax('edit');
      } catch (e) {}
    },
    renderTax: function(m) {
      const isE = m === 'edit';
      const gS = isE ? this._SEL_G : (this._CRE_G || new Set());
      const tS = isE ? this._SEL_T : (this._CRE_T || new Set());
      setH(isE ? '#edit-content-genres-btns' : '#create-content-genres-btns', this._GENRES.map(g => `<button type="button" class="btn btn-xs ${gS.has(String(g.id)) ? 'btn-success' : 'btn-outline-secondary'} m-1" data-id="${g.id}">${esc(g.name)}</button>`).join(''));
      setH(isE ? '#edit-content-tags-btns' : '#create-content-tags-btns', this._TAGS.map(t => `<button type="button" class="btn btn-xs ${tS.has(String(t.id)) ? 'btn-success' : 'btn-outline-secondary'} m-1" data-id="${t.id}">${esc(t.name)}</button>`).join(''));
    },
    openEdit: function(c) {
      const f = $('#form-edit-content'); if (!f.length) return;
      f.find('[name="id"]').val(c.id); f.find('[name="title"]').val(c.title); f.find('[name="alternative_titles"]').val(c.alternative_titles || ''); f.find('[name="slug"]').val(c.slug); f.find('[name="status"]').val(c.status); f.find('[name="description"]').val(c.description || ''); f.find('[name="author"]').val(c.author || ''); f.find('[name="artist"]').val(c.artist || ''); f.find('[name="country"]').val(c.country || ''); f.find('[name="release_year"]').val(c.release_year || ''); f.find('[name="cover_image"]').val(c.cover_image || '');
      this._SEL_G = new Set(String(c.genre_ids || '').split(',').filter(Boolean)); this._SEL_T = new Set(String(c.tag_ids || '').split(',').filter(Boolean)); this.renderTax('edit'); window.openModal('modal-edit-content');
    },
    promptCreateTaxonomy: async function(kind) {
      const label = kind === 'genre' ? 'genre' : 'tag';
      const name = window.prompt(`New ${label} name`);
      if (!name) return;
      const endpoint = kind === 'genre' ? '/admin/series_genres' : '/admin/series_tags';
      try { await api(endpoint, { method: 'POST', body: JSON.stringify({ name }) }); await this.loadTax(); } catch (e) { alert(e.message); }
    },
    uploadSpecificImage: async function(input, targetId, type) {
      const file = input?.files?.[0]; if (!file) return;
      const fd = new FormData(); fd.append('images[]', file);
      try {
        const r = await api(`/admin/upload-images?type=${encodeURIComponent(type || 'chapters')}`, { method: 'POST', body: fd, headers: {} });
        const path = r.data?.paths?.[0] || '';
        if (path) document.getElementById(targetId)?.setAttribute('value', path), $(`#${targetId}`).val(path);
      } catch (e) { alert(e.message); }
      input.value = '';
    },
    handleBulkUpload: async function(input, type) {
      const files = Array.from(input?.files || []);
      if (!files.length) return;
      const fd = new FormData(); files.forEach(file => fd.append('images[]', file));
      try {
        const r = await api(`/admin/upload-images?type=${encodeURIComponent(type || 'chapters')}`, { method: 'POST', body: fd, headers: {} });
        const paths = r.data?.paths || [];
        const textarea = document.getElementById('create-chapter-pages');
        if (textarea) textarea.value = paths.join('\n');
      } catch (e) { alert(e.message); }
      input.value = '';
    }
  };

  const Chapters = {
    init: function() {
      console.log("[AdminApp] Chapters Active");
      $('#chapters-content-id').on('change', () => this.load());
      $('#btn-refresh-chapters').on('click', () => this.load());
      $('#btn-add-chapter').on('click', () => window.openModal('modal-create-chapter'));
      $('#create-chapter-type, #edit-chapter-type').on('change', (e) => this.toggle($(e.target).val(), e.target.id.includes('edit') ? 'edit' : 'create'));
      $('#form-create-chapter, #form-edit-chapter').on('submit', async (e) => {
        e.preventDefault(); const isE = e.target.id.includes('edit'); const fd = new FormData(e.target); const cid = $('#chapters-content-id').val();
        const p = Object.fromEntries(fd);
        if ((p.type || '').toLowerCase() === 'image') p.pages = $(isE ? '#edit-chapter-pages' : '#create-chapter-pages').val().split('\n').map(l => l.trim()).filter(Boolean);
        else p.body = $(isE ? '#edit-chapter-body' : '#create-chapter-body').val();
        try { await api(isE ? `/admin/chapters/${fd.get('id')}` : `/admin/content/${cid}/chapters`, { method: isE ? 'PUT' : 'POST', body: JSON.stringify(p) }); window.closeModal(); e.target.reset(); this.toggle('text', isE ? 'edit' : 'create'); this.load(); } catch (e) { alert(e.message); }
      });
      $('#chapters-list-body').on('click', 'button[data-action]', async (e) => {
        const id = e.currentTarget.dataset.id; const act = e.currentTarget.dataset.action;
        if (act === 'edit') this.openEdit(id);
        if (act === 'delete' && confirm('Delete?')) { try { await api(`/admin/chapters/${id}`, { method: 'DELETE' }); this.load(); } catch (e) { alert(e.message); } }
      });
    },
    load: async function() {
      const cid = $('#chapters-content-id').val(); if (!cid) return;
      try { const res = await api(`/admin/content/${cid}/chapters`); const items = res.data?.items || res.data || [];
      setH('#chapters-list-body', items.map(ch => `<tr><td>${ch.chapter_number}</td><td>${ch.title || ''}</td><td>${ch.type}</td><td><span class="badge bg-light text-dark">${ch.username || 'System'}</span></td><td><small>${(ch.created_at || '').split(' ')[0]}</small></td><td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-info" data-action="edit" data-id="${ch.id}"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger" data-action="delete" data-id="${ch.id}"><i class="bi bi-trash"></i></button></div></td></tr>`).join('') || '<tr><td colspan="6">No data</td></tr>');
      } catch (e) {}
    },
    openEdit: async function(id) {
      try {
        const r = await api(`/admin/chapters/${id}`); const ch = r.data; const f = $('#form-edit-chapter');
        f.find('[name="id"]').val(ch.id); f.find('[name="chapter_number"]').val(ch.chapter_number); f.find('[name="title"]').val(ch.title || ''); f.find('[name="type"]').val(ch.type);
        if (ch.type === 'image') { $('#edit-chapter-pages').val((ch.pages || []).join('\n')); $('#edit-chapter-body').val(''); } else { $('#edit-chapter-body').val(ch.body || ch.data || ''); $('#edit-chapter-pages').val(''); }
        this.toggle(ch.type, 'edit'); window.openModal('modal-edit-chapter');
      } catch (e) { alert(e.message); }
    },
    toggle: function(t, p) { $(`#${p}-chapter-body-wrap`).toggleClass('d-none', t === 'image'); $(`#${p}-chapter-pages-wrap`).toggleClass('d-none', t !== 'image'); }
  };

  const Blogs = {
    init: function() {
      this.load(); this.loadPending(); $('#btn-refresh-blogs-all, #btn-refresh-blogs')?.on('click', () => { this.load(); this.loadPending(); });
      $('#all-blogs-body').on('click', 'button[data-action]', async (e) => {
        const id = e.currentTarget.dataset.id; const act = e.currentTarget.dataset.action;
        try { if (act === 'approve') await api(`/admin/blogs/${id}/approve`, { method: 'POST', body: '{}' }); if (act === 'hide') await api(`/admin/blogs/${id}/hide`, { method: 'POST', body: '{}' }); if (act === 'delete' && confirm('Delete?')) await api(`/admin/blogs/${id}`, { method: 'DELETE' }); this.load(); } catch (e) { alert(e.message); }
      });
      $('#pending-blogs-body').on('click', 'button[data-action]', async (e) => {
        const id = e.currentTarget.dataset.id; const act = e.currentTarget.dataset.action;
        try { if (act === 'approve') await api(`/admin/blogs/${id}/approve`, { method: 'POST', body: '{}' }); if (act === 'hide') await api(`/admin/blogs/${id}/hide`, { method: 'POST', body: '{}' }); this.loadPending(); this.load(); } catch (e) { alert(e.message); }
      });
    },
    load: async function() { try { const r = await api('/admin/blogs'); const items = r.data?.items || r.data || []; setH('#all-blogs-body', items.map(b => `<tr><td>${b.id}</td><td>${esc(b.title)}</td><td>@${esc(b.username || b.author_username || '')}</td><td>${b.approved ? 'Yes' : 'No'}</td><td>${(b.created_at || '').split(' ')[0]}</td><td><div class="btn-group btn-group-sm"><button class="btn btn-outline-success" data-action="approve" data-id="${b.id}">Approve</button><button class="btn btn-outline-secondary" data-action="hide" data-id="${b.id}">Hide</button><button class="btn btn-outline-danger" data-action="delete" data-id="${b.id}">Delete</button></div></td></tr>`).join('') || '<tr><td colspan="6">No data</td></tr>'); } catch (e) {} },
    loadPending: async function() { try { const r = await api('/admin/blogs/pending'); const items = r.data?.items || r.data || []; setH('#pending-blogs-body', items.map(b => `<tr><td>${b.id}</td><td>${esc(b.title)}</td><td>@${esc(b.username || b.author_username || '')}</td><td><span class="badge bg-warning text-dark">Pending</span></td><td><div class="btn-group btn-group-sm"><button class="btn btn-outline-success" data-action="approve" data-id="${b.id}">Approve</button><button class="btn btn-outline-secondary" data-action="hide" data-id="${b.id}">Hide</button></div></td></tr>`).join('') || '<tr><td colspan="5">No data</td></tr>'); } catch (e) {} }
  };

  const Users = {
    _U: [], _R: [],
    init: function() {
      this.loadRoles(); this.load(); $('#btn-refresh-users')?.on('click', () => { this.loadRoles(); this.load(); });
      $('#users-list-body').on('click', 'button[data-action="edit"]', (e) => this.open(e.currentTarget.dataset.id));
      $('#form-edit-user').on('submit', async (e) => { e.preventDefault(); const fd = new FormData(e.target); try { await api(`/admin/users/${fd.get('id')}`, { method: 'PUT', body: JSON.stringify({ role: fd.get('role'), is_banned: !!fd.get('is_banned'), email: fd.get('email'), bio: fd.get('bio') }) }); window.closeModal(); this.load(); } catch (e) { alert(e.message); } });
    },
    loadRoles: async function() { try { const r = await api('/admin/rbac/roles'); this._R = r.data?.items || r.data || []; setH('#edit-user-role', this._R.map(x => `<option value="${esc(x.slug)}">${esc(x.name || x.slug)}</option>`).join('') || '<option value="user">User</option>'); } catch (e) {} },
    load: async function() { try { const r = await api('/admin/users'); this._U = r.data?.items || r.data || []; setH('#users-list-body', this._U.map(u => `<tr><td>${u.id}</td><td><b>${u.username}</b></td><td>${u.email || ''}</td><td><span class="badge bg-secondary">${u.role_names || 'user'}</span></td><td class="text-end"><button class="btn btn-xs btn-outline-secondary" data-action="edit" data-id="${u.id}"><i class="bi bi-person-gear"></i></button></td></tr>`).join('')); } catch (e) {} },
    open: function(id) { const u = this._U.find(x => x.id == id); if (!u) return; $('#edit-user-id').val(u.id); $('#edit-user-username').val(u.username); $('#edit-user-email').val(u.email || ''); $('#edit-user-bio').val(u.bio || ''); $('#edit-user-banned').prop('checked', !!u.is_banned); $('#edit-user-role').val(((u.role_names || 'user').split(',')[0] || 'user').trim()); window.openModal('modal-edit-user'); }
  };

  const Comments = {
    init: function() {
      if (!$('#comments-list-body').length) return;
      this.load();
      $('#btn-refresh-comments')?.on('click', () => this.load());
      $('#comments-list-body').on('click', 'button[data-action="delete"]', async (e) => {
        const id = e.currentTarget.dataset.id;
        if (!id || !confirm('Delete?')) return;
        try { await api(`/admin/comments/${id}`, { method: 'DELETE' }); this.load(); } catch (err) { alert(err.message); }
      });
    },
    load: async function() {
      try {
        const r = await api('/admin/comments');
        const items = r.data?.items || r.data || [];
        setH('#comments-list-body', items.map(c => `<tr><td>@${esc(c.username || 'guest')}</td><td class="text-break">${esc(c.body || '')}</td><td>${esc(c.content_title || c.blog_title || '-')}</td><td><small>${esc((c.created_at || '').split(' ')[0])}</small></td><td><button class="btn btn-xs btn-outline-danger" data-action="delete" data-id="${c.id}"><i class="bi bi-trash"></i></button></td></tr>`).join('') || '<tr><td colspan="5">No data</td></tr>');
      } catch (e) {}
    }
  };

  const Uploads = {
    init: function() { if (!$('#uploads-list').length) return; this.load(1); $('#refresh-uploads').on('click', () => this.load(1)); $('#uploads-list').on('click', 'button[data-action="delete-upload"]', async (e) => { if(confirm('Delete?')) { await api(`/admin/uploads/${e.currentTarget.dataset.id}`, { method: 'DELETE' }); this.load(1); } }); },
    load: async function(p) { try { const r = await api(`/admin/uploads?page=${p}`); const items = r.data?.items || r.data || []; setH('#uploads-list', items.map(i => `<tr><td><img src="${i.file_path}" class="img-thumbnail" style="height:40px;cursor:pointer" onclick="window.previewImg('${i.file_path}')"></td><td><small>${esc(i.original_name)}</small></td><td>${esc((i.mime_type || '').split('/')[1] || i.mime_type || '-')}</td><td>${((i.file_size || 0)/1024).toFixed(1)}KB</td><td>@${esc(i.username || 'System')}</td><td>${fmtDate(i.created_at)}</td><td><button class="btn btn-xs btn-outline-danger" data-id="${i.id}" data-action="delete-upload"><i class="bi bi-trash"></i></button></td></tr>`).join('') || '<tr><td colspan="7">No data</td></tr>'); } catch (e) {} }
  };

  const Ops = {
    init: function() {
      if (!$('#queue-jobs-list').length && !$('#btn-trigger-analytics').length) return;
      this.loadQueue();
      $('#btn-run-jobs')?.on('click', async () => {
        try {
          const limit = parseInt($('#jobs-limit').val(), 10) || 5;
          const r = await api('/admin/queue/run-once', { method: 'POST', body: JSON.stringify({ limit }) });
          this.printOutput(r.data || r.meta || { ok: true });
          this.loadQueue();
        } catch (e) { alert(e.message); }
      });
      $('#btn-run-cleanup')?.on('click', async () => {
        try {
          const days = parseInt($('#cleanup-days').val(), 10) || 30;
          const r = await api('/admin/retention/cleanup', { method: 'POST', body: JSON.stringify({ days }) });
          this.printOutput(r.data || { cleaned: true });
        } catch (e) { alert(e.message); }
      });
      $('#btn-trigger-analytics')?.on('click', async () => { try { const r = await api('/admin/maintenance/analytics', { method: 'POST', body: '{}' }); this.printOutput(r.data || {}); } catch (e) { alert(e.message); } });
      $('#btn-trigger-backup')?.on('click', async () => { try { const r = await api('/admin/maintenance/backup', { method: 'POST', body: '{}' }); this.printOutput(r.data || {}); } catch (e) { alert(e.message); } });
      $('#btn-trigger-sitemap')?.on('click', async () => { try { const r = await api('/admin/maintenance/sitemap', { method: 'POST', body: '{}' }); this.printOutput(r.data || {}); } catch (e) { alert(e.message); } });
      $('#btn-trigger-warmup')?.on('click', async () => { try { const r = await api('/admin/maintenance/warmup', { method: 'POST', body: '{}' }); this.printOutput(r.data || {}); } catch (e) { alert(e.message); } });
    },
    loadQueue: async function() {
      try {
        const r = await api('/admin/queue/jobs');
        const items = r.data?.items || r.data || [];
        setH('#queue-jobs-list', items.map(j => `<div class="border-bottom pb-1 mb-1"><strong>#${j.id}</strong> <code>${esc(j.job_type)}</code> <span class="badge bg-${j.status === 'done' ? 'success' : (j.status === 'failed' ? 'danger' : 'secondary')}">${esc(j.status)}</span><div class="small text-muted">${fmtDate(j.created_at)} ${fmtTime(j.created_at)} | attempts: ${j.attempts || 0}</div>${j.last_error ? `<div class="small text-danger">${esc(j.last_error)}</div>` : ''}</div>`).join('') || 'No queued jobs.');
      } catch (e) {
        setH('#queue-jobs-list', esc(e.message));
      }
    },
    printOutput: function(payload) {
      const box = $('#maintenance-output');
      if (!box.length) return;
      box.removeClass('d-none').html(`<pre class="mb-0 text-light">${esc(JSON.stringify(payload, null, 2))}</pre>`);
    }
  };

  const Logs = {
    init: function() {
      if (!$('#logs-body').length && !$('#audit-logs-body').length && !$('#logins-body').length) return;
      this.loadAll();
      $('#btn-refresh-logs')?.on('click', () => this.loadAudit());
      $('#btn-refresh-logins')?.on('click', () => this.loadLogins());
      $('#btn-refresh-access')?.on('click', () => this.loadAccess());
      $('#btn-refresh-error')?.on('click', () => this.loadErrors());
    },
    loadAll: function() { this.loadAudit(); this.loadLogins(); this.loadAccess(); this.loadErrors(); },
    loadAudit: async function() {
      try {
        const r = await api('/admin/audit-logs');
        const items = r.data?.items || r.data || [];
        setH('#logs-body', items.map(l => `<tr><td><small>${fmtTime(l.created_at)}</small></td><td><span class="badge bg-secondary">${esc(l.method)}</span></td><td class="truncate">${esc(l.path)}</td><td>${esc(l.ip_hash || '-')}</td><td>@${esc(l.username || 'guest')}</td></tr>`).join('') || '<tr><td colspan="5">No data</td></tr>');
        setH('#audit-logs-body', items.map(l => `<tr><td>@${esc(l.username || 'guest')}</td><td><span class="badge bg-secondary">${esc(l.method)}</span> ${esc(l.status_code || '')}</td><td>${esc(l.path)}</td><td>${esc(l.created_at || '')}</td></tr>`).join('') || '<tr><td colspan="4">No data</td></tr>');
      } catch (e) {}
    },
    loadLogins: async function() {
      try {
        const r = await api('/admin/login-events');
        const items = r.data?.items || r.data || [];
        setH('#logins-body', items.map(l => `<tr><td>${esc(l.email || '-')}</td><td>${esc(l.ip_hash || '-')}</td><td>${esc((l.user_agent || '').slice(0, 60))}</td><td><span class="badge bg-${Number(l.success) ? 'success' : 'danger'}">${Number(l.success) ? 'Yes' : 'No'}</span></td><td>${esc(l.attempted_at || '')}</td></tr>`).join('') || '<tr><td colspan="5">No data</td></tr>');
        setH('#login-logs-body', items.map(l => `<tr><td>${esc(l.email || '-')}</td><td>${esc(l.ip_hash || '-')}</td><td>${Number(l.success) ? 'success' : esc(l.failure_reason || 'failed')}</td><td>${esc(l.attempted_at || '')}</td></tr>`).join('') || '<tr><td colspan="4">No data</td></tr>');
      } catch (e) {}
    },
    loadAccess: async function() {
      try {
        const r = await api('/admin/logs/access');
        const items = r.data?.items || r.data || [];
        setH('#access-logs-container', items.map(l => `<div class="border-bottom pb-2 mb-2"><div><strong>${esc(l.method || 'GET')}</strong> ${esc(l.path || '-')}</div><div class="small text-muted">${esc(l.created_at || '')} | ${esc(l.ip_hash || '-')} | ${esc(l.duration_ms || 0)}ms</div></div>`).join('') || '<div class="text-muted">No data</div>');
      } catch (e) { setH('#access-logs-container', `<div class="text-danger">${esc(e.message)}</div>`); }
    },
    loadErrors: async function() {
      try {
        const r = await api('/admin/logs/error');
        const items = r.data?.items || r.data || [];
        setH('#error-logs-container', items.length ? items.map(l => `<div class="border-bottom pb-2 mb-2"><pre class="mb-0">${esc(JSON.stringify(l, null, 2))}</pre></div>`).join('') : '<div class="text-muted">No data</div>');
      } catch (e) { setH('#error-logs-container', `<div class="text-danger">${esc(e.message)}</div>`); }
    }
  };

  const Config = {
    init: function() {
      if (!$('#form-env-config').length) return;
      this.load();
      $('#btn-reload-env')?.on('click', () => this.load());
      $('#btn-add-var')?.on('click', () => this.addVar());
      $('#form-env-config').on('submit', async (e) => {
        e.preventDefault();
        const payload = {};
        $('#form-env-config').find('[data-env-key]').each((_, el) => { payload[$(el).data('envKey')] = $(el).val(); });
        try {
          await api('/admin/maintenance/env', { method: 'POST', body: JSON.stringify(payload) });
          this.flash('Saved');
        } catch (err) { alert(err.message); }
      });
      $('#env-sections-wrapper').on('click', '.btn-remove', (e) => { $(e.currentTarget).closest('.env-row').remove(); });
    },
    load: async function() {
      try {
        const r = await api('/admin/maintenance/env');
        const data = r.data || {};
        const rows = Object.entries(data).sort(([a], [b]) => a.localeCompare(b));
        setH('#env-sections-wrapper', rows.map(([k, v]) => this.row(k, v)).join('') || '<div class="text-muted">No variables.</div>');
      } catch (e) {
        setH('#env-sections-wrapper', `<div class="alert alert-danger mb-0">${esc(e.message)}</div>`);
      }
    },
    row: function(key, value) {
      return `<div class="card env-section"><div class="card-body env-row"><div class="row g-2 align-items-center"><div class="col-md-4"><div class="env-key-label">KEY</div><input class="form-control form-control-sm" value="${esc(key)}" data-env-key="${esc(key)}" readonly></div><div class="col-md-7"><div class="env-key-label">VALUE</div><input class="form-control form-control-sm" value="${esc(value)}" data-env-key="${esc(key)}"></div><div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger btn-sm btn-remove">&times;</button></div></div></div></div>`;
    },
    addVar: function() {
      const key = window.prompt('ENV key');
      if (!key) return;
      $('#env-sections-wrapper').prepend(this.row(key.trim().toUpperCase(), ''));
    },
    flash: function(text) {
      const btn = $('#btn-save-env');
      if (!btn.length) return;
      const old = btn.html();
      btn.html(`<i class="bi bi-check2 me-2"></i>${esc(text)}`);
      setTimeout(() => btn.html(old), 1500);
    }
  };

  window.previewImg = (url) => { $('#full-preview').attr('src', url); window.openModal('preview-modal'); };
  window.NMR_ADMIN_CONTENT = Content;
  window.NMR_ADMIN = { promptCreateTaxonomy: (...args) => Content.promptCreateTaxonomy(...args) };

  return {
    init: function() {
      const p = window.location.pathname; const l = (p.split('/')[1] === 'tr' || p.split('/')[1] === 'en') ? '/' + p.split('/')[1] : '';
      const c = p.replace(l, '');
      if (c === '/admin') { Dashboard.init(); Content.init(); Blogs.init(); Ops.init(); Logs.init(); }
      if (c.includes('/admin/content')) { Content.init(); Chapters.init(); }
      if (c.includes('/admin/blogs')) Blogs.init();
      if (c.includes('/admin/comments')) Comments.init();
      if (c.includes('/admin/users')) Users.init();
      if (c.includes('/admin/uploads')) Uploads.init();
      if (c.includes('/admin/ops')) Ops.init();
      if (c.includes('/admin/logs')) Logs.init();
      if (c.includes('/admin/config')) Config.init();
    }
  };
})(window.jQuery);

$(function() { AdminApp.init(); });
