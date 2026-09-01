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
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
        {/* Brand */}
        <div className="flex items-center gap-3">
          <Link to="/" className="flex items-center gap-2 group">
            <div className="w-7 h-7 rounded-lg bg-[var(--accent-color)] text-white flex items-center justify-center font-bold text-xs tracking-wider uppercase shadow-sm">
              NM
            </div>
            <div className="flex items-baseline">
              <span className="font-light tracking-[0.15em] text-xs uppercase text-[var(--text-primary)]">
                NM-READER
              </span>
              <span className="font-serif italic text-[var(--accent-color)] text-[11px] ml-1 font-normal">
                lumiere
              </span>
            </div>
          </Link>
          <span className="hidden sm:inline text-xs text-[var(--text-muted)]">•</span>
          <span className="hidden sm:inline text-[11px] text-[var(--text-secondary)] font-light">
            {t('footer.tagline')}
          </span>
        </div>

        {/* Legal & Copyright */}
        <div className="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 text-[11px] font-mono text-[var(--text-muted)]">
          <span>{t('footer.allRightsReserved')}</span>
          <div className="flex items-center gap-3">
            <span className="hover:text-[var(--accent-color)] transition-colors cursor-pointer">
              {t('footer.privacy')}
            </span>
            <span>•</span>
            <span className="hover:text-[var(--accent-color)] transition-colors cursor-pointer">
              {t('footer.terms')}
            </span>
            <span>•</span>
            <span className="hover:text-[var(--accent-color)] transition-colors cursor-pointer">
              {t('footer.dmca')}
            </span>
          </div>
        </div>
      </div>
    </footer>
  );
};
