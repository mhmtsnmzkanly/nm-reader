// AdminLTE sidebar/dropdown fallback.
// Keep the shell usable if a third-party bundle is blocked by a browser
// extension or a temporary CDN failure. When AdminLTE is present its own
// handler wins; the fallback only runs if the class did not change.
(() => {
  const body = document.body;
  const sidebarToggle = document.querySelector('[data-lte-toggle="sidebar"]');
  const sidebarOverlay = document.querySelector('.sidebar-overlay');
  const sidebarNav = document.querySelector('#panel-sidebar-nav');

  sidebarToggle?.addEventListener('click', event => {
    event.preventDefault();
    const wasOpen = body.classList.contains('sidebar-open');
    window.setTimeout(() => {
      if (body.classList.contains('sidebar-open') === wasOpen) {
        body.classList.toggle('sidebar-open', !wasOpen);
      }
    }, 0);
  });
  sidebarOverlay?.addEventListener('click', () => body.classList.remove('sidebar-open'));

  // Keep treeview toggles deterministic even if a CDN-provided AdminLTE
  // handler is also registered. Capturing and stopping this event prevents
  // duplicate toggles while preserving the same menu-open state AdminLTE uses.
  sidebarNav?.addEventListener('click', event => {
    const target = event.target instanceof Element ? event.target : null;
    const toggle = target?.closest('#panel-sidebar-nav > .nav-item > a[data-lte-toggle="treeview"]');
    if (!toggle) return;
    event.preventDefault();
    event.stopPropagation();
    const item = toggle.closest('.nav-item');
    if (!item) return;
    const isOpen = item.classList.toggle('menu-open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }, true);

  document.addEventListener('click', event => {
    if (window.bootstrap?.Dropdown) return;
    const target = event.target instanceof Element ? event.target : null;
    const toggle = target?.closest('[data-bs-toggle="dropdown"]');
    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
      if (!toggle || !menu.closest('.dropdown')?.contains(toggle)) menu.classList.remove('show');
    });
    if (!toggle) return;
    event.preventDefault();
    const menu = toggle.closest('.dropdown')?.querySelector('.dropdown-menu');
    menu?.classList.toggle('show');
  });
})();

// Lime-CSR admin panel application.
import { createStore, mount, unmount } from 'https://cdn.jsdelivr.net/npm/lime-csr-js@0.2.0/dist/index.min.js';
const configuredNextYear = document.querySelector('meta[name="nmr-next-year"]')?.getAttribute('content') || '';
const nextYear = /^\d{4}$/.test(configuredNextYear) ? configuredNextYear : String(new Date().getFullYear() + 1);

// 1. Initial State Store
const store = createStore({
  currentRoute: 'dashboard',
  loading: false,
  overview: {
    total_users: 0,
    total_contents: 0,
    total_chapters: 0,
    queue_pending: 0
  },
  topContents: [],
  analytics: {
    visits_daily: 0,
    visits_weekly: 0,
    visits_monthly: 0,
    home_to_content: '0%',
    content_to_chapter: '0%',
    error_rate: '0%',
    p95: '0 ms',
    search_total: 0,
    zero_result_pct: '0%',
    d1_retention: '0%',
    new_users: 0,
    total_coins: 0,
    total_unlocks: 0,
    blog_total: 0,
    blog_visible: 0,
    blog_hidden: 0,
    blog_deleted: 0,
    blog_created: 0,
    blog_approved: 0
  },
  dashboardGenres: [],
  dashboardTags: [],
  dashboardReputation: [],
  dashboardTypes: [],
  dashboardChapters: [],
  dashboardBlogAuthors: [],
  dashboardBlogDailyCreated: [],
  dashboardBlogDailyApproved: [],
  monetizationSeries: [],
  zeroResultSearches: [],
  allSeriesList: [],
  seriesList: [],
  seriesMeta: { page: 1, total_pages: 1, total: 0 },
  seriesSearch: '',
  allUsersList: [],
  usersList: [],
  usersMeta: { page: 1, total_pages: 1, total: 0 },
  userSearch: '',
  userDetailId: null,
  userDetail: null,
  userCommentsList: [],
  userCommentsMeta: { page: 1, total_pages: 1, total: 0 },
  userBlogsList: [],
  userBlogsMeta: { page: 1, total_pages: 1, total: 0 },
  userViolationsList: [],
  userViolationsMeta: { page: 1, total_pages: 1, total: 0 },
  blogsList: [],
  blogsMeta: { page: 1, total_pages: 1, total: 0 },
  commentsList: [],
  commentsMeta: { page: 1, total_pages: 1, total: 0 },
  reportsList: [],
  reportsMeta: { page: 1, total_pages: 1, total: 0, counts: {} },
  packagesList: [],
  financeList: [],
  financeMeta: { page: 1, total_pages: 1, total: 0 },
  financeSummary: {},
  logsList: [],
  logsMeta: { page: 1, total_pages: 1, total: 0 },
  uploadsList: [],
  uploadsMeta: { page: 1, total_pages: 1, total: 0 },
  uploadsStats: {},
  queueJobsList: [],
  queueMeta: { page: 1, total_pages: 1, total: 0 },
  systemHealth: {},
  config: (() => {
    const initial = window.__NMR_CONTEXT?.site_config || {};
    return {
      site_name: 'NM Reader',
      site_abbreviation: 'NMR',
      site_slogan: 'En İyi Çevrimiçi Manga ve Novel Okuyucusu',
      site_description: 'Read manga, manhwa, webtoon and novels.',
      default_language: 'tr',
      footer_text: '© 2026 NM Reader. Tüm hakları saklıdır.',
      default_theme: 'dark',
      site_logo: '/assets/img/logo-header.svg',
      logo_url: '/assets/img/logo-footer.svg',
      favicon_url: '/favicon.ico',
      default_profile_image: '/assets/img/default-profile.png',
      default_content_cover_image: '/assets/img/covers/placeholder.svg',
      maintenance_mode: false,
      maintenance_whitelist_text: Array.isArray(initial.maintenance_whitelist_ips) ? initial.maintenance_whitelist_ips.join('\n') : '127.0.0.1\n::1',
      mail_enabled: true,
      mail_send_on_register: true,
      email_verification_required: false,
      mail_from_name: 'NM Reader',
      mail_from_address: 'noreply@nmreader.com',
      password_reset_subject: 'Şifre Sıfırlama Talebi - {{site_name}}',
      password_reset_body: '',
      email_verification_subject: 'E-posta Adresinizi Doğrulayın - {{site_name}}',
      email_verification_body: '',
      ...initial
    };
  })()
});

const csrfToken = window.__NMR_CONTEXT?.auth?.csrf_token || '';
const grantedPermissions = new Set(window.__NMR_CONTEXT?.auth?.permissions || []);

function hasPermission(...permissions) {
  return grantedPermissions.has('*') || permissions.some(permission => grantedPermissions.has(permission));
}

function applyPermissionVisibility(root = document) {
  root.querySelectorAll('[data-requires-permission]').forEach(element => {
    const required = String(element.dataset.requiresPermission || '').split(',').map(value => value.trim()).filter(Boolean);
    element.hidden = required.length > 0 && !hasPermission(...required);
  });
}

// 2. Toast Notification Helper
function showToast(message, type = 'success') {
  const container = document.getElementById('lime-toasts');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = `alert alert-${type} shadow-lg py-2 px-3 mb-0 rounded-3 d-flex align-items-center gap-2 text-dark`;
  const icon = document.createElement('i');
  icon.className = `bi bi-${type === 'success' ? 'check-circle-fill text-success' : 'exclamation-circle-fill text-danger'}`;
  const text = document.createElement('span');
  text.textContent = String(message ?? '');
  toast.append(icon, text);
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

// 3. Authenticated Fetch Helper
async function api(path, options = {}) {
  const alreadyRetried = options._reauthAttempt === true;
  delete options._reauthAttempt;
  options.headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-Token': csrfToken,
    ...(options.headers || {})
  };
  if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(options.body);
  }
  const res = await fetch('/api/v1/admin' + path, options);
  if (res.status === 428 && !alreadyRetried && path !== '/auth/reauth') {
    const password = prompt('Bu kritik işlem için yönetici parolanızı yeniden girin:');
    if (!password) throw new Error('Kritik işlem iptal edildi.');
    const verification = await fetch('/api/v1/admin/auth/reauth', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ password })
    });
    if (!verification.ok) {
      const error = await verification.json().catch(() => ({}));
      throw new Error(error?.error?.message || 'Parola doğrulanamadı.');
    }
    return api(path, { ...options, _reauthAttempt: true });
  }
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err?.error?.message || `HTTP ${res.status}`);
  }
  return res.status === 204 ? null : res.json();
}

function responseItems(response) {
  if (Array.isArray(response?.data)) return response.data;
  if (Array.isArray(response?.data?.items)) return response.data.items;
  return [];
}

function filterByNeedle(items, needle, fields) {
  const query = String(needle || '').trim().toLocaleLowerCase('tr-TR');
  if (!query) return items;

  return items.filter(item => fields.some(field =>
    String(item?.[field] || '').toLocaleLowerCase('tr-TR').includes(query)
  ));
}

const reloadTimers = new Map();
function scheduleReload(key, callback) {
  clearTimeout(reloadTimers.get(key));
  reloadTimers.set(key, setTimeout(callback, 300));
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function safeLocalUrl(value) {
  const url = String(value || '');
  return url.startsWith('/') && !url.startsWith('//') ? escapeHtml(url) : '#';
}

function setTableRows(id, rows, colspan) {
  const target = document.getElementById(id);
  if (!target) return;
  target.innerHTML = rows || `<tr><td colspan="${colspan}" class="text-center text-secondary py-4">Kayıt bulunamadı</td></tr>`;
}

function responseMeta(response) {
  return {
    page: Number(response?.meta?.page || 1),
    total_pages: Math.max(1, Number(response?.meta?.total_pages || 1)),
    total: Number(response?.meta?.total || 0)
  };
}

function renderPager(id, meta, previousHandler, nextHandler) {
  const target = document.getElementById(id);
  if (!target) return;
  const page = Number(meta?.page || 1);
  const totalPages = Math.max(1, Number(meta?.total_pages || 1));
  target.innerHTML = `<div class="d-flex justify-content-between align-items-center"><button class="btn btn-sm btn-outline-secondary" data-on-click="${previousHandler}" ${page <= 1 ? 'disabled' : ''}>Önceki</button><span class="small text-secondary">Sayfa ${page} / ${totalPages} · ${Number(meta?.total || 0)} kayıt</span><button class="btn btn-sm btn-outline-secondary" data-on-click="${nextHandler}" ${page >= totalPages ? 'disabled' : ''}>Sonraki</button></div>`;
}

function renderDashboardTables() {
  setTableRows('panel-top-contents', (store.get('topContents') || []).map(item => `<tr><td class="fw-semibold">${escapeHtml(item.title)}</td><td><span class="badge bg-secondary-subtle text-secondary border">${escapeHtml(item.type)}</span></td><td class="fw-bold text-primary">${Number(item.view_count_7d || 0)}</td><td>${Number(item.comment_count_7d || 0)}</td></tr>`).join(''), 4);
  setTableRows('panel-monetization-series', (store.get('monetizationSeries') || []).map(item => `<tr><td>${escapeHtml(item.title)}</td><td>${Number(item.unlock_count || 0)}</td><td>${Number(item.total_coins || 0)}</td></tr>`).join(''), 3);
  setTableRows('panel-zero-searches', (store.get('zeroResultSearches') || []).map(item => `<tr><td>${escapeHtml(item.query)}</td><td>${Number(item.search_count || 0)}</td><td>${escapeHtml(item.last_searched_at)}</td></tr>`).join(''), 3);
  setTableRows('panel-dashboard-genres', (store.get('dashboardGenres') || []).map(item => `<tr><td>${escapeHtml(item.name)}</td><td class="text-end">${Number(item.view_total || 0)}</td></tr>`).join(''), 2);
  setTableRows('panel-dashboard-tags', (store.get('dashboardTags') || []).map(item => `<tr><td>${escapeHtml(item.name)}</td><td class="text-end">${Number(item.view_total || 0)}</td></tr>`).join(''), 2);
  setTableRows('panel-dashboard-reputation', (store.get('dashboardReputation') || []).map(item => `<tr><td>@${escapeHtml(item.username)}</td><td>${Number(item.comment_count || 0)} yorum</td><td class="text-end">${Number(item.score || 0).toFixed(1)}</td></tr>`).join(''), 3);
  setTableRows('panel-dashboard-types', (store.get('dashboardTypes') || []).map(item => `<tr><td class="text-uppercase">${escapeHtml(item.type)}</td><td class="text-end">${Number(item.view_total || 0)}</td></tr>`).join(''), 2);
  setTableRows('panel-dashboard-chapters', (store.get('dashboardChapters') || []).map(item => `<tr><td>${escapeHtml(item.content_title)} #${escapeHtml(item.chapter_number)}</td><td class="text-end">${Number(item.view_total || 0)}</td></tr>`).join(''), 2);
  setTableRows('panel-dashboard-blog-authors', (store.get('dashboardBlogAuthors') || []).map(item => `<tr><td>@${escapeHtml(item.username)}</td><td>${Number(item.approved_total || 0)} onaylı</td><td class="text-end">${Number(item.blog_total || 0)}</td></tr>`).join(''), 3);
  const dailyBlog = new Map();
  (store.get('dashboardBlogDailyCreated') || []).forEach(item => dailyBlog.set(item.day, { day: item.day, created: Number(item.total || 0), approved: 0 }));
  (store.get('dashboardBlogDailyApproved') || []).forEach(item => {
    const current = dailyBlog.get(item.day) || { day: item.day, created: 0, approved: 0 };
    current.approved = Number(item.total || 0);
    dailyBlog.set(item.day, current);
  });
  setTableRows('panel-dashboard-blog-daily', Array.from(dailyBlog.values()).sort((a, b) => String(b.day).localeCompare(String(a.day))).map(item => `<tr><td>${escapeHtml(item.day)}</td><td>${item.created}</td><td>${item.approved}</td></tr>`).join(''), 3);
}

function renderSeriesTable() {
  const lifecycleLabel = { draft: 'Taslak', scheduled: 'Zamanlandı', published: 'Yayında', archived: 'Arşivlendi' };
  const lifecycleClass = { draft: 'bg-secondary-subtle text-secondary', scheduled: 'bg-info-subtle text-info', published: 'bg-success-subtle text-success', archived: 'bg-dark-subtle text-dark' };
  setTableRows('panel-series-list', (store.get('seriesList') || []).map(item => {
    const lifecycle = item.lifecycle_status || 'published';
    const lifecycleAction = lifecycle === 'archived' ? 'restore' : (lifecycle === 'published' ? 'archive' : 'publish');
    const lifecycleIcon = lifecycle === 'archived' ? 'arrow-counterclockwise' : (lifecycle === 'published' ? 'archive' : 'send-check');
    return `<tr><td class="text-secondary small">${escapeHtml(item.id)}</td><td><span class="badge bg-info-subtle text-info border border-info-subtle text-uppercase">${escapeHtml(item.type)}</span></td><td class="fw-bold">${escapeHtml(item.title)}</td><td class="text-secondary">${escapeHtml(item.slug)}</td><td><span class="badge bg-light text-dark border me-1">${escapeHtml(item.status)}</span><span class="badge ${lifecycleClass[lifecycle] || 'bg-light text-dark'}">${lifecycleLabel[lifecycle] || lifecycle}</span>${item.scheduled_at ? `<small class="d-block text-secondary mt-1">${escapeHtml(item.scheduled_at)}</small>` : ''}</td><td class="text-end text-nowrap"><button class="btn btn-xs btn-outline-primary me-1" data-on-click="openChaptersDrawer" data-id="${escapeHtml(item.id)}"><i class="bi bi-collection me-1"></i>Bölümler</button><button class="btn btn-xs btn-outline-info me-1" data-on-click="previewSeries" data-id="${escapeHtml(item.id)}" title="Önizle"><i class="bi bi-eye"></i></button><button class="btn btn-xs btn-outline-dark me-1" data-on-click="viewSeriesRevisions" data-id="${escapeHtml(item.id)}" title="Revizyonlar"><i class="bi bi-clock-history"></i></button>${hasPermission('admin.content.update') ? `<button class="btn btn-xs btn-outline-warning me-1" data-on-click="changeSeriesLifecycle" data-id="${escapeHtml(item.id)}" data-action="${lifecycleAction}" title="${lifecycleAction}"><i class="bi bi-${lifecycleIcon}"></i></button><a class="btn btn-xs btn-outline-secondary" href="/panel/series/${encodeURIComponent(item.id)}/edit" data-panel-link title="Düzenle"><i class="bi bi-pencil"></i></a>` : ''}</td></tr>`;
  }).join(''), 6);
  renderPager('panel-series-pager', store.get('seriesMeta'), 'previousSeriesPage', 'nextSeriesPage');
}

function renderUsersTable() {
  setTableRows('panel-users-list', (store.get('usersList') || []).map(user => `<tr><td class="fw-bold"><i class="bi bi-person me-1 text-secondary"></i>${escapeHtml(user.username)}</td><td class="text-secondary">${escapeHtml(user.email)}</td><td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">${escapeHtml(user.role_names)}</span></td><td><span class="badge ${escapeHtml(user.account_badge)}">${escapeHtml(user.account_status)}</span></td><td class="small text-secondary">${escapeHtml(user.created_at)}</td><td class="text-end">${hasPermission('admin.users.manage') ? `<a class="btn btn-xs btn-outline-primary me-1" href="/panel/action/user-detail/${encodeURIComponent(user.id)}" data-panel-link><i class="bi bi-person-lines-fill me-1"></i>İncele</a><button class="btn btn-xs btn-outline-secondary me-1" data-on-click="openEditUserModal" data-id="${escapeHtml(user.id)}"><i class="bi bi-pencil me-1"></i>Düzenle</button>` : ''}${hasPermission('admin.wallet.view') ? `<button class="btn btn-xs btn-outline-warning" data-on-click="openWalletModal" data-id="${escapeHtml(user.id)}"><i class="bi bi-cash-coin me-1"></i>Bakiye</button>` : ''}</td></tr>`).join(''), 6);
  renderPager('panel-users-pager', store.get('usersMeta'), 'previousUsersPage', 'nextUsersPage');
}

function userViolationLevel(level) {
  return ({
    warning: ['Uyarı', 'bg-info-subtle text-info'],
    removal: ['İçerik kaldırma', 'bg-warning-subtle text-warning'],
    temporary: ['Süreli engel', 'bg-danger-subtle text-danger'],
    permanent: ['Kalıcı engel', 'bg-dark text-white']
  })[String(level || '')] || [String(level || '-'), 'bg-secondary-subtle text-secondary'];
}

function userModerationStatus(status) {
  return ({
    pending: ['Bekliyor', 'bg-warning-subtle text-warning'],
    approved: ['Onaylı', 'bg-success-subtle text-success'],
    hidden: ['Gizli', 'bg-secondary-subtle text-secondary'],
    deleted: ['Silindi', 'bg-danger-subtle text-danger']
  })[String(status || '')] || [String(status || '-'), 'bg-light text-secondary'];
}

function renderUserCommentsTable() {
  const rows = (store.get('userCommentsList') || []).map(comment => {
    const status = userModerationStatus(comment.moderation_status || 'approved');
    const context = comment.blog_title
      ? `Blog: ${comment.blog_title}`
      : comment.content_title
        ? `${comment.target_type === 'chapter' ? 'Bölüm' : 'İçerik'}: ${comment.content_title}${comment.chapter_number ? ` #${comment.chapter_number}` : ''}`
        : `${comment.target_type || 'Hedef'}: ${comment.target_id || '-'}`;
    return `<tr><td class="text-wrap" style="min-width:260px">${escapeHtml(comment.body)}</td><td><span class="small text-secondary">${escapeHtml(context)}</span></td><td><span class="badge ${status[1]}">${escapeHtml(status[0])}</span></td><td class="text-nowrap"><span class="badge bg-success-subtle text-success">+${Number(comment.upvote_count || 0)}</span> <span class="badge bg-danger-subtle text-danger">-${Number(comment.downvote_count || 0)}</span></td><td class="small text-secondary text-nowrap">${escapeHtml(comment.created_at)}</td></tr>`;
  }).join('');
  setTableRows('panel-user-comments-list', rows, 5);
  renderPager('panel-user-comments-pager', store.get('userCommentsMeta'), 'previousUserCommentsPage', 'nextUserCommentsPage');
}

function renderUserBlogsTable() {
  const labels = { draft: 'Taslak', pending: 'Bekliyor', published: 'Yayınlandı', rejected: 'Reddedildi', hidden: 'Gizli' };
  const rows = (store.get('userBlogsList') || []).map(blog => {
    const status = blog.status || (Number(blog.approved) === 1 ? 'published' : 'pending');
    const statusClass = Number(blog.approved) === 1 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning';
    return `<tr><td class="fw-semibold">${escapeHtml(blog.title)}</td><td class="small text-secondary">${escapeHtml(blog.slug || blog.id)}</td><td><span class="badge ${statusClass}">${escapeHtml(labels[status] || status)}</span></td><td class="small text-secondary text-nowrap">${escapeHtml(blog.created_at)}</td></tr>`;
  }).join('');
  setTableRows('panel-user-blogs-list', rows, 4);
  renderPager('panel-user-blogs-pager', store.get('userBlogsMeta'), 'previousUserBlogsPage', 'nextUserBlogsPage');
}

function renderUserViolationsTable() {
  const rows = (store.get('userViolationsList') || []).map(violation => {
    const level = userViolationLevel(violation.level);
    const active = violation.revoked_at ? 'İptal edildi' : (violation.ends_at && new Date(violation.ends_at.replace(' ', 'T')) < new Date() ? 'Süresi doldu' : 'Aktif');
    return `<tr><td><span class="badge ${level[1]}">${escapeHtml(level[0])}</span><small class="d-block text-secondary">${escapeHtml(violation.scope || 'general')}</small></td><td>${escapeHtml(violation.action || '-')}<small class="d-block text-secondary">${escapeHtml(violation.target_type || '-')}/${escapeHtml(violation.target_id || '-')}</small></td><td class="text-wrap" style="min-width:220px">${escapeHtml(violation.reason)}</td><td>@${escapeHtml(violation.moderator_username || violation.moderator_user_id || '-')}</td><td class="small text-secondary text-nowrap">${escapeHtml(violation.created_at)}<small class="d-block">${escapeHtml(active)}</small></td></tr>`;
  }).join('');
  setTableRows('panel-user-violations-list', rows, 5);
  renderPager('panel-user-violations-pager', store.get('userViolationsMeta'), 'previousUserViolationsPage', 'nextUserViolationsPage');
}

async function loadUserCommentsData(userId, page = 1) {
  if (!userId) return;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '10' });
    const values = {
      q: document.getElementById('panel-user-comments-search')?.value || '',
      target_type: document.getElementById('panel-user-comments-target')?.value || '',
      moderation_status: document.getElementById('panel-user-comments-status')?.value || '',
      sort: document.getElementById('panel-user-comments-sort')?.value || 'newest'
    };
    Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
    const response = await api(`/users/${encodeURIComponent(userId)}/comments?${params.toString()}`);
    if (String(store.get('userDetailId')) !== String(userId)) return;
    store.batch(() => {
      store.set('userCommentsList', responseItems(response));
      store.set('userCommentsMeta', responseMeta(response));
    });
    renderUserCommentsTable();
  } catch (error) {
    const target = document.getElementById('panel-user-comments-list');
    if (target) setTableRows('panel-user-comments-list', `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`, 5);
  }
}

