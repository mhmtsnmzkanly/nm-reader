import React from 'react';
import { Link } from 'react-router-dom';
import { MessageSquare, Heart, FileText, Sparkles } from 'lucide-react';
import { UserActivityItem } from '../../types/api';
import { EmptyState } from '../feedback/EmptyState';
import { usePreferences } from '../../contexts/PreferencesContext';

type ProfileActivityListProps = {
  activities?: UserActivityItem[];
  className?: string;
};

export const ProfileActivityList: React.FC<ProfileActivityListProps> = ({
  activities = [],
  className = '',
}) => {
  const { formatDate, formatRelativeTime, t } = usePreferences();
  if (activities.length === 0) {
    return (
      <EmptyState
        icon={<MessageSquare className="w-10 h-10 text-[var(--accent-color)]" />}
        title={t('profile.emptyActivityTitle')}
        description={t('profile.emptyActivityDesc')}
      />
    );
  }

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'comment':
        return <MessageSquare className="w-4 h-4 text-[var(--accent-color)]" />;
      case 'favorite':
        return <Heart className="w-4 h-4 text-rose-500 fill-current" />;
      case 'blog':
        return <FileText className="w-4 h-4 text-amber-500" />;
      default:
        return <Sparkles className="w-4 h-4 text-[var(--accent-color)]" />;
    }
  };

  const getTypeLabel = (type: string, targetType: string) => {
    if (type === 'favorite') return 'Kütüphaneye Ekledi';
    if (type === 'blog') return 'Blog İncelemesi';
    if (targetType === 'chapter') return 'Bölüm Yorumu';
    if (targetType === 'blog') return 'Blog Yorumu';
    return 'Seri Yorumu';
  };

  const getTargetLink = (target: UserActivityItem['target']) => {
    if (target.type === 'blog') {
      return `/blog/${target.slug || target.id}`;
    }
    const cType = target.content_type || 'manga';
    return `/${cType}/${target.slug || target.id}`;
  };

  return (
    <div className={`flex flex-col gap-3 ${className}`}>
      {activities.map((act) => {
        const link = getTargetLink(act.target);
        return (
          <div
            key={act.id}
            className="p-4 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl flex flex-col gap-2.5 transition-all shadow-xs"
          >
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <div className="p-1.5 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)]">
                  {getTypeIcon(act.type)}
                </div>
                <span className="text-[11px] font-bold uppercase tracking-wider text-[var(--accent-color)]">
                  {getTypeLabel(act.type, act.target.type)}
                </span>
                <span className="text-[var(--text-muted)] text-xs">•</span>
                <Link
                  to={link}
                  className="text-xs font-semibold font-serif text-[var(--text-primary)] hover:text-[var(--accent-color)] truncate max-w-xs sm:max-w-md transition-colors"
                >
                  {act.target.title}
                </Link>
              </div>

              <span className="text-[10px] font-mono text-[var(--text-muted)] shrink-0">
                {formatDate(act.created_at)}
              </span>
            </div>

            {act.text && (
              <p className="text-xs text-[var(--text-secondary)] bg-[var(--bg-tertiary)]/70 p-3 rounded-xl border border-[var(--border-color)]/50 leading-relaxed">
                "{act.text}"
              </p>
            )}
          </div>
        );
      })}
    </div>
  );
};
