import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ChevronDown, Coins, Bell, Sun, Moon } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';
import { useNotifications } from '../../contexts/NotificationsContext';
import { SearchCombobox } from './SearchCombobox';
import { AccountMenu } from './AccountMenu';
import { ContentType } from '../../types/api';

export const Header: React.FC = () => {
  const { isAuthenticated, openNotificationsModal } = useAuth();
  const { unreadCount } = useNotifications();
  const { t, theme, setTheme } = usePreferences();
  const location = useLocation();
  const [isBrowseOpen, setIsBrowseOpen] = useState(false);

  const isDark = theme === 'dark' || theme === 'default' || theme === 'royal';

  const toggleTheme = () => {
    setTheme(isDark ? 'apple' : 'dark');
  };

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

            {/* Browse Dropdown */}
            <div
              className="relative"
              onMouseEnter={() => setIsBrowseOpen(true)}
              onMouseLeave={() => setIsBrowseOpen(false)}
            >
              <button
                className={`flex items-center gap-1 px-3 py-2 rounded-lg transition-colors cursor-pointer ${
                  location.pathname.startsWith('/browse')
                    ? 'text-[var(--accent-color)] font-semibold bg-[var(--accent-light)]'
                    : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)]'
                }`}
              >
                <span>{t('navigation.browse')}</span>
                <ChevronDown className="w-3.5 h-3.5 opacity-70" />
              </button>

              {isBrowseOpen && (
                <div className="absolute left-0 top-full pt-1 w-48 z-50">
                  <div className="p-1.5 bg-[var(--bg-card)] rounded-xl border border-[var(--border-color)] shadow-2xl flex flex-col gap-0.5 animate-in fade-in zoom-in-95">
                    {contentTypes.map((ct) => (
                      <Link
                        key={ct.key}
                        to={`/browse/${ct.key}`}
                        className="px-3 py-2 text-xs font-medium text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-color)] rounded-lg transition-colors"
                      >
                        {ct.label}
                      </Link>
                    ))}
                  </div>
                </div>
              )}
            </div>

            <Link
              to="/genres"
              className={`px-3 py-2 rounded-lg transition-colors ${
                location.pathname.startsWith('/genre')
                  ? 'text-[var(--accent-color)] font-semibold bg-[var(--accent-light)]'
                  : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)]'
              }`}
            >
              {t('navigation.genres')}
            </Link>

            <Link
              to="/tags"
              className={`px-3 py-2 rounded-lg transition-colors ${
                location.pathname.startsWith('/tag')
                  ? 'text-[var(--accent-color)] font-semibold bg-[var(--accent-light)]'
                  : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)]'
              }`}
            >
              {t('navigation.tags')}
            </Link>

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
        <div className="flex items-center gap-1.5 sm:gap-3 shrink-0">
          <SearchCombobox />

          {/* Day / Night Theme Toggle */}
          <button
            onClick={toggleTheme}
            className="p-2 rounded-xl border border-[var(--border-color)] bg-[var(--bg-tertiary)] text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-all cursor-pointer active:scale-95 flex items-center justify-center gap-1.5"
          >
            {isDark ? (
              <>
                <Sun className="w-4 h-4 text-amber-400 animate-spin-slow" />
              </>
            ) : (
              <>
                <Moon className="w-4 h-4 text-[#818CF8]" />
              </>
            )}
          </button>

          <button
            type="button"
            id="header-notifications-btn"
            onClick={openNotificationsModal}
            className="relative p-2 rounded-xl text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer"
            title={t('navigation.notifications')}
            aria-label={t('navigation.notifications')}
          >
            <Bell className="w-4 h-4" />
            {isAuthenticated && unreadCount > 0 && (
              <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-[var(--accent-color)] text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-[var(--bg-card)] shadow-sm">
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
