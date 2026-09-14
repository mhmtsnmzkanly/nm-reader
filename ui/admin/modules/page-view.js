// Owns page mounts, partial snapshots, reload timers and cleanup registration.
export function createPageView({ target, pageSession, mount, mountPartialEngine, applyPermissionVisibility }) {
  const reloadTimers = new Map();
  const pagePartials = new Set();
  const pagePartialTargets = new Map();
  function scheduleReload(key, callback) {
    clearTimeout(reloadTimers.get(key));
    const epoch = pageSession.current().epoch;
    reloadTimers.set(
      key,
      setTimeout(() => {
        reloadTimers.delete(key);
        if (epoch === pageSession.current().epoch) callback();
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
    pageMount = mount(name, { target, context: mountContext });
    // Lime owns the target's DOM and cleanup lifecycle. Keep the target as the
    // page root so every page-local query and listener remains inside the same
    // mount boundary without introducing a second wrapper lifecycle.
    applyPermissionVisibility(target);
    return target;
  }


  return Object.freeze({ mountPage, mountPartial, scheduleReload, disposePage, registerPageCleanup });
}
