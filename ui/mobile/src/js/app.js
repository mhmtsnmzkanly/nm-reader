import Framework7 from 'framework7/bundle';
import 'framework7/css/bundle';
import 'framework7-icons/css/framework7-icons.css';
import App from '../app.f7';
import { createRoutes } from './routes.js';
import { configureApplicationRuntime } from './bootstrap.js';
import { installGlobals } from './globals.js';
import { isCordovaRuntime } from '../utils/env.js';
import { debugError, debugTrace } from '../utils/debug.js';
import '../styles/app.css';

/**
 * Creates the main view only after the app root component has mounted.
 */
function initializeMainView(app, isCordova) {
  const viewElement = document.querySelector('.view-main');

  debugTrace({
    scope: 'app',
    action: 'initializeMainView:start',
    caller: 'ui/mobile/src/js/app.js#initializeMainView',
    callee: 'document.querySelector(.view-main)',
    next: 'create main view if shell is present',
    detail: {
      foundViewElement: Boolean(viewElement),
    },
  });

  if (!viewElement) {
    debugError({
      scope: 'app',
      action: 'initializeMainView:missingElement',
      caller: 'ui/mobile/src/js/app.js#initializeMainView',
      callee: 'app shell DOM',
      next: 'abort main view creation',
      detail: {
        selector: '.view-main',
      },
    });
    return null;
  }

  if (app.views.main) {
    debugTrace({
      scope: 'app',
      action: 'initializeMainView:reuse',
      caller: 'ui/mobile/src/js/app.js#initializeMainView',
      callee: 'existing app.views.main',
      next: 'configure runtime on current main view',
      detail: {
        currentUrl: app.views.main.router?.currentRoute?.url || app.views.main.router?.url || null,
      },
    });
    return app.views.main;
  }

  const mainView = app.views.create(viewElement, {
    browserHistory: !isCordova,
    browserHistoryRoot: '/mobile/',
    browserHistorySeparator: '',
    url: '/startup/',
  });

  debugTrace({
    scope: 'app',
    action: 'initializeMainView:created',
    caller: 'ui/mobile/src/js/app.js#initializeMainView',
    callee: 'app.views.create(.view-main)',
    next: 'hand off to runtime configuration',
    detail: {
      currentUrl: mainView?.router?.currentRoute?.url || mainView?.router?.url || null,
    },
  });

  return mainView;
}

/**
 * Forces the first route navigation so startup page lifecycle always begins.
 */
function startInitialRoute(mainView) {
  if (!mainView?.router) {
    debugError({
      scope: 'app',
      action: 'startInitialRoute:missingRouter',
      caller: 'ui/mobile/src/js/app.js#startInitialRoute',
      callee: 'mainView.router',
      next: 'abort initial navigation',
    });
    return;
  }

  debugTrace({
    scope: 'app',
    action: 'startInitialRoute:navigate',
    caller: 'ui/mobile/src/js/app.js#startInitialRoute',
    callee: 'mainView.router.navigate(/startup/)',
    next: 'begin startup page lifecycle',
    detail: {
      currentUrl: mainView.router.currentRoute?.url || mainView.router.url || null,
    },
  });

  mainView.router.navigate('/startup/', {
    reloadCurrent: true,
    ignoreCache: true,
  });
}

/**
 * Creates the root Framework7 app with the rebuilt mobile architecture.
 */
function createApplication() {
  const isCordova = isCordovaRuntime();
  const routes = createRoutes();

  debugTrace({
    scope: 'app',
    action: 'createApplication:start',
    caller: 'ui/mobile/src/js/app.js#createApplication',
    callee: 'Framework7 constructor',
    next: 'initialize root app instance',
    detail: {
      isCordova,
      routeCount: routes.length,
    },
  });

  const app = new Framework7({
    name: 'NMR Mobile',
    theme: 'auto',
    el: '#app',
    component: App,
    routes,
    init: true,
    on: {
      init() {
        const appInstance = this;

        debugTrace({
          scope: 'app',
          action: 'createApplication:init',
          caller: 'Framework7 init hook',
          callee: 'initializeMainView',
          next: 'mount startup route on main view',
        });

        const mainView = initializeMainView(appInstance, isCordova);
        configureApplicationRuntime(appInstance);
        installGlobals();
        startInitialRoute(mainView);

        debugTrace({
          scope: 'app',
          action: 'createApplication:ready',
          caller: 'Framework7 init hook',
          callee: 'root view',
          next: 'render /startup/ route',
          detail: {
            mainViewReady: Boolean(mainView?.router),
            currentUrl: mainView?.router?.currentRoute?.url || mainView?.router?.url || null,
          },
        });
      },
    },
  });

  debugTrace({
    scope: 'app',
    action: 'createApplication:created',
    caller: 'ui/mobile/src/js/app.js#createApplication',
    callee: 'Framework7 init hook',
    next: 'wait for app root component to mount',
  });

  return app;
}

createApplication();
