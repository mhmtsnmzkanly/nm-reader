import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Calendar, Eye, ThumbsUp, User, ArrowLeft } from 'lucide-react';
import { blogService, commentService } from '../services';
import { BlogSummary, Comment } from '../types/api';
import { CommentThread } from '../components/comments/CommentThread';
import { usePreferences } from '../contexts/PreferencesContext';

export const BlogDetailPage: React.FC = () => {
  const { formatDate, t } = usePreferences();
  const { slug = '' } = useParams<{ slug: string }>();
  const [blog, setBlog] = useState<BlogSummary | null>(null);
  const [comments, setComments] = useState<Comment[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchBlogDetail = async () => {
      setIsLoading(true);
      const res = await blogService.getBlogBySlug(slug);
      if (res.status === 'success') {
        setBlog(res.data);
        const commRes = await commentService.getComments('blog', res.data.id);
        if (commRes.status === 'success') setComments(commRes.data);
      }
      setIsLoading(false);
    };

    fetchBlogDetail();
  }, [slug]);

  if (isLoading) {
    return (
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-12 flex flex-col gap-6 animate-pulse">
        <div className="h-6 w-32 bg-[var(--bg-tertiary)] rounded-lg" />
        <div className="h-10 w-3/4 bg-[var(--bg-tertiary)] rounded-xl" />
        <div className="h-4 w-1/4 bg-[var(--bg-tertiary)] rounded-lg" />
        <div className="h-48 w-full bg-[var(--bg-tertiary)] rounded-2xl mt-4" />
      </div>
    );
  }

  if (!blog) {
    return (
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-16 text-center">
        <p className="text-sm font-mono text-[var(--text-muted)]">Blog yazısı bulunamadı.</p>
        <Link
          to="/blogs"
          className="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-[var(--bg-tertiary)] text-xs font-mono text-[var(--text-primary)] hover:text-[var(--accent-color)] border border-[var(--border-color)] transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Tüm Bloglara Dön</span>
        </Link>
      </div>
    );
  }

  const handleAddComment = async (text: string, isSpoiler: boolean, parentId?: number | null) => {
    await commentService.postComment('blog', blog.id, text, parentId);
    const updatedComm = await commentService.getComments('blog', blog.id);
    if (updatedComm.status === 'success') setComments(updatedComm.data);
  };

  const handleCommentVote = async (commentId: number, direction: 'up' | 'down') => {
    const voteVal = direction === 'up' ? 1 : -1;
    await commentService.voteComment(commentId, voteVal);
    const updatedComm = await commentService.getComments('blog', blog.id);
    if (updatedComm.status === 'success') setComments(updatedComm.data);
  };

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <Link
        to="/blogs"
        className="inline-flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-[var(--text-muted)] hover:text-[var(--accent-color)] transition-colors"
      >
        <ArrowLeft className="w-4 h-4" />
        <span>Tüm Bloglara Dön</span>
      </Link>

      <article className="flex flex-col gap-6">
        <div className="flex flex-col gap-3 border-b border-[var(--border-color)] pb-6">
          <div className="flex items-center gap-2 text-xs font-mono text-[var(--text-muted)]">
            <User className="w-3.5 h-3.5 text-[var(--accent-color)]" />
            <span className="text-[var(--text-primary)] font-semibold">{blog.author_username}</span>
            <span>•</span>
            <Calendar className="w-3.5 h-3.5" />
            <span>{formatDate(blog.created_at || '')}</span>
          </div>

          <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold text-[var(--text-primary)] leading-tight">
            {blog.title}
          </h1>

          <div className="flex items-center gap-4 text-xs font-mono text-[var(--text-muted)] pt-2">
            <span className="flex items-center gap-1">
              <ThumbsUp className="w-3.5 h-3.5 text-[var(--accent-color)]" />
              {t('profile.likesCount', { count: blog.upvote_count || blog.likes || 0 })}
            </span>
          </div>
        </div>

        <div className="text-sm sm:text-base text-[var(--text-secondary)] font-light leading-relaxed whitespace-pre-line py-4">
          {blog.body}
        </div>
      </article>

      <section className="flex flex-col gap-6 pt-8 border-t border-[var(--border-color)]">
        <h2 className="font-serif text-2xl font-bold text-[var(--text-primary)]">
          {t('comments.title')} <span className="italic text-[var(--accent-color)]">({comments.length})</span>
        </h2>
        <CommentThread
          comments={comments}
          onAddComment={handleAddComment}
          onVote={handleCommentVote}
        />
      </section>
    </div>
  );
};
