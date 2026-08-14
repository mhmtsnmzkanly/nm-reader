import { apiClient } from '../client';
import type { ApiResponse } from '../types';
import type {
  ContentSummary,
  ContentType,
  LibraryItem,
  NotificationItem,
  PublicProfileData,
  ReadingHistoryItem,
  UserPreferences,
  UserProfile,
} from '../../types/api';
import type { IUserService } from '../../services/contracts';

export class ApiUserService implements IUserService {
  public getProfile(): Promise<ApiResponse<UserProfile>> {
    return apiClient.get<UserProfile>('/user/profile');
  }

  public updateProfile(data: Partial<UserProfile>): Promise<ApiResponse<UserProfile>> {
    return apiClient.post<UserProfile>('/user/profile', data);
  }

  public getPublicProfile(person: string): Promise<ApiResponse<PublicProfileData>> {
    return apiClient.get<PublicProfileData>(`/profile/${person}`);
  }

  public getPreferences(): Promise<ApiResponse<UserPreferences>> {
    return apiClient.get<UserPreferences>('/user/preferences');
  }

  public updatePreferences(prefs: Partial<UserPreferences>): Promise<ApiResponse<UserPreferences>> {
    return apiClient.put<UserPreferences>('/user/preferences', prefs);
  }

  public getLibrary(page = 1, per_page = 20): Promise<ApiResponse<LibraryItem[]>> {
    return apiClient.get<LibraryItem[]>('/user/history', {
      params: { page, per_page, type: 'library' },
    });
  }

  public addToLibrary(
    contentIdOrSlug: string,
    type: ContentType = 'manga'
  ): Promise<ApiResponse<{ added: boolean; item: LibraryItem }>> {
    return apiClient.post<{ added: boolean; item: LibraryItem }>(
      `/content/${type}/${contentIdOrSlug}/follow`
    );
  }

  public removeFromLibrary(
    idOrSlug: string
  ): Promise<ApiResponse<{ removed: boolean }>> {
    return apiClient.delete<{ removed: boolean }>(`/content/manga/${idOrSlug}/follow`);
  }

  public toggleLibrary(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ in_library: boolean }>> {
    return apiClient.post<{ in_library: boolean }>(`/content/${type}/${slug}/follow`);
  }

  public getFollows(page = 1, per_page = 20): Promise<ApiResponse<ContentSummary[]>> {
    return apiClient.get<ContentSummary[]>('/user/follows', {
      params: { page, per_page },
    });
  }

  public getHistory(page = 1, per_page = 20): Promise<ApiResponse<ReadingHistoryItem[]>> {
    return apiClient.get<ReadingHistoryItem[]>('/user/history', {
      params: { page, per_page },
    });
  }

  public recordHistory(data: {
    contentSlug: string;
    contentType?: ContentType;
    chapterId?: string;
    chapterNumber: number | string;
    chapterTitle?: string | null;
    page?: number;
    totalPages?: number;
    progress: number;
  }): Promise<ApiResponse<{ recorded: boolean }>> {
    return apiClient.post<{ recorded: boolean }>('/user/activity', {
      tab_id: `reader_${data.contentSlug}_${data.chapterNumber}`,
      duration: Math.max(1, Math.round(data.progress * 60)),
    });
  }

  public removeFromHistory(_id: string): Promise<ApiResponse<{ deleted: boolean }>> {
    return apiClient.post<{ deleted: boolean }>('/user/history/clear');
  }

  public clearHistory(): Promise<ApiResponse<{ cleared: boolean }>> {
    return apiClient.post<{ cleared: boolean }>('/user/history/clear');
  }

  public getNotifications(
    page = 1,
    cursor?: string
  ): Promise<ApiResponse<NotificationItem[]>> {
    return apiClient.get<NotificationItem[]>('/user/notifications', {
      params: { page, cursor },
    });
  }

  public markNotificationsRead(id?: number | 'all'): Promise<ApiResponse<{ marked: boolean }>> {
    return apiClient.post<{ marked: boolean }>('/user/notifications/read', {
      id: id ?? 'all',
    });
  }

  public deleteNotification(id: number): Promise<ApiResponse<{ deleted: boolean }>> {
    return apiClient.post<{ deleted: boolean }>('/user/notifications/read', { id });
  }

  public toggleFollowUser(
    username: string
  ): Promise<ApiResponse<{ is_following: boolean; followers_count: number }>> {
    return apiClient.post<{ is_following: boolean; followers_count: number }>(
      `/user/follows/${username}`
    );
  }
}

export const userService = new ApiUserService();
