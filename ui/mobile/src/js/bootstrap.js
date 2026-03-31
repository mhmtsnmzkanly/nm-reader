import { appStore } from '../store/app-store.js';
import { setUnauthorizedHandler } from '../services/http/client.js';
import { tryRecoverSession } from '../services/auth/auth-service.js';
import { debugError, debugTrace } from '../utils/debug.js';

let startupPromise = null;

/**
 * Restores local app state and configures cross-cutting runtime handlers.
 */
export function configureApplicationRuntime(f7) {
  debugTrace({
    scope: 'bootstrap',
    action: 'configureApplicationRuntime:start',
    caller: 'ui/mobile/src/js/bootstrap.js#configureApplicationRuntime',
    callee: 'appStore.hydrate',
    next: 'hydrate persisted store state',
  });

  appStore.hydrate();

  setUnauthorizedHandler(async () => {
    debugTrace({
      scope: 'bootstrap',
      action: 'unauthorizedHandler:entered',
      caller: 'ui/mobile/src/js/bootstrap.js#configureApplicationRuntime',
      callee: 'tryRecoverSession',
      next: 'attempt auth recovery or redirect to /login/',
    });

    const recovered = await tryRecoverSession();
    if (!recovered) {
      debugTrace({
        scope: 'bootstrap',
        action: 'unauthorizedHandler:redirect',
        caller: 'unauthorizedHandler',
        callee: 'router.navigate(/login/)',
        next: 'show login screen',
      });
      f7.views.main.router.navigate('/login/');
    }
    return recovered;
  });

  document.addEventListener('auth:expired', () => {
    debugTrace({
      scope: 'bootstrap',
      action: 'event:authExpired',
      caller: 'document auth:expired listener',
      callee: 'router.navigate(/login/)',
      next: 'force login route',
    });
    f7.views.main.router.navigate('/login/');
  });

  document.addEventListener('backbutton', () => {
    const mainView = f7.views.main;
    if (mainView?.router?.history?.length > 1) {
      debugTrace({
        scope: 'bootstrap',
        action: 'event:backbutton',
        caller: 'document backbutton listener',
        callee: 'mainView.router.back',
        next: 'navigate to previous route',
        detail: {
          historyLength: mainView.router.history.length,
        },
      });
      mainView.router.back();
    }
  });

  debugTrace({
    scope: 'bootstrap',
    action: 'configureApplicationRuntime:ready',
    caller: 'ui/mobile/src/js/bootstrap.js#configureApplicationRuntime',
    callee: 'runtime listeners',
    next: 'wait for startup route',
  });
}

/**
 * Runs the one-time startup flow used by the splash/startup route.
 */
export async function runStartup() {
  if (startupPromise) {
    debugTrace({
      scope: 'startup',
      action: 'runStartup:reusePromise',
      caller: 'ui/mobile/src/js/bootstrap.js#runStartup',
      callee: 'existing startupPromise',
      next: 'await current startup execution',
    });
    return startupPromise;
  }

  startupPromise = (async () => {
    try {
      debugTrace({
        scope: 'startup',
        action: 'runStartup:start',
        caller: 'ui/mobile/src/js/bootstrap.js#runStartup',
        callee: 'appStore.getState',
        next: 'inspect stored auth snapshot',
      });

      const storeState = appStore.getState();
      if (!storeState.auth.accessToken && !storeState.auth.refreshToken) {
        debugTrace({
          scope: 'startup',
          action: 'runStartup:noSession',
          caller: 'runStartup',
          callee: 'route resolver',
          next: 'navigate to /home/',
        });
        return {
          route: '/home/',
        };
      }

      debugTrace({
        scope: 'startup',
        action: 'runStartup:setAuthRestoring',
        caller: 'runStartup',
        callee: 'appStore.actions.setAuthRestoring',
        next: 'start token recovery',
      });

      appStore.actions.setAuthRestoring();
      const recovered = await tryRecoverSession();

      if (!recovered) {
        debugTrace({
          scope: 'startup',
          action: 'runStartup:recoveryFailed',
          caller: 'runStartup',
          callee: 'route resolver',
          next: 'navigate to /home/ as guest',
        });
        return {
          route: '/home/',
        };
      }

      // Startup intentionally stays lightweight.
      // Profile, wallet, and preference payloads are fetched by their own pages
      // so the splash screen never blocks on protected or slow secondary calls.
      debugTrace({
        scope: 'startup',
        action: 'runStartup:recoverySucceeded',
        caller: 'runStartup',
        callee: 'route resolver',
        next: 'navigate to /home/ with recovered auth',
      });

      return {
        route: '/home/',
      };
    }
    catch (error) {
      debugError({
        scope: 'startup',
        action: 'runStartup:error',
        caller: 'ui/mobile/src/js/bootstrap.js#runStartup',
        callee: 'appStore.actions.clearSession',
        next: 'clear broken session and navigate to /home/',
        error,
      });
      appStore.actions.clearSession();
      return {
        route: '/home/',
      };
    }
  })();

  try {
    return await startupPromise;
  } finally {
    debugTrace({
      scope: 'startup',
      action: 'runStartup:finally',
      caller: 'ui/mobile/src/js/bootstrap.js#runStartup',
      callee: 'startupPromise reset',
      next: 'allow future startup attempts',
    });
    startupPromise = null;
  }
}
