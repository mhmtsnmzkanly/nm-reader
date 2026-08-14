import React, { useState, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  User,
  Coins,
  Bookmark,
  History,
  Settings,
  LogOut,
  FileText,
  Bell,
  Languages,
} from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';
import { useNotifications } from '../../contexts/NotificationsContext';

export const AccountMenu: React.FC = () => {
  const { user, isAuthenticated, logout, openAuthModal } = useAuth();
  const { unreadCount } = useNotifications();
  const { lang, setLanguage, t } = usePreferences();
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const navigate = useNavigate();

  useEffect(() => {
    const handleOutside = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleOutside);
    return () => document.removeEventListener('mousedown', handleOutside);
  }, []);

  if (!isAuthenticated || !user) {
    return (
      <button
        type="button"
        onClick={() => openAuthModal('login')}
        className="flex items-center justify-center w-9 h-9 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)] hover:text-[var(--accent-color)] hover:border-[var(--accent-color)] transition-all cursor-pointer shadow-sm active:scale-95"
        title="Giriş Yap / Kayıt Ol"
        aria-label="Giriş Yap veya Kayıt Ol"
      >
        <User className="w-4 h-4" />
      </button>
    );
  }

  const handleLogout = async () => {
    setIsOpen(false);
    await logout();
    navigate('/');
  };

  return (
    <div ref={menuRef} className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 p-0.5 rounded-full ring-1 ring-[var(--border-color)] hover:ring-[var(--accent-color)] transition-all cursor-pointer focus:outline-none"
        aria-label="User account menu"
      >
        <div className="w-8 h-8 rounded-full overflow-hidden bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-center text-[var(--accent-color)] font-serif font-bold text-xs">
          {user.profile_image ? (
            <img src={user.profile_image} alt={user.username} className="w-full h-full object-cover" />
          ) : (
            (user.username || 'U').substring(0, 2).toUpperCase()
          )}
        </div>
      </button>

      {isOpen && (
        <div className="absolute right-0 top-full mt-2 w-64 bg-[var(--bg-card)] rounded-xl border border-[var(--border-color)] shadow-2xl p-2 z-50 animate-in zoom-in-95 transition-colors">
          <div className="p-3 border-b border-[var(--border-color)] mb-1">
            <div className="font-semibold text-sm text-[var(--text-primary)] truncate">
              {user.username || 'Kullanıcı'}
            </div>
            {user.email && (
              <div className="text-xs text-[var(--text-muted)] truncate">{user.email}</div>
            )}
          </div>

          <div className="flex flex-col gap-0.5">
            <Link
              to="/me"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-color)] rounded-lg transition-colors"
            >
              <User className="w-4 h-4 text-[var(--accent-color)]" />
              <span>{t('navigation.profile')}</span>
            </Link>

            <Link
              to="/notifications"
              onClick={() => setIsOpen(false)}
              className="flex items-center justify-between px-3 py-2 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-color)] rounded-lg transition-colors"
            >
              <div className="flex items-center gap-2.5">
                <Bell className="w-4 h-4 text-purple-500" />
                <span>{t('navigation.notifications')}</span>
              </div>
              {unreadCount > 0 && (
                <span className="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-[var(--accent-color)] text-white">
                  {unreadCount}
                </span>
              )}
            </Link>

            <Link
              to="/wallet"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-color)] rounded-lg transition-colors"
            >
              <Coins className="w-4 h-4 text-[var(--accent-color)]" />
              <span>{t('navigation.wallet')} (180 Coin)</span>
            </Link>

            <Link
              to="/library"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-color)] rounded-lg transition-colors"
            >
              <Bookmark className="w-4 h-4 text-[var(--accent-color)]" />
              <span>{t('navigation.library')}</span>
            </Link>

            <Link
              to="/history"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-color)] rounded-lg transition-colors"
            >
              <History className="w-4 h-4 text-emerald-500" />
              <span>{t('navigation.history')}</span>
            </Link>

            <Link
              to="/my-blogs"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-color)] rounded-lg transition-colors"
            >
              <FileText className="w-4 h-4 text-[var(--text-muted)]" />
              <span>{t('navigation.myBlogs')}</span>
            </Link>

            <Link
              to="/preferences"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] hover:text-[var(--accent-color)] rounded-lg transition-colors"
            >
              <Settings className="w-4 h-4 text-[var(--text-muted)]" />
              <span>{t('navigation.preferences')}</span>
            </Link>
          </div>

          <div className="my-2 border-t border-[var(--border-color)]" />

          <div className="flex items-center justify-between px-3 py-1.5">
            <button
              onClick={() => setLanguage(lang === 'tr' ? 'en' : 'tr')}
              className="flex items-center gap-1.5 text-xs font-mono font-bold text-[var(--text-muted)] hover:text-[var(--accent-color)] cursor-pointer uppercase"
            >
              <span><Languages className="w-3.5 h-3.5" />: {lang.toUpperCase()}</span>
            </button>
          </div>

          <div className="my-1 border-t border-[var(--border-color)]" />

          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-rose-500 hover:bg-rose-500/10 rounded-lg transition-colors cursor-pointer"
          >
            <LogOut className="w-4 h-4" />
            <span>{t('navigation.logout')}</span>
          </button>
        </div>
      )}
    </div>
  );
};
