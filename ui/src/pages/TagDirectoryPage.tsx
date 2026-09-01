import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { contentService } from '../services';
import { Tag } from '../types/api';
import { usePreferences } from '../contexts/PreferencesContext';

export const TagDirectoryPage: React.FC = () => {
  const { t } = usePreferences();
  const [tags, setTags] = useState<Tag[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchTags = async () => {
      setIsLoading(true);
      const res = await contentService.getTags();
      if (res.status === 'success') {
        setTags(res.data);
      }
      setIsLoading(false);
    };

    fetchTags();
  }, []);

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
          {tags.map((tag) => (
            <Link
              key={tag.id}
              to={`/tag/${tag.slug}`}
              className="p-4 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl transition-all hover:shadow-xl group flex items-center justify-between shadow-sm"
            >
              <div className="flex flex-col gap-0.5">
                <span className="font-mono text-sm font-semibold text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors">
                  #{tag.name}
                </span>
                <span className="text-[10px] text-[var(--text-muted)]">
                  {t('common.contentsCount', { count: tag.content_count || 0 })}
                </span>
              </div>
              <ChevronRight className="w-4 h-4 text-[var(--text-muted)] group-hover:text-[var(--accent-color)] transition-colors" />
            </Link>
          ))}
        </div>
      )}
    </div>
  );
};
