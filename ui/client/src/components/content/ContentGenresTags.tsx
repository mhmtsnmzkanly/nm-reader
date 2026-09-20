import React from 'react';
import { Link } from 'react-router-dom';
import { Sparkles, Hash } from 'lucide-react';
import { Genre, Tag } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';
import { TaxonomyIcon, taxonomyColor } from '../taxonomy/TaxonomyIcon';

type ContentGenresTagsProps = {
  genres?: Genre[];
  tags?: Tag[];
};

const TaxonomyPill: React.FC<{
  item: Genre | Tag;
  path: 'genre' | 'tag';
}> = ({ item, path }) => {
  const color = taxonomyColor(item.ui_config?.color);
  return (
    <Link
      to={`/${path}/${item.slug}`}
      className="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-semibold shadow-2xs transition-all hover:-translate-y-0.5 hover:shadow-sm"
      style={{ color, borderColor: `${color}55`, backgroundColor: `${color}12` }}
    >
      <TaxonomyIcon name={item.ui_config?.icon} className="h-3.5 w-3.5" />
      <span>{path === 'tag' ? '#' : ''}{item.name}</span>
      {item.content_count !== undefined && (
        <span className="text-[10px] opacity-70 font-mono">({item.content_count})</span>
      )}
    </Link>
  );
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
            {genres.map((genre) => (
              <TaxonomyPill key={`genre-${genre.id || genre.slug}`} item={genre} path="genre" />
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
            {tags.map((tag) => (
              <TaxonomyPill key={`tag-${tag.id || tag.slug}`} item={tag} path="tag" />
            ))}
          </div>
        </div>
      )}
    </div>
  );
};
