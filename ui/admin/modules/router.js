export function createPanelRouter(
  { routeTree, basePath = "/panel", location = globalThis.location } = {},
) {
  if (!routeTree || typeof routeTree !== "object") {
    throw new TypeError("createPanelRouter requires a route tree.");
  }
  const escape = (value) =>
    String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  function segments(pathname = location?.pathname || "") {
    const prefix = new RegExp(`^${escape(basePath)}(?:/|$)`);
    return String(pathname).replace(prefix, "").split("/").filter(Boolean).map(
      (value) => {
        try {
          return decodeURIComponent(value);
        } catch {
          return value;
        }
      },
    );
  }
  return Object.freeze({
    segments,
    resolve: (pathname = location?.pathname || "") =>
      resolveNode(routeTree, segments(pathname)),
  });
}
function resolveNode(node, remaining, params = {}) {
  if (remaining.length === 0) {
    return node.index
      ? { ...node.index, params }
      : node.fallback
      ? { ...node.fallback, params }
      : null;
  }
  for (const branch of node.branches || []) {
    const matched = match(branch.segment, remaining[0]);
    if (!matched) continue;
    const next = { ...params };
    if (branch.param) {
      next[branch.param] = matched.groups?.[branch.param] || matched[1] ||
        remaining[0];
    }
    const result = resolveNode(branch, remaining.slice(1), next);
    if (result) return result;
  }
  return node.fallback ? { ...node.fallback, params } : null;
}
function match(pattern, segment) {
  return typeof pattern === "string"
    ? pattern === segment ? { groups: null } : null
    : String(segment).match(pattern);
}
export function isRouteLink(
  element,
  basePath = "/panel",
  locationRef = globalThis.location,
) {
  if (
    !element || element.tagName !== "A" ||
    typeof element.matches !== "function" ||
    !element.matches("[data-panel-link], [data-route]")
  ) return false;
  if (
    element.hasAttribute("download") ||
    !["", "_self"].includes((element.getAttribute("target") || "").toLowerCase())
  ) return false;
  const href = element.getAttribute("href") || "";
  if (!href || href.startsWith("#")) return false;
  try {
    const url = new URL(href, locationRef?.href);
    return url.origin === locationRef?.origin &&
      new RegExp(
        `^${String(basePath).replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}(?:/|$)`,
      ).test(url.pathname);
  } catch {
    return false;
  }
}

/**
 * Bind browser navigation for panel links only. This deliberately lives next
 * to route-link classification so page code never needs to inspect arbitrary
 * sidebar anchors or treeview controls.
 */
function escapeRouteBase(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

export function bindPanelNavigation({
  documentRef = globalThis.document,
  onNavigate,
  basePath = "/panel",
  locationRef = documentRef?.defaultView?.location || globalThis.location,
} = {}) {
  if (!documentRef || typeof onNavigate !== "function") return () => {};
  const ElementCtor = documentRef.defaultView?.Element || globalThis.Element;
  const onClick = (event) => {
    const target = ElementCtor && event.target instanceof ElementCtor
      ? event.target
      : null;
    const candidate = target?.closest?.("a");
    const link = isRouteLink(candidate, basePath, locationRef) ? candidate : null;
    if (
      !link || event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    ) return;
    event.preventDefault();
    onNavigate(link.getAttribute("href"));
  };
  documentRef.addEventListener("click", onClick);
  return () => documentRef.removeEventListener("click", onClick);
}

/** Navigate to a same-origin panel path and notify the CSR route runner. */
export function navigatePanelPath(
  path,
  {
    locationRef = globalThis.location,
    historyRef = globalThis.history,
    onNavigate,
    basePath = "/panel",
  } = {},
) {
  if (!path || !historyRef || typeof historyRef.pushState !== "function") return false;
  let url;
  try {
    url = new URL(path, locationRef?.href);
  } catch {
    return false;
  }
  if (
    url.origin !== locationRef?.origin ||
    !new RegExp(`^${escapeRouteBase(basePath)}(?:/|$)`).test(url.pathname)
  ) return false;
  historyRef.pushState({}, "", url.pathname + url.search);
  onNavigate?.();
  return true;
}

/**
 * Invoke a route loader through one async boundary. Page loaders are normally
 * async, but a template-only loader can throw synchronously; normalizing both
 * forms lets the navigation runner use one error boundary and one teardown
 * path.
 */
export function runPanelLoader(loader, params = {}) {
  if (typeof loader !== "function") return Promise.resolve(undefined);
  return Promise.resolve().then(() => loader(params));
}
