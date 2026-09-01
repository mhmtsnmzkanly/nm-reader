import React, { useState } from 'react';
import {
  ArrowDownLeft,
  ArrowUpRight,
  RotateCcw,
  BookOpen,
  Sparkles,
  Layers,
  Coins,
  Receipt,
  Info,
} from 'lucide-react';
import { WalletTransaction } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

type TransactionListProps = {
  transactions: WalletTransaction[];
  isLoading?: boolean;
  onResetFilters?: () => void;
};

export const TransactionList: React.FC<TransactionListProps> = ({
  transactions,
  isLoading = false,
  onResetFilters,
}) => {
  const { formatDate, t } = usePreferences();
  const [selectedTx, setSelectedTx] = useState<WalletTransaction | null>(null);

  if (isLoading) {
    return (
      <div className="flex flex-col gap-3">
        {[...Array(5)].map((_, i) => (
          <div
            key={i}
            className="p-4 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl flex items-center justify-between gap-4 animate-pulse"
          >
            <div className="flex items-center gap-3.5 flex-1">
              <div className="w-10 h-10 rounded-xl bg-[var(--bg-tertiary)]" />
              <div className="flex flex-col gap-2 flex-1 max-w-sm">
                <div className="h-4 bg-[var(--bg-tertiary)] rounded w-3/4" />
                <div className="h-3 bg-[var(--bg-tertiary)] rounded w-1/2" />
              </div>
            </div>
            <div className="h-6 w-16 bg-[var(--bg-tertiary)] rounded" />
          </div>
        ))}
      </div>
    );
  }

  if (transactions.length === 0) {
    return (
      <div className="p-12 text-center bg-[var(--bg-card)] border border-dashed border-[var(--border-color)] rounded-2xl flex flex-col items-center justify-center gap-3">
        <div className="w-12 h-12 rounded-2xl bg-[var(--bg-tertiary)] flex items-center justify-center text-[var(--text-muted)]">
          <Receipt className="w-6 h-6" />
        </div>
        <div className="flex flex-col gap-1">
          <h4 className="font-serif font-bold text-base text-[var(--text-primary)]">
            {t('wallet.noTransactionsFound')}
          </h4>
          <p className="text-xs text-[var(--text-secondary)] font-light max-w-sm">
            {t('wallet.noTransactionsDesc')}
          </p>
        </div>
        {onResetFilters && (
          <button
            onClick={onResetFilters}
            className="mt-2 px-4 py-2 rounded-xl bg-[var(--bg-tertiary)] hover:bg-[var(--border-color)] text-xs font-semibold text-[var(--text-primary)] transition-colors cursor-pointer"
          >
            {t('wallet.clearFilters')}
          </button>
        )}
      </div>
    );
  }

  const getTransactionBadge = (tx: WalletTransaction) => {
    const isCredit = tx.coin_delta > 0;

    switch (tx.type) {
      case 'package_credit':
      case 'CREDIT':
        return {
          icon: <ArrowDownLeft className="w-4 h-4" />,
          label: t('wallet.badgePackage'),
          bg: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-500',
        };
      case 'manual_credit':
        return {
          icon: <ArrowDownLeft className="w-4 h-4" />,
          label: t('wallet.badgeBonus'),
          bg: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-500',
        };
      case 'chapter_unlock':
      case 'PURCHASE':
        return {
          icon: <BookOpen className="w-4 h-4" />,
          label: t('wallet.badgeChapter'),
          bg: 'bg-[var(--accent-light)] border-[var(--accent-border)] text-[var(--accent-color)]',
        };
      case 'series_unlock':
        return {
          icon: <Layers className="w-4 h-4" />,
          label: t('wallet.badgeSeries'),
          bg: 'bg-amber-500/10 border-amber-500/20 text-amber-500',
        };
      case 'feature_unlock':
        return {
          icon: <Sparkles className="w-4 h-4" />,
          label: t('wallet.badgeFeature'),
          bg: 'bg-purple-500/10 border-purple-500/20 text-purple-500',
        };
      case 'refund':
      case 'REFUND':
        return {
          icon: <RotateCcw className="w-4 h-4" />,
          label: t('wallet.badgeRefund'),
          bg: 'bg-sky-500/10 border-sky-500/20 text-sky-500',
        };
      default:
        return {
          icon: isCredit ? <ArrowDownLeft className="w-4 h-4" /> : <ArrowUpRight className="w-4 h-4" />,
          label: isCredit ? t('wallet.badgeCredit') : t('wallet.badgeDebit'),
          bg: isCredit
            ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-500'
            : 'bg-[var(--accent-light)] border-[var(--accent-border)] text-[var(--accent-color)]',
        };
    }
  };

  const formatTxDate = (isoString: string) => {
    return formatDate(isoString, {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <>
      <div className="flex flex-col gap-2.5">
        {transactions.map((tx) => {
          const badge = getTransactionBadge(tx);
          const isCredit = tx.coin_delta > 0;
          const delta = tx.coin_delta || tx.amount || 0;

          return (
            <div
              key={tx.id}
              onClick={() => (tx.metadata ? setSelectedTx(tx) : null)}
              className={`p-4 bg-[var(--bg-card)] hover:bg-[var(--bg-tertiary)]/50 border border-[var(--border-color)] hover:border-[var(--accent-color)]/50 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 transition-all duration-200 shadow-sm ${
                tx.metadata ? 'cursor-pointer' : ''
              }`}
            >
              {/* Left Column: Icon & Description */}
              <div className="flex items-center gap-3.5 min-w-0">
                <div
                  className={`w-10 h-10 rounded-xl border flex items-center justify-center flex-shrink-0 shadow-inner ${badge.bg}`}
                >
                  {badge.icon}
                </div>

                <div className="flex flex-col gap-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-xs sm:text-sm font-semibold text-[var(--text-primary)] truncate">
                      {tx.description}
                    </span>
                    <span
                      className={`text-[10px] font-mono px-2 py-0.5 rounded-md border font-semibold ${badge.bg}`}
                    >
                      {badge.label}
                    </span>
                  </div>

                  <div className="flex items-center gap-3 text-[11px] text-[var(--text-muted)] font-mono">
                    <span>{formatTxDate(tx.created_at)}</span>
                    {tx.balance_after !== undefined && (
                      <>
                        <span>•</span>
                        <span>{t('wallet.balanceAfter')}: {tx.balance_after} {t('common.coin')}</span>
                      </>
                    )}
                    {tx.metadata && (
                      <span className="text-[var(--accent-color)] text-[10px] inline-flex items-center gap-1">
                        <Info className="w-3 h-3" /> {t('wallet.detailModalTitle')}
                      </span>
                    )}
                  </div>
                </div>
              </div>

              {/* Right Column: Amount */}
              <div className="flex sm:flex-col items-center sm:items-end justify-between w-full sm:w-auto border-t sm:border-t-0 pt-2 sm:pt-0 border-[var(--border-color)] gap-1">
                <div className="flex items-center gap-1.5 font-mono font-bold text-sm sm:text-base">
                  <span className={isCredit ? 'text-emerald-500' : 'text-[var(--text-primary)]'}>
                    {isCredit ? '+' : ''}
                    {delta}
                  </span>
                  <Coins className="w-4 h-4 text-[var(--accent-color)] fill-current" />
                </div>
                <span className="text-[10px] uppercase font-mono text-[var(--text-muted)] tracking-wider">
                  {isCredit ? t('wallet.badgeCredit') : t('wallet.badgeDebit')}
                </span>
              </div>
            </div>
          );
        })}
      </div>

      {/* Transaction Metadata Detail Modal */}
      {selectedTx && (
        <div
          className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 animate-in fade-in"
          onClick={() => setSelectedTx(null)}
        >
          <div
            className="bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 shadow-2xl flex flex-col gap-4 relative"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-3">
              <div className="flex items-center gap-2">
                <Receipt className="w-4 h-4 text-[var(--accent-color)]" />
                <h3 className="font-serif font-bold text-base text-[var(--text-primary)]">
                  {t('wallet.detailModalTitle')}
                </h3>
              </div>
              <button
                onClick={() => setSelectedTx(null)}
                className="text-[var(--text-muted)] hover:text-[var(--text-primary)] text-sm p-1 rounded-lg"
              >
                ✕
              </button>
            </div>

            <div className="flex flex-col gap-3 text-xs font-mono">
              <div className="flex justify-between py-1 border-b border-[var(--border-color)]/50">
                <span className="text-[var(--text-muted)]">{t('wallet.txId')}:</span>
                <span className="font-semibold text-[var(--text-primary)]">#{selectedTx.id}</span>
              </div>
              <div className="flex justify-between py-1 border-b border-[var(--border-color)]/50">
                <span className="text-[var(--text-muted)]">{t('wallet.descriptionLabel')}:</span>
                <span className="font-semibold text-[var(--text-primary)] text-right max-w-[200px] truncate">
                  {selectedTx.description}
                </span>
              </div>
              <div className="flex justify-between py-1 border-b border-[var(--border-color)]/50">
                <span className="text-[var(--text-muted)]">{t('wallet.amount')}:</span>
                <span className={`font-bold ${selectedTx.coin_delta > 0 ? 'text-emerald-500' : 'text-rose-500'}`}>
                  {selectedTx.coin_delta > 0 ? '+' : ''}{selectedTx.coin_delta} {t('common.coin')}
                </span>
              </div>
              <div className="flex justify-between py-1 border-b border-[var(--border-color)]/50">
                <span className="text-[var(--text-muted)]">{t('wallet.balanceAfter')}:</span>
                <span className="font-semibold text-[var(--text-primary)]">{selectedTx.balance_after} {t('common.coin')}</span>
              </div>
              <div className="flex justify-between py-1 border-b border-[var(--border-color)]/50">
                <span className="text-[var(--text-muted)]">{t('wallet.date')}:</span>
                <span className="text-[var(--text-primary)]">{formatTxDate(selectedTx.created_at)}</span>
              </div>

              {selectedTx.metadata && (
                <div className="mt-2 p-3 bg-[var(--bg-tertiary)] rounded-xl border border-[var(--border-color)]">
                  <span className="text-[10px] uppercase text-[var(--text-muted)] tracking-wider block mb-1">
                    {t('wallet.additionalParams')}
                  </span>
                  <pre className="text-[11px] text-[var(--text-secondary)] whitespace-pre-wrap overflow-x-auto">
                    {typeof selectedTx.metadata === 'string'
                      ? JSON.stringify(JSON.parse(selectedTx.metadata), null, 2)
                      : JSON.stringify(selectedTx.metadata, null, 2)}
                  </pre>
                </div>
              )}
            </div>

            <button
              onClick={() => setSelectedTx(null)}
              className="mt-2 w-full py-2.5 rounded-xl bg-[var(--bg-tertiary)] hover:bg-[var(--border-color)] text-xs font-semibold text-[var(--text-primary)] transition-colors cursor-pointer"
            >
              {t('common.close')}
            </button>
          </div>
        </div>
      )}
    </>
  );
};
