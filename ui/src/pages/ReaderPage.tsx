import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ChevronLeft, ChevronRight, Info } from 'lucide-react';
import { Chapter, ContentType } from '../types/api';
import { fetchChapter } from '../services/chapterService';
import { contentService, userService } from '../services';
import { getReadingProgress, chapterUrl } from '../utils/chapter';
import { usePreferences } from '../contexts/PreferencesContext';
import { useAuth } from '../contexts/AuthContext';
import { AdultGateModal, isAdultConfirmed } from '../components/content/AdultGateModal';
import { MembersOnlyLock } from '../components/content/MembersOnlyLock';

import { ReaderHeader } from '../components/reader/ReaderChrome';
import { NovelReader } from '../components/reader/TextReader';
import { MangaReader } from '../components/reader/ImageReader';
import { LockedChapter } from '../components/reader/LockPanel';
import { ReaderLoading, ReaderError } from '../components/reader/ReaderStates';
import { ReaderSettings } from '../components/reader/ReaderSettings';
import { ChapterComments } from '../components/reader/ChapterComments';

export const ChapterReader: React.FC = () => {
  const navigate = useNavigate();
  const params = useParams<{
    type?: string;
    slug?: string;
    chapterNumber?: string;
    number?: string;
  }>();

  const type = params.type || 'manga';
  const slug = params.slug || '';
  const number = params.chapterNumber || params.number || '1';

  const { readerSettings, t } = usePreferences();
  const { user, isAuthenticated } = useAuth();

  const [chapter, setChapter] = useState<Chapter | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [progress, setProgress] = useState<number>(0);
  const [currentPageIndex, setCurrentPageIndex] = useState<number>(0);
  const [bookmarked, setBookmarked] = useState<boolean>(false);
  const [isSettingsOpen, setIsSettingsOpen] = useState<boolean>(false);
  const [isAdultConfirmedState, setIsAdultConfirmedState] = useState(() => isAdultConfirmed());

  // Load chapter data & initial follow/bookmark status
  const loadChapter = useCallback(async () => {
    if (!slug || !number) return;
    setLoading(true);
    setError(null);
    setCurrentPageIndex(0);

    try {
      const data = await fetchChapter(type, slug, number);
      setChapter(data);

      // Check bookmark/follow status
      contentService.getContentDetail(type as ContentType, slug).then((res) => {
        if (res.status === 'success' && res.data) {
          const isFollowed = res.data.is_followed ?? res.data.user_state?.is_following ?? false;
          setBookmarked(isFollowed);
        }
      });

    } catch (err: any) {
      setError(err?.message || t('reader.errorTitle'));
    } finally {
      setLoading(false);
    }
  }, [type, slug, number, t]);

  useEffect(() => {
    loadChapter();
    window.scrollTo(0, 0);
  }, [loadChapter]);

  const [isHeaderVisible, setIsHeaderVisible] = useState<boolean>(true);
  const lastScrollY = React.useRef<number>(0);

  // Auto-hide UI on scroll down if enabled in preferences
  useEffect(() => {
    if (!readerSettings.auto_hide_ui) {
      setIsHeaderVisible(true);
      return;
    }

    const handleScrollHide = () => {
      const currentScrollY = window.scrollY;
      if (currentScrollY > 100 && currentScrollY > lastScrollY.current) {
        // Scrolling down -> hide header
        setIsHeaderVisible(false);
      } else {
        // Scrolling up -> show header
        setIsHeaderVisible(true);
      }
      lastScrollY.current = currentScrollY;
    };

    window.addEventListener('scroll', handleScrollHide, { passive: true });
    return () => {
      window.removeEventListener('scroll', handleScrollHide);
    };
  }, [readerSettings.auto_hide_ui]);

  // Handle scroll progress in vertical mode
  useEffect(() => {
    if (readerSettings.layout !== 'vertical') return;

    const handleScroll = () => {
      setProgress(getReadingProgress());
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();

    return () => {
      window.removeEventListener('scroll', handleScroll);
    };
  }, [readerSettings.layout]);

  // Update progress in single/double page modes
  useEffect(() => {
    if (readerSettings.layout === 'vertical' || !chapter || !chapter.pages || chapter.pages.length === 0) return;
    const totalPages = chapter.pages.length;
    const pageProg = Math.min(100, Math.max(0, ((currentPageIndex + 1) / totalPages) * 100));
    setProgress(pageProg);
  }, [currentPageIndex, readerSettings.layout, chapter]);

  const lastSavedProgress = React.useRef<Record<string, number>>({});

  // Persist real reader progress after scrolling/page navigation settles.
  useEffect(() => {
    if (!isAuthenticated || !chapter || chapter.access?.granted !== true) return;

    const roundedProgress = Math.min(100, Math.max(0, Math.round(progress)));
    const lastSaved = lastSavedProgress.current[chapter.id] ?? -1;
    if (roundedProgress <= 0 || (roundedProgress < 100 && Math.abs(roundedProgress - lastSaved) < 5)) return;

    const timer = window.setTimeout(async () => {
      const result = await userService.recordHistory({
        contentSlug: slug,
        contentType: type as ContentType,
        chapterId: chapter.id,
        chapterNumber: chapter.chapter_number || number,
        chapterTitle: chapter.title,
        page: currentPageIndex + 1,
        totalPages: chapter.pages?.length || 1,
        progress: roundedProgress,
      });
      if (result.status === 'success') {
        lastSavedProgress.current[chapter.id] = roundedProgress;
      }
    }, 1200);

    return () => window.clearTimeout(timer);
  }, [chapter, currentPageIndex, isAuthenticated, number, progress, slug, type]);

  // Handle unlock action
  const handleUnlock = async () => {
    if (!chapter) return;
    try {
      const res = await contentService.unlockChapter(chapter.id);
      if (res.status === 'success') {
        await loadChapter();
      }
    } catch (err) {
      console.error('Unlock error:', err);
    }
  };

  // Handle bookmark toggle
  const handleToggleBookmark = async () => {
    setBookmarked((prev) => !prev);
    try {
      await contentService.toggleFollow(type as ContentType, slug, bookmarked);
    } catch (err) {
      // Revert if error
      setBookmarked((prev) => !prev);
    }
  };

  if (loading) {
    return <ReaderLoading />;
  }

  if (error || !chapter) {
    return <ReaderError message={error || t('reader.errorTitle')} onRetry={loadChapter} />;
  }

  const isChapterLocked = chapter.access?.locked || !chapter.access?.granted;
  const prevChap = chapter.adjacent_chapters?.prev ?? chapter.navigation?.previous ?? null;
  const nextChap = chapter.adjacent_chapters?.next ?? chapter.navigation?.next ?? null;
  const totalPages = chapter.pages?.length || 0;

  const handleNextChapter = () => {
    if (nextChap) {
      navigate(chapterUrl(chapter.series, nextChap));
    }
  };

  const handlePrevChapter = () => {
    if (prevChap) {
      navigate(chapterUrl(chapter.series, prevChap));
    }
  };

  return (
    <div className="min-h-screen bg-[var(--bg-primary)] text-[var(--text-primary)] flex flex-col pt-16 pb-12 relative selection:bg-[var(--accent-color)] selection:text-white transition-colors duration-300">
      {/* Top Header with Progress Bar & Navigation Controls */}
      <ReaderHeader
        chapter={chapter}
        progress={progress}
        bookmarked={bookmarked}
        onToggleBookmark={handleToggleBookmark}
        onOpenSettings={() => setIsSettingsOpen(true)}
        currentPage={currentPageIndex}
        totalPages={totalPages}
        layout={readerSettings.layout}
        showProgress={readerSettings.show_progress ?? true}
        isVisible={isHeaderVisible}
      />

      {/* Main Reader Content Area */}
      <main className="flex-1 w-full max-w-7xl mx-auto px-2 sm:px-4 py-4 flex flex-col justify-center">
        {chapter.is_members_only && !user ? (
          <MembersOnlyLock className="my-8" />
        ) : isChapterLocked ? (
          <LockedChapter chapter={chapter} onUnlock={handleUnlock} />
        ) : (
          <>
            {/* Translator Note Banner */}
            {chapter.translator_note && (
              <div className="my-4 max-w-4xl mx-auto w-full p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200 shadow-sm">
                <span className="font-bold flex items-center gap-2 mb-1 text-amber-400">
                  <Info className="w-4 h-4 text-amber-400" />
                  {t('reader.translatorNote') || 'Çevirmen Notu:'}
                </span>
                <p className="text-sm text-[var(--text-primary)] whitespace-pre-line leading-relaxed">
                  {chapter.translator_note}
                </p>
              </div>
            )}

            {chapter.type === 'text' ? (
              <NovelReader chapter={chapter} readerSettings={readerSettings} />
            ) : (
              <MangaReader
                chapter={chapter}
                readerSettings={readerSettings}
                currentPageIndex={currentPageIndex}
                onPageChange={setCurrentPageIndex}
                onNextChapter={nextChap ? handleNextChapter : undefined}
                onPrevChapter={prevChap ? handlePrevChapter : undefined}
                isSettingsOpen={isSettingsOpen}
              />
            )}
          </>
        )}

        {/* End of Chapter Navigation Widget */}
        {!isChapterLocked && (!chapter.is_members_only || user) && (
          <>
            <div className="max-w-2xl mx-auto w-full mt-8 pt-8 border-t border-[var(--border-color)] flex items-center justify-between gap-4 px-4">
              <button
                disabled={!prevChap}
                onClick={handlePrevChapter}
                className="py-3 px-5 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] text-xs font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] disabled:opacity-30 disabled:cursor-not-allowed transition-all cursor-pointer flex items-center gap-2 active:scale-95 shadow-sm"
              >
                <ChevronLeft className="w-4 h-4" />
                <span>{t('reader.prevChapter')}</span>
              </button>

              <span className="text-xs font-mono text-[var(--text-muted)]">
                {t('chapters.chapterNumber', { number: chapter.chapter_number })}
              </span>

              <button
                disabled={!nextChap}
                onClick={handleNextChapter}
                className="py-3 px-5 rounded-xl bg-[var(--accent-color)] text-white text-xs font-bold hover:opacity-90 disabled:opacity-30 disabled:cursor-not-allowed transition-all cursor-pointer flex items-center gap-2 shadow-lg shadow-[var(--accent-color)]/20 active:scale-95"
              >
                <span>{t('reader.nextChapter')}</span>
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>

            {/* Chapter Comments Section */}
            <ChapterComments
              chapterId={String(chapter.id)}
              chapterNumber={chapter.chapter_number}
              seriesSlug={slug}
            />
          </>
        )}
      </main>

      {/* Reader Settings Dialog */}
      <ReaderSettings
        isOpen={isSettingsOpen}
        onClose={() => setIsSettingsOpen(false)}
        mode={chapter.type === 'text' ? 'text' : 'image'}
      />

      {/* Adult Content Warning Modal */}
      {chapter.is_adult && !isAdultConfirmedState && (
        <AdultGateModal
          isOpen={true}
          onConfirm={() => setIsAdultConfirmedState(true)}
          onCancel={() => {
            if (window.history.length > 1) {
              navigate(-1);
            } else {
              navigate(`/${type}/${slug}`);
            }
          }}
        />
      )}
    </div>
  );
};

export const ReaderPage = ChapterReader;
export default ChapterReader;
