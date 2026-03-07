/**
 * admin-content.js - specialized logic for Administrative Content Management.
 *
 * This module manages:
 * - Content Discovery: Fetches and renders the primary content table.
 * - Series Management: Handles CRUD operations for manga and novels.
 * - Taxonomy: Dynamic genre and tag selection for series.
 * - Automation: Real-time slug generation from series titles.
 * - Orchestration: Inter-module events for chapter management and modal flows.
 */
(() => {
  const ctx = window.__NMR_CONTEXT || {};
  const csrfToken = (ctx.auth && ctx.auth.csrf_token) || sessionStorage.getItem('csrf_token') || null;

  const api = async (path, options = {}) => {
    const method = (options.method || 'GET').toUpperCase();
    const headers = Object.assign({}, options.headers || {});
    if (options.body !== undefined && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }
    if (csrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(method)) headers['X-CSRF-Token'] = csrfToken;
    const res = await fetch(`/api/v1${path}`, { method, credentials: 'include', headers, body: options.body });
    const payload = await res.json().catch(() => ({ status: 'error', error: { message: 'Invalid API response' } }));
    if (!res.ok || payload.status === 'error') {
      const msg = payload?.error?.message || payload?.message || `HTTP ${res.status}`;
      console.error('[API Error]', { path, method, status: res.status, payload });
      throw new Error(msg);
    }
    return payload;
  };

  const $ = (sel) => document.querySelector(sel);
  const setHtml = (sel, html) => { const el = $(sel); if (el) el.innerHTML = html; };
  let _ALL_GENRES = [];
  let _ALL_TAGS = [];
  let _SELECTED_GENRES = new Set();
  let _SELECTED_TAGS = new Set();
  let _CREATE_GENRES = new Set();
  let _CREATE_TAGS = new Set();
  let _CONTENTS = [];

  const slugify = (text) => {
    return (text || '')
      .toString()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .replace(/--+/g, '-')
      .slice(0, 80);
  };

  const toRouteType = (value) => String(value || '').trim().toLowerCase().replace(/_/g, '-');
  const findContentById = (id) => _CONTENTS.find((item) => String(item.id) === String(id));
  const toContentSelection = (contentId, content = null) => {
    const source = content || findContentById(contentId) || {};
    return {
      id: String(contentId || source.id || '').trim(),
      title: String(source.title || '').trim(),
      slug: String(source.slug || '').trim(),
      type: toRouteType(source.type || source.route_type || ''),
    };
  };

  const loadContents = async () => {
    if (!$('#contents-list-body')) return;
    try {
      const res = await api('/admin/contents');
      const items = (res.data || []).map((item) => ({
        ...item,
        route_type: toRouteType(item.type),
      }));
      _CONTENTS = items;
      setHtml('#contents-list-body', items.map(c => `
        <tr>
          <td>${c.id}</td>
          <td>${c.type}</td>
          <td>${c.title}</td>
          <td><code>${c.slug}</code></td>
          <td><span class="badge bg-${c.status === 'ongoing' ? 'primary' : 'success'}">${c.status}</span></td>
          <td>
            <div class="btn-group">
              <button class="btn btn-xs btn-outline-info" data-action="chapter" data-id="${c.id}">Chapters</button>
              <button class="btn btn-xs btn-outline-primary" data-action="add-chapter" data-id="${c.id}" title="Create chapter"><i class="bi bi-plus-lg"></i></button>
              <button class="btn btn-xs btn-info" data-action="edit" data-id="${c.id}"><i class="bi bi-pencil"></i></button>
            </div>
          </td>
        </tr>
      `).join('') || '<tr><td colspan="6" class="text-center">No contents found</td></tr>');
      document.dispatchEvent(new CustomEvent('nmr:admin-contents:loaded', {
        detail: {
          items: items.map((item) => ({
            id: item.id,
            title: item.title,
            slug: item.slug,
            type: item.route_type,
          })),
        },
      }));
    } catch (e) {
      setHtml('#contents-list-body', `<tr><td colspan="6" class="text-center text-danger">${e.message}</td></tr>`);
    }
  };

  const loadTaxonomy = async () => {
    try {
      const g = await api('/admin/genres');
      _ALL_GENRES = g.data || [];
      if ($('#genres-list-body')) {
        setHtml('#genres-list-body', _ALL_GENRES.map(x => `<tr><td style="width:40px">${x.id}</td><td>${x.name}</td></tr>`).join(''));
      }
      
      const t = await api('/admin/tags');
      _ALL_TAGS = t.data || [];
      if ($('#tags-list-body')) {
        setHtml('#tags-list-body', _ALL_TAGS.map(x => `<tr><td style="width:40px">${x.id}</td><td>${x.name}</td></tr>`).join(''));
      }
      
      renderCreateTaxonomyButtons();
    } catch (e) {
      if ($('#genres-list-body')) {
        setHtml('#genres-list-body', '<tr><td colspan="2" class="text-center text-danger">Error</td></tr>');
      }
    }
  };

  const renderTaxonomyButtons = () => {
    setHtml('#edit-content-genres-btns', _ALL_GENRES.map(g => {
      const active = _SELECTED_GENRES.has(String(g.id));
      return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'}" data-action="toggle-genre" data-id="${g.id}">${g.name}</button>`;
    }).join(''));
    setHtml('#edit-content-tags-btns', _ALL_TAGS.map(t => {
      const active = _SELECTED_TAGS.has(String(t.id));
      return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'}" data-action="toggle-tag" data-id="${t.id}">${t.name}</button>`;
    }).join(''));
  };

  const renderCreateTaxonomyButtons = () => {
    setHtml('#create-content-genres-btns', _ALL_GENRES.map(g => {
      const active = _CREATE_GENRES.has(String(g.id));
      return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'}" data-action="c-toggle-genre" data-id="${g.id}">${g.name}</button>`;
    }).join(''));
    setHtml('#create-content-tags-btns', _ALL_TAGS.map(t => {
      const active = _CREATE_TAGS.has(String(t.id));
      return `<button type="button" class="btn btn-xs ${active ? 'btn-success' : 'btn-outline-secondary'}" data-action="c-toggle-tag" data-id="${t.id}">${t.name}</button>`;
    }).join(''));
  };

  const toggleTax = (type, id) => {
    const set = type === 'genre' ? _SELECTED_GENRES : _SELECTED_TAGS;
    const idStr = String(id);
    if (set.has(idStr)) {
      set.delete(idStr);
    } else {
      set.add(idStr);
    }
    renderTaxonomyButtons();
  };

  const toggleCreateTax = (type, id) => {
    const set = type === 'genre' ? _CREATE_GENRES : _CREATE_TAGS;
    const idStr = String(id);
    if (set.has(idStr)) set.delete(idStr); else set.add(idStr);
    renderCreateTaxonomyButtons();
  };

  const openEditContent = (id) => {
    const c = _CONTENTS.find(x => x.id == id);
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
    _SELECTED_GENRES = new Set((String(c.genre_ids || '')).split(',').map(x => x.trim()).filter(Boolean));
    _SELECTED_TAGS = new Set((String(c.tag_ids || '')).split(',').map(x => x.trim()).filter(Boolean));
    renderTaxonomyButtons();
    new bootstrap.Modal($('#modal-edit-content')).show();
  };

  const openChapterManager = (contentId, content = null) => {
    const detail = toContentSelection(contentId, content);
    if (!detail.id) return;
    const input = $('#chapters-content-id');
    if (input) input.value = detail.id;
    document.dispatchEvent(new CustomEvent('nmr:admin-content:selected', { detail }));
  };

  const openCreateChapter = (contentId, content = null) => {
    const detail = toContentSelection(contentId, content);
    if (!detail.id) return;
    openChapterManager(detail.id, detail);
    document.dispatchEvent(new CustomEvent('nmr:admin-chapter:create', { detail }));
  };

  const promptCreateTaxonomy = async (type) => {
    const normalized = String(type || '').trim().toLowerCase();
    if (!['genre', 'tag'].includes(normalized)) return;
    const name = prompt(`Enter new ${normalized} name:`);
    if (!name) return;
    try {
      await api(`/admin/${normalized}s`, { method: 'POST', body: JSON.stringify({ name }) });
      loadTaxonomy();
    } catch (e) {
      alert(e.message);
    }
  };

  const init = () => {
    window.NMR_ADMIN_CONTENT = Object.assign(window.NMR_ADMIN_CONTENT || {}, {
      promptCreateTaxonomy,
      uploadSpecificImage: async (input, targetId, type = 'chapters') => {
        const file = input.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('type', type);
        formData.append('images[]', file);
        try {
          const res = await api(`/admin/upload-images?type=${type}`, { method: 'POST', body: formData });
          if (res.data?.paths?.length > 0) {
            document.getElementById(targetId).value = res.data.paths[0];
          }
        } catch (e) {
          console.error('[Upload Specific Error]', e);
          alert('Upload failed: ' + e.message);
        }
        input.value = '';
      },
      handleBulkUpload: async (input, type = 'chapters') => {
        let files = Array.from(input.files);
        if (files.length === 0) return;

        // Sort files by name naturally (1.png, 2.png, 10.png)
        files.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));

        const total = files.length;
        console.log(`[Bulk Upload] Starting sequential upload for ${total} files of type ${type}.`);

        const area = document.getElementById('create-chapter-pages');
        if (!area) return;

        let successCount = 0;
        let failCount = 0;

        for (let i = 0; i < total; i++) {
          const file = files[i];
          const formData = new FormData();
          formData.append('type', type);
          formData.append('images[]', file);

          try {
            console.log(`[Bulk Upload] Progress: ${i + 1}/${total} - Uploading ${file.name}...`);
            const res = await api(`/admin/upload-images?type=${type}`, { method: 'POST', body: formData });

            if (res.data?.paths?.length > 0) {
              const existing = area.value.trim();
              const newPath = res.data.paths[0];
              area.value = existing ? existing + '\n' + newPath : newPath;
              successCount++;
            }
          } catch (e) {
            console.error(`[Bulk Upload] Failed file: ${file.name}`, e);
            failCount++;
          }
        }

        alert(`Upload complete!\nSuccess: ${successCount}\nFailed: ${failCount}`);
        input.value = '';
      },
    });

    loadContents();
    loadTaxonomy();
    $('#btn-refresh-contents')?.addEventListener('click', loadContents);
    $('#btn-refresh-contents')?.addEventListener('click', loadTaxonomy);

    $('#contents-list-body')?.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const action = btn.dataset.action;
      const content = findContentById(btn.dataset.id);
      if (action === 'edit') openEditContent(btn.dataset.id);
      if (action === 'chapter') openChapterManager(btn.dataset.id, content || null);
      if (action === 'add-chapter') openCreateChapter(btn.dataset.id, content || null);
    });

    $('#edit-content-genres-btns')?.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action="toggle-genre"]');
      if (btn) toggleTax('genre', btn.dataset.id);
    });
    $('#edit-content-tags-btns')?.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action="toggle-tag"]');
      if (btn) toggleTax('tag', btn.dataset.id);
    });

    $('#form-create-content')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        const payload = Object.fromEntries(new FormData(e.target));
        const createRes = await api('/admin/content', { method: 'POST', body: JSON.stringify(payload) });
        if (createRes?.data?.id) {
          await api(`/admin/contents/${createRes.data.id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(_CREATE_GENRES), tags: Array.from(_CREATE_TAGS) }) });
        }
        bootstrap.Modal.getInstance($('#modal-create-content')).hide();
        e.target.reset();
        _CREATE_GENRES = new Set();
        _CREATE_TAGS = new Set();
        renderCreateTaxonomyButtons();
        loadContents();
      } catch (err) { alert(err.message); }
    });

    $('#form-edit-content')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const id = fd.get('id');
      try {
        await api(`/admin/content/${id}`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(fd)) });
        await api(`/admin/contents/${id}/taxonomy`, { method: 'PUT', body: JSON.stringify({ genres: Array.from(_SELECTED_GENRES), tags: Array.from(_SELECTED_TAGS) }) });
        bootstrap.Modal.getInstance($('#modal-edit-content')).hide();
        loadContents();
        if (typeof showPopup === 'function') {
          showPopup('Content saved', 'success');
        }
      } catch (err) {
        if (typeof showPopup === 'function') {
          showPopup(err.message || 'Failed to save content', 'error');
        } else {
          alert(err.message);
        }
      }
    });

    $('#create-content-genres-btns')?.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action=\"c-toggle-genre\"]');
      if (btn) toggleCreateTax('genre', btn.dataset.id);
    });
    $('#create-content-tags-btns')?.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action=\"c-toggle-tag\"]');
      if (btn) toggleCreateTax('tag', btn.dataset.id);
    });

    const titleInput = $('#create-content-title');
    const slugInput = $('#create-content-slug');
    if (titleInput && slugInput) {
      let userEditedSlug = false;
      slugInput.addEventListener('input', () => { userEditedSlug = slugInput.value.trim() !== ''; });
      titleInput.addEventListener('input', () => {
        if (userEditedSlug) return;
        slugInput.value = slugify(titleInput.value);
      });
    }
  };

  document.addEventListener('DOMContentLoaded', init);
})();
