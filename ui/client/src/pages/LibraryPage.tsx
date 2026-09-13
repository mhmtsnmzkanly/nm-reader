import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Bookmark, ArrowUpDown, Compass, AlertCircle } from 'lucide-react';
import { userService, contentService } from '../services';
import { LibraryItem, ContentType } from '../types/api';
import { LibraryCard } from '../components/library/LibraryCard';
import { Pagination } from '../components/feedback/Pagination';
import { EmptyState } from '../components/feedback/EmptyState';
import { ErrorState } from '../components/feedback/ErrorState';
import { Dialog } from '../components/ui/Dialog';
import { Button } from '../components/ui/Button';
import { usePreferences } from '../contexts/PreferencesContext';

type FilterType = 'all' | 'manga' | 'manhwa' | 'novel';
type SortType = 'recently_added' | 'recently_read' | 'title' | 'rating';

export const LibraryPage: React.FC = () => {
  const { t } = usePreferences();
  const [library, setLibrary] = useState<LibraryItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filter, Sort, Pagination
  const [activeFilter, setActiveFilter] = useState<FilterType>('all');
  const [activeSort, setActiveSort] = useState<SortType>('recently_added');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  // Remove Modal Confirmation
  const [itemToRemove, setItemToRemove] = useState<LibraryItem | null>(null);
  const [isRemoving, setIsRemoving] = useState(false);

  const fetchLibrary = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const res = await userService.getLibrary(currentPage, 12, activeFilter, activeSort);
      if (res.status === 'success') {
        setLibrary(res.data);
        if (res.meta) {
          setTotalPages(Number(res.meta.total_pages) || 1);
          setTotalCount(Number(res.meta.total) || res.data.length);
        }
      } else {
        setError(res.error?.message || t('library.loadError'));
      }
    } catch {
      setError(t('library.networkError'));
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, activeFilter, activeSort, t]);

  useEffect(() => {
    fetchLibrary();
  }, [fetchLibrary]);

  // Handle Remove
  const handleRequestRemove = (id: string) => {
    const found = library.find((i) => i.id === id);
    if (found) {
      setItemToRemove(found);
    }
  };

  const handleConfirmRemove = async () => {
    if (!itemToRemove) return;
    setIsRemoving(true);
    try {
      const res = await userService.removeFromLibrary(itemToRemove.content.slug, itemToRemove.content.type);
      if (res.status === 'success') {
        setItemToRemove(null);
        await fetchLibrary();
      }
    } finally {
      setIsRemoving(false);
    }
  };

  // Handle Follow Toggle
  const handleToggleFollow = async (slug: string) => {
    const item = library.find((i) => i.content.slug === slug);
    if (!item) return;

    // Optimistic UI update
    setLibrary((prev) =>
      prev.map((i) =>
        i.content.slug === slug
          ? { ...i, user_state: { ...i.user_state, is_following: !i.user_state.is_following } }
          : i
      )
    );

    try {
      await contentService.toggleFollow(item.content.type as ContentType, slug, item.user_state.is_following);
    } catch (err) {
      console.error('Follow toggle error:', err);
      // Revert on failure
      fetchLibrary();
    }
  };

  const filterTabs: { key: FilterType; label: string }[] = [
    { key: 'all', label: t('common.activeAll') },
    { key: 'manga', label: 'Manga' },
    { key: 'manhwa', label: 'Manhwa / Webtoon' },
    { key: 'novel', label: 'Novel' },
  ];

  const sortOptions: { key: SortType; label: string }[] = [
    { key: 'recently_added', label: t('library.sortRecent') },
    { key: 'recently_read', label: t('library.sortReading') },
    { key: 'title', label: t('library.sortTitle') },
    { key: 'rating', label: t('library.sortRating') },
  ];

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      {/* Header Section */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-[var(--border-color)] pb-6">
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
              {t('library.personalCollection')}
            </span>
            <span className="px-2 py-0.5 rounded-full text-[11px] font-mono font-semibold bg-[var(--accent-light)] text-[var(--accent-color)]">
              {t('common.seriesCount', { count: totalCount })}
            </span>
          </div>
          <h1 className="font-serif text-3xl sm:text-4xl font-bold text-[var(--text-primary)]">
            {t('library.libraryTitle')}
          </h1>
          <p className="text-sm text-[var(--text-secondary)] font-light max-w-xl">
            {t('library.librarySubtitle')}
          </p>
        </div>

        <Link to="/browse">
          <Button variant="outline" size="sm" className="gap-2 cursor-pointer w-full sm:w-auto">
            <Compass className="w-4 h-4 text-[var(--accent-color)]" />
            <span>{t('library.discoverNew')}</span>
          </Button>
        </Link>
      </div>

      {/* Filter and Sort Control Bar */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 p-3 bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)]">
        {/* Filter Tabs */}
        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
          {filterTabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              onClick={() => {
                setActiveFilter(tab.key);
                setCurrentPage(1);
              }}
              className={`px-3.5 py-1.5 rounded-xl font-mono text-xs font-semibold whitespace-nowrap transition-all cursor-pointer ${
                activeFilter === tab.key
                  ? 'bg-[var(--accent-color)] text-white shadow-sm'
                  : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-card)]'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* Sort Dropdown */}
        <div className="flex items-center gap-2">
          <ArrowUpDown className="w-4 h-4 text-[var(--text-muted)] flex-shrink-0" />
          <select
            value={activeSort}
            onChange={(e) => {
              setActiveSort(e.target.value as SortType);
              setCurrentPage(1);
            }}
            aria-label={t('library.sortCriteria')}
            className="w-full sm:w-auto bg-[var(--bg-tertiary)] text-[var(--text-primary)] border border-[var(--border-color)] rounded-xl px-3 py-1.5 text-xs font-mono font-medium focus:outline-none focus:border-[var(--accent-color)] cursor-pointer"
          >
            {sortOptions.map((opt) => (
              <option key={opt.key} value={opt.key}>
                {opt.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Main Content Area */}
      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-6 gap-4">
          {[...Array(12)].map((_, i) => (
            <div
              key={i}
              className="bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)] p-3 flex flex-col gap-3"
            >
              <div className="aspect-[3/4] w-full bg-[var(--bg-tertiary)] rounded-xl animate-pulse" />
              <div className="h-4 bg-[var(--bg-tertiary)] rounded-md w-3/4 animate-pulse" />
              <div className="h-3 bg-[var(--bg-tertiary)] rounded-md w-1/2 animate-pulse" />
              <div className="h-8 bg-[var(--bg-tertiary)] rounded-xl w-full animate-pulse mt-auto" />
            </div>
          ))}
        </div>
      ) : error ? (
        <ErrorState
          title={t('feedback.errorTitle')}
          message={error}
          onRetry={fetchLibrary}
        />
      ) : library.length === 0 ? (
        <EmptyState
          title={
            activeFilter === 'all'
              ? t('library.emptyTitle')
              : t('library.emptyFilteredTitle')
          }
          description={
            activeFilter === 'all'
              ? t('library.emptyDesc')
              : t('library.emptyFilteredDesc')
          }
          actionLabel={t('library.exploreCta')}
          onAction={() => window.location.assign('/browse')}
          icon={<Bookmark className="w-12 h-12 text-[var(--accent-color)]" />}
        />
      ) : (
        <div className="flex flex-col gap-6">
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-6 gap-4 sm:gap-6">
            {library.map((item) => (
              <LibraryCard
                key={item.id}
                item={item}
                onRemove={handleRequestRemove}
                onToggleFollow={handleToggleFollow}
              />
            ))}
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <Pagination
              currentPage={currentPage}
              totalPages={totalPages}
              total={totalCount}
              perPage={12}
              onPageChange={(page) => {
                setCurrentPage(page);
                window.scrollTo({ top: 0, behavior: 'smooth' });
              }}
            />
          )}
        </div>
      )}

      {/* Remove Confirmation Dialog */}
      <Dialog
        isOpen={Boolean(itemToRemove)}
        onClose={() => !isRemoving && setItemToRemove(null)}
        title={t('library.removeModalTitle')}
      >
        <div className="flex flex-col gap-4">
          <div className="flex items-start gap-3 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400">
            <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
            <p className="text-xs">
              <strong className="text-[var(--text-primary)] font-semibold font-serif">
                {itemToRemove?.content.title}
              </strong>{' '}
              {t('library.removeConfirmDesc')}
            </p>
          </div>

          <div className="flex items-center justify-end gap-2 pt-2">
            <Button
              variant="outline"
              size="sm"
              disabled={isRemoving}
              onClick={() => setItemToRemove(null)}
              className="cursor-pointer"
            >
              {t('common.cancelAlt')}
            </Button>
            <Button
              variant="danger"
              size="sm"
              disabled={isRemoving}
              onClick={handleConfirmRemove}
              className="cursor-pointer bg-rose-600 hover:bg-rose-700 text-white"
            >
              {isRemoving ? t('library.removing') : t('library.removeConfirmBtn')}
            </Button>
          </div>
        </div>
      </Dialog>
    </div>
  );
};
