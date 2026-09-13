import { apiClient } from '../client';
import type { ApiResponse } from '../types';
import type {
  ChapterUnlockRow,
  FeatureEntitlement,
  FeatureProduct,
  SeriesUnlockRow,
  ShopPackage,
  WalletData,
  WalletTransaction,
} from '../../types/api';
import type { IWalletService } from '../../services/contracts';

export class ApiWalletService implements IWalletService {
  public getWallet(): Promise<ApiResponse<WalletData>> {
    return apiClient.get<WalletData>('/user/wallet');
  }

  public getTransactions(
    page = 1,
    per_page = 20,
    filter?: string,
    sort?: string,
    search?: string
  ): Promise<ApiResponse<WalletTransaction[]>> {
    return apiClient.get<WalletTransaction[]>('/user/wallet/transactions', {
      params: { page, per_page, filter, sort, search },
    });
  }

  public getSeriesUnlocks(page = 1, per_page = 20): Promise<ApiResponse<SeriesUnlockRow[]>> {
    return apiClient.get<SeriesUnlockRow[]>('/user/unlocks/series', {
      params: { page, per_page },
    });
  }

  public getChapterUnlocks(page = 1, per_page = 20): Promise<ApiResponse<ChapterUnlockRow[]>> {
    return apiClient.get<ChapterUnlockRow[]>('/user/unlocks/chapters', {
      params: { page, per_page },
    });
  }

  public getFeatureEntitlements(): Promise<ApiResponse<FeatureEntitlement[]>> {
    return apiClient.get<FeatureEntitlement[]>('/user/features/entitlements');
  }

  public getShopPackages(): Promise<ApiResponse<ShopPackage[]>> {
    return apiClient.get<ShopPackage[]>('/shop/packages');
  }

  public getShopFeatures(): Promise<ApiResponse<FeatureProduct[]>> {
    return apiClient.get<FeatureProduct[]>('/shop/features');
  }

  public purchasePackage(
    packageId: number
  ): Promise<ApiResponse<{ purchased: boolean; balance: number; package: ShopPackage }>> {
    return apiClient.post<{ purchased: boolean; balance: number; package: ShopPackage }>(
      `/shop/packages/${packageId}/purchase`
    );
  }

  public purchaseChapter(
    chapterId: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number; balance: number }>> {
    return apiClient.post<{ unlocked: boolean; transaction_id: number; balance: number }>(
      `/chapter/${chapterId}/unlock`
    );
  }

  public purchaseAdFree(): Promise<ApiResponse<{ purchased: boolean; expires_at: string }>> {
    return apiClient.post<{ purchased: boolean; expires_at: string }>(
      '/user/features/ad-free/purchase'
    );
  }
}

export const walletService = new ApiWalletService();
