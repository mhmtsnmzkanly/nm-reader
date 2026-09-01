import React, { useState, useEffect, useCallback } from 'react';
import {
  ShieldCheck,
  Zap,
  Sparkles,
  CheckCircle2,
  AlertTriangle,
  Loader2,
  Coins,
  ArrowRight,
} from 'lucide-react';
import { walletService } from '../../services';
import { FeatureEntitlement } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

type PurchaseState = 'idle' | 'purchasing' | 'success' | 'error';

interface AdFreeCardProps {
  onPurchased?: () => void;
  onOpenTopUp?: () => void;
}

export const AdFreeCard: React.FC<AdFreeCardProps> = ({ onPurchased, onOpenTopUp }) => {
  const { t } = usePreferences();
  const [purchaseState, setPurchaseState] = useState<PurchaseState>('idle');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [activeEntitlement, setActiveEntitlement] = useState<FeatureEntitlement | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);

  const checkStatus = useCallback(async () => {
    setIsLoading(true);
    const res = await walletService.getFeatureEntitlements();
    if (res.status === 'success' && res.data) {
      const adFree = res.data.find(
        (e) =>
          e.feature_key === 'ad_free' &&
          (e.is_active || (e.expires_at && new Date(e.expires_at).getTime() > Date.now()))
      );
      setActiveEntitlement(adFree || null);
    }
    setIsLoading(false);
  }, []);

  useEffect(() => {
    checkStatus();
  }, [checkStatus]);

  const handlePurchase = async () => {
    setPurchaseState('purchasing');
    setErrorMessage(null);

    const res = await walletService.purchaseAdFree();
    if (res.status === 'success') {
      setPurchaseState('success');
      await checkStatus();
      if (onPurchased) onPurchased();
    } else {
      setPurchaseState('error');
      setErrorMessage(res.error.message || t('adFree.statusError'));
    }
  };

  const isAlreadyActive = !!activeEntitlement;

  return (
    <div
      className={`rounded-3xl p-6 sm:p-7 border relative overflow-hidden transition-all duration-300 ${
        isAlreadyActive
          ? 'bg-emerald-500/5 border-emerald-500/30 ring-2 ring-emerald-500/20 shadow-md'
          : 'bg-[var(--bg-card)] border-[var(--border-color)] hover:border-[var(--accent-color)] shadow-sm'
      }`}
    >
      {/* Background subtle decoration */}
      <div className="absolute top-0 right-0 -mt-8 -mr-8 w-36 h-36 bg-[var(--accent-color)]/5 rounded-full blur-2xl pointer-events-none" />

      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-6 relative z-10">
        {/* Left Side: Info & Value Prop */}
        <div className="flex items-start gap-4">
          <div
            className={`w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 border ${
              isAlreadyActive
                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                : 'bg-[var(--accent-light)] text-[var(--accent-color)] border-[var(--accent-border)]'
            }`}
          >
            <ShieldCheck className="w-6 h-6" />
          </div>

          <div className="flex flex-col gap-1.5 max-w-xl">
            <div className="flex items-center gap-2 flex-wrap">
              <h3 className="font-serif font-bold text-lg text-[var(--text-primary)]">
                {t('shop.adFreeTitle')}
              </h3>

              {isAlreadyActive ? (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500 text-white shadow-xs">
                  <CheckCircle2 className="w-3 h-3" />
                  {t('adFree.badgeActive')}
                </span>
              ) : (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-[var(--accent-color)] text-white">
                  <Sparkles className="w-3 h-3" />
                  {t('adFree.priceLabel')}
                </span>
              )}
            </div>

            <p className="text-xs text-[var(--text-secondary)] leading-relaxed">
              {isAlreadyActive && activeEntitlement?.expires_at
                ? t('adFree.activeUntil', {
                    date: new Date(activeEntitlement.expires_at).toLocaleDateString(),
                  })
                : t('shop.adFreeDesc')}
            </p>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] font-mono text-[var(--text-muted)] mt-1">
              <span className="flex items-center gap-1">
                <Zap className="w-3.5 h-3.5 text-amber-500" />
                {t('adFree.zeroDelay')}
              </span>
              <span className="flex items-center gap-1">
                <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" />
                {t('adFree.compatibleAll')}
              </span>
            </div>
          </div>
        </div>

        {/* Right Side: Action Button with State Machine */}
        <div className="flex flex-col items-end gap-2 shrink-0">
          {isAlreadyActive ? (
            <div className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
              <CheckCircle2 className="w-4 h-4" />
              <span>{t('shop.adFreeActive', { expires: activeEntitlement?.expires_at ? new Date(activeEntitlement.expires_at).toLocaleDateString() : 'Aktif' })}</span>
            </div>
          ) : (
            <div className="flex flex-col gap-2 w-full sm:w-auto">
              <button
                onClick={handlePurchase}
                disabled={purchaseState === 'purchasing'}
                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[var(--accent-color)] text-white hover:opacity-90 font-semibold text-xs transition-opacity shadow-md shadow-[var(--accent-color)]/20 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
              >
                {purchaseState === 'purchasing' ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    <span>{t('adFree.statusPurchasing')}</span>
                  </>
                ) : (
                  <>
                    <Coins className="w-4 h-4" />
                    <span>{t('adFree.activateNow')}</span>
                  </>
                )}
              </button>

              {purchaseState === 'error' && (
                <div className="flex flex-col gap-1 text-right">
                  <span className="text-[11px] text-rose-500 font-medium">
                    {errorMessage}
                  </span>
                  {onOpenTopUp && (
                    <button
                      onClick={onOpenTopUp}
                      className="text-[11px] font-mono text-[var(--accent-color)] hover:underline flex items-center justify-end gap-1"
                    >
                      <span>{t('access.goToShop')}</span>
                      <ArrowRight className="w-3 h-3" />
                    </button>
                  )}
                </div>
              )}

              {purchaseState === 'success' && (
                <span className="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium text-right flex items-center justify-end gap-1">
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  {t('adFree.statusSuccess')}
                </span>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
