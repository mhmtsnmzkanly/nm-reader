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
    return api.get<Comment[]>('/comments', {
      target_type: targetType,
      target_id: idOrSlug,
      page,
      cursor,
    });
  }

  async postComment(
    targetType: 'content' | 'chapter' | 'blog',
    idOrSlug: string,
    body: string,
    parent_id?: number | null
  ): Promise<ApiResponse<{ comment_id: number }>> {
    return api.post<{ comment_id: number }>('/comments', {
      target_type: targetType,
      target_id: idOrSlug,
      body,
      parent_id,
    });
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
