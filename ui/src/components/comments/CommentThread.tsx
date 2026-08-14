import React, { useState } from 'react';
import { ThumbsUp, ThumbsDown, MessageSquare } from 'lucide-react';
import { Comment } from '../../types/api';
import { CommentComposer } from './CommentComposer';
import { usePreferences } from '../../contexts/PreferencesContext';

type CommentThreadProps = {
  comments: Comment[];
  onAddComment: (content: string, isSpoiler: boolean, parentId?: number | null) => Promise<void>;
  onVote: (commentId: number, direction: 'up' | 'down') => void;
};

export const CommentThread: React.FC<CommentThreadProps> = ({
  comments,
  onAddComment,
  onVote,
}) => {
  const { formatRelativeTime, t } = usePreferences();
  const [replyingTo, setReplyingTo] = useState<number | null>(null);

  const rootComments = comments.filter((c) => !c.parent_id);
  const getReplies = (parentId: number) => comments.filter((c) => c.parent_id === parentId);

  const renderCommentItem = (comment: Comment, isChild = false) => {
    const replies = getReplies(comment.id);

    return (
      <div
        key={comment.id}
        className={`flex flex-col gap-2 p-3 sm:p-4 bg-[var(--bg-card)] rounded-xl border border-[var(--border-color)] shadow-sm min-w-0 ${
          isChild ? 'ml-2.5 sm:ml-8 border-l-2 border-l-[var(--accent-color)] bg-[var(--bg-tertiary)]/50' : ''
        }`}
      >
        {/* Author Header */}
        <div className="flex items-center justify-between text-xs min-w-0">
          <div className="flex items-center gap-2 min-w-0">
            <div className="w-6 h-6 rounded-full bg-[var(--bg-tertiary)] border border-[var(--accent-color)]/30 text-[var(--accent-color)] flex items-center justify-center font-bold text-[10px] font-serif shrink-0">
              {(comment.username || 'A').substring(0, 2).toUpperCase()}
            </div>
            <span className="font-semibold text-[var(--text-primary)] font-serif truncate">{comment.username}</span>
            <span className="text-[10px] text-[var(--text-muted)] font-mono shrink-0">
              {formatRelativeTime(comment.created_at || '')}
            </span>
          </div>
        </div>

        {/* Comment Body */}
        <p className="text-xs text-[var(--text-primary)] font-light leading-relaxed my-1 break-words overflow-hidden">
          {comment.body}
        </p>

        {/* Actions Bar */}
        <div className="flex items-center justify-between pt-2 border-t border-[var(--border-color)] text-xs text-[var(--text-muted)]">
          <div className="flex items-center gap-3">
            <button
              onClick={() => onVote(comment.id, 'up')}
              className="flex items-center gap-1 hover:text-[var(--accent-color)] transition-colors cursor-pointer"
            >
              <ThumbsUp className="w-3.5 h-3.5" />
              <span className="font-mono text-[11px]">{comment.upvote_count}</span>
            </button>

            <button
              onClick={() => onVote(comment.id, 'down')}
              className="flex items-center gap-1 hover:text-rose-500 transition-colors cursor-pointer"
            >
              <ThumbsDown className="w-3.5 h-3.5" />
              <span className="font-mono text-[11px]">{comment.downvote_count}</span>
            </button>

            {!isChild && (
              <button
                onClick={() => setReplyingTo(replyingTo === comment.id ? null : comment.id)}
                className="flex items-center gap-1 hover:text-[var(--text-primary)] transition-colors cursor-pointer text-[11px] font-mono"
              >
                <MessageSquare className="w-3.5 h-3.5 text-[var(--accent-color)]" />
                <span>{t('comments.reply')}</span>
              </button>
            )}
          </div>
        </div>

        {/* Inline Reply Composer */}
        {replyingTo === comment.id && (
          <div className="mt-3">
            <CommentComposer
              placeholder={t('comments.replyingTo', { user: comment.username })}
              onSubmit={async (content, isSpoiler) => {
                await onAddComment(content, isSpoiler, comment.id);
                setReplyingTo(null);
              }}
            />
          </div>
        )}

        {/* Child Replies */}
        {replies.length > 0 && (
          <div className="flex flex-col gap-2 mt-2">
            {replies.map((child) => renderCommentItem(child, true))}
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="flex flex-col gap-6">
      <CommentComposer onSubmit={(content, isSpoiler) => onAddComment(content, isSpoiler, null)} />

      <div className="flex flex-col gap-4">
        {comments.length === 0 ? (
          <div className="p-8 text-center text-[var(--text-muted)] text-xs font-mono border border-dashed border-[var(--border-color)] rounded-2xl">
            {t('comments.noComments')}
          </div>
        ) : (
          rootComments.map((comment) => renderCommentItem(comment))
        )}
      </div>
    </div>
  );
};
