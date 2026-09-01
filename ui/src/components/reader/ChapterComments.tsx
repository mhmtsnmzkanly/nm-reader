import React, { useEffect, useState, useCallback } from 'react';
import { MessageSquare, Loader2, Sparkles } from 'lucide-react';
import { commentService } from '../../services';
import { Comment } from '../../types/api';
import { CommentThread } from '../comments/CommentThread';
import { usePreferences } from '../../contexts/PreferencesContext';

interface ChapterCommentsProps {
  chapterId: string;
  chapterNumber?: number | string;
  seriesSlug?: string;
}

export const ChapterComments: React.FC<ChapterCommentsProps> = ({
  chapterId,
  chapterNumber,
}) => {
  const { t } = usePreferences();
  const [comments, setComments] = useState<Comment[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);

  const loadComments = useCallback(async () => {
    setIsLoading(true);
    const res = await commentService.getComments('chapter', chapterId);
    if (res.status === 'success' && res.data) {
      setComments(res.data);
    }
    setIsLoading(false);
  }, [chapterId]);

  useEffect(() => {
    loadComments();
  }, [loadComments]);

  const handleAddComment = async (
    content: string,
    isSpoiler: boolean,
    parentId?: number | null
  ) => {
    const res = await commentService.postComment('chapter', chapterId, content, parentId);
    if (res.status === 'success') {
      await loadComments();
    }
  };

  const handleVote = async (commentId: number, direction: 'up' | 'down') => {
    // Optimistic update
    setComments((prev) =>
      prev.map((c) => {
        if (c.id === commentId) {
          return {
            ...c,
            upvote_count: direction === 'up' ? (c.upvote_count || 0) + 1 : c.upvote_count,
            downvote_count: direction === 'down' ? (c.downvote_count || 0) + 1 : c.downvote_count,
          };
        }
        return c;
      })
    );

    await commentService.voteComment(commentId, direction === 'up' ? 1 : -1);
  };

  return (
    <section className="max-w-4xl mx-auto w-full mt-10 pt-8 border-t border-[var(--border-color)] flex flex-col gap-6 px-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] flex items-center justify-center">
            <MessageSquare className="w-5 h-5" />
          </div>
          <div>
            <h3 className="font-serif font-bold text-lg text-[var(--text-primary)]">
              {t('comments.title')}
            </h3>
            {chapterNumber && (
              <span className="text-xs font-mono text-[var(--text-muted)]">
                {t('chapters.chapterNumber', { number: chapterNumber })} • {comments.length} {t('comments.title').toLowerCase()}
              </span>
            )}
          </div>
        </div>

        <span className="text-xs font-mono text-[var(--text-muted)] bg-[var(--bg-tertiary)] px-3 py-1 rounded-full border border-[var(--border-color)]">
          {comments.length} {t('common.commentsCount', { count: comments.length })}
        </span>
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="flex flex-col items-center justify-center p-12 gap-3">
          <Loader2 className="w-6 h-6 text-[var(--accent-color)] animate-spin" />
          <span className="text-xs font-mono text-[var(--text-muted)]">
            {t('common.loading')}
          </span>
        </div>
      ) : (
        <CommentThread
          comments={comments}
          onAddComment={handleAddComment}
          onVote={handleVote}
        />
      )}
    </section>
  );
};
