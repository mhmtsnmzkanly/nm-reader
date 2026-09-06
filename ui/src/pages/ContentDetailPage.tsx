import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ShieldCheck, Coins, ArrowRight, Sparkles, Lock, BookOpen, MessageSquare, Star } from 'lucide-react';
import { contentService, commentService, userService } from '../services';
import {
  ContentDetail,
  ContentDetailChapter,
  ContentOverview,
  ContentType,
  Comment,
  ContentSummary,
} from '../types/api';
import { Breadcrumb } from '../components/navigation/Breadcrumb';
import { ContentHero } from '../components/content/ContentHero';
import { ContentMetadata } from '../components/content/ContentMetadata';
import { ContentChapterList } from '../components/content/ContentChapterList';
import { UnlockModal } from '../components/content/UnlockModal';
import { ContentCard } from '../components/content/ContentCard';
import { CommentThread } from '../components/comments/CommentThread';
import { VoteControl } from '../components/voting/VoteControl';
import { Skeleton } from '../components/feedback/Skeleton';
import { ErrorState } from '../components/feedback/ErrorState';
import { EmptyState } from '../components/feedback/EmptyState';
import { Button } from '../components/ui/Button';
import { usePreferences } from '../contexts/PreferencesContext';
import { useAuth } from '../contexts/AuthContext';
import { useMe } from '../contexts/MeContext';
import { AdultGateModal, isAdultConfirmed } from '../components/content/AdultGateModal';
import { MembersOnlyLock } from '../components/content/MembersOnlyLock';

function getBootstrapContentOverview(type: string, slug: string): ContentOverview | null {
  if (typeof window === 'undefined') return null;
  const page = window.__NMR_CONTEXT?.current_page;
  const pageData = page?.data;
  if (page?.route !== 'content' || pageData?.type !== type || pageData?.slug !== slug) return null;
  const content = pageData.content;
  if (!content || typeof content !== 'object') return null;
  if (!Array.isArray(pageData.chapters) || !Array.isArray(pageData.related)) return null;
  return { content, chapters: pageData.chapters, related: pageData.related };
}

