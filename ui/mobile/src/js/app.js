import Framework7 from 'framework7/bundle';
import 'framework7/css/bundle';
import 'framework7-icons/css/framework7-icons.css';
import App from '../app.f7';
import { createRoutes } from './routes.js';
import { configureApplicationRuntime } from './bootstrap.js';
import { installGlobals } from './globals.js';
import { isCordovaRuntime } from '../utils/env.js';
import { debugTrace } from '../utils/debug.js';
import '../styles/app.css';

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
  });

  const mainView = app.views.create('.view-main', {
    browserHistory: !isCordova,
    browserHistoryRoot: '/mobile/',
    browserHistorySeparator: '',
    url: '/startup/',
  });

  debugTrace({
    scope: 'app',
    action: 'createApplication:created',
    caller: 'ui/mobile/src/js/app.js#createApplication',
    callee: 'app.views.create(.view-main)',
    next: 'initialize main router view',
    detail: {
      mainViewReady: Boolean(mainView?.router),
    },
  });

  debugTrace({
    scope: 'app',
    action: 'createApplication:viewReady',
    caller: 'ui/mobile/src/js/app.js#createApplication',
    callee: 'configureApplicationRuntime',
    next: 'configure runtime handlers',
    detail: {
      currentUrl: mainView?.router?.currentRoute?.url || mainView?.router?.url || null,
    },
  });

  configureApplicationRuntime(app);
  installGlobals();

  debugTrace({
    scope: 'app',
    action: 'createApplication:ready',
    caller: 'ui/mobile/src/js/app.js#createApplication',
    callee: 'root view',
    next: 'render /startup/ route',
  });

  return app;
}

createApplication();
