// deno-lint-ignore-file no-import-prefix -- Production loads Lime CSR from its CDN.
// Lime-CSR admin panel application.
import {
  attr,
  conditionals,
  createEngine,
  createStore,
  defineModule,
  events,
  loops,
  model,
  partials,
  ref,
  show,
  text,
} from "https://cdn.jsdelivr.net/gh/mhmtsnmzkanly/lime-csr-js@v0.6.2/dist/index.min.js";
import {
  createAdminApi,
  createAdminReauth,
  responseItems,
  responseMeta,
} from "./modules/api.js?130";
import { createAccess } from "./modules/access.js?125";
import {
  bindPanelNavigation,
  createPanelRouter,
  navigatePanelPath,
} from "./modules/router.js?129";
import { createPanelNavigationController } from "./modules/navigation-controller.js";
import { createPageView } from "./modules/page-view.js";
import { createPageSession } from "./modules/page-session.js?125";
import {
  capturePanelGroupState,
  createPanelNavigation,
  restorePanelGroupState,
} from "./modules/navigation.js?130";
import { createI18n } from "./modules/i18n.js?131";
import { resolvePanelContext } from "./modules/bootstrap.js";
import { initializeShell, panelTitle } from "./modules/shell.js";
import { bindLanguageSelector, savedPanelLocale } from "./modules/language-selector.js";
import { createThemeController } from "./modules/theme.js";
import { createModalService } from "./modules/modal.js";
import { createFeedback } from "./modules/feedback.js?125";
import { createRequestGate } from "./modules/request-gate.js?125";
import { createTranslationModule } from "./modules/directives/translation.js?126";
import { bindFormAction } from "./modules/form-action.js?126";
import { createDirtyGuard } from "./modules/dirty-guard.js";
import { createCommandPalette } from "./modules/command-palette.js";
import { createDashboardChartManager } from "./modules/dashboard-charts.js?125";
import { createDashboardDataController } from "./modules/dashboard-data.js?131";
import { createDashboardView } from "./modules/dashboard-view.js?125";
import { createConfigPagesController } from "./modules/config-pages.js?129";
import { createCollectionDataController } from "./modules/collection-data.js?127";
import { createChaptersPageController } from "./modules/chapters-page.js?128";
import { createContentPreviewsController } from "./modules/content-previews.js?127";
import { createChapterEditorController } from "./modules/chapter-editor.js?129";
import { createSeriesEditorController } from "./modules/series-editor.js?130";
import { createTaxonomyPageController } from "./modules/taxonomy-page.js?127";
import { createAccessControlPagesController } from "./modules/access-control-pages.js?127";
import { createModerationPagesController } from "./modules/moderation-pages.js?129";
import { createPanelTableRenderers } from "./modules/table-renderers.js?131";
import { createPanelUi } from "./modules/ui.js?126";
import { createPermissionUi } from "./modules/permission-ui.js?125";
import { createOpsOperationUi } from "./modules/ops-ui.js?125";
import { createOpsDataController } from "./modules/ops-data.js?127";
import { createPanelHandlers } from "./modules/handlers.js?126";
import { createOpsHandlers } from "./modules/ops-handlers.js?126";
import { createPanelStore } from "./modules/state.js?125";
import { createUsersListController } from "./modules/users-list.js?126";
import { createUsersDetailController } from "./modules/users-detail.js?135";
import { createUserPenaltyController } from "./modules/user-penalty.js";
import { createUserWalletController } from "./modules/user-wallet.js";
import { createMiscPagesController } from "./modules/misc-pages.js?128";
import {
  commentThreadFields,
  formatDateTime,
  formatNumber,
  moderationActionLabel,
  moderationScopeLabel,
  reportStatus,
  targetTypeLabel,
  userModerationStatus,
  userViolationLevel,
} from "./modules/formatters.js?127";
import {
  createPanelRoutes,
} from "./modules/routes.js?128";
function initializeAdmin() {
initializeShell();
const nextYear = String(new Date().getFullYear() + 1);

const panelLanguages = globalThis.__NMR_CONTEXT?.supported_langs?.length
  ? globalThis.__NMR_CONTEXT.supported_langs
  : ["tr", "en"];
const i18n = createI18n({
  locale: savedPanelLocale(panelLanguages, globalThis.__NMR_CONTEXT?.lang_code || "en"),
  fallbackLocale: globalThis.__NMR_CONTEXT?.default_lang || "en",
  supported: panelLanguages,
  initialDictionaries: globalThis.__NMR_CONTEXT?.translations || {},
});
document.documentElement.lang = i18n.locale();
function panelTranslate(key, fallback, params = {}) {
  const value = i18n.t(key, params);
  return value === key ? fallback : value;
}
const modalService = createModalService({
  documentRef: document,
  windowRef: globalThis.window,
  translate: panelTranslate,
});
const feedback = createFeedback({
  documentRef: document,
  windowRef: globalThis.window,
  modalService,
});
const { confirmAction, promptValue } = feedback;
const dirtyGuard = createDirtyGuard({
  windowRef: globalThis.window,
  confirmAction,
  translate: panelTranslate,
});
const panelBindFormAction = (form, submitAction, options = {}) =>
  bindFormAction(form, submitAction, { dirtyGuard, ...options });
const formatUserModerationStatus = (status) =>
  userModerationStatus(status, panelTranslate);
const formatUserViolationLevel = (level) =>
  userViolationLevel(level, panelTranslate);
const formatReportStatus = (status) => reportStatus(status, panelTranslate);
const panelFormatNumber = (value) => formatNumber(value, i18n.locale());
const panelFormatDateTime = (value) => formatDateTime(value, i18n.locale());
// Load the selected dictionary once for pages that opt into data-i18n. The
// current legacy templates remain usable while the migration is incremental.
const dictionaryReady = Promise.all([
  i18n.load(),
  i18n.load(i18n.fallbackLocale()),
]).catch((error) => {
  console.warn("Panel çeviri sözlüğü yüklenemedi:", error);
});
const i18nReady = Promise.race([
  dictionaryReady,
  new Promise((resolve) => globalThis.setTimeout(resolve, 3000)),
]);
const panelTranslationModule = createTranslationModule({
  defineModule,
  attr,
  i18n,
});
const panelModules = [
  partials(),
  conditionals(),
  loops(),
  model(),
  text(),
  show(),
  events(),
  ref(),
  panelTranslationModule,
];
const panelEngine = createEngine({ modules: panelModules });
// Partials and pages share panelEngine. In Lime-CSR v0.6.1+, nested mount
// boundaries ([data-lime-mount]) are isolated by the events module, so the
// outer page engine skips events from nested partial targets. Partials mount
// with full event delegation, eliminating double dispatch and event drop.
const panelNavigation = createPanelNavigation({ documentRef: document });
let sidebarRender = null;
function renderSidebar() {
  const shell = document.getElementById("panel-sidebar-nav");
  if (!shell) return;
  const groupState = capturePanelGroupState(shell);
  const currentRoute = store?.get("currentRoute") || "dashboard";
  const currentSection = store?.get("currentSection") || currentRoute;
  // `render()` returns a cleanup handle rather than an unmounting mount
  // instance. Dispose the previous link-phase listeners before a late
  // dictionary refresh so sidebar renders cannot accumulate handlers.
  sidebarRender?.cleanup?.();
  // Sidebar links are handled by the panel navigation adapter below; it has
  // no Lime data-on-* actions.
  sidebarRender = panelEngine.render(shell, { store, handlers });
  applyPermissionVisibility(shell);
  restorePanelGroupState(shell, groupState);
  panelNavigation.setActiveRoute?.(currentRoute, currentSection);
}
const requestGates = {
  series: createRequestGate(),
  users: createRequestGate(),
  likers: createRequestGate(),
  userComments: createRequestGate(),
  userBlogs: createRequestGate(),
  userViolations: createRequestGate(),
  blogs: createRequestGate(),
  comments: createRequestGate(),
  reports: createRequestGate(),
  packages: createRequestGate(),
  finance: createRequestGate(),
  logs: createRequestGate(),
  uploads: createRequestGate(),
};

const store = createPanelStore({
  createStore,
  context: globalThis.__NMR_CONTEXT,
});
const csrfToken = globalThis.__NMR_CONTEXT?.auth?.csrf_token || "";
const access = createAccess(globalThis.__NMR_CONTEXT?.auth?.permissions || []);

function hasPermission(...permissions) {
  return access.any(permissions);
}

const permissionUi = createPermissionUi({
  documentRef: document,
  can: (...permissions) => hasPermission(...permissions),
});
const applyPermissionVisibility = permissionUi.apply;

// 2. Toast Notification Helper
function showToast(message, type = "success") {
  feedback.toast(message, type);
}

// 3. Authenticated Fetch Helper
const pageSession = createPageSession();
let pageEpoch = pageSession.current().epoch;
let pageRequests = pageSession.current().controller;

function assertCurrentPage(epoch) {
  if (!pageSession.isCurrent(epoch)) {
    throw new DOMException("Sayfa değişti.", "AbortError");
  }
}

const adminApi = createAdminApi({
  getEpoch: () => pageEpoch,
  getSignal: () => pageRequests.signal,
  getCsrfToken: () => csrfToken,
  assertCurrentPage,
  translate: panelTranslate,
  reauthenticate: createAdminReauth({
    getCsrfToken: () => csrfToken,
    assertCurrentPage,
    promptImpl: promptValue,
    translate: panelTranslate,
  }),
});

// Backwards-compatible callable API plus api.get/post/put/patch/delete for
// newly extracted page modules.
const api = adminApi;

// Central mount boundary. Page features use this wrapper so the engine's
// object-only configuration stays isolated from individual page modules.
function mount(name, options = {}) {
  const { target: mountTarget, context = {}, store: mountStore, ...rest } =
    options;
  return panelEngine.mount({
    ...rest,
    target: mountTarget,
    template: name,
    store: mountStore || store,
    context,
    handlers: rest.handlers || handlers,
  });
}

function mountPartialEngine(name, options = {}) {
  return mount(name, options);
}

const pageView = createPageView({
  target: document.getElementById("panel-app"),
  pageSession,
  mount,
  mountPartialEngine,
  applyPermissionVisibility,
});
function scheduleReload(key, callback) {
  return pageView.scheduleReload(key, callback);
}
function mountPartial(name, target, context = {}) {
  return pageView.mountPartial(name, target, context);
}
function registerPageCleanup(callback) {
  return pageView.registerPageCleanup(callback);
}
function mountPage(name, context = {}) {
  document.title = panelTitle(store.get("currentRoute"), context);
  return pageView.mountPage(name, context);
}
function disposePage() {
  return pageView.disposePage();
}

const panelUi = createPanelUi({
  documentRef: document,
  mountPartial,
});
const {
  safeLocalPath,
  setIconButtonLabel,
  mountHeaderCells,
  setTableRows,
  renderPager,
} = panelUi;

const usersListController = createUsersListController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  requestGate: requestGates.users,
  hasPermission,
  setTableRows,
  renderPager,
  showToast,
  translate: panelTranslate,
  documentRef: document,
});
const { renderUsersTable, loadUsersData } = usersListController;

