import { apiClient } from '../client';
import type { ApiResponse } from '../types';
import type {
  ChapterReader,
  ChapterSummary,
  ContentDetail,
  ContentSummary,
  ContentType,
  Genre,
  HomeData,
  SearchFilters,
  SearchSuggestItem,
  Tag,
} from '../../types/api';
import type { IContentService } from '../../services/contracts';

export class ApiContentService implements IContentService {
  public getHome(page = 1, per_page = 20): Promise<ApiResponse<HomeData>> {
    return apiClient.get<HomeData>('/home', {
      params: { page, per_page },
    });
  }

  public getContentByType(
    type: ContentType,
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    return apiClient.get<ContentSummary[]>(`/content/type/${type}`, {
      params: { page, per_page },
    });
  }

  public getContentDetail(type: ContentType, slug: string): Promise<ApiResponse<ContentDetail>> {
    return apiClient.get<ContentDetail>(`/content/${type}/${slug}`);
  }

  public getChapters(
    type: ContentType,
    slug: string,
    page = 1,
    per_page = 50
  ): Promise<ApiResponse<ChapterSummary[]>> {
    return apiClient.get<ChapterSummary[]>(`/content/${type}/${slug}/chapters`, {
      params: { page, per_page },
    });
  }

  public getChapterReader(
    type: ContentType,
    slug: string,
    chapterNumber: string
  ): Promise<ApiResponse<ChapterReader>> {
    return apiClient.get<ChapterReader>(`/content/${type}/${slug}/chapter/${chapterNumber}`);
  }

  public getGenres(page = 1, per_page = 50): Promise<ApiResponse<Genre[]>> {
    return apiClient.get<Genre[]>('/genres', {
      params: { page, per_page },
    });
  }

  public getGenreContents(
    slug: string,
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    return apiClient.get<ContentSummary[]>(`/genre/${slug}`, {
      params: { page, per_page },
    });
  }

  public getTags(page = 1, per_page = 50): Promise<ApiResponse<Tag[]>> {
    return apiClient.get<Tag[]>('/tags', {
      params: { page, per_page },
    });
  }

  public getTagContents(
    slug: string,
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    return apiClient.get<ContentSummary[]>(`/tag/${slug}`, {
      params: { page, per_page },
    });
  }

  public search(
    q: string,
    page = 1,
    per_page = 20,
    filters?: SearchFilters
  ): Promise<ApiResponse<ContentSummary[]>> {
    return apiClient.get<ContentSummary[]>('/search', {
      params: {
        q,
        page,
        per_page,
        status: filters?.status,
        sort: filters?.sort,
        genres: filters?.genres?.join(','),
        tags: filters?.tags?.join(','),
      },
    });
  }

  public searchSuggest(q: string): Promise<ApiResponse<SearchSuggestItem[]>> {
    return apiClient.get<SearchSuggestItem[]>('/search/suggest', {
      params: { q },
    });
  }

  public toggleFollow(type: ContentType, slug: string, currentlyFollowed = false): Promise<ApiResponse<{ followed: boolean }>> {
    const endpoint = `/content/${type}/${slug}/follow`;
    return currentlyFollowed
      ? apiClient.delete<{ followed: boolean }>(endpoint)
      : apiClient.post<{ followed: boolean }>(endpoint);
  }

  public rateContent(
    type: ContentType,
    slug: string,
    rating: number
  ): Promise<ApiResponse<{ rated: boolean }>> {
    return apiClient.post<{ rated: boolean }>(`/content/${type}/${slug}/rate`, {
      rating,
    });
  }

  public unlockSeries(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number }>> {
    return apiClient.post<{ unlocked: boolean; transaction_id: number }>(
      `/content/${type}/${slug}/unlock`
    );
  }

  public unlockChapter(
    chapterId: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number }>> {
    return apiClient.post<{ unlocked: boolean; transaction_id: number }>(
      `/chapter/${chapterId}/unlock`
    );
  }
}

export const contentService = new ApiContentService();
