import React from 'react';
import { Link } from 'react-router-dom';
import { Lock, CheckCircle, FileText, Image as ImageIcon, Sparkles } from 'lucide-react';
import { ContentDetailChapter, ContentType } from '../../types/api';
import { Badge } from '../ui/Badge';
import { usePreferences } from '../../contexts/PreferencesContext';

type ChapterRowProps = {
  chapter: ContentDetailChapter;
  contentType: ContentType;
  contentSlug: string;
  isLastRead?: boolean;
  onLockClick?: (chapter: ContentDetailChapter) => void;
};

export const ChapterRow: React.FC<ChapterRowProps> = ({
  chapter,
  contentType,
  contentSlug,
  isLastRead = false,
  onLockClick,
}) => {
  const { formatDate, t } = usePreferences();
  const isLocked = chapter.is_locked || (chapter.access && !chapter.access.granted);
  const chapNum = chapter.number ?? chapter.chapter_number ?? '1';
  const pubDate = chapter.published_at || chapter.created_at;

  const formattedDate = pubDate ? formatDate(pubDate) : '';

  const rowContent = (
    <div
      className={`group flex items-center justify-between p-3.5 sm:p-4 rounded-xl border transition-all duration-200 ${
        isLastRead
          ? 'bg-[var(--accent-light)]/40 border-[var(--accent-color)] shadow-sm'
          : isLocked
          ? 'bg-[var(--bg-card)] border-[var(--border-color)] hover:border-amber-500/50 hover:bg-[var(--bg-tertiary)]/50'
          : 'bg-[var(--bg-card)] border-[var(--border-color)] hover:border-[var(--accent-color)] hover:shadow-md'
      }`}
    >
      {/* Left Chapter Info */}
      <div className="flex items-center gap-3 min-w-0">
        <div
          className={`flex items-center justify-center p-2.5 rounded-xl border flex-shrink-0 ${
            isLastRead
              ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)]'
              : isLocked
              ? 'bg-amber-500/10 text-amber-500 border-amber-500/20'
              : 'bg-[var(--bg-tertiary)] text-[var(--accent-color)] border-[var(--border-color)]'
          }`}
        >
          {chapter.type === 'text' ? (
            <FileText className="w-4 h-4" />
          ) : (
            <ImageIcon className="w-4 h-4" />
          )}
        </div>

        <div className="flex flex-col gap-0.5 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <span
              className={`text-sm font-semibold font-serif transition-colors truncate ${
                isLastRead
                  ? 'text-[var(--accent-color)] font-bold'
                  : 'text-[var(--text-primary)] group-hover:text-[var(--accent-color)]'
              }`}
            >
              {t('content.chapter', { number: chapNum })}
            </span>

            {chapter.title && (
              <span className="text-xs text-[var(--text-secondary)] hidden sm:inline font-light truncate max-w-xs">
                — {chapter.title}
              </span>
            )}

            {isLastRead && (
              <Badge variant="gold" size="sm" className="gap-1 animate-pulse">
                <Sparkles className="w-3 h-3" />
                <span>{t('chapters.lastChapter')}</span>
              </Badge>
            )}
          </div>

          <div className="flex items-center gap-2 text-[11px] text-[var(--text-muted)] font-mono">
            {formattedDate && <span>{formattedDate}</span>}
            {chapter.title && (
              <span className="sm:hidden text-[var(--text-secondary)] truncate">
                {chapter.title}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Right Action / Price Badge */}
      <div className="flex items-center gap-2 flex-shrink-0 ml-2">
        {isLocked ? (
          <Badge
            variant="gold"
            className="gap-1.5 py-1 px-2.5 font-mono cursor-pointer hover:scale-105 transition-transform"
          >
            <Lock className="w-3.5 h-3.5 text-amber-500" />
            <span>{chapter.price_coin || 10} Coin</span>
          </Badge>
        ) : (
          <div className="flex items-center gap-1 text-xs font-mono text-emerald-500 font-semibold px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20">
            <CheckCircle className="w-3.5 h-3.5" />
            <span className="sm:hidden">{t('chapters.readAction')}</span>
          </div>
        )}
      </div>
    </div>
  );

  if (isLocked) {
    return (
      <div
        onClick={() => onLockClick && onLockClick(chapter)}
        className="cursor-pointer select-none"
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            onLockClick && onLockClick(chapter);
          }
        }}
      >
        {rowContent}
      </div>
    );
  }

  return (
    <Link to={`/${contentType}/${contentSlug}/chapter/${chapNum}`}>
      {rowContent}
    </Link>
  );
};
