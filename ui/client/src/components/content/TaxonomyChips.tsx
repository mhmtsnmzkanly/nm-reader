import React from 'react';
import { Link } from 'react-router-dom';
import { Genre, Tag } from '../../types/api';

type TaxonomyChipsProps = {
  genres?: Genre[];
  tags?: Tag[];
};

export const TaxonomyChips: React.FC<TaxonomyChipsProps> = ({ genres = [], tags = [] }) => {
  return (
    <div className="flex flex-wrap gap-2 my-2">
      {genres.map((g) => (
        <Link
          key={`genre-${g.id}`}
          to={`/genre/${g.slug}`}
          className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-[var(--accent-light)] text-[var(--accent-color)] border border-[var(--accent-border)] hover:opacity-90 transition-opacity"
        >
          <span>{g.name}</span>
          {g.content_count !== undefined && (
            <span className="text-[10px] opacity-70 font-mono">({g.content_count})</span>
          )}
        </Link>
      ))}
      {tags.map((t) => (
        <Link
          key={`tag-${t.id}`}
          to={`/tag/${t.slug}`}
          className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border border-[var(--border-color)] hover:text-[var(--text-primary)] transition-colors"
        >
          <span>#{t.name}</span>
          {t.content_count !== undefined && (
            <span className="text-[10px] opacity-70 font-mono">({t.content_count})</span>
          )}
        </Link>
      ))}
    </div>
  );
};
