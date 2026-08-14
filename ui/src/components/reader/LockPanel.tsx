import React, { useState, useEffect } from 'react';
import { Lock, Coins, AlertCircle, ShoppingBag, ArrowRight, Loader2 } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Chapter } from '../../types/api';
import { walletService } from '../../services';
import { usePreferences } from '../../contexts/PreferencesContext';

type LockPanelProps = {
  chapter: Chapter;
  onUnlock?: () => Promise<void>;
};

export const LockPanel: React.FC<LockPanelProps> = ({ chapter, onUnlock }) => {
  const { t } = usePreferences();
  const [isUnlocking, setIsUnlocking] = useState(false);
  const [walletBalance, setWalletBalance] = useState<number | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const coinPrice = chapter.access?.price_coin ?? chapter.price_coin ?? 10;

  useEffect(() => {
    const fetchBalance = async () => {
      try {
        const res = await walletService.getWallet();
        if (res.status === 'success' && res.data) {
          setWalletBalance(res.data.balance_coin ?? res.data.balance ?? 0);
        }
      } catch {
        // ignore
      }
    };
    fetchBalance();
  }, []);

  const hasEnoughCoins = walletBalance !== null ? walletBalance >= coinPrice : true;
  const missingCoins = walletBalance !== null ? Math.max(0, coinPrice - walletBalance) : 0;

  const handleUnlockClick = async () => {
    if (!onUnlock) return;
    setIsUnlocking(true);
    setErrorMessage(null);
    try {
      await onUnlock();
    } catch (err: any) {
      setErrorMessage(err?.message || t('reader.unlockError'));
    } finally {
      setIsUnlocking(false);
    }
  };

  return (
    <div className="max-w-md w-full mx-auto my-16 p-7 sm:p-8 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-3xl shadow-2xl flex flex-col items-center text-center gap-6 relative overflow-hidden transition-colors duration-300">
      <div className="absolute top-0 right-0 w-48 h-48 bg-[var(--accent-color)]/5 rounded-full blur-2xl pointer-events-none" />

      {/* Lock Icon */}
      <div className="p-4 rounded-2xl bg-[var(--accent-light)] text-[var(--accent-color)] border border-[var(--accent-border)] shadow-inner">
        <Lock className="w-8 h-8" aria-hidden="true" />
      </div>

      {/* Title & Description */}
      <div className="flex flex-col gap-1.5">
        <span className="text-[10px] uppercase font-mono font-bold tracking-[0.25em] text-[var(--accent-color)]">
          {t('reader.lockedContent')}
        </span>
        <h2 className="font-serif text-2xl font-bold text-[var(--text-primary)]">
          {t('reader.chapterLocked', { number: chapter.chapter_number || chapter.number })}
        </h2>
        <p className="text-xs text-[var(--text-secondary)] font-light max-w-xs leading-relaxed">
          {t('reader.lockedDescription')}
        </p>
      </div>

      {/* Price & Balance Cards */}
      <div className="grid grid-cols-2 gap-3 w-full font-mono text-xs">
        <div className="p-3 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex flex-col gap-0.5 text-left">
          <span className="text-[10px] uppercase text-[var(--text-muted)] tracking-wider">
            {t('reader.chapterPrice')}
          </span>
          <div className="flex items-center gap-1 font-bold text-sm text-[var(--accent-color)]">
            <Coins className="w-3.5 h-3.5 fill-current" />
            <span>{t('wallet.coinAmount', { amount: coinPrice })}</span>
          </div>
        </div>

        <div className="p-3 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex flex-col gap-0.5 text-left">
          <span className="text-[10px] uppercase text-[var(--text-muted)] tracking-wider">
            {t('reader.yourBalance')}
          </span>
          <div
            className={`flex items-center gap-1 font-bold text-sm ${
              hasEnoughCoins ? 'text-emerald-500' : 'text-rose-500'
            }`}
          >
            <Coins className="w-3.5 h-3.5 fill-current" />
            <span>{walletBalance !== null ? t('wallet.coinAmount', { amount: walletBalance }) : '...'}</span>
          </div>
        </div>
      </div>

      {/* Error or Insufficient Notice */}
      {errorMessage && (
        <div className="w-full p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-500 text-xs font-mono flex items-center gap-2 text-left">
          <AlertCircle className="w-4 h-4 flex-shrink-0" />
          <span>{errorMessage}</span>
        </div>
      )}

      {walletBalance !== null && !hasEnoughCoins && (
        <div className="w-full p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-left flex flex-col gap-1.5">
          <div className="flex items-center gap-1.5 text-amber-600 dark:text-amber-400 font-semibold font-mono text-xs">
            <AlertCircle className="w-4 h-4 flex-shrink-0" />
            <span>{t('reader.insufficientBalance', { missing: missingCoins })}</span>
          </div>
          <p className="text-[11px] text-[var(--text-secondary)] leading-relaxed">
            {t('reader.pleaseBuyCoins')}
          </p>
        </div>
      )}

      {/* Action Buttons */}
      <div className="flex flex-col gap-2.5 w-full">
        {walletBalance !== null && !hasEnoughCoins ? (
          <Link to="/shop" className="w-full">
            <button
              type="button"
              className="w-full py-3 px-5 rounded-xl bg-[var(--accent-color)] text-white font-bold text-xs hover:opacity-90 transition-opacity shadow-lg shadow-[var(--accent-color)]/25 cursor-pointer flex items-center justify-center gap-2"
            >
              <ShoppingBag className="w-4 h-4" />
              <span>{t('reader.buyCoin')}</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </Link>
        ) : (
          <button
            type="button"
            disabled={isUnlocking}
            onClick={handleUnlockClick}
            aria-label="Unlock Chapter"
            className="w-full py-3 px-5 rounded-xl bg-[var(--accent-color)] text-white font-bold text-xs hover:opacity-90 disabled:opacity-50 transition-opacity shadow-lg shadow-[var(--accent-color)]/25 cursor-pointer flex items-center justify-center gap-2"
          >
            {isUnlocking ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin text-white" />
                <span>{t('reader.unlocking')}</span>
              </>
            ) : (
              <>
                <Coins className="w-4 h-4" />
                <span>{t('reader.unlockWithCoins', { price: coinPrice })}</span>
              </>
            )}
          </button>
        )}

        <Link
          to={`/manga/${chapter.series?.slug || chapter.content_slug || ''}`}
          className="text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)] font-mono transition-colors pt-1"
        >
          {t('reader.returnToSeries')}
        </Link>
      </div>
    </div>
  );
};

export const LockedChapter = LockPanel;

