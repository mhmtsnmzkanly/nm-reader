import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Chapter, ContentType } from '../types/api';
import { fetchChapter } from '../services/chapterService';
import { contentService, userService } from '../services';
import { getReadingProgress, chapterUrl } from '../utils/chapter';
import { usePreferences } from '../contexts/PreferencesContext';

import { ReaderHeader } from '../components/reader/ReaderChrome';
import { NovelReader } from '../components/reader/TextReader';
import { MangaReader } from '../components/reader/ImageReader';
import { LockedChapter } from '../components/reader/LockPanel';
import { ReaderLoading, ReaderError } from '../components/reader/ReaderStates';
import { ReaderSettings } from '../components/reader/ReaderSettings';

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

  const { readerSettings } = usePreferences();

  const [chapter, setChapter] = useState<Chapter | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [progress, setProgress] = useState<number>(0);
  const [currentPageIndex, setCurrentPageIndex] = useState<number>(0);
  const [bookmarked, setBookmarked] = useState<boolean>(false);
  const [isSettingsOpen, setIsSettingsOpen] = useState<boolean>(false);

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

      // Record reading history for this chapter
      userService.recordHistory({
        contentSlug: slug,
        contentType: type as ContentType,
        chapterId: data.id,
        chapterNumber: data.chapter_number || number,
        chapterTitle: data.title,
        page: 1,
        totalPages: data.pages?.length || 1,
        progress: 10,
      });
    } catch (err: any) {
      setError(err?.message || 'Bölüm yüklenemedi.');
    } finally {
      setLoading(false);
    }
  }, [type, slug, number]);

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
      await contentService.toggleFollow(type as ContentType, slug);
    } catch (err) {
      // Revert if error
      setBookmarked((prev) => !prev);
    }
  };

  if (loading) {
    return <ReaderLoading />;
  }

  if (error || !chapter) {
    return <ReaderError message={error || 'Bölüm yüklenemedi.'} onRetry={loadChapter} />;
  }

  const isChapterLocked = chapter.access?.locked || !chapter.access?.granted;
  const prevChap = chapter.navigation?.previous;
  const nextChap = chapter.navigation?.next;
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
        {isChapterLocked ? (
          <LockedChapter chapter={chapter} onUnlock={handleUnlock} />
        ) : chapter.type === 'text' ? (
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

        {/* End of Chapter Navigation Widget */}
        {!isChapterLocked && (
          <div className="max-w-2xl mx-auto w-full mt-8 pt-8 border-t border-[var(--border-color)] flex items-center justify-between gap-4 px-4">
            <button
              disabled={!prevChap}
              onClick={handlePrevChapter}
              className="py-3 px-5 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] text-xs font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] disabled:opacity-30 disabled:cursor-not-allowed transition-all cursor-pointer flex items-center gap-2 active:scale-95 shadow-sm"
            >
              <ChevronLeft className="w-4 h-4" />
              <span>Önceki Bölüm</span>
            </button>

            <span className="text-xs font-mono text-[var(--text-muted)]">
              Bölüm {chapter.chapter_number}
            </span>

            <button
              disabled={!nextChap}
              onClick={handleNextChapter}
              className="py-3 px-5 rounded-xl bg-[var(--accent-color)] text-white text-xs font-bold hover:opacity-90 disabled:opacity-30 disabled:cursor-not-allowed transition-all cursor-pointer flex items-center gap-2 shadow-lg shadow-[var(--accent-color)]/20 active:scale-95"
            >
              <span>Sonraki Bölüm</span>
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        )}
      </main>

      {/* Reader Settings Dialog */}
      <ReaderSettings
        isOpen={isSettingsOpen}
        onClose={() => setIsSettingsOpen(false)}
        mode={chapter.type === 'text' ? 'text' : 'image'}
      />
    </div>
  );
};

export const ReaderPage = ChapterReader;
export default ChapterReader;
