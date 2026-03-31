import { appStore } from '../store/app-store.js';
import { setUnauthorizedHandler } from '../services/http/client.js';
import { tryRecoverSession } from '../services/auth/auth-service.js';

let startupPromise = null;

/**
 * Restores local app state and configures cross-cutting runtime handlers.
 */
export function configureApplicationRuntime(f7) {
  appStore.hydrate();

  setUnauthorizedHandler(async () => {
    const recovered = await tryRecoverSession();
    if (!recovered) {
      f7.views.main.router.navigate('/login/');
    }
    return recovered;
  });

  document.addEventListener('auth:expired', () => {
    f7.views.main.router.navigate('/login/');
  });

  document.addEventListener('backbutton', () => {
    const mainView = f7.views.main;
    if (mainView?.router?.history?.length > 1) {
      mainView.router.back();
    }
  });
}

/**
 * Runs the one-time startup flow used by the splash/startup route.
 */
export async function runStartup() {
  if (startupPromise) {
    return startupPromise;
  }

  startupPromise = (async () => {
    try {
      const storeState = appStore.getState();
      if (!storeState.auth.accessToken && !storeState.auth.refreshToken) {
        return {
          route: '/home/',
        };
      }

      appStore.actions.setAuthRestoring();
      const recovered = await tryRecoverSession();

      if (!recovered) {
        return {
          route: '/home/',
        };
      }

      // Startup intentionally stays lightweight.
      // Profile, wallet, and preference payloads are fetched by their own pages
      // so the splash screen never blocks on protected or slow secondary calls.
      return {
        route: '/home/',
      };
    }
    catch (error) {
      appStore.actions.clearSession();
      return {
        route: '/home/',
      };
    }
  })();

  try {
    return await startupPromise;
  } finally {
    startupPromise = null;
  }
}