async function loadUserBlogsData(userId, page = 1) {
  if (!userId) return;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '10' });
    const values = {
      q: document.getElementById('panel-user-blogs-search')?.value || '',
      status: document.getElementById('panel-user-blogs-status')?.value || '',
      sort: document.getElementById('panel-user-blogs-sort')?.value || 'newest'
    };
    Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
    const response = await api(`/users/${encodeURIComponent(userId)}/blogs?${params.toString()}`);
    if (String(store.get('userDetailId')) !== String(userId)) return;
    store.batch(() => {
      store.set('userBlogsList', responseItems(response));
      store.set('userBlogsMeta', responseMeta(response));
    });
    renderUserBlogsTable();
  } catch (error) {
    const target = document.getElementById('panel-user-blogs-list');
    if (target) setTableRows('panel-user-blogs-list', `<tr><td colspan="4" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`, 4);
  }
}

async function loadUserViolationsData(userId, page = 1) {
  if (!userId) return;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '10' });
    const level = document.getElementById('panel-user-violations-level')?.value || '';
    const scope = document.getElementById('panel-user-violations-scope')?.value || '';
    if (level) params.set('level', level);
    if (scope) params.set('scope', scope);
    const response = await api(`/users/${encodeURIComponent(userId)}/violations?${params.toString()}`);
    if (String(store.get('userDetailId')) !== String(userId)) return;
    store.batch(() => {
      store.set('userViolationsList', responseItems(response));
      store.set('userViolationsMeta', responseMeta(response));
    });
    renderUserViolationsTable();
  } catch (error) {
    const target = document.getElementById('panel-user-violations-list');
    if (target) setTableRows('panel-user-violations-list', `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`, 5);
  }
}

function userDetailFilters(overlay, userId) {
  const bindReload = (selector, key, loader) => {
    overlay.querySelectorAll(selector).forEach(input => {
      const eventName = input.tagName === 'INPUT' ? 'input' : 'change';
      input.addEventListener(eventName, () => scheduleReload(key, () => loader(userId, 1)));
    });
  };
  bindReload('#panel-user-comments-search, #panel-user-comments-target, #panel-user-comments-status, #panel-user-comments-sort', 'user-comments', loadUserCommentsData);
  bindReload('#panel-user-blogs-search, #panel-user-blogs-status, #panel-user-blogs-sort', 'user-blogs', loadUserBlogsData);
  bindReload('#panel-user-violations-level, #panel-user-violations-scope', 'user-violations', loadUserViolationsData);
  overlay.querySelectorAll('[data-user-tab]').forEach(button => button.addEventListener('click', () => {
    const tab = button.dataset.userTab;
    overlay.querySelectorAll('[data-user-tab]').forEach(item => item.classList.toggle('active', item === button));
    overlay.querySelectorAll('[data-user-section]').forEach(section => { section.hidden = section.dataset.userSection !== tab; });
    if (tab === 'blogs') loadUserBlogsData(userId, Number(store.get('userBlogsMeta')?.page || 1));
    if (tab === 'violations') loadUserViolationsData(userId, Number(store.get('userViolationsMeta')?.page || 1));
  }));
}

async function loadUserDetailPage(userId) {
  if (!userId) throw new Error('Kullanıcı kimliği bulunamadı');
  const response = await api(`/users/${encodeURIComponent(userId)}/overview`);
  const overview = response?.data || {};
  const user = overview.user || {};
  const stats = overview.stats || {};
  store.batch(() => {
    store.set('userDetailId', userId);
    store.set('userDetail', overview);
    store.set('userCommentsList', []);
    store.set('userBlogsList', []);
    store.set('userViolationsList', []);
    store.set('userCommentsMeta', { page: 1, total_pages: 1, total: 0 });
    store.set('userBlogsMeta', { page: 1, total_pages: 1, total: 0 });
    store.set('userViolationsMeta', { page: 1, total_pages: 1, total: 0 });
  });
  const restrictions = (overview.active_restrictions || []).map(item => {
    const level = userViolationLevel(item.level);
    return `<span class="badge ${level[1]} me-1 mb-1">${escapeHtml(item.type || 'general')}: ${escapeHtml(level[0])}${item.ends_at ? ` · ${escapeHtml(item.ends_at)}` : ''}</span>`;
  }).join('') || '<span class="text-secondary">Aktif işlem kısıtlaması yok</span>';
  const profileImage = safeLocalUrl(user.profile_image || store.get('config')?.default_profile_image || '/assets/img/default-profile.png');
  const target = document.getElementById('panel-action-page');
  if (!target) return;
  const overlay = openDialog(
    `Kullanıcı: @${user.username || userId}`,
    `<div class="row g-4 mb-4"><div class="col-lg-8"><div class="d-flex gap-3 align-items-center"><img src="${profileImage}" alt="" width="72" height="72" class="rounded-circle object-fit-cover border"><div><h4 class="mb-1">${escapeHtml(user.display_name || user.username || userId)}</h4><div class="text-secondary">@${escapeHtml(user.username || userId)} · ${escapeHtml(user.email || '-')}</div><div class="mt-2"><span class="badge ${user.is_banned ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'}">${user.is_banned ? 'Aktif kısıtlama' : 'Etkileşim açık'}</span> <span class="badge bg-primary-subtle text-primary">${escapeHtml(user.role_names || 'user')}</span></div></div></div>${user.bio ? `<p class="mt-3 mb-0 text-secondary">${escapeHtml(user.bio)}</p>` : ''}</div><div class="col-lg-4 d-flex justify-content-lg-end align-items-start"><a class="btn btn-danger btn-lg" href="/panel/action/user-penalty/${encodeURIComponent(userId)}" data-panel-link><i class="bi bi-shield-exclamation me-2"></i>CEZA VER</a></div></div><div class="row g-3 mb-4"><div class="col-md-4"><div class="card bg-body-tertiary border-0 h-100"><div class="card-body"><div class="small text-secondary">Yorumlar</div><div class="fs-3 fw-bold">${Number(stats.comments_total || 0).toLocaleString('tr-TR')}</div></div></div></div><div class="col-md-4"><div class="card bg-body-tertiary border-0 h-100"><div class="card-body"><div class="small text-secondary">Bloglar</div><div class="fs-3 fw-bold">${Number(stats.blogs_total || 0).toLocaleString('tr-TR')}</div></div></div></div><div class="col-md-4"><div class="card bg-body-tertiary border-0 h-100"><div class="card-body"><div class="small text-secondary">Cezalar</div><div class="fs-3 fw-bold">${Number(stats.violations_total || 0).toLocaleString('tr-TR')}</div></div></div></div></div><div class="mb-4"><h6 class="text-uppercase small text-secondary mb-2">Aktif kısıtlamalar</h6>${restrictions}</div><ul class="nav nav-tabs mb-3" role="tablist"><li class="nav-item"><button type="button" class="nav-link active" data-user-tab="comments">Yorumlar</button></li><li class="nav-item"><button type="button" class="nav-link" data-user-tab="blogs">Bloglar</button></li><li class="nav-item"><button type="button" class="nav-link" data-user-tab="violations">Ceza geçmişi</button></li></ul><section data-user-section="comments"><div class="row g-2 mb-3"><div class="col-lg-5"><input id="panel-user-comments-search" class="form-control" placeholder="Yorumlarda ara..."></div><div class="col-md-3"><select id="panel-user-comments-target" class="form-select"><option value="">Tüm hedefler</option><option value="series">İçerik</option><option value="chapter">Bölüm</option><option value="blog">Blog</option></select></div><div class="col-md-2"><select id="panel-user-comments-status" class="form-select"><option value="">Tüm durumlar</option><option value="approved">Onaylı</option><option value="pending">Bekliyor</option><option value="hidden">Gizli</option><option value="deleted">Silindi</option></select></div><div class="col-md-2"><select id="panel-user-comments-sort" class="form-select"><option value="newest">Yeni</option><option value="oldest">Eski</option></select></div></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Yorum</th><th>Hedef</th><th>Durum</th><th>Oylar</th><th>Tarih</th></tr></thead><tbody id="panel-user-comments-list"></tbody></table></div><div id="panel-user-comments-pager" class="mt-3"></div></section><section data-user-section="blogs" hidden><div class="row g-2 mb-3"><div class="col-lg-6"><input id="panel-user-blogs-search" class="form-control" placeholder="Bloglarda ara..."></div><div class="col-md-3"><select id="panel-user-blogs-status" class="form-select"><option value="">Tüm durumlar</option><option value="draft">Taslak</option><option value="pending">Bekliyor</option><option value="published">Yayınlandı</option><option value="hidden">Gizli</option></select></div><div class="col-md-3"><select id="panel-user-blogs-sort" class="form-select"><option value="newest">Yeni</option><option value="oldest">Eski</option></select></div></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Başlık</th><th>Slug</th><th>Durum</th><th>Tarih</th></tr></thead><tbody id="panel-user-blogs-list"></tbody></table></div><div id="panel-user-blogs-pager" class="mt-3"></div></section><section data-user-section="violations" hidden><div class="row g-2 mb-3"><div class="col-md-6"><select id="panel-user-violations-level" class="form-select"><option value="">Tüm seviyeler</option><option value="warning">Uyarı</option><option value="removal">İçerik kaldırma</option><option value="temporary">Süreli engel</option><option value="permanent">Kalıcı engel</option></select></div><div class="col-md-6"><select id="panel-user-violations-scope" class="form-select"><option value="">Tüm kapsamlar</option><option value="general">Genel</option><option value="comment">Yorum</option><option value="blog">Blog</option></select></div></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Seviye</th><th>İşlem / Hedef</th><th>Gerekçe</th><th>Moderatör</th><th>Tarih</th></tr></thead><tbody id="panel-user-violations-list"></tbody></table></div><div id="panel-user-violations-pager" class="mt-3"></div></section>`,
    async () => {},
    'modal-xl'
  );
  overlay.querySelector('button[type="submit"]')?.remove();
  overlay.querySelector('.card-footer')?.remove();
  userDetailFilters(overlay, userId);
  renderUserCommentsTable();
  renderUserBlogsTable();
  renderUserViolationsTable();
  await loadUserCommentsData(userId, 1);
}

function violationScopeForTarget(targetType) {
  return targetType === 'comment' ? 'comment' : (targetType === 'blog' ? 'blog' : 'general');
}

async function loadUserPenaltyPage(userId) {
  if (!userId) throw new Error('Kullanıcı kimliği bulunamadı');
  let overview = store.get('userDetail');
  if (String(store.get('userDetailId')) !== String(userId) || !overview?.user) {
    overview = (await api(`/users/${encodeURIComponent(userId)}/overview`))?.data || {};
  }
  const user = overview.user || {};
  const overlay = openDialog(
    `Ceza ver: @${user.username || userId}`,
    `<div class="alert alert-info d-flex gap-2"><i class="bi bi-info-circle"></i><div>Beğeni ve oy işlemleri engellenmez. Buradaki kapsam yalnızca seçilen içerik, yorum veya blog oluşturma etkileşimlerini etkiler.</div></div><div class="row g-3"><div class="col-md-4"><label class="form-label">Hedef türü</label><select class="form-select" name="target_type" required><option value="comment">Yorum</option><option value="blog">Blog</option><option value="series">İçerik</option><option value="chapter">Bölüm</option><option value="system">Genel sistem</option></select></div><div class="col-md-4"><label class="form-label">Hedef ID</label><input class="form-control" name="target_id" maxlength="32" required></div><div class="col-md-4"><label class="form-label">Ceza seviyesi</label><select class="form-select" name="level" id="panel-user-penalty-level"><option value="warning">Uyarı</option><option value="removal">İçeriği kaldır</option><option value="temporary">Süreli engel</option><option value="permanent">Kalıcı engel</option></select></div><div class="col-md-4"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="auto_escalate" value="1" id="panel-user-penalty-auto" checked><label class="form-check-label" for="panel-user-penalty-auto">Geçmişe göre otomatik yükselt</label></div></div><div class="col-md-4"><label class="form-label">Bitiş (süreli ceza)</label><input type="datetime-local" class="form-control" name="ends_at"></div><div class="col-12"><label class="form-label">Gerekçe</label><textarea class="form-control" name="reason" rows="5" maxlength="1000" required></textarea></div></div>`,
    async (formData, form) => {
      const payload = Object.fromEntries(formData.entries());
      payload.auto_escalate = form.elements.auto_escalate.checked;
      payload.scope = violationScopeForTarget(payload.target_type);
      if (payload.auto_escalate) payload.level = 'warning';
      await api(`/users/${encodeURIComponent(userId)}/violations`, { method: 'POST', body: payload });
      showToast('Ceza kaydı oluşturuldu');
      panelNavigate(`/panel/action/user-detail/${encodeURIComponent(userId)}`);
    },
    'modal-xl'
  );
  const form = overlay.querySelector('#panel-dialog-form');
  const auto = form?.elements.auto_escalate;
  const level = form?.elements.level;
  const sync = () => { if (level) level.disabled = Boolean(auto?.checked); };
  auto?.addEventListener('change', sync);
  sync();
  const submit = form?.querySelector('button[type="submit"]');
  if (submit) submit.textContent = 'CEZAYI UYGULA';
}

function renderBlogsTable() {
  setTableRows('panel-blogs-list', (store.get('blogsList') || []).map(blog => `<tr><td class="fw-bold">${escapeHtml(blog.title)}</td><td>${escapeHtml(blog.username)}</td><td><span class="badge ${escapeHtml(blog.status_badge)}">${escapeHtml(blog.status_label)}</span></td><td class="small text-secondary">${escapeHtml(blog.created_at)}</td><td class="text-end">${hasPermission('admin.blog.hide') && blog.can_approve ? `<button class="btn btn-xs btn-outline-success me-1" data-on-click="approveBlog" data-id="${escapeHtml(blog.id)}"><i class="bi bi-check-circle me-1"></i>Onayla</button>` : ''}${hasPermission('admin.blog.hide') && blog.can_hide ? `<button class="btn btn-xs btn-outline-warning me-1" data-on-click="hideBlog" data-id="${escapeHtml(blog.id)}"><i class="bi bi-eye-slash me-1"></i>Gizle</button>` : ''}${hasPermission('admin.blog.hide') ? `<button class="btn btn-xs btn-outline-danger" data-on-click="deleteBlog" data-id="${escapeHtml(blog.id)}"><i class="bi bi-trash me-1"></i>Sil</button>` : ''}</td></tr>`).join(''), 5);
  renderPager('panel-blogs-pager', store.get('blogsMeta'), 'previousBlogsPage', 'nextBlogsPage');
}

function renderCommentsTable() {
  const statusLabel = { pending: 'Bekliyor', approved: 'Onaylı', hidden: 'Gizli', deleted: 'Silindi' };
  const statusClass = { pending: 'bg-warning-subtle text-warning', approved: 'bg-success-subtle text-success', hidden: 'bg-secondary-subtle text-secondary', deleted: 'bg-danger-subtle text-danger' };
  setTableRows('panel-comments-list', (store.get('commentsList') || []).map(comment => { const status = comment.moderation_status || 'approved'; const nextStatus = status === 'approved' ? 'hidden' : 'approved'; return `<tr><td class="fw-bold">${escapeHtml(comment.username)}</td><td class="text-wrap">${escapeHtml(comment.body)}</td><td><span class="small text-secondary">${escapeHtml(comment.context_label)}</span></td><td><span class="badge ${statusClass[status] || 'bg-light text-secondary'}">${escapeHtml(statusLabel[status] || status)}</span></td><td><span class="badge bg-success-subtle text-success">+${Number(comment.upvote_count || 0)}</span> <span class="badge bg-danger-subtle text-danger">-${Number(comment.downvote_count || 0)}</span></td><td class="small text-secondary">${escapeHtml(comment.created_at)}</td><td class="text-end text-nowrap">${hasPermission('admin.comment.delete') ? `<button class="btn btn-xs btn-outline-${nextStatus === 'approved' ? 'success' : 'warning'} me-1" data-on-click="moderateComment" data-id="${escapeHtml(comment.id)}" data-status="${nextStatus}" title="${nextStatus === 'approved' ? 'Onayla' : 'Gizle'}"><i class="bi bi-${nextStatus === 'approved' ? 'check-circle' : 'eye-slash'}"></i></button><button class="btn btn-xs btn-outline-danger" data-on-click="deleteComment" data-id="${escapeHtml(comment.id)}" title="Sil"><i class="bi bi-trash"></i></button>` : ''}</td></tr>`; }).join(''), 7);
  renderPager('panel-comments-pager', store.get('commentsMeta'), 'previousCommentsPage', 'nextCommentsPage');
}

function reportStatus(status) {
  return ({
    pending: ['Bekleyen', 'bg-warning-subtle text-warning'],
    reviewing: ['İncelenen', 'bg-info-subtle text-info'],
    resolved: ['Çözüldü', 'bg-success-subtle text-success'],
    rejected: ['Reddedildi', 'bg-secondary-subtle text-secondary']
  })[status] || [status || '-', 'bg-light text-secondary'];
}

function renderReportsTable() {
  setTableRows('panel-reports-list', (store.get('reportsList') || []).map(report => {
    const status = reportStatus(report.status);
    const target = report.target_url
      ? `<a href="${safeLocalUrl(report.target_url)}" target="_blank" rel="noopener">${escapeHtml(report.target_title || report.target_id)}</a>`
      : escapeHtml(report.target_title || report.comment_snippet || report.target_id);
    return `<tr><td>${Number(report.id)}</td><td><strong>@${escapeHtml(report.reporter_username)}</strong></td><td><span class="badge bg-light text-dark border me-1">${escapeHtml(report.target_type)}</span>${target}</td><td>${escapeHtml(report.reason)}</td><td><span class="badge ${status[1]}">${status[0]}</span></td><td class="small text-secondary">${escapeHtml(report.created_at)}</td><td class="text-end"><a class="btn btn-xs btn-outline-primary" href="/panel/reports/${Number(report.id)}" data-panel-link><i class="bi bi-search me-1"></i>İncele</a></td></tr>`;
  }).join(''), 7);
  const meta = store.get('reportsMeta') || {};
  const counts = meta.counts || {};
  ['pending', 'reviewing', 'resolved', 'rejected'].forEach(status => {
    const element = document.getElementById(`panel-report-count-${status}`);
    if (element) element.textContent = String(Number(counts[status] || 0));
  });
  const page = Number(meta.page || 1);
  const totalPages = Math.max(1, Number(meta.total_pages || 1));
  const label = document.getElementById('panel-reports-page');
  if (label) label.textContent = `Sayfa ${page} / ${totalPages} · ${Number(meta.total || 0)} kayıt`;
  const previous = document.getElementById('panel-reports-prev');
  const next = document.getElementById('panel-reports-next');
  if (previous) previous.disabled = page <= 1;
  if (next) next.disabled = page >= totalPages;
}

function renderPackagesTable() {
  setTableRows('panel-packages-list', (store.get('packagesList') || []).map(item => `<tr><td class="fw-bold">${escapeHtml(item.name)}</td><td class="text-warning fw-bold">${Number(item.coin_amount || 0)}</td><td class="text-success">+${Number(item.bonus_coin || 0)}</td><td><span class="badge bg-secondary-subtle text-secondary">${escapeHtml(item.display_price)} ${escapeHtml(item.currency)}</span></td><td><span class="badge ${escapeHtml(item.status_badge)}">${escapeHtml(item.status_label)}</span></td><td class="text-end"><button class="btn btn-xs btn-outline-secondary" data-on-click="openEditPackageModal" data-id="${escapeHtml(item.id)}"><i class="bi bi-pencil"></i> Düzenle</button></td></tr>`).join(''), 6);
}

function renderFinanceTable() {
  const eligible = new Set(['chapter_unlock', 'series_unlock', 'feature_unlock', 'manual_debit']);
  setTableRows('panel-finance-list', (store.get('financeList') || []).map(item => {
    const canRefund = Number(item.coin_delta) < 0 && eligible.has(item.type) && Number(item.refunded_coin || 0) < Math.abs(Number(item.coin_delta));
    return `<tr><td>${Number(item.id)}</td><td><strong>@${escapeHtml(item.username)}</strong><small class="d-block text-secondary">${escapeHtml(item.user_id)}</small></td><td><span class="badge bg-light text-dark border">${escapeHtml(item.type)}</span></td><td class="fw-bold ${Number(item.coin_delta) >= 0 ? 'text-success' : 'text-danger'}">${Number(item.coin_delta) > 0 ? '+' : ''}${Number(item.coin_delta)}</td><td>${Number(item.balance_after)}</td><td><small>${escapeHtml(item.reference_type || '-')} / ${escapeHtml(item.reference_id || '-')}</small><span class="d-block text-secondary">${escapeHtml(item.description || '')}</span></td><td class="small text-secondary">${escapeHtml(item.created_at)}</td><td class="text-end">${canRefund && hasPermission('admin.finance.refund') ? `<button class="btn btn-xs btn-outline-danger" data-on-click="refundFinanceTransaction" data-id="${Number(item.id)}"><i class="bi bi-arrow-counterclockwise me-1"></i>İade</button>` : (Number(item.refunded_coin || 0) > 0 ? '<span class="badge bg-danger-subtle text-danger">İade edildi</span>' : '')}</td></tr>`;
  }).join(''), 8);
  const summary = store.get('financeSummary') || {};
  const values = { circulating: summary.circulating_coin, credited: summary.credited_coin, spent: summary.spent_coin, refunded: summary.refunded_coin };
  Object.entries(values).forEach(([key, value]) => { const element = document.getElementById(`panel-finance-${key}`); if (element) element.textContent = Number(value || 0).toLocaleString('tr-TR'); });
  renderPager('panel-finance-pager', store.get('financeMeta'), 'previousFinancePage', 'nextFinancePage');
}

