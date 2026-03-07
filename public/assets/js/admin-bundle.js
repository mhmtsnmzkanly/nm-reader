/**
 * admin-bundle.js - Unified Administrative Controller for NovelMangaReader.
 * Fully optimized for jQuery 3.7+ and AdminLTE Compatibility.
 */

window.AdminApp = (function($) {
  const ctx = window.__NMR_CONTEXT || {};
  const csrfToken = (ctx.auth && ctx.auth.csrf_token) || sessionStorage.getItem('csrf_token') || null;

  // Global Modal System
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

  $(document).on('click', '.modal-overlay, .modal', function(e) {
    if (e.target === this || $(e.target).hasClass('modal-close') || $(e.target).attr('data-bs-dismiss') === 'modal' || $(e.target).hasClass('btn-close')) {
      window.closeModal();
    }
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
            <td class="text-end fw-bold">${c.view_count_7d}</td>
            <td class="text-end text-muted">${c.comment_count_7d || 0}</td>
          </tr>
        `).join('') || '<tr><td colspan="3" class="text-center">No data</td></tr>');
      } catch (e) {}
    }
  };

  const Content = {
    _CONTENTS: [],
    _ALL_GENRES: [],
    _ALL_TAGS: [],
    _SELECTED_GENRES: new Set(),
    _SELECTED_TAGS: new Set(),
    _CREATE_GENRES: new Set(),
    _CREATE_TAGS: new Set(),

    init: function() {
      this.load();
      this.loadTaxonomy();
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
            if (!sel.find(`option[value="${c.id}"]`).length) sel.append(new Option(c.title, c.id));
            sel.val(c.id).trigger('change');
          }
          if (action === 'add-chapter') {
            $('#create-chapter-content').val(c.title);
            window.openModal('modal-create-chapter');
          }
        }
      });

      // Taxonomy Button Clicks
      $('#edit-content-genres-btns').on('click', 'button', (e) => {
        const id = String(e.currentTarget.dataset.id);
        if (this._SELECTED_GENRES.has(id)) this._SELECTED_GENRES.delete(id); else this._SELECTED_GENRES.add(id);
        this.renderTaxonomyButtons('edit');
      });
      $('#edit-content-tags-btns').on('click', 'button', (e) => {
        const id = String(e.currentTarget.dataset.id);
        if (this._SELECTED_TAGS.has(id)) this._SELECTED_TAGS.delete(id); else this._SELECTED_TAGS.add(id);
        this.renderTaxonomyButtons('edit');
      });
      $('#create-content-genres-btns').on('click', 'button', (e) => {
        const id = String(e.currentTarget.dataset.id);
        if (this._CREATE_GENRES.has(id)) this._CREATE_GENRES.delete(id); else this._CREATE_GENRES.add(id);
        this.renderTaxonomyButtons('create');
      });
      $('#create-content-tags-btns').on('click', 'button', (e) => {
        const id = String(e.currentTarget.dataset.id);
        if (this._CREATE_TAGS.has(id)) this._CREATE_TAGS.delete(id); else this._CREATE_TAGS.add(id);
        this.renderTaxonomyButtons('create');
      });

      $('#form-create-content').on('submit', async (e) => {
        e.preventDefault();
        try {
          const data = Object.fromEntries(new FormData(e.target));
          const res = await api('/admin/content', { method: 'POST', body: JSON.stringify(data) });
          if (res.data?.id) {
            await api(`/admin/contents/${res.data.id}/taxonomy`, { 
              method: 'PUT', 
              body: JSON.stringify({ genres: Array.from(this._CREATE_GENRES), tags: Array.from(this._CREATE_TAGS) }) 
            });
          }
          window.closeModal(); e.target.reset(); 
          this._CREATE_GENRES.clear(); this._CREATE_TAGS.clear();
          this.load();
        } catch (err) { alert(err.message); }
      });

      $('#form-edit-content').on('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const id = fd.get('id');
        try {
          await api(`/admin/content/${id}`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(fd)) });
          await api(`/admin/contents/${id}/taxonomy`, { 
            method: 'PUT', 
            body: JSON.stringify({ genres: Array.from(this._SELECTED_GENRES), tags: Array.from(this._SELECTED_TAGS) }) 
          });
          window.closeModal(); this.load();
        } catch (err) { alert(err.message); }
      });
    },
    load: async function() {
      try {
        const res = await api('/admin/content');
        this._CONTENTS = res.data?.items || res.data || [];
        if (!Array.isArray(this._CONTENTS)) this._CONTENTS = [];

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
      } catch (e) { setHtml('#contents-list-body', `<tr><td colspan="6" class="text-center text-danger">${e.message}</td></tr>`); }
    },
    loadTaxonomy: async function() {
      try {
        const [g, t] = await Promise.all([api('/admin/genres'), api('/admin/tags')]);
        this._ALL_GENRES = g.data || [];
        this._ALL_TAGS = t.data || [];
        this.renderTaxonomyButtons('create');
        this.renderTaxonomyButtons('edit');
        
        // Also populate the reference tables below if they exist
        setHtml('#genres-list-body', this._ALL_GENRES.map(x => `<tr><td>${x.id}</td><td>${x.name}</td></tr>`).join(''));
        setHtml('#tags-list-body', this._ALL_TAGS.map(x => `<tr><td>${x.id}</td><td>${x.name}</td></tr>`).join(''));
      } catch (e) {}
    },
    renderTaxonomyButtons: function(mode = 'edit') {
      const isEdit = mode === 'edit';
      const genSet = isEdit ? this._SELECTED_GENRES : this._CREATE_GENRES;
      const tagSet = isEdit ? this._SELECTED_TAGS : this._CREATE_TAGS;
      const prefix = isEdit ? '#edit-content' : '#create-content';

      setHtml(`${prefix}-genres-btns`, this._ALL_GENRES.map(g => {
        const active = genSet.has(String(g.id));
        return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'} m-1" data-id="${g.id}">${g.name}</button>`;
      }).join(''));

      setHtml(`${prefix}-tags-btns`, this._ALL_TAGS.map(t => {
        const active = tagSet.has(String(t.id));
        return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'} m-1" data-id="${t.id}">${t.name}</button>`;
      }).join(''));
    },
    openEdit: function(c) {
      const f = $('#form-edit-content');
      if (!f.length) return;
      f.find('[name="id"]').val(c.id);
      f.find('[name="title"]').val(c.title);
      f.find('[name="alternative_titles"]').val(c.alternative_titles || '');
      f.find('[name="slug"]').val(c.slug);
      f.find('[name="status"]').val(c.status);
      f.find('[name="description"]').val(c.description || '');
      f.find('[name="cover_image"]').val(c.cover_image || '');
      f.find('[name="author"]').val(c.author || '');
      f.find('[name="artist"]').val(c.artist || '');
      f.find('[name="country"]').val(c.country || '');
      f.find('[name="release_year"]').val(c.release_year || '');
      
      this._SELECTED_GENRES = new Set(String(c.genre_ids || '').split(',').filter(Boolean));
      this._SELECTED_TAGS = new Set(String(c.tag_ids || '').split(',').filter(Boolean));
      this.renderTaxonomyButtons('edit');
      window.openModal('modal-edit-content');
    },
    promptCreateTaxonomy: async function(type) {
      const name = prompt(`New ${type} name:`);
      if (!name) return;
      try {
        await api(`/admin/series_${type}s`, { method: 'POST', body: JSON.stringify({ name }) });
        this.loadTaxonomy();
      } catch (e) { alert(e.message); }
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
      let files = Array.from(input.files); if (files.length === 0) return;
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
      alert(`Upload complete! Success: ${ok}, Fail: ${err}`); input.value = '';
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
        if (fd.get('type') === 'image') payload.data = $('#create-chapter-pages').val().split('\n').map(l => l.trim()).filter(Boolean).join('|');
        else payload.data = $('#create-chapter-body').val();
        
        try {
          await api(`/admin/content/${cid}/chapters`, { method: 'POST', body: JSON.stringify(payload) });
          window.closeModal(); e.target.reset(); this.load();
        } catch (err) { alert(err.message); }
      });

      $('#form-edit-chapter').on('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = Object.fromEntries(fd);
        if (fd.get('type') === 'image') payload.data = $('#edit-chapter-pages').val().split('\n').map(l => l.trim()).filter(Boolean).join('|');
        else payload.data = $('#edit-chapter-body').val();
        
        try {
          await api(`/admin/chapters/${fd.get('id')}`, { method: 'PUT', body: JSON.stringify(payload) });
          window.closeModal(); this.load();
        } catch (err) { alert(err.message); }
      });

      $('#chapters-list-body').on('click', 'button[data-action]', async (e) => {
        const btn = e.currentTarget;
        const id = btn.dataset.id;
        if (btn.dataset.action === 'edit') this.openEdit(id);
        if (btn.dataset.action === 'delete') {
          if (!confirm('Delete chapter?')) return;
          try { await api(`/admin/chapters/${id}`, { method: 'DELETE' }); this.load(); } catch (err) { alert(err.message); }
        }
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
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" data-action="edit" data-id="${ch.id}"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-danger" data-action="delete" data-id="${ch.id}"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No chapters</td></tr>');
      } catch (e) {}
    },
    openEdit: async function(id) {
      try {
        const res = await api(`/admin/chapters/${id}`);
        const ch = res.data;
        const f = $('#form-edit-chapter');
        if (!f.length) return;

        f.find('[name="id"]').val(ch.id);
        f.find('[name="chapter_number"]').val(ch.chapter_number);
        f.find('[name="title"]').val(ch.title || '');
        f.find('[name="type"]').val(ch.type);
        
        if (ch.type === 'image') {
          $('#edit-chapter-pages').val((ch.data || '').split('|').join('\n'));
          $('#edit-chapter-body').val('');
        } else {
          $('#edit-chapter-body').val(ch.data || '');
          $('#edit-chapter-pages').val('');
        }
        
        this.toggleEditor(ch.type, 'edit');
        window.openModal('modal-edit-chapter');
      } catch (e) { alert(e.message); }
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
        setHtml('#uploads-list', (res.data || []).map(item => `
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

  const Comments = {
    init: function() {
      if (!$('#comments-list-body').length) return;
      console.log("[AdminApp] Initializing Comments...");
      this.load();
      $('#btn-refresh-comments').on('click', () => this.load());
      $('#comments-list-body').on('click', 'button[data-action="delete"]', (e) => {
        this.delete(e.currentTarget.dataset.id);
      });
    },
    load: async function() {
      try {
        const res = await api('/admin/comments');
        const items = res.data?.items || res.data || [];
        setHtml('#comments-list-body', items.map(c => {
          const targetTitle = c.content_title ? `${c.content_title} (Ch. ${c.chapter_number || '?'})` : (c.blog_title || 'N/A');
          return `
            <tr>
              <td><strong class="text-sm">@${c.username}</strong></td>
              <td style="max-width:400px"><div class="text-truncate" title="${c.body}">${c.body}</div></td>
              <td><small class="text-muted">${targetTitle}</small></td>
              <td><small>${(c.created_at || '').split(' ')[1] || (c.created_at || '')}</small></td>
              <td>
                <button class="btn btn-xs btn-outline-danger" data-action="delete" data-id="${c.id}">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          `;
        }).join('') || '<tr><td colspan="5" class="text-center">No comments found</td></tr>');
      } catch (e) { setHtml('#comments-list-body', `<tr><td colspan="5" class="text-center text-danger">${e.message}</td></tr>`); }
    },
    delete: async function(id) {
      if (!confirm('Delete comment?')) return;
      try { await api(`/admin/comments/${id}`, { method: 'DELETE' }); this.load(); } catch (e) { alert(e.message); }
    }
  };

  window.previewImg = (url) => { $('#full-preview').attr('src', url); window.openModal('preview-modal'); };

  return {
    Modules: { Dashboard, Content, Chapters, Uploads, Comments },
    formatDuration: (s) => { if (!s || s <= 0) return '0s'; const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60); return h > 0 ? `${h}h ${m}m` : (m > 0 ? `${m}m` : `${s}s`); },
    init: function() {
      const path = window.location.pathname;
      const lang = (path.split('/')[1] === 'tr' || path.split('/')[1] === 'en') ? '/' + path.split('/')[1] : '';
      if (path === lang + '/admin') this.Modules.Dashboard.init();
      if (path.includes('/admin/content')) { this.Modules.Content.init(); this.Modules.Chapters.init(); }
      if (path.includes('/admin/uploads')) this.Modules.Uploads.init();
      if (path.includes('/admin/comments')) this.Modules.Comments.init();
    }
  };
})(window.jQuery);

$(function() { AdminApp.init(); });
