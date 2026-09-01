import { api } from '../apiClient';
import {
  ApiResponse,
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
import { IContentService } from '../contracts';

export class ApiContentService implements IContentService {
  async getHome(page = 1, per_page = 20): Promise<ApiResponse<HomeData>> {
    return api.get<HomeData>('/home', { page, per_page });
  }

  async getContentByType(
    type: ContentType,
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    return api.get<ContentSummary[]>(`/${type}`, { page, per_page });
  }

  async getContentDetail(type: ContentType, slug: string): Promise<ApiResponse<ContentDetail>> {
    return api.get<ContentDetail>(`/content/${type}/${slug}`);
  }

  async getChapters(
    type: ContentType,
    slug: string,
    page = 1,
    per_page = 50
  ): Promise<ApiResponse<ChapterSummary[]>> {
    return api.get<ChapterSummary[]>(`/content/${type}/${slug}/chapters`, { page, per_page });
  }

  async getChapterReader(
    type: ContentType,
    slug: string,
    chapterNumber: string
  ): Promise<ApiResponse<ChapterReader>> {
    return api.get<ChapterReader>(`/content/${type}/${slug}/chapter/${chapterNumber}`);
  }

  async getGenres(page = 1, per_page = 50): Promise<ApiResponse<Genre[]>> {
    return api.get<Genre[]>('/genres', { page, per_page });
  }

  async getGenreContents(
    slug: string,
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    return api.get<ContentSummary[]>(`/genre/${slug}`, { page, per_page });
  }

  async getTags(page = 1, per_page = 50): Promise<ApiResponse<Tag[]>> {
    return api.get<Tag[]>('/tags', { page, per_page });
  }

  async getTagContents(
    slug: string,
    page = 1,
    per_page = 20
  ): Promise<ApiResponse<ContentSummary[]>> {
    return api.get<ContentSummary[]>(`/tag/${slug}`, { page, per_page });
  }

  async search(
    q: string,
    page = 1,
    per_page = 20,
    filters?: SearchFilters
  ): Promise<ApiResponse<ContentSummary[]>> {
    return api.get<ContentSummary[]>('/search', { q, page, per_page, ...(filters || {}) });
  }

  async searchSuggest(q: string): Promise<ApiResponse<SearchSuggestItem[]>> {
    return api.get<SearchSuggestItem[]>('/search/suggest', { q });
  }

  async toggleFollow(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ followed: boolean }>> {
    return api.post<{ followed: boolean }>(`/content/${type}/${slug}/follow`);
  }

  async rateContent(
    type: ContentType,
    slug: string,
    rating: number
  ): Promise<ApiResponse<{ rated: boolean }>> {
    return api.post<{ rated: boolean }>(`/content/${type}/${slug}/rate`, { rating });
  }

  async unlockSeries(
    type: ContentType,
    slug: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number }>> {
    return api.post<{ unlocked: boolean; transaction_id: number }>(
      `/content/${type}/${slug}/unlock`
    );
  }

  async unlockChapter(
    chapterId: string
  ): Promise<ApiResponse<{ unlocked: boolean; transaction_id: number }>> {
    return api.post<{ unlocked: boolean; transaction_id: number }>(`/chapter/${chapterId}/unlock`);
  }
}
