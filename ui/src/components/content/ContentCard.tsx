import React from 'react';
import { Link } from 'react-router-dom';
import { Star, Eye, Lock } from 'lucide-react';
import { ContentSummary } from '../../types/api';
import { Badge } from '../ui/Badge';
import { usePreferences } from '../../contexts/PreferencesContext';

type ContentCardProps = {
  content: ContentSummary;
  rank?: number;
};

export const ContentCard: React.FC<ContentCardProps> = ({ content, rank }) => {
  const { t } = usePreferences();
  const rating = content.rating_avg ?? (content as any).rating_average ?? 0;
  const views = (content as any).total_views ?? (content.rating_count ? content.rating_count * 12 : 0);
  const latestChap = (content as any).latest_chapter ?? content.chapter_count;
  const contentTypeLabel = (content as any).content_type ?? content.type ?? 'manga';

  const authorName =
    typeof content.author === 'object' && content.author !== null
      ? content.author.name
      : typeof content.author === 'string'
      ? content.author
      : null;

  return (
    <div className="group relative bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-xl overflow-hidden transition-all duration-300 hover:shadow-xl hover:shadow-[var(--accent-color)]/10 flex flex-col h-full">
      {/* Cover Image Wrapper */}
      <Link to={`/${content.type}/${content.slug}`} className="relative aspect-[3/4] overflow-hidden bg-[var(--bg-tertiary)]">
        {content.cover_image ? (
          <img
            src={content.cover_image}
            alt={content.title}
            referrerPolicy="no-referrer"
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out"
            loading="lazy"
          />
        ) : (
          <div className="w-full h-full bg-[var(--bg-tertiary)] text-[var(--accent-color)] flex items-center justify-center font-serif text-3xl font-bold">
            {content.title ? content.title.substring(0, 1) : '?'}
          </div>
        )}

        {/* Gradient Overlay */}
        <div className="absolute inset-0 bg-gradient-to-t from-[var(--bg-card)] via-transparent to-transparent opacity-80 group-hover:opacity-40 transition-opacity" />

        {/* Top Badges */}
        <div className="absolute top-2 left-2 flex items-center gap-1.5 z-10 flex-wrap max-w-[calc(100%-44px)]">
          <Badge variant="gold" size="sm">
            {content.type}
          </Badge>
          {content.is_adult && (
            <span
              className="px-1.5 py-0.5 rounded-md bg-rose-600 text-white font-mono font-bold text-[10px] tracking-wider shadow-sm border border-rose-400/30"
              title={t('adult.longBadge')}
            >
              {t('adult.badge')}
            </span>
          )}
          {content.is_members_only && (
            <span
              className="px-1.5 py-0.5 rounded-md bg-purple-600 text-white font-mono font-bold text-[10px] tracking-wider shadow-sm border border-purple-400/30 flex items-center gap-0.5"
              title={t('membersOnly.badge')}
            >
              <Lock className="w-2.5 h-2.5 shrink-0" />
              <span className="hidden sm:inline">{t('membersOnly.shortBadge')}</span>
            </span>
          )}
          {content.status === 'ongoing' && (
            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0" title={t('browse.statusOngoing')} />
          )}
        </div>

        {/* Rank Overlay */}
        {rank !== undefined && (
          <div className="absolute top-2 right-2 w-7 h-7 rounded-lg bg-[var(--accent-color)] text-white font-bold text-xs flex items-center justify-center shadow-lg">
            #{rank}
          </div>
        )}

        {/* Stats overlay bottom */}
        <div className="absolute bottom-2 left-2 right-2 flex items-center justify-between text-[11px] text-[var(--text-primary)] font-mono z-10">
          <div className="flex items-center gap-1 bg-[var(--bg-card)]/80 backdrop-blur-md px-2 py-0.5 rounded border border-[var(--border-color)]">
            <Star className="w-3 h-3 text-amber-500 fill-amber-500" />
            <span>{(typeof rating === 'number' && !isNaN(rating) ? rating : 0).toFixed(1)}</span>
          </div>
          <div className="flex items-center gap-1 bg-[var(--bg-card)]/80 backdrop-blur-md px-2 py-0.5 rounded border border-[var(--border-color)] text-[var(--text-secondary)]">
            <Eye className="w-3 h-3" />
            <span>{((typeof views === 'number' && !isNaN(views) ? views : 0) / 1000).toFixed(0)}k</span>
          </div>
        </div>
      </Link>

      {/* Card Metadata */}
      <div className="p-3.5 flex flex-col flex-grow justify-between gap-2">
        <div>
          <Link to={`/${content.type}/${content.slug}`}>
            <h3 className="font-semibold text-sm font-serif text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors line-clamp-1">
              {content.title}
            </h3>
          </Link>
          {authorName && (
            <p className="text-xs text-[var(--text-secondary)] line-clamp-1 mt-0.5 font-light">
              {authorName}
            </p>
          )}
        </div>

        <div className="flex items-center justify-between pt-2 border-t border-[var(--border-color)] text-xs text-[var(--text-secondary)]">
          <span className="font-mono text-[11px] text-[var(--accent-color)]">
            {latestChap ? t('chapters.chapterNumber', { number: latestChap }) : t('common.comingSoon')}
          </span>
          <span className="text-[10px] uppercase tracking-wider text-[var(--text-muted)]">
            {String(contentTypeLabel).replace('_', ' ')}
          </span>
        </div>
      </div>
    </div>
  );
};
