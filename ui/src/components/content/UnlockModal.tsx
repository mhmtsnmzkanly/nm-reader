import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Lock, Coins, AlertCircle, CheckCircle2, ShoppingBag } from 'lucide-react';
import { Dialog } from '../ui/Dialog';
import { Button } from '../ui/Button';
import { ContentDetailChapter } from '../../types/api';

type UnlockModalProps = {
  isOpen: boolean;
  onClose: () => void;
  targetChapter?: ContentDetailChapter | null;
  isSeriesUnlock?: boolean;
  seriesTitle?: string;
  seriesPrice?: number;
  walletBalance: number;
  onConfirmUnlock: (chapterId?: string) => Promise<boolean>;
};

export const UnlockModal: React.FC<UnlockModalProps> = ({
  isOpen,
  onClose,
  targetChapter,
  isSeriesUnlock = false,
  seriesTitle = '',
  seriesPrice = 0,
  walletBalance,
  onConfirmUnlock,
}) => {
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSuccess, setIsSuccess] = useState(false);

  const price = isSeriesUnlock
    ? seriesPrice
    : targetChapter?.price_coin ?? 10;

  const title = isSeriesUnlock
    ? `Tüm Serinin Kilidini Aç: ${seriesTitle}`
    : `Bölüm ${targetChapter?.number || targetChapter?.chapter_number || ''} Kilidini Aç`;

  const itemSubtitle = isSeriesUnlock
    ? 'Bu serideki mevcut ve gelecekteki tüm kilitli bölümlere süresiz erişim kazanırsınız.'
    : targetChapter?.title
    ? `"${targetChapter.title}" başlıklı bölüme süresiz erişim kazanırsınız.`
    : 'Bu bölüme süresiz erişim kazanırsınız.';

  const hasEnoughCoins = walletBalance >= price;

  const handleUnlock = async () => {
    setIsProcessing(true);
    setError(null);

    try {
      const ok = await onConfirmUnlock(targetChapter?.id);
      if (ok) {
        setIsSuccess(true);
        setTimeout(() => {
          setIsSuccess(false);
          onClose();
        }, 1200);
      } else {
        setError('Kilit açma işlemi gerçekleştirilemedi. Lütfen bakiyenizi kontrol edin.');
      }
    } catch {
      setError('Bir hata meydana geldi.');
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="Kilitli İçeriğe Erişim">
      <div className="flex flex-col gap-5 py-2">
        {/* Header Icon + Info */}
        <div className="flex items-start gap-4 p-4 rounded-2xl bg-[var(--bg-tertiary)] border border-[var(--border-color)]">
          <div className="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-500 flex-shrink-0">
            <Lock className="w-6 h-6" />
          </div>
          <div className="flex flex-col gap-1 min-w-0">
            <h4 className="font-serif font-bold text-sm sm:text-base text-[var(--text-primary)] truncate">
              {title}
            </h4>
            <p className="text-xs text-[var(--text-secondary)] font-light leading-relaxed">
              {itemSubtitle}
            </p>
          </div>
        </div>

        {/* Coin & Balance Summary */}
        <div className="grid grid-cols-2 gap-3 font-mono text-xs">
          <div className="p-3.5 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] flex flex-col gap-1">
            <span className="text-[10px] uppercase text-[var(--text-muted)] tracking-wider">İşlem Ücreti</span>
            <div className="flex items-center gap-1.5 text-amber-500 font-bold text-base">
              <Coins className="w-4 h-4" />
              <span>{price} Coin</span>
            </div>
          </div>

          <div className="p-3.5 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] flex flex-col gap-1">
            <span className="text-[10px] uppercase text-[var(--text-muted)] tracking-wider">Mevcut Bakiyeniz</span>
            <div className={`flex items-center gap-1.5 font-bold text-base ${hasEnoughCoins ? 'text-emerald-500' : 'text-rose-500'}`}>
              <Coins className="w-4 h-4" />
              <span>{walletBalance} Coin</span>
            </div>
          </div>
        </div>

        {/* Status Alerts */}
        {isSuccess && (
          <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 text-xs font-mono flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 flex-shrink-0" />
            <span>Kilit başarıyla açıldı! Yönlendiriliyorsunuz...</span>
          </div>
        )}

        {error && (
          <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-500 text-xs font-mono flex items-center gap-2">
            <AlertCircle className="w-4 h-4 flex-shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {!hasEnoughCoins && !isSuccess && (
          <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-xs flex flex-col gap-2">
            <div className="flex items-center gap-1.5 font-semibold font-mono">
              <AlertCircle className="w-4 h-4 flex-shrink-0" />
              <span>Yetersiz Bakiye</span>
            </div>
            <p className="text-[11px] leading-relaxed">
              Bu bölümü açmak için {price - walletBalance} Coin daha gereklidir. Mağazadan Coin paketi temin edebilirsiniz.
            </p>
          </div>
        )}

        {/* Footer Actions */}
        <div className="flex items-center justify-end gap-3 pt-3 border-t border-[var(--border-color)]">
          <Button variant="ghost" size="md" onClick={onClose} disabled={isProcessing}>
            Vazgeç
          </Button>

          {hasEnoughCoins ? (
            <Button
              variant="gold"
              size="md"
              onClick={handleUnlock}
              isLoading={isProcessing}
              disabled={isSuccess}
              className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90 font-semibold"
            >
              <Lock className="w-4 h-4" />
              <span>{price} Coin ile Aç</span>
            </Button>
          ) : (
            <Link to="/shop" onClick={onClose}>
              <Button variant="gold" size="md" className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90">
                <ShoppingBag className="w-4 h-4" />
                <span>Coin Satın Al</span>
              </Button>
            </Link>
          )}
        </div>
      </div>
    </Dialog>
  );
};
