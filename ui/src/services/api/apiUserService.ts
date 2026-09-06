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
    const response = await api.get<ContentSummary[]>('/user/follows', { page, per_page });
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

  async addToLibrary(
    contentIdOrSlug: string,
    type: ContentType = 'manga'
  ): Promise<ApiResponse<{ added: boolean; item?: LibraryItem }>> {
    const result = await api.post<{ followed: boolean }>(`/content/${type}/${contentIdOrSlug}/follow`);
    if (result.status !== 'success') return result as ApiResponse<{ added: boolean }>;
    return { ...result, data: { added: result.data.followed } };
  }

  async removeFromLibrary(idOrSlug: string, type?: ContentType): Promise<ApiResponse<{ removed: boolean }>> {
    const [encodedType, encodedSlug] = idOrSlug.includes(':') ? idOrSlug.split(':', 2) : [type, idOrSlug];
    if (!encodedType || !encodedSlug) {
      throw new Error('Content type and slug are required to remove a library item');
    }
    const result = await api.delete<{ followed: boolean }>(`/content/${encodedType}/${encodedSlug}/follow`);
    if (result.status !== 'success') return result as ApiResponse<{ removed: boolean }>;
    return { ...result, data: { removed: true } };
  }

  async toggleLibrary(
    type: ContentType,
    slug: string,
    currentlyInLibrary = false
  ): Promise<ApiResponse<{ in_library: boolean }>> {
    const endpoint = `/content/${type}/${slug}/follow`;
    const result = currentlyInLibrary
      ? await api.delete<{ followed: boolean }>(endpoint)
      : await api.post<{ followed: boolean }>(endpoint);
    if (result.status !== 'success') return result as ApiResponse<{ in_library: boolean }>;
    return { ...result, data: { in_library: result.data.followed } };
  }

  async getFollows(page = 1, per_page = 20): Promise<ApiResponse<ContentSummary[]>> {
    return api.get<ContentSummary[]>('/user/follows', { page, per_page });
  }

  async getFollowingUsers(page = 1, per_page = 20): Promise<ApiResponse<FollowingUserItem[]>> {
    return api.get<FollowingUserItem[]>('/user/follows/users', { page, per_page });
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
    username: string,
    currentlyFollowing: boolean
  ): Promise<ApiResponse<{ is_following: boolean; followers_count: number }>> {
    const endpoint = `/user/follows/${username}`;
    return currentlyFollowing
      ? api.delete<{ is_following: boolean; followers_count: number }>(endpoint)
      : api.post<{ is_following: boolean; followers_count: number }>(endpoint);
  }
}
