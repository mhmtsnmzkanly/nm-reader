/**
 * admin-bundle.js - Unified Administrative Controller for NovelMangaReader.
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

  const setHtml = (sel, html) => { const el = $(sel); if (el.elements.length) el.html(html); };
  const setText = (sel, val) => { const el = $(sel); if (el.elements.length) el.elements[0].textContent = String(val); };

  const Dashboard = {
    init: function() {
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
    _CONTENTS: [],
    init: function() {
      this.load();
      $('#btn-refresh-contents')?.on('click', () => this.load());
      
      // Slug Auto-fill
      const tIn = document.getElementById('create-content-title');
      const sIn = document.getElementById('create-content-slug');
      if (tIn && sIn) {
        tIn.addEventListener('input', () => { sIn.value = tIn.value.toLowerCase().trim().replace(/\s+/g, '-').replace(/[^\w-]+/g, ''); });
      }

      $('#contents-list-body')?.on('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const id = btn.dataset.id;
        const c = this._CONTENTS.find(x => x.id == id);
        if (!c) return;

        if (btn.dataset.action === 'edit') this.openEdit(c);
        if (btn.dataset.action === 'chapter' || btn.dataset.action === 'add-chapter') {
          const sel = document.getElementById('chapters-content-id');
          if (sel) {
            if (!Array.from(sel.options).some(o => o.value == c.id)) {
              const o = document.createElement('option'); o.value = c.id; o.textContent = c.title; sel.appendChild(o);
            }
            sel.value = c.id;
            $(sel).trigger('change');
          }
          if (btn.dataset.action === 'add-chapter') window.openModal('modal-create-chapter');
        }
      });

      $('#form-create-content')?.on('submit', async (e) => {
        e.preventDefault();
        try {
          const fd = new FormData(e.target);
          await api('/admin/content', { method: 'POST', body: JSON.stringify(Object.fromEntries(fd)) });
          window.closeModal(); e.target.reset(); this.load();
        } catch (err) { alert(err.message); }
      });

      $('#form-edit-content')?.on('submit', async (e) => {
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
      } catch (e) {}
    },
    openEdit: function(c) {
      const f = document.getElementById('form-edit-content');
      if (!f) return;
      f.elements['id'].value = c.id;
      f.elements['title'].value = c.title;
      f.elements['slug'].value = c.slug;
      f.elements['status'].value = c.status;
      f.elements['description'].value = c.description || '';
      f.elements['cover_image'].value = c.cover_image || '';
      window.openModal('modal-edit-content');
    }
  };

  const Chapters = {
    init: function() {
      $('#chapters-content-id')?.on('change', () => this.load());
      $('#btn-refresh-chapters')?.on('click', () => this.load());
      $('#btn-add-chapter')?.on('click', () => window.openModal('modal-create-chapter'));

      $('#create-chapter-type')?.on('change', (e) => this.toggleEditor(e.target.value, 'create'));
      $('#edit-chapter-type')?.on('change', (e) => this.toggleEditor(e.target.value, 'edit'));

      $('#form-create-chapter')?.on('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const cid = document.getElementById('chapters-content-id').value;
        const payload = Object.fromEntries(fd);
        if (fd.get('type') === 'image') payload.data = fd.get('pages').split('\n').map(l => l.trim()).filter(Boolean).join('|');
        try {
          await api(`/admin/content/${cid}/chapters`, { method: 'POST', body: JSON.stringify(payload) });
          window.closeModal(); e.target.reset(); this.load();
        } catch (err) { alert(err.message); }
      });

      $('#chapters-list-body')?.on('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        if (btn.dataset.action === 'delete') this.delete(btn.dataset.id);
      });
    },
    load: async function() {
      const cid = document.getElementById('chapters-content-id')?.value;
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
      document.getElementById(`${prefix}-chapter-body-wrap`)?.classList.toggle('d-none', type === 'image');
      document.getElementById(`${prefix}-chapter-pages-wrap`)?.classList.toggle('d-none', type !== 'image');
    },
    delete: async function(id) {
      if (!confirm('Delete chapter?')) return;
      try { await api(`/admin/chapters/${id}`, { method: 'DELETE' }); this.load(); } catch (e) { alert(e.message); }
    }
  };

  const Uploads = {
    init: function() {
      if (!document.getElementById('uploads-list')) return;
      this.load(1);
      $('#refresh-uploads')?.on('click', () => this.load(1));
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
    const img = document.getElementById('full-preview');
    if (img) img.src = url;
    window.openModal('preview-modal');
  };

  return {
    Modules: { Dashboard, Content, Chapters, Uploads },
    init: function() {
      const path = window.location.pathname;
      const lang = (path.split('/')[1] === 'tr' || path.split('/')[1] === 'en') ? '/' + path.split('/')[1] : '';
      
      if (path === lang + '/admin') this.Modules.Dashboard.init();
      if (path.includes('/admin/content')) { this.Modules.Content.init(); this.Modules.Chapters.init(); }
      if (path.includes('/admin/uploads')) this.Modules.Uploads.init();
      // Add other modules as needed...
    }
  };
})(window.jQuery || window.melt || window.$);

document.addEventListener('DOMContentLoaded', () => AdminApp.init());
