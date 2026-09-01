import React from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft, ChevronLeft, ChevronRight, Settings, Bookmark, BookmarkCheck } from 'lucide-react';
import { Chapter } from '../../types/api';
import { chapterUrl } from '../../utils/chapter';
import { usePreferences } from '../../contexts/PreferencesContext';
import { ReportButton } from '../feedback/ReportButton';

type ReaderHeaderProps = {
  chapter: Chapter;
  progress: number;
  bookmarked: boolean;
  onToggleBookmark: () => void;
  onOpenSettings: () => void;
  currentPage?: number;
  totalPages?: number;
  layout?: 'vertical' | 'single' | 'double';
  showProgress?: boolean;
  isVisible?: boolean;
};

export const ReaderHeader: React.FC<ReaderHeaderProps> = ({
  chapter,
  progress,
  bookmarked,
  onToggleBookmark,
  onOpenSettings,
  currentPage,
  totalPages,
  layout = 'vertical',
  showProgress = true,
  isVisible = true,
}) => {
  const navigate = useNavigate();
  const { t } = usePreferences();

  const prevChap = chapter.navigation?.previous;
  const nextChap = chapter.navigation?.next;

  const handleBack = () => {
    if (chapter.series?.type && chapter.series?.slug) {
      navigate(`/${chapter.series.type}/${chapter.series.slug}`);
    } else {
      navigate(-1);
    }
  };

  const handlePrev = () => {
    if (prevChap) {
      navigate(chapterUrl(chapter.series, prevChap));
    }
  };

  const handleNext = () => {
    if (nextChap) {
      navigate(chapterUrl(chapter.series, nextChap));
    }
  };

  const chapterNum = chapter.chapter_number;
  const chapterTitleStr = chapter.title ? `: ${chapter.title}` : '';
  const roundedProgress = Math.round(progress);

  return (
    <header
      className={`fixed top-0 left-0 right-0 z-50 w-full bg-[var(--bg-card)]/95 backdrop-blur-xl border-b border-[var(--border-color)] shadow-xl transition-all duration-300 overflow-hidden ${
        isVisible ? 'translate-y-0 opacity-100' : '-translate-y-full opacity-0 pointer-events-none'
      }`}
    >
      {/* Top Reading Progress Bar (Fixed at the very top edge) */}
      {showProgress && (
        <div className="w-full h-1 bg-[var(--border-color)] relative overflow-hidden">
          <div
            className="h-full bg-[var(--accent-color)] transition-all duration-150 shadow-[0_0_10px_var(--accent-color)]"
            style={{ width: `${Math.min(100, Math.max(0, progress))}%` }}
          />
        </div>
      )}

      <div className="max-w-7xl mx-auto px-2 sm:px-6 py-2 flex items-center justify-between gap-1 sm:gap-4">
        {/* Left: Back & Series/Chapter Title */}
        <div className="flex items-center gap-1.5 sm:gap-3 min-w-0 flex-1">
          <button
            onClick={handleBack}
            aria-label="Back to series"
            title={t('reader.backToSeries')}
            className="p-1.5 sm:p-2 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-all shrink-0 cursor-pointer active:scale-95"
          >
            <ArrowLeft className="w-4 h-4 sm:w-5 sm:h-5" />
          </button>

          <div className="flex flex-col min-w-0">
            <span className="text-[10px] font-mono font-semibold uppercase tracking-wider text-[var(--accent-color)] truncate">
              {chapter.series?.title || 'SERIES'}
            </span>
            <span className="text-xs sm:text-sm font-serif font-bold text-[var(--text-primary)] truncate">
              {t('chapters.chapterNumber', { number: chapterNum })}{chapterTitleStr}
            </span>
          </div>
        </div>

        {/* Center: Chapter Navigation (Prev & Next buttons with indicator) */}
        <div className="flex items-center gap-0.5 sm:gap-1.5 bg-[var(--bg-tertiary)] border border-[var(--border-color)] p-0.5 sm:p-1 rounded-xl shadow-inner shrink-0">
          <button
            disabled={!prevChap}
            onClick={handlePrev}
            aria-label="Previous chapter"
            title={t('reader.prevChapter')}
            className="p-1 sm:px-2.5 sm:py-1.5 rounded-lg bg-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-card)] disabled:opacity-30 disabled:hover:bg-transparent disabled:cursor-not-allowed transition-all cursor-pointer flex items-center gap-1 active:scale-95 text-xs font-medium"
          >
            <ChevronLeft className="w-4 h-4" />
            <span className="hidden md:inline">{t('reader.prev')}</span>
          </button>

          {/* Chapter & Progress Badge */}
          <div className="hidden xs:flex px-2 py-0.5 rounded-md bg-[var(--bg-card)] border border-[var(--border-color)] text-[10px] sm:text-[11px] font-mono text-[var(--text-primary)] items-center gap-1 whitespace-nowrap">
            <span className="font-semibold text-[var(--accent-color)]">#{chapterNum}</span>
            {showProgress && (
              <>
                {layout !== 'vertical' && currentPage !== undefined && totalPages !== undefined && totalPages > 0 ? (
                  <>
                    <span className="opacity-40">•</span>
                    <span className="text-[10px] opacity-90">{currentPage + 1}/{totalPages}</span>
                    <span className="opacity-40">•</span>
                    <span className="text-[10px] opacity-80">{roundedProgress}%</span>
                  </>
                ) : (
                  <>
                    <span className="opacity-40">•</span>
                    <span className="text-[10px] opacity-80">{roundedProgress}%</span>
                  </>
                )}
              </>
            )}
          </div>

          <button
            disabled={!nextChap}
            onClick={handleNext}
            aria-label="Next chapter"
            title={t('reader.nextChapter')}
            className="p-1 sm:px-2.5 sm:py-1.5 rounded-lg bg-[var(--accent-light)] text-[var(--accent-color)] hover:bg-[var(--accent-color)] hover:text-white disabled:opacity-30 disabled:bg-transparent disabled:text-[var(--text-muted)] disabled:cursor-not-allowed transition-all cursor-pointer flex items-center gap-1 active:scale-95 text-xs font-medium border border-[var(--accent-border)]"
          >
            <span className="hidden md:inline">{t('reader.next')}</span>
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>

        {/* Right: Settings, Report & Bookmark */}
        <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
          <ReportButton
            targetType="chapter"
            targetId={chapter.id}
            targetTitle={`Bölüm ${chapter.chapter_number}`}
            variant="icon"
            className="p-2 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-amber-500 hover:border-amber-500/40"
          />

          <button
            onClick={onOpenSettings}
            aria-label="Reader Settings"
            title={t('reader.settings')}
            className="p-2 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-all cursor-pointer active:scale-95"
          >
            <Settings className="w-4 h-4 sm:w-5 sm:h-5" />
          </button>

          <button
            onClick={onToggleBookmark}
            aria-label={bookmarked ? t('reader.removeBookmark') : t('reader.addBookmark')}
            title={bookmarked ? t('reader.removeBookmark') : t('reader.addBookmark')}
            className={`p-2 rounded-xl border transition-all cursor-pointer active:scale-95 ${
              bookmarked
                ? 'bg-[var(--accent-light)] border-[var(--accent-color)] text-[var(--accent-color)] shadow-[0_0_12px_var(--accent-light)]'
                : 'bg-[var(--bg-tertiary)] border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
            }`}
          >
            {bookmarked ? (
              <BookmarkCheck className="w-4 h-4 sm:w-5 sm:h-5 fill-[var(--accent-color)]" />
            ) : (
              <Bookmark className="w-4 h-4 sm:w-5 sm:h-5" />
            )}
          </button>
        </div>
      </div>
    </header>
  );
};

export const ReaderChrome = ReaderHeader;
