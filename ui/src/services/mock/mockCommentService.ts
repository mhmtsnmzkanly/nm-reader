import { ICommentService } from '../contracts';
import { ApiResponse, ApiSuccess, ApiError, Comment } from '../../types/api';
import { mockComments } from '../../mocks/fixtures';
import { scenarioManager } from '../../mocks/scenarios';

function makeSuccess<T>(data: T, meta: Record<string, unknown> = {}): ApiSuccess<T> {
  return { status: 'success', data, meta, error: null };
}

function makeError(code: number, key: string, message: string): ApiError {
  return {
    status: 'error',
    data: null,
    meta: {},
    error: { code, key, message, params: {} },
  };
}

const delay = (ms = 150) => new Promise((res) => setTimeout(res, ms));

export class MockCommentService implements ICommentService {
  async getComments(
    targetType: 'content' | 'chapter' | 'blog',
    idOrSlug: string,
    page = 1,
    cursor?: string
  ): Promise<ApiResponse<Comment[]>> {
    await delay();
    return makeSuccess(mockComments, { page, per_page: 20 });
  }

  async postComment(
    targetType: 'content' | 'chapter' | 'blog',
    idOrSlug: string,
    body: string,
    parent_id?: number | null
  ): Promise<ApiResponse<{ comment_id: number }>> {
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }
    if (sc === 'forbidden_commenting') {
      return makeError(403, 'FORBIDDEN', 'Your commenting privileges have been suspended');
    }
    if (!body || !body.trim()) {
      return makeError(400, 'BAD_REQUEST', 'Comment body cannot be empty');
    }

    const newId = Date.now();
    const comment: Comment = {
      id: newId,
      body,
      parent_id: parent_id || null,
      upvote_count: 0,
      downvote_count: 0,
      created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
      user_id: 'u8k2m4qz',
      username: 'deniz',
      profile_image: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop&q=80',
      my_vote: 0,
    };

    mockComments.push(comment);
    return makeSuccess({ comment_id: newId });
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
    await delay();
    const sc = scenarioManager.getScenario();
    if (sc === 'normal_guest') {
      return makeError(401, 'UNAUTHORIZED', 'Authentication required');
    }

    const target = mockComments.find((c) => c.id === commentId);
    if (!target) {
      return makeError(404, 'NOT_FOUND', 'Comment not found');
    }

    target.my_vote = vote;
    if (vote === 1) target.upvote_count += 1;
    if (vote === -1) target.downvote_count += 1;

    return makeSuccess({
      comment_id: commentId,
      upvote_count: target.upvote_count,
      downvote_count: target.downvote_count,
      my_vote: vote,
    });
  }
}
