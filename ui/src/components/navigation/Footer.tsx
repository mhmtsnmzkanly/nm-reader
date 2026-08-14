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
        {/* Bottom copyright */}
        <div className="border-t border-[var(--border-color)] pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[var(--text-muted)]">
          <div className="flex items-center gap-1 font-mono text-[11px]">
            <span>© 2026 NM-Reader.</span>
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
