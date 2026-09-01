import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Coins,
  Bookmark,
  History,
  FileText,
  Settings,
  Sparkles,
  MessageSquare,
  Compass,
  Users,
  ShieldCheck,
} from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { usePreferences } from '../contexts/PreferencesContext';
import { userService, walletService } from '../services';
import {
  UserProfile,
  ContentSummary,
  ReadingHistoryItem,
  UserActivityItem,
} from '../types/api';
import { ProfileTab } from '../types/domain';
import { ProfileHeader } from '../components/profile/ProfileHeader';
import { ReadingSummaryCard } from '../components/profile/ReadingSummaryCard';
import { RecentlyReadList } from '../components/profile/RecentlyReadList';
import { ProfileLibraryGrid } from '../components/profile/ProfileLibraryGrid';
import { ProfileActivityList } from '../components/profile/ProfileActivityList';
import { FollowingUsersList } from '../components/profile/FollowingUsersList';
import { SessionManager } from '../components/account/SessionManager';
import { ProfileEditModal } from '../components/profile/ProfileEditModal';
import { LoginPrompt } from '../components/feedback/LoginPrompt';
import { Skeleton } from '../components/feedback/Skeleton';

export const ProfilePage: React.FC = () => {
  const { user, isAuthenticated, isLoading: isAuthLoading, refreshProfile, openAuthModal } = useAuth();
  const { t } = usePreferences();

  const [activeTab, setActiveTab] = useState<ProfileTab>('overview');
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [libraryItems, setLibraryItems] = useState<ContentSummary[]>([]);
  const [historyItems, setHistoryItems] = useState<ReadingHistoryItem[]>([]);
  const [activityItems, setActivityItems] = useState<UserActivityItem[]>([]);
  const [walletBalance, setWalletBalance] = useState<number | null>(null);
  const [isLoadingData, setIsLoadingData] = useState(true);

  useEffect(() => {
    if (!isAuthenticated) {
      setIsLoadingData(false);
      return;
    }

    const loadProfileData = async () => {
      setIsLoadingData(true);
      try {
        const [followsRes, historyRes, publicRes, walletRes] = await Promise.all([
          userService.getFollows(1, 20),
          userService.getHistory(1, 20),
          user?.username ? userService.getPublicProfile(user.username) : Promise.resolve(null),
          walletService.getWallet(),
        ]);

        if (followsRes.status === 'success') {
          setLibraryItems(followsRes.data);
        }
        if (historyRes.status === 'success') {
          setHistoryItems(historyRes.data);
        }
        if (publicRes && publicRes.status === 'success' && publicRes.data.activities) {
          setActivityItems(publicRes.data.activities);
        }
        if (walletRes.status === 'success' && walletRes.data) {
          setWalletBalance(walletRes.data.balance_coin ?? walletRes.data.balance ?? 0);
        }
      } catch (err) {
        console.error('Error loading profile data:', err);
      } finally {
        setIsLoadingData(false);
      }
    };

    loadProfileData();
  }, [isAuthenticated, user?.username]);

  if (isAuthLoading) {
    return (
      <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">
        <Skeleton variant="rect" className="h-64 rounded-3xl" />
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {[...Array(4)].map((_, i) => (
            <Skeleton key={i} variant="rect" className="h-24 rounded-2xl" />
          ))}
        </div>
      </div>
    );
  }

  if (!isAuthenticated || !user) {
    return (
      <div className="max-w-md mx-auto my-12 px-4">
        <LoginPrompt
          title={t('profile.loginPromptTitle')}
          description={t('profile.loginPromptDesc')}
          actionText={t('auth.loginTitle')}
          onAction={() => openAuthModal('login')}
        />
      </div>
    );
  }

  const handleSaveProfile = async (updated: Partial<UserProfile>) => {
    const res = await userService.updateProfile(updated);
    if (res.status === 'success') {
      await refreshProfile();
    } else {
      throw new Error(res.error?.message || t('profile.updateError'));
    }
  };

  const tabs: { key: ProfileTab; label: string; count?: number }[] = [
    { key: 'overview', label: t('profile.tabsOverview') },
    { key: 'library', label: t('profile.tabsLibrary'), count: libraryItems.length },
    { key: 'history', label: t('profile.recentReadsTitle'), count: historyItems.length },
    { key: 'following', label: t('following.title') },
    { key: 'sessions', label: t('sessions.title') },
    { key: 'activity', label: t('profile.tabsActivity'), count: activityItems.length },
  ];

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      {/* Profile Header (Cover, Avatar, Bio, Edit Profile Button, Top Stats) */}
      <ProfileHeader
        user={user}
        isOwnProfile={true}
        onEditProfile={() => setIsEditModalOpen(true)}
      />

      {/* Quick Navigation Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <Link
          to="/wallet"
          className="p-3.5 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl transition-all group flex flex-col gap-2 shadow-xs"
        >
          <div className="p-2 w-fit rounded-xl bg-[var(--accent-light)] text-[var(--accent-color)] border border-[var(--accent-border)]">
            <Coins className="w-4 h-4 fill-current" />
          </div>
          <div className="flex flex-col">
            <span className="text-xs font-bold font-serif text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors">
              {t('navigation.wallet')}
            </span>
            <span className="text-[10px] font-mono text-[var(--text-muted)]">
              {walletBalance !== null ? `${walletBalance} ${t('common.coins')}` : t('navigation.wallet')}
            </span>
          </div>
        </Link>

        <Link
          to="/library"
          className="p-3.5 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl transition-all group flex flex-col gap-2 shadow-xs"
        >
          <div className="p-2 w-fit rounded-xl bg-purple-500/10 text-purple-500 border border-purple-500/20">
            <Bookmark className="w-4 h-4" />
          </div>
          <div className="flex flex-col">
            <span className="text-xs font-bold font-serif text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors">
              {t('navigation.library')}
            </span>
            <span className="text-[10px] font-mono text-[var(--text-muted)]">
              {t('common.seriesCount', { count: libraryItems.length })}
            </span>
          </div>
        </Link>

        <Link
          to="/history"
          className="p-3.5 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl transition-all group flex flex-col gap-2 shadow-xs"
        >
          <div className="p-2 w-fit rounded-xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
            <History className="w-4 h-4" />
          </div>
          <div className="flex flex-col">
            <span className="text-xs font-bold font-serif text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors">
              {t('navigation.history')}
            </span>
            <span className="text-[10px] font-mono text-[var(--text-muted)]">
              {t('common.chaptersCount', { count: historyItems.length })}
            </span>
          </div>
        </Link>

        <Link
          to="/my-blogs"
          className="p-3.5 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl transition-all group flex flex-col gap-2 shadow-xs"
        >
          <div className="p-2 w-fit rounded-xl bg-amber-500/10 text-amber-500 border border-amber-500/20">
            <FileText className="w-4 h-4" />
          </div>
          <div className="flex flex-col">
            <span className="text-xs font-bold font-serif text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors">
              {t('navigation.myBlogs')}
            </span>
            <span className="text-[10px] font-mono text-[var(--text-muted)]">{t('blog.title')}</span>
          </div>
        </Link>

        <Link
          to="/preferences"
          className="p-3.5 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl transition-all group flex flex-col gap-2 shadow-xs"
        >
          <div className="p-2 w-fit rounded-xl bg-slate-500/10 text-slate-500 border border-slate-500/20">
            <Settings className="w-4 h-4" />
          </div>
          <div className="flex flex-col">
            <span className="text-xs font-bold font-serif text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors">
              {t('navigation.preferences')}
            </span>
            <span className="text-[10px] font-mono text-[var(--text-muted)]">{t('preferences.title')}</span>
          </div>
        </Link>
      </div>

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

      {/* Tab Content */}
      {isLoadingData ? (
        <div className="flex flex-col gap-4">
          <Skeleton variant="rect" className="h-44 rounded-2xl" />
          <Skeleton variant="rect" className="h-44 rounded-2xl" />
        </div>
      ) : (
        <div className="flex flex-col gap-8">
          {/* TAB 1: OVERVIEW */}
          {activeTab === 'overview' && (
            <div className="flex flex-col gap-8">
              {/* Reading Summary Card */}
              <ReadingSummaryCard reading={user.reading} />

              {/* Son Okunanlar Section */}
              <div className="flex flex-col gap-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <History className="w-4 h-4 text-[var(--accent-color)]" />
                    <h2 className="font-serif text-lg font-bold text-[var(--text-primary)]">
                      {t('profile.continueReadingTitle')}
                    </h2>
                  </div>
                  {historyItems.length > 0 && (
                    <button
                      type="button"
                      onClick={() => setActiveTab('history')}
                      className="text-xs font-mono text-[var(--accent-color)] hover:underline cursor-pointer"
                    >
                      {t('common.seeAllWithCount', { count: historyItems.length })}
                    </button>
                  )}
                </div>

                <RecentlyReadList items={historyItems} limit={3} showViewAll={false} />
              </div>

              {/* Kütüphane Özeti Section */}
              <div className="flex flex-col gap-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Bookmark className="w-4 h-4 text-[var(--accent-color)]" />
                    <h2 className="font-serif text-lg font-bold text-[var(--text-primary)]">
                      {t('profile.librarySeriesTitle')}
                    </h2>
                  </div>
                  {libraryItems.length > 0 && (
                    <button
                      type="button"
                      onClick={() => setActiveTab('library')}
                      className="text-xs font-mono text-[var(--accent-color)] hover:underline cursor-pointer"
                    >
                      {t('common.seeAllWithCount', { count: libraryItems.length })}
                    </button>
                  )}
                </div>

                <ProfileLibraryGrid items={libraryItems} limit={5} showViewAll={false} />
              </div>

              {/* Son Aktiviteler Section */}
              <div className="flex flex-col gap-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <MessageSquare className="w-4 h-4 text-[var(--accent-color)]" />
                    <h2 className="font-serif text-lg font-bold text-[var(--text-primary)]">
                      {t('profile.recentActivitiesTitle')}
                    </h2>
                  </div>
                  {activityItems.length > 0 && (
                    <button
                      type="button"
                      onClick={() => setActiveTab('activity')}
                      className="text-xs font-mono text-[var(--accent-color)] hover:underline cursor-pointer"
                    >
                      {t('common.seeAllWithCount', { count: activityItems.length })}
                    </button>
                  )}
                </div>

                <ProfileActivityList activities={activityItems} />
              </div>
            </div>
          )}

          {/* TAB 2: HISTORY */}
          {activeTab === 'history' && (
            <div className="flex flex-col gap-6">
              <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-4">
                <div>
                  <h2 className="font-serif text-xl font-bold text-[var(--text-primary)]">
                    {t('history.title')}
                  </h2>
                  <p className="text-xs text-[var(--text-muted)]">
                    {t('profile.historySubtitle')}
                  </p>
                </div>
                <Link
                  to="/browse"
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-[var(--accent-color)] text-xs font-mono font-semibold text-[var(--text-primary)] hover:text-[var(--accent-color)] transition-all"
                >
                  <Compass className="w-3.5 h-3.5" />
                  <span>{t('common.exploreNew')}</span>
                </Link>
              </div>

              <RecentlyReadList items={historyItems} showViewAll={false} />
            </div>
          )}

          {/* TAB 3: LIBRARY */}
          {activeTab === 'library' && (
            <div className="flex flex-col gap-6">
              <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-4">
                <div>
                  <h2 className="font-serif text-xl font-bold text-[var(--text-primary)]">
                    {t('profile.followedSeriesTitle')}
                  </h2>
                  <p className="text-xs text-[var(--text-muted)]">
                    {t('library.title')} ({libraryItems.length})
                  </p>
                </div>
                <Link
                  to="/browse"
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-[var(--accent-color)] text-xs font-mono font-semibold text-[var(--text-primary)] hover:text-[var(--accent-color)] transition-all"
                >
                  <Sparkles className="w-3.5 h-3.5 text-[var(--accent-color)]" />
                  <span>{t('common.findMoreSeries')}</span>
                </Link>
              </div>

              <ProfileLibraryGrid items={libraryItems} showViewAll={false} />
            </div>
          )}

          {/* TAB 4: FOLLOWING */}
          {activeTab === 'following' && (
            <FollowingUsersList />
          )}

          {/* TAB 5: SESSIONS */}
          {activeTab === 'sessions' && (
            <SessionManager />
          )}

          {/* TAB 6: ACTIVITY */}
          {activeTab === 'activity' && (
            <div className="flex flex-col gap-6">
              <div className="border-b border-[var(--border-color)] pb-4">
                <h2 className="font-serif text-xl font-bold text-[var(--text-primary)]">
                  {t('profile.communityActivitiesTitle')}
                </h2>
                <p className="text-xs text-[var(--text-muted)]">
                  {t('profile.communityActivitiesSubtitle')}
                </p>
              </div>

              <ProfileActivityList activities={activityItems} />
            </div>
          )}
        </div>
      )}

      {/* Edit Profile Modal */}
      {isEditModalOpen && (
        <ProfileEditModal
          isOpen={isEditModalOpen}
          onClose={() => setIsEditModalOpen(false)}
          user={user}
          onSave={handleSaveProfile}
        />
      )}
    </div>
  );
};
