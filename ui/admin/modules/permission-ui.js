export function createPermissionUi(
  { documentRef = globalThis.document, can = () => false } = {},
) {
  function apply(root = documentRef) {
    if (!root?.querySelectorAll) return;
    root.querySelectorAll("[data-requires-permission]").forEach((element) => {
      const required = String(element.dataset.requiresPermission || "").split(
        ",",
      ).map((value) => value.trim()).filter(Boolean);
      const allowed = required.length === 0 || can(...required);
      element.classList.toggle("permission-hidden", !allowed);
      if (allowed) element.removeAttribute("aria-hidden");
      else element.setAttribute("aria-hidden", "true");
    });
    root.querySelectorAll("#panel-sidebar-nav [data-panel-group]").forEach(
      (toggle) => {
        const group = toggle.closest(".nav-item");
        const submenu = group?.querySelector(".nav-treeview");
        if (!submenu) return;
        const children = Array.from(
          submenu.querySelectorAll(":scope > .nav-item"),
        );
        const hasVisibleChild = children.some(
          (child) => !child.hidden && !child.classList.contains("permission-hidden"),
        );
        group.classList.toggle("permission-hidden", !hasVisibleChild);
        if (hasVisibleChild) group.removeAttribute("aria-hidden");
        else group.setAttribute("aria-hidden", "true");
      },
    );
  }
  return Object.freeze({ apply });
}
