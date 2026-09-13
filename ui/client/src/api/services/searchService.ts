import { apiClient } from '../client';
import type { ApiResponse } from '../types';
import type { ContentSummary, SearchFilters, SearchSuggestItem } from '../../types/api';

export class ApiSearchService {
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
}

export const searchService = new ApiSearchService();