const usersDetailController = createUsersDetailController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  hasPermission,
  safeLocalPath,
  setTableRows,
  renderPager,
  mountPage,
  scheduleReload,
  bindFormAction: panelBindFormAction,
  registerPageCleanup,
  showToast,
  userModerationStatus: formatUserModerationStatus,
  userViolationLevel: formatUserViolationLevel,
  commentThreadFields,
  targetTypeLabel,
  moderationScopeLabel,
  moderationActionLabel,
  formatNumber: panelFormatNumber,
  requestGates: {
    comments: requestGates.userComments,
    blogs: requestGates.userBlogs,
    violations: requestGates.userViolations,
  },
  translate: panelTranslate,
  documentRef: document,
});

const userPenaltyController = createUserPenaltyController({
  store, api, getPageEpoch: () => pageEpoch, assertCurrentPage, mountPage,
  bindFormAction: panelBindFormAction, registerPageCleanup, panelNavigate, showToast,
  translate: panelTranslate,
});
const userWalletController = createUserWalletController({
  store, api, responseItems, responseMeta, getPageEpoch: () => pageEpoch,
  assertCurrentPage, hasPermission, mountPage, mountPartial, bindFormAction: panelBindFormAction,
  registerPageCleanup, showToast, renderPager,
  translate: panelTranslate, formatNumber: panelFormatNumber,
});

