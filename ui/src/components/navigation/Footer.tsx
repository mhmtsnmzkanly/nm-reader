import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Sparkles, Heart, Shield, BookOpen, Compass, Wallet, Bell, Layers } from 'lucide-react';
import { usePreferences } from '../../contexts/PreferencesContext';

export const Footer: React.FC = () => {
  const { t } = usePreferences();
  const location = useLocation();

  // Hide footer on reader view
  if (location.pathname.includes('/chapter/')) {
    return null;
  }

  return (
    <footer className="w-full bg-[var(--bg-card)] border-t border-[var(--border-color)] mt-auto transition-colors duration-300">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-14 flex flex-col gap-10">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8">
          {/* Brand & Mission */}
          <div className="flex flex-col gap-3">
            <Link to="/" className="flex items-center gap-2 group">
              <div className="w-8 h-8 rounded-xl bg-[var(--accent-color)] text-white flex items-center justify-center font-bold text-sm tracking-wider uppercase shadow-md shadow-[var(--accent-color)]/20">
                NM
              </div>
              <div className="flex items-baseline">
                <span className="font-light tracking-[0.15em] text-sm uppercase text-[var(--text-primary)]">
                  NM-READER
                </span>
                <span className="font-serif italic text-[var(--accent-color)] text-xs ml-1 font-normal">
                  lumiere
                </span>
              </div>
            </Link>
            <p className="text-xs text-[var(--text-secondary)] leading-relaxed font-light">
              Geniş manga, webtoon, manhwa ve novel kütüphanesi ile modern, hızlı ve kesintisiz dijital okuma deneyimi.
            </p>
          </div>

          {/* Keşfet & Türler */}
          <div className="flex flex-col gap-2.5">
            <span className="text-[11px] font-mono font-bold uppercase tracking-wider text-[var(--text-primary)]">
              Kategoriler
            </span>
            <div className="flex flex-col gap-1.5 text-xs text-[var(--text-secondary)]">
              <Link to="/browse/manga" className="hover:text-[var(--accent-color)] transition-colors">
                Manga
              </Link>
              <Link to="/browse/manhwa" className="hover:text-[var(--accent-color)] transition-colors">
                Manhwa
              </Link>
              <Link to="/browse/manhua" className="hover:text-[var(--accent-color)] transition-colors">
                Manhua
              </Link>
              <Link to="/browse/webtoon" className="hover:text-[var(--accent-color)] transition-colors">
                Webtoon
              </Link>
              <Link to="/browse/novel" className="hover:text-[var(--accent-color)] transition-colors">
                Novel & Light Novel
              </Link>
            </div>
          </div>

          {/* Hızlı Erişim */}
          <div className="flex flex-col gap-2.5">
            <span className="text-[11px] font-mono font-bold uppercase tracking-wider text-[var(--text-primary)]">
              Hızlı Erişim
            </span>
            <div className="flex flex-col gap-1.5 text-xs text-[var(--text-secondary)]">
              <Link to="/genres" className="hover:text-[var(--accent-color)] transition-colors">
                Tür Dizini (Genres)
              </Link>
              <Link to="/tags" className="hover:text-[var(--accent-color)] transition-colors">
                Etiketler (Tags)
              </Link>
              <Link to="/blogs" className="hover:text-[var(--accent-color)] transition-colors">
                Blog & İncelemeler
              </Link>
              <Link to="/shop" className="hover:text-[var(--accent-color)] transition-colors">
                Coin & Mağaza
              </Link>
              <Link to="/preferences" className="hover:text-[var(--accent-color)] transition-colors">
                Tercihler & Tema
              </Link>
            </div>
          </div>

          {/* Topluluk & Güvenlik */}
          <div className="flex flex-col gap-2.5">
            <span className="text-[11px] font-mono font-bold uppercase tracking-wider text-[var(--text-primary)]">
              Kullanıcı & Destek
            </span>
            <div className="flex flex-col gap-1.5 text-xs text-[var(--text-secondary)]">
              <Link to="/library" className="hover:text-[var(--accent-color)] transition-colors">
                Kütüphanem
              </Link>
              <Link to="/history" className="hover:text-[var(--accent-color)] transition-colors">
                Okuma Geçmişi
              </Link>
              <Link to="/wallet" className="hover:text-[var(--accent-color)] transition-colors">
                Cüzdan Yönetimi
              </Link>
              <Link to="/notifications" className="hover:text-[var(--accent-color)] transition-colors">
                Bildirim Merkezi
              </Link>
            </div>
          </div>
        </div>

        {/* Bottom copyright */}
        <div className="border-t border-[var(--border-color)] pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[var(--text-muted)]">
          <div className="flex items-center gap-1 font-mono text-[11px]">
            <span>© 2026 NM-Reader Lumiere. Tüm hakları saklıdır.</span>
          </div>
          <div className="flex items-center gap-4 text-[11px] font-mono">
            <span className="hover:text-[var(--accent-color)] cursor-pointer">Gizlilik Politikası</span>
            <span>•</span>
            <span className="hover:text-[var(--accent-color)] cursor-pointer">Kullanım Şartları</span>
            <span>•</span>
            <span className="hover:text-[var(--accent-color)] cursor-pointer">DMCA & İletişim</span>
          </div>
        </div>
      </div>
    </footer>
  );
};
