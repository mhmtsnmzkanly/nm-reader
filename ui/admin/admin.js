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
  show,
  text,
} from "https://cdn.jsdelivr.net/npm/lime-csr-js@0.3.0/dist/index.min.js";
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
  runPanelLoader,
} from "./modules/router.js?129";
import { createPageSession } from "./modules/page-session.js?125";
import {
  capturePanelGroupState,
  createPanelNavigation,
  restorePanelGroupState,
} from "./modules/navigation.js?130";
import { createI18n } from "./modules/i18n.js?131";
import { createFeedback } from "./modules/feedback.js?125";
import { createRequestGate } from "./modules/request-gate.js?125";
import { createTranslationModule } from "./modules/directives/translation.js?126";
import { bindFormAction } from "./modules/form-action.js?126";
import { createDashboardChartManager } from "./modules/dashboard-charts.js?125";
import { createDashboardDataController } from "./modules/dashboard-data.js?130";
import { createDashboardView } from "./modules/dashboard-view.js?125";
import { createConfigPagesController } from "./modules/config-pages.js?129";
import { createCollectionDataController } from "./modules/collection-data.js?127";
import { createChaptersPageController } from "./modules/chapters-page.js?128";
import { createContentPreviewsController } from "./modules/content-previews.js?127";
import { createChapterEditorController } from "./modules/chapter-editor.js?128";
import { createSeriesEditorController } from "./modules/series-editor.js?130";
import { createTaxonomyPageController } from "./modules/taxonomy-page.js?126";
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
  panelPageHeaderKeys,
  panelRootRoutes,
} from "./modules/routes.js?127";
const configuredNextYear = document
  .querySelector('meta[name="nmr-next-year"]')
  ?.getAttribute("content") || "";
const nextYear = /^\d{4}$/.test(configuredNextYear)
  ? configuredNextYear
  : String(new Date().getFullYear() + 1);

const i18n = createI18n({
  locale: globalThis.__NMR_CONTEXT?.lang_code || "en",
  fallbackLocale: globalThis.__NMR_CONTEXT?.default_lang || "en",
  supported: globalThis.__NMR_CONTEXT?.supported_langs || [],
  initialDictionaries: globalThis.__NMR_CONTEXT?.translations || {},
});
document.documentElement.lang = i18n.locale();
const feedback = createFeedback(document);
const { confirmAction, promptValue } = feedback;
function panelTranslate(key, fallback, params = {}) {
  const value = i18n.t(key, params);
  return value === key ? fallback : value;
}
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
  panelTranslationModule,
];
const panelEngine = createEngine({ modules: panelModules });
// Partials are mounted inside the page engine's target. Lime's events module
// delegates from its mount target, so mounting a second events-enabled engine
// here would dispatch every partial action twice (nested target + #panel-app).
// Structural/translation modules still run for partials; the page engine's
// single delegated listener handles their data-on-* attributes.
const panelPartialEngine = createEngine({
  modules: panelModules.filter((module) => module.name !== "events"),
});
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
  // no Lime data-on-* actions. Use the structural engine so a dictionary
  // refresh cannot add a second delegated events listener to the sidebar.
  sidebarRender = panelPartialEngine.render(shell, { store, handlers });
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

// Central mount boundary. Page features supply the documented 0.3.0 positional
// signature here, keeping engine ownership in one place during the refactor.
function mount(name, options = {}) {
  const { target: mountTarget, context = {}, store: mountStore, ...rest } =
    options;
  return panelEngine.mount(mountTarget, name, mountStore || store, {
    ...rest,
    context,
    handlers: rest.handlers || handlers,
  });
}

function mountPartialEngine(name, options = {}) {
  const { target: mountTarget, context = {}, store: mountStore, ...rest } =
    options;
  return panelPartialEngine.mount(mountTarget, name, mountStore || store, {
    ...rest,
    context,
  });
}

const reloadTimers = new Map();
const pagePartials = new Set();
const pagePartialTargets = new Map();
function scheduleReload(key, callback) {
  clearTimeout(reloadTimers.get(key));
  const epoch = pageEpoch;
  reloadTimers.set(
    key,
    setTimeout(() => {
      reloadTimers.delete(key);
      if (epoch === pageEpoch) callback();
    }, 300),
  );
}

