import { api } from '../apiClient';
import {
  ApiResponse,
  ChapterUnlockRow,
  FeatureEntitlement,
  FeatureProduct,
  SeriesUnlockRow,
  ShopPackage,
  WalletData,
  WalletTransaction,
} from '../../types/api';
import { IWalletService } from '../contracts';

export class ApiWalletService implements IWalletService {
  async getWallet(): Promise<ApiResponse<WalletData>> {
    return api.get<WalletData>('/user/wallet');
  }

  async getTransactions(
    page = 1,
    per_page = 20,
    filter?: string,
    sort?: string,
    search?: string
  ): Promise<ApiResponse<WalletTransaction[]>> {
    return api.get<WalletTransaction[]>('/user/wallet/transactions', {
      page,
      per_page,
      filter,
      sort,
      search,
    });
  }

  async getSeriesUnlocks(page = 1, per_page = 20): Promise<ApiResponse<SeriesUnlockRow[]>> {
    return api.get<SeriesUnlockRow[]>('/user/unlocks/series', { page, per_page });
  }

  async getChapterUnlocks(page = 1, per_page = 20): Promise<ApiResponse<ChapterUnlockRow[]>> {
    return api.get<ChapterUnlockRow[]>('/user/unlocks/chapters', { page, per_page });
  }

  async getFeatureEntitlements(): Promise<ApiResponse<FeatureEntitlement[]>> {
    return api.get<FeatureEntitlement[]>('/user/features/entitlements');
  }

  async getShopPackages(): Promise<ApiResponse<ShopPackage[]>> {
    return api.get<ShopPackage[]>('/shop/packages');
  }

  async getShopFeatures(): Promise<ApiResponse<FeatureProduct[]>> {
    return api.get<FeatureProduct[]>('/shop/features');
  }

  async purchasePackage(
    packageId: number
  ): Promise<ApiResponse<{ purchased: boolean; balance: number; package: ShopPackage }>> {
    return api.post<{ purchased: boolean; balance: number; package: ShopPackage }>(
      `/shop/packages/${packageId}/purchase`
    );
  }

  async purchaseChapter(
    chapterId: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number; balance: number }>> {
    return api.post<{ unlocked: boolean; transaction_id: number; balance: number }>(
      `/chapter/${chapterId}/unlock`
    );
  }

  async purchaseAdFree(): Promise<ApiResponse<{ purchased: boolean; expires_at: string }>> {
    return api.post<{ purchased: boolean; expires_at: string }>('/user/features/ad-free/purchase');
  }
}
