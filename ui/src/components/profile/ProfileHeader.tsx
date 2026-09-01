import React from 'react';
import { Calendar, Edit3, UserCheck, UserPlus, BookOpen, Bookmark, MessageSquare, Layers } from 'lucide-react';
import { UserProfile } from '../../types/api';
import { Avatar } from './Avatar';
import { Button } from '../ui/Button';
import { usePreferences } from '../../contexts/PreferencesContext';

type ProfileHeaderProps = {
  user: UserProfile;
  isOwnProfile?: boolean;
  isFollowing?: boolean;
  isFollowLoading?: boolean;
  onEditProfile?: () => void;
  onToggleFollow?: () => void;
  className?: string;
};

export const ProfileHeader: React.FC<ProfileHeaderProps> = ({
  user,
  isOwnProfile = false,
  isFollowing = false,
  isFollowLoading = false,
  onEditProfile,
  onToggleFollow,
  className = '',
}) => {
  const { formatDate, t } = usePreferences();
  const displayName = user.display_name || user.username || t('profile.defaultUser');
  const username = user.username || 'user';
  const avatar = user.avatar || user.profile_image;
  const cover = user.cover_image || 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=1200&auto=format&fit=crop&q=80';
  const bio = user.bio;
  const joinedDate = user.joined_at || user.created_at;

  const formattedJoinDate = joinedDate
    ? formatDate(joinedDate, {
        month: 'long',
        year: 'numeric',
      })
    : '';

  const stats = {
    chapters_read: user.stats?.chapters_read ?? 0,
    series_following: user.stats?.series_following ?? 0,
    library_count: user.stats?.library_count ?? 0,
    comments: user.stats?.comments ?? (user.stats as any)?.comments_count ?? 0,
    score: user.stats?.score ?? 0,
    followers_count: user.stats?.followers_count ?? 0,
    following_count: user.stats?.following_count ?? 0,
  };

  return (
    <div
      className={`relative bg-[var(--bg-card)] border border-[var(--border-color)] rounded-3xl overflow-hidden shadow-sm transition-all duration-300 ${className}`}
    >
      {/* Cover Banner with Overlay */}
      <div className="relative h-40 sm:h-56 w-full overflow-hidden bg-[var(--bg-tertiary)]">
        <img
          src={cover}
          alt={`${displayName} Cover`}
          referrerPolicy="no-referrer"
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-[var(--bg-card)] via-[var(--bg-card)]/40 to-transparent" />
      </div>

      {/* Profile Details Container */}
      <div className="px-6 sm:px-8 pb-8 -mt-16 sm:-mt-20 relative flex flex-col gap-6">
        <div className="flex flex-col sm:flex-row items-start sm:items-end justify-between gap-4">
          {/* Avatar & Identifiers */}
          <div className="flex flex-col sm:flex-row items-start sm:items-end gap-4 sm:gap-6">
            <Avatar
              src={avatar}
              name={displayName}
              size="xl"
              ring
              className="border-4 border-[var(--bg-card)]"
            />

            <div className="flex flex-col gap-1 pb-1">
              <div className="flex items-center gap-2.5 flex-wrap">
                <h1 className="font-serif text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">
                  {displayName}
                </h1>
                <span className="px-2.5 py-0.5 rounded-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-xs font-mono font-medium text-[var(--text-secondary)]">
                  @{username}
                </span>
                {user.is_guest && (
                  <span className="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-500 text-[10px] font-bold uppercase">
                    {t('profile.guestBadge')}
                  </span>
                )}
              </div>

              {/* Join Date & Followers pill */}
              <div className="flex items-center gap-4 text-xs text-[var(--text-muted)] font-mono flex-wrap mt-0.5">
                <span className="flex items-center gap-1.5">
                  <Calendar className="w-3.5 h-3.5 text-[var(--accent-color)]" />
                  <span>{t('profile.joinedAt', { date: formattedJoinDate })}</span>
                </span>
                <span>•</span>
                <span className="text-[var(--text-secondary)]">
                  <strong className="text-[var(--text-primary)] font-bold">
                    {stats.followers_count ?? 0}
                  </strong>{' '}
                  {t('profile.followers')}
                </span>
                <span>•</span>
                <span className="text-[var(--text-secondary)]">
                  <strong className="text-[var(--text-primary)] font-bold">
                    {stats.following_count ?? 0}
                  </strong>{' '}
                  {t('profile.following')}
                </span>
              </div>
            </div>
          </div>

          {/* Action Button: Edit or Follow */}
          <div className="w-full sm:w-auto flex justify-start sm:justify-end shrink-0 pt-2 sm:pt-0">
            {isOwnProfile ? (
              <Button
                variant="outline"
                size="md"
                onClick={onEditProfile}
                className="w-full sm:w-auto gap-2 rounded-xl"
              >
                <Edit3 className="w-4 h-4 text-[var(--accent-color)]" />
                <span>{t('profile.editProfileBtn')}</span>
              </Button>
            ) : (
              <Button
                variant={isFollowing ? 'secondary' : 'primary'}
                size="md"
                isLoading={isFollowLoading}
                onClick={onToggleFollow}
                className="w-full sm:w-auto gap-2 rounded-xl"
              >
                {isFollowing ? (
                  <>
                    <UserCheck className="w-4 h-4 text-emerald-500" />
                    <span>{t('profile.followingStatus')}</span>
                  </>
                ) : (
                  <>
                    <UserPlus className="w-4 h-4" />
                    <span>{t('profile.followUser')}</span>
                  </>
                )}
              </Button>
            )}
          </div>
        </div>

        {/* Bio Section */}
        <div className="max-w-3xl">
          {bio ? (
            <p className="text-sm text-[var(--text-secondary)] leading-relaxed">
              {bio}
            </p>
          ) : (
            <p className="text-xs text-[var(--text-muted)] italic">
              {isOwnProfile
                ? t('profile.noBioOwn')
                : t('profile.noBioOther')}
            </p>
          )}
        </div>

        {/* Stats Grid Strip */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 border-t border-[var(--border-color)]">
          <div className="p-3 bg-[var(--bg-tertiary)] rounded-2xl border border-[var(--border-color)]/60 flex items-center gap-3">
            <div className="p-2 rounded-xl bg-[var(--accent-light)] text-[var(--accent-color)] shrink-0">
              <BookOpen className="w-4 h-4" />
            </div>
            <div className="flex flex-col">
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {t('profile.chaptersRead')}
              </span>
              <span className="font-serif text-lg font-bold text-[var(--text-primary)]">
                {(stats.chapters_read ?? 0).toLocaleString()}
              </span>
            </div>
          </div>

          <div className="p-3 bg-[var(--bg-tertiary)] rounded-2xl border border-[var(--border-color)]/60 flex items-center gap-3">
            <div className="p-2 rounded-xl bg-purple-500/10 text-purple-500 shrink-0">
              <Bookmark className="w-4 h-4" />
            </div>
            <div className="flex flex-col">
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {t('profile.library')}
              </span>
              <span className="font-serif text-lg font-bold text-[var(--text-primary)]">
                {stats.library_count ?? 0}
              </span>
            </div>
          </div>

          <div className="p-3 bg-[var(--bg-tertiary)] rounded-2xl border border-[var(--border-color)]/60 flex items-center gap-3">
            <div className="p-2 rounded-xl bg-emerald-500/10 text-emerald-500 shrink-0">
              <Layers className="w-4 h-4" />
            </div>
            <div className="flex flex-col">
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {t('profile.followingCount')}
              </span>
              <span className="font-serif text-lg font-bold text-[var(--text-primary)]">
                {stats.series_following ?? 0}
              </span>
            </div>
          </div>

          <div className="p-3 bg-[var(--bg-tertiary)] rounded-2xl border border-[var(--border-color)]/60 flex items-center gap-3">
            <div className="p-2 rounded-xl bg-amber-500/10 text-amber-500 shrink-0">
              <MessageSquare className="w-4 h-4" />
            </div>
            <div className="flex flex-col">
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {t('profile.comments')}
              </span>
              <span className="font-serif text-lg font-bold text-[var(--text-primary)]">
                {stats.comments ?? (stats as any).comments_count ?? 0}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
