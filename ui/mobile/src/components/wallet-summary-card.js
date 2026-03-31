import { formatCoins } from '../utils/format.js';

/**
 * Renders the wallet summary card used by the wallet and content screens.
 */
export function renderWalletSummaryCard(wallet) {
  return `
    <div class="card mobile-wallet-card">
      <div class="card-content card-content-padding">
        <div class="mobile-wallet-card__label">Wallet balance</div>
        <div class="mobile-wallet-card__value">${formatCoins(wallet.balance)}</div>
        <div class="mobile-wallet-card__meta">
          <span>Purchased: ${formatCoins(wallet.totalPurchased)}</span>
          <span>Spent: ${formatCoins(wallet.totalSpent)}</span>
        </div>
      </div>
    </div>
  `;
}
