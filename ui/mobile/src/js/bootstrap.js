import { appStore } from '../store/app-store.js';
import { setUnauthorizedHandler } from '../services/http/client.js';
import { tryRecoverSession } from '../services/auth/auth-service.js';
import { fetchProfile, fetchPreferences } from '../services/user/user-service.js';
import { fetchWalletSummary } from '../services/wallet/wallet-service.js';

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

    const [profile, wallet, preferences] = await Promise.allSettled([
      fetchProfile(),
      fetchWalletSummary(),
      fetchPreferences(),
    ]);

    if (profile.status === 'fulfilled') {
      appStore.actions.setUser(profile.value);
    }

    if (wallet.status === 'fulfilled') {
      appStore.actions.setWalletSummary(wallet.value);
    }

    if (preferences.status === 'fulfilled') {
      appStore.actions.setPreferences({
        language: preferences.value.lang || appStore.getState().preferences.language,
        theme: preferences.value.theme || appStore.getState().preferences.theme,
      });
      appStore.actions.setReaderPreferences({
        theme: preferences.value.reader_theme || appStore.getState().readerPreferences.theme,
        fontSize: Number(preferences.value.reader_font_size || appStore.getState().readerPreferences.fontSize),
        imageMode: preferences.value.reader_layout || appStore.getState().readerPreferences.imageMode,
      });
    }

    return {
      route: '/home/',
    };
  })();

  try {
    return await startupPromise;
  } finally {
    startupPromise = null;
  }
}
