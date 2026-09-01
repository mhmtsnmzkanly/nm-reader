import React from 'react';
import { Link } from 'react-router-dom';
import { Clock, Play, BookOpen } from 'lucide-react';
import { ReadingHistoryItem } from '../../types/api';
import { EmptyState } from '../feedback/EmptyState';
import { usePreferences } from '../../contexts/PreferencesContext';

type RecentlyReadListProps = {
  items?: ReadingHistoryItem[];
  limit?: number;
  showViewAll?: boolean;
  className?: string;
};

export const RecentlyReadList: React.FC<RecentlyReadListProps> = ({
  items = [],
  limit,
  showViewAll = true,
  className = '',
}) => {
  const { t, formatRelativeTime } = usePreferences();
  const displayItems = limit ? items.slice(0, limit) : items;

  if (items.length === 0) {
    return (
      <EmptyState
        icon={<BookOpen className="w-10 h-10 text-[var(--accent-color)]" />}
        title={t('profile.emptyHistoryTitle')}
        description={t('profile.emptyHistoryDesc')}
      />
    );
  }

  return (
    <div className={`flex flex-col gap-3 ${className}`}>
      {displayItems.map((item) => {
        const cover = item.content_cover_image || item.series?.cover;
        const title = item.content_title || item.series?.title || t('common.untitledSeries');
        const slug = item.content_slug || item.series?.slug || '';
        const contentType = item.content_type || item.series?.type || 'manga';
        const chapterNum = item.chapter_number || item.chapter?.number || '1';
        const chapterTitle = item.chapter_title || item.chapter?.title;
        const progress = item.progress ?? 50;
        const readAt = formatRelativeTime(item.read_at);
        const readerLink = `/${contentType}/${slug}/chapter/${chapterNum}`;
        const seriesLink = `/${contentType}/${slug}`;

        return (
          <div
            key={item.id || item.chapter_id || `${slug}-${chapterNum}`}
            className="group p-4 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 transition-all shadow-xs"
          >
            {/* Series Info */}
            <div className="flex items-center gap-3.5 min-w-0">
              <Link to={seriesLink} className="relative shrink-0 overflow-hidden rounded-xl">
                {cover ? (
                  <img
                    src={cover}
                    alt={title}
                    referrerPolicy="no-referrer"
                    className="w-14 h-20 object-cover group-hover:scale-105 transition-transform duration-300 border border-[var(--border-color)]"
                  />
                ) : (
                  <div className="w-14 h-20 bg-[var(--bg-tertiary)] rounded-xl border border-[var(--border-color)] flex items-center justify-center text-[var(--accent-color)] font-serif font-bold text-lg">
                    {title.substring(0, 1)}
                  </div>
                )}
              </Link>

              <div className="flex flex-col min-w-0">
                <Link
                  to={seriesLink}
                  className="font-serif text-sm font-bold text-[var(--text-primary)] hover:text-[var(--accent-color)] truncate transition-colors"
                >
                  {title}
                </Link>

                <div className="flex items-center gap-2 mt-1">
                  <span className="px-2 py-0.5 rounded-md bg-[var(--accent-light)] text-[var(--accent-color)] text-[11px] font-bold font-mono">
                    {t('chapters.chapterNumber', { number: chapterNum })}
                  </span>
                  {chapterTitle && (
                    <span className="text-xs text-[var(--text-secondary)] truncate hidden md:inline">
                      {chapterTitle}
                    </span>
                  )}
                </div>

                <div className="flex items-center gap-3 mt-2 text-[11px] font-mono text-[var(--text-muted)]">
                  <span className="flex items-center gap-1">
                    <Clock className="w-3.5 h-3.5 text-[var(--accent-color)]" />
                    {readAt}
                  </span>
                  <span>•</span>
                  <span>{t('library.progress')}: %{progress}</span>
                </div>

                {/* Progress Bar */}
                <div className="w-36 sm:w-44 h-1.5 bg-[var(--bg-tertiary)] rounded-full mt-2 overflow-hidden">
                  <div
                    className="h-full bg-[var(--accent-color)] rounded-full transition-all duration-500"
                    style={{ width: `${Math.min(100, Math.max(5, progress))}%` }}
                  />
                </div>
              </div>
            </div>

            {/* Read CTA Button */}
            <div className="w-full sm:w-auto flex justify-end shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-[var(--border-color)]/50">
              <Link
                to={readerLink}
                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[var(--accent-color)] hover:opacity-90 text-white font-bold uppercase tracking-wider text-xs transition-all active:scale-95 shadow-sm shadow-[var(--accent-color)]/20"
              >
                <Play className="w-3.5 h-3.5 fill-current" />
                <span>{t('home.resumeReading')}</span>
              </Link>
            </div>
          </div>
        );
      })}

      {showViewAll && items.length > 0 && (
        <div className="pt-2 flex justify-center">
          <Link
            to="/history"
            className="text-xs font-mono font-semibold text-[var(--accent-color)] hover:underline flex items-center gap-1"
          >
            {t('profile.viewAllHistory')} →
          </Link>
        </div>
      )}
    </div>
  );
};
