import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  BookOpen,
  Calendar,
  Coins,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  Sparkles,
} from 'lucide-react';
import { walletService } from '../../services';
import { SeriesUnlockRow } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

export const UnlockedSeriesList: React.FC = () => {
  const { t, formatRelativeTime } = usePreferences();
  const [seriesUnlocks, setSeriesUnlocks] = useState<SeriesUnlockRow[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [totalPages, setTotalPages] = useState<number>(1);
  const [totalItems, setTotalItems] = useState<number>(0);
  const perPage = 8;

  const loadData = useCallback(async () => {
    setIsLoading(true);
    const res = await walletService.getSeriesUnlocks(currentPage, perPage);
    if (res.status === 'success' && res.data) {
      setSeriesUnlocks(res.data);
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
            {t('unlocks.seriesTitle')}
          </h2>
          <p className="text-xs text-[var(--text-secondary)] mt-0.5">
            {t('unlocks.seriesSubtitle')}
          </p>
        </div>

        <span className="text-xs font-mono text-[var(--text-muted)] self-start sm:self-auto bg-[var(--bg-tertiary)] px-3 py-1.5 rounded-xl border border-[var(--border-color)]">
          {t('common.seriesCount', { count: totalItems })}
        </span>
      </div>

      {/* Grid */}
      {isLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <div
              key={i}
              className="h-64 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl animate-pulse"
            />
          ))}
        </div>
      ) : seriesUnlocks.length === 0 ? (
        <div className="p-10 text-center border border-dashed border-[var(--border-color)] rounded-2xl flex flex-col items-center gap-3">
          <div className="w-12 h-12 rounded-full bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] flex items-center justify-center">
            <BookOpen className="w-6 h-6" />
          </div>
          <h3 className="font-serif font-bold text-sm text-[var(--text-primary)]">
            {t('unlocks.emptySeries')}
          </h3>
          <p className="text-xs text-[var(--text-secondary)] max-w-md">
            {t('unlocks.emptySeriesDesc')}
          </p>
          <Link
            to="/browse"
            className="mt-2 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[var(--accent-color)] text-white text-xs font-semibold hover:opacity-90 transition-opacity"
          >
            <span>{t('common.exploreNew')}</span>
            <ExternalLink className="w-3.5 h-3.5" />
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {seriesUnlocks.map((item) => (
            <div
              key={item.id}
              className="bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xs hover:border-[var(--accent-color)]/50 transition-all flex flex-col justify-between group"
            >
              {/* Cover & Type Badge */}
              <div className="relative aspect-[3/4] w-full bg-[var(--bg-tertiary)] overflow-hidden">
                {item.cover_image ? (
                  <img
                    src={item.cover_image}
                    alt={item.title || item.series_title}
                    referrerPolicy="no-referrer"
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                ) : (
                  <div className="w-full h-full flex items-center justify-center bg-[var(--accent-light)] text-[var(--accent-color)] font-serif font-bold text-xl">
                    {(item.title || item.series_title || 'S').substring(0, 2).toUpperCase()}
                  </div>
                )}

                <div className="absolute top-2 left-2 flex items-center gap-1.5">
                  <span className="px-2 py-0.5 rounded-lg text-[10px] font-mono font-bold uppercase bg-black/60 text-white backdrop-blur-xs border border-white/10">
                    {item.type || 'manga'}
                  </span>
                </div>

                <div className="absolute top-2 right-2">
                  <span className="px-2 py-0.5 rounded-lg text-[10px] font-mono font-bold bg-emerald-500 text-white shadow-xs flex items-center gap-1">
                    <Sparkles className="w-2.5 h-2.5" />
                    {t('common.unlocked')}
                  </span>
                </div>
              </div>

              {/* Body */}
              <div className="p-3.5 flex flex-col gap-2 flex-1 justify-between">
                <div>
                  <h3 className="font-serif font-bold text-sm text-[var(--text-primary)] line-clamp-1 group-hover:text-[var(--accent-color)] transition-colors">
                    {item.title || item.series_title || item.content_slug}
                  </h3>

                  <div className="flex items-center justify-between text-[11px] text-[var(--text-muted)] font-mono mt-1">
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

                <Link
                  to={`/${item.type || 'manga'}/${item.content_slug || item.series_slug}`}
                  className="w-full mt-2 py-2 px-3 rounded-xl bg-[var(--bg-tertiary)] hover:bg-[var(--accent-color)] text-[var(--text-secondary)] hover:text-white text-xs font-semibold text-center transition-colors flex items-center justify-center gap-1.5"
                >
                  <span>{t('unlocks.viewSeries')}</span>
                  <ExternalLink className="w-3.5 h-3.5" />
                </Link>
              </div>
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