const dashboardChartManager = createDashboardChartManager({
  documentRef: document,
  translate: panelTranslate,
});
const destroyDashboardCharts = dashboardChartManager.destroy;
const dashboardView = createDashboardView({
  store,
  documentRef: document,
  chartManager: dashboardChartManager,
  setTableRows,
  safeLocalPath,
  translate: panelTranslate,
  formatDateTime: panelFormatDateTime,
});
const {
  renderDashboardTables,
  renderDashboardCharts,
  updateDashboardStateView,
} = dashboardView;
const dashboardDataController = createDashboardDataController({
  store,
  api,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  renderDashboardTables,
  renderDashboardCharts,
  updateDashboardStateView,
  destroyDashboardCharts,
  registerPageCleanup,
  translate: panelTranslate,
  formatNumber: panelFormatNumber,
  documentRef: document,
  hasPermission,
  panelNavigate,
});
const { loadDashboardData } = dashboardDataController;

const opsOperationUi = createOpsOperationUi({
  documentRef: document,
  store,
});
const setOpsOperation = opsOperationUi.set;

const tableRenderers = createPanelTableRenderers({
  store,
  hasPermission,
  setTableRows,
  renderPager,
  safeLocalPath,
  reportStatus: formatReportStatus,
  targetTypeLabel,
  commentThreadFields,
  translate: panelTranslate,
  formatNumber: panelFormatNumber,
  formatDateTime: panelFormatDateTime,
  documentRef: document,
});
const {
  renderSeriesTable,
  renderBlogsTable,
  renderCommentsTable,
  renderReportsTable,
  renderPackagesTable,
  renderFinanceTable,
  renderQueueTable,
  renderLogsTable,
  renderUploadsTable,
} = tableRenderers;
const collectionDataController = createCollectionDataController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  seriesRequestGate: requestGates.series,
  requestGates: {
    blogs: requestGates.blogs,
    comments: requestGates.comments,
    reports: requestGates.reports,
    packages: requestGates.packages,
    finance: requestGates.finance,
    logs: requestGates.logs,
    uploads: requestGates.uploads,
  },
  renderSeriesTable,
  renderBlogsTable,
  renderCommentsTable,
  renderReportsTable,
  renderPackagesTable,
  renderFinanceTable,
  renderLogsTable,
  renderUploadsTable,
  setTableRows,
  showToast,
  targetTypeLabel,
  translate: panelTranslate,
  documentRef: document,
});
const {
  loadSeriesData,
  loadBlogsData,
  loadCommentsData,
  loadReportsData,
  loadPackagesData,
  loadFinanceData,
  loadLogsData,
  loadUploadsData,
} = collectionDataController;
const chaptersPageController = createChaptersPageController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  mountEditorPage,
  mountPartial,
  panelNavigate,
  confirmAction,
  promptValue,
  modalService,
  showToast,
  registerPageCleanup,
  loadSeriesData,
  translate: panelTranslate,
  documentRef: document,
});
const { loadChaptersPage } = chaptersPageController;
const configPagesController = createConfigPagesController({
  store,
  api,
  responseItems,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  mountPartial,
  panelNavigate,
  confirmAction,
  bindFormAction: panelBindFormAction,
  registerPageCleanup,
  showToast,
  translate: panelTranslate,
  documentRef: document,
});
const { loadEnvPage, loadWebhookPage, loadConfigData } = configPagesController;
const opsDataController = createOpsDataController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  hasPermission,
  renderQueueTable,
  setTableRows,
  showToast,
  registerPageCleanup,
  translate: panelTranslate,
  documentRef: document,
});
const { loadQueueJobsData } = opsDataController;
const moderationPagesController = createModerationPagesController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch: () => pageEpoch,
  getPageParent: () => navigationController.parentPath(),
  assertCurrentPage,
  safeLocalPath,
  mountEditorPage,
  mountPartial,
  renderPager,
  panelNavigate,
  showToast,
  hasPermission,
  requestGate: requestGates.likers,
  bindFormAction: panelBindFormAction,
  registerPageCleanup,
  translate: panelTranslate,
  targetTypeLabel,
  documentRef: document,
});
const { loadLikersPage, loadReportDetailPage } = moderationPagesController;
const contentPreviewsController = createContentPreviewsController({
  store,
  api,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  safeLocalPath,
  mountEditorPage,
  translate: panelTranslate,
});
const {
  loadSeriesPreviewPage,
  loadChapterPreviewPage,
  loadBlogPreviewPage,
} = contentPreviewsController;
const chapterEditorController = createChapterEditorController({
  api,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  mountEditorPage,
  mountPartial,
  registerPageCleanup,
  safeLocalPath,
  confirmAction,
  hasPermission,
  showToast,
  panelNavigate,
  translate: panelTranslate,
  documentRef: document,
});
const { renderChapterPage, uploadImages } = chapterEditorController;
const seriesEditorController = createSeriesEditorController({
  store,
  api,
  responseItems,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  loadTaxonomies,
  uploadImages,
  bindTaxonomyButtons,
  selectedValues,
  mountPartial,
  registerPageCleanup,
  bindFormAction: panelBindFormAction,
  showToast,
  panelNavigate,
  nextYear,
  translate: panelTranslate,
  documentRef: document,
});
const { loadSeriesEditorPage } = seriesEditorController;
const taxonomyPageController = createTaxonomyPageController({
  api,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  loadTaxonomies,
  mountEditorPage,
  mountPartial,
  confirmAction,
  promptValue,
  modalService,
  showToast,
  registerPageCleanup,
  documentRef: document,
  translate: panelTranslate,
});
const { loadTaxonomyPage } = taxonomyPageController;
const accessControlPagesController = createAccessControlPagesController({
  api,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  hasPermission,
  mountEditorPage,
  mountHeaderCells,
  mountPartial,
  confirmAction,
  showToast,
  registerPageCleanup,
  translate: panelTranslate,
});
const { loadRbacPage, loadOwnershipPage } = accessControlPagesController;
const miscPagesController = createMiscPagesController({
  store,
  api,
  responseItems,
  getPageEpoch: () => pageEpoch,
  assertCurrentPage,
  mountEditorPage,
  mountPartial,
  mountHeaderCells,
  panelNavigate,
  confirmAction,
  reloadCurrentRoute: () => triggerNavigate(),
  showToast,
  registerPageCleanup,
  translate: panelTranslate,
});
const {
  loadSeriesRevisionsPage,
  loadTeamPage,
  renderPackagePage,
  loadPackagePage,
  loadAdFreePage,
  loadPricingPage,
  loadLogPage,
  loadAuditPage,
  loadModerationPage,
} = miscPagesController;