function renderQueueTable() {
  setTableRows('panel-queue-jobs', (store.get('queueJobsList') || []).map(job => `<tr><td>${escapeHtml(job.id)}</td><td>${escapeHtml(job.job_type)}</td><td><span class="badge bg-light text-dark border">${escapeHtml(job.status)}</span></td><td>${Number(job.attempts || 0)}</td><td class="text-danger small">${escapeHtml(job.last_error)}</td><td>${escapeHtml(job.created_at)}</td><td class="text-end">${hasPermission('admin.jobs.run') && ['failed', 'cancelled'].includes(job.status) ? `<button class="btn btn-xs btn-outline-primary me-1" data-on-click="retryQueueJob" data-id="${Number(job.id)}">Tekrarla</button>` : ''}${hasPermission('admin.jobs.run') && job.status === 'pending' ? `<button class="btn btn-xs btn-outline-danger" data-on-click="cancelQueueJob" data-id="${Number(job.id)}">İptal</button>` : ''}</td></tr>`).join(''), 7);
  renderPager('panel-queue-pager', store.get('queueMeta'), 'previousQueuePage', 'nextQueuePage');
  const health = store.get('systemHealth') || {};
  const db = document.getElementById('panel-health-database');
  if (db) { db.textContent = health.database?.ok ? `Çalışıyor · ${health.database.version || ''}` : 'Hata'; db.className = `fw-bold ${health.database?.ok ? 'text-success' : 'text-danger'}`; }
  const storage = document.getElementById('panel-health-storage');
  if (storage) { storage.textContent = health.storage?.ok ? `${(Number(health.storage.free_bytes || 0) / 1073741824).toFixed(1)} GB boş` : 'Yazma hatası'; storage.className = `fw-bold ${health.storage?.ok ? 'text-success' : 'text-danger'}`; }
  const queue = document.getElementById('panel-health-queue');
  if (queue) queue.textContent = `${Number(health.queue?.pending || 0)} bekleyen · ${Number(health.queue?.failed || 0)} hata`;
  const backup = document.getElementById('panel-health-backup');
  if (backup) backup.textContent = health.backup ? `${health.backup.file} · ${health.backup.created_at}` : 'Yedek bulunamadı';
}

function renderLogsTable() {
  setTableRows('panel-audit-logs', (store.get('logsList') || []).map(log => `<tr><td><span class="badge bg-secondary-subtle text-secondary">${escapeHtml(log.method)}</span></td><td class="fw-bold text-break">${escapeHtml(log.path)}</td><td><span class="badge ${Number(log.status_code) >= 500 ? 'bg-danger-subtle text-danger' : (Number(log.status_code) >= 400 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success')}">${Number(log.status_code || 0)}</span></td><td>${escapeHtml(log.username || log.user_id || '-')}</td><td>${Number(log.duration_ms || 0)}ms</td><td class="text-secondary">${escapeHtml(log.created_at)}</td><td><button class="btn btn-xs btn-outline-secondary" data-on-click="viewAuditLog" data-id="${Number(log.id)}"><i class="bi bi-braces"></i></button></td></tr>`).join(''), 7);
  renderPager('panel-logs-pager', store.get('logsMeta'), 'previousLogsPage', 'nextLogsPage');
}

function renderUploadsTable() {
  setTableRows('panel-uploads-list', (store.get('uploadsList') || []).map(item => {
    const references = Array.isArray(item.references) ? item.references : [];
    const referenceHtml = references.length
      ? `<div class="upload-reference-list">${references.map(reference => {
        const label = `${reference.entity_type || 'kayıt'} · ${reference.label || reference.entity_id || '-'}`;
        const relation = reference.relation ? ` <span class="text-secondary">(${reference.relation})</span>` : '';
        return reference.url && reference.url !== '#'
          ? `<a href="${safeLocalUrl(reference.url)}" class="d-block text-truncate" target="_blank" rel="noopener" title="${escapeHtml(label)}">${escapeHtml(label)}${relation}</a>`
          : `<span class="d-block text-truncate" title="${escapeHtml(label)}">${escapeHtml(label)}${relation}</span>`;
      }).join('')}</div>`
      : '<span class="badge bg-warning-subtle text-warning">Orphan</span>';
    return `<tr><td>${hasPermission('admin.uploads.delete') ? `<input type="checkbox" class="form-check-input" data-upload-select value="${Number(item.id)}">` : ''}</td><td><a href="${safeLocalUrl(item.file_path)}" target="_blank" rel="noopener"><img src="${safeLocalUrl(item.file_path)}" alt="" loading="lazy" class="rounded border object-fit-cover" width="52" height="52"></a></td><td><strong>${escapeHtml(item.original_name)}</strong><small class="d-block text-secondary">${escapeHtml(item.image_id)}</small></td><td>${escapeHtml(item.mime_type)}</td><td>${escapeHtml(item.size_label)}</td><td><span class="badge ${references.length ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'}">${references.length ? `${references.length} bağlantı` : 'Orphan'}</span>${referenceHtml}</td><td>${escapeHtml(item.username)}</td><td class="small text-secondary">${escapeHtml(item.created_at)}</td><td class="text-end text-nowrap">${hasPermission('admin.uploads.optimize') ? `<button class="btn btn-xs btn-outline-primary me-1" data-on-click="optimizeUpload" data-id="${Number(item.id)}" title="Optimize et"><i class="bi bi-lightning"></i></button>` : ''}${hasPermission('admin.uploads.delete') ? `<button class="btn btn-xs btn-outline-danger" data-on-click="deleteUpload" data-id="${Number(item.id)}"><i class="bi bi-trash"></i></button>` : ''}</td></tr>`;
  }).join(''), 9);
  renderPager('panel-uploads-pager', store.get('uploadsMeta'), 'previousUploadsPage', 'nextUploadsPage');
  const stats = store.get('uploadsStats') || {};
  const count = document.getElementById('panel-upload-count'); if (count) count.textContent = Number(stats.total_files || 0).toLocaleString('tr-TR');
  const size = document.getElementById('panel-upload-size'); if (size) size.textContent = `${(Number(stats.total_bytes || 0) / 1048576).toFixed(1)} MB`;
  const types = document.getElementById('panel-upload-types'); if (types) types.textContent = `JPEG ${Number(stats.jpeg_files || 0)} · PNG ${Number(stats.png_files || 0)} · WebP ${Number(stats.webp_files || 0)} · GIF ${Number(stats.gif_files || 0)}`;
}

function renderRouteTables(route) {
  if (route === 'dashboard') renderDashboardTables();
  if (route === 'series') renderSeriesTable();
  if (route === 'users') renderUsersTable();
  if (route === 'blogs') renderBlogsTable();
  if (route === 'comments') renderCommentsTable();
  if (route === 'reports') renderReportsTable();
  if (route === 'monetization') renderPackagesTable();
  if (route === 'finance') renderFinanceTable();
  if (route === 'ops') renderQueueTable();
  if (route === 'logs') renderLogsTable();
  if (route === 'uploads') renderUploadsTable();
}

let dialogPreviousFocus = null;
let dialogCleanup = null;
let pageDialogMode = false;
let pageDialogParent = '/panel';

function closeDialog() {
  const dialog = document.getElementById('panel-dialog');
  if (!dialog) {
    document.body.classList.remove('panel-dialog-open');
    dialogPreviousFocus = null;
    dialogCleanup = null;
    return;
  }
  const navigateBack = pageDialogMode;
  const parentPath = pageDialogParent;
  pageDialogMode = false;
  const cleanup = dialogCleanup;
  dialogCleanup = null;
  dialog.remove();
  document.body.classList.remove('panel-dialog-open');
  if (dialogPreviousFocus instanceof HTMLElement && document.contains(dialogPreviousFocus)) {
    dialogPreviousFocus.focus();
  }
  dialogPreviousFocus = null;
  if (typeof cleanup === 'function') {
    Promise.resolve(cleanup()).catch(error => showToast(error.message || 'Geçici yüklemeler temizlenemedi.', 'danger'));
  }
  if (navigateBack) panelNavigate(parentPath);
}

function openDialog(title, body, onSubmit, size = 'modal-lg') {
  if (pageDialogMode) document.getElementById('panel-dialog')?.remove();
  else closeDialog();
  dialogPreviousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
  const overlay = document.createElement('div');
  overlay.id = 'panel-dialog';
  overlay.className = pageDialogMode ? 'panel-dialog-page' : 'modal-backdrop-custom p-3';
  overlay.tabIndex = -1;
  overlay.innerHTML = pageDialogMode ? `
    <div class="app-content-header py-3 px-4 bg-body border-bottom">
      <div class="container-fluid d-flex flex-wrap align-items-center gap-3">
        <a class="btn btn-outline-secondary btn-lg text-nowrap" href="${escapeHtml(pageDialogParent)}" data-panel-link><i class="bi bi-arrow-left me-1"></i>Geri dön</a>
        <div><nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small"><li class="breadcrumb-item"><a href="/panel" data-panel-link>Panel</a></li><li class="breadcrumb-item active">${escapeHtml(title)}</li></ol></nav><h3 class="mb-0 fw-bold fs-4">${escapeHtml(title)}</h3></div>
      </div>
    </div>
    <div class="app-content p-4"><div class="container-fluid"><div class="card border-0 shadow-sm"><form id="panel-dialog-form"><div class="card-body p-4">${body}</div><div class="card-footer bg-transparent d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="${escapeHtml(pageDialogParent)}" data-panel-link>İptal</a><button type="submit" class="btn btn-primary">Kaydet</button></div></form></div></div></div>` : `
    <div class="modal-dialog ${size} m-0 w-100" role="dialog" aria-modal="true" aria-labelledby="panel-dialog-title">
      <div class="modal-content shadow-lg">
        <form id="panel-dialog-form">
          <div class="modal-header">
            <h5 class="modal-title" id="panel-dialog-title">${escapeHtml(title)}</h5>
            <button type="button" class="btn-close" data-dialog-close aria-label="Kapat"></button>
          </div>
          <div class="modal-body">${body}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-dialog-close>Vazgeç</button>
            <button type="submit" class="btn btn-primary">Kaydet</button>
          </div>
        </form>
      </div>
    </div>`;
  overlay.addEventListener('click', event => {
    if (event.target === overlay || event.target.closest('[data-dialog-close]')) closeDialog();
  });
  overlay.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeDialog();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...overlay.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];
    if (focusable.length === 0) {
      event.preventDefault();
      overlay.focus();
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  overlay.querySelector('form').addEventListener('submit', async event => {
    event.preventDefault();
    const submit = event.submitter;
    if (submit) submit.disabled = true;
    try {
      await onSubmit(new FormData(event.currentTarget), event.currentTarget);
    } catch (error) {
      showToast(error.message, 'danger');
      if (submit) submit.disabled = false;
    }
  });
  const pageTarget = pageDialogMode ? document.getElementById('panel-action-page') : null;
  if (pageTarget) pageTarget.appendChild(overlay);
  else document.body.appendChild(overlay);
  if (!pageDialogMode) document.body.classList.add('panel-dialog-open');
  applyPermissionVisibility(overlay);
  overlay.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
  return overlay;
}

function taxonomyChoices(items, selectedIds, name) {
  const selected = new Set(String(selectedIds || '').split(',').filter(Boolean));
  return items.map(item => `
    <label class="btn btn-sm ${selected.has(String(item.id)) ? 'btn-primary' : 'btn-outline-secondary'}">
      <input class="visually-hidden" type="checkbox" name="${name}" value="${escapeHtml(item.id)}" ${selected.has(String(item.id)) ? 'checked' : ''}>
      ${escapeHtml(item.name)}
    </label>`).join('') || '<span class="text-secondary small">Kayıt bulunamadı.</span>';
}

function contentForm(content = {}, genres = [], tags = []) {
  const checked = value => Number(value) === 1 ? 'checked' : '';
  const dateValue = value => value ? String(value).replace(' ', 'T').slice(0, 16) : '';
  return `
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Tür</label><select class="form-select" name="type" ${content.id ? 'disabled' : 'required'}>
        ${['novel', 'manga', 'manhwa', 'webtoon', 'light-novel', 'web-novel'].map(type => `<option value="${type}" ${String(content.type || '').replace('_', '-') === type ? 'selected' : ''}>${type}</option>`).join('')}
      </select></div>
      <div class="col-md-4"><label class="form-label">Başlık</label><input class="form-control" name="title" value="${escapeHtml(content.title)}" required></div>
      <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug" value="${escapeHtml(content.slug)}" ${content.id ? 'disabled' : ''}></div>
      <div class="col-md-4"><label class="form-label">Durum</label><select class="form-select" name="status">
        <option value="ongoing" ${content.status === 'ongoing' ? 'selected' : ''}>Devam ediyor</option>
        <option value="completed" ${content.status === 'completed' ? 'selected' : ''}>Tamamlandı</option>
        <option value="hiatus" ${content.status === 'hiatus' ? 'selected' : ''}>Ara verdi</option>
        <option value="dropped" ${content.status === 'dropped' ? 'selected' : ''}>Bırakıldı</option>
      </select></div>
      <div class="col-md-4"><label class="form-label">Yayın durumu</label><select class="form-select" name="lifecycle_status" data-lifecycle-select><option value="draft" ${content.lifecycle_status === 'draft' ? 'selected' : ''}>Taslak</option><option value="scheduled" ${content.lifecycle_status === 'scheduled' ? 'selected' : ''}>Zamanlandı</option><option value="published" ${!content.lifecycle_status || content.lifecycle_status === 'published' ? 'selected' : ''}>Yayında</option><option value="archived" ${content.lifecycle_status === 'archived' ? 'selected' : ''}>Arşivlendi</option></select></div>
      <div class="col-md-4"><label class="form-label">Planlanan yayın</label><input type="datetime-local" class="form-control" name="scheduled_at" value="${escapeHtml(dateValue(content.scheduled_at))}"></div>
      <div class="col-md-8"><label class="form-label">Alternatif başlıklar</label><input class="form-control" name="alternative_titles" value="${escapeHtml(content.alternative_titles)}"></div>
      <div class="col-12"><label class="form-label">Açıklama</label><textarea class="form-control" name="description" rows="4">${escapeHtml(content.description)}</textarea></div>
      <div class="col-md-6"><label class="form-label">Kapak görseli yolu</label><input class="form-control" name="cover_image" value="${escapeHtml(content.cover_image)}"></div>
      <div class="col-md-6"><label class="form-label">Kapak yükle</label><input type="file" accept="image/*" class="form-control" name="cover_file"></div>
      <div class="col-md-3"><label class="form-label">Yazar</label><input class="form-control" name="author" value="${escapeHtml(content.author)}"></div>
      <div class="col-md-3"><label class="form-label">Çizer</label><input class="form-control" name="artist" value="${escapeHtml(content.artist)}"></div>
      <div class="col-md-3"><label class="form-label">Ülke</label><input class="form-control" name="country" value="${escapeHtml(content.country)}"></div>
      <div class="col-md-3"><label class="form-label">Yayın yılı</label><input type="number" class="form-control" name="release_year" min="1800" max="${escapeHtml(nextYear)}" value="${escapeHtml(content.release_year)}"></div>
      <div class="col-md-3 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_adult" value="1" id="dialog-adult" ${checked(content.is_adult)}><label class="form-check-label" for="dialog-adult">Yetişkin içeriği</label></div>
      <div class="col-md-3 form-check"><input class="form-check-input" type="checkbox" name="is_members_only" value="1" id="dialog-members" ${checked(content.is_members_only)}><label class="form-check-label" for="dialog-members">Sadece üyeler</label></div>
      <div class="col-md-3 form-check"><input class="form-check-input" type="checkbox" name="disable_comments" value="1" id="dialog-disable-comments" ${checked(content.disable_comments)}><label class="form-check-label" for="dialog-disable-comments">Yorumları kapat</label></div>
      <div class="col-12"><label class="form-label d-block">Türler</label><div class="d-flex flex-wrap gap-2" data-taxonomy-choices>${taxonomyChoices(genres, content.genre_ids, 'genres')}</div></div>
      <div class="col-12"><label class="form-label d-block">Etiketler</label><div class="d-flex flex-wrap gap-2" data-taxonomy-choices>${taxonomyChoices(tags, content.tag_ids, 'tags')}</div></div>
    </div>`;
}

function chapterForm(chapter = {}) {
  const pricing = chapter.pricing || {};
  const dateValue = value => value ? String(value).replace(' ', 'T').slice(0, 16) : '';
  return `
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Bölüm numarası</label><input class="form-control" name="chapter_number" value="${escapeHtml(chapter.chapter_number)}" required></div>
      <div class="col-md-4"><label class="form-label">Tür</label><select class="form-select" name="type" id="dialog-chapter-type"><option value="text" ${chapter.type !== 'image' ? 'selected' : ''}>Metin</option><option value="image" ${chapter.type === 'image' ? 'selected' : ''}>Görsel</option></select></div>
      <div class="col-md-4"><label class="form-label">Başlık</label><input class="form-control" name="title" maxlength="200" value="${escapeHtml(chapter.title)}"></div>
      <div class="col-md-4"><label class="form-label">Coin fiyatı</label><input type="number" min="0" class="form-control" name="price_amount" value="${escapeHtml(pricing.base_price ?? chapter.price_amount ?? 0)}"></div>
      <div class="col-md-4"><label class="form-label">Yayın tarihi</label><input type="datetime-local" class="form-control" name="published_at" value="${escapeHtml(dateValue(pricing.published_at ?? chapter.published_at))}"></div>
      <div class="col-md-4"><label class="form-label">Ücretsiz olma tarihi</label><input type="datetime-local" class="form-control" name="is_free_after" value="${escapeHtml(dateValue(pricing.is_free_after ?? chapter.is_free_after))}"></div>
      <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_members_only" value="1" id="dialog-chapter-members" ${Number(chapter.is_members_only) === 1 ? 'checked' : ''}><label class="form-check-label" for="dialog-chapter-members">Sadece üyeler</label></div>
      <div class="col-12"><label class="form-label">Çevirmen notu</label><textarea class="form-control" name="translator_note" maxlength="2000" rows="3" placeholder="Okuyucuya gösterilecek ek not (isteğe bağlı)">${escapeHtml(chapter.translator_note)}</textarea></div>
      <div class="col-12" data-chapter-body><label class="form-label">Metin</label><textarea class="form-control" name="body" rows="8">${escapeHtml(chapter.body)}</textarea></div>
      <div class="col-12" data-chapter-pages>
        <label class="form-label">Görsel sayfaları</label>
        <input type="file" accept="image/*,.zip" multiple class="form-control mb-2" name="page_files">
        <div class="row g-2 mb-2" data-page-preview></div>
        <textarea class="form-control" name="pages" rows="6" placeholder="Görsel yolları (satır başına bir tane)">${escapeHtml((chapter.pages || []).join('\n'))}</textarea>
        <div class="form-text">Önizleme kartlarındaki oklarla sıralayın veya bir kartı kaldırın.</div>
      </div>
    </div>`;
}

async function loadTaxonomies() {
  const [genreResponse, tagResponse] = await Promise.all([api('/genres'), api('/tags')]);
  return { genres: responseItems(genreResponse), tags: responseItems(tagResponse) };
}

async function uploadImages(files, type) {
  if (!files?.length) return [];
  const body = new FormData();
  Array.from(files).forEach(file => body.append('images[]', file));
  const response = await api(`/upload-images?type=${encodeURIComponent(type)}`, { method: 'POST', body });
  return response?.data?.paths || [];
}

function bindTaxonomyButtons(overlay) {
  overlay.querySelectorAll('[data-taxonomy-choices] label').forEach(label => {
    label.addEventListener('click', () => {
      setTimeout(() => {
        const checked = label.querySelector('input').checked;
        label.classList.toggle('btn-primary', checked);
        label.classList.toggle('btn-outline-secondary', !checked);
      });
    });
  });
}

function selectedValues(formData, name) {
  return formData.getAll(name).map(String);
}

function chapterPayload(formData, form) {
  const payload = Object.fromEntries(formData.entries());
  delete payload.page_files;
  if (Object.prototype.hasOwnProperty.call(payload, 'price_amount')) {
    payload.price_amount = Number(payload.price_amount || 0);
  }
  payload.is_members_only = form.elements.is_members_only?.checked ? 1 : 0;
  if (payload.type === 'image') {
    payload.pages = String(payload.pages || '').split('\n').map(value => value.trim()).filter(Boolean);
    delete payload.body;
  } else {
    delete payload.pages;
  }
  return payload;
}

