export function createPageSession() {
  let epoch = 0;
  let controller = new AbortController();
  const cleanups = new Set();
  function begin() {
    controller.abort();
    epoch += 1;
    controller = new AbortController();
    return Object.freeze({ epoch, signal: controller.signal, controller });
  }
  function onCleanup(callback) {
    if (typeof callback !== "function") return () => {};
    cleanups.add(callback);
    return () => cleanups.delete(callback);
  }
  async function dispose() {
    controller.abort();
    // Invalidate the session before awaiting user cleanup. A response that
    // was already queued must not pass the old epoch while disposal runs.
    epoch += 1;
    controller = new AbortController();
    const pending = [...cleanups];
    cleanups.clear();
    await Promise.allSettled(
      pending.map((callback) => Promise.resolve().then(callback)),
    );
  }
  return Object.freeze({
    begin,
    onCleanup,
    dispose,
    current: () =>
      Object.freeze({ epoch, signal: controller.signal, controller }),
    isCurrent: (candidate) => candidate === epoch,
    abort: () => controller.abort(),
  });
}
