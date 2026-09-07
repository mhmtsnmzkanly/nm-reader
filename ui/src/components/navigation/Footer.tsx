import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Sparkles, Heart, Shield, BookOpen, Compass, Wallet, Bell, Layers } from 'lucide-react';
import { usePreferences } from '../../contexts/PreferencesContext';
import { useSiteConfig } from '../../contexts/SiteConfigContext';

export const Footer: React.FC = () => {
  const { t } = usePreferences();
  const { site_name: siteName, site_slogan: siteSlogan, footer_text: footerText, logo_url: logoUrl } = useSiteConfig();
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
          <Link to="/" aria-label={siteName} className="flex items-center gap-2 group">
            <img
              src={logoUrl}
              alt={siteName}
              className="h-8 w-auto max-w-[160px] object-contain transition-transform group-hover:scale-105"
              onError={(event) => {
                event.currentTarget.onerror = null;
                event.currentTarget.src = '/assets/img/logo-footer.svg';
              }}
            />
          </Link>
          <span className="hidden sm:inline text-xs text-[var(--text-muted)]">•</span>
          <span className="hidden sm:inline text-[11px] text-[var(--text-secondary)] font-light">
            {siteSlogan || t('footer.tagline')}
          </span>
        </div>

        {/* Legal & Copyright */}
        <div className="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 text-[11px] font-mono text-[var(--text-muted)]">
          <span>{footerText || t('footer.allRightsReserved')}</span>
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
