/**
 * admin-chapters.js - Administrative Controller for Chapter Management.
 *
 * This module manages the lifecycle of individual chapters (Novels & Manga).
 * Complex features include:
 * - Content Resolution: Maintains a dynamic lookup map to resolve series by ID/Slug/Type.
 * - Multi-Mode Editor: Swaps between Markdown (text) and Image Path (list) editors.
 * - Orchestration: Listens for events from 'admin-content.js' to auto-select series.
 * - UI Sync: Handles modal pre-population, validation, and table refreshes.
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
  const setValue = (sel, value) => {
    const el = $(sel);
    if (el) el.value = value;
  };
  const chapterPlaceholder = () => '<tr><td colspan="5"></td></tr>';
  const getContentId = () => {
    const input = $('#chapters-content-id');
    if (!input) return '';
    return String(input.value || '').trim();
  };
  const toRouteType = (value) => String(value || '').trim().toLowerCase().replace(/_/g, '-');
  const parsePagesInput = (value) => String(value || '')
    .split(/\r\n|\r|\n/)
    .map((line) => line.trim())
    .filter(Boolean);
  const toggleEditorByType = (type, prefix = 'edit') => {
    const bodyWrap = $(`#${prefix}-chapter-body-wrap`);
    const pagesWrap = $(`#${prefix}-chapter-pages-wrap`);
    const normalized = String(type || 'text').toLowerCase();
    if (normalized === 'image') {
      bodyWrap?.classList.add('d-none');
      pagesWrap?.classList.remove('d-none');
      return;
    }
    bodyWrap?.classList.remove('d-none');
    pagesWrap?.classList.add('d-none');
  };
  const hideModal = (selector) => {
    const el = $(selector);
    if (!el) return;
    const instance = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
    instance.hide();
  };

  const contentLookup = new Map();
  let contentOptions = [];
  let selectedContent = null;

  const formatTypeLabel = (value) => {
    const normalized = toRouteType(value);
    if (normalized === '') return 'Unknown';
    return normalized
      .split('-')
      .filter(Boolean)
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  };
  const formatContentLabel = (content) => {
    const typeLabel = formatTypeLabel(content.type);
    const name = String(content.title || content.slug || content.id).trim();
    return `${typeLabel} - ${name}`;
  };
  const renderContentSelectOptions = () => {
    const select = $('#chapters-content-id');
    if (!select) return;

    const currentId = String(select.value || '').trim();
    const selectedId = String(selectedContent?.id || '').trim();
    const preferredId = currentId || selectedId;

    select.innerHTML = '';
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = '';
    select.appendChild(empty);

    contentOptions.forEach((item) => {
      const opt = document.createElement('option');
      opt.value = item.id;
      opt.textContent = formatContentLabel(item);
      select.appendChild(opt);
    });

    if (preferredId !== '' && contentOptions.some((item) => item.id === preferredId)) {
      select.value = preferredId;
    }
  };

  const saveContentLookup = (raw) => {
    const id = String(raw?.id || '').trim();
    if (id === '') return null;
    const next = {
      id,
      title: String(raw?.title || '').trim(),
      slug: String(raw?.slug || '').trim(),
      type: toRouteType(raw?.type || ''),
    };
    const prev = contentLookup.get(id) || {};
    const merged = {
      id,
      title: next.title || String(prev.title || ''),
      slug: next.slug || String(prev.slug || ''),
      type: next.type || String(prev.type || ''),
    };
    contentLookup.set(id, merged);
    return merged;
  };
  const upsertContentOption = (raw) => {
    const saved = saveContentLookup(raw);
    if (!saved) return null;
    const idx = contentOptions.findIndex((item) => item.id === saved.id);
    if (idx === -1) {
      contentOptions.push(saved);
      return saved;
    }
    contentOptions[idx] = {
      ...contentOptions[idx],
      ...saved,
    };
    return contentOptions[idx];
  };

  const selectContent = (raw) => {
    const saved = saveContentLookup(raw || {});
    if (saved) {
      selectedContent = { ...saved };
      return selectedContent;
    }
    const id = String(raw?.id || '').trim();
    if (id !== '') {
      selectedContent = { id, title: '', slug: '', type: '' };
      return selectedContent;
    }
    selectedContent = null;
    return null;
  };

  const resolveContent = (contentId = '') => {
    const id = String(contentId || getContentId()).trim();
    if (id === '') return null;
    const fromLookup = contentLookup.get(id) || {};
    const fromSelected = selectedContent && selectedContent.id === id ? selectedContent : {};
    return {
      id,
      title: String(fromSelected.title || fromLookup.title || '').trim(),
      slug: String(fromSelected.slug || fromLookup.slug || '').trim(),
      type: toRouteType(fromSelected.type || fromLookup.type || ''),
    };
  };

  const syncSelectedFromInput = () => {
    const id = getContentId();
    if (id === '') {
      selectedContent = null;
      return;
    }
    const known = contentLookup.get(id);
    if (known) {
      selectedContent = { ...known };
      return;
    }
    if (!selectedContent || selectedContent.id !== id) {
      selectedContent = { id, title: '', slug: '', type: '' };
    }
  };

  const loadChapters = async () => {
    const contentId = getContentId();
    if (!contentId) {
      setHtml('#chapters-list-body', chapterPlaceholder());
      return;
    }
    try {
      const res = await api(`/admin/content/${contentId}/chapters`);
      const items = res.data || [];
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
    } catch (e) {
      setHtml('#chapters-list-body', `<tr><td colspan="6" class="text-center text-danger">${e.message}</td></tr>`);
    }
  };

  const deleteChapter = async (id) => {
    if (!confirm('Delete this chapter?')) return;
    await api(`/admin/chapters/${id}`, { method: 'DELETE' });
    loadChapters();
  };

  const openEditChapter = async (btn) => {
    const chapterId = String(btn.dataset.id || '').trim();
    if (chapterId === '') return;

    try {
      const res = await api(`/admin/chapters/${chapterId}`);
      const chapter = res.data || {};
      const chapterType = String(chapter.type || btn.dataset.type || 'text');

      $('#edit-chapter-id').value = chapter.id || chapterId;
      $('#edit-chapter-number').value = chapter.chapter_number || btn.dataset.num || '';
      $('#edit-chapter-title').value = chapter.title || btn.dataset.title || '';
      $('#edit-chapter-type').value = chapterType;
      $('#edit-chapter-body').value = chapter.body || '';
      $('#edit-chapter-pages').value = Array.isArray(chapter.pages) ? chapter.pages.join('\n') : '';
      toggleEditorByType(chapterType);

      new bootstrap.Modal($('#modal-edit-chapter')).show();
    } catch (e) {
      alert(e.message || 'Failed to load chapter detail');
    }
  };

  const openCreateChapter = (raw = null) => {
    if (raw && raw.id) {
      upsertContentOption(raw);
      selectContent(raw);
      renderContentSelectOptions();
      setValue('#chapters-content-id', String(raw.id || '').trim());
    } else {
      syncSelectedFromInput();
    }

    const content = resolveContent();
    if (!content || content.id === '') {
      alert('Select a content first.');
      return;
    }
    if (content.slug === '' || content.type === '') {
      alert('Cannot resolve content type/slug. Select the content from the list first.');
      return;
    }

    $('#form-create-chapter')?.reset();
    setValue('#create-chapter-content-id', content.id);
    setValue('#create-chapter-content-type', content.type);
    setValue('#create-chapter-content-slug', content.slug);
    setValue(
      '#create-chapter-content',
      `${content.title ? `${content.title} (${content.id})` : content.id} - ${content.type}/${content.slug}`
    );
    setValue('#create-chapter-body', '');
    setValue('#create-chapter-pages', '');
    setValue('#create-chapter-type', 'text');
    toggleEditorByType('text', 'create');
    new bootstrap.Modal($('#modal-create-chapter')).show();
  };

  const init = () => {
    const contentInput = $('#chapters-content-id');
    renderContentSelectOptions();
    syncSelectedFromInput();
    if (contentInput && contentInput.value.trim() !== '') {
      loadChapters();
    } else {
      setHtml('#chapters-list-body', chapterPlaceholder());
    }

    $('#btn-add-chapter')?.addEventListener('click', () => openCreateChapter());
    $('#btn-refresh-chapters')?.addEventListener('click', loadChapters);
    contentInput?.addEventListener('change', () => {
      syncSelectedFromInput();
      loadChapters();
    });

    $('#chapters-list-body')?.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const action = btn.dataset.action;
      if (action === 'edit') void openEditChapter(btn);
      if (action === 'delete') deleteChapter(btn.dataset.id);
    });

    $('#edit-chapter-type')?.addEventListener('change', (e) => {
      toggleEditorByType(e.target.value, 'edit');
    });
    $('#create-chapter-type')?.addEventListener('change', (e) => {
      toggleEditorByType(e.target.value, 'create');
    });
    toggleEditorByType($('#edit-chapter-type')?.value || 'text', 'edit');
    toggleEditorByType($('#create-chapter-type')?.value || 'text', 'create');

    $('#form-create-chapter')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const contentId = String($('#create-chapter-content-id')?.value || '').trim();
      const contentType = toRouteType($('#create-chapter-content-type')?.value || '');
      const contentSlug = String($('#create-chapter-content-slug')?.value || '').trim();
      const type = String(fd.get('type') || 'text').toLowerCase();
      const payload = {
        chapter_number: String(fd.get('chapter_number') || '').trim(),
        title: String(fd.get('title') || '').trim(),
        type,
      };

      if (contentId === '' || contentType === '' || contentSlug === '') {
        alert('Content identity is missing. Select content from the list and try again.');
        return;
      }
      if (payload.chapter_number === '') {
        alert('Chapter number is required.');
        return;
      }
      if (type === 'image') {
        const pages = parsePagesInput($('#create-chapter-pages')?.value);
        if (pages.length === 0) {
          alert('At least one image path is required.');
          return;
        }
        payload.pages = pages;
      } else {
        const body = String($('#create-chapter-body')?.value || '').trim();
        if (body === '') {
          alert('Body is required for text chapters.');
          return;
        }
        payload.body = body;
      }

      try {
        await api(`/admin/content/${encodeURIComponent(contentType)}/${encodeURIComponent(contentSlug)}/chapters`, {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        hideModal('#modal-create-chapter');
        setValue('#chapters-content-id', contentId);
        syncSelectedFromInput();
        loadChapters();
      } catch (err) {
        alert(err.message);
      }
    });

    $('#form-edit-chapter')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const id = fd.get('id');
      const type = String(fd.get('type') || 'text').toLowerCase();
      const payload = {
        chapter_number: String(fd.get('chapter_number') || '').trim(),
        title: String(fd.get('title') || '').trim(),
        type,
      };
      if (type === 'image') {
        const pages = parsePagesInput($('#edit-chapter-pages')?.value);
        if (pages.length === 0) {
          alert('At least one image path is required.');
          return;
        }
        payload.pages = pages;
      } else {
        const body = String($('#edit-chapter-body')?.value || '').trim();
        if (body === '') {
          alert('Body is required for text chapters.');
          return;
        }
        payload.body = body;
      }
      try {
        await api(`/admin/chapters/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
        hideModal('#modal-edit-chapter');
        loadChapters();
      } catch (err) { alert(err.message); }
    });

    document.addEventListener('nmr:admin-contents:loaded', (e) => {
      const items = Array.isArray(e?.detail?.items) ? e.detail.items : [];
      contentOptions = items
        .map((item) => saveContentLookup(item))
        .filter((item) => item !== null);
      renderContentSelectOptions();
      syncSelectedFromInput();
      if (getContentId() !== '') {
        loadChapters();
      }
    });

    document.addEventListener('nmr:admin-content:selected', (e) => {
      const detail = e?.detail || {};
      const id = String(detail.id || '').trim();
      if (!id) return;
      upsertContentOption(detail);
      selectContent(detail);
      renderContentSelectOptions();
      if (contentInput) contentInput.value = id;
      loadChapters();
      contentInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      contentInput?.focus();
    });

    document.addEventListener('nmr:admin-chapter:create', (e) => {
      const detail = e?.detail || {};
      const id = String(detail.id || '').trim();
      if (!id) return;
      upsertContentOption(detail);
      renderContentSelectOptions();
      if (contentInput) contentInput.value = id;
      openCreateChapter(detail);
    });
  };

  document.addEventListener('DOMContentLoaded', init);
})();
