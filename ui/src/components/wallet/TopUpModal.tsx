import React, { useState, useEffect } from 'react';
import { Coins, Sparkles, Check, X, ShieldCheck, ArrowRight, Loader2 } from 'lucide-react';
import { walletService } from '../../services';
import { ShopPackage } from '../../types/api';
import { Button } from '../ui/Button';

type TopUpModalProps = {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (newBalance: number, pkg: ShopPackage) => void;
};

export const TopUpModal: React.FC<TopUpModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [packages, setPackages] = useState<ShopPackage[]>([]);
  const [selectedPkg, setSelectedPkg] = useState<ShopPackage | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isPurchasing, setIsPurchasing] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!isOpen) {
      setSuccessMessage(null);
      return;
    }

    const loadPackages = async () => {
      setIsLoading(true);
      const res = await walletService.getShopPackages();
      if (res.status === 'success' && res.data) {
        setPackages(res.data);
        const featured = res.data.find((p) => p.is_featured) || res.data[0];
        setSelectedPkg(featured || null);
      }
      setIsLoading(false);
    };

    loadPackages();
  }, [isOpen]);

  if (!isOpen) return null;

  const handlePurchase = async () => {
    if (!selectedPkg) return;

    setIsPurchasing(true);
    try {
      const res = await walletService.purchasePackage(selectedPkg.id);
      if (res.status === 'success' && res.data) {
        const total = selectedPkg.coin_amount + selectedPkg.bonus_coin;
        setSuccessMessage(`+${total} Coin başarıyla cüzdanınıza yüklendi!`);
        setTimeout(() => {
          onSuccess(res.data.balance, selectedPkg);
          onClose();
        }, 1200);
      }
    } catch {
      // ignore
    } finally {
      setIsPurchasing(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
      onClick={onClose}
    >
      <div
        className="bg-[var(--bg-card)] border border-[var(--border-color)] rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl flex flex-col gap-6 relative"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-[var(--accent-light)] border border-[var(--accent-border)] flex items-center justify-center text-[var(--accent-color)]">
              <Coins className="w-5 h-5 fill-current" />
            </div>
            <div>
              <span className="text-[10px] uppercase tracking-widest text-[var(--accent-color)] font-mono font-bold">
                Coin Mağazası
              </span>
              <h3 className="font-serif font-bold text-lg text-[var(--text-primary)]">
                Bakiye Yükle
              </h3>
            </div>
          </div>

          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg text-[var(--text-muted)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] flex items-center justify-center transition-colors cursor-pointer"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {successMessage ? (
          <div className="py-12 flex flex-col items-center justify-center gap-3 text-center animate-in zoom-in-95">
            <div className="w-16 h-16 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 flex items-center justify-center">
              <Check className="w-8 h-8" />
            </div>
            <h4 className="font-serif text-lg font-bold text-[var(--text-primary)]">
              İşlem Başarılı!
            </h4>
            <p className="text-xs text-[var(--text-secondary)] max-w-xs">
              {successMessage}
            </p>
          </div>
        ) : (
          <>
            {/* Packages Grid */}
            <div className="flex flex-col gap-2.5">
              <label className="text-xs font-mono text-[var(--text-muted)] uppercase tracking-wider">
                Paket Seçin
              </label>

              {isLoading ? (
                <div className="py-8 flex justify-center text-[var(--accent-color)]">
                  <Loader2 className="w-6 h-6 animate-spin" />
                </div>
              ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[300px] overflow-y-auto pr-1">
                  {packages.map((pkg) => {
                    const isSelected = selectedPkg?.id === pkg.id;
                    const totalCoin = pkg.coin_amount + pkg.bonus_coin;

                    return (
                      <div
                        key={pkg.id}
                        onClick={() => setSelectedPkg(pkg)}
                        className={`p-4 rounded-2xl border transition-all duration-200 cursor-pointer relative flex flex-col justify-between gap-3 ${
                          isSelected
                            ? 'bg-[var(--accent-light)] border-[var(--accent-color)] shadow-md'
                            : 'bg-[var(--bg-tertiary)] border-[var(--border-color)] hover:border-[var(--accent-border)]'
                        }`}
                      >
                        {pkg.is_featured && (
                          <span className="absolute top-3 right-3 text-[9px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-[var(--accent-color)] text-white">
                            Popüler
                          </span>
                        )}

                        <div>
                          <span className="text-xs font-semibold text-[var(--text-primary)] block">
                            {pkg.name}
                          </span>
                          <div className="flex items-baseline gap-1 mt-1">
                            <span className="text-xl font-mono font-bold text-[var(--text-primary)]">
                              {totalCoin}
                            </span>
                            <span className="text-xs text-[var(--accent-color)] font-medium">
                              Coin
                            </span>
                          </div>
                          {pkg.bonus_coin > 0 && (
                            <span className="text-[10px] text-emerald-500 font-mono font-semibold block mt-0.5">
                              +{pkg.bonus_coin} Bonus Coin
                            </span>
                          )}
                        </div>

                        <div className="flex items-center justify-between pt-2 border-t border-[var(--border-color)]/50">
                          <span className="text-xs font-bold font-mono text-[var(--text-primary)]">
                            ₺{pkg.display_price}
                          </span>
                          {isSelected && (
                            <div className="w-5 h-5 rounded-full bg-[var(--accent-color)] text-white flex items-center justify-center">
                              <Check className="w-3 h-3" />
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Selected Summary & Action */}
            {selectedPkg && (
              <div className="flex flex-col gap-3 pt-2">
                <div className="p-3.5 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-between text-xs font-mono">
                  <span className="text-[var(--text-muted)]">Ödenecek Tutar:</span>
                  <span className="font-bold text-sm text-[var(--text-primary)]">
                    ₺{selectedPkg.display_price} {selectedPkg.currency}
                  </span>
                </div>

                <Button
                  variant="gold"
                  size="lg"
                  fullWidth
                  disabled={isPurchasing}
                  onClick={handlePurchase}
                  className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90 font-semibold shadow-lg shadow-[var(--accent-color)]/25 py-3"
                >
                  {isPurchasing ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin text-white" />
                      <span>İşlem Yapılıyor...</span>
                    </>
                  ) : (
                    <>
                      <Sparkles className="w-4 h-4 text-white" />
                      <span>
                        ₺{selectedPkg.display_price} ile {selectedPkg.coin_amount + selectedPkg.bonus_coin} Coin Yükle
                      </span>
                      <ArrowRight className="w-4 h-4 text-white ml-1" />
                    </>
                  )}
                </Button>

                <div className="flex items-center justify-center gap-1.5 text-[10px] text-[var(--text-muted)] font-mono">
                  <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" />
                  <span>Güvenli 256-Bit SSL Şifreli Ödeme Altyapısı</span>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
};
