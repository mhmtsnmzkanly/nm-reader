import { apiClient } from '../client';
import type { ApiResponse } from '../types';
import type { ContentSummary, Genre, Tag } from '../../types/api';

export class ApiTaxonomyService {
  public getGenres(page = 1, per_page = 50): Promise<ApiResponse<Genre[]>> {
    return apiClient.get<Genre[]>('/genres', { params: { page, per_page } });
  }

  public getGenreContents(slug: string, page = 1, per_page = 20): Promise<ApiResponse<ContentSummary[]>> {
    return apiClient.get<ContentSummary[]>(`/genre/${slug}`, { params: { page, per_page } });
  }

  public getTags(page = 1, per_page = 50): Promise<ApiResponse<Tag[]>> {
    return apiClient.get<Tag[]>('/tags', { params: { page, per_page } });
  }

  public getTagContents(slug: string, page = 1, per_page = 20): Promise<ApiResponse<ContentSummary[]>> {
    return apiClient.get<ContentSummary[]>(`/tag/${slug}`, { params: { page, per_page } });
  }

  public getSeriesGenres(): Promise<ApiResponse<Genre[]>> {
    return apiClient.get<Genre[]>('/series_genres');
  }

  public getSeriesTags(): Promise<ApiResponse<Tag[]>> {
    return apiClient.get<Tag[]>('/series_tags');
  }
}

export const taxonomyService = new ApiTaxonomyService();
