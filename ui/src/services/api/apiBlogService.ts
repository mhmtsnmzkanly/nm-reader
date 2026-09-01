import { api } from '../apiClient';
import { ApiResponse, BlogSummary } from '../../types/api';
import { IBlogService } from '../contracts';

export class ApiBlogService implements IBlogService {
  async getBlogs(
    page = 1,
    per_page = 10,
    sort: 'latest' | 'popular' = 'latest',
    tag?: string
  ): Promise<ApiResponse<BlogSummary[]>> {
    return api.get<BlogSummary[]>('/blogs', { page, per_page, sort, tag });
  }

  async getBlogBySlug(slug: string): Promise<ApiResponse<BlogSummary>> {
    return api.get<BlogSummary>(`/blogs/${slug}`);
  }

  async getRelatedBlogs(slug: string, limit = 4): Promise<ApiResponse<BlogSummary[]>> {
    return api.get<BlogSummary[]>(`/blogs/${slug}/related`, { limit });
  }

  async voteBlog(
    slug: string,
    vote: -1 | 0 | 1
  ): Promise<
    ApiResponse<{ vote: number; upvote_count: number; downvote_count: number; likes: number }>
  > {
    return api.post<{ vote: number; upvote_count: number; downvote_count: number; likes: number }>(
      `/blogs/${slug}/vote`,
      { vote }
    );
  }

  async uploadBlogImage(formData: FormData): Promise<ApiResponse<{ path: string; url: string }>> {
    return api.post<{ path: string; url: string }>('/blogs/image', formData);
  }

  async getUserBlogs(page = 1, per_page = 20): Promise<ApiResponse<BlogSummary[]>> {
    return api.get<BlogSummary[]>('/user/blogs', { page, per_page });
  }

  async getMyBlog(id: string): Promise<ApiResponse<BlogSummary>> {
    return api.get<BlogSummary>(`/user/blogs/${id}`);
  }

  async createBlog(
    title: string,
    body: string,
    tags?: string[],
    excerpt?: string,
    cover_image?: string,
    status?: 'draft' | 'pending'
  ): Promise<ApiResponse<BlogSummary>> {
    return api.post<BlogSummary>('/blogs', {
      title,
      body,
      tags,
      excerpt,
      cover_image,
      status,
    });
  }

  async updateBlog(
    id: string,
    payload: {
      title?: string;
      body?: string;
      tags?: string[];
      excerpt?: string;
      cover_image?: string;
      status?: 'draft' | 'pending';
    }
  ): Promise<ApiResponse<BlogSummary>> {
    return api.put<BlogSummary>(`/blogs/${id}`, payload);
  }

  async deleteBlog(id: string): Promise<ApiResponse<{ deleted: boolean }>> {
    return api.delete<{ deleted: boolean }>(`/blogs/${id}`);
  }
}
