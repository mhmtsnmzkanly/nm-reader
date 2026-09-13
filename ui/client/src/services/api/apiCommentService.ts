import { api } from '../apiClient';
import { ApiResponse, Comment } from '../../types/api';
import { ICommentService } from '../contracts';

export class ApiCommentService implements ICommentService {
  async getComments(
    targetType: 'content' | 'chapter' | 'blog',
    idOrSlug: string,
    page = 1,
    cursor?: string
  ): Promise<ApiResponse<Comment[]>> {
    let endpoint = `/comments`;
    if (targetType === 'chapter') {
      endpoint = `/chapter/${idOrSlug}/comments`;
    } else if (targetType === 'blog') {
      endpoint = `/blogs/${idOrSlug}/comments`;
    } else if (targetType === 'content') {
      const parts = idOrSlug.split('/');
      const type = parts.length > 1 ? parts[0] : 'manga';
      const slug = parts.length > 1 ? parts[1] : parts[0];
      endpoint = `/content/${type}/${slug}/comments`;
    }
    return api.get<Comment[]>(endpoint, { page, cursor });
  }

  async postComment(
    targetType: 'content' | 'chapter' | 'blog',
    idOrSlug: string,
    body: string,
    parent_id?: number | null
  ): Promise<ApiResponse<{ comment_id: number }>> {
    let endpoint = `/comments`;
    if (targetType === 'chapter') {
      endpoint = `/chapter/${idOrSlug}/comment`;
    } else if (targetType === 'blog') {
      endpoint = `/blogs/${idOrSlug}/comments`;
    } else if (targetType === 'content') {
      const parts = idOrSlug.split('/');
      const type = parts.length > 1 ? parts[0] : 'manga';
      const slug = parts.length > 1 ? parts[1] : parts[0];
      endpoint = `/content/${type}/${slug}/comment`;
    }
    return api.post<{ comment_id: number }>(endpoint, { body, parent_id });
  }

  async voteComment(
    commentId: number,
    vote: -1 | 0 | 1
  ): Promise<
    ApiResponse<{
      comment_id: number;
      upvote_count: number;
      downvote_count: number;
      my_vote: number;
    }>
  > {
    return api.post<{
      comment_id: number;
      upvote_count: number;
      downvote_count: number;
      my_vote: number;
    }>(`/comments/${commentId}/vote`, { vote });
  }
}
