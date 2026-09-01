import React from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BookOpen,
  Coins,
  MessageSquare,
  Sparkles,
  UserPlus,
  ThumbsUp,
  AtSign,
  Check,
  Trash2,
  ExternalLink,
} from 'lucide-react';
import { NotificationItem } from '../../types/api';
import { getNotificationTargetUrl } from '../../contexts/NotificationsContext';
import { usePreferences } from '../../contexts/PreferencesContext';

type NotificationCardProps = {
  notification: NotificationItem;
  onMarkAsRead?: (id: number) => void;
  onDelete?: (id: number) => void;
  onCloseModal?: () => void;
  compact?: boolean;
};

export const NotificationCard: React.FC<NotificationCardProps> = ({
  notification,
  onMarkAsRead,
  onDelete,
  onCloseModal,
  compact = false,
}) => {
  const navigate = useNavigate();
  const { formatRelativeTime, t } = usePreferences();
  const isUnread = notification.is_read === 0;
  const targetUrl = getNotificationTargetUrl(notification);

  const handleClick = (e: React.MouseEvent) => {
    // If clicked on action buttons, do not navigate
    if ((e.target as HTMLElement).closest('button[data-action="true"]')) {
      return;
    }

    if (isUnread && onMarkAsRead) {
      onMarkAsRead(notification.id);
    }

    if (targetUrl) {
      if (onCloseModal) onCloseModal();
      navigate(targetUrl);
    }
  };

  const getIcon = () => {
    switch (notification.type) {
      case 'chapter_release':
      case 'new_chapter':
        return <BookOpen className="w-4 h-4 text-[var(--accent-color)]" />;
      case 'comment_vote':
        return <ThumbsUp className="w-4 h-4 text-emerald-500" />;
      case 'comment_reply':
      case 'comment':
      case 'reply':
        return <MessageSquare className="w-4 h-4 text-sky-500" />;
      case 'mention':
        return <AtSign className="w-4 h-4 text-indigo-500" />;
      case 'user_follow':
      case 'follow':
        return <UserPlus className="w-4 h-4 text-pink-500" />;
      case 'coin_reward':
      case 'wallet_transaction':
        return <Coins className="w-4 h-4 text-amber-500" />;
      case 'system_announcement':
      case 'system':
      default:
        return <Sparkles className="w-4 h-4 text-purple-500" />;
    }
  };

  return (
    <article
      id={`notification-item-${notification.id}`}
      onClick={handleClick}
      role={targetUrl ? 'button' : 'article'}
      tabIndex={targetUrl ? 0 : undefined}
      onKeyDown={(e) => {
        if ((e.key === 'Enter' || e.key === ' ') && targetUrl) {
          e.preventDefault();
          handleClick(e as unknown as React.MouseEvent);
        }
      }}
      className={`group relative rounded-xl border transition-all duration-200 flex items-start gap-3 sm:gap-3.5 ${
        compact ? 'p-3' : 'p-3.5 sm:p-4'
      } ${
        targetUrl ? 'cursor-pointer hover:border-[var(--accent-color)]/60' : ''
      } ${
        isUnread
          ? 'bg-[var(--bg-card)] border-[var(--accent-color)]/40 shadow-sm ring-1 ring-[var(--accent-color)]/15'
          : 'bg-[var(--bg-tertiary)]/50 border-[var(--border-color)] opacity-90 hover:opacity-100'
      }`}
    >
      {/* Icon or Actor Avatar */}
      <div className="relative shrink-0 mt-0.5">
        {notification.actor_avatar ? (
          <div className="relative w-9 h-9 rounded-xl overflow-hidden border border-[var(--border-color)] shadow-sm">
            <img
              src={notification.actor_avatar}
              alt={notification.actor_username || 'User'}
              className="w-full h-full object-cover"
              referrerPolicy="no-referrer"
            />
            <div className="absolute -bottom-1 -right-1 p-0.5 rounded-full bg-[var(--bg-card)] border border-[var(--border-color)]">
              {getIcon()}
            </div>
          </div>
        ) : (
          <div
            className={`p-2.5 rounded-xl border flex items-center justify-center ${
              isUnread
                ? 'bg-[var(--accent-light)] border-[var(--accent-border)]'
                : 'bg-[var(--bg-card)] border-[var(--border-color)]'
            }`}
          >
            {getIcon()}
          </div>
        )}
      </div>

      {/* Content */}
      <div className="flex-1 min-w-0 flex flex-col gap-1">
        <div className="flex items-center justify-between gap-2">
          <h4 className="text-xs sm:text-sm font-semibold text-[var(--text-primary)] truncate">
            {notification.title}
          </h4>
          <span className="text-[10px] text-[var(--text-muted)] font-mono whitespace-nowrap shrink-0">
            {formatRelativeTime(notification.created_at)}
          </span>
        </div>

        <p className="text-xs text-[var(--text-secondary)] leading-relaxed break-words line-clamp-2">
          {notification.body}
        </p>

        {/* Target link hint if applicable */}
        {targetUrl && (
          <div className="flex items-center gap-1 text-[11px] font-medium text-[var(--accent-color)] mt-0.5 group-hover:underline">
            <span>{t('notifications.inspect')}</span>
            <ExternalLink className="w-3 h-3 opacity-70" />
          </div>
        )}
      </div>

      {/* Action Controls */}
      <div className="flex items-center gap-1 shrink-0 ml-1">
        {isUnread && onMarkAsRead && (
          <button
            type="button"
            data-action="true"
            id={`mark-read-btn-${notification.id}`}
            onClick={(e) => {
              e.stopPropagation();
              onMarkAsRead(notification.id);
            }}
            title={t('notifications.markAsReadTitle')}
            aria-label={t('notifications.markAsReadTitle')}
            className="p-1.5 rounded-lg text-[var(--text-muted)] hover:text-[var(--accent-color)] hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer"
          >
            <Check className="w-3.5 h-3.5" />
          </button>
        )}

        {onDelete && (
          <button
            type="button"
            data-action="true"
            id={`delete-notif-btn-${notification.id}`}
            onClick={(e) => {
              e.stopPropagation();
              onDelete(notification.id);
            }}
            title={t('notifications.deleteTitle')}
            aria-label={t('notifications.deleteTitle')}
            className="p-1.5 rounded-lg text-[var(--text-muted)] hover:text-rose-500 hover:bg-rose-500/10 opacity-60 group-hover:opacity-100 transition-all cursor-pointer"
          >
            <Trash2 className="w-3.5 h-3.5" />
          </button>
        )}

        {isUnread && (
          <span
            className="w-2 h-2 rounded-full bg-[var(--accent-color)] shrink-0 ml-1 animate-pulse"
            title={t('notifications.unreadState')}
            aria-label={t('notifications.unreadState')}
          />
        )}
      </div>
    </article>
  );
};