const routeTableRenderers = {
  dashboard: renderDashboardTables,
  series: renderSeriesTable,
  user: renderUsersTable,
  blogs: renderBlogsTable,
  comments: renderCommentsTable,
  reports: renderReportsTable,
  monetization: renderPackagesTable,
  finance: renderFinanceTable,
  ops: renderQueueTable,
  logs: renderLogsTable,
  uploads: renderUploadsTable,
};

function renderRouteTables(route) {
  routeTableRenderers[route]?.();
}

function mountEditorPage(title, body, onSubmit) {
  const fields = body.context || {};
  const page = mountPage(body.name + "-page", {
    header: { title, parent_path: navigationController.parentPath(), show_back: true },
    fields,
    // Some presentation-only pages (for example likers) bind directly to
    // their page context instead of a form partial's `fields` scope. Keep the
    // shared `fields` contract and expose the same read-only values at root.
    ...fields,
  });
  const form = page.querySelector("[data-editor-form]");
  if (!form || typeof onSubmit !== "function") return page;
  registerPageCleanup(
    panelBindFormAction(form, onSubmit, {
      onError: (error) => showToast(error.message, "danger"),
    }),
  );
  return page;
}

async function loadTaxonomies() {
  const requestEpoch = pageEpoch;
  const [genreResponse, tagResponse] = await Promise.all([
    api("/genres"),
    api("/tags"),
  ]);
  assertCurrentPage(requestEpoch);
  return {
    genres: responseItems(genreResponse),
    tags: responseItems(tagResponse),
  };
}

