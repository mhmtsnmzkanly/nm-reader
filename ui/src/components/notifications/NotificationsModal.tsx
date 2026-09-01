import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Bell,
  CheckCheck,
  ExternalLink,
  RefreshCw,
  X,
} from 'lucide-react';
import { useNotifications } from '../../contexts/NotificationsContext';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';
import { NotificationCard } from './NotificationCard';
import { NotificationFilterTabs } from './NotificationFilterTabs';
import { EmptyState } from '../feedback/EmptyState';
import { ErrorState } from '../feedback/ErrorState';
import { Skeleton } from '../feedback/Skeleton';
import { Button } from '../ui/Button';

export const NotificationsModal: React.FC = () => {
  const { t } = usePreferences();
  const {
    isModalOpen,
    closeNotificationsModal,
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

  const { isAuthenticated, openAuthModal } = useAuth();
  const navigate = useNavigate();

  // Close modal on Escape key
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isModalOpen) {
        closeNotificationsModal();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isModalOpen, closeNotificationsModal]);

  if (!isModalOpen) return null;

  const handleViewAll = () => {
    closeNotificationsModal();
    navigate('/notifications');
  };

  return (
    <div
      id="notifications-modal-container"
      className="fixed inset-0 z-50 flex items-start justify-center sm:justify-end sm:items-start p-3 sm:p-6 sm:pt-16 pointer-events-auto"
    >
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity animate-fade-in"
        onClick={closeNotificationsModal}
        aria-hidden="true"
      />

      {/* Modal Card */}
      <div
        role="dialog"
        aria-modal="true"
        aria-label={t('notifications.title')}
        className="relative w-full max-w-lg bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl shadow-2xl z-10 overflow-hidden flex flex-col max-h-[88vh] transition-colors duration-300"
      >
        {/* Modal Header */}
        <div className="flex items-center justify-between p-4 sm:p-5 border-b border-[var(--border-color)] bg-[var(--bg-tertiary)]/50">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)]">
              <Bell className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="font-serif font-bold text-lg text-[var(--text-primary)]">
                  {t('notifications.title')}
                </h3>
                {unreadCount > 0 && (
                  <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--accent-color)] text-white animate-pulse">
                    {t('notifications.newBadge', { count: unreadCount })}
                  </span>
                )}
              </div>
              <p className="text-xs text-[var(--text-muted)]">{t('notifications.modalSubtitle')}</p>
            </div>
          </div>

          <div className="flex items-center gap-1">
            <button
              type="button"
              id="refresh-notif-btn"
              onClick={() => fetchNotifications()}
              title={t('notifications.refreshTitle')}
              aria-label={t('notifications.refreshTitle')}
              className="p-2 text-[var(--text-muted)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] rounded-full transition-colors cursor-pointer"
            >
              <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
            </button>
            <button
              type="button"
              id="close-notif-modal-btn"
              onClick={closeNotificationsModal}
              className="p-2 text-[var(--text-muted)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] rounded-full transition-colors cursor-pointer"
              aria-label={t('common.close')}
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Filter & Actions Bar */}
        {isAuthenticated && (
          <div className="px-4 sm:px-5 py-2.5 border-b border-[var(--border-color)] bg-[var(--bg-card)] flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
              <NotificationFilterTabs />
              {unreadCount > 0 && (
                <button
                  type="button"
                  id="mark-all-read-btn"
                  onClick={markAllAsRead}
                  className="flex items-center gap-1 text-xs font-semibold text-[var(--accent-color)] hover:underline whitespace-nowrap cursor-pointer shrink-0 ml-1"
                >
                  <CheckCheck className="w-3.5 h-3.5" />
                  <span className="hidden sm:inline">{t('notifications.markAllRead')}</span>
                  <span className="sm:hidden">{t('notifications.markAllReadShort')}</span>
                </button>
              )}
            </div>
          </div>
        )}

        {/* Modal Content / Notification List */}
        <div className="flex-1 overflow-y-auto p-4 sm:p-5 flex flex-col gap-2.5 min-h-[260px]">
          {!isAuthenticated ? (
            <div className="flex flex-col items-center justify-center p-8 text-center gap-4 my-auto">
              <div className="p-4 rounded-2xl bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)]">
                <Bell className="w-8 h-8" />
              </div>
              <div className="flex flex-col gap-1 max-w-xs">
                <h4 className="font-bold text-sm text-[var(--text-primary)]">
                  {t('notifications.loginPromptTitle')}
                </h4>
                <p className="text-xs text-[var(--text-muted)]">
                  {t('notifications.loginPrompt')}
                </p>
              </div>
              <Button
                variant="primary"
                size="md"
                onClick={() => {
                  closeNotificationsModal();
                  openAuthModal('login');
                }}
                className="bg-[var(--accent-color)] text-white hover:opacity-90 cursor-pointer"
              >
                {t('notifications.loginOrRegister')}
              </Button>
            </div>
          ) : isLoading && filteredNotifications.length === 0 ? (
            <div className="flex flex-col gap-2.5">
              {Array.from({ length: 4 }).map((_, idx) => (
                <div
                  key={idx}
                  className="p-3.5 rounded-xl border border-[var(--border-color)] bg-[var(--bg-tertiary)]/40 flex items-start gap-3"
                >
                  <Skeleton variant="avatar" className="w-9 h-9 shrink-0" />
                  <div className="flex-1 flex flex-col gap-2">
                    <Skeleton variant="text" className="w-1/2 h-3.5" />
                    <Skeleton variant="text" className="w-full h-3" />
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
              icon={<Bell className="w-10 h-10 text-[var(--text-muted)] opacity-50" />}
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
                onCloseModal={closeNotificationsModal}
                compact
              />
            ))
          )}
        </div>

        {/* Modal Footer */}
        {isAuthenticated && (
          <div className="p-3 sm:p-4 border-t border-[var(--border-color)] bg-[var(--bg-tertiary)]/50 flex items-center justify-between">
            <span className="text-xs text-[var(--text-muted)] font-medium">
              {t('notifications.notificationsListed', { count: filteredNotifications.length })}
            </span>
            <button
              type="button"
              id="view-all-notifications-page-btn"
              onClick={handleViewAll}
              className="flex items-center gap-1.5 text-xs font-bold text-[var(--accent-color)] hover:underline cursor-pointer"
            >
              <span>{t('notifications.openInFullPage')}</span>
              <ExternalLink className="w-3.5 h-3.5" />
            </button>
          </div>
        )}
      </div>
    </div>
  );
};
