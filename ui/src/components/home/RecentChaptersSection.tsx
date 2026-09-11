import React, { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Clock, BookOpen, ChevronRight } from 'lucide-react';
import { RecentChapterItem } from '../../types/api';
import { Badge } from '../ui/Badge';
import { usePreferences } from '../../contexts/PreferencesContext';
import { parseDate } from '../../utils/formatDate';

type RecentChaptersSectionProps = {
  chapters: RecentChapterItem[];
};

type GroupedRecentSeries = {
  seriesId: string;
  seriesSlug: string;
  seriesTitle: string;
  seriesType: string;
  cover: string;
  latestTime: number;
  latestDateStr: string;
  chapters: RecentChapterItem[];
};

export const RecentChaptersSection: React.FC<RecentChaptersSectionProps> = ({ chapters }) => {
  const { t, formatRelativeTime } = usePreferences();

  const groupedSeries = useMemo(() => {
    if (!chapters || chapters.length === 0) return [];

    const map = new Map<string, GroupedRecentSeries>();

    for (const ch of chapters) {
      const key = ch.series_slug || ch.series_id || ch.series_title;
      const pubDateStr = ch.published_at || ch.created_at || '';
      const pubTime = pubDateStr ? parseDate(pubDateStr)?.getTime() || 0 : 0;
      const coverImg = ch.cover || ch.cover_image || '';

      const existing = map.get(key);
      if (!existing) {
        map.set(key, {
          seriesId: ch.series_id,
          seriesSlug: ch.series_slug || ch.series_id,
          seriesTitle: ch.series_title,
          seriesType: ch.series_type || 'manga',
          cover: coverImg,
          latestTime: pubTime,
          latestDateStr: pubDateStr,
          chapters: [ch],
        });
      } else {
        existing.chapters.push(ch);
        if (pubTime > existing.latestTime) {
          existing.latestTime = pubTime;
          existing.latestDateStr = pubDateStr;
        }
        if (!existing.cover && coverImg) {
          existing.cover = coverImg;
        }
      }
    }

    // Serileri en son gelen bölümün zamanına göre sırala (en güncel seri en başta)
    const list = Array.from(map.values());
    list.sort((a, b) => b.latestTime - a.latestTime);

    // Her serinin kendi bölümlerini en yeniden eskiye doğru sırala
    for (const item of list) {
      item.chapters.sort((a, b) => {
        const timeA = a.published_at || a.created_at ? parseDate(a.published_at || a.created_at || '')?.getTime() || 0 : 0;
        const timeB = b.published_at || b.created_at ? parseDate(b.published_at || b.created_at || '')?.getTime() || 0 : 0;
        if (timeA !== timeB) return timeB - timeA;

        const numA = parseFloat(String(a.chapter_number)) || 0;
        const numB = parseFloat(String(b.chapter_number)) || 0;
        return numB - numA;
      });
    }

    return list;
  }, [chapters]);

  if (!chapters || chapters.length === 0 || groupedSeries.length === 0) {
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

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        {groupedSeries.map((item) => (
          <div
            key={item.seriesSlug}
            className="group bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)]/60 rounded-2xl p-4 transition-all duration-300 hover:shadow-lg flex gap-4"
          >
            {/* Thumbnail Kapak Görseli */}
            <Link
              to={`/${item.seriesType}/${item.seriesSlug}`}
              className="w-20 sm:w-24 flex-shrink-0 aspect-[3/4] rounded-xl overflow-hidden bg-[var(--bg-tertiary)] relative shadow-xs"
            >
              {item.cover ? (
                <img
                  src={item.cover}
                  alt={item.seriesTitle}
                  referrerPolicy="no-referrer"
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  loading="lazy"
                />
              ) : (
                <div className="w-full h-full flex items-center justify-center font-bold text-xs text-[var(--text-muted)] bg-slate-800">
                  {item.seriesTitle.substring(0, 2)}
                </div>
              )}
            </Link>

            {/* Seri Bilgileri ve Alt Alta Bölüm Listesi */}
            <div className="flex flex-col justify-between flex-grow min-w-0">
              <div>
                {/* Tür Etiketi & En Son Bölümün Zamanı */}
                <div className="flex items-center justify-between gap-1 text-[10px] text-[var(--text-muted)] font-mono mb-1.5">
                  <Badge variant="gold" size="sm" className="text-[9px] px-1.5 py-0">
                    {item.seriesType.toUpperCase()}
                  </Badge>
                  <span className="flex items-center gap-1 text-[var(--text-muted)] truncate">
                    <Clock className="w-3 h-3 text-[var(--text-muted)]" />
                    {formatRelativeTime(item.latestDateStr)}
                  </span>
                </div>

                {/* Seri Başlığı */}
                <Link to={`/${item.seriesType}/${item.seriesSlug}`}>
                  <h3 className="font-bold text-sm sm:text-base font-serif text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors line-clamp-1 leading-snug mb-3">
                    {item.seriesTitle}
                  </h3>
                </Link>
              </div>

              {/* Alt Alta Sıralanan Yeni Bölümler */}
              <div className="flex flex-col gap-1.5">
                {item.chapters.map((ch) => {
                  const chapterNum = ch.chapter_number;
                  const pubDate = ch.published_at || ch.created_at || '';

                  return (
                    <Link
                      key={ch.id}
                      to={`/${item.seriesType}/${item.seriesSlug}/chapter/${chapterNum}`}
                      className="flex items-center justify-between px-2.5 py-1.5 rounded-lg bg-[var(--bg-tertiary)]/70 hover:bg-[var(--accent-light)] border border-[var(--border-color)] hover:border-[var(--accent-color)] text-xs transition-colors group/ch"
                    >
                      <span className="flex items-center gap-1.5 truncate font-medium text-[var(--text-primary)] group-hover/ch:text-[var(--accent-color)]">
                        <BookOpen className="w-3.5 h-3.5 flex-shrink-0 text-[var(--accent-color)]" />
                        <span className="truncate">
                          {t('chapters.chapterNumber', { number: chapterNum })}
                          {ch.chapter_title ? ` - ${ch.chapter_title}` : ''}
                        </span>
                      </span>

                      <span className="flex items-center gap-1 text-[10px] font-mono text-[var(--text-muted)] flex-shrink-0 ml-2">
                        <span>{formatRelativeTime(pubDate)}</span>
                        <ChevronRight className="w-3.5 h-3.5 group-hover/ch:translate-x-0.5 transition-transform" />
                      </span>
                    </Link>
                  );
                })}
              </div>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
};

