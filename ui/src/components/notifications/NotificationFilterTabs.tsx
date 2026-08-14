import React from 'react';
import { NotificationCategory, useNotifications } from '../../contexts/NotificationsContext';

type TabItem = {
  id: NotificationCategory;
  label: string;
};

export const NotificationFilterTabs: React.FC = () => {
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
    { id: 'all', label: 'Tümü', count: notifications.length },
    { id: 'unread', label: 'Okunmamış', badge: unreadCount },
    { id: 'chapters', label: 'Bölümler', count: chaptersCount },
    { id: 'social', label: 'Sosyal & Yorumlar', count: socialCount },
    { id: 'system', label: 'Sistem & Cüzdan', count: systemCount },
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
            <span>{tab.label}</span>
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
