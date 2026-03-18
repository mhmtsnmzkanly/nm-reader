import { Template7 } from 'framework7';
import api from './api.js';

const STORAGE_KEY = 'nmr_mobile_lang_v1';
const fallback = {
  login_title: 'Login',
  register_title: 'Register',
  login: 'Login',
  register: 'Create account',
  email: 'Email',
  password: 'Password',
  username: 'Username',
  remember_me: 'Remember me',
  login_success: 'Login successful',
  login_failed: 'Login failed',
  register_success: 'Account created. You are now logged in.',
  register_failed: 'Register failed',
  logout_success: 'Logged out',
  wallet: 'Wallet',
  transactions: 'Transactions',
  coin_packages: 'Coin Packages',
  features: 'Features',
  unlock_confirm_title: 'Confirm Purchase',
  unlock_confirm_body: 'Unlock this chapter for {price} coins?',
  login_required: 'Login required.',
  purchase_title: 'Unlock Chapter',
  purchase_confirm: 'Unlock Now',
  purchase_cancel: 'Cancel',
  language: 'Language',
  loading: 'Loading...',
  latest_chapters: 'Latest Chapters',
  recently_added: 'Recently Added',
  no_chapters: 'No chapters yet.',
  no_recent: 'No recent series.',
  chapters: 'Chapters',
  free: 'Free',
  locked: 'Locked',
  coins: 'coins',
  load_more: 'Load more',
  payments_soon: 'Payments coming soon. Packages are informational.',
  features_info: 'Payments coming soon. Features are informational.',
  unlock: 'Unlock',
  reader_locked: 'This chapter is locked.',
  chapter_not_available: 'Chapter not available.',
  failed_load_content: 'Failed to load content.',
  failed_load_list: 'Failed to load list.',
  failed_load_chapter: 'Failed to load chapter.',
  failed_load_wallet: 'Failed to load wallet.',
  failed_load_transactions: 'Failed to load transactions.',
  failed_load_packages: 'Failed to load packages.',
  failed_load_features: 'Failed to load features.',
};

const i18n = {
  lang: 'en',
  dictionary: { ...fallback },
};

function resolveLang() {
  if (typeof localStorage !== 'undefined') {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) return stored;
  }
  if (typeof navigator !== 'undefined') {
    const candidate = (navigator.language || 'en').split('-')[0];
    return candidate || 'en';
  }
  return 'en';
}

function applyGlobals() {
  Template7.global = Template7.global || {};
  Template7.global.i18n = i18n.dictionary;
  Template7.global.lang = i18n.lang;
  if (typeof document !== 'undefined') {
    document.documentElement.lang = i18n.lang;
  }
}

async function loadI18n(lang) {
  i18n.lang = lang;
  try {
    const payload = await api.system.getI18n(lang);
    const data = payload?.data || payload || {};
    i18n.dictionary = { ...fallback, ...data };
  } catch (err) {
    i18n.dictionary = { ...fallback };
  }
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(STORAGE_KEY, lang);
  }
  applyGlobals();
  if (typeof document !== 'undefined') {
    document.dispatchEvent(new CustomEvent('i18n:updated', { detail: { lang } }));
  }
  return i18n.dictionary;
}

async function initI18n() {
  const lang = resolveLang();
  await loadI18n(lang);
}

export { initI18n, loadI18n };
