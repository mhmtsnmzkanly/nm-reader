import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Bell, CheckCheck, ChevronRight, RefreshCw } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { useNotifications } from '../contexts/NotificationsContext';
import { NotificationCard } from '../components/notifications/NotificationCard';
import { NotificationFilterTabs } from '../components/notifications/NotificationFilterTabs';
import { EmptyState } from '../components/feedback/EmptyState';
import { ErrorState } from '../components/feedback/ErrorState';
import { Skeleton } from '../components/feedback/Skeleton';
import { LoginPrompt } from '../components/feedback/LoginPrompt';
import { usePreferences } from '../contexts/PreferencesContext';

export const NotificationsPage: React.FC = () => {
  const { t } = usePreferences();
  const { isAuthenticated } = useAuth();
  const {
    filteredNotifications,
    unreadCount,
    isLoading,
    isError,
    errorMessage,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
    deleteNotification,
  } = useNotifications();

  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  return (
    <div className="min-h-screen py-6 sm:py-10 bg-[var(--bg-primary)] transition-colors duration-300">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 flex flex-col gap-6">
        {/* Breadcrumb Navigation */}
        <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-xs text-[var(--text-muted)]">
          <Link to="/" className="hover:text-[var(--accent-color)] transition-colors">
            {t('navigation.home')}
          </Link>
          <ChevronRight className="w-3.5 h-3.5 opacity-60" />
          <span className="text-[var(--text-primary)] font-medium">{t('notifications.title')}</span>
        </nav>

        {/* Page Header */}
        <div className="p-5 sm:p-7 rounded-2xl bg-[var(--bg-card)] border border-[var(--border-color)] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-start sm:items-center gap-3.5">
            <div className="p-3 rounded-2xl bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] shrink-0">
              <Bell className="w-6 h-6" />
            </div>
            <div>
              <div className="flex items-center gap-2.5 flex-wrap">
                <h1 className="text-xl sm:text-2xl font-serif font-bold text-[var(--text-primary)]">
                  {t('notifications.title')}
                </h1>
                {unreadCount > 0 && (
                  <span className="px-2.5 py-0.5 text-xs font-bold rounded-full bg-[var(--accent-color)] text-white animate-pulse">
                    {t('notifications.unreadBadge', { count: unreadCount })}
                  </span>
                )}
              </div>
              <p className="text-xs sm:text-sm text-[var(--text-secondary)] mt-1">
                {t('notifications.headerSubtitle')}
              </p>
            </div>
          </div>

          {isAuthenticated && (
            <div className="flex items-center gap-2 self-start sm:self-auto shrink-0">
              <button
                type="button"
                id="page-refresh-notifs-btn"
                onClick={() => fetchNotifications()}
                className="p-2.5 rounded-xl border border-[var(--border-color)] bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-all cursor-pointer"
                title={t('notifications.refreshTitle')}
                aria-label={t('notifications.refreshTitle')}
              >
                <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
              </button>

              {unreadCount > 0 && (
                <button
                  type="button"
                  id="page-mark-all-read-btn"
                  onClick={markAllAsRead}
                  className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] hover:opacity-85 transition-all cursor-pointer shadow-sm"
                >
                  <CheckCheck className="w-4 h-4" />
                  <span>{t('notifications.markAllRead')}</span>
                </button>
              )}
            </div>
          )}
        </div>

        {/* Not Authenticated Guard */}
        {!isAuthenticated ? (
          <LoginPrompt message={t('notifications.loginPrompt')} />
        ) : (
          <>
            {/* Filter Tabs */}
            <div className="p-2 rounded-2xl bg-[var(--bg-card)] border border-[var(--border-color)] shadow-sm">
              <NotificationFilterTabs />
            </div>

            {/* Notification List Container */}
            <div className="flex flex-col gap-3">
              {isLoading && filteredNotifications.length === 0 ? (
                <div className="flex flex-col gap-3">
                  {Array.from({ length: 5 }).map((_, idx) => (
                    <div
                      key={idx}
                      className="p-4 rounded-xl border border-[var(--border-color)] bg-[var(--bg-card)] flex items-start gap-4"
                    >
                      <Skeleton variant="avatar" className="w-10 h-10 shrink-0" />
                      <div className="flex-1 flex flex-col gap-2">
                        <Skeleton variant="text" className="w-1/3 h-4" />
                        <Skeleton variant="text" className="w-full h-3" />
                        <Skeleton variant="text" className="w-4/5 h-3" />
                      </div>
                    </div>
                  ))}
                </div>
              ) : isError ? (
                <ErrorState
                  title={t('notifications.loadErrorTitle')}
                  message={errorMessage || t('notifications.loadErrorDesc')}
                  onRetry={fetchNotifications}
                />
              ) : filteredNotifications.length === 0 ? (
                <EmptyState
                  icon={<Bell className="w-12 h-12 text-[var(--text-muted)] opacity-40" />}
                  title={t('notifications.emptyTitle')}
                  description={t('notifications.emptyDesc')}
                />
              ) : (
                filteredNotifications.map((notification) => (
                  <NotificationCard
                    key={notification.id}
                    notification={notification}
                    onMarkAsRead={markAsRead}
                    onDelete={deleteNotification}
                  />
                ))
              )}
            </div>
          </>
        )}
      </div>
    </div>
  );
};
