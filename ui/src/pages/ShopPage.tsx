import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  Coins,
  Sparkles,
  Zap,
  ShieldCheck,
  Check,
  ArrowRight,
  Wallet,
  Clock,
  HelpCircle,
} from 'lucide-react';
import { walletService } from '../services';
import { ShopPackage, WalletData } from '../types/api';
import { Button } from '../components/ui/Button';
import { TopUpModal } from '../components/wallet/TopUpModal';

export const ShopPage: React.FC = () => {
  const [packages, setPackages] = useState<ShopPackage[]>([]);
  const [wallet, setWallet] = useState<WalletData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isTopUpOpen, setIsTopUpOpen] = useState(false);
  const [selectedPkgId, setSelectedPkgId] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    const [pkgRes, walletRes] = await Promise.all([
      walletService.getShopPackages(),
      walletService.getWallet(),
    ]);

    if (pkgRes.status === 'success' && pkgRes.data) {
      setPackages(pkgRes.data);
    }
    if (walletRes.status === 'success' && walletRes.data) {
      setWallet(walletRes.data);
    }
    setIsLoading(false);
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleBuyClick = (pkgId: string) => {
    setSelectedPkgId(pkgId);
    setIsTopUpOpen(true);
  };

  const handleTopUpSuccess = async () => {
    await loadData();
  };

  const perks = [
    {
      icon: Zap,
      title: 'Anında Kilit Açma',
      desc: 'Satın aldığınız coinlerle favori webtoon ve manga bölümlerine beklemeden erişin.',
    },
    {
      icon: ShieldCheck,
      title: 'Sınırsız ve Güvenli Erişim',
      desc: 'Kilidini açtığınız tüm bölümler hesabınıza kalıcı olarak tanımlanır.',
    },
    {
      icon: Sparkles,
      title: 'İçerik Üreticilerine Destek',
      desc: 'Her coin harcamanız, kaliteli çeviri ve orijinal içerik üretimini destekler.',
    },
  ];

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-10 transition-colors duration-300">
      {/* Header Banner */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 border-b border-[var(--border-color)] pb-8">
        <div className="flex flex-col gap-1.5">
          <div className="flex items-center gap-2">
            <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-mono font-bold">
              Mağaza & Coin Merkezi
            </span>
          </div>
          <h1 className="font-serif text-3xl sm:text-4xl font-bold text-[var(--text-primary)]">
            Coin & Bakiye <span className="italic text-[var(--accent-color)]">Paketleri</span>
          </h1>
          <p className="text-xs sm:text-sm text-[var(--text-secondary)] font-light max-w-xl leading-relaxed">
            Kilitli bölümleri anında açmak, yeni serileri keşfetmek ve platformu desteklemek için en uygun paketi seçin.
          </p>
        </div>

        {/* Current Balance Widget */}
        <div className="p-4 rounded-2xl bg-[var(--bg-card)] border border-[var(--border-color)] shadow-sm flex items-center justify-between gap-4 self-start md:self-auto min-w-[240px]">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)]">
              <Wallet className="w-5 h-5" />
            </div>
            <div className="flex flex-col">
              <span className="text-[10px] uppercase font-mono tracking-wider text-[var(--text-muted)]">
                Mevcut Bakiye
              </span>
              <div className="flex items-center gap-1.5 font-serif font-bold text-lg text-[var(--accent-color)]">
                <Coins className="w-4 h-4 fill-current" />
                <span>{wallet?.balance_coin ?? 180} Coin</span>
              </div>
            </div>
          </div>

          <Link
            to="/wallet"
            className="text-xs font-mono text-[var(--text-secondary)] hover:text-[var(--accent-color)] flex items-center gap-1 transition-colors"
          >
            <span>Cüzdan</span>
            <ArrowRight className="w-3.5 h-3.5" />
          </Link>
        </div>
      </div>

      {/* Packages Grid */}
      <div className="flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <h2 className="font-serif text-xl font-bold text-[var(--text-primary)]">
            Mevcut <span className="italic text-[var(--accent-color)]">Paketler</span>
          </h2>
          <span className="text-xs font-mono text-[var(--text-muted)]">
            Güvenli & Anında Yükleme
          </span>
        </div>

        {isLoading ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            {[...Array(3)].map((_, i) => (
              <div key={i} className="h-72 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-3xl animate-pulse" />
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            {packages.map((pkg) => {
              const totalCoins = pkg.coin_amount + (pkg.bonus_coin || 0);
              const isPopular = pkg.is_featured || pkg.badge === 'Popüler' || pkg.bonus_coin >= 50;

              return (
                <div
                  key={pkg.id}
                  className={`bg-[var(--bg-card)] border rounded-3xl p-6 sm:p-7 flex flex-col justify-between gap-6 transition-all duration-300 relative overflow-hidden shadow-sm hover:shadow-xl group ${
                    isPopular
                      ? 'border-[var(--accent-color)] ring-2 ring-[var(--accent-color)]/20'
                      : 'border-[var(--border-color)] hover:border-[var(--accent-color)]'
                  }`}
                >
                  {/* Highlight Ribbon */}
                  {isPopular && (
                    <div className="absolute top-0 right-0 bg-[var(--accent-color)] text-white text-[10px] font-mono font-bold uppercase tracking-wider px-3.5 py-1 rounded-bl-xl shadow-sm flex items-center gap-1">
                      <Sparkles className="w-3 h-3 fill-current" />
                      <span>{pkg.badge || 'En Popüler'}</span>
                    </div>
                  )}

                  {/* Package Header */}
                  <div className="flex flex-col gap-3">
                    <span className="text-sm font-bold font-serif text-[var(--text-primary)]">
                      {pkg.name}
                    </span>

                    <div className="flex items-baseline gap-2">
                      <div className="text-3xl sm:text-4xl font-serif text-[var(--accent-color)] font-extrabold flex items-center gap-1.5">
                        <Coins className="w-6 h-6 fill-current shrink-0" />
                        <span>{totalCoins}</span>
                      </div>
                      <span className="text-xs font-mono text-[var(--text-muted)] uppercase">
                        Coin
                      </span>
                    </div>

                    {pkg.bonus_coin > 0 && (
                      <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 font-mono text-xs font-semibold w-fit">
                        <Zap className="w-3.5 h-3.5 fill-current" />
                        <span>+{pkg.bonus_coin} Bonus Coin Hediye</span>
                      </div>
                    )}
                  </div>

                  {/* Pricing and Action */}
                  <div className="flex flex-col gap-3 pt-5 border-t border-[var(--border-color)]">
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-mono text-[var(--text-muted)]">Fiyat</span>
                      <span className="text-2xl font-serif text-[var(--text-primary)] font-bold">
                        {pkg.display_price}
                      </span>
                    </div>

                    <Button
                      variant={isPopular ? 'gold' : 'secondary'}
                      size="md"
                      fullWidth
                      onClick={() => handleBuyClick(pkg.id)}
                      className={`gap-2 cursor-pointer shadow-md ${
                        isPopular
                          ? 'bg-[var(--accent-color)] text-white hover:opacity-90'
                          : 'hover:border-[var(--accent-color)] hover:text-[var(--accent-color)]'
                      }`}
                    >
                      <Coins className="w-4 h-4" />
                      <span>Hemen Satın Al</span>
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Coin Perks / Features */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t border-[var(--border-color)]">
        {perks.map((perk, idx) => {
          const Icon = perk.icon;
          return (
            <div
              key={idx}
              className="p-5 rounded-2xl bg-[var(--bg-card)] border border-[var(--border-color)] flex items-start gap-4 shadow-xs"
            >
              <div className="p-2.5 rounded-xl bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] shrink-0">
                <Icon className="w-5 h-5" />
              </div>
              <div className="flex flex-col gap-1">
                <h3 className="font-serif font-bold text-sm text-[var(--text-primary)]">
                  {perk.title}
                </h3>
                <p className="text-xs text-[var(--text-secondary)] font-light leading-relaxed">
                  {perk.desc}
                </p>
              </div>
            </div>
          );
        })}
      </div>

      {/* TopUp Modal */}
      <TopUpModal
        isOpen={isTopUpOpen}
        onClose={() => {
          setIsTopUpOpen(false);
          setSelectedPkgId(null);
        }}
        onSuccess={handleTopUpSuccess}
      />
    </div>
  );
};

