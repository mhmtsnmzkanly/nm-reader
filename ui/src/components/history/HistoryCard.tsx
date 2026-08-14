import React from 'react';
import { Link } from 'react-router-dom';
import { Clock, Play, Trash2, BookOpen, CheckCircle2, Star } from 'lucide-react';
import { ReadingHistoryItem } from '../../types/api';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { usePreferences } from '../../contexts/PreferencesContext';

type HistoryCardProps = {
  item: ReadingHistoryItem;
  onRemove: (id: string) => void;
};

export const HistoryCard: React.FC<HistoryCardProps> = ({ item, onRemove }) => {
  const { formatDate, formatRelativeTime, t } = usePreferences();
  const contentSlug = item.content?.slug || item.content_slug || '';
  const contentType = item.content?.type || item.content_type || 'manga';
  const contentTitle = item.content?.title || item.content_title || 'Başlıksız Seri';
  const coverImage = item.content?.cover || item.content_cover_image || item.series?.cover;
  const rating = item.content?.rating;

  const chapId = item.chapter?.id || item.chapter_id;
  const chapNum = item.chapter?.number ?? item.chapter_number ?? 1;
  const chapTitle = item.chapter?.title ?? item.chapter_title;
  const progress = item.progress ?? 0;
  const isCompleted = progress >= 100;
  const lastPage = item.last_page;
  const totalPages = item.total_pages;

  const formattedDate = formatDate(item.read_at, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <div
      id={`history-card-${item.id}`}
      className="p-4 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)]/60 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 transition-all duration-200 hover:shadow-md"
    >
      {/* Left Column: Cover & Information */}
      <div className="flex items-center gap-4 min-w-0 flex-1">
        {/* Cover Thumbnail */}
        <Link
          to={`/${contentType}/${contentSlug}`}
          className="relative w-16 h-22 sm:w-18 sm:h-24 flex-shrink-0 rounded-xl overflow-hidden bg-[var(--bg-tertiary)] border border-[var(--border-color)] group"
        >
          {coverImage ? (
            <img
              src={coverImage}
              alt={contentTitle}
              referrerPolicy="no-referrer"
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center font-serif text-lg font-bold text-[var(--accent-color)]">
              {contentTitle.charAt(0)}
            </div>
          )}
          <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity" />
        </Link>

        {/* Content Info */}
        <div className="flex flex-col gap-1.5 min-w-0 flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <Badge variant="gold" size="sm" className="uppercase font-bold tracking-wider text-[9px] px-1.5 py-0.5">
              {contentType}
            </Badge>
            {rating && (
              <span className="flex items-center gap-1 text-[11px] font-mono text-amber-500 font-semibold">
                <Star className="w-3 h-3 fill-amber-500" />
                {rating.toFixed(1)}
              </span>
            )}
            <span className="text-[11px] font-mono text-[var(--text-muted)] flex items-center gap-1 ml-auto sm:ml-0">
              <Clock className="w-3 h-3 text-[var(--accent-color)]" />
              {formattedDate}
            </span>
          </div>

          {/* Title */}
          <Link
            to={`/${contentType}/${contentSlug}`}
            className="font-serif font-bold text-base text-[var(--text-primary)] hover:text-[var(--accent-color)] transition-colors truncate"
          >
            {contentTitle}
          </Link>

          {/* Chapter Details */}
          <div className="flex items-center gap-2 text-xs text-[var(--text-secondary)]">
            <span className="font-mono font-semibold text-[var(--text-primary)]">
              {t('chapters.chapterNumber', { number: chapNum })}
            </span>
            {chapTitle && (
              <span className="truncate text-[var(--text-muted)] font-normal">
                — {chapTitle}
              </span>
            )}
          </div>

          {/* Progress Bar & Page count */}
          <div className="flex items-center gap-3 mt-1 max-w-xs">
            <div className="flex-1 h-1.5 bg-[var(--bg-tertiary)] rounded-full overflow-hidden">
              <div
                className={`h-full rounded-full transition-all duration-500 ${
                  isCompleted ? 'bg-emerald-500' : 'bg-[var(--accent-color)]'
                }`}
                style={{ width: `${Math.max(4, Math.min(100, progress))}%` }}
              />
            </div>
            <span className="font-mono text-[11px] font-semibold text-[var(--accent-color)] whitespace-nowrap">
              {isCompleted ? (
                <span className="text-emerald-500 flex items-center gap-1">
                  <CheckCircle2 className="w-3 h-3" /> %100
                </span>
              ) : (
                <>%{progress} {lastPage && totalPages ? `(${lastPage}/${totalPages} ${t('reader.page') || 'p'})` : ''}</>
              )}
            </span>
          </div>
        </div>
      </div>

      {/* Right Column: Actions */}
      <div className="flex items-center gap-2 w-full sm:w-auto justify-end pt-2 sm:pt-0 border-t sm:border-t-0 border-[var(--border-color)]">
        <Link
          to={`/${contentType}/${contentSlug}/chapter/${chapNum}`}
          className="flex-1 sm:flex-initial"
        >
          <Button
            variant="primary"
            size="sm"
            className="w-full sm:w-auto gap-1.5 bg-[var(--accent-color)] text-white hover:opacity-90 cursor-pointer font-medium text-xs shadow-sm"
          >
            <Play className="w-3.5 h-3.5 fill-current" />
            <span>{t('home.resumeReading')}</span>
          </Button>
        </Link>

        <Button
          variant="outline"
          size="sm"
          onClick={() => onRemove(item.id)}
          title={t('common.delete')}
          className="text-[var(--text-muted)] hover:text-rose-500 hover:border-rose-300 dark:hover:border-rose-900 cursor-pointer p-2"
        >
          <Trash2 className="w-4 h-4" />
        </Button>
      </div>
    </div>
  );
};
