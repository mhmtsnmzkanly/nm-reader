import React from 'react';
import { Flame } from 'lucide-react';
import { HomeBlogItem } from '../../types/api';
import { BlogCard } from '../blogs/BlogCard';
import { usePreferences } from '../../contexts/PreferencesContext';

type PopularBlogsSectionProps = {
  blogs: HomeBlogItem[];
};

export const PopularBlogsSection: React.FC<PopularBlogsSectionProps> = ({ blogs }) => {
  const { t } = usePreferences();

  if (!blogs || blogs.length === 0) {
    return null;
  }

  return (
    <section className="my-8">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <div className="p-2 rounded-lg bg-amber-500/10 text-amber-500">
            <Flame className="w-5 h-5" />
          </div>
          <div>
            <h2 className="text-xl font-extrabold font-serif text-[var(--text-primary)] tracking-tight">
              {t('home.popularBlogsTitle')}
            </h2>
            <p className="text-xs text-[var(--text-secondary)]">{t('home.popularBlogsSubtitle')}</p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        {blogs.map((blog) => (
          <BlogCard key={blog.id} blog={blog as any} />
        ))}
      </div>
    </section>
  );
};

