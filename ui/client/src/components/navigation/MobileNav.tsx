import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Home, Compass, Bookmark, User } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';

export const MobileNav: React.FC = () => {
  const { isAuthenticated, openAuthModal } = useAuth();
  const { t } = usePreferences();
  const location = useLocation();

  // Hide mobile nav on reader page
  if (location.pathname.includes('/chapter/')) {
    return null;
  }

  const navItems = [
    { id: 'home', path: '/', label: t('navigation.home'), icon: Home },
    { id: 'browse', path: '/browse', label: t('navigation.browse'), icon: Compass },
    {
      id: 'library',
      path: '/library',
      requiresAuth: true,
      label: t('navigation.library'),
      icon: Bookmark,
    },
    {
      id: 'profile',
      path: '/me',
      requiresAuth: true,
      label: t('navigation.profile'),
      icon: User,
    },
  ];

  return (
    <nav className="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-[var(--bg-card)]/95 backdrop-blur-md border-t border-[var(--border-color)] px-2 py-1 flex items-center justify-around shadow-2xl transition-colors duration-300">
      {navItems.map((item) => {
        const Icon = item.icon;
        const isActive =
          item.id === 'browse'
            ? location.pathname.startsWith('/browse')
            : location.pathname === item.path;

        if (item.requiresAuth && !isAuthenticated) {
          return (
            <button
              key={item.id}
              type="button"
              onClick={() => openAuthModal('login')}
              className="flex flex-col items-center justify-center min-w-[56px] min-h-[48px] py-1 px-2 rounded-xl text-center transition-colors text-[var(--text-secondary)] hover:text-[var(--text-primary)] cursor-pointer"
            >
              <Icon className="w-5 h-5 stroke-2" />
              <span className="text-[10px] uppercase tracking-wider mt-0.5 truncate max-w-[64px]">
                {item.label}
              </span>
            </button>
          );
        }

        return (
          <Link
            key={item.id}
            to={item.path}
            className={`flex flex-col items-center justify-center min-w-[56px] min-h-[48px] py-1 px-2 rounded-xl text-center transition-colors ${
              isActive
                ? 'text-[var(--accent-color)] font-bold'
                : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
            }`}
          >
            <Icon className={`w-5 h-5 ${isActive ? 'stroke-[2.5]' : 'stroke-2'}`} />
            <span className="text-[10px] uppercase tracking-wider mt-0.5 truncate max-w-[64px]">
              {item.label}
            </span>
          </Link>
        );
      })}
    </nav>
  );
};