function mountPartial(name, target, context = {}) {
  if (!target) return null;
  // The page mount owns Lime's delegated event listeners on #panel-app.
  // Partials are nested inside that target, so attaching handlers here would
  // invoke the same data-on-* action more than once as the event bubbles.
  // They are also rendered as short-lived snapshots; the parent page or the
  // page controller explicitly remounts them when data changes, so they do not
  // need independent reactive subscriptions.
  const previous = pagePartialTargets.get(target);
  if (previous) {
    previous.instance?.unmount?.();
    previous.unregister?.();
    pagePartials.delete(previous.instance);
  }
  const instance = mountPartialEngine(name, { target, context });
  applyPermissionVisibility(target);
  if (instance?.unmount) {
    pagePartials.add(instance);
    const unregister = registerPageCleanup(() => {
      // A route replacement may have already disposed this partial
      // synchronously. Lime instances are expected to be disposable, but the
      // guard keeps cleanup idempotent when session disposal runs afterward.
      if (pagePartials.has(instance)) {
        instance.unmount();
        pagePartials.delete(instance);
      }
      if (pagePartialTargets.get(target)?.instance === instance) {
        pagePartialTargets.delete(target);
      }
    });
    pagePartialTargets.set(target, { instance, unregister });
  }
  return instance;
}

