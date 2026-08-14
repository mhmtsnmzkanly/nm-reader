import { apiClient } from '../client';
import type { ApiResponse } from '../types';
import type { Comment } from '../../types/api';
import type { ICommentService } from '../../services/contracts';

export class ApiCommentService implements ICommentService {
  public getComments(
    targetType: 'content' | 'chapter' | 'blog',
    idOrSlug: string,
    page = 1,
    cursor?: string
  ): Promise<ApiResponse<Comment[]>> {
    let endpoint = '';
    if (targetType === 'chapter') {
      endpoint = `/chapter/${idOrSlug}/comments`;
    } else if (targetType === 'blog') {
      endpoint = `/blogs/${idOrSlug}/comments`;
    } else {
      // Content default: supports manga/solo-leveling or direct slug
      endpoint = idOrSlug.includes('/') ? `/content/${idOrSlug}/comments` : `/content/manga/${idOrSlug}/comments`;
    }

    return apiClient.get<Comment[]>(endpoint, {
      params: { page, cursor },
    });
  }

  public postComment(
    targetType: 'content' | 'chapter' | 'blog',
    idOrSlug: string,
    body: string,
    parent_id?: number | null
  ): Promise<ApiResponse<{ comment_id: number }>> {
    let endpoint = '';
    if (targetType === 'chapter') {
      endpoint = `/chapter/${idOrSlug}/comment`;
    } else if (targetType === 'blog') {
      endpoint = `/blogs/${idOrSlug}/comments`;
    } else {
      endpoint = idOrSlug.includes('/') ? `/content/${idOrSlug}/comment` : `/content/manga/${idOrSlug}/comment`;
    }

    return apiClient.post<{ comment_id: number }>(endpoint, {
      body,
      parent_id: parent_id ?? null,
    });
  }

  public voteComment(
    commentId: number,
    vote: -1 | 0 | 1
  ): Promise<ApiResponse<{ comment_id: number; upvote_count: number; downvote_count: number; my_vote: number }>> {
    return apiClient.post<{ comment_id: number; upvote_count: number; downvote_count: number; my_vote: number }>(
      `/comments/${commentId}/vote`,
      { vote }
    );
  }
}

export const commentService = new ApiCommentService();