async function openChapterEditor(content, chapterId = null) {
  const chapter = chapterId ? (await api(`/chapters/${chapterId}`))?.data || {} : {};
  const uploadedPaths = [];
  const originalPrice = chapterId ? Number(chapter.pricing?.base_price ?? chapter.price_amount ?? 0) : null;
  const overlay = openDialog(
    chapterId ? `Bölümü Düzenle: ${chapter.chapter_number}` : `${content.title} — Yeni Bölüm`,
    chapterForm(chapter),
    async (formData, form) => {
      const payload = chapterPayload(formData, form);
      const requestedPrice = chapterId && !Object.prototype.hasOwnProperty.call(payload, 'price_amount')
        ? originalPrice
        : Number(payload.price_amount || 0);
      if (chapterId) {
        if (requestedPrice !== originalPrice && !hasPermission('admin.shop.manage')) {
          throw new Error('Bölüm fiyatını değiştirmek için admin.shop.manage izni gerekir.');
        }
        // Price changes use the dedicated pricing endpoint so its audit trail
        // and price_last_update semantics remain consistent with other shop
        // operations. The content update keeps the existing price otherwise.
        delete payload.price_amount;
      }
      await api(chapterId ? `/chapters/${chapterId}` : `/content/${content.id}/chapters`, {
        method: chapterId ? 'PUT' : 'POST',
        body: payload
      });
      if (chapterId && requestedPrice !== originalPrice) {
        await api(`/chapters/${chapterId}/pricing`, {
          method: 'PUT',
          body: { price_coin: requestedPrice, is_active: requestedPrice > 0 }
        });
      }
      closeDialog();
      showToast(chapterId ? 'Bölüm güncellendi' : 'Bölüm oluşturuldu');
      await loadSeriesData();
      await openChaptersDialog(content.id);
    }
  );
  const typeInput = overlay.querySelector('#dialog-chapter-type');
  const priceInput = overlay.querySelector('[name="price_amount"]');
  if (chapterId && priceInput && !hasPermission('admin.shop.manage')) {
    priceInput.disabled = true;
    priceInput.title = 'Fiyat değiştirmek için admin.shop.manage izni gerekir.';
  }
  const pagesInput = overlay.querySelector('[name="pages"]');
  const pagePreview = overlay.querySelector('[data-page-preview]');
  const pagePaths = () => pagesInput.value.split(/\r?\n/).map(value => value.trim()).filter(Boolean);
  const renderPagePreview = () => {
    const paths = pagePaths();
    pagePreview.innerHTML = paths.map((path, index) => `
      <div class="col-6 col-sm-4 col-md-3" data-page-card data-page-index="${index}">
        <div class="card h-100 border shadow-sm">
          <div class="ratio ratio-3x4 bg-body-tertiary rounded-top overflow-hidden">
            <img src="${safeLocalUrl(path)}" alt="Sayfa ${index + 1}" loading="lazy" class="w-100 h-100 object-fit-contain" onerror="this.replaceWith(Object.assign(document.createElement('div'), {className:'d-flex align-items-center justify-content-center text-secondary small p-2', textContent:'Önizleme yok'}))">
          </div>
          <div class="card-body p-2">
            <div class="small text-secondary text-truncate mb-2" title="${escapeHtml(path)}">${index + 1}. ${escapeHtml(path)}</div>
            <div class="btn-group btn-group-sm w-100">
              <button type="button" class="btn btn-outline-secondary" data-page-move="up" ${index === 0 ? 'disabled' : ''} aria-label="Yukarı taşı"><i class="bi bi-arrow-up"></i></button>
              <button type="button" class="btn btn-outline-secondary" data-page-move="down" ${index === paths.length - 1 ? 'disabled' : ''} aria-label="Aşağı taşı"><i class="bi bi-arrow-down"></i></button>
              <button type="button" class="btn btn-outline-danger" data-page-remove aria-label="Sayfayı kaldır"><i class="bi bi-trash"></i></button>
            </div>
          </div>
        </div>
      </div>`).join('') || '<div class="col-12"><div class="text-secondary small">Henüz görsel sayfası yok.</div></div>';
  };
  pagePreview.addEventListener('click', event => {
    const button = event.target.closest('[data-page-move], [data-page-remove]');
    if (!button) return;
    const card = button.closest('[data-page-card]');
    const index = Number(card?.dataset.pageIndex);
    const paths = pagePaths();
    if (!Number.isInteger(index) || !paths[index]) return;
    if (button.hasAttribute('data-page-remove')) {
      paths.splice(index, 1);
    } else {
      const direction = button.dataset.pageMove === 'up' ? -1 : 1;
      const targetIndex = index + direction;
      if (targetIndex < 0 || targetIndex >= paths.length) return;
      [paths[index], paths[targetIndex]] = [paths[targetIndex], paths[index]];
    }
    pagesInput.value = paths.join('\n');
    renderPagePreview();
  });
  pagesInput.addEventListener('input', renderPagePreview);
  let previousType = typeInput.value;
  const syncChapterFields = () => {
    const image = typeInput.value === 'image';
    overlay.querySelector('[data-chapter-body]').hidden = image;
    overlay.querySelector('[data-chapter-pages]').hidden = !image;
  };
  typeInput.addEventListener('change', () => {
    const nextType = typeInput.value;
    if (nextType !== previousType) {
      const warning = nextType === 'image'
        ? 'Metin içeriği görsel bölüme çevrilecek. Kaydederseniz metin içeriği kaldırılır. Devam edilsin mi?'
        : 'Görsel sayfaları metin bölüme çevrilecek. Kaydederseniz görsel sayfaları kaldırılır. Devam edilsin mi?';
      if (!confirm(warning)) {
        typeInput.value = previousType;
        return;
      }
      previousType = nextType;
    }
    syncChapterFields();
  });
  overlay.querySelector('[name="page_files"]').addEventListener('change', async event => {
    const files = Array.from(event.target.files || []);
    const zipFiles = files.filter(file => /\.zip$/i.test(file.name));
    if (zipFiles.length > 0 && files.length > 1) {
      showToast('ZIP ile diğer görselleri aynı anda seçmeyin; önce ZIP veya görsellerden birini yükleyin.', 'danger');
      event.target.value = '';
      return;
    }
    try {
      const paths = await uploadImages(files, 'chapters');
      uploadedPaths.push(...paths);
      const textarea = overlay.querySelector('[name="pages"]');
      const existing = textarea.value.split('\n').map(value => value.trim()).filter(Boolean);
      textarea.value = [...existing, ...paths].join('\n');
      renderPagePreview();
      showToast(`${paths.length} görsel yüklendi`);
    } catch (error) { showToast(error.message, 'danger'); }
    event.target.value = '';
  });
  dialogCleanup = async () => {
    if (uploadedPaths.length > 0) {
      await api('/uploads/cleanup', { method: 'POST', body: { paths: uploadedPaths } });
    }
  };
  syncChapterFields();
  renderPagePreview();
}

async function openSeriesPreview(contentId) {
  const response = await api(`/content/${contentId}/preview`);
  const content = response?.data || {};
  const overlay = openDialog(
    `Önizleme: ${content.title || contentId}`,
    `<div class="row g-4"><div class="col-md-4"><div class="ratio ratio-3x4 bg-body-tertiary rounded overflow-hidden">${content.cover_image ? `<img src="${safeLocalUrl(content.cover_image)}" class="w-100 h-100 object-fit-cover" alt="">` : '<div class="d-flex align-items-center justify-content-center text-secondary">Kapak yok</div>'}</div></div><div class="col-md-8"><div class="d-flex flex-wrap gap-2 mb-3"><span class="badge bg-primary">${escapeHtml(content.type)}</span><span class="badge bg-secondary">${escapeHtml(content.status)}</span><span class="badge bg-info text-dark">${escapeHtml(content.lifecycle_status)}</span></div><h3>${escapeHtml(content.title)}</h3><p class="text-secondary">${escapeHtml(content.alternative_titles)}</p><p>${escapeHtml(content.description || 'Açıklama yok')}</p><dl class="row small"><dt class="col-sm-3">Yazar</dt><dd class="col-sm-9">${escapeHtml(content.author || '-')}</dd><dt class="col-sm-3">Çizer</dt><dd class="col-sm-9">${escapeHtml(content.artist || '-')}</dd><dt class="col-sm-3">Planlanan yayın</dt><dd class="col-sm-9">${escapeHtml(content.scheduled_at || '-')}</dd></dl>${content.lifecycle_status === 'published' ? `<a href="${safeLocalUrl(content.url_path)}" target="_blank" rel="noopener" class="btn btn-outline-primary">Canlı sayfayı aç</a>` : '<div class="alert alert-warning small">Bu içerik halka açık değil; yalnızca yönetici önizlemesindesiniz.</div>'}</div></div>`,
    async () => {},
    'modal-xl'
  );
  overlay.querySelector('button[type="submit"]')?.remove();
}

async function openSeriesRevisions(contentId) {
  const response = await api(`/content/${contentId}/revisions?limit=50`);
  const rows = responseItems(response).map(revision => `<tr><td>${escapeHtml(revision.created_at)}</td><td>${escapeHtml(revision.moderator_username || revision.moderator_user_id || '-')}</td><td><span class="badge bg-secondary">${escapeHtml(revision.action)}</span></td><td>${escapeHtml(revision.snapshot?.title || '-')}</td><td>${escapeHtml(revision.snapshot?.status || '-')} / ${escapeHtml(revision.snapshot?.lifecycle_status || 'published')}</td></tr>`).join('');
  const overlay = openDialog('İçerik Revizyon Geçmişi', `<div class="table-responsive" style="max-height:70vh"><table class="table table-sm align-middle"><thead class="table-dark position-sticky top-0"><tr><th>Tarih</th><th>Yönetici</th><th>İşlem</th><th>Başlık</th><th>Durum</th></tr></thead><tbody>${rows || '<tr><td colspan="5" class="text-center text-secondary">Revizyon bulunamadı</td></tr>'}</tbody></table></div>`, async () => {}, 'modal-xl');
  overlay.querySelector('button[type="submit"]')?.remove();
}

async function openTaxonomyDialog() {
  const { genres, tags } = await loadTaxonomies();
  const renderRows = items => items.map(item => `<tr data-taxonomy-row="${escapeHtml(item.id)}"><td class="text-secondary">${escapeHtml(item.id)}</td><td><strong>${escapeHtml(item.name)}</strong><small class="d-block text-secondary">${escapeHtml(item.slug || '')}</small></td><td>${Number(item.usage_count || 0)}</td><td style="width:90px"><input type="number" min="0" class="form-control form-control-sm" data-taxonomy-order value="${Number(item.sort_order || 0)}"></td><td class="text-end text-nowrap"><button type="button" class="btn btn-xs btn-outline-secondary me-1" data-edit-taxonomy="${escapeHtml(item.id)}" data-name="${escapeHtml(item.name)}" title="Düzenle"><i class="bi bi-pencil"></i></button><button type="button" class="btn btn-xs btn-outline-primary me-1" data-merge-taxonomy="${escapeHtml(item.id)}" title="Birleştir"><i class="bi bi-intersect"></i></button><button type="button" class="btn btn-xs btn-outline-danger" data-delete-taxonomy="${escapeHtml(item.id)}" data-name="${escapeHtml(item.name)}" data-usage="${Number(item.usage_count || 0)}" title="Sil"><i class="bi bi-trash"></i></button></td></tr>`).join('') || '<tr><td colspan="5" class="text-secondary">Kayıt bulunamadı</td></tr>';
  const overlay = openDialog(
    'Tür ve Etiket Yönetimi',
    `<div class="row g-4">
      <div class="col-md-6"><div class="d-flex justify-content-between mb-2"><h6>Türler</h6><button type="button" class="btn btn-sm btn-primary" data-create-taxonomy="genre">Yeni Tür</button></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>ID</th><th>Ad / Slug</th><th>Kullanım</th><th>Sıra</th><th></th></tr></thead><tbody>${renderRows(genres)}</tbody></table></div></div>
      <div class="col-md-6"><div class="d-flex justify-content-between mb-2"><h6>Etiketler</h6><button type="button" class="btn btn-sm btn-primary" data-create-taxonomy="tag">Yeni Etiket</button></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>ID</th><th>Ad / Slug</th><th>Kullanım</th><th>Sıra</th><th></th></tr></thead><tbody>${renderRows(tags)}</tbody></table></div></div>
    </div>`,
    async () => {
      const items = Array.from(overlay.querySelectorAll('[data-taxonomy-row]')).map(row => ({ id: Number(row.dataset.taxonomyRow), sort_order: Number(row.querySelector('[data-taxonomy-order]').value || 0) }));
      await api('/taxonomies/order', { method: 'PUT', body: { items } });
      showToast('Taksonomi sırası kaydedildi');
      await openTaxonomyDialog();
    },
    'modal-xl'
  );
  const submit = overlay.querySelector('button[type="submit"]');
  if (submit) submit.textContent = 'Sıralamayı Kaydet';
  overlay.addEventListener('click', async event => {
    try {
      const createButton = event.target.closest('[data-create-taxonomy]');
      if (createButton) {
        const kind = createButton.dataset.createTaxonomy;
        const name = prompt(kind === 'genre' ? 'Yeni tür adı:' : 'Yeni etiket adı:');
        if (!name?.trim()) return;
        await api(kind === 'genre' ? '/series_genres' : '/series_tags', { method: 'POST', body: { name: name.trim() } });
        showToast(kind === 'genre' ? 'Tür oluşturuldu' : 'Etiket oluşturuldu');
        await openTaxonomyDialog();
        return;
      }
      const editButton = event.target.closest('[data-edit-taxonomy]');
      if (editButton) {
        const name = prompt('Yeni ad:', editButton.dataset.name || '');
        if (!name?.trim() || name.trim() === editButton.dataset.name) return;
        await api(`/taxonomies/${editButton.dataset.editTaxonomy}`, { method: 'PUT', body: { name: name.trim() } });
        showToast('Taksonomi güncellendi');
        await openTaxonomyDialog();
        return;
      }
      const mergeButton = event.target.closest('[data-merge-taxonomy]');
      if (mergeButton) {
        const targetId = Number(prompt('Bu kaydın birleştirileceği hedef taksonomi ID:'));
        if (!targetId) return;
        await api('/taxonomies/merge', { method: 'POST', body: { source_id: Number(mergeButton.dataset.mergeTaxonomy), target_id: targetId } });
        showToast('Taksonomiler birleştirildi');
        await openTaxonomyDialog();
        return;
      }
      const deleteButton = event.target.closest('[data-delete-taxonomy]');
      if (deleteButton) {
        if (Number(deleteButton.dataset.usage || 0) > 0) throw new Error('Kullanılan bir kayıt silinemez; önce başka bir kayda birleştirin.');
        if (!confirm(`“${deleteButton.dataset.name}” silinsin mi?`)) return;
        await api(`/taxonomies/${deleteButton.dataset.deleteTaxonomy}`, { method: 'DELETE' });
        showToast('Taksonomi silindi');
        await openTaxonomyDialog();
      }
    } catch (error) { showToast(error.message, 'danger'); }
  });
}

async function openTeamDialog(content) {
  const response = await api(`/series/${content.id}/team`);
  const members = responseItems(response);
  const rows = members.map(member => `<tr><td><strong>@${escapeHtml(member.username)}</strong><br><small>${escapeHtml(member.user_id)}</small></td><td>${escapeHtml(member.role)}</td><td>${escapeHtml(member.created_at || '-')}</td><td class="text-end"><button type="button" class="btn btn-xs btn-outline-danger" data-remove-team="${member.id}"><i class="bi bi-trash"></i></button></td></tr>`).join('') || '<tr><td colspan="4" class="text-center text-secondary">Henüz ekip üyesi yok</td></tr>';
  const overlay = openDialog(
    `${content.title} — Ekip Yönetimi`,
    `<div class="row g-2 mb-4"><div class="col-md-7"><label class="form-label">Kullanıcı ID</label><input class="form-control" name="user_id" pattern="[a-z0-9]{8}" required></div><div class="col-md-5"><label class="form-label">Görev</label><select class="form-select" name="role"><option value="translator">Çevirmen</option><option value="proofreader">Kontrolör</option><option value="cleaner">Cleaner</option><option value="typesetter">Dizgici</option><option value="uploader">Uploader</option><option value="lead">Proje lideri</option></select></div></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Kullanıcı</th><th>Görev</th><th>Tarih</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`,
    async formData => {
      await api(`/series/${content.id}/team`, { method: 'POST', body: Object.fromEntries(formData.entries()) });
      showToast('Ekip üyesi atandı');
      await openTeamDialog(content);
    }
  );
  overlay.addEventListener('click', async event => {
    const button = event.target.closest('[data-remove-team]');
    if (!button || !confirm('Ekip üyesini çıkarmak istediğinize emin misiniz?')) return;
    try {
      await api(`/series/team/${button.dataset.removeTeam}`, { method: 'DELETE' });
      showToast('Ekip üyesi çıkarıldı');
      await openTeamDialog(content);
    } catch (error) { showToast(error.message, 'danger'); }
  });
}

async function openUserEditor(userId) {
  const user = (store.get('allUsersList') || []).find(item => String(item.id) === String(userId));
  if (!user) throw new Error('Kullanıcı bulunamadı');
  const rolesResponse = await api('/rbac/roles');
  const roles = responseItems(rolesResponse);
  const currentRole = String(user.role_names || 'user').split(',')[0].trim();
  const banEndsAt = String(user.ban_ends_at || '').replace(' ', 'T').slice(0, 16);
  const legacyVotingOption = user.ban_type === 'voting'
    ? '<option value="voting" selected>Oy verme (eski kayıt; artık kısıtlanmıyor)</option>'
    : '';
  const banLevel = user.ban_level || 'temporary';
  const overlay = openDialog(
    `Kullanıcıyı Düzenle: ${user.username}`,
    `<div class="mb-3"><label class="form-label">Kullanıcı adı</label><input class="form-control" value="${escapeHtml(user.username)}" disabled></div>
     <div class="mb-3"><label class="form-label">E-posta</label><input type="email" class="form-control" name="email" value="${escapeHtml(user.email)}" required></div>
     <div class="mb-3"><label class="form-label">Biyografi</label><textarea class="form-control" name="bio" maxlength="1000" rows="4">${escapeHtml(user.bio)}</textarea></div>
     <div class="mb-3"><label class="form-label">Rol</label><select class="form-select" name="role">${roles.map(role => `<option value="${escapeHtml(role.slug)}" ${role.slug === currentRole ? 'selected' : ''}>${escapeHtml(role.name || role.slug)}</option>`).join('')}</select></div>
     <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_banned" value="1" id="dialog-user-banned" ${Number(user.is_banned) === 1 ? 'checked' : ''}><label class="form-check-label" for="dialog-user-banned">Kullanıcıyı yasakla</label></div>
     <div class="mb-3"><label class="form-label">Yasak kapsamı</label><select class="form-select" name="ban_type"><option value="general" ${user.ban_type === 'general' || !user.ban_type ? 'selected' : ''}>Tüm üretim işlemleri</option><option value="comment" ${user.ban_type === 'comment' ? 'selected' : ''}>Yorum</option><option value="blog" ${user.ban_type === 'blog' ? 'selected' : ''}>Blog</option>${legacyVotingOption}<option value="reporting" ${user.ban_type === 'reporting' ? 'selected' : ''}>Rapor gönderme</option></select></div>
     <div class="mb-3"><label class="form-label">Ceza seviyesi</label><select class="form-select" name="ban_level"><option value="warning" ${banLevel === 'warning' ? 'selected' : ''}>Uyarı (engelleme yok)</option><option value="removal" ${banLevel === 'removal' ? 'selected' : ''}>İçerik kaldırma (engelleme yok)</option><option value="temporary" ${banLevel === 'temporary' ? 'selected' : ''}>Süreli işlem engeli</option><option value="permanent" ${banLevel === 'permanent' ? 'selected' : ''}>Kalıcı işlem engeli</option></select></div>
     <div class="mb-3"><label class="form-label">Yasak bitişi (boş = süresiz)</label><input type="datetime-local" class="form-control" name="ban_ends_at" value="${escapeHtml(banEndsAt)}"></div>
     <div><label class="form-label">Yasak nedeni</label><textarea class="form-control" name="ban_reason" maxlength="1000" rows="3">${escapeHtml(user.ban_reason || '')}</textarea></div>`,
    async (formData, form) => {
      const payload = Object.fromEntries(formData.entries());
      payload.is_banned = form.elements.is_banned.checked;
      await api(`/users/${user.id}`, { method: 'PUT', body: payload });
      closeDialog();
      showToast('Kullanıcı güncellendi');
      loadUsersData();
    }
  );
}

async function openRbacDialog() {
  const response = await api('/rbac/matrix');
  const roles = response?.data?.roles || [];
  const permissionGroups = response?.data?.permissions || {};
  const heading = roles.map(role => `<th class="text-center">${escapeHtml(role.name || role.slug)}</th>`).join('');
  let rows = '';
  Object.entries(permissionGroups).forEach(([group, permissions]) => {
    rows += `<tr class="table-secondary"><td colspan="${roles.length + 1}"><strong>${escapeHtml(group)}</strong></td></tr>`;
    Object.entries(permissions).forEach(([code, label]) => {
      rows += `<tr><td><code>${escapeHtml(code)}</code><br><small class="text-secondary">${escapeHtml(label)}</small></td>${roles.map(role => {
        const rolePermissions = String(role.permissions || '').split(',');
        const granted = role.slug === 'superadmin' || rolePermissions.includes('*') || rolePermissions.includes(code);
        const canChange = granted ? hasPermission('admin.permissions.revoke') : hasPermission('admin.permissions.grant');
        const locked = role.slug === 'admin' && code === 'admin.panel.access';
        return `<td class="text-center">${canChange && !locked ? `<button type="button" class="btn btn-sm border-0" data-toggle-permission data-role="${escapeHtml(role.slug)}" data-permission="${escapeHtml(code)}" data-granted="${granted ? '1' : '0'}" title="${granted ? 'İzni kaldır' : 'İzni ver'}"><i class="bi ${granted ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-secondary'}"></i></button>` : `<i class="bi ${granted ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-secondary opacity-50'}"></i>`}</td>`;
      }).join('')}</tr>`;
    });
  });
  const overlay = openDialog(
    'Yetki ve Rol Matrisi',
    `<p class="text-secondary small">Temel rol izinleri uygulama yapılandırmasından, panelde yaptığınız ekleme ve kaldırmalar veritabanındaki RBAC geçersiz kılmalarından okunur. Kullanıcı rolünü kullanıcı düzenleme ekranından değiştirebilirsiniz.</p><div class="table-responsive" style="max-height:65vh"><table class="table table-sm table-bordered align-middle"><thead class="table-dark position-sticky top-0"><tr><th>İzin</th>${heading}</tr></thead><tbody>${rows}</tbody></table></div>`,
    async () => {},
    'modal-xl'
  );
  overlay.querySelector('button[type="submit"]')?.remove();
  overlay.addEventListener('click', async event => {
    const button = event.target.closest('[data-toggle-permission]');
    if (!button) return;
    const granted = button.dataset.granted === '1';
    if (!confirm(`${button.dataset.permission} izni ${button.dataset.role} rolü için ${granted ? 'kaldırılsın' : 'verilsin'} mi?`)) return;
    try {
      await api(granted ? '/rbac/permissions' : '/rbac/permissions/assign', {
        method: granted ? 'DELETE' : 'POST',
        body: { role: button.dataset.role, permission: button.dataset.permission }
      });
      showToast(granted ? 'İzin kaldırıldı' : 'İzin verildi');
      await openRbacDialog();
    } catch (error) { showToast(error.message, 'danger'); }
  });
}

