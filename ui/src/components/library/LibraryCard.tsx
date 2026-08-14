import React from 'react';
import { Link } from 'react-router-dom';
import { Star, Play, Trash2, Heart, BookOpen, CheckCircle2 } from 'lucide-react';
import { LibraryItem } from '../../types/api';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { usePreferences } from '../../contexts/PreferencesContext';

type LibraryCardProps = {
  item: LibraryItem;
  onRemove: (id: string) => void;
  onToggleFollow?: (slug: string) => void;
};

export const LibraryCard: React.FC<LibraryCardProps> = ({
  item,
  onRemove,
  onToggleFollow,
}) => {
  const { t, formatDate } = usePreferences();
  const { content, user_state, added_at } = item;
  const progress = user_state.last_read_progress ?? 0;
  const hasStarted = user_state.last_read_chapter_number != null;
  const nextChapterNum = user_state.last_read_chapter_number || 1;
  const isCompleted = progress >= 100;

  const formattedDate = formatDate(added_at);

  return (
    <div
      id={`library-card-${item.id}`}
      className="group relative bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)]/60 rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-xl hover:shadow-[var(--accent-color)]/5 flex flex-col justify-between"
    >
      {/* Top Cover Media */}
      <div className="relative aspect-[3/4] overflow-hidden bg-[var(--bg-tertiary)]">
        <Link to={`/${content.type}/${content.slug}`} className="block w-full h-full">
          {content.cover ? (
            <img
              src={content.cover}
              alt={content.title}
              referrerPolicy="no-referrer"
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out"
              loading="lazy"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center font-serif text-3xl font-bold text-[var(--accent-color)]">
              {content.title.charAt(0)}
            </div>
          )}

          {/* Vignette Overlay */}
          <div className="absolute inset-0 bg-gradient-to-t from-[var(--bg-card)] via-transparent to-transparent opacity-85 group-hover:opacity-50 transition-opacity" />
        </Link>

        {/* Top Badges */}
        <div className="absolute top-2.5 left-2.5 flex items-center gap-1.5 z-10">
          <Badge variant="gold" size="sm" className="uppercase font-bold tracking-wider text-[10px]">
            {content.type}
          </Badge>
          {content.status?.toLowerCase() === 'completed' ? (
            <Badge variant="success" size="sm" className="text-[10px]">
              {t('browse.statusCompleted')}
            </Badge>
          ) : (
            <Badge variant="outline" size="sm" className="bg-[var(--bg-card)]/80 backdrop-blur-xs text-[10px]">
              {t('browse.statusOngoing')}
            </Badge>
          )}
        </div>

        {/* Top Right Actions: Follow Toggle & Remove from Library */}
        <div className="absolute top-2.5 right-2.5 flex items-center gap-1.5 z-10">
          {onToggleFollow && (
            <button
              type="button"
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onToggleFollow(content.slug);
              }}
              title={user_state.is_following ? t('content.unfollow') : t('content.follow')}
              className={`p-1.5 rounded-lg backdrop-blur-md transition-all cursor-pointer ${
                user_state.is_following
                  ? 'bg-rose-500/90 text-white hover:bg-rose-600'
                  : 'bg-[var(--bg-card)]/80 text-[var(--text-secondary)] hover:text-rose-500 hover:bg-[var(--bg-card)]'
              }`}
            >
              <Heart className={`w-3.5 h-3.5 ${user_state.is_following ? 'fill-current' : ''}`} />
            </button>
          )}

          <button
            type="button"
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              onRemove(item.id);
            }}
            title={t('library.removeFromLibrary')}
            className="p-1.5 rounded-lg bg-[var(--bg-card)]/80 backdrop-blur-md text-[var(--text-muted)] hover:text-rose-500 hover:bg-[var(--bg-card)] transition-colors cursor-pointer"
          >
            <Trash2 className="w-3.5 h-3.5" />
          </button>
        </div>

        {/* Bottom Rating & Progress info on Cover */}
        <div className="absolute bottom-2.5 left-2.5 right-2.5 flex items-center justify-between text-xs font-mono z-10">
          <div className="flex items-center gap-1 bg-[var(--bg-card)]/90 backdrop-blur-md px-2 py-0.5 rounded-md border border-[var(--border-color)] text-amber-500">
            <Star className="w-3 h-3 fill-amber-500" />
            <span className="font-bold text-[var(--text-primary)]">{(content.rating ?? 0).toFixed(1)}</span>
          </div>

          {hasStarted && (
            <div className="flex items-center gap-1 bg-[var(--bg-card)]/90 backdrop-blur-md px-2 py-0.5 rounded-md border border-[var(--border-color)] text-[var(--accent-color)] font-semibold text-[11px]">
              {isCompleted ? (
                <>
                  <CheckCircle2 className="w-3 h-3 text-emerald-500" />
                  <span className="text-emerald-500">%100</span>
                </>
              ) : (
                <span>%{progress}</span>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Body Details */}
      <div className="p-3.5 flex flex-col flex-1 justify-between gap-3">
        <div>
          <Link to={`/${content.type}/${content.slug}`}>
            <h3 className="font-serif font-bold text-sm text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors line-clamp-1">
              {content.title}
            </h3>
          </Link>
          <div className="flex items-center justify-between mt-1 text-[11px] text-[var(--text-muted)] font-mono">
            <span>{formattedDate}</span>
            {content.total_chapters && (
              <span>{t('content.chaptersCount', { count: content.total_chapters })}</span>
            )}
          </div>
        </div>

        {/* Reading Progress Bar (if started) */}
        {hasStarted ? (
          <div className="flex flex-col gap-1.5 pt-1">
            <div className="flex items-center justify-between text-[11px] font-mono">
              <span className="text-[var(--text-secondary)]">
                {t('library.lastRead')}: <strong className="text-[var(--text-primary)]">{t('chapters.chapterNumber', { number: nextChapterNum })}</strong>
              </span>
              <span className="text-[var(--accent-color)] font-semibold">%{progress}</span>
            </div>
            <div className="w-full h-1.5 bg-[var(--bg-tertiary)] rounded-full overflow-hidden">
              <div
                className={`h-full rounded-full transition-all duration-500 ${
                  isCompleted ? 'bg-emerald-500' : 'bg-[var(--accent-color)]'
                }`}
                style={{ width: `${Math.max(4, Math.min(100, progress))}%` }}
              />
            </div>
          </div>
        ) : (
          <div className="py-1 text-[11px] text-[var(--text-muted)] italic font-mono">
            {t('library.notStarted')}
          </div>
        )}

        {/* Primary CTA Button */}
        <div className="pt-2 border-t border-[var(--border-color)]">
          <Link
            to={`/${content.type}/${content.slug}/chapter/${nextChapterNum}`}
            className="w-full"
          >
            <Button
              variant={hasStarted ? 'primary' : 'outline'}
              size="sm"
              fullWidth
              className={`gap-1.5 cursor-pointer font-medium text-xs ${
                hasStarted ? 'bg-[var(--accent-color)] text-white hover:opacity-90' : ''
              }`}
            >
              {hasStarted ? (
                <>
                  <Play className="w-3.5 h-3.5 fill-current" />
                  <span>{t('home.resumeReading')} ({t('chapters.chapterNumber', { number: nextChapterNum })})</span>
                </>
              ) : (
                <>
                  <BookOpen className="w-3.5 h-3.5" />
                  <span>{t('content.startReading')}</span>
                </>
              )}
            </Button>
          </Link>
        </div>
      </div>
    </div>
  );
};
