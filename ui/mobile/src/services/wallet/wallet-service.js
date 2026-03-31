import { request } from '../http/client.js';
import { normalizeWalletSummary } from '../../utils/normalize.js';

/**
 * Loads the wallet summary for the authenticated user.
 */
export async function fetchWalletSummary() {
  const response = await request('/user/wallet');
  return normalizeWalletSummary(response.data || {});
}

/**
 * Loads paginated wallet transactions.
 */
export async function fetchWalletTransactions(page = 1, perPage = 20) {
  const response = await request(`/user/wallet/transactions?page=${page}&per_page=${perPage}`);
  return {
    items: Array.isArray(response.data) ? response.data : [],
    meta: response.meta,
  };
}

/**
 * Loads informational shop packages shown in the wallet/shop flow.
 */
export async function fetchShopPackages() {
  const response = await request('/shop/packages');
  return Array.isArray(response.data) ? response.data : [];
}

/**
 * Loads informational feature products shown in the wallet/shop flow.
 */
export async function fetchShopFeatures() {
  const response = await request('/shop/features');
  return Array.isArray(response.data) ? response.data : [];
}

/**
 * Unlocks a single chapter and returns the backend purchase payload.
 */
export async function unlockChapter(chapterId) {
  const response = await request(`/chapter/${chapterId}/unlock`, {
    method: 'POST',
  });
  return response.data || {};
}