async function openOwnershipMatrix() {
  const [matrixResponse, ownershipResponse] = await Promise.all([api('/rbac/matrix'), api('/rbac/ownership')]);
  const roles = matrixResponse?.data?.roles || [];
  const capabilities = ownershipResponse?.data?.capabilities || [];
  const records = ownershipResponse?.data?.records || [];
  const roleHeading = roles.map(role => `<th class="text-center">${escapeHtml(role.name || role.slug)}</th>`).join('');
  const rolePermissions = role => String(role.permissions || '').split(',').map(value => value.trim()).filter(Boolean);
  const canRole = (role, capability) => {
    if (capability.scope === 'owner') return 'Sahibi';
    if (capability.scope === 'authenticated') return 'Giriş yapmış kullanıcı';
    const permissions = rolePermissions(role);
    if (role.slug === 'superadmin' || permissions.includes('*')) return 'Evet';
    if (capability.scope === 'role_any') {
      return String(capability.permission || '').split(' veya ').some(permission => permissions.includes(permission)) ? 'Evet' : 'Hayır';
    }
    return permissions.includes(capability.permission) ? 'Evet' : 'Hayır';
  };
  const capabilityRows = capabilities.map(capability => {
    const cells = roles.map(role => {
      const value = canRole(role, capability);
      const style = value === 'Evet' ? 'text-success' : (value === 'Sahibi' ? 'text-info' : 'text-secondary');
      return `<td class="text-center small ${style}">${value}</td>`;
    }).join('');
    return `<tr><td>${escapeHtml(capability.entity_label || capability.entity_type)}</td><td>${escapeHtml(capability.action || '-')}</td><td><code>${escapeHtml(capability.permission || 'kayıt sahibi')}</code></td>${cells}</tr>`;
  }).join('');
  const recordRows = records.map(record => `<tr><td><span class="badge bg-light text-dark border">${escapeHtml(record.entity_type)}</span></td><td>${escapeHtml(record.label || record.entity_id || '-')}<small class="d-block text-secondary">${escapeHtml(record.entity_id || '')}</small></td><td>${escapeHtml(record.owner_username || record.owner_id || '-')}</td><td class="small text-secondary">${escapeHtml(record.created_at || '-')}</td></tr>`).join('');
  const overlay = openDialog(
    'İçerik Sahipliği ve İşlem Yetkileri',
    `<p class="text-secondary small">Rol tablosu yönetim API’sindeki izinlerden, “Sahibi” satırları ise kullanıcının kendi oluşturduğu kayıtlara uygulanan kapsamdan hesaplanır.</p>
     <div class="table-responsive mb-4" style="max-height:55vh"><table class="table table-sm table-bordered align-middle"><thead class="table-dark position-sticky top-0"><tr><th>Varlık</th><th>İşlem</th><th>Gerekli izin / kapsam</th>${roleHeading}</tr></thead><tbody>${capabilityRows || '<tr><td colspan="4" class="text-secondary">Yetki kaydı bulunamadı.</td></tr>'}</tbody></table></div>
     <h6>Son oluşturulan kayıtlar</h6><div class="table-responsive" style="max-height:35vh"><table class="table table-sm align-middle"><thead><tr><th>Tür</th><th>Kayıt</th><th>Sahibi</th><th>Oluşturulma</th></tr></thead><tbody>${recordRows || '<tr><td colspan="4" class="text-secondary">Kayıt bulunamadı.</td></tr>'}</tbody></table></div>`,
    async () => {},
    'modal-xl'
  );
  overlay.querySelector('button[type="submit"]')?.remove();
}

function packageForm(packageItem = {}) {
  return `<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Paket adı</label><input class="form-control" name="name" value="${escapeHtml(packageItem.name)}" required></div>
    <div class="col-md-3"><label class="form-label">Coin</label><input type="number" min="1" class="form-control" name="coin_amount" value="${escapeHtml(packageItem.coin_amount || '')}" required></div>
    <div class="col-md-3"><label class="form-label">Bonus coin</label><input type="number" min="0" class="form-control" name="bonus_coin" value="${escapeHtml(packageItem.bonus_coin || 0)}"></div>
    <div class="col-md-4"><label class="form-label">Fiyat</label><input type="number" min="0" step="0.01" class="form-control" name="display_price" value="${escapeHtml(packageItem.display_price || '0.00')}"></div>
    <div class="col-md-4"><label class="form-label">Para birimi</label><input class="form-control" name="currency" maxlength="3" value="${escapeHtml(packageItem.currency || 'TRY')}"></div>
    <div class="col-md-2"><label class="form-label">Sıra</label><input type="number" class="form-control" name="sort_order" value="${escapeHtml(packageItem.sort_order || 0)}"></div>
    <div class="col-md-2"><label class="form-label">Durum</label><select class="form-select" name="is_active"><option value="1" ${Number(packageItem.is_active ?? 1) === 1 ? 'selected' : ''}>Aktif</option><option value="0" ${Number(packageItem.is_active) === 0 ? 'selected' : ''}>Pasif</option></select></div>
  </div>`;
}

function openPackageEditor(packageItem = null) {
  openDialog(packageItem ? 'Paketi Düzenle' : 'Yeni Coin Paketi', packageForm(packageItem || {}), async formData => {
    const payload = Object.fromEntries(formData.entries());
    payload.coin_amount = Number(payload.coin_amount);
    payload.bonus_coin = Number(payload.bonus_coin || 0);
    payload.sort_order = Number(payload.sort_order || 0);
    payload.is_active = payload.is_active === '1';
    await api(packageItem ? `/shop/packages/${packageItem.id}` : '/shop/packages', { method: packageItem ? 'PUT' : 'POST', body: payload });
    closeDialog();
    showToast(packageItem ? 'Paket güncellendi' : 'Paket oluşturuldu');
    loadPackagesData();
  });
}

async function openWalletDialog(userId) {
  const canManageWallet = hasPermission('admin.wallet.manage');
  const canLoadPackages = canManageWallet && hasPermission('admin.shop.manage');
  const [walletResponse, transactionResponse, packageResponse] = await Promise.all([
    api(`/wallets/${userId}`),
    api(`/wallets/${userId}/transactions?per_page=50`),
    canLoadPackages ? api('/shop/packages?per_page=100') : Promise.resolve(null)
  ]);
  const wallet = walletResponse?.data || {};
  const transactions = responseItems(transactionResponse);
  const packages = responseItems(packageResponse);
  const rows = transactions.map(item => `<tr><td>${escapeHtml(item.type)}</td><td class="${Number(item.coin_delta) >= 0 ? 'text-success' : 'text-danger'}">${Number(item.coin_delta)}</td><td>${Number(item.balance_after || 0)}</td><td>${escapeHtml([item.reference_type, item.reference_id].filter(Boolean).join(':') || '-')}</td><td>${escapeHtml(item.created_at)}</td></tr>`).join('') || '<tr><td colspan="5" class="text-center text-secondary">İşlem bulunamadı</td></tr>';
  const overlay = openDialog(
    `Cüzdan: ${userId}`,
    `<div class="row g-3 mb-4"><div class="col-md-4"><div class="border rounded p-3"><small class="text-secondary">Bakiye</small><div class="fs-4 fw-bold text-warning">${Number(wallet.balance_coin || 0)} coin</div></div></div><div class="col-md-4"><div class="border rounded p-3"><small class="text-secondary">Toplam satın alınan</small><div class="fs-4 fw-bold">${Number(wallet.total_coin_purchased || 0)}</div></div></div><div class="col-md-4"><div class="border rounded p-3"><small class="text-secondary">Toplam harcanan</small><div class="fs-4 fw-bold">${Number(wallet.total_coin_spent || 0)}</div></div></div></div>
     <div data-requires-permission="admin.wallet.manage"><h6>Manuel bakiye işlemi</h6><div class="row g-2 mb-4"><div class="col-md-3"><select class="form-select" name="wallet_action"><option value="credit">Coin ekle</option><option value="debit">Coin düş</option></select></div><div class="col-md-3"><input type="number" min="1" class="form-control" name="amount" value="10" required></div><div class="col-md-6"><input class="form-control" name="reason" placeholder="İşlem nedeni" required></div></div></div>
     ${canLoadPackages ? `<div class="border rounded p-3 mb-4"><h6>Paket tanımla</h6><div class="row g-2"><div class="col-md-4"><select class="form-select" name="package_id">${packages.map(item => `<option value="${item.id}">${escapeHtml(item.name)} (${Number(item.total_coin || 0)} coin)</option>`).join('')}</select></div><div class="col-md-3"><input class="form-control" name="cash_amount" placeholder="Nakit tutarı"></div><div class="col-md-3"><input class="form-control" name="grant_reason" placeholder="Neden"></div><div class="col-md-2 d-grid"><button type="button" class="btn btn-info" data-grant-package>Paketi ver</button></div></div></div>` : ''}
     <h6>Son işlemler</h6><div class="table-responsive" style="max-height:280px"><table class="table table-sm"><thead><tr><th>Tür</th><th>Değişim</th><th>Son bakiye</th><th>Referans</th><th>Tarih</th></tr></thead><tbody>${rows}</tbody></table></div>`,
    async formData => {
      const action = String(formData.get('wallet_action')) === 'debit' ? 'debit' : 'credit';
      await api(`/wallets/${userId}/${action}`, { method: 'POST', body: { amount: Number(formData.get('amount')), reason: String(formData.get('reason') || '') } });
      showToast('Cüzdan güncellendi');
      await openWalletDialog(userId);
    },
    'modal-xl'
  );
  if (!canManageWallet) overlay.querySelector('button[type="submit"]')?.remove();
  overlay.addEventListener('click', async event => {
    if (!event.target.closest('[data-grant-package]')) return;
    const form = overlay.querySelector('form');
    try {
      await api(`/wallets/${userId}/grant-package`, { method: 'POST', body: { package_id: Number(form.elements.package_id.value), cash_amount: form.elements.cash_amount.value, reason: form.elements.grant_reason.value } });
      showToast('Paket kullanıcıya tanımlandı');
      await openWalletDialog(userId);
    } catch (error) { showToast(error.message, 'danger'); }
  });
}

async function openAdFreeDialog() {
  const response = await api('/features');
  const item = responseItems(response).find(feature => feature.feature_key === 'ad_free') || {};
  openDialog('Reklamsız Ürün Ayarı', `<div class="row g-3"><div class="col-md-6"><label class="form-label">Ürün adı</label><input class="form-control" name="name" value="${escapeHtml(item.name)}" required></div><div class="col-md-2"><label class="form-label">Coin fiyatı</label><input type="number" min="0" class="form-control" name="coin_price" value="${Number(item.coin_price || 0)}"></div><div class="col-md-2"><label class="form-label">Süre (gün)</label><input type="number" min="1" class="form-control" name="duration_days" value="${Number(item.duration_days || 30)}"></div><div class="col-md-2"><label class="form-label">Durum</label><select class="form-select" name="is_active"><option value="1" ${Number(item.is_active ?? 1) === 1 ? 'selected' : ''}>Aktif</option><option value="0" ${Number(item.is_active) === 0 ? 'selected' : ''}>Pasif</option></select></div></div>`, async formData => {
    const payload = Object.fromEntries(formData.entries());
    payload.coin_price = Number(payload.coin_price || 0);
    payload.duration_days = Number(payload.duration_days || 30);
    payload.is_active = payload.is_active === '1';
    await api('/features/ad-free', { method: 'PUT', body: payload });
    closeDialog();
    showToast('Reklamsız ürün ayarı kaydedildi');
  });
}

function openPricingDialog() {
  openDialog('Seri / Bölüm Fiyatlandırması', `<div class="row g-3"><div class="col-md-4"><label class="form-label">Hedef</label><select class="form-select" name="target_type"><option value="series">Seri</option><option value="chapters">Bölüm</option></select></div><div class="col-md-4"><label class="form-label">Hedef ID</label><input class="form-control" name="target_id" pattern="[a-z0-9]{6}" required></div><div class="col-md-2"><label class="form-label">Coin fiyatı</label><input type="number" min="0" class="form-control" name="price_coin" value="0"></div><div class="col-md-2"><label class="form-label">Durum</label><select class="form-select" name="is_active"><option value="1">Aktif</option><option value="0">Pasif</option></select></div></div>`, async formData => {
    const targetType = String(formData.get('target_type'));
    const targetId = String(formData.get('target_id'));
    await api(`/${targetType}/${targetId}/pricing`, { method: 'PUT', body: { price_coin: Number(formData.get('price_coin') || 0), is_active: formData.get('is_active') === '1' } });
    closeDialog();
    showToast('Fiyatlandırma kaydedildi');
  });
}

async function openWebhooksDialog() {
  const response = await api('/webhooks');
  const items = responseItems(response);
  const rows = items.map(item => `<tr><td>${escapeHtml(item.platform)}</td><td>${escapeHtml(item.event)}</td><td class="text-break">${escapeHtml(item.webhook_url)}</td><td>${Number(item.is_active) === 1 ? 'Aktif' : 'Pasif'}</td><td class="text-end"><button type="button" class="btn btn-xs btn-outline-info" data-test-webhook="${item.id}">Test</button> <button type="button" class="btn btn-xs btn-outline-danger" data-delete-webhook="${item.id}">Sil</button></td></tr>`).join('') || '<tr><td colspan="5" class="text-center text-secondary">Webhook bulunamadı</td></tr>';
  const overlay = openDialog('Webhook Yönetimi', `<div class="row g-2 mb-4"><div class="col-md-3"><select class="form-select" name="platform"><option value="discord">Discord</option><option value="telegram">Telegram</option><option value="custom">Özel HTTP</option></select></div><div class="col-md-3"><select class="form-select" name="event"><option value="chapter_published">Bölüm yayınlandı</option><option value="blog_approved">Blog onaylandı</option><option value="series_created">Seri oluşturuldu</option></select></div><div class="col-md-6"><input type="url" class="form-control" name="webhook_url" placeholder="https://..." required></div></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Platform</th><th>Olay</th><th>URL</th><th>Durum</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`, async formData => {
    await api('/webhooks', { method: 'POST', body: Object.fromEntries(formData.entries()) });
    showToast('Webhook oluşturuldu');
    await openWebhooksDialog();
  }, 'modal-xl');
  overlay.addEventListener('click', async event => {
    const testButton = event.target.closest('[data-test-webhook]');
    const deleteButton = event.target.closest('[data-delete-webhook]');
    try {
      if (testButton) {
        const result = await api(`/webhooks/${testButton.dataset.testWebhook}/test`, { method: 'POST' });
        showToast(result?.data?.success === false ? 'Webhook testi başarısız' : 'Webhook testi tamamlandı', result?.data?.success === false ? 'danger' : 'success');
      }
      if (deleteButton && confirm('Webhook silinsin mi?')) {
        await api(`/webhooks/${deleteButton.dataset.deleteWebhook}`, { method: 'DELETE' });
        showToast('Webhook silindi');
        await openWebhooksDialog();
      }
    } catch (error) { showToast(error.message, 'danger'); }
  });
}

