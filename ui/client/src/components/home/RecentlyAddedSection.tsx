import React from 'react';
import { Sparkles } from 'lucide-react';
import { ContentSummary } from '../../types/api';
import { ContentCard } from '../content/ContentCard';
import { usePreferences } from '../../contexts/PreferencesContext';

type RecentlyAddedSectionProps = {
  items: ContentSummary[];
};

export const RecentlyAddedSection: React.FC<RecentlyAddedSectionProps> = ({ items }) => {
  const { t } = usePreferences();

  if (!items || items.length === 0) {
    return null;
  }

  return (
    <section className="my-8">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <div className="p-2 rounded-lg bg-[var(--accent-color)]/10 text-[var(--accent-color)]">
            <Sparkles className="w-5 h-5" />
          </div>
          <div>
            <h2 className="text-xl font-extrabold font-serif text-[var(--text-primary)] tracking-tight">
              {t('home.recentlyAddedTitle')}
            </h2>
            <p className="text-xs text-[var(--text-secondary)]">{t('home.recentlyAddedSubtitle')}</p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        {items.map((item) => (
          <ContentCard key={item.id} content={item} />
        ))}
      </div>
    </section>
  );
};

