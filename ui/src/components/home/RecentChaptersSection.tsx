import React from 'react';
import { Link } from 'react-router-dom';
import { Clock, BookOpen, ChevronRight } from 'lucide-react';
import { RecentChapterItem } from '../../types/api';
import { Badge } from '../ui/Badge';
import { usePreferences } from '../../contexts/PreferencesContext';

type RecentChaptersSectionProps = {
  chapters: RecentChapterItem[];
};

export const RecentChaptersSection: React.FC<RecentChaptersSectionProps> = ({ chapters }) => {
  const { t, formatRelativeTime } = usePreferences();

  if (!chapters || chapters.length === 0) {
    return null;
  }

  return (
    <section className="my-8">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <div className="p-2 rounded-lg bg-[var(--accent-color)]/10 text-[var(--accent-color)]">
            <Clock className="w-5 h-5" />
          </div>
          <div>
            <h2 className="text-xl font-extrabold font-serif text-[var(--text-primary)] tracking-tight">
              {t('home.recentChaptersTitle')}
            </h2>
            <p className="text-xs text-[var(--text-secondary)]">{t('home.recentChaptersSubtitle')}</p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {chapters.map((ch) => {
          const coverImg = ch.cover || ch.cover_image || '';
          const seriesType = ch.series_type || 'manga';
          const seriesSlug = ch.series_slug || ch.series_id;
          const chapterNum = ch.chapter_number;
          const pubDate = ch.published_at || ch.created_at || '';

          return (
            <div
              key={ch.id}
              className="group relative bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-xl p-3 transition-all duration-300 hover:shadow-lg flex items-center gap-3.5"
            >
              {/* Thumbnail Image */}
              <Link
                to={`/${seriesType}/${seriesSlug}`}
                className="w-16 h-22 flex-shrink-0 aspect-[3/4] rounded-lg overflow-hidden bg-[var(--bg-tertiary)] relative"
              >
                {coverImg ? (
                  <img
                    src={coverImg}
                    alt={ch.series_title}
                    referrerPolicy="no-referrer"
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    loading="lazy"
                  />
                ) : (
                  <div className="w-full h-full flex items-center justify-center font-bold text-xs text-[var(--text-muted)] bg-slate-800">
                    {ch.series_title.substring(0, 2)}
                  </div>
                )}
              </Link>

              {/* Chapter Metadata */}
              <div className="flex flex-col justify-between flex-grow min-w-0 h-full py-0.5">
                <div className="flex flex-col gap-1">
                  <div className="flex items-center justify-between gap-1 text-[10px] text-[var(--text-muted)] font-mono">
                    <Badge variant="gold" size="sm" className="text-[9px] px-1.5 py-0">
                      {seriesType.toUpperCase()}
                    </Badge>
                    <span className="flex items-center gap-1 text-[var(--text-muted)] truncate">
                      <Clock className="w-3 h-3 text-[var(--text-muted)]" />
                      {formatRelativeTime(pubDate)}
                    </span>
                  </div>

                  <Link to={`/${seriesType}/${seriesSlug}`}>
                    <h3 className="font-bold text-sm font-serif text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors line-clamp-1 leading-snug">
                      {ch.series_title}
                    </h3>
                  </Link>
                </div>

                {/* Chapter Link */}
                <Link
                  to={`/${seriesType}/${seriesSlug}/chapter/${chapterNum}`}
                  className="flex items-center justify-between mt-2 pt-2 border-t border-[var(--border-color)] text-xs font-semibold text-[var(--accent-color)] hover:underline"
                >
                  <span className="flex items-center gap-1.5 truncate">
                    <BookOpen className="w-3.5 h-3.5 flex-shrink-0" />
                    <span className="truncate">
                      {t('chapters.chapterNumber', { number: chapterNum })} {ch.chapter_title ? `- ${ch.chapter_title}` : ''}
                    </span>
                  </span>
                  <ChevronRight className="w-4 h-4 flex-shrink-0 group-hover:translate-x-1 transition-transform" />
                </Link>
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
};

