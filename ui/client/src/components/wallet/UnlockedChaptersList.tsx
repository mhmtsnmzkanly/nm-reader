import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  BookOpen,
  Calendar,
  Coins,
  ChevronLeft,
  ChevronRight,
  ArrowRight,
  Sparkles,
} from 'lucide-react';
import { walletService } from '../../services';
import { ChapterUnlockRow } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

export const UnlockedChaptersList: React.FC = () => {
  const { t, formatRelativeTime } = usePreferences();
  const [chapterUnlocks, setChapterUnlocks] = useState<ChapterUnlockRow[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [totalPages, setTotalPages] = useState<number>(1);
  const [totalItems, setTotalItems] = useState<number>(0);
  const perPage = 10;

  const loadData = useCallback(async () => {
    setIsLoading(true);
    const res = await walletService.getChapterUnlocks(currentPage, perPage);
    if (res.status === 'success' && res.data) {
      setChapterUnlocks(res.data);
      if (res.meta) {
        setTotalPages((res.meta.total_pages as number) || 1);
        setTotalItems((res.meta.total as number) || res.data.length);
      }
    }
    setIsLoading(false);
  }, [currentPage]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  return (
    <div className="flex flex-col gap-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-4">
        <div>
          <h2 className="text-lg sm:text-xl font-bold font-serif text-[var(--text-primary)]">
            {t('unlocks.chaptersTitle')}
          </h2>
          <p className="text-xs text-[var(--text-secondary)] mt-0.5">
            {t('unlocks.chaptersSubtitle')}
          </p>
        </div>

        <span className="text-xs font-mono text-[var(--text-muted)] self-start sm:self-auto bg-[var(--bg-tertiary)] px-3 py-1.5 rounded-xl border border-[var(--border-color)]">
          {t('common.chaptersCount', { count: totalItems })}
        </span>
      </div>

      {/* List */}
      {isLoading ? (
        <div className="flex flex-col gap-3">
          {[1, 2, 3, 4].map((i) => (
            <div
              key={i}
              className="h-20 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl animate-pulse"
            />
          ))}
        </div>
      ) : chapterUnlocks.length === 0 ? (
        <div className="p-10 text-center border border-dashed border-[var(--border-color)] rounded-2xl flex flex-col items-center gap-3">
          <div className="w-12 h-12 rounded-full bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] flex items-center justify-center">
            <BookOpen className="w-6 h-6" />
          </div>
          <h3 className="font-serif font-bold text-sm text-[var(--text-primary)]">
            {t('unlocks.emptyChapters')}
          </h3>
          <p className="text-xs text-[var(--text-secondary)] max-w-md">
            {t('unlocks.emptyChaptersDesc')}
          </p>
        </div>
      ) : (
        <div className="flex flex-col gap-3">
          {chapterUnlocks.map((item) => (
            <div
              key={item.id}
              className="p-4 rounded-2xl bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)]/50 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xs group"
            >
              {/* Left Side: Series Cover & Chapter Details */}
              <div className="flex items-center gap-3.5 min-w-0 flex-1">
                <div className="w-12 h-16 rounded-xl overflow-hidden bg-[var(--bg-tertiary)] border border-[var(--border-color)] shrink-0">
                  {item.cover_image ? (
                    <img
                      src={item.cover_image}
                      alt={item.series_title || item.title}
                      referrerPolicy="no-referrer"
                      className="w-full h-full object-cover"
                    />
                  ) : (
                    <div className="w-full h-full bg-[var(--accent-color)] text-white font-bold flex items-center justify-center text-xs">
                      {(item.series_title || 'C').substring(0, 2).toUpperCase()}
                    </div>
                  )}
                </div>

                <div className="flex flex-col gap-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-serif font-bold text-sm text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors truncate">
                      {item.series_title || item.title}
                    </span>
                    <span className="px-2 py-0.5 rounded-lg text-[10px] font-mono font-bold bg-[var(--accent-light)] text-[var(--accent-color)] border border-[var(--accent-border)]">
                      {t('unlocks.chapterBadge', { number: item.chapter_number })}
                    </span>
                  </div>

                  {item.chapter_title && (
                    <span className="text-xs text-[var(--text-secondary)] truncate">
                      {item.chapter_title}
                    </span>
                  )}

                  <div className="flex items-center gap-4 text-xs font-mono text-[var(--text-muted)]">
                    <span className="flex items-center gap-1">
                      <Coins className="w-3 h-3 text-[var(--accent-color)]" />
                      {item.price_coin} {t('common.coins')}
                    </span>
                    <span className="flex items-center gap-1">
                      <Calendar className="w-3 h-3 text-[var(--text-muted)]" />
                      {formatRelativeTime(item.created_at)}
                    </span>
                  </div>
                </div>
              </div>

              {/* Right Side: Read Action */}
              <Link
                to={`/${item.type || 'manga'}/${item.series_slug || item.content_slug}/read/${item.chapter_number}`}
                className="self-end sm:self-center px-4 py-2 rounded-xl bg-[var(--accent-color)] text-white text-xs font-semibold hover:opacity-90 transition-opacity flex items-center gap-1.5 shadow-sm shrink-0"
              >
                <span>{t('unlocks.readAction')}</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </Link>
            </div>
          ))}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between border-t border-[var(--border-color)] pt-4">
          <span className="text-xs font-mono text-[var(--text-muted)]">
            {t('common.paginationLabel', { current: currentPage, total: totalPages })}
          </span>

          <div className="flex items-center gap-2">
            <button
              disabled={currentPage <= 1}
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
              className="p-2 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] text-[var(--text-primary)] disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>

            <button
              disabled={currentPage >= totalPages}
              onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
              className="p-2 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] text-[var(--text-primary)] disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
