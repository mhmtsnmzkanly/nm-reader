import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Bookmark, Filter, ArrowUpDown, Compass, Sparkles, AlertCircle } from 'lucide-react';
import { userService, contentService } from '../services';
import { LibraryItem, ContentType } from '../types/api';
import { LibraryCard } from '../components/library/LibraryCard';
import { Pagination } from '../components/feedback/Pagination';
import { EmptyState } from '../components/feedback/EmptyState';
import { ErrorState } from '../components/feedback/ErrorState';
import { Skeleton } from '../components/feedback/Skeleton';
import { Dialog } from '../components/ui/Dialog';
import { Button } from '../components/ui/Button';

type FilterType = 'all' | 'manga' | 'manhwa' | 'novel';
type SortType = 'recently_added' | 'recently_read' | 'title' | 'rating';

export const LibraryPage: React.FC = () => {
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
        setError(res.error?.message || 'Kütüphane yüklenirken bir hata oluştu.');
      }
    } catch {
      setError('Bağlantı hatası meydana geldi. Lütfen tekrar deneyin.');
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, activeFilter, activeSort]);

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
      const res = await userService.removeFromLibrary(itemToRemove.id);
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
      await contentService.toggleFollow(item.content.type as ContentType, slug);
    } catch (err) {
      console.error('Follow toggle error:', err);
      // Revert on failure
      fetchLibrary();
    }
  };

  const filterTabs: { key: FilterType; label: string }[] = [
    { key: 'all', label: 'Tümü' },
    { key: 'manga', label: 'Manga' },
    { key: 'manhwa', label: 'Manhwa / Webtoon' },
    { key: 'novel', label: 'Roman & LN' },
  ];

  const sortOptions: { key: SortType; label: string }[] = [
    { key: 'recently_added', label: 'En Son Eklenenler' },
    { key: 'recently_read', label: 'Son Okuma Durumu' },
    { key: 'title', label: 'Başlık (A-Z)' },
    { key: 'rating', label: 'Puan (Yüksekten Düşüğe)' },
  ];

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      {/* Header Section */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-[var(--border-color)] pb-6">
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
              Kişisel Koleksiyon
            </span>
            <span className="px-2 py-0.5 rounded-full text-[11px] font-mono font-semibold bg-[var(--accent-light)] text-[var(--accent-color)]">
              {totalCount} Seri
            </span>
          </div>
          <h1 className="font-serif text-3xl sm:text-4xl font-bold text-[var(--text-primary)]">
            Kütüphanem
          </h1>
          <p className="text-sm text-[var(--text-secondary)] font-light max-w-xl">
            Kaydettiğiniz tüm serileri, okuma ilerlemenizi ve son kalınan bölümleri buradan yönetebilirsiniz.
          </p>
        </div>

        <Link to="/browse">
          <Button variant="outline" size="sm" className="gap-2 cursor-pointer w-full sm:w-auto">
            <Compass className="w-4 h-4 text-[var(--accent-color)]" />
            <span>Yeni Seriler Keşfet</span>
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
            aria-label="Sıralama Ölçütü"
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
          title="Kütüphane Yüklenemedi"
          message={error}
          onRetry={fetchLibrary}
        />
      ) : library.length === 0 ? (
        <EmptyState
          title={
            activeFilter === 'all'
              ? 'Kütüphanenizde Henüz İçerik Yok'
              : 'Bu Filtreye Uygun Seri Bulunamadı'
          }
          description={
            activeFilter === 'all'
              ? 'Beğendiğiniz serileri kütüphanenize ekleyerek okuma sürecinizi takip edebilir, kaldığınız bölümden okumaya devam edebilirsiniz.'
              : 'Seçtiğiniz kategoride kütüphanenize eklenmiş bir seri bulunmuyor. Farklı bir filtre seçebilir veya serileri keşfedebilirsiniz.'
          }
          actionLabel="Serileri Keşfet"
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
        title="Kütüphaneden Kaldır"
      >
        <div className="flex flex-col gap-4">
          <div className="flex items-start gap-3 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400">
            <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
            <p className="text-xs">
              <strong className="text-[var(--text-primary)] font-semibold font-serif">
                {itemToRemove?.content.title}
              </strong>{' '}
              serisini kütüphanenizden kaldırmak istediğinize emin misiniz? (Okuma ilerleme geçmişiniz profilinizde saklanmaya devam edecektir.)
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
              Vazgeç
            </Button>
            <Button
              variant="danger"
              size="sm"
              disabled={isRemoving}
              onClick={handleConfirmRemove}
              className="cursor-pointer bg-rose-600 hover:bg-rose-700 text-white"
            >
              {isRemoving ? 'Kaldırılıyor...' : 'Evet, Kaldır'}
            </Button>
          </div>
        </div>
      </Dialog>
    </div>
  );
};