function bindTaxonomyButtons(pageRoot) {
  const bindings = [];
  pageRoot?.querySelectorAll("[data-taxonomy-choices] label").forEach((label) => {
    const onClick = () => {
      setTimeout(() => {
        const input = label.querySelector("input");
        if (!input) return;
        const checked = input.checked;
        label.classList.toggle("btn-primary", checked);
        label.classList.toggle("btn-outline-secondary", !checked);
      });
    };
    label.addEventListener("click", onClick);
    bindings.push(() => label.removeEventListener("click", onClick));
  });
  return () => bindings.splice(0).forEach((unbind) => unbind());
}

function selectedValues(formData, name) {
  return formData.getAll(name).map(String);
}

function panelNavigate(path) {
  navigatePanelPath(path, { onNavigate: triggerNavigate });
}

async function loadChapterPage(seriesId, chapterId = null) {
  const requestEpoch = pageEpoch;
  let content = (store.get("seriesList") || []).find(
    (item) => String(item.id) === String(seriesId),
  );
  if (!content) {
    content = responseItems(
      await api("/series?q=" + encodeURIComponent(seriesId) + "&per_page=100"),
    ).find((item) => String(item.id) === String(seriesId));
  }
  if (!content) {
    throw new Error(
      panelTranslate("admin.content.not_found", "İçerik bulunamadı."),
    );
  }
  await renderChapterPage(content, chapterId);
  assertCurrentPage(requestEpoch);
}

