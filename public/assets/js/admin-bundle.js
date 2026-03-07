/**
 * admin-bundle.js - Complete Administrative Controller for NovelMangaReader.
 * Migrated to jQuery 3.7+ with full AdminLTE and API v1 integration.
 */

window.AdminApp = (function($) {
  const ctx = window.__NMR_CONTEXT || {};
  const csrfToken = (ctx.auth && ctx.auth.csrf_token) || sessionStorage.getItem('csrf_token') || null;

  // --- UI HELPERS ---
  window.openModal = (id) => {
    const el = document.getElementById(id);
    if (!el) return;
    $('.modal-overlay, .modal').removeClass('active show');
    $(el).css('display', 'block').addClass('active show');
    if ($(el).hasClass('modal') && !$('.modal-backdrop').length) {
      $('body').append('<div class="modal-backdrop fade show"></div>').addClass('modal-open');
    }
  };

  window.closeModal = () => {
    $('.modal-overlay, .modal').removeClass('active show').css('display', 'none');
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
  };

  $(document).on('click', '.modal-overlay, .modal, .btn-close, [data-bs-dismiss="modal"]', function(e) {
    if (e.target === this || $(this).hasClass('btn-close') || $(this).attr('data-bs-dismiss') === 'modal') window.closeModal();
  });

  const api = async (path, options = {}) => {
    const method = (options.method || 'GET').toUpperCase();
    const headers = { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) };
    if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    if (csrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(method)) headers['X-CSRF-Token'] = csrfToken;

    try {
      const res = await fetch(`/api/v1${path}`, { method, credentials: 'include', headers, body: options.body });
      const payload = await res.json();
      if (!res.ok || payload.status === 'error') throw new Error(payload?.message || `HTTP ${res.status}`);
      return payload;
    } catch (e) { console.error(`Admin API Error [${path}]:`, e); throw e; }
  };

  const setHtml = (sel, html) => { const el = $(sel); if (el.length) el.html(html); };
  const setText = (sel, val) => { const el = $(sel); if (el.length) el.text(String(val)); };

  // --- MODULES ---

  const Dashboard = {
    init: function() {
      console.log("[AdminApp] Dashboard Init");
      this.loadOverview();
      $('#btn-refresh-reputation')?.on('click', () => this.loadOverview());
    },
    loadOverview: async function() {
      try {
        const res = await api('/admin/overview');
        const d = res.data || {};
        setText('#kpi-users', d.kpis?.users_total || 0);
        setText('#kpi-contents', d.kpis?.contents_total || 0);
        setText('#kpi-chapters', d.kpis?.chapters_total || 0);
        setText('#kpi-unread', d.kpis?.blogs_pending_total || 0);
        const m = d.metrics || {};
        setHtml('#metrics-top-contents', (m.top_contents_7d || []).map(c => `
          <tr>
            <td><a href="/admin/content" class="text-decoration-none">${c.title}</a></td>
            <td><small class="badge bg-light text-dark">${c.type}</small></td>
            <td class="text-end fw-bold">${c.view_count_7d}</td>
            <td class="text-end text-muted">${c.comment_count_7d || 0}</td>
          </tr>
        `).join('') || '<tr><td colspan="4" class="text-center">No data</td></tr>');
        setHtml('#metrics-funnel-health', `
          <div class="mb-2 small">Home-to-Content: ${m.funnel?.home_to_content_pct || 0}%</div>
          <div class="mb-2 small">Content-to-Chapter: ${m.funnel?.content_to_chapter_pct || 0}%</div>
          <hr class="my-2 opacity-10">
          <div class="small">Error Rate: ${m.performance_slo?.server_error_rate_pct_24h || 0}%</div>
          <div class="small">P95 Latency: ${m.performance_slo?.p95_duration_ms_24h || 0}ms</div>
        `);
        setHtml('#metrics-retention-search', `
          <div class="mb-2 small">Searches (7d): ${m.retention_search?.search_total_7d || 0}</div>
          <div class="mb-2 small">Zero Results: ${m.retention_search?.zero_result_pct_7d || 0}%</div>
          <hr class="my-2 opacity-10">
          <div class="small">D1 Retention: ${m.retention_search?.d1_retention_pct || 0}%</div>
          <div class="small">New Users (7d): ${m.retention_search?.new_users_7d || 0}</div>
        `);
      } catch (e) {}
    }
  };

  const Content = {
    _CONTENTS: [], _ALL_GENRES: [], _ALL_TAGS: [],
    _SEL_GENRES: new Set(), _SEL_TAGS: new Set(), _CRE_GENRES: new Set(), _CRE_TAGS: new Set(),

    init: function() {
      console.log("[AdminApp] Content Init");
      this.load(); this.loadTaxonomy();
      $('#btn-refresh-contents').on('click', () => this.load());
      $('#create-content-title').on('input', function() {
        $('#create-content-slug').val($(this).val().toLowerCase().trim().replace(/\s+/g, '-').replace(/[^\w-]+/g, ''));
      });

      $('#contents-list-body').on('click', 'button[data-action]', (e) => {
        const btn = e.currentTarget; const id = btn.dataset.id; const action = btn.dataset.action;
        const c = this._CONTENTS.find(x => x.id == id); if (!c) return;
        if (action === 'edit') this.openEdit(c);
        if (action === 'chapter' || action === 'add-chapter') {
          const sel = $('#chapters-content-id');
          if (sel.length) {
            if (!sel.find(`option[value="${c.id}"]`).length) sel.append(new Option(c.title, c.id));
            sel.val(c.id).trigger('change');
          }
          if (action === 'add-chapter') window.openModal('modal-create-chapter');
        }
      });

      $('#edit-content-genres-btns').on('click', 'button', (e) => {
        const id = String(e.currentTarget.dataset.id);
        if (this._SEL_GENRES.has(id)) this._SEL_GENRES.delete(id); else this._SEL_GENRES.add(id);
        this.renderTaxButtons('edit');
      });
      $('#create-content-genres-btns').on('click', 'button', (e) => {
        const id = String(e.currentTarget.dataset.id);
        if (this._CRE_GENRES.has(id)) this._CRE_GENRES.delete(id); else this._CRE_GENRES.add(id);
        this.renderTaxButtons('create');
      });

      $('#form-create-content').on('submit', async (e) => {
        e.preventDefault();
        try {
          const data = Object.fromEntries(new FormData(e.target));
          const res = await api('/admin/content', { method: 'POST', body: JSON.stringify(data) });
          if (res.data?.id) await api(`/admin/contents/${res.data.id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(this._CRE_GENRES), tags: Array.from(this._CRE_TAGS) }) });
          window.closeModal(); e.target.reset(); this._CRE_GENRES.clear(); this._CRE_TAGS.clear(); this.load();
        } catch (err) { alert(err.message); }
      });

      $('#form-edit-content').on('submit', async (e) => {
        e.preventDefault(); const fd = new FormData(e.target); const id = fd.get('id');
        try {
          await api(`/admin/content/${id}`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(fd)) });
          await api(`/admin/contents/${id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(this._SEL_GENRES), tags: Array.from(this._SEL_TAGS) }) });
          window.closeModal(); this.load();
        } catch (err) { alert(err.message); }
      });
    },
    load: async function() {
      try {
        const res = await api('/admin/content');
        this._CONTENTS = res.data?.items || res.data || [];
        setHtml('#contents-list-body', this._CONTENTS.map(c => `
          <tr><td>${c.id}</td><td><span class="badge bg-light text-dark">${c.type}</span></td><td>${c.title}</td><td><code>${c.slug}</code></td><td>${c.status}</td><td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-info" data-action="edit" data-id="${c.id}"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-primary" data-action="chapter" data-id="${c.id}"><i class="bi bi-list-ul"></i></button><button class="btn btn-outline-success" data-action="add-chapter" data-id="${c.id}"><i class="bi bi-plus-lg"></i></button></div></td></tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No contents</td></tr>');
        const sel = $('#chapters-content-id'); if (sel.length) { const cur = sel.val(); sel.html('<option value="">-- Select Series --</option>' + this._CONTENTS.map(c => `<option value="${c.id}" ${c.id == cur ? 'selected' : ''}>${c.title}</option>`).join('')); }
      } catch (e) {}
    },
    loadTaxonomy: async function() {
      try {
        const [g, t] = await Promise.all([api('/admin/genres'), api('/admin/tags')]);
        this._ALL_GENRES = g.data || []; this._ALL_TAGS = t.data || [];
        this.renderTaxButtons('create'); this.renderTaxButtons('edit');
      } catch (e) {}
    },
    renderTaxButtons: function(mode) {
      const isE = mode === 'edit'; const gS = isE ? this._SEL_GENRES : this._CRE_GENRES;
      setHtml(isE ? '#edit-content-genres-btns' : '#create-content-genres-btns', this._ALL_GENRES.map(g => `<button type="button" class="btn btn-xs ${gS.has(String(g.id)) ? 'btn-success' : 'btn-outline-secondary'} m-1" data-id="${g.id}">${g.name}</button>`).join(''));
    },
    openEdit: function(c) {
      const f = $('#form-edit-content'); if (!f.length) return;
      f.find('[name="id"]').val(c.id); f.find('[name="title"]').val(c.title); f.find('[name="slug"]').val(c.slug); f.find('[name="status"]').val(c.status); f.find('[name="description"]').val(c.description || ''); f.find('[name="author"]').val(c.author || ''); f.find('[name="artist"]').val(c.artist || ''); f.find('[name="country"]').val(c.country || ''); f.find('[name="release_year"]').val(c.release_year || '');
      this._SEL_GENRES = new Set(String(c.genre_ids || '').split(',').filter(Boolean));
      this.renderTaxButtons('edit'); window.openModal('modal-edit-content');
    }
  };

  const Chapters = {
    init: function() {
      console.log("[AdminApp] Chapters Init");
      $('#chapters-content-id').on('change', () => this.load());
      $('#btn-refresh-chapters').on('click', () => this.load());
      $('#btn-add-chapter').on('click', () => window.openModal('modal-create-chapter'));
      $('#create-chapter-type').on('change', (e) => this.toggleEditor($(e.target).val(), 'create'));
      $('#edit-chapter-type').on('change', (e) => this.toggleEditor($(e.target).val(), 'edit'));

      $('#form-create-chapter').on('submit', async (e) => {
        e.preventDefault(); const fd = new FormData(e.target); const cid = $('#chapters-content-id').val();
        const payload = Object.fromEntries(fd);
        if (fd.get('type') === 'image') payload.data = $('#create-chapter-pages').val().split('\n').map(l => l.trim()).filter(Boolean).join('|');
        else payload.data = $('#create-chapter-body').val();
        try { await api(`/admin/content/manga/slug/chapters`, { method: 'POST', body: JSON.stringify(payload) }); window.closeModal(); this.load(); } catch (err) { alert(err.message); }
      });

      $('#chapters-list-body').on('click', 'button[data-action]', async (e) => {
        const id = e.currentTarget.dataset.id; const act = e.currentTarget.dataset.action;
        if (act === 'edit') this.openEdit(id);
        if (act === 'delete' && confirm('Delete chapter?')) { try { await api(`/admin/chapters/${id}`, { method: 'DELETE' }); this.load(); } catch (e) { alert(e.message); } }
      });
    },
    load: async function() {
      const cid = $('#chapters-content-id').val(); if (!cid) return;
      try {
        const res = await api(`/admin/content/${cid}/chapters`); const items = res.data?.items || res.data || [];
        setHtml('#chapters-list-body', items.map(ch => `<tr><td>${ch.chapter_number}</td><td>${ch.title || ''}</td><td>${ch.type}</td><td><span class="badge bg-light text-dark">${ch.username || 'System'}</span></td><td><small>${(ch.created_at || '').split(' ')[0]}</small></td><td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-info" data-action="edit" data-id="${ch.id}"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger" data-action="delete" data-id="${ch.id}"><i class="bi bi-trash"></i></button></div></td></tr>`).join('') || '<tr><td colspan="6">No chapters</td></tr>');
      } catch (e) {}
    },
    openEdit: async function(id) {
      try {
        const res = await api(`/admin/chapters/${id}`); const ch = res.data; const f = $('#form-edit-chapter');
        f.find('[name="id"]').val(ch.id); f.find('[name="chapter_number"]').val(ch.chapter_number); f.find('[name="title"]').val(ch.title || ''); f.find('[name="type"]').val(ch.type);
        if (ch.type === 'image') $('#edit-chapter-pages').val((ch.data || '').split('|').join('\n')); else $('#edit-chapter-body').val(ch.data || '');
        this.toggleEditor(ch.type, 'edit'); window.openModal('modal-edit-chapter');
      } catch (e) { alert(e.message); }
    },
    toggleEditor: function(t, p) { $(`#${p}-chapter-body-wrap`).toggleClass('d-none', t === 'image'); $(`#${p}-chapter-pages-wrap`).toggleClass('d-none', t !== 'image'); }
  };

  const Blogs = {
    init: function() {
      console.log("[AdminApp] Blogs Init");
      this.load(); $('#btn-refresh-blogs-all')?.on('click', () => this.load());
      $('#all-blogs-body').on('click', 'button[data-action]', async (e) => {
        const id = e.currentTarget.dataset.id; const act = e.currentTarget.dataset.action;
        if (act === 'approve') await api(`/admin/blogs/${id}/approve`, { method: 'POST', body: '{}' });
        if (act === 'hide') await api(`/admin/blogs/${id}/hide`, { method: 'POST', body: '{}' });
        if (act === 'delete' && confirm('Delete?')) await api(`/admin/blogs/${id}`, { method: 'DELETE' });
        this.load();
      });
    },
    load: async function() {
      try {
        const res = await api('/admin/blogs'); const items = res.data?.items || res.data || [];
        setHtml('#all-blogs-body', items.map(b => `<tr><td>${b.id}</td><td>${b.title}</td><td>@${b.author_username}</td><td>${b.approved ? 'Yes' : 'No'}</td><td>${(b.created_at || '').split(' ')[0]}</td><td><div class="btn-group btn-group-sm"><button class="btn btn-outline-success" data-action="approve" data-id="${b.id}">Approve</button><button class="btn btn-outline-secondary" data-action="hide" data-id="${b.id}">Hide</button><button class="btn btn-outline-danger" data-action="delete" data-id="${b.id}">Delete</button></div></td></tr>`).join(''));
      } catch (e) {}
    }
  };

  const Users = {
    _USERS: [],
    init: function() {
      console.log("[AdminApp] Users Init");
      this.load(); 
      this.loadRoles();
      $('#btn-refresh-users')?.on('click', () => this.load());
      $('#users-list-body').on('click', 'button[data-action="edit"]', (e) => this.openEdit(e.currentTarget.dataset.id));
      $('#form-edit-user').on('submit', async (e) => {
        e.preventDefault(); const fd = new FormData(e.target);
        try { await api(`/admin/users/${fd.get('id')}`, { method: 'PUT', body: JSON.stringify({ role: fd.get('role'), is_banned: !!fd.get('is_banned'), email: fd.get('email'), bio: fd.get('bio') }) }); window.closeModal(); this.load(); } catch (e) { alert(e.message); }
      });
    },
    load: async function() {
      try {
        const res = await api('/admin/users'); this._USERS = res.data?.items || res.data || [];
        setHtml('#users-list-body', this._USERS.map(u => `<tr><td>${u.id}</td><td><b>${u.username}</b></td><td>${u.email || ''}</td><td><span class="badge bg-secondary">${this.firstRole(u)}</span></td><td class="text-end"><button class="btn btn-xs btn-outline-secondary" data-action="edit" data-id="${u.id}"><i class="bi bi-person-gear"></i></button></td></tr>`).join(''));
      } catch (e) {}
    },
    loadRoles: async function() {
      const sel = $('#edit-user-role'); if (!sel.length) return;
      try {
        const res = await api('/admin/rbac/roles');
        const items = res.data?.items || res.data || [];
        sel.html(items.map(r => `<option value="${r.slug}">${r.name || r.slug}</option>`).join(''));
      } catch (e) {}
    },
    firstRole: function(u) {
      if (u.role_names) return u.role_names.split(',')[0].trim();
      if (u.roles) return typeof u.roles === 'string' ? u.roles.split(',')[0] : (Array.isArray(u.roles) ? u.roles[0] : 'user');
      return 'user';
    },
    openEdit: function(id) {
      const u = this._USERS.find(x => x.id == id); if (!u) return;
      $('#edit-user-id').val(u.id); $('#edit-user-username').val(u.username); $('#edit-user-email').val(u.email || ''); $('#edit-user-bio').val(u.bio || ''); 
      $('#edit-user-role').val(this.firstRole(u));
      $('#edit-user-banned').prop('checked', !!u.is_banned);
      window.openModal('modal-edit-user');
    }
  };

  const Uploads = {
    init: function() {
      console.log("[AdminApp] Uploads Init");
      this.load(1); $('#refresh-uploads').on('click', () => this.load(1));
      $('#uploads-list').on('click', 'button[data-action="delete-upload"]', async (e) => { if(confirm('Delete?')) { await api(`/admin/uploads/${e.currentTarget.dataset.id}`, { method: 'DELETE' }); this.load(1); } });
    },
    load: async function(p) {
      try {
        const res = await api(`/admin/uploads?page=${p}`); const items = res.data?.items || res.data || [];
        setHtml('#uploads-list', items.map(i => `<tr><td><img src="${i.file_path}" class="img-thumbnail" style="height:40px;cursor:pointer" onclick="window.previewImg('${i.file_path}')"></td><td><small>${i.original_name}</small></td><td>${i.mime_type.split('/')[1]}</td><td>${(i.file_size/1024).toFixed(1)}KB</td><td>@${i.username || 'System'}</td><td><button class="btn btn-xs btn-outline-danger" data-id="${i.id}" data-action="delete-upload"><i class="bi bi-trash"></i></button></td></tr>`).join(''));
      } catch (e) {}
    }
  };

  const Ops = {
    init: function() {
      console.log("[AdminApp] Ops Init");
      $('#btn-run-jobs')?.on('click', async () => { await api('/admin/queue/run-once', { method: 'POST', body: '{}' }); alert('Done'); });
      $('#btn-trigger-analytics')?.on('click', async () => { await api('/admin/maintenance/analytics', { method: 'POST', body: '{}' }); alert('Analytics Triggered'); });
    }
  };

  const Logs = {
    init: function() {
      console.log("[AdminApp] Logs Init");
      this.load(); $('#btn-refresh-logs')?.on('click', () => this.load());
    },
    load: async function() {
      try {
        const res = await api('/admin/audit-logs'); const items = res.data?.items || res.data || [];
        setHtml('#logs-body', items.map(l => `<tr><td><small>${l.created_at.split(' ')[1]}</small></td><td><span class="badge bg-secondary">${l.method}</span></td><td class="truncate">${l.path}</td><td>@${l.username || 'guest'}</td></tr>`).join(''));
      } catch (e) {}
    }
  };

  window.previewImg = (url) => { $('#full-preview').attr('src', url); window.openModal('preview-modal'); };

  return {
    init: function() {
      const path = window.location.pathname; const lang = (path.split('/')[1] === 'tr' || path.split('/')[1] === 'en') ? '/' + path.split('/')[1] : '';
      const p = path.replace(lang, '');
      if (p === '/admin') Dashboard.init();
      if (p.includes('/admin/content')) { Content.init(); Chapters.init(); }
      if (p.includes('/admin/blogs')) Blogs.init();
      if (p.includes('/admin/users')) Users.init();
      if (p.includes('/admin/uploads')) Uploads.init();
      if (p.includes('/admin/ops')) Ops.init();
      if (p.includes('/admin/logs')) Logs.init();
    }
  };
})(window.jQuery);

$(function() { AdminApp.init(); });
