import { IWalletService } from '../contracts';
import {
  ApiResponse,
  ApiSuccess,
  ApiError,
  WalletData,
  WalletTransaction,
  SeriesUnlockRow,
  ChapterUnlockRow,
  FeatureEntitlement,
  ShopPackage,
  FeatureProduct,
} from '../../types/api';
import {
  mockWalletData,
  mockTransactions,
  mockSeriesUnlocks,
  mockChapterUnlocks,
  mockEntitlements,
  mockShopPackages,
  mockFeatureProducts,
} from '../../mocks/fixtures';
import { scenarioManager } from '../../mocks/scenarios';

function makeSuccess<T>(data: T, meta: Record<string, unknown> = {}): ApiSuccess<T> {
  return { status: 'success', data, meta, error: null };
}

function makeError(code: number, key: string, message: string): ApiError {
  return {
    status: 'error',
    data: null,
    meta: {},
    error: { code, key, message, params: {} },
  };
}

const delay = (ms = 150) => new Promise((res) => setTimeout(res, ms));

export class MockWalletService implements IWalletService {
  async getWallet(): Promise<ApiResponse<WalletData>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    return makeSuccess(mockWalletData);
  }

  async getTransactions(
    page = 1,
    per_page = 20,
    filter = 'ALL',
    sort = 'newest',
    search = ''
  ): Promise<ApiResponse<WalletTransaction[]>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    let items = [...mockTransactions];

    // Filter by type
    if (filter && filter !== 'ALL') {
      const f = filter.toUpperCase();
      if (f === 'CREDIT') {
        items = items.filter(
          (t) =>
            t.coin_delta > 0 ||
            t.type === 'package_credit' ||
            t.type === 'manual_credit' ||
            t.type === 'CREDIT'
        );
      } else if (f === 'PURCHASE') {
        items = items.filter(
          (t) =>
            t.coin_delta < 0 ||
            t.type === 'chapter_unlock' ||
            t.type === 'series_unlock' ||
            t.type === 'feature_unlock' ||
            t.type === 'manual_debit' ||
            t.type === 'PURCHASE'
        );
      } else if (f === 'REFUND') {
        items = items.filter(
          (t) => t.type === 'refund' || t.type === 'REFUND'
        );
      }
    }

    // Search query
    if (search && search.trim()) {
      const q = search.toLowerCase().trim();
      items = items.filter((t) => {
        const descMatch = (t.description || '').toLowerCase().includes(q);
        const refMatch = (t.reference_id || '').toLowerCase().includes(q);
        const typeMatch = (t.type || '').toLowerCase().includes(q);
        return descMatch || refMatch || typeMatch;
      });
    }

    // Sorting
    if (sort === 'newest') {
      items.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
    } else if (sort === 'oldest') {
      items.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
    } else if (sort === 'amount_desc') {
      items.sort((a, b) => Math.abs(b.coin_delta) - Math.abs(a.coin_delta));
    } else if (sort === 'amount_asc') {
      items.sort((a, b) => Math.abs(a.coin_delta) - Math.abs(b.coin_delta));
    }

    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = items.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = items.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async purchasePackage(
    packageId: number
  ): Promise<ApiResponse<{ purchased: boolean; balance: number; package: ShopPackage }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const pkg = mockShopPackages.find((p) => p.id === packageId);
    if (!pkg) {
      return makeError(404, 'NOT_FOUND', 'Package not found');
    }

    const totalCoins = pkg.coin_amount + pkg.bonus_coin;
    mockWalletData.balance_coin += totalCoins;
    mockWalletData.balance = mockWalletData.balance_coin;
    mockWalletData.total_coin_purchased += totalCoins;
    mockWalletData.updated_at = new Date().toISOString().replace('T', ' ').substring(0, 19);

    const txId = Date.now();
    mockTransactions.unshift({
      id: txId,
      type: 'package_credit',
      coin_delta: totalCoins,
      amount: totalCoins,
      balance_after: mockWalletData.balance_coin,
      reference_type: 'package',
      reference_id: String(pkg.id),
      reference: {
        type: 'package',
        id: String(pkg.id),
      },
      description: `${pkg.name} Yüklemesi (+${pkg.coin_amount} Coin${pkg.bonus_coin > 0 ? ` + ${pkg.bonus_coin} Bonus` : ''})`,
      metadata: JSON.stringify({ package_id: pkg.id, price: pkg.display_price, currency: pkg.currency }),
      created_by: null,
      created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
    });

    return makeSuccess({ purchased: true, balance: mockWalletData.balance_coin, package: pkg });
  }

  async purchaseChapter(
    chapterId: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number; balance: number }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    // Check if already unlocked in mockChapterUnlocks
    const existing = mockChapterUnlocks.find((u) => u.chapter_id === chapterId);
    if (existing) {
      return makeSuccess({ unlocked: true, transaction_id: existing.transaction_id, balance: mockWalletData.balance_coin });
    }

    // Find chapter in fixtures
    const { mockChapters } = await import('../../mocks/fixtures');
    let targetChapter: any = null;
    let targetSlug = '';

    for (const [s, list] of Object.entries(mockChapters)) {
      const found = list.find((c) => c.id === chapterId);
      if (found) {
        targetChapter = found;
        targetSlug = s;
        break;
      }
    }

    const price = targetChapter ? targetChapter.price_coin || 10 : 10;

    if (sc === 'insufficient_coins' || mockWalletData.balance_coin < price) {
      return makeError(402, 'PAYMENT_REQUIRED', `Yetersiz bakiye. Bu işlem için ${price} Coin gereklidir.`);
    }

    mockWalletData.balance_coin -= price;
    mockWalletData.balance = mockWalletData.balance_coin;
    mockWalletData.total_coin_spent += price;
    mockWalletData.updated_at = new Date().toISOString().replace('T', ' ').substring(0, 19);

    const txId = Date.now();
    mockTransactions.unshift({
      id: txId,
      type: 'chapter_unlock',
      coin_delta: -price,
      amount: -price,
      balance_after: mockWalletData.balance_coin,
      reference_type: 'chapter',
      reference_id: chapterId,
      reference: {
        type: 'chapter',
        id: chapterId,
      },
      description: targetChapter
        ? `${targetSlug.replace(/-/g, ' ')} — Bölüm #${targetChapter.chapter_number} Kilidi Açıldı`
        : `Bölüm Kilidi Açıldı (${chapterId})`,
      metadata: JSON.stringify({ content_slug: targetSlug, chapter_id: chapterId }),
      created_by: null,
      created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
    });

    if (targetChapter) {
      targetChapter.is_locked = false;
      if (targetChapter.access) {
        targetChapter.access.granted = true;
        targetChapter.access.reason = 'chapter_unlocked';
        targetChapter.access.is_chapter_unlocked = true;
      }
      if (targetChapter.type === 'image' && (!targetChapter.pages || targetChapter.pages.length === 0)) {
        targetChapter.pages = [
          { image_path: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=1000&auto=format&fit=crop&q=80', page_order: 1 },
          { image_path: 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?w=1000&auto=format&fit=crop&q=80', page_order: 2 },
        ];
      }
    }

    mockChapterUnlocks.unshift({
      id: Date.now(),
      content_id: targetChapter?.content_id || 'a1b2c3',
      chapter_id: chapterId,
      chapter_number: targetChapter?.chapter_number || '1',
      chapter_title: targetChapter?.title || null,
      price_coin: price,
      transaction_id: txId,
      unlocked_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
    });

    return makeSuccess({ unlocked: true, transaction_id: txId, balance: mockWalletData.balance_coin });
  }

  async getSeriesUnlocks(
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<SeriesUnlockRow[]>> {
    await delay();
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = mockSeriesUnlocks.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = mockSeriesUnlocks.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getChapterUnlocks(
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ChapterUnlockRow[]>> {
    await delay();
    const validPage = Math.max(1, page);
    const validPerPage = Math.min(50, Math.max(1, per_page));
    const total = mockChapterUnlocks.length;
    const total_pages = Math.ceil(total / validPerPage) || 1;
    const start = (validPage - 1) * validPerPage;
    const paginated = mockChapterUnlocks.slice(start, start + validPerPage);

    return makeSuccess(paginated, { page: validPage, per_page: validPerPage, total, total_pages });
  }

  async getFeatureEntitlements(): Promise<ApiResponse<FeatureEntitlement[]>> {
    await delay();
    return makeSuccess(mockEntitlements, { total: mockEntitlements.length });
  }

  async getShopPackages(): Promise<ApiResponse<ShopPackage[]>> {
    await delay();
    return makeSuccess(mockShopPackages, { page: 1, per_page: 20 });
  }

  async getShopFeatures(): Promise<ApiResponse<FeatureProduct[]>> {
    await delay();
    return makeSuccess(mockFeatureProducts);
  }

  async purchaseAdFree(): Promise<ApiResponse<{ purchased: boolean; expires_at: string }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    const price = 45;
    if (sc === 'insufficient_coins' || mockWalletData.balance_coin < price) {
      return makeError(402, 'PAYMENT_REQUIRED', 'Insufficient coin balance');
    }

    mockWalletData.balance_coin -= price;
    mockWalletData.total_coin_spent += price;

    const txId = Date.now();
    const expires = new Date(Date.now() + 30 * 86400 * 1000)
      .toISOString()
      .replace('T', ' ')
      .substring(0, 19);

    mockTransactions.unshift({
      id: txId,
      type: 'feature_unlock',
      coin_delta: -price,
      balance_after: mockWalletData.balance_coin,
      reference_type: 'feature',
      reference_id: 'ad_free',
      description: 'Activated 30-day Ad-Free Experience',
      metadata: null,
      created_by: null,
      created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
    });

    mockEntitlements.unshift({
      id: Date.now(),
      feature_key: 'ad_free',
      source_type: 'shop',
      source_id: 'ad_free',
      transaction_id: txId,
      starts_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
      expires_at: expires,
      created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
    });

    return makeSuccess({ purchased: true, expires_at: expires });
  }
}