// 4. Global Action Handlers. The registry only wires Lime events to injected
// domain functions; transport, routing and page state stay outside it.
const handlers = Object.freeze({
  // Lime's events module registers delegated listeners for event names that
  // exist in the handler map. These no-op sentinels keep all supported event
  // types listening on #panel-app, including attributes introduced later by
  // a partial render. They are never used as application actions.
  click: () => {},
  input: () => {},
  change: () => {},
  submit: () => {},
  ...createPanelHandlers({
    store,
    api,
    showToast,
    confirmAction,
    promptValue,
    scheduleReload,
    setIconButtonLabel,
    loadDashboardData,
    loadSeriesData,
    loadUsersData,
    loadBlogsData,
    loadCommentsData,
    loadReportsData,
    loadLogsData,
    loadUploadsData,
    loadQueueJobsData,
    loadLikersPage,
    loadFinanceData,
    loadUserCommentsData: usersDetailController.loadUserCommentsData,
    loadUserBlogsData: usersDetailController.loadUserBlogsData,
    loadUserViolationsData: usersDetailController.loadUserViolationsData,
    loadUserWalletPage: userWalletController.loadUserWalletPage,
    registerPageCleanup,
    translate: panelTranslate,
  }),
  ...createOpsHandlers({
    api,
    showToast,
    confirmAction,
    translate: panelTranslate,
    setOpsOperation,
    loadQueueJobsData,
  }),
});

/*
 * Route construction follows below. Keeping the handler registry above the
 * route map makes its dependency boundary explicit while function declarations
 * remain hoisted for the bootstrap path.
 */

// 5. Router & View Mount

