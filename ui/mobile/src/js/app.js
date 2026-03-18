import $ from 'dom7';
import Framework7 from 'framework7/bundle';

// Import F7 Styles
import 'framework7/css/bundle';

// Import Icons and App Custom Styles
import '../css/icons.css';
import '../css/app.css';

// Import Routes
import routes from './routes.js';
// Import Store
import store from './store.js';
// Import API
import api from './api.js';

// Import main app component
import App from '../app.f7';

const session = api.loadSession();
if (session) {
  api.setSession({
    apiToken: session.apiToken,
    csrfToken: session.csrfToken,
    refreshToken: session.refreshToken,
  });
  store.dispatch('setAuth', {
    user: session.user || null,
    apiToken: session.apiToken,
    csrfToken: session.csrfToken,
    refreshToken: session.refreshToken,
  });
}

var app = new Framework7({
  name: 'NMR Mobile', // App name
  theme: 'auto', // Automatic theme detection
  el: '#app', // App root element
  component: App, // App main component
  // App store
  store: store,
  // App routes
  routes: routes,
});

export { app };
