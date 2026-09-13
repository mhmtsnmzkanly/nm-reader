export function setPanelGroupState(item, toggle, open) {
  if (!item || !toggle) return;
  item.classList.toggle("menu-open", open);
  toggle.setAttribute("aria-expanded", String(open));
  const submenu = item.querySelector(".nav-treeview");
  if (submenu) submenu.style.display = open ? "block" : "none";
}

/**
 * Capture the user's expanded treeview choices before the sidebar is
 * re-rendered. The key is the stable data-panel-group value, so this does not
 * depend on the sidebar's DOM position or translated label.
 */
export function capturePanelGroupState(root) {
  if (!root?.querySelectorAll) return new Map();
  return new Map(
    Array.from(root.querySelectorAll("[data-panel-group]")).map((toggle) => [
      toggle.getAttribute("data-panel-group"),
      toggle.getAttribute("aria-expanded") === "true" ||
        toggle.closest(".nav-item")?.classList.contains("menu-open") === true,
    ]),
  );
}

/** Restore a previously captured treeview state after the sidebar is mounted. */
export function restorePanelGroupState(root, state) {
  if (!root?.querySelectorAll || !(state instanceof Map)) return;
  root.querySelectorAll("[data-panel-group]").forEach((toggle) => {
    const key = toggle.getAttribute("data-panel-group");
    if (!state.has(key)) return;
    const item = toggle.closest(".nav-item");
    if (item) setPanelGroupState(item, toggle, state.get(key));
  });
}

/**
 * Synchronize route highlighting without exposing sidebar structure to the
 * router. The navigation adapter owns both active links and their parent
 * treeview state.
 */
export function setPanelActiveRoute(root, route, section = route) {
  if (!root?.querySelectorAll) return null;
  const links = Array.from(root.querySelectorAll("a[data-route]"));
  const active = links.find((link) => link.getAttribute("data-route") === route) ||
    links.find((link) => link.getAttribute("data-route") === section) || null;
  links.forEach((link) => {
    const isActive = link === active;
    link.classList.toggle("active-nav-link", isActive);
    if (isActive) link.setAttribute("aria-current", "page");
    else link.removeAttribute("aria-current");
  });
  const activeGroup = active?.closest(".nav-treeview")?.closest(".nav-item");
  if (activeGroup) {
    const toggle = activeGroup.querySelector(":scope > [data-panel-group]");
    if (toggle) setPanelGroupState(activeGroup, toggle, true);
  }
  return active;
}

export function bindPanelGroups(
  root,
  setGroupState,
  { elementConstructor = globalThis.Element } = {},
) {
  if (!root || typeof setGroupState !== "function") return () => {};
  const toggle = (event) => {
    const target =
      elementConstructor && event.target instanceof elementConstructor
        ? event.target
        : null;
    const groupToggle = target?.closest("[data-panel-group]");
    if (!groupToggle || !root.contains(groupToggle)) return;
    const item = groupToggle.closest(".nav-item");
    if (!item) return;
    event.preventDefault();
    setGroupState(item, groupToggle, !item.classList.contains("menu-open"));
  };
  const onClick = (event) => {
    toggle(event);
  };
  const onKeydown = (event) => {
    if (event.key === "Enter" || event.key === " ") toggle(event);
  };
  root.addEventListener("click", onClick);
  root.addEventListener("keydown", onKeydown);
  return () => {
    root.removeEventListener("click", onClick);
    root.removeEventListener("keydown", onKeydown);
  };
}

/**
 * Own the shell-level interactions that are not routes. Keeping this outside
 * the router makes sidebar markup replaceable without changing navigation
 * semantics.
 */
export function createPanelNavigation({ documentRef = globalThis.document } = {}) {
  const document = documentRef;
  const body = document?.body;
  const sidebarToggle = document?.querySelector?.('[data-lte-toggle="sidebar"]');
  const sidebarOverlay = document?.querySelector?.(".sidebar-overlay");
  const sidebarNav = document?.querySelector?.("#panel-sidebar-nav");
  const ElementCtor = document?.defaultView?.Element || globalThis.Element;
  if (!document || !body) return Object.freeze({ cleanup: () => {} });

  const unbindGroups = bindPanelGroups(sidebarNav, setPanelGroupState, {
    elementConstructor: ElementCtor,
  });
  const onSidebarToggle = (event) => {
    event.preventDefault();
    const wasOpen = body.classList.contains("sidebar-open");
    document.defaultView?.setTimeout?.(() => {
      if (body.classList.contains("sidebar-open") === wasOpen) {
        body.classList.toggle("sidebar-open", !wasOpen);
      }
    }, 0);
  };
  const onOverlayClick = () => body.classList.remove("sidebar-open");
  sidebarToggle?.addEventListener("click", onSidebarToggle);
  sidebarOverlay?.addEventListener("click", onOverlayClick);

  const onDocumentClick = (event) => {
    if (globalThis.bootstrap?.Dropdown) return;
    const target = ElementCtor && event.target instanceof ElementCtor
      ? event.target
      : null;
    const toggle = target?.closest('[data-bs-toggle="dropdown"]');
    document.querySelectorAll(".dropdown-menu.show").forEach((menu) => {
      if (!toggle || !menu.closest(".dropdown")?.contains(toggle)) {
        menu.classList.remove("show");
      }
    });
    if (!toggle) return;
    event.preventDefault();
    toggle.closest(".dropdown")?.querySelector(".dropdown-menu")?.classList.toggle("show");
  };
  document.addEventListener("click", onDocumentClick);

  return Object.freeze({
    setGroupState: setPanelGroupState,
    setActiveRoute: (route, section = route) =>
      setPanelActiveRoute(sidebarNav, route, section),
    cleanup() {
      sidebarToggle?.removeEventListener("click", onSidebarToggle);
      sidebarOverlay?.removeEventListener("click", onOverlayClick);
      document.removeEventListener("click", onDocumentClick);
      unbindGroups();
    },
  });
}