const panelRoutes = createPanelRoutes({
  loaders: {
    loadSeriesData,
    loadSeriesEditorPage,
    loadOwnershipPage,
    loadSeriesPreviewPage,
    loadSeriesRevisionsPage,
    loadChaptersPage,
    loadChapterPage,
    loadChapterPreviewPage,
    loadRbacPage,
    loadTeamPage,
    loadTaxonomyPage,
    loadUsersData,
    loadUserDetailPage: usersDetailController.loadUserDetailPage,
    loadUserPenaltyPage: userPenaltyController.loadUserPenaltyPage,
    loadUserWalletPage: userWalletController.loadUserWalletPage,
    loadBlogsData,
    loadBlogPreviewPage,
    loadLikersPage,
    loadCommentsData,
    loadReportsData,
    loadReportDetailPage,
    loadPackagesData,
    renderPackagePage,
    loadPackagePage,
    loadAdFreePage,
    loadPricingPage,
    loadFinanceData,
    loadQueueJobsData,
    loadLogsData,
    loadModerationPage,
    loadLogPage,
    loadAuditPage,
    loadUploadsData,
    loadConfigData,
    loadWebhookPage,
    loadEnvPage,
    loadDashboardData,
  },
});
const panelRouter = createPanelRouter({ routeTree: panelRoutes });

const navigationController = createPanelNavigationController({
  panelRouter, panelNavigation, store, hasPermission, showToast,
  panelTranslate, disposePage, mountPage, renderRouteTables,
  dirtyGuard,
  beginPage: () => {
    const session = pageSession.begin();
    pageEpoch = session.epoch;
    pageRequests = session.controller;
  },
});
function navigate() {
  return navigationController.navigate();
}

applyPermissionVisibility(document);
const unbindLanguageSelector = bindLanguageSelector({
  select: document.getElementById("panel-language"),
  supported: panelLanguages,
  i18n,
  onChange: () => {
    renderSidebar();
    i18n.refresh(document);
  },
  onError: () => showToast(panelTranslate("admin.language.load_failed", "Dil yüklenemedi. Tekrar deneyin."), "danger"),
});
const themeController = createThemeController({
  documentRef: document,
  windowRef: globalThis.window,
  onThemeChange: () => {
    if (store.get("currentRoute") === "dashboard") {
      renderDashboardCharts?.();
    }
  },
});
const unbindTheme = themeController.init();
const commandPalette = createCommandPalette({
  documentRef: document,
  windowRef: globalThis.window,
  panelNavigate,
  hasPermission,
  setTheme: (mode) => themeController.setTheme(mode),
  translate: panelTranslate,
});
const unbindPanelNavigation = bindPanelNavigation({
  documentRef: document,
  onNavigate: panelNavigate,
});
// The panel document lives for the lifetime of this page. Keep cleanup hooks
// for test hosts and embedders that dispose the application explicitly.
const onPopState = () => triggerNavigate();
globalThis.addEventListener?.("beforeunload", () => {
  commandPalette.cleanup?.();
  dirtyGuard.cleanup?.();
  unbindTheme?.();
  unbindPanelNavigation();
  unbindLanguageSelector();
  onPopState && globalThis.removeEventListener?.("popstate", onPopState);
  panelNavigation.cleanup?.();
}, { once: true });
globalThis.addEventListener("popstate", onPopState);
function triggerNavigate() {
  navigationController.triggerNavigate();
}
let adminStartPromise = null;
function startAdmin() {
  if (adminStartPromise) return adminStartPromise;
  adminStartPromise = (async () => {
    await i18nReady;
    renderSidebar();
    await navigate();
    // A slow dictionary must never block the first paint, but should still
    // update the sidebar when it eventually arrives.
    void dictionaryReady.then(() => {
      renderSidebar();
      i18n.refresh(document);
      // Do not re-run the current route here. A late dictionary response must
      // never dispose an editor or reset unsaved form/filter values. The next
      // navigation will use the freshly loaded dictionary.
    }).catch((error) => {
      console.warn("Panel çeviri sözlüğü güncellenemedi:", error);
    });
    return true;
  })();
  return adminStartPromise;
}

return startAdmin();
}

let bootstrapPromise;
export function startAdmin() {
  if (!bootstrapPromise) {
    bootstrapPromise = resolvePanelContext().then((context) => {
      globalThis.__NMR_CONTEXT = context;
      return initializeAdmin();
    });
  }
  return bootstrapPromise;
}
const adminReady = startAdmin();
export { adminReady };
