import React, { createContext, useContext, useMemo } from 'react';

export type SiteConfig = {
  site_name: string;
  site_slogan: string;
  site_abbreviation: string;
  site_description: string;
  default_language: 'tr' | 'en';
  footer_text: string;
  default_theme: string;
  site_logo: string;
  logo_url: string;
  favicon_url: string;
  default_profile_image: string;
  default_content_cover_image: string;
};

export const defaultSiteConfig: SiteConfig = {
  site_name: 'NM Reader',
  site_slogan: 'En İyi Çevrimiçi Manga ve Novel Okuyucusu',
  site_abbreviation: 'NMR',
  site_description: 'Read manga, manhwa, webtoon and novels.',
  default_language: 'tr',
  footer_text: '© 2026 NM Reader. Tüm hakları saklıdır.',
  default_theme: 'dark',
  site_logo: '/assets/img/logo-header.svg',
  logo_url: '/assets/img/logo-footer.svg',
  favicon_url: '/favicon.ico',
  default_profile_image: '/assets/img/default-profile.png',
  default_content_cover_image: '/assets/img/covers/placeholder.svg',
};

function readBootstrapConfig(): SiteConfig {
  if (typeof window === 'undefined') return defaultSiteConfig;

  const raw = window.__NMR_CONTEXT?.site_config;
  if (!raw || typeof raw !== 'object') return defaultSiteConfig;

  const config = { ...defaultSiteConfig } as SiteConfig;
  (Object.keys(defaultSiteConfig) as Array<keyof SiteConfig>).forEach((key) => {
    const value = (raw as Record<string, unknown>)[key];
    if (typeof value === 'string' && value.trim() !== '') {
      if (key === 'default_language') {
        config[key] = value === 'en' ? 'en' : 'tr';
      } else {
        config[key] = value as never;
      }
    }
  });
  return config;
}

const SiteConfigContext = createContext<SiteConfig>(defaultSiteConfig);

export const SiteConfigProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const config = useMemo(readBootstrapConfig, []);
  return <SiteConfigContext.Provider value={config}>{children}</SiteConfigContext.Provider>;
};

export const useSiteConfig = (): SiteConfig => useContext(SiteConfigContext);