export const ContentDetailPage: React.FC = () => {
  const { t } = usePreferences();
  const { user } = useAuth();
  const { me, isLoading: isMeLoading, refreshMe } = useMe();
  const { type = 'manga', slug = '' } = useParams<{ type: string; slug: string }>();
  const navigate = useNavigate();

  const [content, setContent] = useState<ContentDetail | null>(null);
  const [chapters, setChapters] = useState<ContentDetailChapter[]>([]);
  const [comments, setComments] = useState<Comment[]>([]);
  const [commentsLoaded, setCommentsLoaded] = useState(false);
  const [relatedContent, setRelatedContent] = useState<ContentSummary[]>([]);
  const [walletBalance, setWalletBalance] = useState<number>(0);

  const [isFollowing, setIsFollowing] = useState(false);
  const [isInLibrary, setIsInLibrary] = useState(false);

  const [activeTab, setActiveTab] = useState<'chapters' | 'comments'>('chapters');

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Adult gate state
  const [isAdultConfirmedState, setIsAdultConfirmedState] = useState(() => isAdultConfirmed());

  // Unlock Modal State
  const [isUnlockModalOpen, setIsUnlockModalOpen] = useState(false);
  const [unlockTargetChapter, setUnlockTargetChapter] = useState<ContentDetailChapter | null>(null);
  const [isSeriesUnlockTarget, setIsSeriesUnlockTarget] = useState(false);

  const loadData = useCallback(async () => {
    if (!slug) return;
    setIsLoading(true);
    setError(null);
    setComments([]);
    setCommentsLoaded(false);

    try {
      const bootstrapOverview = getBootstrapContentOverview(type, slug);
      const overviewRes = bootstrapOverview
        ? { status: 'success' as const, data: bootstrapOverview, meta: {}, error: null }
        : await contentService.getContentOverview(type as ContentType, slug);

      if (overviewRes.status === 'error') {
        setError(overviewRes.error.message || t('content.notFoundTitle'));
        setIsLoading(false);
        return;
      }

      const detailData = overviewRes.data.content;
      setContent(detailData);

      // Set user follow & library state
      setIsFollowing(
        detailData.user_state?.is_following ?? detailData.is_followed ?? false
      );
      setIsInLibrary(detailData.user_state?.is_in_library ?? false);

      // Chapters
      const mappedChapters: ContentDetailChapter[] = overviewRes.data.chapters.map((c) => ({
          ...c,
          number: Number(c.chapter_number || 1),
          published_at: c.created_at || (c as any).published_at || '',
          price_coin: c.price_coin ?? (c.access?.chapter_unlock_price || 0),
      }));
      setChapters(mappedChapters);

      setWalletBalance(me?.wallet?.balance_coin ?? me?.wallet?.balance ?? 0);

      // Related Content
      setRelatedContent(overviewRes.data.related.filter((c) => c.slug !== slug).slice(0, 3));
    } catch {
      setError(t('content.networkError'));
    } finally {
      setIsLoading(false);
    }
  }, [type, slug, t, user, me?.wallet]);

  const loadComments = useCallback(async () => {
    if (commentsLoaded || !slug) return;
    const response = await commentService.getComments('content', slug);
    if (response.status === 'success') {
      setComments(response.data);
      setCommentsLoaded(true);
    }
  }, [commentsLoaded, slug]);

  useEffect(() => {
    if (isMeLoading) return;
    loadData();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [isMeLoading, loadData]);

  // User Actions
  const handleToggleFollow = async () => {
    setIsFollowing((prev) => !prev);
    await contentService.toggleFollow(type as ContentType, slug, isFollowing);
  };

  const handleToggleLibrary = async () => {
    setIsInLibrary((prev) => !prev);
    await userService.toggleLibrary(type as ContentType, slug, isInLibrary);
  };

  // Unlock Chapter Click
  const handleLockClick = (chapter: ContentDetailChapter) => {
    setUnlockTargetChapter(chapter);
    setIsSeriesUnlockTarget(false);
    setIsUnlockModalOpen(true);
  };

  // Unlock Series Click
  const handleUnlockSeriesClick = () => {
    setUnlockTargetChapter(null);
    setIsSeriesUnlockTarget(true);
    setIsUnlockModalOpen(true);
  };

  // Confirm Unlock handler
  const handleConfirmUnlock = async (chapterId?: string): Promise<boolean> => {
    if (isSeriesUnlockTarget) {
      const res = await contentService.unlockSeries(type as ContentType, slug);
      if (res.status === 'success') {
        // Refresh detail & wallet
        await refreshMe();
        await loadData();
        return true;
      }
      return false;
    } else if (chapterId) {
      const res = await contentService.unlockChapter(chapterId);
      if (res.status === 'success') {
        // Refresh detail & wallet
        await refreshMe();
        await loadData();
        return true;
      }
      return false;
    }
    return false;
  };

  // Rating
  const handleRate = async (score: number) => {
    await contentService.rateContent(type as ContentType, slug, score);
  };

  // Comments
  const handleAddComment = async (text: string, isSpoiler: boolean, parentId?: number | null) => {
    await commentService.postComment('content', slug, text, parentId);
    const updatedComm = await commentService.getComments('content', slug);
    if (updatedComm.status === 'success') setComments(updatedComm.data);
  };

  const handleCommentVote = async (commentId: number, direction: 'up' | 'down') => {
    const voteVal = direction === 'up' ? 1 : -1;
    await commentService.voteComment(commentId, voteVal);
    const updatedComm = await commentService.getComments('content', slug);
    if (updatedComm.status === 'success') setComments(updatedComm.data);
  };

  // Breadcrumb items
  const breadcrumbItems = [
    {
      label: type.charAt(0).toUpperCase() + type.slice(1).replace('-', ' '),
      href: `/${type}`,
    },
    {
      label: content?.title || slug,
    },
  ];

  if (isLoading) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6 flex flex-col gap-8">
        <Skeleton variant="text" className="h-5 w-48" />
        <div className="p-8 rounded-3xl bg-[var(--bg-card)] border border-[var(--border-color)] flex flex-col md:flex-row gap-6">
          <Skeleton variant="card" className="w-56 h-80 rounded-2xl flex-shrink-0" />
          <div className="flex-1 flex flex-col gap-4">
            <Skeleton variant="text" className="h-6 w-32" />
            <Skeleton variant="text" className="h-10 w-3/4" />
            <Skeleton variant="text" className="h-4 w-1/2" />
            <Skeleton variant="rect" className="h-28 w-full mt-4" />
          </div>
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
          <div className="lg:col-span-8 flex flex-col gap-4">
            <Skeleton variant="rect" className="h-64 w-full rounded-2xl" />
            <Skeleton variant="rect" className="h-96 w-full rounded-2xl" />
          </div>
          <div className="lg:col-span-4 flex flex-col gap-4">
            <Skeleton variant="rect" className="h-48 w-full rounded-2xl" />
            <Skeleton variant="rect" className="h-48 w-full rounded-2xl" />
          </div>
        </div>
      </div>
    );
  }

  if (error || !content) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-12">
        <Breadcrumb items={breadcrumbItems} className="mb-6" />
        {error ? (
          <ErrorState
            title={t('content.loadErrorTitle')}
            message={error}
            onRetry={loadData}
          />
        ) : (
          <EmptyState
            title={t('content.notFoundTitle')}
            description={t('content.notFoundDesc')}
            actionLabel={t('feedback.backHome')}
            onAction={() => navigate('/')}
          />
        )}
      </div>
    );
  }

  const initialScore =
    typeof content.rating === 'object' && content.rating !== null
      ? content.rating.average
      : typeof content.rating === 'number'
      ? content.rating
      : content.rating_avg ?? content.rating_average ?? 0;

  const genresList = content.genres || content.series_genres || [];
  const tagsList = content.tags || content.series_tags || [];
  const lastReadId = content.user_state?.last_read_chapter_id;

  return (
    <div className="max-w-7xl mx-auto px-3 sm:px-6 py-4 sm:py-6 flex flex-col gap-6 sm:gap-8 transition-colors duration-300 w-full overflow-hidden">
      {/* Breadcrumb Navigation */}
      <Breadcrumb items={breadcrumbItems} />

      {/* Hero Header Section */}
      <ContentHero
        content={content}
        chapters={chapters}
        isFollowing={isFollowing}
        isInLibrary={isInLibrary}
        onToggleFollow={handleToggleFollow}
        onToggleLibrary={handleToggleLibrary}
        genres={genresList}
        tags={tagsList}
      />

      {/* Main Content Layout (Grid) */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-start">
        {/* Left / Main Column */}
        <div className="lg:col-span-8 flex flex-col gap-6 sm:gap-8 min-w-0">
          {/* Structured Metadata Card */}
          <ContentMetadata content={content} chapterCount={chapters.length} />

          {/* Section with Tabs: Chapter List & Comments/Reviews */}
          <div className="flex flex-col gap-5">
            {/* Tab Controls Header */}
            <div className="flex items-center gap-2 border-b border-[var(--border-color)] pb-2 overflow-x-auto">
              <button
                type="button"
                onClick={() => setActiveTab('chapters')}
                className={`flex items-center gap-2 px-4 py-2.5 rounded-xl font-serif text-sm sm:text-base font-bold transition-all cursor-pointer whitespace-nowrap ${
                  activeTab === 'chapters'
                    ? 'bg-[var(--accent-color)] text-white shadow-md'
                    : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)]'
                }`}
              >
                <BookOpen className="w-4 h-4 shrink-0" />
                <span>{t('content.chapterList')}</span>
                <span
                  className={`px-2 py-0.5 rounded-full text-xs font-mono font-medium ${
                    activeTab === 'chapters'
                      ? 'bg-white/20 text-white'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-muted)] border border-[var(--border-color)]'
                  }`}
                >
                  {chapters.length}
                </span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setActiveTab('comments');
                  void loadComments();
                }}
                className={`flex items-center gap-2 px-4 py-2.5 rounded-xl font-serif text-sm sm:text-base font-bold transition-all cursor-pointer whitespace-nowrap ${
                  activeTab === 'comments'
                    ? 'bg-[var(--accent-color)] text-white shadow-md'
                    : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)]'
                }`}
              >
                <MessageSquare className="w-4 h-4 shrink-0" />
                <span>{t('content.commentsAndReviews')}</span>
                <span
                  className={`px-2 py-0.5 rounded-full text-xs font-mono font-medium ${
                    activeTab === 'comments'
                      ? 'bg-white/20 text-white'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-muted)] border border-[var(--border-color)]'
                  }`}
                >
                  {comments.length}
                </span>
              </button>
            </div>

            {/* Members-Only Banner if guest */}
            {content.is_members_only && !user && (
              <MembersOnlyLock compact className="mb-2" />
            )}

            {/* Tab 1: Chapter List */}
            {activeTab === 'chapters' && (
              <ContentChapterList
                chapters={chapters}
                contentType={content.type}
                contentSlug={content.slug}
                lastReadChapterId={lastReadId}
                onLockClick={handleLockClick}
              />
            )}

            {/* Tab 2: Reviews & Comments */}
            {activeTab === 'comments' && (
              <div className="flex flex-col gap-6">
                {/* 1. Rating & Vote */}
                <div className="bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-5 sm:p-6 flex flex-col gap-4 shadow-sm transition-colors duration-300">
                  <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-3">
                    <div className="flex items-center gap-2">
                      <Star className="w-5 h-5 text-amber-500 fill-amber-500" />
                      <h3 className="font-serif text-lg sm:text-xl font-bold text-[var(--text-primary)]">
                        {t('content.reviewAndRate')}
                      </h3>
                    </div>
                    <span className="text-xs font-mono text-[var(--text-muted)]">
                      {t('content.overallRating')}: <strong className="text-[var(--text-primary)] font-bold">{initialScore > 5 ? (initialScore / 2).toFixed(1) : initialScore.toFixed(1)}</strong> / 5.0
                    </span>
                  </div>

                  <VoteControl
                    contentId={content.id}
                    initialRating={initialScore > 5 ? initialScore / 2 : initialScore}
                    onRate={handleRate}
                  />
                </div>

                {/* 2. Community Discussions */}
                <section className="bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-5 sm:p-6 flex flex-col gap-5 shadow-sm transition-colors duration-300">
                  <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-3">
                    <h3 className="font-serif text-xl font-bold text-[var(--text-primary)]">
                      {t('content.communityDiscussions')}
                    </h3>
                    <span className="text-xs font-mono text-[var(--text-muted)]">
                      {t('content.commentsCount', { count: comments.length })}
                    </span>
                  </div>

                  <CommentThread
                    comments={comments}
                    onAddComment={handleAddComment}
                    onVote={handleCommentVote}
                  />
                </section>
              </div>
            )}
          </div>
        </div>

        {/* Right Sidebar */}
        <div className="lg:col-span-4 flex flex-col gap-6 min-w-0 sticky top-20">
          {/* Wallet / Coin Summary & Unlock Series Action */}
          <div className="p-5 rounded-2xl bg-gradient-to-br from-[var(--bg-card)] to-[var(--bg-tertiary)] border border-[var(--border-color)] flex flex-col gap-3.5 shadow-sm">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-mono uppercase tracking-wider text-[var(--text-muted)] font-semibold">
                {t('content.yourWalletBalance')}
              </span>
              <div className="flex items-center gap-1.5 text-amber-500 font-mono font-bold text-sm">
                <Coins className="w-4 h-4" />
                <span>{walletBalance} {t('common.coin')}</span>
              </div>
            </div>
            <p className="text-xs text-[var(--text-secondary)] font-light leading-relaxed">
              {t('content.walletBalanceHint')}
            </p>

            {/* Unlock Series CTA if applicable */}
            {content.series_unlock_price !== undefined &&
              content.series_unlock_price > 0 &&
              !content.is_series_unlocked && (
                <Button
                  variant="gold"
                  size="md"
                  fullWidth
                  onClick={handleUnlockSeriesClick}
                  className="gap-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-semibold text-xs shadow-md cursor-pointer justify-center py-2.5"
                >
                  <Lock className="w-3.5 h-3.5" />
                  <span>{t('content.unlockEntireSeriesBtn', { price: content.series_unlock_price })}</span>
                </Button>
              )}

            <Link to="/shop" className="pt-0.5">
              <Button variant="outline" size="sm" fullWidth className="gap-1.5 text-xs font-mono justify-between cursor-pointer">
                <span>{t('content.goToCoinShop')}</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </Button>
            </Link>
          </div>

          {/* License & Platform Card */}
          <div className="p-5 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl flex flex-col gap-3 shadow-sm">
            <div className="flex items-center gap-2 text-xs font-mono font-bold text-[var(--accent-color)]">
              <ShieldCheck className="w-4 h-4" />
              <span className="uppercase tracking-wider">{t('content.licenseAndSecurity')}</span>
            </div>
            <p className="text-xs text-[var(--text-secondary)] leading-relaxed font-light">
              {t('content.licenseDisclaimer')}
            </p>
          </div>

          {/* Related Titles */}
          {relatedContent.length > 0 && (
            <div className="flex flex-col gap-3.5">
              <div className="flex items-center gap-2 text-xs font-mono font-semibold uppercase tracking-wider text-[var(--text-primary)]">
                <Sparkles className="w-3.5 h-3.5 text-[var(--accent-color)]" />
                <span>{t('content.similarTitles')}</span>
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-1 gap-3">
                {relatedContent.map((item) => (
                  <ContentCard key={item.id} content={item} />
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Unlock Confirmation Modal */}
      <UnlockModal
        isOpen={isUnlockModalOpen}
        onClose={() => setIsUnlockModalOpen(false)}
        targetChapter={unlockTargetChapter}
        isSeriesUnlock={isSeriesUnlockTarget}
        seriesTitle={content.title}
        seriesPrice={content.series_unlock_price || 0}
        walletBalance={walletBalance}
        onConfirmUnlock={handleConfirmUnlock}
      />

      {/* Adult Content Gate Modal */}
      {content.is_adult && !isAdultConfirmedState && (
        <AdultGateModal
          isOpen={true}
          onConfirm={() => setIsAdultConfirmedState(true)}
          onCancel={() => {
            if (window.history.length > 1) {
              navigate(-1);
            } else {
              navigate('/');
            }
          }}
        />
      )}
    </div>
  );
};
