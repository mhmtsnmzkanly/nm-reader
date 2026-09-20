import { runPanelLoader } from "./router.js";
import { panelPageHeaderKeys, panelRootRoutes } from "./routes.js";

// Coordinates route access, page replacement and stale-loader protection.
export function createPanelNavigationController({
  panelRouter, panelNavigation, store, hasPermission, showToast,
  panelTranslate, disposePage, beginPage, mountPage, renderRouteTables,
  browser = globalThis,
  dirtyGuard = null,
}) {
  let pageParent = "/panel";
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
    if (dirtyGuard && !(await dirtyGuard.checkCanNavigate())) return;
    const navigationId = ++navigationSequence;
    let resolved = resolvePanelRoute();
    if (!resolved) return;

    if (resolved.redirect && browser.location.pathname !== resolved.redirect) {
      showToast(
        panelTranslate(
          "admin.route.not_found",
          "İstenen panel sayfası bulunamadı; dashboard açıldı.",
        ),
        "warning",
      );
      browser.history.replaceState({}, "", resolved.redirect);
      resolved = resolvePanelRoute();
    }

    const required = resolved.permissions || [];
    if (required.length > 0 && !hasPermission(...required)) {
      if (browser.location.pathname !== "/panel") {
        browser.history.replaceState({}, "", "/panel");
        resolved = resolvePanelRoute();
      }
      const fallbackRequired = resolved?.permissions || [];
      if (fallbackRequired.length > 0 && !hasPermission(...fallbackRequired)) {
        await disposePage();
        if (navigationId !== navigationSequence) return;
        beginPage();
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
    beginPage();
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
          beginPage();
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

  return Object.freeze({ navigate, triggerNavigate, parentPath: () => pageParent });
}
