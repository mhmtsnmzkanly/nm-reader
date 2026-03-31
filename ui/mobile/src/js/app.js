import Framework7 from 'framework7/bundle';
import 'framework7/css/bundle';
import 'framework7-icons/css/framework7-icons.css';
import App from '../app.f7';
import { createRoutes } from './routes.js';
import { configureApplicationRuntime } from './bootstrap.js';
import { installGlobals } from './globals.js';
import { isCordovaRuntime } from '../utils/env.js';
import '../styles/app.css';

/**
 * Creates the root Framework7 app with the rebuilt mobile architecture.
 */
function createApplication() {
  const isCordova = isCordovaRuntime();

  const app = new Framework7({
    name: 'NMR Mobile',
    theme: 'auto',
    el: '#app',
    component: App,
    routes: createRoutes(),
    view: {
      browserHistory: !isCordova,
      browserHistoryRoot: '/mobile/',
      browserHistorySeparator: '',
      url: '/startup/',
    },
  });

  configureApplicationRuntime(app);
  installGlobals();
  return app;
}

createApplication();
