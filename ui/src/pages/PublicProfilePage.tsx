import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import {
  Bookmark,
  MessageSquare,
  FileText,
  ArrowLeft,
} from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { userService } from '../services';
import { PublicProfileData } from '../types/api';
import { ProfileTab } from '../types/domain';
import { ProfileHeader } from '../components/profile/ProfileHeader';
import { ReadingSummaryCard } from '../components/profile/ReadingSummaryCard';
import { ProfileLibraryGrid } from '../components/profile/ProfileLibraryGrid';
import { ProfileActivityList } from '../components/profile/ProfileActivityList';
import { Skeleton } from '../components/feedback/Skeleton';
import { ErrorState } from '../components/feedback/ErrorState';
import { usePreferences } from '../contexts/PreferencesContext';

export const PublicProfilePage: React.FC = () => {
  const { username = 'deniz' } = useParams<{ username: string }>();
  const { isAuthenticated, openAuthModal } = useAuth();
  const { formatDate, t } = usePreferences();

  const [profileData, setProfileData] = useState<PublicProfileData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<ProfileTab>('overview');
  const [isFollowLoading, setIsFollowLoading] = useState(false);

  useEffect(() => {
    let isMounted = true;

    const fetchPublicProfile = async () => {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const res = await userService.getPublicProfile(username);
        if (!isMounted) return;

        if (res.status === 'success') {
          setProfileData(res.data);
        } else {
          setErrorMessage(res.error?.message || t('profile.userNotFoundDesc'));
        }
      } catch (err: unknown) {
        if (!isMounted) return;
        setErrorMessage(err instanceof Error ? err.message : t('profile.updateError'));
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    fetchPublicProfile();

    return () => {
      isMounted = false;
    };
  }, [username, t]);

  const handleToggleFollow = async () => {
    if (!isAuthenticated) {
      openAuthModal('login');
      return;
    }

    if (!profileData) return;

    setIsFollowLoading(true);
    try {
      const res = await userService.toggleFollowUser(username);
      if (res.status === 'success') {
        const { is_following, followers_count } = res.data;
        setProfileData((prev) => {
          if (!prev) return prev;
          return {
            ...prev,
            user_state: {
              ...prev.user_state,
              is_following,
            },
            stats: {
              ...prev.stats,
              followers_count,
            },
            user: {
              ...prev.user,
              user_state: {
                ...prev.user.user_state,
                is_following,
              },
              stats: {
                ...prev.user.stats,
                followers_count,
              },
            },
          };
        });
      }
    } catch (err) {
      console.error('Follow toggle error:', err);
    } finally {
      setIsFollowLoading(false);
    }
  };

  if (isLoading) {
    return (
      <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">
        <Skeleton variant="rect" className="h-64 rounded-3xl" />
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {[...Array(4)].map((_, i) => (
            <Skeleton key={i} variant="rect" className="h-24 rounded-2xl" />
          ))}
        </div>
        <Skeleton variant="rect" className="h-48 rounded-2xl" />
      </div>
    );
  }

  if (errorMessage || !profileData) {
    return (
      <div className="max-w-md mx-auto my-16 px-4">
        <ErrorState
          title={t('profile.userNotFoundTitle')}
          message={errorMessage || t('profile.userNotFoundDesc')}
          onRetry={() => window.location.reload()}
        />
        <div className="mt-6 flex justify-center">
          <Link
            to="/browse"
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-xs font-mono font-semibold text-[var(--text-primary)] hover:text-[var(--accent-color)] hover:border-[var(--accent-color)] transition-all"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>{t('common.backToExplore')}</span>
          </Link>
        </div>
      </div>
    );
  }

  const { user, stats, reading, library = [], activities = [], blogs = [] } = profileData;
  const isFollowing = Boolean(profileData.user_state?.is_following);

  const tabs: { key: ProfileTab; label: string; count?: number }[] = [
    { key: 'overview', label: t('profile.tabsOverview') },
    { key: 'library', label: t('profile.tabsLibrary'), count: library.length },
    { key: 'activity', label: t('profile.tabsActivity'), count: activities.length },
  ];

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      {/* Profile Header */}
      <ProfileHeader
        user={{
          ...user,
          stats: {
            ...stats,
            chapters_read: stats.chapters_read || 0,
            series_following: stats.series_following || 0,
            library_count: stats.library_count || library.length,
            comments: stats.comments || stats.comments_count || 0,
          },
        }}
        isOwnProfile={false}
        isFollowing={isFollowing}
        isFollowLoading={isFollowLoading}
        onToggleFollow={handleToggleFollow}
      />

      {/* Tabs Navigation */}
      <div className="border-b border-[var(--border-color)] flex items-center gap-1 sm:gap-2 overflow-x-auto">
        {tabs.map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-3 text-xs font-bold uppercase tracking-wider transition-all border-b-2 -mb-px flex items-center gap-2 cursor-pointer shrink-0 ${
              activeTab === tab.key
                ? 'border-[var(--accent-color)] text-[var(--accent-color)] bg-[var(--accent-light)]/50 rounded-t-xl'
                : 'border-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] rounded-t-xl'
            }`}
          >
            <span>{tab.label}</span>
            {tab.count !== undefined && tab.count > 0 && (
              <span
                className={`px-1.5 py-0.2 rounded-md text-[10px] font-mono ${
                  activeTab === tab.key
                    ? 'bg-[var(--accent-color)] text-white'
                    : 'bg-[var(--bg-tertiary)] text-[var(--text-muted)]'
                }`}
              >
                {tab.count}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Tab Contents */}
      <div className="flex flex-col gap-8">
        {/* TAB 1: OVERVIEW */}
        {activeTab === 'overview' && (
          <div className="flex flex-col gap-8">
            {/* Reading Summary */}
            {reading && <ReadingSummaryCard reading={reading} />}

            {/* Public Library Grid Section */}
            <div className="flex flex-col gap-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Bookmark className="w-4 h-4 text-[var(--accent-color)]" />
                  <h2 className="font-serif text-lg font-bold text-[var(--text-primary)]">
                    {t('profile.followedSeriesTitle')}
                  </h2>
                </div>
                {library.length > 0 && (
                  <button
                    type="button"
                    onClick={() => setActiveTab('library')}
                    className="text-xs font-mono text-[var(--accent-color)] hover:underline cursor-pointer"
                  >
                    {t('common.seeAllWithCount', { count: library.length })}
                  </button>
                )}
              </div>

              <ProfileLibraryGrid
                items={library}
                limit={5}
                showViewAll={false}
                emptyTitle={t('profile.libraryGridEmptyTitle')}
                emptyDescription={t('profile.libraryEmptyOther')}
              />
            </div>

            {/* Public Activities Section */}
            <div className="flex flex-col gap-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <MessageSquare className="w-4 h-4 text-[var(--accent-color)]" />
                  <h2 className="font-serif text-lg font-bold text-[var(--text-primary)]">
                    {t('profile.userActivitiesTitle')}
                  </h2>
                </div>
                {activities.length > 0 && (
                  <button
                    type="button"
                    onClick={() => setActiveTab('activity')}
                    className="text-xs font-mono text-[var(--accent-color)] hover:underline cursor-pointer"
                  >
                    {t('common.seeAllWithCount', { count: activities.length })}
                  </button>
                )}
              </div>

              <ProfileActivityList activities={activities} />
            </div>

            {/* User Blogs Section if any */}
            {blogs.length > 0 && (
              <div className="flex flex-col gap-4">
                <div className="flex items-center gap-2">
                  <FileText className="w-4 h-4 text-amber-500" />
                  <h2 className="font-serif text-lg font-bold text-[var(--text-primary)]">
                    {t('profile.userPublishedPosts')}
                  </h2>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  {blogs.map((b) => (
                    <Link
                      key={b.slug}
                      to={`/blog/${b.slug}`}
                      className="p-5 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl flex flex-col justify-between gap-3 group transition-all"
                    >
                      <div>
                        <h3 className="font-serif text-base font-bold text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors">
                          {b.title}
                        </h3>
                        <p className="text-xs text-[var(--text-secondary)] line-clamp-2 mt-1">
                          {b.excerpt || b.body}
                        </p>
                      </div>
                      <div className="flex items-center justify-between text-[11px] font-mono text-[var(--text-muted)] pt-2 border-t border-[var(--border-color)]/60">
                        <span>{formatDate(b.created_at)}</span>
                        <span>{t('common.likesCount', { count: b.likes })}</span>
                      </div>
                    </Link>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {/* TAB 2: LIBRARY */}
        {activeTab === 'library' && (
          <div className="flex flex-col gap-6">
            <div className="border-b border-[var(--border-color)] pb-4">
              <h2 className="font-serif text-xl font-bold text-[var(--text-primary)]">
                {t('profile.tabsLibrary')} ({library.length})
              </h2>
              <p className="text-xs text-[var(--text-muted)]">
                {t('profile.userLibrarySubtitle', { user: user.display_name || user.username })}
              </p>
            </div>

            <ProfileLibraryGrid
              items={library}
              showViewAll={false}
              emptyTitle={t('profile.libraryGridEmptyTitle')}
              emptyDescription={t('profile.libraryEmptyOther')}
            />
          </div>
        )}

        {/* TAB 3: ACTIVITY */}
        {activeTab === 'activity' && (
          <div className="flex flex-col gap-6">
            <div className="border-b border-[var(--border-color)] pb-4">
              <h2 className="font-serif text-xl font-bold text-[var(--text-primary)]">
                {t('profile.communityActivitiesTitle')}
              </h2>
              <p className="text-xs text-[var(--text-muted)]">
                {t('profile.userCommentsSubtitle', { user: user.display_name || user.username })}
              </p>
            </div>

            <ProfileActivityList activities={activities} />
          </div>
        )}
      </div>
    </div>
  );
};
