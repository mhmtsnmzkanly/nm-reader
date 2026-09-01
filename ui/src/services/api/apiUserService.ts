import { api } from '../apiClient';
import {
  ApiResponse,
  ContentSummary,
  ContentType,
  FollowingUserItem,
  LibraryItem,
  NotificationItem,
  PublicProfileData,
  ReadingHistoryItem,
  UserPreferences,
  UserProfile,
} from '../../types/api';
import { IUserService } from '../contracts';

export class ApiUserService implements IUserService {
  async getProfile(): Promise<ApiResponse<UserProfile>> {
    return api.get<UserProfile>('/user/profile');
  }

  async updateProfile(data: Partial<UserProfile>): Promise<ApiResponse<UserProfile>> {
    return api.post<UserProfile>('/user/profile', data);
  }

  async getPublicProfile(person: string): Promise<ApiResponse<PublicProfileData>> {
    return api.get<PublicProfileData>(`/profile/${person}`);
  }

  async getPreferences(): Promise<ApiResponse<UserPreferences>> {
    return api.get<UserPreferences>('/user/preferences');
  }

  async updatePreferences(prefs: Partial<UserPreferences>): Promise<ApiResponse<UserPreferences>> {
    return api.put<UserPreferences>('/user/preferences', prefs);
  }

  async getLibrary(
    page = 1,
    per_page = 20,
    filter?: string,
    sort?: string
  ): Promise<ApiResponse<LibraryItem[]>> {
    return api.get<LibraryItem[]>('/user/library', { page, per_page, filter, sort });
  }

  async addToLibrary(
    contentIdOrSlug: string,
    type?: ContentType
  ): Promise<ApiResponse<{ added: boolean; item: LibraryItem }>> {
    return api.post<{ added: boolean; item: LibraryItem }>('/user/library', {
      content_id: contentIdOrSlug,
      type,
    });
  }

  async removeFromLibrary(idOrSlug: string): Promise<ApiResponse<{ removed: boolean }>> {
    return api.delete<{ removed: boolean }>(`/user/library/${idOrSlug}`);
  }

  async toggleLibrary(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ in_library: boolean }>> {
    return api.post<{ in_library: boolean }>(`/content/${type}/${slug}/library`);
  }

  async getFollows(page = 1, per_page = 20): Promise<ApiResponse<ContentSummary[]>> {
    return api.get<ContentSummary[]>('/user/follows', { page, per_page });
  }

  async getFollowingUsers(page = 1, per_page = 20): Promise<ApiResponse<FollowingUserItem[]>> {
    return api.get<FollowingUserItem[]>('/user/following-users', { page, per_page });
  }

  async getHistory(page = 1, per_page = 20): Promise<ApiResponse<ReadingHistoryItem[]>> {
    return api.get<ReadingHistoryItem[]>('/user/history', { page, per_page });
  }

  async recordHistory(data: {
    contentSlug: string;
    contentType?: ContentType;
    chapterId?: string;
    chapterNumber: number | string;
    chapterTitle?: string | null;
    page?: number;
    totalPages?: number;
    progress: number;
  }): Promise<ApiResponse<{ recorded: boolean }>> {
    return api.post<{ recorded: boolean }>('/user/history', data);
  }

  async removeFromHistory(id: string): Promise<ApiResponse<{ deleted: boolean }>> {
    return api.delete<{ deleted: boolean }>(`/user/history/${id}`);
  }

  async clearHistory(): Promise<ApiResponse<{ cleared: boolean }>> {
    return api.delete<{ cleared: boolean }>('/user/history');
  }

  async getNotifications(
    page = 1,
    cursor?: string
  ): Promise<ApiResponse<NotificationItem[]>> {
    return api.get<NotificationItem[]>('/user/notifications', { page, cursor });
  }

  async markNotificationsRead(id?: number | 'all'): Promise<ApiResponse<{ marked: boolean }>> {
    return api.post<{ marked: boolean }>('/user/notifications/read', { id });
  }

  async deleteNotification(id: number): Promise<ApiResponse<{ deleted: boolean }>> {
    return api.delete<{ deleted: boolean }>(`/user/notifications/${id}`);
  }

  async toggleFollowUser(
    username: string
  ): Promise<ApiResponse<{ is_following: boolean; followers_count: number }>> {
    return api.post<{ is_following: boolean; followers_count: number }>(
      `/profile/${username}/follow`
    );
  }
}
