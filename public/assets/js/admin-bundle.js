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
      if (!res.ok || d.status === 'error') throw new Error(d?.message || `HTTP ${res.status}`);
      return d;
    } catch (e) { console.error(`Admin API Error [${p}]:`, e); throw e; }
  };

  const setH = (s, h) => { const el = $(s); if (el.length) el.html(h); };
  const setT = (s, v) => { const el = $(s); if (el.length) el.text(String(v)); };

  // --- MODULES ---

  const Dashboard = {
    init: function() {
      console.log("[AdminApp] Dashboard Active");
      this.load();
      $('#btn-refresh-reputation')?.on('click', () => this.load());
    },
    load: async function() {
      try {
        const res = await api('/admin/overview'); const d = res.data || {};
        setT('#kpi-users', d.kpis?.users_total || 0); setT('#kpi-contents', d.kpis?.contents_total || 0);
        setT('#kpi-chapters', d.kpis?.chapters_total || 0); setT('#kpi-unread', d.kpis?.blogs_pending_total || 0);
        const m = d.metrics || {};
        setH('#metrics-top-contents', (m.top_contents_7d || []).map(c => `<tr><td><a href="/admin/content" class="text-decoration-none">${c.title}</a></td><td><small class="badge bg-light text-dark">${c.type}</small></td><td class="text-end fw-bold">${c.view_count_7d}</td><td class="text-end text-muted">${c.comment_count_7d || 0}</td></tr>`).join('') || '<tr><td colspan="4" class="text-center">No data</td></tr>');
        setH('#metrics-funnel-health', `<div class="mb-2 small">Home-to-Content: ${m.funnel?.home_to_content_pct || 0}%</div><div class="mb-2 small">Content-to-Chapter: ${m.funnel?.content_to_chapter_pct || 0}%</div><hr class="my-2 opacity-10"><div class="small">Error Rate: ${m.performance_slo?.server_error_rate_pct_24h || 0}%</div><div class="small">P95 Latency: ${m.performance_slo?.p95_duration_ms_24h || 0}ms</div>`);
        setH('#metrics-retention-search', `<div class="mb-2 small">Searches (7d): ${m.retention_search?.search_total_7d || 0}</div><div class="mb-2 small">Zero Results: ${m.retention_search?.zero_result_pct_7d || 0}%</div><hr class="my-2 opacity-10"><div class="small">D1 Retention: ${m.retention_search?.d1_retention_pct || 0}%</div><div class="small">New Users (7d): ${m.retention_search?.new_users_7d || 0}</div>`);
        this.loadCharts();
      } catch (e) {}
    },
    loadCharts: async function() {
      try {
        const v = await api('/admin/stats/views'); const s = v.data || {};
        this.chart('chartTopTags', s.series_tags, 'view_total');
        this.chart('chartTopGenres', s.series_genres, 'view_total');
        this.chart('chartTopContents', s.series, 'view_total');
      } catch (e) {}
    },
    chart: function(id, data, key) {
      if (typeof Chart === 'undefined') return; const el = document.getElementById(id); if (!el) return;
      new Chart(el.getContext('2d'), { type: 'bar', data: { labels: (data || []).map(x => x.name || x.title || 'N/A'), datasets: [{ data: (data || []).map(x => x[key]), backgroundColor: 'rgba(13,110,253,0.6)' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });
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
          if (b.dataset.action === 'add-chapter') window.openModal('modal-create-chapter');
        }
      });
      $('#form-create-content').on('submit', async (e) => {
        e.preventDefault(); try { const d = Object.fromEntries(new FormData(e.target)); const r = await api('/admin/content', { method: 'POST', body: JSON.stringify(d) });
        if (r.data?.id) await api(`/admin/contents/${r.data.id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(this._SEL_G), tags: Array.from(this._SEL_T) }) });
        window.closeModal(); e.target.reset(); this.load(); } catch (e) { alert(e.message); }
      });
      $('#form-edit-content').on('submit', async (e) => {
        e.preventDefault(); const fd = new FormData(e.target); const id = fd.get('id'); try {
        await api(`/admin/content/${id}`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(fd)) });
        await api(`/admin/contents/${id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(this._SEL_G), tags: Array.from(this._SEL_T) }) });
        window.closeModal(); this.load(); } catch (e) { alert(e.message); }
      });
      $('#edit-content-genres-btns, #create-content-genres-btns').on('click', 'button', (e) => { const id = String(e.currentTarget.dataset.id); const isE = e.delegateTarget.id.includes('edit'); const set = isE ? this._SEL_G : this._CRE_G || new Set(); if (set.has(id)) set.delete(id); else set.add(id); this.renderTax(isE ? 'edit' : 'create'); });
    },
    load: async function() {
      try {
        const res = await api('/admin/content'); this._DATA = res.data?.items || res.data || [];
        setH('#contents-list-body', this._DATA.map(c => `<tr><td>${c.id}</td><td><span class="badge bg-light text-dark">${c.type}</span></td><td>${c.title}</td><td><code>${c.slug}</code></td><td>${c.status}</td><td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-info" data-action="edit" data-id="${c.id}"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-primary" data-action="chapter" data-id="${c.id}"><i class="bi bi-list-ul"></i></button><button class="btn btn-outline-success" data-action="add-chapter" data-id="${c.id}"><i class="bi bi-plus-lg"></i></button></div></td></tr>`).join('') || '<tr><td colspan="6">No data</td></tr>');
        const s = $('#chapters-content-id'); if (s.length) { const cur = s.val(); s.html('<option value="">-- Select Series --</option>' + this._DATA.map(c => `<option value="${c.id}" ${c.id == cur ? 'selected' : ''}>${c.title}</option>`).join('')); }
      } catch (e) {}
    },
    loadTax: async function() { try { const [g, t] = await Promise.all([api('/admin/genres'), api('/admin/tags')]); this._GENRES = g.data || []; this._TAGS = t.data || []; this.renderTax('create'); this.renderTax('edit'); } catch (e) {} },
    renderTax: function(m) { const isE = m === 'edit'; const gS = isE ? this._SEL_G : this._CRE_G || new Set(); setH(isE ? '#edit-content-genres-btns' : '#create-content-genres-btns', this._GENRES.map(g => `<button type="button" class="btn btn-xs ${gS.has(String(g.id)) ? 'btn-success' : 'btn-outline-secondary'} m-1" data-id="${g.id}">${g.name}</button>`).join('')); },
    openEdit: function(c) {
      const f = $('#form-edit-content'); if (!f.length) return;
      f.find('[name="id"]').val(c.id); f.find('[name="title"]').val(c.title); f.find('[name="alternative_titles"]').val(c.alternative_titles || ''); f.find('[name="slug"]').val(c.slug); f.find('[name="status"]').val(c.status); f.find('[name="description"]').val(c.description || ''); f.find('[name="author"]').val(c.author || ''); f.find('[name="artist"]').val(c.artist || ''); f.find('[name="country"]').val(c.country || ''); f.find('[name="release_year"]').val(c.release_year || '');
      this._SEL_G = new Set(String(c.genre_ids || '').split(',').filter(Boolean)); this.renderTax('edit'); window.openModal('modal-edit-content');
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
        const p = Object.fromEntries(fd); p.data = $(isE ? '#edit-chapter-pages' : '#create-chapter-pages').val().split('\n').map(l => l.trim()).filter(Boolean).join('|') || $(isE ? '#edit-chapter-body' : '#create-chapter-body').val();
        try { await api(isE ? `/admin/chapters/${fd.get('id')}` : `/admin/content/${cid}/chapters`, { method: isE ? 'PUT' : 'POST', body: JSON.stringify(p) }); window.closeModal(); this.load(); } catch (e) { alert(e.message); }
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
        if (ch.type === 'image') $('#edit-chapter-pages').val((ch.data || '').split('|').join('\n')); else $('#edit-chapter-body').val(ch.data || '');
        this.toggle(ch.type, 'edit'); window.openModal('modal-edit-chapter');
      } catch (e) { alert(e.message); }
    },
    toggle: function(t, p) { $(`#${p}-chapter-body-wrap`).toggleClass('d-none', t === 'image'); $(`#${p}-chapter-pages-wrap`).toggleClass('d-none', t !== 'image'); }
  };

  const Blogs = {
    init: function() {
      this.load(); $('#btn-refresh-blogs-all')?.on('click', () => this.load());
      $('#all-blogs-body').on('click', 'button[data-action]', async (e) => {
        const id = e.currentTarget.dataset.id; const act = e.currentTarget.dataset.action;
        try { if (act === 'approve') await api(`/admin/blogs/${id}/approve`, { method: 'POST', body: '{}' }); if (act === 'hide') await api(`/admin/blogs/${id}/hide`, { method: 'POST', body: '{}' }); if (act === 'delete' && confirm('Delete?')) await api(`/admin/blogs/${id}`, { method: 'DELETE' }); this.load(); } catch (e) { alert(e.message); }
      });
    },
    load: async function() { try { const r = await api('/admin/blogs'); const items = r.data?.items || r.data || []; setH('#all-blogs-body', items.map(b => `<tr><td>${b.id}</td><td>${b.title}</td><td>@${b.author_username}</td><td>${b.approved ? 'Yes' : 'No'}</td><td>${(b.created_at || '').split(' ')[0]}</td><td><div class="btn-group btn-group-sm"><button class="btn btn-outline-success" data-action="approve" data-id="${b.id}">Approve</button><button class="btn btn-outline-secondary" data-action="hide" data-id="${b.id}">Hide</button><button class="btn btn-outline-danger" data-action="delete" data-id="${b.id}">Delete</button></div></td></tr>`).join('')); } catch (e) {} }
  };

  const Users = {
    _U: [],
    init: function() {
      this.load(); $('#btn-refresh-users')?.on('click', () => this.load());
      $('#users-list-body').on('click', 'button[data-action="edit"]', (e) => this.open(e.currentTarget.dataset.id));
      $('#form-edit-user').on('submit', async (e) => { e.preventDefault(); const fd = new FormData(e.target); try { await api(`/admin/users/${fd.get('id')}`, { method: 'PUT', body: JSON.stringify({ role: fd.get('role'), is_banned: !!fd.get('is_banned'), email: fd.get('email'), bio: fd.get('bio') }) }); window.closeModal(); this.load(); } catch (e) { alert(e.message); } });
    },
    load: async function() { try { const r = await api('/admin/users'); this._U = r.data?.items || r.data || []; setH('#users-list-body', this._U.map(u => `<tr><td>${u.id}</td><td><b>${u.username}</b></td><td>${u.email || ''}</td><td><span class="badge bg-secondary">${u.role_names || 'user'}</span></td><td class="text-end"><button class="btn btn-xs btn-outline-secondary" data-action="edit" data-id="${u.id}"><i class="bi bi-person-gear"></i></button></td></tr>`).join('')); } catch (e) {} },
    open: function(id) { const u = this._U.find(x => x.id == id); if (!u) return; $('#edit-user-id').val(u.id); $('#edit-user-username').val(u.username); $('#edit-user-email').val(u.email || ''); $('#edit-user-bio').val(u.bio || ''); $('#edit-user-banned').prop('checked', !!u.is_banned); window.openModal('modal-edit-user'); }
  };

  const Uploads = {
    init: function() { if (!$('#uploads-list').length) return; this.load(1); $('#refresh-uploads').on('click', () => this.load(1)); $('#uploads-list').on('click', 'button[data-action="delete-upload"]', async (e) => { if(confirm('Delete?')) { await api(`/admin/uploads/${e.currentTarget.dataset.id}`, { method: 'DELETE' }); this.load(1); } }); },
    load: async function(p) { try { const r = await api(`/admin/uploads?page=${p}`); const items = r.data?.items || r.data || []; setH('#uploads-list', items.map(i => `<tr><td><img src="${i.file_path}" class="img-thumbnail" style="height:40px;cursor:pointer" onclick="window.previewImg('${i.file_path}')"></td><td><small>${i.original_name}</small></td><td>${i.mime_type.split('/')[1]}</td><td>${(i.file_size/1024).toFixed(1)}KB</td><td>@${i.username || 'System'}</td><td><button class="btn btn-xs btn-outline-danger" data-id="${i.id}" data-action="delete-upload"><i class="bi bi-trash"></i></button></td></tr>`).join('')); } catch (e) {} }
  };

  const Ops = { init: function() { $('#btn-run-jobs')?.on('click', async () => { await api('/admin/queue/run-once', { method: 'POST', body: '{}' }); alert('Done'); }); $('#btn-trigger-analytics')?.on('click', async () => { await api('/admin/maintenance/analytics', { method: 'POST', body: '{}' }); alert('Analytics Triggered'); }); } };

  const Logs = {
    init: function() { if (!$('#logs-body').length) return; this.load(); $('#btn-refresh-logs')?.on('click', () => this.load()); },
    load: async function() { try { const r = await api('/admin/audit-logs'); const items = r.data?.items || r.data || []; setH('#logs-body', items.map(l => `<tr><td><small>${l.created_at.split(' ')[1]}</small></td><td><span class="badge bg-secondary">${l.method}</span></td><td class="truncate">${l.path}</td><td>@${l.username || 'guest'}</td></tr>`).join('')); } catch (e) {} }
  };

  window.previewImg = (url) => { $('#full-preview').attr('src', url); window.openModal('preview-modal'); };

  return {
    init: function() {
      const p = window.location.pathname; const l = (p.split('/')[1] === 'tr' || p.split('/')[1] === 'en') ? '/' + p.split('/')[1] : '';
      const c = p.replace(l, '');
      if (c === '/admin') Dashboard.init();
      if (c.includes('/admin/content')) { Content.init(); Chapters.init(); }
      if (c.includes('/admin/blogs')) Blogs.init();
      if (c.includes('/admin/users')) Users.init();
      if (c.includes('/admin/uploads')) Uploads.init();
      if (c.includes('/admin/ops')) Ops.init();
      if (c.includes('/admin/logs')) Logs.init();
    }
  };
})(window.jQuery);

$(function() { AdminApp.init(); });
