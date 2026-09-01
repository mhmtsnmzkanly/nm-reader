import React from 'react';
import { Link } from 'react-router-dom';
import { Sparkles, Hash } from 'lucide-react';
import { Genre, Tag } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

type ContentGenresTagsProps = {
  genres?: Genre[];
  tags?: Tag[];
};

export const ContentGenresTags: React.FC<ContentGenresTagsProps> = ({
  genres = [],
  tags = [],
}) => {
  const { t } = usePreferences();
  const hasGenres = genres && genres.length > 0;
  const hasTags = tags && tags.length > 0;

  if (!hasGenres && !hasTags) return null;

  return (
    <div className="bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-5 sm:p-6 flex flex-col gap-5 shadow-sm transition-colors duration-300">
      {/* Genres Section */}
      {hasGenres && (
        <div className="flex flex-col gap-2.5">
          <div className="flex items-center gap-2 text-xs font-mono font-semibold uppercase tracking-wider text-[var(--accent-color)]">
            <Sparkles className="w-3.5 h-3.5" />
            <span>{t('common.genres')}</span>
          </div>

          <div className="flex flex-wrap gap-2">
            {genres.map((g) => (
              <Link
                key={`genre-${g.id || g.slug}`}
                to={`/genre/${g.slug}`}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-[var(--accent-light)] text-[var(--accent-color)] border border-[var(--accent-border)] hover:opacity-90 hover:scale-102 transition-all shadow-2xs"
              >
                <span>{g.name}</span>
                {g.content_count !== undefined && (
                  <span className="text-[10px] opacity-70 font-mono">({g.content_count})</span>
                )}
              </Link>
            ))}
          </div>
        </div>
      )}

      {/* Tags Section */}
      {hasTags && (
        <div className="flex flex-col gap-2.5 pt-3 border-t border-[var(--border-color)]">
          <div className="flex items-center gap-2 text-xs font-mono font-semibold uppercase tracking-wider text-[var(--text-muted)]">
            <Hash className="w-3.5 h-3.5" />
            <span>{t('common.tags')}</span>
          </div>

          <div className="flex flex-wrap gap-2">
            {tags.map((item) => (
              <Link
                key={`tag-${item.id || item.slug}`}
                to={`/tag/${item.slug}`}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border border-[var(--border-color)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-all shadow-2xs"
              >
                <span>#{item.name}</span>
                {item.content_count !== undefined && (
                  <span className="text-[10px] opacity-70 font-mono">({item.content_count})</span>
                )}
              </Link>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};
