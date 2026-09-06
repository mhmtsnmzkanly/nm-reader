import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  Users,
  UserCheck,
  UserPlus,
  BookOpen,
  Eye,
  Loader2,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
} from 'lucide-react';
import { userService } from '../../services';
import { FollowingUserItem } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

export const FollowingUsersList: React.FC = () => {
  const { t } = usePreferences();
  const [users, setUsers] = useState<FollowingUserItem[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [togglingUsername, setTogglingUsername] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [totalPages, setTotalPages] = useState<number>(1);
  const [totalItems, setTotalItems] = useState<number>(0);
  const perPage = 10;

  const loadFollowing = useCallback(async () => {
    setIsLoading(true);
    const res = await userService.getFollowingUsers(currentPage, perPage);
    if (res.status === 'success' && res.data) {
      setUsers(res.data);
      if (res.meta) {
        setTotalPages((res.meta.total_pages as number) || 1);
        setTotalItems((res.meta.total as number) || res.data.length);
      }
    }
    setIsLoading(false);
  }, [currentPage]);

  useEffect(() => {
    loadFollowing();
  }, [loadFollowing]);

  const handleToggleFollow = async (username: string) => {
    setTogglingUsername(username);
    const current = users.find((user) => user.username.toLowerCase() === username.toLowerCase());
    const res = await userService.toggleFollowUser(username, current?.is_following ?? true);
    if (res.status === 'success' && res.data) {
      setUsers((prev) =>
        prev.map((u) => {
          if (u.username.toLowerCase() === username.toLowerCase()) {
            return {
              ...u,
              is_following: res.data.is_following,
              followers_count: res.data.followers_count,
            };
          }
          return u;
        })
      );
    }
    setTogglingUsername(null);
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-4">
        <div>
          <h2 className="text-lg sm:text-xl font-bold font-serif text-[var(--text-primary)]">
            {t('following.title')}
          </h2>
          <p className="text-xs text-[var(--text-secondary)] mt-0.5">
            {t('following.subtitle')}
          </p>
        </div>

        <span className="text-xs font-mono text-[var(--text-muted)] self-start sm:self-auto bg-[var(--bg-tertiary)] px-3 py-1.5 rounded-xl border border-[var(--border-color)]">
          {t('common.recordsCount', { count: totalItems })}
        </span>
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <div
              key={i}
              className="h-28 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl animate-pulse"
            />
          ))}
        </div>
      ) : users.length === 0 ? (
        <div className="p-10 text-center border border-dashed border-[var(--border-color)] rounded-2xl flex flex-col items-center gap-3">
          <div className="w-12 h-12 rounded-full bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] flex items-center justify-center">
            <Users className="w-6 h-6" />
          </div>
          <h3 className="font-serif font-bold text-sm text-[var(--text-primary)]">
            {t('following.empty')}
          </h3>
          <p className="text-xs text-[var(--text-secondary)] max-w-md">
            {t('following.emptyDesc')}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {users.map((user) => {
            const isFollowing = user.is_following ?? true;
            const isToggling = togglingUsername === user.username;

            return (
              <div
                key={user.id}
                className="p-4 sm:p-5 rounded-2xl bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)]/50 transition-all flex flex-col justify-between gap-4 shadow-xs group"
              >
                {/* User Info Header */}
                <div className="flex items-start justify-between gap-3">
                  <Link
                    to={`/u/${user.username}`}
                    className="flex items-center gap-3 min-w-0 flex-1 hover:opacity-90 transition-opacity"
                  >
                    <div className="w-12 h-12 rounded-2xl overflow-hidden bg-[var(--bg-tertiary)] border border-[var(--border-color)] shrink-0">
                      {user.avatar ? (
                        <img
                          src={user.avatar}
                          alt={user.display_name || user.username}
                          referrerPolicy="no-referrer"
                          className="w-full h-full object-cover"
                        />
                      ) : (
                        <div className="w-full h-full bg-[var(--accent-color)] text-white font-serif font-bold flex items-center justify-center text-sm">
                          {(user.display_name || user.username || 'U').substring(0, 2).toUpperCase()}
                        </div>
                      )}
                    </div>

                    <div className="flex flex-col min-w-0">
                      <div className="flex items-center gap-1.5">
                        <span className="font-serif font-bold text-sm text-[var(--text-primary)] truncate group-hover:text-[var(--accent-color)] transition-colors">
                          {user.display_name || user.username}
                        </span>
                        {user.role === 'admin' && (
                          <span className="px-1.5 py-0.5 rounded-md text-[9px] font-mono font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                            ADMIN
                          </span>
                        )}
                        {user.role === 'translator' && (
                          <span className="px-1.5 py-0.5 rounded-md text-[9px] font-mono font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                            TRANSLATOR
                          </span>
                        )}
                      </div>

                      <span className="text-xs font-mono text-[var(--text-muted)] truncate">
                        @{user.username}
                      </span>
                    </div>
                  </Link>

                  {/* Follow / Unfollow Button */}
                  <button
                    onClick={() => handleToggleFollow(user.username)}
                    disabled={isToggling}
                    className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5 shrink-0 disabled:opacity-50 disabled:cursor-not-allowed ${
                      isFollowing
                        ? 'bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-rose-500 hover:border-rose-500/30 hover:bg-rose-500/10'
                        : 'bg-[var(--accent-color)] text-white hover:opacity-90 shadow-sm'
                    }`}
                  >
                    {isToggling ? (
                      <Loader2 className="w-3.5 h-3.5 animate-spin" />
                    ) : isFollowing ? (
                      <>
                        <UserCheck className="w-3.5 h-3.5" />
                        <span>{t('following.unfollow')}</span>
                      </>
                    ) : (
                      <>
                        <UserPlus className="w-3.5 h-3.5" />
                        <span>{t('following.follow')}</span>
                      </>
                    )}
                  </button>
                </div>

                {/* User Bio if present */}
                {user.bio && (
                  <p className="text-xs text-[var(--text-secondary)] line-clamp-2 leading-relaxed">
                    {user.bio}
                  </p>
                )}

                {/* User Stats & Profile Link */}
                <div className="flex items-center justify-between pt-3 border-t border-[var(--border-color)] text-xs text-[var(--text-muted)] font-mono">
                  <div className="flex items-center gap-3">
                    <span className="flex items-center gap-1">
                      <Users className="w-3.5 h-3.5 text-[var(--accent-color)]" />
                      {user.followers_count || 0}
                    </span>

                    {user.chapters_read !== undefined && (
                      <span className="flex items-center gap-1">
                        <BookOpen className="w-3.5 h-3.5 text-[var(--text-muted)]" />
                        {user.chapters_read}
                      </span>
                    )}
                  </div>

                  <Link
                    to={`/u/${user.username}`}
                    className="flex items-center gap-1 text-[var(--text-secondary)] hover:text-[var(--accent-color)] transition-colors"
                  >
                    <span>{t('following.viewProfile')}</span>
                    <ExternalLink className="w-3 h-3" />
                  </Link>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between border-t border-[var(--border-color)] pt-4">
          <span className="text-xs font-mono text-[var(--text-muted)]">
            {t('common.paginationLabel', { current: currentPage, total: totalPages })}
          </span>

          <div className="flex items-center gap-2">
            <button
              disabled={currentPage <= 1}
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
              className="p-2 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] text-[var(--text-primary)] disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>

            <button
              disabled={currentPage >= totalPages}
              onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
              className="p-2 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] text-[var(--text-primary)] disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
