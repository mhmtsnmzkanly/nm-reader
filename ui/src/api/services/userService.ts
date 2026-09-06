import { apiClient } from '../client';
import type { ApiResponse } from '../types';
import type {
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

  public async getLibrary(
    page = 1,
    per_page = 20,
    filter?: string,
    sort?: string
  ): Promise<ApiResponse<LibraryItem[]>> {
    const response = await apiClient.get<ContentSummary[]>('/user/follows', {
      params: { page, per_page },
    });
    if (response.status !== 'success') {
      return response as ApiResponse<LibraryItem[]>;
    }
    let contents = response.data;
    if (filter && filter !== 'all') {
      contents = contents.filter((content) => {
        if (filter === 'novel') return ['novel', 'light-novel', 'web-novel'].includes(content.type);
        if (filter === 'manhwa') return ['manhwa', 'webtoon'].includes(content.type);
        return content.type === filter;
      });
    }
    if (sort === 'title') contents = [...contents].sort((a, b) => a.title.localeCompare(b.title));
    if (sort === 'rating') contents = [...contents].sort((a, b) => (b.rating_avg ?? 0) - (a.rating_avg ?? 0));

    return {
      ...response,
      data: contents.map((content) => ({
        id: `${content.type}:${content.slug}`,
        content: {
          id: content.id,
          type: content.type,
          slug: content.slug,
          title: content.title,
          cover: content.cover_image ?? content.cover ?? '',
          status: content.status,
          rating: content.rating_avg,
          total_chapters: content.chapter_count,
        },
        added_at: content.created_at ?? new Date(0).toISOString(),
        user_state: { is_following: true },
      })),
    };
  }

  public async addToLibrary(
    contentIdOrSlug: string,
    type: ContentType = 'manga'
  ): Promise<ApiResponse<{ added: boolean; item?: LibraryItem }>> {
    const result = await apiClient.post<{ followed: boolean }>(`/content/${type}/${contentIdOrSlug}/follow`);
    if (result.status !== 'success') return result as ApiResponse<{ added: boolean }>;
    return { ...result, data: { added: result.data.followed } };
  }

  public async removeFromLibrary(
    idOrSlug: string,
    type: ContentType = 'manga'
  ): Promise<ApiResponse<{ removed: boolean }>> {
    const result = await apiClient.delete<{ followed: boolean }>(`/content/${type}/${idOrSlug}/follow`);
    if (result.status !== 'success') return result as ApiResponse<{ removed: boolean }>;
    return { ...result, data: { removed: result.data.followed === false } };
  }

  public async toggleLibrary(
    type: ContentType,
    slug: string,
    currentlyInLibrary = false
  ): Promise<ApiResponse<{ in_library: boolean }>> {
    const endpoint = `/content/${type}/${slug}/follow`;
    const response = currentlyInLibrary
      ? apiClient.delete<{ in_library: boolean }>(endpoint)
      : apiClient.post<{ in_library: boolean }>(endpoint);
    const result = await response;
    if (result.status !== 'success') {
      return result as ApiResponse<{ in_library: boolean }>;
    }
    const followed = (result.data as { followed?: boolean }).followed;
    return { ...result, data: { in_library: followed ?? !currentlyInLibrary } };
  }

  public getFollows(page = 1, per_page = 20): Promise<ApiResponse<ContentSummary[]>> {
    return apiClient.get<ContentSummary[]>('/user/follows', {
      params: { page, per_page },
    });
  }

  public getFollowingUsers(page = 1, per_page = 20): Promise<ApiResponse<FollowingUserItem[]>> {
    return apiClient.get<FollowingUserItem[]>('/user/follows/users', { params: { page, per_page } });
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
    return apiClient.post<{ recorded: boolean }>('/user/history', data);
  }

  public removeFromHistory(id: string): Promise<ApiResponse<{ deleted: boolean }>> {
    return apiClient.delete<{ deleted: boolean }>(`/user/history/${id}`);
  }

  public clearHistory(): Promise<ApiResponse<{ cleared: boolean }>> {
    return apiClient.delete<{ cleared: boolean }>('/user/history');
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
    return apiClient.delete<{ deleted: boolean }>(`/user/notifications/${id}`);
  }

  public toggleFollowUser(
    username: string,
    currentlyFollowing: boolean
  ): Promise<ApiResponse<{ is_following: boolean; followers_count: number }>> {
    const endpoint = `/user/follows/${username}`;
    return currentlyFollowing
      ? apiClient.delete<{ is_following: boolean; followers_count: number }>(endpoint)
      : apiClient.post<{ is_following: boolean; followers_count: number }>(endpoint);
  }
}

export const userService = new ApiUserService();
