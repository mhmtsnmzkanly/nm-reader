import React, { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ChevronDown, Coins, Bell, Sun, Moon, Compass, Layers, Tag } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';
import { useNotifications } from '../../contexts/NotificationsContext';
import { SearchCombobox } from './SearchCombobox';
import { AccountMenu } from './AccountMenu';
import { ContentType } from '../../types/api';
import { walletService } from '../../services';

export const Header: React.FC = () => {
  const { isAuthenticated } = useAuth();
  const { unreadCount, openNotificationsModal } = useNotifications();
  const { t, theme, setTheme } = usePreferences();
  const location = useLocation();
  const [isBrowseOpen, setIsBrowseOpen] = useState(false);
  const [walletBalance, setWalletBalance] = useState<number | null>(null);

  useEffect(() => {
    if (!isAuthenticated) {
      setWalletBalance(null);
      return;
    }
    const fetchBalance = async () => {
      try {
        const res = await walletService.getWallet();
        if (res.status === 'success' && res.data) {
          setWalletBalance(res.data.balance_coin ?? res.data.balance ?? 0);
        }
      } catch {
        // ignore
      }
    };
    fetchBalance();
  }, [isAuthenticated, location.pathname]);

  // Close browse dropdown on route change
  useEffect(() => {
    setIsBrowseOpen(false);
  }, [location.pathname]);

  const isDark = theme === 'dark' || theme === 'default' || theme === 'royal';

  const toggleTheme = () => {
    setTheme(isDark ? 'apple' : 'dark');
  };

  const isBrowseActive =
    location.pathname.startsWith('/browse') ||
    location.pathname.startsWith('/genre') ||
    location.pathname.startsWith('/tag');

  const contentTypes: { key: ContentType; label: string }[] = [
    { key: 'manga', label: 'Manga' },
    { key: 'manhwa', label: 'Manhwa' },
    { key: 'manhua', label: 'Manhua' },
    { key: 'webtoon', label: 'Webtoon' },
    { key: 'light-novel', label: 'Light Novel' },
    { key: 'web-novel', label: 'Web Novel' },
    { key: 'novel', label: 'Novel' },
  ];

  return (
    <header className="sticky top-0 z-40 w-full bg-[var(--bg-card)]/90 backdrop-blur-md border-b border-[var(--border-color)] transition-colors duration-300">
      <div className="max-w-7xl mx-auto px-2.5 sm:px-6 h-16 flex items-center justify-between gap-1.5 sm:gap-4">
        {/* Brand Wordmark */}
        <div className="flex items-center gap-2 sm:gap-8 min-w-0">
          <Link to="/" className="flex items-center gap-1.5 sm:gap-2.5 group shrink-0">
            <div className="w-8 h-8 rounded bg-[var(--accent-color)] text-white flex items-center justify-center font-bold text-sm tracking-wider uppercase shadow-lg shadow-[var(--accent-color)]/20 group-hover:scale-105 transition-all">
              NM
            </div>
            <div className="hidden xs:flex items-baseline">
              <span className="font-light tracking-[0.15em] text-xs sm:text-sm uppercase text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors">
                NM-READER
              </span>
              <span className="font-serif italic text-[var(--accent-color)] text-[10px] sm:text-xs ml-1 font-normal">
                lumiere
              </span>
            </div>
          </Link>

          {/* Desktop Navigation */}
          <nav className="hidden lg:flex items-center gap-1 font-medium text-xs tracking-wider uppercase">
            <Link
              to="/"
              className={`px-3 py-2 rounded-lg transition-colors ${
                location.pathname === '/'
                  ? 'text-[var(--accent-color)] font-semibold bg-[var(--accent-light)]'
                  : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)]'
              }`}
            >
              {t('navigation.home')}
            </Link>

            {/* Browse Dropdown with Formats, Genres & Tags */}
            <div
              className="relative"
              onMouseEnter={() => setIsBrowseOpen(true)}
              onMouseLeave={() => setIsBrowseOpen(false)}
            >
              <button
                onClick={() => setIsBrowseOpen((prev) => !prev)}
                className={`flex items-center gap-1.5 px-3 py-2 rounded-lg transition-colors cursor-pointer ${
                  isBrowseActive
                    ? 'text-[var(--accent-color)] font-semibold bg-[var(--accent-light)]'
                    : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)]'
                }`}
              >
                <Compass className="w-3.5 h-3.5 opacity-80" />
                <span>{t('navigation.browse')}</span>
                <ChevronDown
                  className={`w-3.5 h-3.5 opacity-70 transition-transform duration-200 ${
                    isBrowseOpen ? 'rotate-180' : ''
                  }`}
                />
              </button>

              {isBrowseOpen && (
                <div className="absolute left-0 top-full pt-1.5 w-72 sm:w-80 z-50 animate-in fade-in zoom-in-95 duration-150">
                  <div className="p-3 bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)] shadow-2xl flex flex-col gap-3">
                    {/* Section 1: Türler & Etiketler Dizinleri */}
                    <div className="flex flex-col gap-1.5">
                      <span className="px-1 text-[10px] font-mono font-bold uppercase tracking-wider text-[var(--accent-color)]">
                        {t('navigation.categories') || 'Kategoriler & Dizin'}
                      </span>
                      <div className="grid grid-cols-2 gap-1.5">
                        <Link
                          to="/genres"
                          onClick={() => setIsBrowseOpen(false)}
                          className={`flex items-center gap-2.5 p-2 rounded-xl border transition-all ${
                            location.pathname.startsWith('/genre')
                              ? 'bg-[var(--accent-light)] border-[var(--accent-color)]/30 text-[var(--accent-color)] font-semibold'
                              : 'bg-[var(--bg-tertiary)] border-transparent hover:border-[var(--border-color)] text-[var(--text-primary)] hover:bg-[var(--bg-primary)]'
                          }`}
                        >
                          <div className="p-1.5 rounded-lg bg-[var(--bg-card)] text-[var(--accent-color)] shrink-0 shadow-xs">
                            <Layers className="w-3.5 h-3.5" />
                          </div>
                          <div className="flex flex-col min-w-0">
                            <span className="text-xs font-semibold truncate leading-tight">
                              {t('navigation.genres')}
                            </span>
                            <span className="text-[9px] text-[var(--text-muted)] font-mono truncate">
                              Dizini Gör
                            </span>
                          </div>
                        </Link>

                        <Link
                          to="/tags"
                          onClick={() => setIsBrowseOpen(false)}
                          className={`flex items-center gap-2.5 p-2 rounded-xl border transition-all ${
                            location.pathname.startsWith('/tag')
                              ? 'bg-[var(--accent-light)] border-[var(--accent-color)]/30 text-[var(--accent-color)] font-semibold'
                              : 'bg-[var(--bg-tertiary)] border-transparent hover:border-[var(--border-color)] text-[var(--text-primary)] hover:bg-[var(--bg-primary)]'
                          }`}
                        >
                          <div className="p-1.5 rounded-lg bg-[var(--bg-card)] text-[var(--accent-color)] shrink-0 shadow-xs">
                            <Tag className="w-3.5 h-3.5" />
                          </div>
                          <div className="flex flex-col min-w-0">
                            <span className="text-xs font-semibold truncate leading-tight">
                              {t('navigation.tags')}
                            </span>
                            <span className="text-[9px] text-[var(--text-muted)] font-mono truncate">
                              Etiketleri Bul
                            </span>
                          </div>
                        </Link>
                      </div>
                    </div>

                    {/* Divider */}
                    <div className="h-px bg-[var(--border-color)] w-full" />

                    {/* Section 2: İçerik Formatları */}
                    <div className="flex flex-col gap-1.5">
                      <div className="flex items-center justify-between px-1">
                        <span className="text-[10px] font-mono font-bold uppercase tracking-wider text-[var(--text-muted)]">
                          Formatlar
                        </span>
                        <Link
                          to="/browse"
                          onClick={() => setIsBrowseOpen(false)}
                          className="text-[10px] font-mono text-[var(--accent-color)] hover:underline"
                        >
                          {t('common.viewAll')} →
                        </Link>
                      </div>
                      <div className="grid grid-cols-2 gap-1">
                        {contentTypes.map((ct) => {
                          const isTypeActive = location.pathname === `/browse/${ct.key}`;
                          return (
                            <Link
                              key={ct.key}
                              to={`/browse/${ct.key}`}
                              onClick={() => setIsBrowseOpen(false)}
                              className={`px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors flex items-center justify-between ${
                                isTypeActive
                                  ? 'bg-[var(--accent-light)] text-[var(--accent-color)] font-semibold'
                                  : 'text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--text-primary)]'
                              }`}
                            >
                              <span>{ct.label}</span>
                            </Link>
                          );
                        })}
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>

            <Link
              to="/blogs"
              className={`px-3 py-2 rounded-lg transition-colors ${
                location.pathname.startsWith('/blog')
                  ? 'text-[var(--accent-color)] font-semibold bg-[var(--accent-light)]'
                  : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)]'
              }`}
            >
              {t('navigation.blogs')}
            </Link>
          </nav>
        </div>

        {/* Right Section: Search & Actions */}
        <div className="flex items-center gap-1 sm:gap-2.5 shrink-0">
          <SearchCombobox />

          {/* Day / Night Theme Toggle */}
          <button
            onClick={toggleTheme}
            aria-label={isDark ? t('preferences.themeLight') : t('preferences.themeDark')}
            title={isDark ? t('preferences.themeLight') : t('preferences.themeDark')}
            className="w-9 h-9 p-2 rounded-xl border border-[var(--border-color)] bg-[var(--bg-tertiary)] text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-all cursor-pointer active:scale-95 flex items-center justify-center shrink-0"
          >
            {isDark ? (
              <Sun className="w-4 h-4 text-amber-400" />
            ) : (
              <Moon className="w-4 h-4 text-[#818CF8]" />
            )}
          </button>

          {isAuthenticated && (
            <Link
              to="/wallet"
              className="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] hover:opacity-80 transition-all text-xs font-semibold tracking-wider shrink-0"
            >
              <Coins className="w-3.5 h-3.5 fill-current" />
              <span>{walletBalance !== null ? `${walletBalance} ${t('common.coin')}` : t('navigation.wallet')}</span>
            </Link>
          )}

          <button
            type="button"
            id="header-notifications-btn"
            onClick={openNotificationsModal}
            className="relative w-9 h-9 p-2 rounded-xl border border-[var(--border-color)] sm:border-transparent bg-[var(--bg-tertiary)] sm:bg-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer flex items-center justify-center shrink-0"
            title={t('navigation.notifications')}
            aria-label={t('navigation.notifications')}
          >
            <Bell className="w-4 h-4" />
            {isAuthenticated && unreadCount > 0 && (
              <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-[var(--accent-color)] text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-[var(--bg-card)] shadow-sm">
                {unreadCount > 9 ? '9+' : unreadCount}
              </span>
            )}
          </button>

          <AccountMenu />
        </div>
      </div>
    </header>
  );
};
