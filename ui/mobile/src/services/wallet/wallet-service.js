import { request } from '../http/client.js';
import { normalizeWalletSummary } from '../../utils/normalize.js';
import { debugError, debugTrace } from '../../utils/debug.js';

/**
 * Loads the wallet summary for the authenticated user.
 */
export async function fetchWalletSummary() {
  debugTrace({
    scope: 'walletService',
    action: 'fetchWalletSummary:start',
    caller: 'walletService.fetchWalletSummary',
    callee: 'httpClient.request',
    next: 'normalize-wallet',
  });
  try {
    const response = await request('/user/wallet');
    const wallet = normalizeWalletSummary(response.data || {});
    debugTrace({
      scope: 'walletService',
      action: 'fetchWalletSummary:success',
      caller: 'walletService.fetchWalletSummary',
      callee: 'normalizeWalletSummary',
      next: 'render-wallet-summary',
      detail: { balance: wallet.balance },
    });
    return wallet;
  } catch (error) {
    debugError({
      scope: 'walletService',
      action: 'fetchWalletSummary:error',
      caller: 'walletService.fetchWalletSummary',
      callee: 'httpClient.request',
      next: 'surface-wallet-error',
      detail: { message: error.message },
    });
    throw error;
  }
}

/**
 * Loads paginated wallet transactions.
 */
export async function fetchWalletTransactions(page = 1, perPage = 20) {
  debugTrace({
    scope: 'walletService',
    action: 'fetchWalletTransactions:start',
    caller: 'walletService.fetchWalletTransactions',
    callee: 'httpClient.request',
    next: 'return-wallet-transactions',
    detail: { page, perPage },
  });
  try {
    const response = await request(`/user/wallet/transactions?page=${page}&per_page=${perPage}`);
    const items = Array.isArray(response.data) ? response.data : [];
    debugTrace({
      scope: 'walletService',
      action: 'fetchWalletTransactions:success',
      caller: 'walletService.fetchWalletTransactions',
      callee: 'httpClient.request',
      next: 'render-wallet-transactions',
      detail: { count: items.length },
    });
    return {
      items,
      meta: response.meta,
    };
  } catch (error) {
    debugError({
      scope: 'walletService',
      action: 'fetchWalletTransactions:error',
      caller: 'walletService.fetchWalletTransactions',
      callee: 'httpClient.request',
      next: 'surface-wallet-transactions-error',
      detail: { page, perPage, message: error.message },
    });
    throw error;
  }
}

/**
 * Loads informational shop packages shown in the wallet/shop flow.
 */
export async function fetchShopPackages() {
  debugTrace({
    scope: 'walletService',
    action: 'fetchShopPackages:start',
    caller: 'walletService.fetchShopPackages',
    callee: 'httpClient.request',
    next: 'return-shop-packages',
  });
  const response = await request('/shop/packages');
  return Array.isArray(response.data) ? response.data : [];
}

/**
 * Loads informational feature products shown in the wallet/shop flow.
 */
export async function fetchShopFeatures() {
  debugTrace({
    scope: 'walletService',
    action: 'fetchShopFeatures:start',
    caller: 'walletService.fetchShopFeatures',
    callee: 'httpClient.request',
    next: 'return-shop-features',
  });
  const response = await request('/shop/features');
  return Array.isArray(response.data) ? response.data : [];
}

/**
 * Unlocks a single chapter and returns the backend purchase payload.
 */
export async function unlockChapter(chapterId) {
  debugTrace({
    scope: 'walletService',
    action: 'unlockChapter:start',
    caller: 'walletService.unlockChapter',
    callee: 'httpClient.request',
    next: 'reload-reader-after-unlock',
    detail: { chapterId },
  });
  try {
    const response = await request(`/chapter/${chapterId}/unlock`, {
      method: 'POST',
    });
    debugTrace({
      scope: 'walletService',
      action: 'unlockChapter:success',
      caller: 'walletService.unlockChapter',
      callee: 'httpClient.request',
      next: 'return-unlock-payload',
      detail: { chapterId },
    });
    return response.data || {};
  } catch (error) {
    debugError({
      scope: 'walletService',
      action: 'unlockChapter:error',
      caller: 'walletService.unlockChapter',
      callee: 'httpClient.request',
      next: 'surface-unlock-error',
      detail: { chapterId, message: error.message },
    });
    throw error;
  }
}
