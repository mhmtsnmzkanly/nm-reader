/**
 * admin-config.js - Secure .env management for ROOT_USER.
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
  const wrapper = $('#env-sections-wrapper');

  const categories = {
    'APP': { label: 'Application', icon: 'bi-cpu', keys: ['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_TIMEZONE'] },
    'DB': { label: 'Database', icon: 'bi-database', keys: ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET'] },
    'SITE': { label: 'Site Identity', icon: 'bi-globe', keys: ['SITE_NAME', 'SITE_ABBREVIATION', 'SITE_DESCRIPTION', 'SITE_LOGO', 'SITE_ADDRESS'] },
    'DEFAULT': { label: 'Defaults', icon: 'bi-palette', keys: ['DEFAULT_LANGUAGE', 'DEFAULT_THEME', 'DEFAULT_PROFILE_IMAGE', 'DEFAULT_CONTENT_COVER_IMAGE'] },
    'SECURITY': { label: 'Security & Session', icon: 'bi-shield-lock', keys: ['ENFORCE_HTTPS', 'ROOT_USER', 'GOOGLE_ANALYTICS_ID', 'GOOGLE_RECAPTCHA_SITE_KEY', 'GOOGLE_RECAPTCHA_SECRET_KEY', 'SESSION_LIFETIME', 'REFRESH_TOKEN_DAYS', 'CACHE_TTL'] }
  };

  const createRow = (key = '', value = '') => {
    const div = document.createElement('div');
    div.className = 'col-12 col-lg-6 env-row p-2 rounded mb-1';
    div.innerHTML = `
      <div class="env-key-label">${key}</div>
      <div class="input-group input-group-sm shadow-sm">
        <input type="text" class="form-control fw-bold env-key d-none" value="${key}">
        <input type="text" class="form-control env-value py-2" value="${value}" placeholder="Value">
        <button class="btn btn-outline-danger btn-remove" type="button" title="Delete"><i class="bi bi-trash"></i></button>
      </div>
    `;
    div.querySelector('.btn-remove').addEventListener('click', () => {
      if(confirm(`Remove ${key}?`)) div.remove();
    });
    return div;
  };

  const createSection = (id, title, icon) => {
    const section = document.createElement('div');
    section.id = `cat-${id}`;
    section.className = 'card card-outline card-secondary shadow-sm env-section mb-4';
    section.innerHTML = `
      <div class="card-header"><h3 class="card-title text-uppercase fs-7 fw-bold"><i class="bi ${icon} me-2"></i>${title}</h3></div>
      <div class="card-body"><div class="row g-3 env-container"></div></div>
    `;
    return section;
  };

  const loadEnv = async () => {
    if (!wrapper) return;
    try {
      const res = await api('/admin/maintenance/env');
      wrapper.innerHTML = '';
      const data = res.data || {};
      
      const sectionEls = {};
      Object.entries(categories).forEach(([id, info]) => {
        const el = createSection(id, info.label, info.icon);
        sectionEls[id] = el;
        wrapper.appendChild(el);
      });

      const otherSection = createSection('OTHER', 'Other Variables', 'bi-three-dots');
      wrapper.appendChild(otherSection);

      Object.entries(data).forEach(([k, v]) => {
        let assigned = false;
        for (const [id, info] of Object.entries(categories)) {
          if (info.keys.includes(k) || k.startsWith(id + '_')) {
            sectionEls[id].querySelector('.env-container').appendChild(createRow(k, v));
            assigned = true;
            break;
          }
        }
        if (!assigned) {
          otherSection.querySelector('.env-container').appendChild(createRow(k, v));
        }
      });

      document.querySelectorAll('.env-section').forEach(s => {
        if (s.querySelector('.env-container').children.length === 0) s.remove();
      });

    } catch (e) {
      wrapper.innerHTML = `<div class="col-12 alert alert-danger">${e.message}</div>`;
    }
  };

  const saveEnv = async (e) => {
    e.preventDefault();
    const btn = $('#btn-save-env');
    const originalText = btn.innerHTML;
    
    if (!confirm('Are you absolutely sure? Incorrect settings will crash the site immediately.')) return;

    const payload = {};
    document.querySelectorAll('.env-row').forEach(row => {
      const k = row.querySelector('.env-key').value.trim();
      const v = row.querySelector('.env-value').value;
      if (k !== '') payload[k] = v;
    });

    try {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
      await api('/admin/maintenance/env', { method: 'POST', body: JSON.stringify(payload) });
      if (typeof window.showPopup === 'function') window.showPopup('Configuration saved!', 'success');
      else alert('Saved!');
      loadEnv();
    } catch (e) {
      alert('Save failed: ' + e.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  };

  const init = () => {
    loadEnv();
    $('#form-env-config')?.addEventListener('submit', saveEnv);
    $('#btn-reload-env')?.addEventListener('click', loadEnv);
    $('#btn-add-var')?.addEventListener('click', () => {
      const key = prompt('Enter variable name:');
      if (key) {
        let other = $('#cat-OTHER');
        if (!other) {
          other = createSection('OTHER', 'Other Variables', 'bi-three-dots');
          wrapper.appendChild(other);
        }
        other.querySelector('.env-container').appendChild(createRow(key.toUpperCase(), ''));
      }
    });

    document.querySelectorAll('#env-category-nav a').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = link.getAttribute('href').substring(1);
        const target = document.getElementById(targetId);
        if (target) target.scrollIntoView({ behavior: 'smooth' });
        document.querySelectorAll('#env-category-nav a').forEach(l => l.classList.remove('active'));
        link.classList.add('active');
      });
    });
  };

  document.addEventListener('DOMContentLoaded', init);
})();
