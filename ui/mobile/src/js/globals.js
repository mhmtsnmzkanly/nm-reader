import { appStore } from '../store/app-store.js';

/**
 * Exposes a tiny debugging surface for local development without polluting the app design.
 */
export function installGlobals() {
  if (typeof window === 'undefined') {
    return;
  }

  window.NMRMobile = {
    getState: () => appStore.getState(),
  };
}
