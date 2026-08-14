import React, { useEffect, useState, useCallback } from 'react';
import {
  Wallet,
  Search,
  SlidersHorizontal,
  ChevronLeft,
  ChevronRight,
  RotateCcw,
  Sparkles,
} from 'lucide-react';
import { walletService } from '../services';
import {
  WalletData,
  WalletTransaction,
  TransactionFilterType,
  TransactionSortOption,
} from '../types/api';
import { WalletBalance } from '../components/wallet/WalletBalance';
import { TransactionList } from '../components/wallet/TransactionList';
import { TopUpModal } from '../components/wallet/TopUpModal';

export const WalletPage: React.FC = () => {
  const [wallet, setWallet] = useState<WalletData | null>(null);
  const [transactions, setTransactions] = useState<WalletTransaction[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isTxLoading, setIsTxLoading] = useState(false);

  // Filter & Search & Sort states
  const [activeFilter, setActiveFilter] = useState<TransactionFilterType>('ALL');
  const [sortOption, setSortOption] = useState<TransactionSortOption>('newest');
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  // Pagination states
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const perPage = 10;

  // TopUp Modal
  const [isTopUpOpen, setIsTopUpOpen] = useState(false);

  // Debounce search
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearch(searchQuery);
      setCurrentPage(1);
    }, 300);
    return () => clearTimeout(handler);
  }, [searchQuery]);

  // Load wallet data
  const loadWallet = useCallback(async () => {
    const wRes = await walletService.getWallet();
    if (wRes.status === 'success' && wRes.data) {
      setWallet(wRes.data);
    }
  }, []);

  // Load transactions data
  const loadTransactions = useCallback(async () => {
    setIsTxLoading(true);
    const tRes = await walletService.getTransactions(
      currentPage,
      perPage,
      activeFilter,
      sortOption,
      debouncedSearch
    );

    if (tRes.status === 'success' && tRes.data) {
      setTransactions(tRes.data);
      if (tRes.meta) {
        setTotalPages((tRes.meta.total_pages as number) || 1);
        setTotalItems((tRes.meta.total as number) || tRes.data.length);
      }
    }
    setIsTxLoading(false);
  }, [currentPage, activeFilter, sortOption, debouncedSearch]);

  // Initial load
  useEffect(() => {
    const init = async () => {
      setIsLoading(true);
      await Promise.all([loadWallet(), loadTransactions()]);
      setIsLoading(false);
    };
    init();
  }, [loadWallet, loadTransactions]);

  const handleTopUpSuccess = async () => {
    await Promise.all([loadWallet(), loadTransactions()]);
  };

  const handleResetFilters = () => {
    setActiveFilter('ALL');
    setSortOption('newest');
    setSearchQuery('');
    setCurrentPage(1);
  };

  const filterTabs: { id: TransactionFilterType; label: string }[] = [
    { id: 'ALL', label: 'Tümü' },
    { id: 'CREDIT', label: 'Yüklemeler' },
    { id: 'PURCHASE', label: 'Harcamalar' },
    { id: 'REFUND', label: 'İadeler' },
  ];

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border-color)] pb-6">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 rounded-2xl bg-[var(--accent-light)] border border-[var(--accent-border)] flex items-center justify-center text-[var(--accent-color)] shadow-sm">
            <Wallet className="w-6 h-6" />
          </div>
          <div>
            <span className="text-[10px] uppercase tracking-[0.25em] text-[var(--accent-color)] font-mono font-bold">
              Hesap & Finans
            </span>
            <h1 className="font-serif text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">
              Coin & Bakiye <span className="italic text-[var(--accent-color)]">Yönetimi</span>
            </h1>
          </div>
        </div>

        <button
          onClick={() => setIsTopUpOpen(true)}
          className="self-start sm:self-auto inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[var(--accent-color)] text-white hover:opacity-90 font-semibold text-xs transition-opacity shadow-md shadow-[var(--accent-color)]/20 cursor-pointer"
        >
          <Sparkles className="w-4 h-4 text-white" />
          <span>Coin Yükle</span>
        </button>
      </div>

      {/* Balance Card */}
      <WalletBalance
        wallet={wallet}
        onOpenTopUp={() => setIsTopUpOpen(true)}
      />

      {/* Transactions Section */}
      <div className="flex flex-col gap-5">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h2 className="font-serif text-xl sm:text-2xl font-bold text-[var(--text-primary)]">
              İşlem <span className="italic text-[var(--accent-color)]">Geçmişi</span>
            </h2>
            <p className="text-xs text-[var(--text-secondary)] font-mono mt-0.5">
              Toplam {totalItems} kayıt listeleniyor
            </p>
          </div>

          {/* Filter Pills */}
          <div className="flex items-center gap-1.5 p-1 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-xl overflow-x-auto max-w-full">
            {filterTabs.map((tab) => {
              const isActive = activeFilter === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => {
                    setActiveFilter(tab.id);
                    setCurrentPage(1);
                  }}
                  className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all cursor-pointer ${
                    isActive
                      ? 'bg-[var(--accent-color)] text-white shadow-xs'
                      : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)]'
                  }`}
                >
                  {tab.label}
                </button>
              );
            })}
          </div>
        </div>

        {/* Search & Sort Bar */}
        <div className="grid grid-cols-1 sm:grid-cols-12 gap-3">
          <div className="sm:col-span-8 relative">
            <Search className="w-4 h-4 text-[var(--text-muted)] absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="İşlem adı, bölüm veya paket ara..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-9 pr-4 py-2.5 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-xl text-xs text-[var(--text-primary)] placeholder-[var(--text-muted)] focus:outline-hidden focus:border-[var(--accent-color)] transition-colors"
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery('')}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)]"
              >
                ✕
              </button>
            )}
          </div>

          <div className="sm:col-span-4 flex items-center gap-2">
            <div className="relative flex-1">
              <SlidersHorizontal className="w-3.5 h-3.5 text-[var(--text-muted)] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
              <select
                value={sortOption}
                onChange={(e) => {
                  setSortOption(e.target.value as TransactionSortOption);
                  setCurrentPage(1);
                }}
                className="w-full pl-8 pr-7 py-2.5 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-xl text-xs text-[var(--text-primary)] appearance-none focus:outline-hidden focus:border-[var(--accent-color)] transition-colors cursor-pointer"
              >
                <option value="newest">En Yeni Tarih</option>
                <option value="oldest">En Eski Tarih</option>
                <option value="amount_desc">En Yüksek Tutar</option>
                <option value="amount_asc">En Düşük Tutar</option>
              </select>
            </div>

            {(activeFilter !== 'ALL' || sortOption !== 'newest' || searchQuery) && (
              <button
                onClick={handleResetFilters}
                title="Filtreleri Sıfırla"
                className="p-2.5 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors cursor-pointer"
              >
                <RotateCcw className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>

        {/* Transactions List */}
        <TransactionList
          transactions={transactions}
          isLoading={isLoading || isTxLoading}
          onResetFilters={handleResetFilters}
        />

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="flex items-center justify-between border-t border-[var(--border-color)] pt-4 mt-2">
            <span className="text-xs font-mono text-[var(--text-muted)]">
              Sayfa {currentPage} / {totalPages}
            </span>

            <div className="flex items-center gap-2">
              <button
                disabled={currentPage <= 1}
                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                className="p-2 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] text-[var(--text-primary)] disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>

              <button
                disabled={currentPage >= totalPages}
                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                className="p-2 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] text-[var(--text-primary)] disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Top Up Modal */}
      <TopUpModal
        isOpen={isTopUpOpen}
        onClose={() => setIsTopUpOpen(false)}
        onSuccess={handleTopUpSuccess}
      />
    </div>
  );
};