async function openEnvDialog() {
  const response = await api('/maintenance/env');
  const values = response?.data || {};
  const envGroups = [
    { title: 'Uygulama ve Adres', icon: 'bi-gear', keys: ['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'SITE_ADDRESS', 'APP_TIMEZONE'] },
    { title: 'Oturum ve Kimlik Doğrulama', icon: 'bi-shield-lock', keys: ['SESSION_LIFETIME', 'SESSION_COOKIE_LIFETIME', 'REFRESH_TOKEN_DAYS', 'SESSION_COOKIE_SECURE', 'SESSION_COOKIE_SAME_SITE', 'REMEMBER_COOKIE_SECURE', 'REMEMBER_COOKIE_SAME_SITE', 'ENFORCE_HTTPS'] },
    { title: 'Cache, CORS ve Proxy', icon: 'bi-hdd-network', keys: ['CACHE_TTL', 'CORS_ALLOWED_ORIGINS', 'TRUSTED_PROXIES'] },
    { title: 'Entegrasyonlar', icon: 'bi-plug', keys: ['RESEND_API_KEY', 'MAIL_FROM_NAME', 'MAIL_FROM_ADDRESS', 'GOOGLE_ANALYTICS_ID', 'GOOGLE_RECAPTCHA_SITE_KEY', 'GOOGLE_RECAPTCHA_SECRET_KEY', 'CLOUDFLARE_TURNSTILE_SITE_KEY', 'CLOUDFLARE_TURNSTILE_SECRET_KEY'] }
  ];
  const editableKeys = envGroups.flatMap(group => group.keys);
  const booleanKeys = new Set(['APP_DEBUG', 'SESSION_COOKIE_SECURE', 'REMEMBER_COOKIE_SECURE', 'ENFORCE_HTTPS']);
  const numericKeys = new Set(['SESSION_LIFETIME', 'SESSION_COOKIE_LIFETIME', 'REFRESH_TOKEN_DAYS', 'CACHE_TTL']);
  const sensitiveKeys = new Set(editableKeys.filter(key => /(?:PASSWORD|SECRET|TOKEN|KEY)$/.test(key)));
  const labels = {
    APP_NAME: 'Uygulama adı', APP_ENV: 'Çalışma ortamı', APP_DEBUG: 'Debug modu', APP_URL: 'Uygulama URL', SITE_ADDRESS: 'Site adresi',
    APP_TIMEZONE: 'Saat dilimi', CORS_ALLOWED_ORIGINS: 'CORS izinli adresler', SESSION_LIFETIME: 'Oturum süresi (sn)', SESSION_COOKIE_LIFETIME: 'Oturum cookie süresi (sn)',
    REFRESH_TOKEN_DAYS: 'Refresh token süresi (gün)', CACHE_TTL: 'Cache süresi (sn)',
    SESSION_COOKIE_SECURE: 'Oturum çerezi Secure', SESSION_COOKIE_SAME_SITE: 'Oturum çerezi SameSite', ENFORCE_HTTPS: 'HTTPS zorunlu',
    REMEMBER_COOKIE_SECURE: 'Remember çerezi Secure', REMEMBER_COOKIE_SAME_SITE: 'Remember çerezi SameSite',
    TRUSTED_PROXIES: 'Güvenilen proxy adresleri', RESEND_API_KEY: 'Resend API anahtarı',
    GOOGLE_ANALYTICS_ID: 'Google Analytics ID', GOOGLE_RECAPTCHA_SITE_KEY: 'reCAPTCHA site anahtarı',
    GOOGLE_RECAPTCHA_SECRET_KEY: 'reCAPTCHA gizli anahtarı', CLOUDFLARE_TURNSTILE_SITE_KEY: 'Turnstile site anahtarı',
    CLOUDFLARE_TURNSTILE_SECRET_KEY: 'Turnstile gizli anahtarı', MAIL_FROM_NAME: 'Mail gönderici adı', MAIL_FROM_ADDRESS: 'Mail gönderici adresi'
  };
  const renderField = key => {
    const value = values[key] ?? '';
    const label = labels[key] || key;
    if (booleanKeys.has(key)) {
      const checked = String(value).toLowerCase() === 'true' || String(value) === '1';
      return `<div class="col-md-6"><div class="form-check form-switch border rounded-3 p-3"><input class="form-check-input ms-0 me-2" type="checkbox" id="env-${key}" name="${key}" data-env-key="${key}" data-env-type="boolean" value="true" ${checked ? 'checked' : ''}><label class="form-check-label fw-semibold" for="env-${key}">${escapeHtml(label)} <code>${key}</code></label></div></div>`;
    }
    const inputType = sensitiveKeys.has(key) ? 'password' : (numericKeys.has(key) ? 'number' : (key === 'MAIL_FROM_ADDRESS' ? 'email' : (key === 'APP_URL' || key === 'SITE_ADDRESS' ? 'url' : 'text')));
    const safeValue = sensitiveKeys.has(key) && value === '********' ? '********' : String(value);
    const placeholder = sensitiveKeys.has(key) ? 'Değiştirmek istemiyorsanız boş bırakın' : '';
    return `<div class="col-md-6"><label class="form-label fw-semibold" for="env-${key}">${escapeHtml(label)} <code>${key}</code></label><input type="${inputType}" class="form-control${sensitiveKeys.has(key) ? ' font-monospace' : ''}" id="env-${key}" name="${key}" data-env-key="${key}" value="${escapeHtml(safeValue)}" placeholder="${escapeHtml(placeholder)}" autocomplete="off"></div>`;
  };
  const groupedFields = envGroups.map(group => `<section class="mb-4 last-child-mb-0"><h6 class="border-bottom pb-2 mb-3"><i class="bi ${group.icon} text-secondary me-2"></i>${escapeHtml(group.title)}</h6><div class="row g-3">${group.keys.map(renderField).join('')}</div></section>`).join('');
  openDialog('Ortam Değişkenleri (.env)', `<div class="alert alert-warning small">Bu alan yalnızca root yönetici içindir. Hassas değerler maskeli gösterilir; değiştirmek istemediğiniz gizli alanları boş bırakabilirsiniz. Kaydetme sırasında mevcut değerler korunur ve yedek alınır.</div>${groupedFields}`, async (formData, form) => {
    const payload = {};
    form.querySelectorAll('[data-env-key]').forEach(input => {
      const key = input.dataset.envKey;
      const value = input.dataset.envType === 'boolean' ? (input.checked ? 'true' : 'false') : input.value;
      if (sensitiveKeys.has(key) && (value === '' || value === '********')) return;
      payload[key] = value;
    });
    await api('/maintenance/env', { method: 'POST', body: payload });
    closeDialog();
    showToast('.env kaydedildi');
  }, 'modal-xl');
}

async function openLogDialog(path) {
  const response = await api(`/${path}?per_page=100`);
  const items = responseItems(response);
  const columns = items.length ? Object.keys(items[0]).slice(0, 8) : [];
  const heading = columns.map(column => `<th>${escapeHtml(column)}</th>`).join('');
  const rows = items.map(item => `<tr>${columns.map(column => `<td class="text-break">${escapeHtml(typeof item[column] === 'object' ? JSON.stringify(item[column]) : item[column])}</td>`).join('')}</tr>`).join('') || `<tr><td class="text-center text-secondary">Kayıt bulunamadı</td></tr>`;
  const overlay = openDialog('Log Görüntüleyici', `<div class="table-responsive" style="max-height:70vh"><table class="table table-sm table-hover font-monospace"><thead class="table-dark position-sticky top-0"><tr>${heading}</tr></thead><tbody>${rows}</tbody></table></div>`, async () => {}, 'modal-xl');
  overlay.querySelector('button[type="submit"]')?.remove();
}

function panelNavigate(path) {
  if (!path) return;
  history.pushState({}, '', path);
  navigate();
}

function envPageMarkup(values) {
  const groups = [
    { title: 'Uygulama ve Adres', keys: ['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'SITE_ADDRESS', 'APP_TIMEZONE'] },
    { title: 'Oturum ve Güvenlik', keys: ['SESSION_LIFETIME', 'SESSION_COOKIE_LIFETIME', 'REFRESH_TOKEN_DAYS', 'SESSION_COOKIE_SECURE', 'SESSION_COOKIE_SAME_SITE', 'REMEMBER_COOKIE_SECURE', 'REMEMBER_COOKIE_SAME_SITE', 'ENFORCE_HTTPS'] },
    { title: 'Cache, CORS ve Proxy', keys: ['CACHE_TTL', 'CORS_ALLOWED_ORIGINS', 'TRUSTED_PROXIES'] },
    { title: 'Entegrasyonlar', keys: ['RESEND_API_KEY', 'MAIL_FROM_NAME', 'MAIL_FROM_ADDRESS', 'GOOGLE_ANALYTICS_ID', 'GOOGLE_RECAPTCHA_SITE_KEY', 'GOOGLE_RECAPTCHA_SECRET_KEY', 'CLOUDFLARE_TURNSTILE_SITE_KEY', 'CLOUDFLARE_TURNSTILE_SECRET_KEY'] }
  ];
  const booleans = new Set(['APP_DEBUG', 'SESSION_COOKIE_SECURE', 'REMEMBER_COOKIE_SECURE', 'ENFORCE_HTTPS']);
  const numericKeys = new Set(['SESSION_LIFETIME', 'SESSION_COOKIE_LIFETIME', 'REFRESH_TOKEN_DAYS', 'CACHE_TTL']);
  const sensitive = key => /(?:PASSWORD|SECRET|TOKEN|KEY)$/.test(key);
  const labels = { APP_NAME: 'Uygulama adı', APP_ENV: 'Çalışma ortamı', APP_DEBUG: 'Debug modu', APP_URL: 'Uygulama URL', SITE_ADDRESS: 'Site adresi', APP_TIMEZONE: 'Saat dilimi', SESSION_LIFETIME: 'Oturum süresi (sn)', SESSION_COOKIE_LIFETIME: 'Oturum cookie süresi (sn)', REFRESH_TOKEN_DAYS: 'Refresh token süresi (gün)', CACHE_TTL: 'Cache süresi (sn)', SESSION_COOKIE_SECURE: 'Oturum çerezi Secure', SESSION_COOKIE_SAME_SITE: 'Oturum çerezi SameSite', ENFORCE_HTTPS: 'HTTPS zorunlu', REMEMBER_COOKIE_SECURE: 'Remember çerezi Secure', REMEMBER_COOKIE_SAME_SITE: 'Remember çerezi SameSite', CORS_ALLOWED_ORIGINS: 'CORS izinli adresler', TRUSTED_PROXIES: 'Güvenilen proxy adresleri', RESEND_API_KEY: 'Resend API anahtarı', MAIL_FROM_NAME: 'Mail gönderici adı', MAIL_FROM_ADDRESS: 'Mail gönderici adresi', GOOGLE_ANALYTICS_ID: 'Google Analytics ID', GOOGLE_RECAPTCHA_SITE_KEY: 'reCAPTCHA site anahtarı', GOOGLE_RECAPTCHA_SECRET_KEY: 'reCAPTCHA gizli anahtarı', CLOUDFLARE_TURNSTILE_SITE_KEY: 'Turnstile site anahtarı', CLOUDFLARE_TURNSTILE_SECRET_KEY: 'Turnstile gizli anahtarı' };
  const field = key => {
    const value = values[key] ?? '';
    const label = labels[key] || key;
    if (booleans.has(key)) {
      const checked = ['true', '1', 'yes', 'on'].includes(String(value).toLowerCase());
      return `<div class="col-md-6"><div class="form-check form-switch border rounded-3 p-3"><input class="form-check-input ms-0 me-2" type="checkbox" id="route-env-${key}" data-env-key="${key}" data-env-type="boolean" ${checked ? 'checked' : ''}><label class="form-check-label fw-semibold" for="route-env-${key}">${escapeHtml(label)} <code>${key}</code></label></div></div>`;
    }
    const isSecret = sensitive(key);
    const type = isSecret ? 'password' : (numericKeys.has(key) ? 'number' : (key === 'MAIL_FROM_ADDRESS' ? 'email' : (key === 'APP_URL' || key === 'SITE_ADDRESS' ? 'url' : 'text')));
    return `<div class="col-md-6"><label class="form-label fw-semibold" for="route-env-${key}">${escapeHtml(label)} <code>${key}</code></label><input class="form-control${isSecret ? ' font-monospace' : ''}" type="${type}" id="route-env-${key}" data-env-key="${key}" value="${escapeHtml(String(value))}" placeholder="${isSecret ? 'Değiştirmek istemiyorsanız boş bırakın' : ''}" autocomplete="off"></div>`;
  };
  return `<div class="alert alert-warning small">Bu sayfa yalnızca root yönetici içindir. Hassas değerler maskeli gösterilir ve boş bırakılırsa korunur.</div><form id="panel-config-env-form"><div class="card-body p-0">${groups.map(group => `<section class="mb-4"><h6 class="border-bottom pb-2 mb-3">${escapeHtml(group.title)}</h6><div class="row g-3">${group.keys.map(field).join('')}</div></section>`).join('')}</div><div class="d-flex justify-content-end gap-2"><a href="/panel/config" data-panel-link class="btn btn-outline-secondary">İptal</a><button class="btn btn-primary" type="submit">Kaydet</button></div></form>`;
}

async function loadEnvPage() {
  const target = document.getElementById('panel-config-env-page');
  if (!target) return;
  try {
    const response = await api('/maintenance/env');
    target.innerHTML = envPageMarkup(response?.data || {});
    const form = target.querySelector('#panel-config-env-form');
    form?.addEventListener('submit', async event => {
      event.preventDefault();
      const payload = {};
      form.querySelectorAll('[data-env-key]').forEach(input => {
        const key = input.dataset.envKey;
        const value = input.dataset.envType === 'boolean' ? (input.checked ? 'true' : 'false') : input.value;
        if (/(?:PASSWORD|SECRET|TOKEN|KEY)$/.test(key) && (value === '' || value === '********')) return;
        payload[key] = value;
      });
      try { await api('/maintenance/env', { method: 'POST', body: payload }); showToast('.env kaydedildi'); panelNavigate('/panel/config-env'); }
      catch (error) { showToast(error.message, 'danger'); }
    });
  } catch (error) { target.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; }
}

async function loadWebhookPage() {
  const target = document.getElementById('panel-webhook-page');
  if (!target) return;
  try {
    const response = await api('/webhooks');
    const items = responseItems(response);
    const rows = items.map(item => `<tr><td>${escapeHtml(item.platform)}</td><td>${escapeHtml(item.event)}</td><td class="text-break">${escapeHtml(item.webhook_url)}</td><td>${Number(item.is_active) === 1 ? 'Aktif' : 'Pasif'}</td><td class="text-end text-nowrap"><button type="button" class="btn btn-xs btn-outline-info me-1" data-test-webhook="${item.id}">Test</button><button type="button" class="btn btn-xs btn-outline-danger" data-delete-webhook="${item.id}">Sil</button></td></tr>`).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">Webhook bulunamadı</td></tr>';
    target.innerHTML = `<form id="panel-webhook-form" class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-3"><div class="col-md-3"><label class="form-label">Platform</label><select class="form-select" name="platform"><option value="discord">Discord</option><option value="telegram">Telegram</option><option value="custom">Özel HTTP</option></select></div><div class="col-md-3"><label class="form-label">Olay</label><select class="form-select" name="event"><option value="chapter_published">Bölüm yayınlandı</option><option value="blog_approved">Blog onaylandı</option><option value="series_created">Seri oluşturuldu</option></select></div><div class="col-md-6"><label class="form-label">Webhook URL</label><input type="url" class="form-control" name="webhook_url" placeholder="https://..." required></div></div></div><div class="card-footer bg-transparent text-end"><button class="btn btn-primary" type="submit">Webhook ekle</button></div></form><div class="card border-0 shadow-sm"><div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Platform</th><th>Olay</th><th>URL</th><th>Durum</th><th></th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
    target.querySelector('#panel-webhook-form')?.addEventListener('submit', async event => { event.preventDefault(); try { await api('/webhooks', { method: 'POST', body: Object.fromEntries(new FormData(event.currentTarget).entries()) }); showToast('Webhook oluşturuldu'); await loadWebhookPage(); } catch (error) { showToast(error.message, 'danger'); } });
    target.onclick = async event => {
      const test = event.target.closest('[data-test-webhook]');
      const remove = event.target.closest('[data-delete-webhook]');
      try {
        if (test) { const result = await api(`/webhooks/${test.dataset.testWebhook}/test`, { method: 'POST' }); showToast(result?.data?.success === false ? 'Webhook testi başarısız' : 'Webhook testi tamamlandı', result?.data?.success === false ? 'danger' : 'success'); }
        if (remove && confirm('Webhook silinsin mi?')) { await api(`/webhooks/${remove.dataset.deleteWebhook}`, { method: 'DELETE' }); showToast('Webhook silindi'); await loadWebhookPage(); }
      } catch (error) { showToast(error.message, 'danger'); }
    };
  } catch (error) { target.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; }
}

async function loadReportDetailPage(id) {
  const target = document.getElementById('panel-report-detail-page');
  if (!target) return;
  try {
    const response = await api(`/reports/${encodeURIComponent(id)}`);
    const report = response?.data || {};
    const crumb = document.getElementById('panel-report-breadcrumb');
    if (crumb) crumb.innerHTML = `<li class="breadcrumb-item"><a href="/panel" data-panel-link>Panel</a></li><li class="breadcrumb-item"><a href="/panel/reports" data-panel-link>Raporlar</a></li><li class="breadcrumb-item active">#${Number(report.id || id)}</li>`;
    const targetLink = report.target_url ? `<a class="btn btn-sm btn-outline-primary" href="${safeLocalUrl(report.target_url)}" target="_blank" rel="noopener">Hedefi aç</a>` : '<span class="text-secondary">Hedef bağlantısı yok</span>';
    target.innerHTML = `<form id="panel-report-detail-form" class="card border-0 shadow-sm"><div class="card-body p-4"><div class="row g-4"><div class="col-md-4"><small class="text-secondary d-block">Bildiren</small><strong>@${escapeHtml(report.reporter_username)}</strong></div><div class="col-md-4"><small class="text-secondary d-block">Hedef</small><span>${escapeHtml(report.target_type)} / ${escapeHtml(report.target_title || report.target_id)}</span></div><div class="col-md-4"><small class="text-secondary d-block">Neden</small><span>${escapeHtml(report.reason)}</span></div><div class="col-12"><small class="text-secondary d-block">Açıklama</small><div class="border rounded p-3 bg-body-tertiary">${escapeHtml(report.description || report.comment_body || 'Açıklama yok')}</div></div><div class="col-12">${targetLink}</div><div class="col-md-4"><label class="form-label">Durum</label><select class="form-select" name="status"><option value="pending" ${report.status === 'pending' ? 'selected' : ''}>Bekleyen</option><option value="reviewing" ${report.status === 'reviewing' ? 'selected' : ''}>İncelenen</option><option value="resolved" ${report.status === 'resolved' ? 'selected' : ''}>Çözüldü</option><option value="rejected" ${report.status === 'rejected' ? 'selected' : ''}>Reddedildi</option></select></div><div class="col-12"><label class="form-label">Moderatör notu</label><textarea class="form-control" name="admin_note" rows="6" maxlength="2000">${escapeHtml(report.admin_note)}</textarea></div></div></div><div class="card-footer bg-transparent d-flex justify-content-end"><button class="btn btn-primary" type="submit">Kaydet</button></div></form>`;
    const form = target.querySelector('#panel-report-detail-form');
    if (!hasPermission('admin.reports.manage')) { form.querySelectorAll('select, textarea').forEach(input => { input.disabled = true; }); form.querySelector('button[type="submit"]')?.remove(); }
    form.addEventListener('submit', async event => { event.preventDefault(); try { await api(`/reports/${encodeURIComponent(id)}`, { method: 'PUT', body: Object.fromEntries(new FormData(form).entries()) }); showToast('Rapor güncellendi'); panelNavigate('/panel/reports'); } catch (error) { showToast(error.message, 'danger'); } });
  } catch (error) { target.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; }
}

async function loadSeriesEditorPage(mode, id = null) {
  const target = document.getElementById('panel-series-editor-fields');
  const form = document.getElementById('panel-series-editor-form');
  if (!target || !form) return;
  try {
    let content = {};
    if (id) {
      let found = (store.get('allSeriesList') || []).find(item => String(item.id) === String(id));
      if (!found) found = responseItems(await api(`/series?q=${encodeURIComponent(id)}&per_page=100`)).find(item => String(item.id) === String(id));
      if (!found) throw new Error('İçerik bulunamadı');
      content = found;
    }
    const { genres, tags } = await loadTaxonomies();
    document.getElementById('panel-series-editor-title').textContent = mode === 'edit' ? 'Seriyi düzenle' : 'Yeni seri oluştur';
    const crumb = document.getElementById('panel-series-breadcrumb');
    if (crumb) crumb.innerHTML = `<li class="breadcrumb-item"><a href="/panel" data-panel-link>Panel</a></li><li class="breadcrumb-item"><a href="/panel/series" data-panel-link>İçerikler</a></li><li class="breadcrumb-item active">${mode === 'edit' ? 'Düzenle' : 'Yeni'}</li>`;
    target.innerHTML = contentForm(content, genres, tags);
    bindTaxonomyButtons(form);
    const uploadedPaths = [];
    form.querySelector('[name="cover_file"]')?.addEventListener('change', async event => { try { const paths = await uploadImages(event.target.files, 'series_cover'); uploadedPaths.push(...paths); if (paths[0]) form.querySelector('[name="cover_image"]').value = paths[0]; showToast('Kapak görseli yüklendi'); } catch (error) { showToast(error.message, 'danger'); } });
    form.addEventListener('submit', async event => { event.preventDefault(); try { const data = new FormData(form); const selectedGenres = selectedValues(data, 'genres'); const selectedTags = selectedValues(data, 'tags'); const payload = Object.fromEntries(data.entries()); delete payload.genres; delete payload.tags; delete payload.cover_file; payload.is_adult = form.elements.is_adult.checked ? 1 : 0; payload.is_members_only = form.elements.is_members_only.checked ? 1 : 0; payload.disable_comments = form.elements.disable_comments.checked ? 1 : 0; const response = mode === 'edit' ? await api(`/content/${encodeURIComponent(id)}`, { method: 'PUT', body: payload }) : await api('/content', { method: 'POST', body: payload }); const contentId = id || response?.data?.id; if (contentId) await api(`/contents/${encodeURIComponent(contentId)}/taxonomy`, { method: 'PUT', body: { genres: selectedGenres, tags: selectedTags } }); showToast(mode === 'edit' ? 'İçerik güncellendi' : 'İçerik oluşturuldu'); panelNavigate('/panel/series'); } catch (error) { showToast(error.message, 'danger'); } });
  } catch (error) { target.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; }
}

const actionPermissions = {
  taxonomy: ['admin.content.create'], ownership: ['admin.panel.access'], rbac: ['admin.panel.access'], chapters: ['admin.panel.access'], preview: ['admin.panel.access'], revisions: ['admin.panel.access'], team: ['admin.panel.access'], 'user-edit': ['admin.users.manage'], 'user-detail': ['admin.users.manage'], 'user-penalty': ['admin.users.manage'], wallet: ['admin.wallet.view'], 'package-new': ['admin.shop.manage'], 'package-edit': ['admin.shop.manage'], 'ad-free': ['admin.shop.manage'], pricing: ['admin.shop.manage'], moderation: ['admin.logs.view'], 'log-viewer': ['admin.logs.view'], 'audit-log': ['admin.logs.view']
};

const actionParents = {
  taxonomy: '/panel', ownership: '/panel/users', rbac: '/panel/users', chapters: '/panel/series', preview: '/panel/series', revisions: '/panel/series', team: '/panel/series', 'user-edit': '/panel/users', 'user-detail': '/panel/users', 'user-penalty': '/panel/users', wallet: '/panel/users', 'package-new': '/panel/monetization', 'package-edit': '/panel/monetization', 'ad-free': '/panel/monetization', pricing: '/panel/monetization', moderation: '/panel/logs', 'log-viewer': '/panel/logs', 'audit-log': '/panel/logs'
};

async function loadPanelActionPage(action, id = null) {
  pageDialogMode = true;
  pageDialogParent = actionParents[action] || '/panel';
  if (action === 'user-penalty' && id) pageDialogParent = `/panel/action/user-detail/${encodeURIComponent(id)}`;
  try {
    if (action === 'taxonomy') await openTaxonomyDialog();
    else if (action === 'ownership') await openOwnershipMatrix();
    else if (action === 'rbac') await openRbacDialog();
    else if (action === 'chapters') await openChaptersDialog(id);
    else if (action === 'preview') await openSeriesPreview(id);
    else if (action === 'revisions') await openSeriesRevisions(id);
    else if (action === 'team') {
      let content = (store.get('allSeriesList') || []).find(item => String(item.id) === String(id));
      if (!content) content = responseItems(await api(`/series?q=${encodeURIComponent(id)}&per_page=100`)).find(item => String(item.id) === String(id));
      if (!content) throw new Error('İçerik bulunamadı');
      await openTeamDialog(content);
    } else if (action === 'user-edit') {
      if (!(store.get('allUsersList') || []).some(item => String(item.id) === String(id))) {
        const found = responseItems(await api(`/users?q=${encodeURIComponent(id)}&per_page=100`)).find(item => String(item.id) === String(id));
        if (found) store.set('allUsersList', [found]);
      }
      await openUserEditor(id);
    } else if (action === 'user-detail') await loadUserDetailPage(id);
    else if (action === 'user-penalty') await loadUserPenaltyPage(id);
    else if (action === 'wallet') await openWalletDialog(id);
    else if (action === 'package-new') await openPackageEditor();
    else if (action === 'package-edit') {
      let item = (store.get('packagesList') || []).find(packageItem => String(packageItem.id) === String(id));
      if (!item) item = responseItems(await api('/shop/packages?per_page=100')).find(packageItem => String(packageItem.id) === String(id));
      if (!item) throw new Error('Paket bulunamadı');
      await openPackageEditor(item);
    } else if (action === 'ad-free') await openAdFreeDialog();
    else if (action === 'pricing') openPricingDialog();
    else if (action === 'moderation') openModerationActionDialog();
    else if (action === 'log-viewer') await openLogDialog({ 'login-events': 'login-events', error: 'logs/error', errors: 'logs/error', 'moderation-actions': 'moderation-actions', moderation: 'moderation-actions' }[id] || id);
    else if (action === 'audit-log') {
      let item = (store.get('logsList') || []).find(log => String(log.id) === String(id));
      if (!item) item = responseItems(await api('/audit-logs?per_page=100')).find(log => String(log.id) === String(id));
      if (!item) throw new Error('Denetim kaydı bulunamadı');
      openDialog(`Denetim Kaydı #${item.id}`, `<pre class="bg-dark text-light rounded p-3 mb-0 text-wrap">${escapeHtml(JSON.stringify(item, null, 2))}</pre>`, async () => {});
      document.querySelector('#panel-dialog button[type="submit"]')?.remove();
    }
  } catch (error) {
    const target = document.getElementById('panel-action-page');
    if (target) target.innerHTML = `<div class="p-4"><a class="btn btn-outline-secondary btn-lg" href="${escapeHtml(pageDialogParent)}" data-panel-link><i class="bi bi-arrow-left me-1"></i>Geri dön</a><div class="alert alert-danger mt-4">${escapeHtml(error.message)}</div></div>`;
  }
}

function openModerationActionDialog() {
  openDialog(
    'Yeni Moderasyon Kaydı',
    `<div class="row g-3">
      <div class="col-md-4"><label class="form-label">Hedef türü</label><select class="form-select" name="target_type" required><option value="user">Kullanıcı</option><option value="content">İçerik</option><option value="chapter">Bölüm</option><option value="blog">Blog</option><option value="comment">Yorum</option><option value="system">Sistem</option></select></div>
      <div class="col-md-4"><label class="form-label">Hedef ID</label><input class="form-control" name="target_id" required></div>
      <div class="col-md-4"><label class="form-label">İşlem</label><input class="form-control" name="action" placeholder="warn, hide, review..." required></div>
      <div class="col-12"><label class="form-label">Gerekçe</label><textarea class="form-control" name="reason" rows="4"></textarea></div>
    </div>`,
    async formData => {
      await api('/moderation-actions', { method: 'POST', body: Object.fromEntries(formData.entries()) });
      closeDialog();
      showToast('Moderasyon kaydı oluşturuldu');
      loadLogsData();
    }
  );
}

async function openReportDialog(reportId) {
  const response = await api(`/reports/${reportId}`);
  const report = response?.data || {};
  const target = report.target_url
    ? `<a class="btn btn-sm btn-outline-primary" href="${safeLocalUrl(report.target_url)}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Hedefi aç</a>`
    : `<span class="text-secondary">Hedef bağlantısı mevcut değil</span>`;
  const overlay = openDialog(
    `Rapor #${Number(report.id || reportId)}`,
    `<div class="row g-3 mb-4">
      <div class="col-md-4"><small class="text-secondary d-block">Bildiren</small><strong>@${escapeHtml(report.reporter_username)}</strong></div>
      <div class="col-md-4"><small class="text-secondary d-block">Hedef</small><span>${escapeHtml(report.target_type)} / ${escapeHtml(report.target_title || report.target_id)}</span></div>
      <div class="col-md-4"><small class="text-secondary d-block">Neden</small><span>${escapeHtml(report.reason)}</span></div>
      <div class="col-12"><small class="text-secondary d-block">Açıklama</small><div class="border rounded p-3 bg-body-tertiary">${escapeHtml(report.description || report.comment_body || 'Açıklama yok')}</div></div>
      <div class="col-12">${target}</div>
      <div class="col-md-4"><label class="form-label">Durum</label><select class="form-select" name="status"><option value="pending" ${report.status === 'pending' ? 'selected' : ''}>Bekleyen</option><option value="reviewing" ${report.status === 'reviewing' ? 'selected' : ''}>İncelenen</option><option value="resolved" ${report.status === 'resolved' ? 'selected' : ''}>Çözüldü</option><option value="rejected" ${report.status === 'rejected' ? 'selected' : ''}>Reddedildi</option></select></div>
      <div class="col-md-8"><label class="form-label">Moderatör notu</label><textarea class="form-control" name="admin_note" rows="4" maxlength="2000">${escapeHtml(report.admin_note)}</textarea></div>
    </div>`,
    async formData => {
      await api(`/reports/${reportId}`, { method: 'PUT', body: Object.fromEntries(formData.entries()) });
      closeDialog();
      showToast('Rapor güncellendi');
      await loadReportsData(Number(store.get('reportsMeta')?.page || 1));
    }
  );
  if (!hasPermission('admin.reports.manage')) {
    overlay.querySelectorAll('select, textarea').forEach(field => { field.disabled = true; });
    overlay.querySelector('button[type="submit"]')?.remove();
  }
}

async function openChaptersDialog(contentId, page = 1) {
  let content = (store.get('allSeriesList') || []).find(item => String(item.id) === String(contentId));
  if (!content) content = responseItems(await api(`/series?q=${encodeURIComponent(contentId)}&per_page=100`)).find(item => String(item.id) === String(contentId));
  if (!content) throw new Error('İçerik bulunamadı');
  const response = await api(`/content/${content.id}/chapters?page=${Math.max(1, Number(page))}&per_page=25`);
  const chapters = responseItems(response);
  const chapterMeta = responseMeta(response);
  const rows = chapters.map(chapter => `
    <tr>
      <td><input type="checkbox" class="form-check-input" data-chapter-select value="${escapeHtml(chapter.id)}"></td>
      <td>${escapeHtml(chapter.chapter_number)}</td>
      <td>${escapeHtml(chapter.title || '-')}</td>
      <td>${escapeHtml(chapter.type)}</td>
      <td>${Number(chapter.price_amount || 0)} coin</td>
      <td>${escapeHtml(chapter.published_at || '-')}</td>
      <td class="text-end">
        <button type="button" class="btn btn-xs btn-outline-primary" data-edit-chapter="${escapeHtml(chapter.id)}" data-requires-permission="admin.content.update"><i class="bi bi-pencil"></i></button>
        <button type="button" class="btn btn-xs btn-outline-danger" data-delete-chapter="${escapeHtml(chapter.id)}" data-requires-permission="admin.content.update"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`).join('');
  const overlay = openDialog(
    `${content.title} — Bölümler (Sayfa ${chapterMeta.page}/${chapterMeta.total_pages})`,
    `<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div class="btn-group btn-group-sm" data-requires-permission="admin.content.update"><button type="button" class="btn btn-outline-success" data-bulk-chapter="publish">Yayınla</button><button type="button" class="btn btn-outline-warning" data-bulk-chapter="schedule">Zamanla</button><button type="button" class="btn btn-outline-info" data-bulk-chapter="set_price">Fiyatlandır</button><button type="button" class="btn btn-outline-danger" data-bulk-chapter="delete">Sil</button></div><div class="d-flex gap-2"><button type="button" class="btn btn-outline-primary" data-manage-team data-requires-permission="admin.content.update"><i class="bi bi-people me-1"></i>Ekip</button><button type="button" class="btn btn-primary" data-create-chapter data-requires-permission="admin.chapter.create"><i class="bi bi-plus-lg me-1"></i>Yeni Bölüm</button></div></div>
     <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><input type="checkbox" class="form-check-input" data-select-all-chapters></th><th>#</th><th>Başlık</th><th>Tür</th><th>Fiyat</th><th>Yayın</th><th></th></tr></thead><tbody>${rows || '<tr><td colspan="7" class="text-center text-secondary py-4">Bölüm bulunamadı</td></tr>'}</tbody></table></div>
     <div class="d-flex justify-content-between align-items-center mt-3"><small class="text-secondary">${Number(chapterMeta.total || chapters.length)} bölüm</small><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-chapter-page="prev" ${chapterMeta.page <= 1 ? 'disabled' : ''}>Önceki</button><button type="button" class="btn btn-outline-secondary" data-chapter-page="next" ${chapterMeta.page >= chapterMeta.total_pages ? 'disabled' : ''}>Sonraki</button></div></div>`,
    async () => {},
    'modal-xl'
  );
  overlay.querySelector('button[type="submit"]')?.remove();
  overlay.addEventListener('click', async event => {
    const pageButton = event.target.closest('[data-chapter-page]');
    if (pageButton && !pageButton.disabled) {
      const nextPage = chapterMeta.page + (pageButton.dataset.chapterPage === 'next' ? 1 : -1);
      await openChaptersDialog(content.id, nextPage);
      return;
    }
    const createButton = event.target.closest('[data-create-chapter]');
    const teamButton = event.target.closest('[data-manage-team]');
    const editButton = event.target.closest('[data-edit-chapter]');
    const deleteButton = event.target.closest('[data-delete-chapter]');
    const bulkButton = event.target.closest('[data-bulk-chapter]');
    if (createButton) await openChapterEditor(content);
    if (teamButton) await openTeamDialog(content);
    if (editButton) await openChapterEditor(content, editButton.dataset.editChapter);
    if (deleteButton && confirm('Bu bölümü silmek istediğinize emin misiniz?')) {
      try {
        await api(`/chapters/${deleteButton.dataset.deleteChapter}`, { method: 'DELETE' });
        showToast('Bölüm silindi');
        await loadSeriesData();
        await openChaptersDialog(content.id);
      } catch (error) { showToast(error.message, 'danger'); }
    }
    if (bulkButton) {
      const ids = Array.from(overlay.querySelectorAll('[data-chapter-select]:checked')).map(input => input.value);
      if (!ids.length) return showToast('Önce en az bir bölüm seçin', 'danger');
      const action = bulkButton.dataset.bulkChapter;
      const params = {};
      if (action === 'schedule') {
        const publishedAt = prompt('Yayın tarihi (YYYY-MM-DD HH:MM):');
        if (!publishedAt) return;
        params.published_at = publishedAt;
      }
      if (action === 'set_price') {
        const price = prompt('Coin fiyatı:', '0');
        if (price === null) return;
        params.price_amount = Number(price);
        const freeAfter = prompt('Ücretsiz olma tarihi (isteğe bağlı):', '');
        if (freeAfter) params.is_free_after = freeAfter;
      }
      if (action === 'delete' && !confirm(`${ids.length} bölümü silmek istediğinize emin misiniz?`)) return;
      try {
        const result = await api('/chapters/bulk', { method: 'POST', body: { ids, action, params } });
        showToast(`${result?.data?.affected || ids.length} bölüm güncellendi`);
        await loadSeriesData();
        await openChaptersDialog(content.id);
      } catch (error) { showToast(error.message, 'danger'); }
    }
  });
  overlay.querySelector('[data-select-all-chapters]')?.addEventListener('change', event => {
    overlay.querySelectorAll('[data-chapter-select]').forEach(input => { input.checked = event.target.checked; });
  });
}

// 4. Data Fetchers
async function loadDashboardData() {
  try {
    const [data, insights, monetization, searches] = await Promise.all([
      api('/overview'),
      api('/metrics/insights?days=30&limit=10').catch(() => null),
      api('/analytics/monetization?days=30').catch(() => null),
      api('/analytics/search-insights?days=30&limit=10').catch(() => null)
    ]);
    if (data?.data) {
      const metrics = data.data.metrics || {};
      const insightData = insights?.data || {};
      const visits = insightData.visits || {};
      const views = insightData.views || {};
      const blogSummary = insightData.blogs?.summary || {};
      const money = monetization?.data || {};
      const searchData = searches?.data || {};
      store.batch(() => {
        store.set('overview.total_users', data.data.kpis?.users_total || 0);
        store.set('overview.total_contents', data.data.kpis?.contents_total || 0);
        store.set('overview.total_chapters', data.data.kpis?.chapters_total || 0);
        store.set('overview.queue_pending', data.data.kpis?.blogs_pending_total || 0);
        store.set('topContents', metrics.top_contents_7d || []);
        store.set('analytics.visits_daily', visits.daily || 0);
        store.set('analytics.visits_weekly', visits.weekly || 0);
        store.set('analytics.visits_monthly', visits.monthly || 0);
        store.set('analytics.home_to_content', `${metrics.funnel?.home_to_content_pct || 0}%`);
        store.set('analytics.content_to_chapter', `${metrics.funnel?.content_to_chapter_pct || 0}%`);
        store.set('analytics.error_rate', `${metrics.performance_slo?.server_error_rate_pct_24h || 0}%`);
        store.set('analytics.p95', `${metrics.performance_slo?.p95_duration_ms_24h || 0} ms`);
        store.set('analytics.search_total', metrics.retention_search?.search_total_7d || 0);
        store.set('analytics.zero_result_pct', `${metrics.retention_search?.zero_result_pct_7d || 0}%`);
        store.set('analytics.d1_retention', `${metrics.retention_search?.d1_retention_pct || 0}%`);
        store.set('analytics.new_users', metrics.retention_search?.new_users_7d || 0);
        store.set('analytics.total_coins', money.total_coins_spent || 0);
        store.set('analytics.total_unlocks', money.total_unlocks || 0);
        store.set('analytics.blog_total', blogSummary.total || 0);
        store.set('analytics.blog_visible', blogSummary.visible_total || 0);
        store.set('analytics.blog_hidden', blogSummary.hidden_total || 0);
        store.set('analytics.blog_deleted', blogSummary.deleted_total || 0);
        store.set('analytics.blog_created', blogSummary.created_last_days || 0);
        store.set('analytics.blog_approved', blogSummary.approved_last_days || 0);
        store.set('dashboardGenres', views.series_genres || []);
        store.set('dashboardTags', views.series_tags || []);
        store.set('dashboardReputation', insightData.reputation || []);
        store.set('dashboardTypes', views.types || []);
        store.set('dashboardChapters', views.chapters || []);
        store.set('dashboardBlogAuthors', insightData.blogs?.top_authors || []);
        store.set('dashboardBlogDailyCreated', insightData.blogs?.daily_created || []);
        store.set('dashboardBlogDailyApproved', insightData.blogs?.daily_approved || []);
        store.set('monetizationSeries', money.top_series || []);
        store.set('zeroResultSearches', searchData.zero_result_searches || []);
      });
      renderDashboardTables();
    }
  } catch (e) {
    console.error('Dashboard load error:', e);
  }
}

async function loadSeriesData(page = 1) {
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '20' });
    const values = {
      q: document.getElementById('panel-series-search')?.value || '',
      status: document.getElementById('panel-series-status')?.value || '',
      type: document.getElementById('panel-series-type')?.value || '',
      lifecycle: document.getElementById('panel-series-lifecycle')?.value || '',
      sort: document.getElementById('panel-series-sort')?.value || 'newest'
    };
    Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
    const res = await api(`/series?${params.toString()}`);
    const items = responseItems(res);
    store.batch(() => {
      store.set('allSeriesList', items);
      store.set('seriesList', items);
      store.set('seriesMeta', responseMeta(res));
    });
    renderSeriesTable();
  } catch (e) {
    showToast('İçerikler yüklenemedi: ' + e.message, 'danger');
  }
}

async function loadUsersData(page = 1) {
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '20' });
    const values = {
      q: document.getElementById('panel-users-search')?.value || '',
      status: document.getElementById('panel-users-status')?.value || '',
      role: document.getElementById('panel-users-role')?.value || '',
      sort: document.getElementById('panel-users-sort')?.value || 'newest'
    };
    Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
    const res = await api(`/users?${params.toString()}`);
    const items = responseItems(res).map(user => ({
      ...user,
      role_names: user.role_names || 'user',
      account_status: Number(user.is_banned) === 1 ? 'Yasaklı' : 'Aktif',
      account_badge: Number(user.is_banned) === 1
        ? 'bg-danger-subtle text-danger border border-danger-subtle'
        : 'bg-success-subtle text-success border border-success-subtle'
    }));
    store.batch(() => {
      store.set('allUsersList', items);
      store.set('usersList', items);
      store.set('usersMeta', responseMeta(res));
    });
    renderUsersTable();
  } catch (e) {
    showToast('Kullanıcılar yüklenemedi: ' + e.message, 'danger');
  }
}

