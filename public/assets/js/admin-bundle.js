/**
 * admin-bundle.js - Unified Administrative Controller for NovelMangaReader.
 * Fully migrated to jQuery 3.7+
 */

window.AdminApp = (function($) {
  const ctx = window.__NMR_CONTEXT || {};
  const csrfToken = (ctx.auth && ctx.auth.csrf_token) || sessionStorage.getItem('csrf_token') || null;

  // Global Modal System
  window.openModal = (id) => {
    $('.modal-overlay').removeClass('active');
    setTimeout(() => {
      const el = document.getElementById(id);
      if (el) el.classList.add('active');
    }, 10);
  };
  window.closeModal = () => $('.modal-overlay').removeClass('active');
  $('body').on('click', '.modal-overlay', function(e) { if (e.target === this) window.closeModal(); });

  const api = async (path, options = {}) => {
    const method = (options.method || 'GET').toUpperCase();
    const headers = Object.assign({}, options.headers || {});
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

  const Dashboard = {
    init: function() {
      this.loadOverview();
      $('#btn-refresh-reputation').on('click', () => this.loadOverview());
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
    _CONTENTS: [],
    init: function() {
      this.load();
      $('#btn-refresh-contents').on('click', () => this.load());
      
      $('#create-content-title').on('input', function() {
        $('#create-content-slug').val($(this).val().toLowerCase().trim().replace(/\s+/g, '-').replace(/[^\w-]+/g, ''));
      });

      $('#contents-list-body').on('click', 'button[data-action]', (e) => {
        const btn = e.currentTarget;
        const id = btn.dataset.id;
        const action = btn.dataset.action;
        const c = this._CONTENTS.find(x => x.id == id);
        if (!c) return;

        if (action === 'edit') this.openEdit(c);
        if (action === 'chapter' || action === 'add-chapter') {
          const sel = $('#chapters-content-id');
          if (sel.length) {
            if (!sel.find(`option[value="${c.id}"]`).length) {
              sel.append(new Option(c.title, c.id));
            }
            sel.val(c.id).trigger('change');
          }
          if (action === 'add-chapter') window.openModal('modal-create-chapter');
        }
      });

      $('#form-create-content').on('submit', async (e) => {
        e.preventDefault();
        try {
          const data = Object.fromEntries(new FormData(e.target));
          await api('/admin/content', { method: 'POST', body: JSON.stringify(data) });
          window.closeModal(); e.target.reset(); this.load();
        } catch (err) { alert(err.message); }
      });

      $('#form-edit-content').on('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
          await api(`/admin/content/${fd.get('id')}`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(fd)) });
          window.closeModal(); this.load();
        } catch (err) { alert(err.message); }
      });
    },
    load: async function() {
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
        
        const sel = $('#chapters-content-id');
        if (sel.length) {
          const cur = sel.val();
          sel.html('<option value="">-- Select Series --</option>' + this._CONTENTS.map(c => `<option value="${c.id}" ${c.id == cur ? 'selected' : ''}>${c.title}</option>`).join(''));
        }
      } catch (e) {}
    },
    openEdit: function(c) {
      const f = $('#form-edit-content');
      if (!f.length) return;
      f.find('[name="id"]').val(c.id);
      f.find('[name="title"]').val(c.title);
      f.find('[name="slug"]').val(c.slug);
      f.find('[name="status"]').val(c.status);
      f.find('[name="description"]').val(c.description || '');
      f.find('[name="cover_image"]').val(c.cover_image || '');
      window.openModal('modal-edit-content');
    }
  };

  const Chapters = {
    init: function() {
      $('#chapters-content-id').on('change', () => this.load());
      $('#btn-refresh-chapters').on('click', () => this.load());
      $('#btn-add-chapter').on('click', () => window.openModal('modal-create-chapter'));

      $('#create-chapter-type').on('change', (e) => this.toggleEditor($(e.target).val(), 'create'));
      $('#edit-chapter-type').on('change', (e) => this.toggleEditor($(e.target).val(), 'edit'));

      $('#form-create-chapter').on('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const cid = $('#chapters-content-id').val();
        const payload = Object.fromEntries(fd);
        if (fd.get('type') === 'image') payload.data = fd.get('pages').split('\n').map(l => l.trim()).filter(Boolean).join('|');
        try {
          await api(`/admin/content/${cid}/chapters`, { method: 'POST', body: JSON.stringify(payload) });
          window.closeModal(); e.target.reset(); this.load();
        } catch (err) { alert(err.message); }
      });

      $('#chapters-list-body').on('click', 'button[data-action="delete"]', async (e) => {
        if (!confirm('Delete chapter?')) return;
        try { 
          await api(`/admin/chapters/${e.currentTarget.dataset.id}`, { method: 'DELETE' }); 
          this.load(); 
        } catch (err) { alert(err.message); }
      });
    },
    load: async function() {
      const cid = $('#chapters-content-id').val();
      if (!cid) return;
      try {
        const res = await api(`/admin/content/${cid}/chapters`);
        const items = res.data?.items || res.data || [];
        setHtml('#chapters-list-body', items.map(ch => `
          <tr>
            <td>${ch.chapter_number}</td>
            <td>${ch.title || ''}</td>
            <td>${ch.type}</td>
            <td><span class="badge bg-light text-dark">${ch.username || 'System'}</span></td>
            <td><small>${(ch.created_at || '').split(' ')[0]}</small></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${ch.id}"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No chapters</td></tr>');
      } catch (e) {}
    },
    toggleEditor: function(type, prefix) {
      $(`#${prefix}-chapter-body-wrap`).toggleClass('d-none', type === 'image');
      $(`#${prefix}-chapter-pages-wrap`).toggleClass('d-none', type !== 'image');
    }
  };

  const Uploads = {
    init: function() {
      if (!$('#uploads-list').length) return;
      this.load(1);
      $('#refresh-uploads').on('click', () => this.load(1));
    },
    load: async function(page) {
      try {
        const res = await api(`/admin/uploads?page=${page}`);
        const items = res.data || [];
        setHtml('#uploads-list', items.map(item => `
          <tr>
            <td><img src="${item.file_path}" class="img-thumbnail" style="height:40px;cursor:pointer" onclick="window.previewImg('${item.file_path}')"></td>
            <td><small>${item.original_name}</small></td>
            <td>${item.mime_type.split('/')[1]}</td>
            <td>${(item.file_size/1024).toFixed(1)}KB</td>
            <td>${item.username || 'System'}</td>
            <td><button class="btn btn-xs btn-danger" onclick="AdminApp.Modules.Uploads.delete(${item.id})"><i class="bi bi-trash"></i></button></td>
          </tr>
        `).join(''));
      } catch (e) {}
    },
    delete: async function(id) {
      if (confirm('Delete?')) { await api(`/admin/uploads/${id}`, { method: 'DELETE' }); this.load(1); }
    }
  };

  window.previewImg = (url) => { 
    $('#full-preview').attr('src', url);
    window.openModal('preview-modal');
  };

  return {
    Modules: { Dashboard, Content, Chapters, Uploads },
    formatDuration: (s) => { if (!s || s <= 0) return '0s'; const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60); return h > 0 ? `${h}h ${m}m` : (m > 0 ? `${m}m` : `${s}s`); },
    init: function() {
      const path = window.location.pathname;
      const lang = (path.split('/')[1] === 'tr' || path.split('/')[1] === 'en') ? '/' + path.split('/')[1] : '';
      if (path === lang + '/admin') this.Modules.Dashboard.init();
      if (path.includes('/admin/content')) { this.Modules.Content.init(); this.Modules.Chapters.init(); }
      if (path.includes('/admin/uploads')) this.Modules.Uploads.init();
    }
  };
})(window.jQuery);

$(function() { AdminApp.init(); });
