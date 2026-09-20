import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { contentService } from '../services';
import { Tag } from '../types/api';
import { usePreferences } from '../contexts/PreferencesContext';
import { TaxonomyIcon, taxonomyColor } from '../components/taxonomy/TaxonomyIcon';

function getBootstrapTags(): Tag[] | null {
  if (typeof window === 'undefined') return null;
  const page = window.__NMR_CONTEXT?.current_page;
  const items = page?.data?.items;
  return page?.route === 'tags' && Array.isArray(items) ? items as Tag[] : null;
}

export const TagDirectoryPage: React.FC = () => {
  const { t } = usePreferences();
  const [bootstrapTags] = useState(getBootstrapTags);
  const [tags, setTags] = useState<Tag[]>(bootstrapTags || []);
  const [isLoading, setIsLoading] = useState(bootstrapTags === null);

  useEffect(() => {
    const fetchTags = async () => {
      if (bootstrapTags !== null) {
        setIsLoading(false);
        return;
      }
      setIsLoading(true);
      const res = await contentService.getTags();
      if (res.status === 'success') {
        setTags(res.data);
      }
      setIsLoading(false);
    };

    fetchTags();
  }, [bootstrapTags]);

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <div className="border-b border-[var(--border-color)] pb-6">
        <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
          {t('navigation.tags')}
        </span>
        <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)]">
          {t('tag.directoryTitle')}
        </h1>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          {[...Array(8)].map((_, i) => (
            <div key={i} className="h-20 bg-[var(--bg-tertiary)] rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          {tags.map((tag) => {
            const color = taxonomyColor(tag.ui_config?.color);
            return (
              <Link
                key={tag.id}
                to={`/tag/${tag.slug}`}
                className="p-4 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl transition-all hover:-translate-y-0.5 hover:shadow-xl group flex items-center gap-3"
                style={{ '--taxonomy-color': color } as React.CSSProperties}
              >
                <span
                  className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-transform group-hover:rotate-3 group-hover:scale-110"
                  style={{ color, backgroundColor: `${color}18` }}
                >
                  <TaxonomyIcon name={tag.ui_config?.icon} className="h-5 w-5" />
                </span>
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                  <span className="truncate font-mono text-sm font-semibold text-[var(--text-primary)] transition-colors group-hover:text-[var(--taxonomy-color)]">
                    #{tag.name}
                  </span>
                  <span className="text-[10px] text-[var(--text-muted)]">
                    {t('common.contentsCount', { count: tag.content_count || 0 })}
                  </span>
                </div>
                <ChevronRight className="w-4 h-4 text-[var(--text-muted)] transition-all group-hover:translate-x-1 group-hover:text-[var(--taxonomy-color)]" />
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
};