async function loadBlogsData(page = 1) {
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '20' });
    const values = { q: document.getElementById('panel-blogs-search')?.value || '', status: document.getElementById('panel-blogs-status')?.value || '', sort: document.getElementById('panel-blogs-sort')?.value || 'newest' };
    Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
    const res = await api(`/blogs?${params.toString()}`);
    const labels = { draft: 'Taslak', pending: 'Bekliyor', published: 'Yayınlandı', rejected: 'Reddedildi', hidden: 'Gizli' };
    store.batch(() => {
    store.set('blogsList', responseItems(res).map(blog => {
      const approved = Number(blog.approved) === 1;
      return {
        ...blog,
        status_label: labels[blog.status] || (approved ? 'Onaylı' : 'Bekliyor / Gizli'),
        status_badge: approved
          ? 'bg-success-subtle text-success border border-success-subtle'
          : 'bg-warning-subtle text-warning border border-warning-subtle',
        can_approve: approved ? false : true,
        can_hide: approved ? true : false
      };
    }));
    store.set('blogsMeta', responseMeta(res));
    });
    renderBlogsTable();
  } catch (e) {
    showToast('Bloglar yüklenemedi: ' + e.message, 'danger');
  }
}

async function loadCommentsData(page = 1) {
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '20' });
    const values = { q: document.getElementById('panel-comments-search')?.value || '', target_type: document.getElementById('panel-comments-target')?.value || '', status: document.getElementById('panel-comments-status')?.value || '', sort: document.getElementById('panel-comments-sort')?.value || 'newest' };
    Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
    const res = await api(`/comments?${params.toString()}`);
    store.batch(() => {
    store.set('commentsList', responseItems(res).map(comment => ({
      ...comment,
      context_label: comment.blog_title
        ? `Blog: ${comment.blog_title}`
        : comment.content_title
          ? `${comment.target_type === 'chapter' ? 'Bölüm' : 'İçerik'}: ${comment.content_title}${comment.chapter_number ? ` #${comment.chapter_number}` : ''}`
          : `${comment.target_type || 'Hedef'}: ${comment.target_id || '-'}`
    })));
    store.set('commentsMeta', responseMeta(res));
    });
    renderCommentsTable();
  } catch (e) {
    showToast('Yorumlar yüklenemedi: ' + e.message, 'danger');
  }
}

async function loadReportsData(page = 1) {
  try {
    const params = new URLSearchParams({ page: String(Math.max(1, Number(page) || 1)), per_page: '20' });
    const status = document.getElementById('panel-report-status')?.value || '';
    const targetType = document.getElementById('panel-report-target')?.value || '';
    if (status) params.set('status', status);
    if (targetType) params.set('target_type', targetType);
    const response = await api(`/reports?${params.toString()}`);
    store.batch(() => {
      store.set('reportsList', responseItems(response));
      store.set('reportsMeta', {
        page: Number(response?.meta?.page || 1),
        total_pages: Number(response?.meta?.total_pages || 1),
        total: Number(response?.meta?.total || 0),
        counts: response?.meta?.counts || {}
      });
    });
    renderReportsTable();
  } catch (error) { showToast('Raporlar yüklenemedi: ' + error.message, 'danger'); }
}

async function loadPackagesData() {
  try {
    const res = await api('/shop/packages');
    store.set('packagesList', responseItems(res).map(item => ({
      ...item,
      status_label: Number(item.is_active) === 1 ? 'Aktif' : 'Pasif',
      status_badge: Number(item.is_active) === 1
        ? 'bg-success-subtle text-success'
        : 'bg-secondary-subtle text-secondary'
    })));
    renderPackagesTable();
  } catch (e) {
    showToast('Paketler yüklenemedi: ' + e.message, 'danger');
  }
}

async function loadFinanceData(page = 1) {
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '25' });
    const values = { q: document.getElementById('panel-finance-search')?.value || '', type: document.getElementById('panel-finance-type')?.value || '', sort: document.getElementById('panel-finance-sort')?.value || 'newest' };
    Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
    const response = await api(`/finance/transactions?${params.toString()}`);
    const data = response?.data || {};
    store.batch(() => {
      store.set('financeList', Array.isArray(data.items) ? data.items : []);
      store.set('financeSummary', data.summary || {});
      store.set('financeMeta', data.meta || { page: 1, total_pages: 1, total: 0 });
    });
    renderFinanceTable();
  } catch (error) { showToast('Finans hareketleri alınamadı: ' + error.message, 'danger'); }
}

async function loadLogsData(page = 1) {
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '50' });
    const values = { q: document.getElementById('panel-logs-search')?.value || '', method: document.getElementById('panel-logs-method')?.value || '', status: document.getElementById('panel-logs-status')?.value || '', sort: document.getElementById('panel-logs-sort')?.value || 'newest', date_from: document.getElementById('panel-logs-from')?.value || '', date_to: document.getElementById('panel-logs-to')?.value || '' };
    Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
    const res = await api(`/audit-logs?${params.toString()}`);
    store.batch(() => { store.set('logsList', responseItems(res)); store.set('logsMeta', responseMeta(res)); });
    renderLogsTable();
  } catch (e) {
    showToast('Loglar yüklenemedi: ' + e.message, 'danger');
  }
}

async function loadUploadsData(page = 1) {
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '30' });
    const query = document.getElementById('panel-uploads-search')?.value || '';
    const mime = document.getElementById('panel-uploads-mime')?.value || '';
    const orphans = document.getElementById('panel-uploads-orphans')?.checked || false;
    if (query) params.set('q', query);
    if (mime) params.set('mime', mime);
    if (orphans) params.set('orphans', '1');
    const response = await api(`/uploads?${params.toString()}`);
    store.batch(() => {
      store.set('uploadsList', responseItems(response).map(item => ({
        ...item,
        original_name: item.original_name || item.file_path || 'Dosya',
        username: item.username || item.user_id || '-',
        size_label: `${Math.max(0, Number(item.file_size || 0) / 1024).toFixed(1)} KB`
      })));
      store.set('uploadsMeta', responseMeta(response));
      store.set('uploadsStats', response?.meta?.stats || {});
    });
    renderUploadsTable();
  } catch (error) { showToast('Yüklemeler alınamadı: ' + error.message, 'danger'); }
}

async function loadQueueJobsData(page = 1) {
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '25' });
    const query = document.getElementById('panel-queue-search')?.value || '';
    const status = document.getElementById('panel-queue-status')?.value || '';
    if (query) params.set('q', query);
    if (status) params.set('status', status);
    const [response, health] = await Promise.all([api(`/queue/jobs?${params.toString()}`), api('/system/health')]);
    store.batch(() => {
      store.set('queueJobsList', responseItems(response));
      store.set('queueMeta', responseMeta(response));
      store.set('systemHealth', health?.data || {});
    });
    renderQueueTable();
  } catch (error) { showToast('Kuyruk alınamadı: ' + error.message, 'danger'); }
}

async function loadConfigData() {
  try {
    const response = await api('/config/site');
    const config = response?.data || {};
    config.maintenance_whitelist_text = Array.isArray(config.maintenance_whitelist_ips)
      ? config.maintenance_whitelist_ips.join('\n')
      : '';
    store.set('config', config);
  } catch (error) { showToast('Ayarlar alınamadı: ' + error.message, 'danger'); }
}

let logAutoRefreshTimer = null;

