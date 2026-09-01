import React from 'react';
import { User, Palette, Calendar, Globe, Star, Eye, BookOpen, Layers } from 'lucide-react';
import { ContentDetail } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

type ContentMetadataProps = {
  content: ContentDetail;
  chapterCount: number;
};

export const ContentMetadata: React.FC<ContentMetadataProps> = ({ content, chapterCount }) => {
  const { t } = usePreferences();

  const authorName =
    typeof content.author === 'object' && content.author !== null
      ? content.author.name
      : typeof content.author === 'string'
      ? content.author
      : t('contentMetadata.unknown');

  const artistName =
    typeof content.artist === 'object' && content.artist !== null
      ? content.artist.name
      : typeof content.artist === 'string'
      ? content.artist
      : null;

  const ratingAvg =
    typeof content.rating === 'object' && content.rating !== null
      ? content.rating.average
      : typeof content.rating === 'number'
      ? content.rating
      : content.rating_avg ?? content.rating_average ?? 0;

  const ratingCount =
    typeof content.rating === 'object' && content.rating !== null
      ? content.rating.count
      : content.rating_count ?? 0;

  const viewCount =
    content.views ??
    content.total_views ??
    (content.rating_count ? content.rating_count * 28 : 12400);

  const statusLabel =
    content.status?.toLowerCase() === 'completed'
      ? t('contentMetadata.statusCompleted')
      : content.status?.toLowerCase() === 'hiatus'
      ? t('contentMetadata.statusHiatus')
      : t('contentMetadata.statusOngoing');

  const metaItems = [
    {
      icon: <User className="w-4 h-4 text-[var(--accent-color)]" />,
      label: t('contentMetadata.author'),
      value: authorName,
    },
    ...(artistName
      ? [
          {
            icon: <Palette className="w-4 h-4 text-[var(--accent-color)]" />,
            label: t('contentMetadata.artist'),
            value: artistName,
          },
        ]
      : []),
    {
      icon: <Layers className="w-4 h-4 text-[var(--accent-color)]" />,
      label: t('contentMetadata.typeFormat'),
      value: content.type?.toUpperCase() || 'MANGA',
    },
    {
      icon: <Globe className="w-4 h-4 text-[var(--accent-color)]" />,
      label: t('contentMetadata.status'),
      value: statusLabel,
    },
    ...(content.release_year
      ? [
          {
            icon: <Calendar className="w-4 h-4 text-[var(--accent-color)]" />,
            label: t('contentMetadata.releaseYear'),
            value: String(content.release_year),
          },
        ]
      : []),
    {
      icon: <Star className="w-4 h-4 text-amber-500 fill-amber-500" />,
      label: t('contentMetadata.score'),
      value: t('contentMetadata.scoreValue', {
        score: ratingAvg.toFixed(1),
        count: ratingCount.toLocaleString(),
      }),
    },
    {
      icon: <Eye className="w-4 h-4 text-sky-500" />,
      label: t('contentMetadata.views'),
      value: viewCount.toLocaleString(),
    },
    {
      icon: <BookOpen className="w-4 h-4 text-emerald-500" />,
      label: t('contentMetadata.chapterCount'),
      value: t('contentMetadata.chapterCountValue', {
        count: chapterCount || content.chapter_count || 0,
      }),
    },
  ];

  return (
    <div className="bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-5 sm:p-6 flex flex-col gap-4 shadow-sm transition-colors duration-300">
      <h3 className="font-serif text-lg font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-3">
        {t('contentMetadata.title')}
      </h3>

      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 text-xs font-mono">
        {metaItems.map((item, idx) => (
          <div
            key={idx}
            className="flex flex-col gap-1 p-3 rounded-xl bg-[var(--bg-tertiary)]/50 border border-[var(--border-color)]/60 min-w-0"
          >
            <div className="flex items-center gap-1.5 text-[var(--text-muted)] text-[11px]">
              {item.icon}
              <span className="truncate">{item.label}</span>
            </div>
            <span className="font-medium text-[var(--text-primary)] truncate font-sans text-xs">
              {item.value}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
};