function clearPagePartials() {
  for (const [partialTarget, entry] of pagePartialTargets) {
    entry.instance?.unmount?.();
    entry.unregister?.();
    pagePartials.delete(entry.instance);
    if (pagePartialTargets.get(partialTarget) === entry) {
      pagePartialTargets.delete(partialTarget);
    }
  }
  // A defensive sweep also handles a partial created by a custom page module
  // that did not register a target entry (for example an inactive mount).
  for (const partial of pagePartials) partial.unmount?.();
  pagePartials.clear();
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
  mountPartial,
  scheduleReload,
  panelNavigate,
  bindFormAction,
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
  bindFormAction,
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
  getPageParent: () => pageParent,
  assertCurrentPage,
  safeLocalPath,
  mountEditorPage,
  mountPartial,
  renderPager,
  panelNavigate,
  showToast,
  hasPermission,
  requestGate: requestGates.likers,
  bindFormAction,
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
  bindFormAction,
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

let pageParent = "/panel";
let pageMount = null;

function registerPageCleanup(callback) {
  return pageSession.onCleanup(callback);
}

async function disposePage() {
  pageMount?.unmount?.();
  pageMount = null;
  for (const timer of reloadTimers.values()) clearTimeout(timer);
  reloadTimers.clear();
  await pageSession.dispose();
}

function mountPage(name, context = {}) {
  if (!target) return null;
  // A route loader may replace the initial loading view with an editor or a
  // detail page. Dispose that short-lived Lime instance before mounting the
  // replacement so delegated listeners and subscriptions do not accumulate.
  pageMount?.unmount?.();
  pageMount = null;
  clearPagePartials();
  // Lime reports an explicit FOR_NOT_ARRAY diagnostic for an absent loop
  // source. Pages are often mounted once before their async payload arrives;
  // seed common collection paths so the first render is a quiet empty state.
  const mountContext = {
    items: [],
    rows: [],
    pages: [],
    packages: [],
    genres: [],
    tags: [],
    restrictions: [],
    roles: [],
    ...context,
  };
  pageMount = mount(name, { target, context: mountContext, store, handlers });
  // Lime owns the target's DOM and cleanup lifecycle. Keep the target as the
  // page root so every page-local query and listener remains inside the same
  // mount boundary without introducing a second wrapper lifecycle.
  applyPermissionVisibility(target);
  return target;
}

function mountEditorPage(title, body, onSubmit) {
  const fields = body.context || {};
  const page = mountPage(body.name + "-page", {
    header: { title, parent_path: pageParent, show_back: true },
    fields,
    // Some presentation-only pages (for example likers) bind directly to
    // their page context instead of a form partial's `fields` scope. Keep the
    // shared `fields` contract and expose the same read-only values at root.
    ...fields,
  });
  const form = page.querySelector("[data-editor-form]");
  if (!form || typeof onSubmit !== "function") return page;
  registerPageCleanup(
    bindFormAction(form, onSubmit, {
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
    loadUserWalletPage: usersDetailController.loadUserWalletPage,
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
const target = document.getElementById("panel-app");

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
    loadUserPenaltyPage: usersDetailController.loadUserPenaltyPage,
    loadUserWalletPage: usersDetailController.loadUserWalletPage,
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

function resolvePanelRoute() {
  return panelRouter.resolve();
}

// Root list pages share the same shell as detail/editor pages. Keeping the
// metadata here leaves page templates focused on their body and lets the
// translation module provide the visible title/description at mount time.
function panelPageContext(resolved) {
  const context = { ...resolved.params };
  const metadata = panelPageHeaderKeys[resolved.route] ||
    panelPageHeaderKeys[resolved.section];
  if (!metadata) return context;
  context.header = {
    title_key: metadata[0],
    title: panelTranslate(metadata[0], metadata[1]),
    description_key: metadata[2],
    description: panelTranslate(metadata[2], metadata[3]),
    parent_path: pageParent,
    show_back: !panelRootRoutes.has(resolved.route),
  };
  return context;
}

let navigationSequence = 0;
async function navigate() {
  const navigationId = ++navigationSequence;
  let resolved = resolvePanelRoute();
  if (!resolved) return;

  if (resolved.redirect && globalThis.location.pathname !== resolved.redirect) {
    showToast(
      panelTranslate(
        "admin.route.not_found",
        "İstenen panel sayfası bulunamadı; dashboard açıldı.",
      ),
      "warning",
    );
    globalThis.history.replaceState({}, "", resolved.redirect);
    resolved = resolvePanelRoute();
  }

  const required = resolved.permissions || [];
  if (required.length > 0 && !hasPermission(...required)) {
    if (globalThis.location.pathname !== "/panel") {
      globalThis.history.replaceState({}, "", "/panel");
      resolved = resolvePanelRoute();
    }
    const fallbackRequired = resolved?.permissions || [];
    if (fallbackRequired.length > 0 && !hasPermission(...fallbackRequired)) {
      await disposePage();
      if (navigationId !== navigationSequence) return;
      const session = pageSession.begin();
      pageEpoch = session.epoch;
      pageRequests = session.controller;
      mountPage("panel-page-error-shell", {
        header: {
          title: panelTranslate("admin.page.error_title", "Sayfa kullanılamıyor"),
          parent_path: "/",
          show_back: true,
        },
        error_message: panelTranslate(
          "admin.permission.denied",
          "Bu panel bölümü için gerekli yetkiniz bulunmuyor.",
        ),
      });
      return;
    }
  }

  await disposePage();
  if (navigationId !== navigationSequence) return;
  const session = pageSession.begin();
  pageEpoch = session.epoch;
  pageRequests = session.controller;
  const sectionParent = {
    taxonomies: "series",
    webhook: "config",
    "config-env": "config",
  }[resolved.section] || resolved.section;
  pageParent = resolved.section === "user" && resolved.params.userId
    ? "/panel/user/" + encodeURIComponent(resolved.params.userId)
    : sectionParent && sectionParent !== "dashboard"
    ? "/panel/" + sectionParent
    : "/panel";
  if (
    ["chapter-new", "chapter-edit", "chapter-preview", "series-team"].includes(
      resolved.route,
    )
  ) {
    pageParent = "/panel/series/" +
      encodeURIComponent(resolved.params.seriesId) +
      "/chapters";
  }
  store.set("currentRoute", resolved.route);

  const activeSection = resolved.section || resolved.route || "dashboard";
  store.set("currentSection", activeSection);
  panelNavigation.setActiveRoute?.(resolved.route, activeSection);

  if (resolved.view) {
    mountPage(resolved.view, panelPageContext(resolved));
    renderRouteTables(resolved.route);
  } else {
    mountPage("panel-loading");
  }

  if (typeof resolved.load === "function") {
    runPanelLoader(resolved.load, resolved.params).catch((error) => {
      if (error?.name === "AbortError" || navigationId !== navigationSequence) return;
      void (async () => {
        // A loader may have installed page-local listeners before a later
        // synchronous/async failure. Dispose that failed page before showing
        // the error view so its cleanup callbacks cannot leak into the next
        // navigation.
        await disposePage();
        if (navigationId !== navigationSequence) return;
        const session = pageSession.begin();
        pageEpoch = session.epoch;
        pageRequests = session.controller;
        mountPage("panel-page-error-shell", {
          header: {
            title: panelTranslate("admin.page.error_title", "Sayfa yüklenemedi"),
            parent_path: pageParent,
            show_back: true,
          },
          error_message: error.message || panelTranslate(
            "admin.page.load_failed",
            "Sayfa verileri yüklenemedi.",
          ),
        });
      })().catch((disposeError) => {
        if (disposeError?.name !== "AbortError") {
          console.error("Panel hata görünümü hazırlanamadı:", disposeError);
        }
      });
    });
  }
}

applyPermissionVisibility(document);
const unbindPanelNavigation = bindPanelNavigation({
  documentRef: document,
  onNavigate: panelNavigate,
});
// The panel document lives for the lifetime of this page. Keep cleanup hooks
// for test hosts and embedders that dispose the application explicitly.
const onPopState = () => triggerNavigate();
globalThis.addEventListener?.("beforeunload", () => {
  unbindPanelNavigation();
  onPopState && globalThis.removeEventListener?.("popstate", onPopState);
  panelNavigation.cleanup?.();
}, { once: true });
globalThis.addEventListener("popstate", onPopState);
function triggerNavigate() {
  void navigate().catch((error) => {
    if (error?.name === "AbortError") return;
    console.error("Panel navigasyonu başarısız:", error);
    showToast(
      error?.message ||
        panelTranslate("admin.page.load_failed", "Panel sayfası yüklenemedi."),
      "danger",
    );
  });
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

const adminReady = startAdmin();
export { adminReady, startAdmin };
