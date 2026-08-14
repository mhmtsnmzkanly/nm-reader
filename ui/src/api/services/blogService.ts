import { apiClient } from '../client';
import type { ApiResponse } from '../types';
import type { BlogSummary } from '../../types/api';
import type { IBlogService } from '../../services/contracts';

export class ApiBlogService implements IBlogService {
  public getBlogs(
    page = 1,
    per_page = 20,
    sort: 'latest' | 'popular' = 'latest',
    tag?: string
  ): Promise<ApiResponse<BlogSummary[]>> {
    return apiClient.get<BlogSummary[]>('/blogs', {
      params: { page, per_page, sort, tag },
    });
  }

  public getBlogBySlug(slug: string): Promise<ApiResponse<BlogSummary>> {
    return apiClient.get<BlogSummary>(`/blogs/${slug}`);
  }

  public getUserBlogs(page = 1, per_page = 20): Promise<ApiResponse<BlogSummary[]>> {
    return apiClient.get<BlogSummary[]>('/user/blogs', {
      params: { page, per_page },
    });
  }

  public createBlog(
    title: string,
    body: string,
    tags?: string[],
    excerpt?: string
  ): Promise<ApiResponse<BlogSummary>> {
    return apiClient.post<BlogSummary>('/blogs', {
      title,
      body,
      tags,
      excerpt,
    });
  }

  public uploadBlogImage(formData: FormData): Promise<ApiResponse<{ url: string }>> {
    return apiClient.post<{ url: string }>('/blogs/image', formData);
  }

  public voteBlog(
    slug: string,
    vote: -1 | 0 | 1
  ): Promise<ApiResponse<{ vote: number; upvote_count: number; downvote_count: number }>> {
    return apiClient.post<{ vote: number; upvote_count: number; downvote_count: number }>(
      `/blogs/${slug}/vote`,
      { vote }
    );
  }

  public toggleLikeBlog(slug: string): Promise<ApiResponse<{ liked: boolean; likes: number }>> {
    return apiClient.post<{ liked: boolean; likes: number }>(`/blogs/${slug}/vote`, {
      vote: 1,
    });
  }

  public getRelatedBlogs(slug: string, limit = 4): Promise<ApiResponse<BlogSummary[]>> {
    return apiClient.get<BlogSummary[]>('/blogs', {
      params: { related_to: slug, per_page: limit },
    });
  }
}

export const blogService = new ApiBlogService();
