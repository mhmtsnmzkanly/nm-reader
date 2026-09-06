import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { NotificationItem, NotificationPayloadData } from '../types/api';
import { userService } from '../services';
import { useAuth } from './AuthContext';

export type NotificationCategory = 'all' | 'unread' | 'chapters' | 'social' | 'system';

export function parseNotificationData(data: string | NotificationPayloadData | undefined): NotificationPayloadData {
  if (!data) return {};
  if (typeof data === 'object') return data;
  try {
    return JSON.parse(data);
  } catch {
    return {};
  }
}

export function getNotificationTargetUrl(notification: NotificationItem): string | null {
  if (notification.target_url) return notification.target_url;
  const payload = parseNotificationData(notification.data);
  if (payload.url) return payload.url;
  if (payload.content_slug && payload.chapter_number) {
    const type = payload.content_type || 'manga';
    return `/${type}/${payload.content_slug}/chapter/${payload.chapter_number}`;
  }
  if (payload.content_slug) {
    const type = payload.content_type || 'manga';
    return `/${type}/${payload.content_slug}`;
  }
  if (payload.username) {
    return `/u/${payload.username}`;
  }
  if (notification.type === 'coin_reward' || notification.type === 'wallet_transaction') {
    return '/wallet';
  }
  if (notification.type === 'user_follow' || notification.type === 'follow') {
    return `/u/${notification.actor_username}`;
  }
  return null;
}

type NotificationsContextType = {
  notifications: NotificationItem[];
  unreadCount: number;
  isLoading: boolean;
  isError: boolean;
  errorMessage: string | null;
  activeFilter: NotificationCategory;
  setActiveFilter: (filter: NotificationCategory) => void;
  filteredNotifications: NotificationItem[];
  fetchNotifications: () => Promise<void>;
  markAsRead: (id: number) => Promise<void>;
  markAllAsRead: () => Promise<void>;
  deleteNotification: (id: number) => Promise<void>;
  isModalOpen: boolean;
  openNotificationsModal: () => void;
  closeNotificationsModal: () => void;
  toggleNotificationsModal: () => void;
};

const NotificationsContext = createContext<NotificationsContextType | undefined>(undefined);

export const NotificationsProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isAuthenticated } = useAuth();
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [isError, setIsError] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [activeFilter, setActiveFilter] = useState<NotificationCategory>('all');
  const [isModalOpen, setIsModalOpen] = useState<boolean>(false);

  const openNotificationsModal = useCallback(() => setIsModalOpen(true), []);
  const closeNotificationsModal = useCallback(() => setIsModalOpen(false), []);
  const toggleNotificationsModal = useCallback(() => setIsModalOpen((prev) => !prev), []);

  const fetchNotifications = useCallback(async () => {
    if (!isAuthenticated) {
      setNotifications([]);
      setIsLoading(false);
      setIsError(false);
      return;
    }

    setIsLoading(true);
    setIsError(false);
    setErrorMessage(null);

    try {
      const res = await userService.getNotifications();
      if (res.status === 'success' && res.data) {
        setNotifications(res.data);
      } else {
        setIsError(true);
        setErrorMessage(res.error?.message || 'Bildirimler yüklenemedi.');
      }
    } catch {
      setIsError(true);
      setErrorMessage('Ağ bağlantısı sağlanamadı.');
    } finally {
      setIsLoading(false);
    }
  }, [isAuthenticated]);

  useEffect(() => {
    fetchNotifications();
  }, [fetchNotifications]);

  const markAsRead = async (id: number) => {
    setNotifications((prev) =>
      prev.map((n) => (n.id === id ? { ...n, is_read: 1 } : n))
    );
    try {
      await userService.markNotificationsRead(id);
    } catch (e) {
      console.error('Failed to mark notification read', e);
    }
  };

  const markAllAsRead = async () => {
    setNotifications((prev) => prev.map((n) => ({ ...n, is_read: 1 })));
    try {
      await userService.markNotificationsRead('all');
    } catch (e) {
      console.error('Failed to mark all notifications read', e);
    }
  };

  const deleteNotification = async (id: number) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
    try {
      await userService.deleteNotification(id);
    } catch (e) {
      console.error('Failed to delete notification', e);
    }
  };

  const unreadCount = notifications.filter((n) => n.is_read === 0).length;

  const filteredNotifications = notifications.filter((n) => {
    if (activeFilter === 'unread') {
      return n.is_read === 0;
    }
    if (activeFilter === 'chapters') {
      return n.type === 'chapter_release' || n.type === 'new_chapter';
    }
    if (activeFilter === 'social') {
      return (
        n.type === 'comment_reply' ||
        n.type === 'comment_vote' ||
        n.type === 'comment' ||
        n.type === 'reply' ||
        n.type === 'mention' ||
        n.type === 'user_follow' ||
        n.type === 'follow'
      );
    }
    if (activeFilter === 'system') {
      return (
        n.type === 'coin_reward' ||
        n.type === 'wallet_transaction' ||
        n.type === 'system' ||
        n.type === 'system_announcement'
      );
    }
    return true;
  });

  return (
    <NotificationsContext.Provider
      value={{
        notifications,
        unreadCount,
        isLoading,
        isError,
        errorMessage,
        activeFilter,
        setActiveFilter,
        filteredNotifications,
        fetchNotifications,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        isModalOpen,
        openNotificationsModal,
        closeNotificationsModal,
        toggleNotificationsModal,
      }}
    >
      {children}
    </NotificationsContext.Provider>
  );
};

export const useNotifications = () => {
  const context = useContext(NotificationsContext);
  if (!context) {
    throw new Error('useNotifications must be used within a NotificationsProvider');
  }
  return context;
};
