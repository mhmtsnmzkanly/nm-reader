import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import Markdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { Calendar, Eye, ThumbsUp, ThumbsDown, User, ArrowLeft, Clock } from 'lucide-react';
import { blogService, commentService } from '../services';
import { BlogSummary, Comment } from '../types/api';
import { CommentThread } from '../components/comments/CommentThread';
import { RelatedBlogs } from '../components/blog/RelatedBlogs';
import { ReportButton } from '../components/feedback/ReportButton';
import { usePreferences } from '../contexts/PreferencesContext';
import { useAuth } from '../contexts/AuthContext';

function getBootstrapBlog(slug: string): BlogSummary | null {
  if (typeof window === 'undefined') return null;
  const page = window.__NMR_CONTEXT?.current_page;
  const pageData = page?.data;
  if (page?.route !== 'blog' || pageData?.slug !== slug) return null;
  const blog = pageData.blog;
  return blog && typeof blog === 'object' ? blog : null;
}

export const BlogDetailPage: React.FC = () => {
  const { formatDate, t } = usePreferences();
  const { user, isAuthenticated, openAuthModal } = useAuth();
  const { slug = '' } = useParams<{ slug: string }>();
  const [blog, setBlog] = useState<BlogSummary | null>(null);
  const [comments, setComments] = useState<Comment[]>([]);
  const [relatedBlogs, setRelatedBlogs] = useState<BlogSummary[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [userVote, setUserVote] = useState<number>(0);
  const [upvotes, setUpvotes] = useState<number>(0);
  const [downvotes, setDownvotes] = useState<number>(0);
  const [isVoting, setIsVoting] = useState(false);
  const [voteMessage, setVoteMessage] = useState<string | null>(null);

  useEffect(() => {
    const fetchBlogDetail = async () => {
      setIsLoading(true);
      const bootstrapBlog = getBootstrapBlog(slug);
      const res = bootstrapBlog
        ? { status: 'success' as const, data: bootstrapBlog, meta: {}, error: null }
        : await blogService.getBlogBySlug(slug);
      if (res.status === 'success') {
        setBlog(res.data);
        const myVote = (res.data as any).my_vote ?? (res.data.user_state?.liked ? 1 : 0);
        setUserVote(myVote);
        setUpvotes(res.data.upvote_count ?? res.data.likes ?? 0);
        setDownvotes(res.data.downvote_count ?? 0);
        const [commRes, relRes] = await Promise.all([
          commentService.getComments('blog', res.data.slug),
          blogService.getRelatedBlogs(slug, 3),
        ]);
        if (commRes.status === 'success') setComments(commRes.data);
        if (relRes.status === 'success') setRelatedBlogs(relRes.data);
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
        <p className="text-sm font-mono text-[var(--text-muted)]">{t('blog.notFound')}</p>
        <Link
          to="/blogs"
          className="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-[var(--bg-tertiary)] text-xs font-mono text-[var(--text-primary)] hover:text-[var(--accent-color)] border border-[var(--border-color)] transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>{t('blog.backToBlogs')}</span>
        </Link>
      </div>
    );
  }

  const isAuthor = Boolean(
    user &&
      (user.id === blog.user_id ||
        (blog.author_username && user.username === blog.author_username) ||
        (typeof blog.author === 'object' && blog.author?.username && user.username === blog.author.username))
  );

  const handleAddComment = async (text: string, isSpoiler: boolean, parentId?: number | null) => {
    await commentService.postComment('blog', blog.slug, text, parentId);
    const updatedComm = await commentService.getComments('blog', blog.slug);
    if (updatedComm.status === 'success') setComments(updatedComm.data);
  };

  const handleCommentVote = async (commentId: number, direction: 'up' | 'down') => {
    const voteVal = direction === 'up' ? 1 : -1;
    await commentService.voteComment(commentId, voteVal);
    const updatedComm = await commentService.getComments('blog', blog.slug);
    if (updatedComm.status === 'success') setComments(updatedComm.data);
  };

  const handleVoteBlog = async (direction: 'up' | 'down') => {
    if (!blog || isVoting) return;
    setVoteMessage(null);

    if (!isAuthenticated) {
      openAuthModal('login');
      return;
    }

    if (isAuthor) {
      setVoteMessage(t('blog.cannotVoteOwn'));
      return;
    }

    setIsVoting(true);
    const voteValue = direction === 'up' ? 1 : -1;
    try {
      const res = await blogService.voteBlog(blog.slug, voteValue);
      if (res.status === 'success' && res.data) {
        const returnedVote = res.data.my_vote !== undefined ? res.data.my_vote : (res.data.vote as number);
        setUserVote(returnedVote);
        if (res.data.upvote_count !== undefined) {
          setUpvotes(res.data.upvote_count);
        }
        if (res.data.downvote_count !== undefined) {
          setDownvotes(res.data.downvote_count);
        }
      }
    } catch (err: any) {
      const msg = err?.response?.data?.message || err?.message;
      setVoteMessage(msg || t('blog.cannotVoteOwn'));
    } finally {
      setIsVoting(false);
    }
  };

  const blogTagNames = blog.tags?.map((t) => (typeof t === 'string' ? t : t.name)) || [];

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <Link
        to="/blogs"
        className="inline-flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-[var(--text-muted)] hover:text-[var(--accent-color)] transition-colors"
      >
        <ArrowLeft className="w-4 h-4" />
        <span>{t('blog.backToBlogs')}</span>
      </Link>

      {/* Restricted Access Alert if not published */}
      {blog.status && blog.status !== 'published' && (
        <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 flex items-center justify-between gap-4 text-xs font-mono">
          <div className="flex items-center gap-2">
            <span className="font-bold uppercase tracking-wider">{blog.status}:</span>
            <span>{t('blog.restrictedAccessDesc')}</span>
          </div>
          <span className="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-bold uppercase">
            {blog.status}
          </span>
        </div>
      )}

      <article className="flex flex-col gap-6">
        {/* Cover Image if present */}
        {blog.cover_image && (
          <div className="w-full aspect-[21/9] sm:aspect-[2.5/1] rounded-3xl overflow-hidden border border-[var(--border-color)] shadow-sm bg-[var(--bg-tertiary)]">
            <img
              src={blog.cover_image}
              alt={blog.title}
              referrerPolicy="no-referrer"
              className="w-full h-full object-cover"
            />
          </div>
        )}

        <div className="flex flex-col gap-3 border-b border-[var(--border-color)] pb-6">
          <div className="flex items-center justify-between gap-4 flex-wrap">
            <div className="flex items-center gap-2 text-xs font-mono text-[var(--text-muted)] flex-wrap">
              <User className="w-3.5 h-3.5 text-[var(--accent-color)]" />
              <span className="text-[var(--text-primary)] font-semibold">
                {blog.author?.display_name || blog.author_username || t('blog.authorFallback')}
              </span>
              <span>•</span>
              <Calendar className="w-3.5 h-3.5" />
              <span>{formatDate(blog.created_at || '')}</span>
              {blog.read_time && (
                <>
                  <span>•</span>
                  <Clock className="w-3.5 h-3.5 text-[var(--accent-color)]" />
                  <span>{t('blog.readingTime', { min: blog.read_time })}</span>
                </>
              )}
            </div>

            <ReportButton
              targetType="blog"
              targetId={blog.id}
              targetTitle={blog.title}
              variant="button"
              size="sm"
            />
          </div>

          <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold text-[var(--text-primary)] leading-tight">
            {blog.title}
          </h1>

          {/* Tags */}
          {blogTagNames.length > 0 && (
            <div className="flex flex-wrap items-center gap-2 pt-1">
              {blogTagNames.map((tagName, i) => (
                <span
                  key={i}
                  className="px-2.5 py-1 rounded-lg text-xs font-mono bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border border-[var(--border-color)]"
                >
                  #{tagName}
                </span>
              ))}
            </div>
          )}

          <div className="flex items-center gap-3 text-xs font-mono text-[var(--text-muted)] pt-2 flex-wrap">
            <div className="inline-flex items-center gap-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-1 shadow-xs">
              {/* Upvote Button */}
              <button
                type="button"
                onClick={() => handleVoteBlog('up')}
                disabled={isVoting || isAuthor}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all cursor-pointer ${
                  userVote === 1
                    ? 'bg-[var(--accent-color)] text-white font-bold shadow-xs'
                    : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-card)] disabled:opacity-50 disabled:cursor-not-allowed'
                }`}
                aria-label={t('blog.voteUp')}
                title={isAuthor ? t('blog.cannotVoteOwn') : t('blog.voteUp')}
              >
                <ThumbsUp className={`w-3.5 h-3.5 ${userVote === 1 ? 'fill-current' : ''}`} />
                <span>{upvotes}</span>
              </button>

              {/* Downvote Button */}
              <button
                type="button"
                onClick={() => handleVoteBlog('down')}
                disabled={isVoting || isAuthor}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all cursor-pointer ${
                  userVote === -1
                    ? 'bg-rose-500 text-white font-bold shadow-xs'
                    : 'text-[var(--text-secondary)] hover:text-rose-400 hover:bg-[var(--bg-card)] disabled:opacity-50 disabled:cursor-not-allowed'
                }`}
                aria-label={t('blog.voteDown')}
                title={isAuthor ? t('blog.cannotVoteOwn') : t('blog.voteDown')}
              >
                <ThumbsDown className={`w-3.5 h-3.5 ${userVote === -1 ? 'fill-current' : ''}`} />
                <span>{downvotes}</span>
              </button>
            </div>

            {blog.views !== undefined && (
              <span className="flex items-center gap-1 ml-1">
                <Eye className="w-3.5 h-3.5" />
                {blog.views}
              </span>
            )}
          </div>
        </div>

        <div className="prose prose-neutral dark:prose-invert max-w-none text-sm sm:text-base leading-relaxed py-4">
          <Markdown
            remarkPlugins={[remarkGfm]}
            components={{
              a: ({ node, href, children, ...props }) => {
                const isExternal = href?.startsWith('http://') || href?.startsWith('https://');
                return (
                  <a
                    href={href}
                    target={isExternal ? '_blank' : undefined}
                    rel={isExternal ? 'noopener noreferrer' : undefined}
                    className="text-[var(--accent-color)] hover:underline font-medium"
                    {...props}
                  >
                    {children}
                  </a>
                );
              },
              img: ({ node, src, alt, ...props }) => (
                <img
                  src={src}
                  alt={alt || ''}
                  loading="lazy"
                  className="rounded-2xl max-w-full my-4 border border-[var(--border-color)]"
                  {...props}
                />
              ),
              code: ({ node, className, children, ...props }) => {
                const match = /language-(\w+)/.exec(className || '');
                const isInline = !match && typeof children === 'string' && !children.includes('\n');
                if (isInline) {
                  return (
                    <code className="px-1.5 py-0.5 rounded-md bg-[var(--bg-tertiary)] text-[var(--accent-color)] font-mono text-xs" {...props}>
                      {children}
                    </code>
                  );
                }
                return (
                  <code className={className} {...props}>
                    {children}
                  </code>
                );
              },
            }}
          >
            {blog.body || ''}
          </Markdown>
        </div>

        {/* Post End Interaction & Vote Bar */}
        <div className="p-6 rounded-3xl bg-[var(--bg-card)] border border-[var(--border-color)] flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
          <div className="flex flex-col gap-1 text-center sm:text-left">
            <h3 className="font-serif font-bold text-base text-[var(--text-primary)]">
              {t('blog.votePromptTitle')}
            </h3>
            <p className="text-xs text-[var(--text-secondary)]">
              {isAuthor
                ? t('blog.cannotVoteOwn')
                : !isAuthenticated
                ? t('blog.loginToVote')
                : t('blog.votePromptDesc')}
            </p>
            {voteMessage && (
              <p className="text-xs text-rose-500 font-mono mt-1">{voteMessage}</p>
            )}
          </div>

          <div className="flex items-center gap-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-2xl p-1.5 shrink-0">
            {/* Upvote Button */}
            <button
              type="button"
              onClick={() => handleVoteBlog('up')}
              disabled={isVoting || isAuthor}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-mono font-semibold transition-all cursor-pointer ${
                userVote === 1
                  ? 'bg-[var(--accent-color)] text-white shadow-xs'
                  : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-card)] disabled:opacity-50 disabled:cursor-not-allowed'
              }`}
              title={isAuthor ? t('blog.cannotVoteOwn') : t('blog.voteUp')}
            >
              <ThumbsUp className={`w-4 h-4 ${userVote === 1 ? 'fill-current' : ''}`} />
              <span>{t('blog.voteUp')}</span>
              <span className="opacity-80 font-mono text-[11px]">({upvotes})</span>
            </button>

            {/* Downvote Button */}
            <button
              type="button"
              onClick={() => handleVoteBlog('down')}
              disabled={isVoting || isAuthor}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-mono font-semibold transition-all cursor-pointer ${
                userVote === -1
                  ? 'bg-rose-500 text-white shadow-xs'
                  : 'text-[var(--text-secondary)] hover:text-rose-400 hover:bg-[var(--bg-card)] disabled:opacity-50 disabled:cursor-not-allowed'
              }`}
              title={isAuthor ? t('blog.cannotVoteOwn') : t('blog.voteDown')}
            >
              <ThumbsDown className={`w-4 h-4 ${userVote === -1 ? 'fill-current' : ''}`} />
              <span>{t('blog.voteDown')}</span>
              <span className="opacity-80 font-mono text-[11px]">({downvotes})</span>
            </button>
          </div>
        </div>
      </article>

      {/* Related Blogs Component */}
      <RelatedBlogs blogs={relatedBlogs} slug={slug} currentBlogId={blog.id} tags={blogTagNames} />

      {/* Comments Section */}
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
