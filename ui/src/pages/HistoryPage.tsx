import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { History, Trash2, Compass, AlertCircle } from 'lucide-react';
import { userService } from '../services';
import { ReadingHistoryItem } from '../types/api';
import { HistoryCard } from '../components/history/HistoryCard';
import { Pagination } from '../components/feedback/Pagination';
import { EmptyState } from '../components/feedback/EmptyState';
import { ErrorState } from '../components/feedback/ErrorState';
import { Dialog } from '../components/ui/Dialog';
import { Button } from '../components/ui/Button';

export const HistoryPage: React.FC = () => {
  const [history, setHistory] = useState<ReadingHistoryItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Pagination
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  // Delete Modals
  const [itemToDelete, setItemToDelete] = useState<ReadingHistoryItem | null>(null);
  const [isDeletingItem, setIsDeletingItem] = useState(false);
  const [isConfirmingClearAll, setIsConfirmingClearAll] = useState(false);
  const [isClearingAll, setIsClearingAll] = useState(false);

  const fetchHistory = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const res = await userService.getHistory(currentPage, 15);
      if (res.status === 'success') {
        setHistory(res.data);
        if (res.meta) {
          setTotalPages(Number(res.meta.total_pages) || 1);
          setTotalCount(Number(res.meta.total) || res.data.length);
        }
      } else {
        setError(res.error?.message || 'Geçmiş verileri yüklenirken bir hata oluştu.');
      }
    } catch {
      setError('Sunucu bağlantısı sağlanamadı. Lütfen tekrar deneyin.');
    } finally {
      setIsLoading(false);
    }
  }, [currentPage]);

  useEffect(() => {
    fetchHistory();
  }, [fetchHistory]);

  // Handle single item remove
  const handleRequestRemove = (id: string) => {
    const found = history.find((i) => i.id === id || i.chapter_id === id);
    if (found) {
      setItemToDelete(found);
    }
  };

  const handleConfirmRemoveItem = async () => {
    if (!itemToDelete) return;
    setIsDeletingItem(true);
    try {
      const res = await userService.removeFromHistory(itemToDelete.id);
      if (res.status === 'success') {
        setItemToDelete(null);
        await fetchHistory();
      }
    } finally {
      setIsDeletingItem(false);
    }
  };

  // Handle Clear All
  const handleConfirmClearAll = async () => {
    setIsClearingAll(true);
    try {
      const res = await userService.clearHistory();
      if (res.status === 'success') {
        setIsConfirmingClearAll(false);
        setHistory([]);
        setTotalCount(0);
        setTotalPages(1);
      }
    } finally {
      setIsClearingAll(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      {/* Top Header */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-[var(--border-color)] pb-6">
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
              Okuma Kayıtları
            </span>
            <span className="px-2 py-0.5 rounded-full text-[11px] font-mono font-semibold bg-[var(--accent-light)] text-[var(--accent-color)]">
              {totalCount} Kayıt
            </span>
          </div>
          <h1 className="font-serif text-3xl sm:text-4xl font-bold text-[var(--text-primary)]">
            Okuma Geçmişim
          </h1>
          <p className="text-sm text-[var(--text-secondary)] font-light max-w-xl">
            Okuduğunuz tüm bölümler, sayfa bazlı ilerlemeniz ve son aktivite tarihleriniz burada listelenir.
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          {history.length > 0 && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setIsConfirmingClearAll(true)}
              className="gap-2 text-rose-500 hover:bg-rose-500/10 border-rose-200 dark:border-rose-900/50 cursor-pointer text-xs"
            >
              <Trash2 className="w-4 h-4" />
              <span>Geçmişi Temizle</span>
            </Button>
          )}

          <Link to="/browse">
            <Button variant="outline" size="sm" className="gap-2 cursor-pointer text-xs">
              <Compass className="w-4 h-4 text-[var(--accent-color)]" />
              <span>Keşfet</span>
            </Button>
          </Link>
        </div>
      </div>

      {/* Main Content */}
      {isLoading ? (
        <div className="flex flex-col gap-3">
          {[...Array(6)].map((_, i) => (
            <div
              key={i}
              className="h-24 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-4 flex items-center gap-4 animate-pulse"
            >
              <div className="w-16 h-20 bg-[var(--bg-tertiary)] rounded-xl" />
              <div className="flex flex-col gap-2 flex-1">
                <div className="w-1/3 h-4 bg-[var(--bg-tertiary)] rounded-md" />
                <div className="w-1/4 h-3 bg-[var(--bg-tertiary)] rounded-md" />
                <div className="w-1/2 h-2 bg-[var(--bg-tertiary)] rounded-md" />
              </div>
            </div>
          ))}
        </div>
      ) : error ? (
        <ErrorState
          title="Geçmiş Yüklenemedi"
          message={error}
          onRetry={fetchHistory}
        />
      ) : history.length === 0 ? (
        <EmptyState
          title="Henüz Okuma Geçmişiniz Yok"
          description="Herhangi bir manga, manhwa veya roman okumaya başladığınızda, son kaldığınız bölüm ve sayfa ilerlemeniz otomatik olarak burada kaydedilecektir."
          actionLabel="Hemen Okumaya Başla"
          onAction={() => window.location.assign('/browse')}
          icon={<History className="w-12 h-12 text-[var(--accent-color)]" />}
        />
      ) : (
        <div className="flex flex-col gap-4">
          {history.map((item) => (
            <HistoryCard
              key={item.id}
              item={item}
              onRemove={handleRequestRemove}
            />
          ))}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="pt-4">
              <Pagination
                currentPage={currentPage}
                totalPages={totalPages}
                total={totalCount}
                perPage={15}
                onPageChange={(page) => {
                  setCurrentPage(page);
                  window.scrollTo({ top: 0, behavior: 'smooth' });
                }}
              />
            </div>
          )}
        </div>
      )}

      {/* Delete Single Item Modal */}
      <Dialog
        isOpen={Boolean(itemToDelete)}
        onClose={() => !isDeletingItem && setItemToDelete(null)}
        title="Okuma Kaydını Sil"
      >
        <div className="flex flex-col gap-4">
          <p className="text-sm text-[var(--text-secondary)]">
            <strong className="text-[var(--text-primary)] font-serif font-bold">
              {itemToDelete?.content?.title || itemToDelete?.content_title}
            </strong>{' '}
            (Bölüm #{itemToDelete?.chapter?.number ?? itemToDelete?.chapter_number}) okuma kaydını geçmişinizden silmek istediğinize emin misiniz?
          </p>

          <div className="flex items-center justify-end gap-2 pt-2">
            <Button
              variant="outline"
              size="sm"
              disabled={isDeletingItem}
              onClick={() => setItemToDelete(null)}
              className="cursor-pointer"
            >
              İptal
            </Button>
            <Button
              variant="danger"
              size="sm"
              disabled={isDeletingItem}
              onClick={handleConfirmRemoveItem}
              className="cursor-pointer bg-rose-600 hover:bg-rose-700 text-white"
            >
              {isDeletingItem ? 'Siliniyor...' : 'Kaydı Sil'}
            </Button>
          </div>
        </div>
      </Dialog>

      {/* Clear All History Modal */}
      <Dialog
        isOpen={isConfirmingClearAll}
        onClose={() => !isClearingAll && setIsConfirmingClearAll(false)}
        title="Tüm Okuma Geçmişini Temizle"
      >
        <div className="flex flex-col gap-4">
          <div className="flex items-start gap-3 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400">
            <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
            <div className="flex flex-col gap-1 text-xs">
              <span className="font-semibold text-sm">Bu işlem geri alınamaz!</span>
              <span>Tüm serilere ait kaldığınız yer bilgisi ve sayfa ilerleme geçmişiniz kalıcı olarak silinecektir.</span>
            </div>
          </div>

          <div className="flex items-center justify-end gap-2 pt-2">
            <Button
              variant="outline"
              size="sm"
              disabled={isClearingAll}
              onClick={() => setIsConfirmingClearAll(false)}
              className="cursor-pointer"
            >
              Vazgeç
            </Button>
            <Button
              variant="danger"
              size="sm"
              disabled={isClearingAll}
              onClick={handleConfirmClearAll}
              className="cursor-pointer bg-rose-600 hover:bg-rose-700 text-white font-semibold"
            >
              {isClearingAll ? 'Temizleniyor...' : 'Tümünü Temizle'}
            </Button>
          </div>
        </div>
      </Dialog>
    </div>
  );
};
