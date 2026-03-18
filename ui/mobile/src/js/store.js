import { createStore } from 'framework7';

const store = createStore({
  state: {
    auth: {
      isLoggedIn: false,
      user: null,
      apiToken: null,
      csrfToken: null,
      refreshToken: null,
    },
    wallet: {
      balance: 0,
      lastUpdated: null,
    },
  },
  getters: {
    auth({ state }) {
      return state.auth;
    },
    wallet({ state }) {
      return state.wallet;
    },
  },
  actions: {
    setAuth({ state }, payload) {
      const user = payload?.user || null;
      const apiToken = payload?.apiToken || null;
      const csrfToken = payload?.csrfToken || null;
      const refreshToken = payload?.refreshToken || null;
      state.auth = {
        isLoggedIn: !!apiToken,
        user,
        apiToken,
        csrfToken,
        refreshToken,
      };
    },
    clearAuth({ state }) {
      state.auth = {
        isLoggedIn: false,
        user: null,
        apiToken: null,
        csrfToken: null,
        refreshToken: null,
      };
    },
    setWallet({ state }, payload) {
      state.wallet = {
        balance: Number(payload?.balance || 0),
        lastUpdated: payload?.lastUpdated || new Date().toISOString(),
      };
    },
  },
});

export default store;
