import React, { useEffect, useState, useCallback } from 'react';
import {
  Wallet,
  Search,
  SlidersHorizontal,
  ChevronLeft,
  ChevronRight,
  RotateCcw,
  Sparkles,
  History,
  BookOpen,
  Layers,
  ShieldCheck,
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
import { UnlockedSeriesList } from '../components/wallet/UnlockedSeriesList';
import { UnlockedChaptersList } from '../components/wallet/UnlockedChaptersList';
import { FeatureEntitlementsList } from '../components/wallet/FeatureEntitlementsList';
import { TopUpModal } from '../components/wallet/TopUpModal';
import { usePreferences } from '../contexts/PreferencesContext';

type WalletSubTab = 'transactions' | 'series_unlocks' | 'chapter_unlocks' | 'entitlements';

export const WalletPage: React.FC = () => {
  const { t } = usePreferences();
  const [wallet, setWallet] = useState<WalletData | null>(null);
  const [transactions, setTransactions] = useState<WalletTransaction[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isTxLoading, setIsTxLoading] = useState(false);

  // Sub Tab
  const [activeSubTab, setActiveSubTab] = useState<WalletSubTab>('transactions');

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

  // Load transactions
  const loadTransactions = useCallback(async () => {
    setIsTxLoading(true);
    const txRes = await walletService.getTransactions(
      currentPage,
      perPage,
      activeFilter,
      debouncedSearch,
      sortOption
    );

    if (txRes.status === 'success' && txRes.data) {
      setTransactions(txRes.data);
      if (txRes.meta) {
        setTotalPages((txRes.meta.total_pages as number) || 1);
        setTotalItems((txRes.meta.total as number) || txRes.data.length);
      }
    }
    setIsTxLoading(false);
  }, [currentPage, activeFilter, debouncedSearch, sortOption]);

  useEffect(() => {
    const init = async () => {
      setIsLoading(true);
      await loadWallet();
      await loadTransactions();
      setIsLoading(false);
    };
    init();
  }, [loadWallet, loadTransactions]);

  const handleTopUpSuccess = async () => {
    await loadWallet();
    await loadTransactions();
  };

  const handleResetFilters = () => {
    setActiveFilter('ALL');
    setSortOption('newest');
    setSearchQuery('');
    setCurrentPage(1);
  };

  const filterTabs: { id: TransactionFilterType; label: string }[] = [
    { id: 'ALL', label: t('wallet.filterAll') },
    { id: 'TOPUP', label: t('wallet.filterTopup') },
    { id: 'CHAPTER_UNLOCK', label: t('wallet.filterChapterUnlock') },
    { id: 'REWARD', label: t('wallet.filterReward') },
    { id: 'AD_FREE', label: t('wallet.filterAdFree') },
  ];

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 flex flex-col gap-8">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border-color)] pb-6">
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <span className="text-[10px] font-mono font-bold uppercase tracking-[0.25em] text-[var(--accent-color)]">
              {t('navigation.wallet')}
            </span>
            <span className="w-1.5 h-1.5 rounded-full bg-[var(--accent-color)]" />
            <span className="text-[10px] font-mono text-[var(--text-muted)]">
              {t('wallet.financialOverview')}
            </span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold font-serif text-[var(--text-primary)]">
            {t('wallet.title')}
          </h1>
        </div>

        <button
          onClick={() => setIsTopUpOpen(true)}
          className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[var(--accent-color)] text-white hover:opacity-90 font-semibold text-xs transition-opacity shadow-md shadow-[var(--accent-color)]/20 cursor-pointer self-start sm:self-auto"
        >
          <Sparkles className="w-3.5 h-3.5" />
          <span>{t('wallet.loadCoin')}</span>
        </button>
      </div>

      {/* Balance Card */}
      <WalletBalance
        wallet={wallet}
        onOpenTopUp={() => setIsTopUpOpen(true)}
      />

      {/* Wallet Sub Navigation Tabs */}
      <div className="flex items-center gap-2 border-b border-[var(--border-color)] overflow-x-auto pb-px">
        <button
          onClick={() => setActiveSubTab('transactions')}
          className={`px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all border-b-2 flex items-center gap-2 cursor-pointer shrink-0 ${
            activeSubTab === 'transactions'
              ? 'border-[var(--accent-color)] text-[var(--accent-color)] bg-[var(--accent-light)]/40 rounded-t-xl'
              : 'border-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] rounded-t-xl'
          }`}
        >
          <History className="w-3.5 h-3.5" />
          <span>{t('wallet.transactionHistory')}</span>
        </button>

        <button
          onClick={() => setActiveSubTab('series_unlocks')}
          className={`px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all border-b-2 flex items-center gap-2 cursor-pointer shrink-0 ${
            activeSubTab === 'series_unlocks'
              ? 'border-[var(--accent-color)] text-[var(--accent-color)] bg-[var(--accent-light)]/40 rounded-t-xl'
              : 'border-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] rounded-t-xl'
          }`}
        >
          <BookOpen className="w-3.5 h-3.5" />
          <span>{t('unlocks.seriesTitle')}</span>
        </button>

        <button
          onClick={() => setActiveSubTab('chapter_unlocks')}
          className={`px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all border-b-2 flex items-center gap-2 cursor-pointer shrink-0 ${
            activeSubTab === 'chapter_unlocks'
              ? 'border-[var(--accent-color)] text-[var(--accent-color)] bg-[var(--accent-light)]/40 rounded-t-xl'
              : 'border-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] rounded-t-xl'
          }`}
        >
          <Layers className="w-3.5 h-3.5" />
          <span>{t('unlocks.chaptersTitle')}</span>
        </button>

        <button
          onClick={() => setActiveSubTab('entitlements')}
          className={`px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all border-b-2 flex items-center gap-2 cursor-pointer shrink-0 ${
            activeSubTab === 'entitlements'
              ? 'border-[var(--accent-color)] text-[var(--accent-color)] bg-[var(--accent-light)]/40 rounded-t-xl'
              : 'border-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] rounded-t-xl'
          }`}
        >
          <ShieldCheck className="w-3.5 h-3.5" />
          <span>{t('entitlements.title')}</span>
        </button>
      </div>

      {/* Sub Tab Content */}
      {activeSubTab === 'transactions' && (
        <div className="flex flex-col gap-5">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h2 className="font-serif text-xl sm:text-2xl font-bold text-[var(--text-primary)]">
                {t('wallet.transactionHistory')}
              </h2>
              <p className="text-xs text-[var(--text-secondary)] font-mono mt-0.5">
                {t('wallet.totalRecordsListed', { count: totalItems })}
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
                placeholder={t('wallet.searchPlaceholder')}
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
                  <option value="newest">{t('wallet.sortNewest')}</option>
                  <option value="oldest">{t('wallet.sortOldest')}</option>
                  <option value="amount_desc">{t('wallet.sortAmountHigh')}</option>
                  <option value="amount_asc">{t('wallet.sortAmountLow')}</option>
                </select>
              </div>

              {(activeFilter !== 'ALL' || sortOption !== 'newest' || searchQuery) && (
                <button
                  onClick={handleResetFilters}
                  title={t('wallet.resetFilters')}
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
                {t('common.paginationLabel', { current: currentPage, total: totalPages })}
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
      )}

      {activeSubTab === 'series_unlocks' && (
        <UnlockedSeriesList />
      )}

      {activeSubTab === 'chapter_unlocks' && (
        <UnlockedChaptersList />
      )}

      {activeSubTab === 'entitlements' && (
        <FeatureEntitlementsList />
      )}

      {/* Top Up Modal */}
      {isTopUpOpen && (
        <TopUpModal
          isOpen={isTopUpOpen}
          onClose={() => setIsTopUpOpen(false)}
          onSuccess={handleTopUpSuccess}
        />
      )}
    </div>
  );
};
