import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Plus, Sparkles, TrendingUp, Filter, Tag as TagIcon, RotateCcw } from 'lucide-react';
import { blogService } from '../services';
import { BlogSummary, PaginationMeta } from '../types/api';
import { BlogCard } from '../components/blogs/BlogCard';
import { Button } from '../components/ui/Button';
import { Pagination } from '../components/feedback/Pagination';
import { usePreferences } from '../contexts/PreferencesContext';

export const BlogListPage: React.FC = () => {
  const { t } = usePreferences();
  const [searchParams, setSearchParams] = useSearchParams();

  const page = parseInt(searchParams.get('page') || '1', 10);
  const perPage = parseInt(searchParams.get('per_page') || '9', 10);
  const sort = (searchParams.get('sort') as 'latest' | 'popular') || 'latest';
  const tag = searchParams.get('tag') || '';

  const [blogs, setBlogs] = useState<BlogSummary[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const POPULAR_TAGS = [
    { id: 'all', name: t('common.activeAll'), slug: '' },
    { id: 'manga', name: 'Manga', slug: 'manga' },
    { id: 'manhwa', name: 'Manhwa', slug: 'manhwa' },
    { id: 'webtoon', name: 'Webtoon', slug: 'webtoon' },
    { id: 'novel', name: 'Light Novel', slug: 'novel' },
    { id: 'anime', name: 'Anime', slug: 'anime' },
    { id: 'writing', name: t('blog.tagWriting'), slug: 'writing' },
    { id: 'analysis', name: t('blog.tagAnalysis'), slug: 'analysis' },
    { id: 'recommendations', name: t('blog.tagRecommendations'), slug: 'recommendations' },
    { id: 'translation', name: t('blog.tagTranslation'), slug: 'translation' },
    { id: 'culture', name: t('blog.tagCulture'), slug: 'culture' },
    { id: 'scifi', name: t('blog.tagScifi'), slug: 'scifi' },
    { id: 'community', name: t('blog.tagCommunity'), slug: 'community' },
  ];

  useEffect(() => {
    const fetchBlogs = async () => {
      setIsLoading(true);
      const res = await blogService.getBlogs(page, perPage, sort, tag);
      if (res.status === 'success') {
        setBlogs(res.data);
        setMeta(res.meta as PaginationMeta);
      }
      setIsLoading(false);
    };

    fetchBlogs();
  }, [page, perPage, sort, tag]);

  const handlePageChange = (newPage: number) => {
    const params = new URLSearchParams(searchParams);
    params.set('page', newPage.toString());
    setSearchParams(params);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleSortChange = (newSort: 'latest' | 'popular') => {
    const params = new URLSearchParams(searchParams);
    params.set('sort', newSort);
    params.set('page', '1');
    setSearchParams(params);
  };

  const handleTagChange = (newTag: string) => {
    const params = new URLSearchParams(searchParams);
    if (newTag) {
      params.set('tag', newTag);
    } else {
      params.delete('tag');
    }
    params.set('page', '1');
    setSearchParams(params);
  };

  const clearFilters = () => {
    setSearchParams({});
  };

  const activeTagObj = POPULAR_TAGS.find((t) => t.slug === tag || t.id === tag);

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      {/* Header Banner */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-[var(--border-color)] pb-6">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
              {t('blog.communityBadge')}
            </span>
          </div>
          <h1 className="font-serif text-3xl sm:text-4xl font-bold text-[var(--text-primary)]">
            {t('blog.pageTitle')}
          </h1>
          <p className="text-sm text-[var(--text-secondary)] mt-1.5 max-w-2xl font-light">
            {t('blog.pageDesc')}
          </p>
        </div>

        <div className="flex items-center gap-3">
          <Link to="/my-blogs" className="hidden sm:block">
            <Button variant="outline" size="md" className="text-xs font-mono">
              {t('blog.myBlogs')}
            </Button>
          </Link>

          <Link to="/blogs/new">
            <Button
              id="new-blog-btn"
              variant="gold"
              size="md"
              className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90 shadow-sm"
            >
              <Plus className="w-4 h-4 text-white" />
              <span>{t('blog.newBlogBtn')}</span>
            </Button>
          </Link>
        </div>
      </div>

      {/* Filter & Sort Controls */}
      <div className="flex flex-col gap-4 bg-[var(--bg-card)] border border-[var(--border-color)] p-4 rounded-2xl shadow-sm">
        {/* Sort & Quick Filter Bar */}
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3.5">
          {/* Sort Tabs */}
          <div className="flex items-center gap-1.5 p-1 bg-[var(--bg-tertiary)] rounded-xl border border-[var(--border-color)]">
            <button
              id="sort-latest-btn"
              onClick={() => handleSortChange('latest')}
              className={`flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all cursor-pointer ${
                sort === 'latest'
                  ? 'bg-[var(--bg-card)] text-[var(--accent-color)] font-bold shadow-sm'
                  : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'
              }`}
            >
              <Sparkles className="w-3.5 h-3.5" />
              <span>{t('blog.sortLatest')}</span>
            </button>

            <button
              id="sort-popular-btn"
              onClick={() => handleSortChange('popular')}
              className={`flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all cursor-pointer ${
                sort === 'popular'
                  ? 'bg-[var(--bg-card)] text-[var(--accent-color)] font-bold shadow-sm'
                  : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'
              }`}
            >
              <TrendingUp className="w-3.5 h-3.5" />
              <span>{t('blog.sortPopular')}</span>
            </button>
          </div>

          {/* Active Filter Indicator / Reset */}
          {tag && (
            <div className="flex items-center gap-2 text-xs font-mono text-[var(--text-secondary)]">
              <span>{t('blog.filteredTagLabel')}</span>
              <span className="font-bold text-[var(--accent-color)] bg-[var(--accent-color)]/10 px-2.5 py-0.5 rounded-full border border-[var(--accent-color)]/20">
                #{activeTagObj ? activeTagObj.name : tag}
              </span>
              <button
                onClick={clearFilters}
                className="flex items-center gap-1 text-[var(--text-muted)] hover:text-rose-500 transition-colors ml-1 cursor-pointer"
                title={t('blog.clearTagFilter')}
              >
                <RotateCcw className="w-3 h-3" />
                <span>{t('common.clear')}</span>
              </button>
            </div>
          )}
        </div>

        {/* Tag Filters Horizontal Row */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
          <div className="flex items-center gap-1.5 text-xs text-[var(--text-muted)] font-mono shrink-0 mr-1">
            <TagIcon className="w-3.5 h-3.5 text-[var(--accent-color)]" />
            <span>{t('blog.tagsFilterLabel')}</span>
          </div>

          {POPULAR_TAGS.map((tItem) => {
            const isSelected = (!tag && tItem.slug === '') || tag === tItem.slug || tag === tItem.id;
            return (
              <button
                key={tItem.id}
                onClick={() => handleTagChange(tItem.slug)}
                className={`px-3 py-1 rounded-full text-xs font-mono whitespace-nowrap transition-all cursor-pointer border ${
                  isSelected
                    ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] font-bold shadow-sm'
                    : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:border-[var(--accent-color)]/50 hover:text-[var(--text-primary)]'
                }`}
              >
                {tItem.name}
              </button>
            );
          })}
        </div>
      </div>

      {/* Blog Cards Grid */}
      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {[...Array(perPage)].map((_, i) => (
            <div
              key={i}
              className="h-80 bg-[var(--bg-tertiary)] rounded-2xl animate-pulse border border-[var(--border-color)]"
            />
          ))}
        </div>
      ) : blogs.length === 0 ? (
        <div className="p-12 text-center flex flex-col items-center justify-center gap-3 text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl bg-[var(--bg-card)]">
          <Filter className="w-8 h-8 text-[var(--accent-color)] opacity-60 mb-1" />
          <p className="text-sm font-sans font-medium text-[var(--text-primary)]">
            {t('blog.noBlogsFound')}
          </p>
          <p className="text-xs text-[var(--text-secondary)]">
            {t('blog.noBlogsSuggestion')}
          </p>
          {tag && (
            <Button variant="outline" size="sm" onClick={clearFilters} className="mt-2">
              {t('blog.clearFilters')}
            </Button>
          )}
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {blogs.map((b) => (
              <BlogCard key={b.id} blog={b} />
            ))}
          </div>

          {/* Pagination */}
          {meta && meta.total_pages > 1 && (
            <div className="pt-4 flex justify-center">
              <Pagination
                currentPage={meta.page || page}
                totalPages={meta.total_pages}
                total={meta.total}
                perPage={meta.per_page || perPage}
                onPageChange={handlePageChange}
              />
            </div>
          )}
        </>
      )}
    </div>
  );
};
