import React from 'react';
import { Coins, Plus, TrendingUp, TrendingDown, Clock, ShoppingBag } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Button } from '../ui/Button';
import { WalletData } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

type WalletBalanceProps = {
  wallet?: WalletData | null;
  onOpenTopUp?: () => void;
};

export const WalletBalance: React.FC<WalletBalanceProps> = ({
  wallet,
  onOpenTopUp,
}) => {
  const { formatDate, t } = usePreferences();
  const balance = wallet?.balance_coin ?? wallet?.balance ?? 180;
  const purchased = wallet?.total_coin_purchased ?? 450;
  const spent = wallet?.total_coin_spent ?? 270;
  const lastUpdated = wallet?.updated_at
    ? formatDate(wallet.updated_at, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    : t('time.today');

  return (
    <div className="bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)] p-6 sm:p-7 shadow-xl flex flex-col gap-6 relative overflow-hidden transition-colors duration-300">
      <div className="absolute top-0 right-0 w-80 h-80 bg-[var(--accent-color)]/5 rounded-full blur-3xl pointer-events-none" />

      {/* Main Row */}
      <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 relative z-10">
        <div className="flex items-center gap-4 sm:gap-5">
          <div className="w-16 h-16 rounded-2xl bg-[var(--accent-light)] border border-[var(--accent-border)] flex items-center justify-center text-[var(--accent-color)] shadow-inner flex-shrink-0">
            <Coins className="w-9 h-9 fill-current animate-pulse" />
          </div>
          <div className="flex flex-col gap-0.5">
            <span className="text-[11px] uppercase tracking-[0.2em] text-[var(--text-muted)] font-semibold font-mono">
              Mevcut Coin Bakiyeniz
            </span>
            <div className="text-3xl sm:text-4xl font-serif font-bold text-[var(--text-primary)] tracking-tight flex items-baseline gap-2">
              <span>{balance.toLocaleString('tr-TR')}</span>
              <span className="text-sm sm:text-base font-sans text-[var(--accent-color)] font-medium">
                Coin
              </span>
            </div>
          </div>
        </div>

        {/* Action CTAs */}
        <div className="flex items-center gap-3 w-full md:w-auto">
          {onOpenTopUp ? (
            <Button
              variant="gold"
              size="md"
              onClick={onOpenTopUp}
              className="gap-2 flex-1 md:flex-initial bg-[var(--accent-color)] text-white hover:opacity-90 font-semibold shadow-md shadow-[var(--accent-color)]/20"
            >
              <Plus className="w-4 h-4 text-white" />
              <span>Hızlı Coin Yükle</span>
            </Button>
          ) : (
            <Link to="/shop" className="flex-1 md:flex-initial">
              <Button
                variant="gold"
                size="md"
                className="w-full gap-2 bg-[var(--accent-color)] text-white hover:opacity-90 font-semibold shadow-md shadow-[var(--accent-color)]/20"
              >
                <Plus className="w-4 h-4 text-white" />
                <span>Coin Yükle</span>
              </Button>
            </Link>
          )}

          <Link to="/shop" className="flex-1 md:flex-initial">
            <Button
              variant="outline"
              size="md"
              className="w-full gap-2 border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]"
            >
              <ShoppingBag className="w-4 h-4" />
              <span>Paketler</span>
            </Button>
          </Link>
        </div>
      </div>

      {/* Stats Divider & Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-5 border-t border-[var(--border-color)] relative z-10">
        <div className="p-3.5 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-between gap-3">
          <div className="flex flex-col gap-0.5">
            <span className="text-[10px] uppercase font-mono tracking-wider text-[var(--text-muted)]">
              Toplam Yüklenen
            </span>
            <span className="text-base font-mono font-bold text-emerald-500">
              +{purchased.toLocaleString('tr-TR')} Coin
            </span>
          </div>
          <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-500">
            <TrendingUp className="w-4 h-4" />
          </div>
        </div>

        <div className="p-3.5 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-between gap-3">
          <div className="flex flex-col gap-0.5">
            <span className="text-[10px] uppercase font-mono tracking-wider text-[var(--text-muted)]">
              Toplam Harcanan
            </span>
            <span className="text-base font-mono font-bold text-[var(--text-primary)]">
              -{spent.toLocaleString('tr-TR')} Coin
            </span>
          </div>
          <div className="p-2 rounded-lg bg-[var(--accent-light)] text-[var(--accent-color)]">
            <TrendingDown className="w-4 h-4" />
          </div>
        </div>

        <div className="p-3.5 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-between gap-3">
          <div className="flex flex-col gap-0.5">
            <span className="text-[10px] uppercase font-mono tracking-wider text-[var(--text-muted)]">
              Son Güncelleme
            </span>
            <span className="text-xs font-mono font-semibold text-[var(--text-secondary)] truncate">
              {lastUpdated}
            </span>
          </div>
          <div className="p-2 rounded-lg bg-[var(--bg-card)] text-[var(--text-muted)]">
            <Clock className="w-4 h-4" />
          </div>
        </div>
      </div>
    </div>
  );
};
