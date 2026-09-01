import React from 'react';
import { Link } from 'react-router-dom';
import { Bookmark, Sparkles } from 'lucide-react';
import { ContentSummary } from '../../types/api';
import { ContentCard } from '../content/ContentCard';
import { EmptyState } from '../feedback/EmptyState';
import { usePreferences } from '../../contexts/PreferencesContext';

type ProfileLibraryGridProps = {
  items?: ContentSummary[];
  limit?: number;
  showViewAll?: boolean;
  emptyTitle?: string;
  emptyDescription?: string;
  className?: string;
};

export const ProfileLibraryGrid: React.FC<ProfileLibraryGridProps> = ({
  items = [],
  limit,
  showViewAll = true,
  emptyTitle,
  emptyDescription,
  className = '',
}) => {
  const { t } = usePreferences();
  const displayItems = limit ? items.slice(0, limit) : items;

  const resolvedEmptyTitle = emptyTitle || t('profile.emptyLibraryTitle');
  const resolvedEmptyDesc = emptyDescription || t('profile.emptyLibraryDesc');

  if (items.length === 0) {
    return (
      <EmptyState
        icon={<Bookmark className="w-10 h-10 text-[var(--accent-color)]" />}
        title={resolvedEmptyTitle}
        description={resolvedEmptyDesc}
      />
    );
  }

  return (
    <div className={`flex flex-col gap-6 ${className}`}>
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        {displayItems.map((item) => (
          <ContentCard key={item.id} content={item} />
        ))}
      </div>

      {showViewAll && items.length > 0 && (
        <div className="flex justify-center pt-2">
          <Link
            to="/library"
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-[var(--accent-color)] text-xs font-mono font-semibold text-[var(--text-primary)] hover:text-[var(--accent-color)] transition-all"
          >
            <Sparkles className="w-3.5 h-3.5 text-[var(--accent-color)]" />
            <span>{t('profile.viewEntireLibrary', { count: items.length })}</span>
          </Link>
        </div>
      )}
    </div>
  );
};