// 5. Global Action Handlers
const handlers = {
  refreshDashboard() { loadDashboardData(); showToast('İstatistikler güncellendi'); },
  loadSeries() { loadSeriesData(Number(store.get('seriesMeta')?.page || 1)); showToast('İçerik listesi yenilendi'); },
  loadUsers() { loadUsersData(Number(store.get('usersMeta')?.page || 1)); showToast('Kullanıcı listesi yenilendi'); },
  loadBlogs() { loadBlogsData(Number(store.get('blogsMeta')?.page || 1)); showToast('Blog listesi yenilendi'); },
  loadComments() { loadCommentsData(Number(store.get('commentsMeta')?.page || 1)); showToast('Yorum listesi yenilendi'); },
  loadReports() { loadReportsData(Number(store.get('reportsMeta')?.page || 1)); },
  loadLogs() { loadLogsData(Number(store.get('logsMeta')?.page || 1)); showToast('Loglar yenilendi'); },
  filterLogs() { scheduleReload('logs', () => loadLogsData(1)); },
  previousLogsPage() { const page = Number(store.get('logsMeta')?.page || 1); if (page > 1) loadLogsData(page - 1); },
  nextLogsPage() { const meta = store.get('logsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadLogsData(Number(meta.page) + 1); },
  viewAuditLog(e, el) {
    panelNavigate(`/panel/action/audit-log/${encodeURIComponent(el.dataset.id)}`);
  },
  exportLogsCsv() {
    const columns = ['id', 'method', 'path', 'status_code', 'user_id', 'username', 'duration_ms', 'created_at', 'user_agent'];
    const csvCell = value => { let textValue = String(value ?? ''); if (/^[=+@-]/.test(textValue)) textValue = "'" + textValue; return `"${textValue.replaceAll('"', '""')}"`; };
    const csv = [columns.join(','), ...(store.get('logsList') || []).map(row => columns.map(column => csvCell(row[column])).join(','))].join('\n');
    const url = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a'); link.href = url; link.download = `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`; link.click(); URL.revokeObjectURL(url);
  },
  toggleLogAutoRefresh() {
    const button = document.getElementById('panel-log-auto');
    if (logAutoRefreshTimer) { clearInterval(logAutoRefreshTimer); logAutoRefreshTimer = null; if (button) button.innerHTML = '<i class="bi bi-broadcast me-1"></i>Otomatik: Kapalı'; return; }
    logAutoRefreshTimer = setInterval(() => loadLogsData(1), 15000);
    if (button) button.innerHTML = '<i class="bi bi-broadcast me-1"></i>Otomatik: 15 sn';
  },
  loadUploads() { loadUploadsData(); showToast('Yüklemeler yenilendi'); },
  loadQueueJobs() { loadQueueJobsData(); showToast('Kuyruk yenilendi'); },
  filterQueue() { scheduleReload('queue', () => loadQueueJobsData(1)); },
  previousQueuePage() { const page = Number(store.get('queueMeta')?.page || 1); if (page > 1) loadQueueJobsData(page - 1); },
  nextQueuePage() { const meta = store.get('queueMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadQueueJobsData(Number(meta.page) + 1); },
  async retryQueueJob(e, el) {
    try { await api(`/queue/jobs/${el.dataset.id}/retry`, { method: 'POST' }); showToast('İş yeniden kuyruğa alındı'); await loadQueueJobsData(Number(store.get('queueMeta')?.page || 1)); }
    catch (error) { showToast(error.message, 'danger'); }
  },
  async cancelQueueJob(e, el) {
    if (!confirm(`Kuyruk işi #${el.dataset.id} iptal edilsin mi?`)) return;
    try { await api(`/queue/jobs/${el.dataset.id}/cancel`, { method: 'POST' }); showToast('İş iptal edildi'); await loadQueueJobsData(Number(store.get('queueMeta')?.page || 1)); }
    catch (error) { showToast(error.message, 'danger'); }
  },

  async openCreateSeriesModal() {
    panelNavigate('/panel/series/new');
  },
  async openEditSeriesModal(e, el) {
    panelNavigate(`/panel/series/${encodeURIComponent(el.dataset.id)}/edit`);
  },
  async previewSeries(e, el) {
    panelNavigate(`/panel/action/preview/${encodeURIComponent(el.dataset.id)}`);
  },
  async viewSeriesRevisions(e, el) {
    panelNavigate(`/panel/action/revisions/${encodeURIComponent(el.dataset.id)}`);
  },
  async changeSeriesLifecycle(e, el) {
    const action = el.dataset.action;
    if (!confirm(`İçerik için ${action} işlemi uygulansın mı?`)) return;
    try {
      await api(`/content/${el.dataset.id}/lifecycle`, { method: 'POST', body: { action } });
      showToast('Yayın durumu güncellendi');
      await loadSeriesData(Number(store.get('seriesMeta')?.page || 1));
    } catch (error) { showToast(error.message, 'danger'); }
  },
  async openChaptersDrawer(e, el) {
    panelNavigate(`/panel/action/chapters/${encodeURIComponent(el.dataset.id)}`);
  },
  async openTaxonomyManager() {
    panelNavigate('/panel/taxonomies');
  },
  async openOwnershipMatrix() {
    panelNavigate('/panel/action/ownership');
  },
  filterSeries() { scheduleReload('series', () => loadSeriesData(1)); },
  filterUsers() { scheduleReload('users', () => loadUsersData(1)); },
  filterBlogs() { scheduleReload('blogs', () => loadBlogsData(1)); },
  filterComments() { scheduleReload('comments', () => loadCommentsData(1)); },
  previousSeriesPage() { const page = Number(store.get('seriesMeta')?.page || 1); if (page > 1) loadSeriesData(page - 1); },
  nextSeriesPage() { const meta = store.get('seriesMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadSeriesData(Number(meta.page) + 1); },
  previousUsersPage() { const page = Number(store.get('usersMeta')?.page || 1); if (page > 1) loadUsersData(page - 1); },
  nextUsersPage() { const meta = store.get('usersMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadUsersData(Number(meta.page) + 1); },
  openUserDetail(e, el) { panelNavigate(`/panel/action/user-detail/${encodeURIComponent(el.dataset.id)}`); },
  filterUserComments() { scheduleReload('user-comments', () => loadUserCommentsData(store.get('userDetailId'), 1)); },
  previousUserCommentsPage() { const page = Number(store.get('userCommentsMeta')?.page || 1); if (page > 1) loadUserCommentsData(store.get('userDetailId'), page - 1); },
  nextUserCommentsPage() { const meta = store.get('userCommentsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadUserCommentsData(store.get('userDetailId'), Number(meta.page) + 1); },
  filterUserBlogs() { scheduleReload('user-blogs', () => loadUserBlogsData(store.get('userDetailId'), 1)); },
  previousUserBlogsPage() { const page = Number(store.get('userBlogsMeta')?.page || 1); if (page > 1) loadUserBlogsData(store.get('userDetailId'), page - 1); },
  nextUserBlogsPage() { const meta = store.get('userBlogsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadUserBlogsData(store.get('userDetailId'), Number(meta.page) + 1); },
  filterUserViolations() { scheduleReload('user-violations', () => loadUserViolationsData(store.get('userDetailId'), 1)); },
  previousUserViolationsPage() { const page = Number(store.get('userViolationsMeta')?.page || 1); if (page > 1) loadUserViolationsData(store.get('userDetailId'), page - 1); },
  nextUserViolationsPage() { const meta = store.get('userViolationsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadUserViolationsData(store.get('userDetailId'), Number(meta.page) + 1); },
  previousBlogsPage() { const page = Number(store.get('blogsMeta')?.page || 1); if (page > 1) loadBlogsData(page - 1); },
  nextBlogsPage() { const meta = store.get('blogsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadBlogsData(Number(meta.page) + 1); },
  previousCommentsPage() { const page = Number(store.get('commentsMeta')?.page || 1); if (page > 1) loadCommentsData(page - 1); },
  nextCommentsPage() { const meta = store.get('commentsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadCommentsData(Number(meta.page) + 1); },
  filterReports() { loadReportsData(1); },
  previousReportsPage() {
    const page = Number(store.get('reportsMeta')?.page || 1);
    if (page > 1) loadReportsData(page - 1);
  },
  nextReportsPage() {
    const meta = store.get('reportsMeta') || {};
    const page = Number(meta.page || 1);
    if (page < Number(meta.total_pages || 1)) loadReportsData(page + 1);
  },
  async openReport(e, el) {
    panelNavigate(`/panel/reports/${encodeURIComponent(el.dataset.id)}`);
  },
  async openEditUserModal(e, el) {
    panelNavigate(`/panel/action/user-edit/${encodeURIComponent(el.dataset.id)}`);
  },
  async openRbacMatrix() {
    panelNavigate('/panel/action/rbac');
  },
  async openWalletModal(e, el) {
    panelNavigate(`/panel/action/wallet/${encodeURIComponent(el.dataset.id)}`);
  },
  openCreatePackageModal() { panelNavigate('/panel/action/package-new'); },
  openEditPackageModal(e, el) {
    const item = (store.get('packagesList') || []).find(packageItem => String(packageItem.id) === String(el.dataset.id));
    if (!item) return showToast('Paket bulunamadı', 'danger');
    panelNavigate(`/panel/action/package-edit/${encodeURIComponent(item.id)}`);
  },
  async openAdFreeModal() {
    panelNavigate('/panel/action/ad-free');
  },
  openPricingModal() { panelNavigate('/panel/action/pricing'); },
  filterFinance() { scheduleReload('finance', () => loadFinanceData(1)); },
  previousFinancePage() { const page = Number(store.get('financeMeta')?.page || 1); if (page > 1) loadFinanceData(page - 1); },
  nextFinancePage() { const meta = store.get('financeMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadFinanceData(Number(meta.page) + 1); },
  async refundFinanceTransaction(e, el) {
    const reason = prompt('İade nedeni:');
    if (!reason?.trim()) return;
    if (!confirm(`İşlem #${el.dataset.id} için coin iadesi yapılsın ve ilgili erişim geri alınsın mı?`)) return;
    try {
      await api(`/finance/transactions/${el.dataset.id}/refund`, { method: 'POST', body: { reason: reason.trim() } });
      showToast('İade işlemi tamamlandı');
      await loadFinanceData(Number(store.get('financeMeta')?.page || 1));
    } catch (error) { showToast(error.message, 'danger'); }
  },

  async deleteBlog(e, el) {
    const id = el.dataset.id;
    if (!confirm(`Bu blog yazısını silmek istediğinize emin misiniz?`)) return;
    try {
      await api(`/blogs/${id}`, { method: 'DELETE' });
      showToast('Blog yazısı silindi');
      loadBlogsData();
    } catch (err) { showToast(err.message, 'danger'); }
  },

  async approveBlog(e, el) {
    try {
      await api(`/blogs/${el.dataset.id}/approve`, { method: 'POST' });
      showToast('Blog yazısı onaylandı');
      loadBlogsData();
    } catch (err) { showToast(err.message, 'danger'); }
  },

  async hideBlog(e, el) {
    if (!confirm('Bu blog yazısını gizlemek istediğinize emin misiniz?')) return;
    try {
      await api(`/blogs/${el.dataset.id}/hide`, { method: 'POST' });
      showToast('Blog yazısı gizlendi');
      loadBlogsData();
    } catch (err) { showToast(err.message, 'danger'); }
  },

  async deleteComment(e, el) {
    const id = el.dataset.id;
    if (!confirm(`Bu yorumu silmek istediğinize emin misiniz?`)) return;
    try {
      await api(`/comments/${id}`, { method: 'DELETE' });
      showToast('Yorum silindi');
      loadCommentsData();
    } catch (err) { showToast(err.message, 'danger'); }
  },

  async moderateComment(e, el) {
    try {
      await api(`/comments/${el.dataset.id}/moderation`, { method: 'PUT', body: { status: el.dataset.status } });
      showToast(el.dataset.status === 'approved' ? 'Yorum onaylandı' : 'Yorum gizlendi');
      loadCommentsData(Number(store.get('commentsMeta')?.page || 1));
    } catch (err) { showToast(err.message, 'danger'); }
  },

  async deleteUpload(e, el) {
    if (!confirm('Bu yükleme kaydı ve bağlı dosya silinsin mi?')) return;
    try {
      await api(`/uploads/${el.dataset.id}`, { method: 'DELETE' });
      showToast('Yükleme silindi');
      loadUploadsData(Number(store.get('uploadsMeta')?.page || 1));
    } catch (error) { showToast(error.message, 'danger'); }
  },
  filterUploads() { scheduleReload('uploads', () => loadUploadsData(1)); },
  previousUploadsPage() { const page = Number(store.get('uploadsMeta')?.page || 1); if (page > 1) loadUploadsData(page - 1); },
  nextUploadsPage() { const meta = store.get('uploadsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadUploadsData(Number(meta.page) + 1); },
  toggleAllUploads(e, el) { document.querySelectorAll('[data-upload-select]').forEach(input => { input.checked = el.checked; }); },
  async bulkDeleteUploads() {
    const ids = Array.from(document.querySelectorAll('[data-upload-select]:checked')).map(input => Number(input.value));
    if (ids.length === 0) return showToast('Önce en az bir dosya seçin', 'danger');
    if (!confirm(`${ids.length} yükleme kaydı ve fiziksel dosyaları silinsin mi?`)) return;
    try { const response = await api('/uploads/bulk-delete', { method: 'POST', body: { ids } }); showToast(`${Number(response?.data?.deleted || 0)} dosya silindi`); await loadUploadsData(1); }
    catch (error) { showToast(error.message, 'danger'); }
  },
  async optimizeUpload(e, el) {
    try { const response = await api(`/uploads/${el.dataset.id}/optimize`, { method: 'POST' }); showToast(`${Number(response?.data?.saved_bytes || 0)} bayt kazanıldı`); await loadUploadsData(Number(store.get('uploadsMeta')?.page || 1)); }
    catch (error) { showToast(error.message, 'danger'); }
  },

  async runQueueWorker() {
    try {
      const limit = Number(document.getElementById('panel-queue-limit')?.value || 20);
      const res = await api('/queue/run-once', { method: 'POST', body: { limit } });
      showToast(`Kuyruk çalıştırıldı (limit: ${res?.data?.limit || limit})`);
      loadQueueJobsData();
    } catch (err) { showToast(err.message, 'danger'); }
  },

  async runRetentionCleanup() {
    try {
      const days = Number(document.getElementById('panel-cleanup-days')?.value || 30);
      await api('/retention/cleanup', { method: 'POST', body: { days } });
      showToast('Sistem temizliği başarıyla tamamlandı');
    } catch (err) { showToast(err.message, 'danger'); }
  },

  async runCacheWarmup() {
    try {
      await api('/maintenance/warmup', { method: 'POST' });
      showToast('Önbellek başarıyla ısıtıldı');
    } catch (err) { showToast(err.message, 'danger'); }
  },

  async generateSitemap() {
    try {
      await api('/maintenance/sitemap', { method: 'POST' });
      showToast('Sitemap başarıyla üretildi');
    } catch (err) { showToast(err.message, 'danger'); }
  },

  async runMaintenance(e, el) {
    const output = document.getElementById('panel-maintenance-output');
    if (output) output.textContent = 'İşlem çalışıyor...';
    try {
      const response = await api(`/maintenance/${el.dataset.task}`, { method: 'POST' });
      const result = response?.data || {};
      if (output) output.textContent = Array.isArray(result.output) ? result.output.join('\n') : JSON.stringify(result, null, 2);
      if (result.success === false) {
        showToast('Bakım işlemi başarısız oldu', 'danger');
      } else {
        showToast('Bakım işlemi tamamlandı');
      }
    } catch (error) {
      if (output) output.textContent = error.message;
      showToast(error.message, 'danger');
    }
  },

  async openLogViewer(e, el) {
    const slug = String(el.dataset.log || '').replace(/^logs\//, '').replace(/[^a-z0-9_-]/gi, '-');
    panelNavigate(`/panel/action/log-viewer/${encodeURIComponent(slug || 'login-events')}`);
  },

  openModerationAction() { panelNavigate('/panel/action/moderation'); },

  async openWebhooks() {
    panelNavigate('/panel/webhook');
  },

  async openEnvEditor() {
    panelNavigate('/panel/config-env');
  },

  async saveConfig(e) {
    if (e) e.preventDefault();
    try {
      const payload = { ...store.get('config') };
      payload.maintenance_whitelist_ips = String(payload.maintenance_whitelist_text || '').split(/\r?\n|,/).map(value => value.trim()).filter(Boolean);
      delete payload.maintenance_whitelist_text;
      // These values are managed in the root-only .env editor.
      delete payload.site_address;
      delete payload.enforce_https;
      const res = await api('/config/site', { method: 'POST', body: payload });
      if (res?.data) {
        const updated = res.data;
        updated.maintenance_whitelist_text = Array.isArray(updated.maintenance_whitelist_ips)
          ? updated.maintenance_whitelist_ips.join('\n')
          : '';
        store.set('config', updated);
      }
      showToast('Site ayarları başarıyla kaydedildi!');
    } catch (err) { showToast(err.message, 'danger'); }
  }
};

// 6. Router & View Mount
const target = document.getElementById('panel-app');
let currentCleanup = null;
const routePermissions = {
  monetization: ['admin.shop.manage'],
  finance: ['admin.finance.view'],
  reports: ['admin.reports.view'],
  uploads: ['admin.uploads.view'],
  ops: ['admin.health.view'],
  logs: ['admin.logs.view'],
  config: ['admin.settings.modify'],
  webhook: ['admin.settings.modify'],
  'config-env': ['admin.settings.modify'],
  'report-detail': ['admin.reports.view'],
  'series-new': ['admin.content.create'],
  'series-edit': ['admin.content.update']
};

const validPanelRoutes = ['dashboard', 'series', 'taxonomies', 'users', 'blogs', 'comments', 'reports', 'monetization', 'finance', 'ops', 'logs', 'uploads', 'config', 'webhook', 'config-env', 'help'];

function panelRoutePath(route) {
  return route === 'dashboard' ? '/panel' : `/panel/${route}`;
}

function resolvePanelRoute() {
  const path = decodeURIComponent(window.location.pathname.replace(/^\/panel\/?/, ''));
  const parts = path.split('/').filter(Boolean);
  const hash = (window.location.hash || '').replace(/^#/, '');
  if (parts.length === 0 && hash) parts.push(hash);
  const section = parts[0] || 'dashboard';
  if (section === 'taxonomies') return { route: 'action', section: 'taxonomies', action: 'taxonomy', id: null, path: '/panel/taxonomies' };
  if (section === 'action' && parts[1]) {
    const action = parts[1];
    const sectionMap = { taxonomy: 'series', ownership: 'users', rbac: 'users', chapters: 'series', preview: 'series', revisions: 'series', team: 'series', 'user-edit': 'users', 'user-detail': 'users', 'user-penalty': 'users', wallet: 'users', 'package-new': 'monetization', 'package-edit': 'monetization', 'ad-free': 'monetization', pricing: 'monetization', moderation: 'logs', 'log-viewer': 'logs', 'audit-log': 'logs' };
    if (Object.prototype.hasOwnProperty.call(actionPermissions, action)) return { route: 'action', section: sectionMap[action] || 'dashboard', action, id: parts[2] || null, path: `/panel/action/${parts.slice(1).join('/')}` };
  }
  if (section === 'reports' && /^\d+$/.test(parts[1] || '')) return { route: 'report-detail', section: 'reports', id: parts[1], path: `/panel/reports/${parts[1]}` };
  if (section === 'series' && parts[1] === 'new') return { route: 'series-new', section: 'series', path: '/panel/series/new' };
  if (section === 'series' && parts[1] && parts[2] === 'edit') return { route: 'series-edit', section: 'series', id: parts[1], path: `/panel/series/${parts[1]}/edit` };
  if (validPanelRoutes.includes(section)) return { route: section, section, path: panelRoutePath(section) };
  return { route: 'dashboard', section: 'dashboard', path: '/panel' };
}

function navigate() {
  const resolved = resolvePanelRoute();
  const requestedRoute = resolved.route;
  const required = requestedRoute === 'action' ? (actionPermissions[resolved.action] || []) : (routePermissions[requestedRoute] || []);
  const route = required.length === 0 || hasPermission(...required) ? requestedRoute : 'dashboard';
  const routePath = route === requestedRoute ? resolved.path : '/panel';
  if (route !== 'action') pageDialogMode = false;

  if (window.location.pathname !== routePath || window.location.hash) {
    history.replaceState({ route }, '', routePath);
  }

  // Update active nav link
  let activeNavLink = null;
  const activeSection = route === 'action' ? resolved.section : route;
  document.querySelectorAll('#panel-sidebar-nav a[data-route]').forEach(a => {
    if (a.getAttribute('data-route') === activeSection) {
      a.classList.add('active-nav-link');
      activeNavLink = a;
    } else {
      a.classList.remove('active-nav-link');
    }
  });
  const activeNavGroup = activeNavLink?.closest('.nav-treeview')?.closest('.nav-item');
  if (activeNavGroup) {
    activeNavGroup.classList.add('menu-open');
    activeNavGroup.querySelector(':scope > .nav-link[data-lte-toggle="treeview"]')?.setAttribute('aria-expanded', 'true');
  }

  if (currentCleanup) {
    currentCleanup();
    currentCleanup = null;
  }
  if (route !== 'logs' && logAutoRefreshTimer) {
    clearInterval(logAutoRefreshTimer);
    logAutoRefreshTimer = null;
  }

  const templateRoute = route === 'series-new' || route === 'series-edit' ? 'series-editor' : (route === 'action' ? 'action' : route);
  currentCleanup = mount(`panel-${templateRoute}`, {
    target,
    store,
    handlers
  });
  applyPermissionVisibility(target);
  renderRouteTables(resolved.section);

  // Fetch view-specific data
  if (route === 'dashboard') loadDashboardData();
  else if (route === 'series') loadSeriesData();
  else if (route === 'users') loadUsersData();
  else if (route === 'blogs') loadBlogsData();
  else if (route === 'comments') loadCommentsData();
  else if (route === 'reports') loadReportsData();
  else if (route === 'monetization') loadPackagesData();
  else if (route === 'finance') loadFinanceData();
  else if (route === 'ops') loadQueueJobsData();
  else if (route === 'logs') loadLogsData();
  else if (route === 'uploads') loadUploadsData();
  else if (route === 'config') loadConfigData();
  else if (route === 'report-detail') loadReportDetailPage(resolved.id);
  else if (route === 'series-new') loadSeriesEditorPage('new');
  else if (route === 'series-edit') loadSeriesEditorPage('edit', resolved.id);
  else if (route === 'webhook') loadWebhookPage();
  else if (route === 'config-env') loadEnvPage();
  else if (route === 'action') loadPanelActionPage(resolved.action, resolved.id);
}

applyPermissionVisibility(document);
const panelSidebarNav = document.getElementById('panel-sidebar-nav');
document.querySelectorAll('#panel-sidebar-nav a[data-route]').forEach(link => {
  const route = link.dataset.route;
  link.href = panelRoutePath(route);
});
panelSidebarNav?.addEventListener('click', event => {
  const target = event.target instanceof Element ? event.target : null;
  const link = target?.closest('#panel-sidebar-nav a[data-route]');
  if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  event.preventDefault();
  event.stopPropagation();
  panelNavigate(panelRoutePath(link.dataset.route || 'dashboard'));
}, true);
document.addEventListener('click', event => {
  const link = event.target instanceof Element ? event.target.closest('a[data-panel-link]') : null;
  if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  event.preventDefault();
  panelNavigate(link.getAttribute('href'));
});
window.addEventListener('popstate', () => navigate());
window.addEventListener('hashchange', () => navigate());
navigate();
