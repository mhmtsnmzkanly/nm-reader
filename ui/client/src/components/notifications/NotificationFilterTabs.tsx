import React from 'react';
import { NotificationCategory, useNotifications } from '../../contexts/NotificationsContext';
import { usePreferences } from '../../contexts/PreferencesContext';

type TabItem = {
  id: NotificationCategory;
  labelKey: string;
};

export const NotificationFilterTabs: React.FC = () => {
  const { t } = usePreferences();
  const { activeFilter, setActiveFilter, notifications, unreadCount } = useNotifications();

  const chaptersCount = notifications.filter(
    (n) => n.type === 'chapter_release' || n.type === 'new_chapter'
  ).length;

  const socialCount = notifications.filter(
    (n) =>
      n.type === 'comment_reply' ||
      n.type === 'comment_vote' ||
      n.type === 'comment' ||
      n.type === 'reply' ||
      n.type === 'mention' ||
      n.type === 'user_follow' ||
      n.type === 'follow'
  ).length;

  const systemCount = notifications.filter(
    (n) =>
      n.type === 'coin_reward' ||
      n.type === 'wallet_transaction' ||
      n.type === 'system' ||
      n.type === 'system_announcement'
  ).length;

  const tabs: (TabItem & { count?: number; badge?: number })[] = [
    { id: 'all', labelKey: 'notifications.filterAll', count: notifications.length },
    { id: 'unread', labelKey: 'notifications.filterUnread', badge: unreadCount },
    { id: 'chapters', labelKey: 'notifications.filterChapters', count: chaptersCount },
    { id: 'social', labelKey: 'notifications.filterSocial', count: socialCount },
    { id: 'system', labelKey: 'notifications.filterSystem', count: systemCount },
  ];

  return (
    <div className="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-1">
      {tabs.map((tab) => {
        const isActive = activeFilter === tab.id;
        return (
          <button
            key={tab.id}
            type="button"
            id={`notif-filter-${tab.id}`}
            onClick={() => setActiveFilter(tab.id)}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all cursor-pointer ${
              isActive
                ? 'bg-[var(--accent-color)] text-white shadow-sm'
                : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--border-color)]'
            }`}
          >
            <span>{t(tab.labelKey)}</span>
            {tab.badge !== undefined && tab.badge > 0 ? (
              <span
                className={`px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
                  isActive ? 'bg-white text-[var(--accent-color)]' : 'bg-[var(--accent-color)] text-white'
                }`}
              >
                {tab.badge}
              </span>
            ) : tab.count !== undefined && tab.count > 0 ? (
              <span
                className={`text-[10px] font-mono ${
                  isActive ? 'text-white/80' : 'text-[var(--text-muted)]'
                }`}
              >
                ({tab.count})
              </span>
            ) : null}
          </button>
        );
      })}
    </div>
  );
};
