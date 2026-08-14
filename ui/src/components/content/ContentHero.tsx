import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Star,
  Bookmark,
  BookOpen,
  Heart,
  RefreshCw,
  Sparkles,
  Lock,
  Calendar,
  ChevronDown,
  ChevronUp,
  Hash,
} from 'lucide-react';
import { ContentDetail, ContentDetailChapter, Genre, Tag } from '../../types/api';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { usePreferences } from '../../contexts/PreferencesContext';

type ContentHeroProps = {
  content: ContentDetail;
  chapters: ContentDetailChapter[];
  isFollowing: boolean;
  isInLibrary: boolean;
  onToggleFollow: () => void;
  onToggleLibrary: () => void;
  genres?: Genre[];
  tags?: Tag[];
};

export const ContentHero: React.FC<ContentHeroProps> = ({
  content,
  chapters,
  isFollowing,
  isInLibrary,
  onToggleFollow,
  onToggleLibrary,
  genres,
  tags,
}) => {
  const { t } = usePreferences();
  const [isDescriptionExpanded, setIsDescriptionExpanded] = useState(false);

  const typeLabels: Record<string, string> = {
    manga: 'Manga',
    manhua: 'Manhua',
    manhwa: 'Manhwa',
    webtoon: 'Webtoon',
    'light-novel': 'Light Novel',
    'web-novel': 'Web Novel',
    novel: 'Novel',
  };

  const statusLabel =
    content.status?.toLowerCase() === 'completed'
      ? t('browse.statusCompleted')
      : content.status?.toLowerCase() === 'hiatus'
      ? t('browse.statusHiatus')
      : t('browse.statusOngoing');

  const coverUrl = content.cover || content.cover_image;
  const bannerUrl = content.banner || coverUrl;

  const ratingAvg =
    typeof content.rating === 'object' && content.rating !== null
      ? content.rating.average
      : typeof content.rating === 'number'
      ? content.rating
      : content.rating_avg ?? content.rating_average ?? 0;

  // Determine first chapter vs last read chapter
  const sortedByNum = [...chapters].sort(
    (a, b) =>
      Number(a.number || a.chapter_number || 0) -
      Number(b.number || b.chapter_number || 0)
  );
  const firstChapter = sortedByNum[0];

  const lastReadId = content.user_state?.last_read_chapter_id;
  const lastReadChapter = lastReadId ? chapters.find((c) => c.id === lastReadId) : null;
  const lastReadNum =
    lastReadChapter?.number ||
    lastReadChapter?.chapter_number ||
    content.user_state?.last_read_chapter_number;

  const descriptionText =
    content.description || content.summary || 'Bu içerik için henüz bir açıklama girilmemiştir.';
  const isDescriptionLong = descriptionText.length > 240;

  const altTitles = Array.isArray(content.alternative_titles)
    ? content.alternative_titles
    : content.alternative_titles
    ? [content.alternative_titles]
    : [];

  const genresList: Genre[] =
    genres ||
    content.genres ||
    (content.genre ? [{ id: '1', name: content.genre, slug: content.genre.toLowerCase() }] : []);
  const tagsList: Tag[] = tags || content.tags || [];

  return (
    <div className="relative rounded-2xl sm:rounded-3xl overflow-hidden bg-[var(--bg-card)] text-[var(--text-primary)] border border-[var(--border-color)] shadow-xl transition-colors duration-300">
      {/* Banner Backdrop */}
      {bannerUrl && (
        <div className="absolute inset-0 h-64 sm:h-80 w-full overflow-hidden pointer-events-none opacity-30 dark:opacity-20 select-none">
          <img
            src={bannerUrl}
            alt=""
            referrerPolicy="no-referrer"
            className="w-full h-full object-cover object-center blur-md scale-105"
          />
          <div className="absolute inset-0 bg-gradient-to-b from-transparent via-[var(--bg-card)]/80 to-[var(--bg-card)]" />
        </div>
      )}

      <div className="relative z-10 p-4 sm:p-8 flex flex-col gap-6">
        <div className="flex flex-col md:flex-row gap-6 sm:gap-8 items-start">
          {/* Cover Column */}
          <div className="w-full sm:w-56 md:w-60 lg:w-64 flex-shrink-0 flex flex-col items-center gap-3.5 mx-auto md:mx-0">
            <div className="aspect-[3/4] w-48 sm:w-full rounded-2xl overflow-hidden shadow-2xl border-2 border-[var(--border-color)] bg-[var(--bg-tertiary)] relative group">
              {/* Top gradient overlay & Top-Center chips: Series Type & Release Year */}
              <div className="absolute top-0 inset-x-0 h-14 bg-gradient-to-b from-black/70 via-black/30 to-transparent z-[5] pointer-events-none" />
              <div className="absolute top-2.5 inset-x-0 flex items-center justify-center gap-1.5 z-10 px-2 pointer-events-none">
                <span className="px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-mono uppercase tracking-wider font-bold bg-amber-500 text-black shadow-md backdrop-blur-xs border border-amber-300/60">
                  {typeLabels[content.type] || content.type}
                </span>
                {content.release_year && (
                  <span className="px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-mono font-bold bg-black/75 text-white shadow-md backdrop-blur-xs border border-white/20">
                    {content.release_year}
                  </span>
                )}
              </div>

              {/* Bottom gradient overlay & Bottom-Center chip: Rating */}
              <div className="absolute bottom-0 inset-x-0 h-16 bg-gradient-to-t from-black/80 via-black/40 to-transparent z-[5] pointer-events-none" />
              <div className="absolute bottom-2.5 inset-x-0 flex items-center justify-center z-10 px-2 pointer-events-none">
                <div className="flex items-center gap-1.5 px-3 py-1 rounded-full bg-black/85 backdrop-blur-md border border-amber-500/50 text-amber-400 font-mono text-xs font-bold shadow-lg">
                  <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                  <span className="text-white font-bold">{ratingAvg.toFixed(1)}</span>
                  <span className="text-amber-400/75 text-[10px] font-normal">/ 10</span>
                </div>
              </div>

              {coverUrl ? (
                <img
                  src={coverUrl}
                  alt={content.title}
                  referrerPolicy="no-referrer"
                  className="w-full h-full object-cover group-hover:scale-102 transition-transform duration-500"
                />
              ) : (
                <div className="w-full h-full flex flex-col items-center justify-center bg-[var(--bg-tertiary)] text-[var(--accent-color)] font-serif text-4xl font-bold">
                  <Sparkles className="w-10 h-10 mb-2 opacity-50" />
                  <span>{content.title.substring(0, 2)}</span>
                </div>
              )}
            </div>

            {/* Quick Actions: Follow & Library (Equal 2-Column Grid for perfect readability) */}
            <div className="w-full grid grid-cols-2 gap-2">
              <Button
                variant={isFollowing ? 'secondary' : 'outline'}
                size="md"
                fullWidth
                onClick={onToggleFollow}
                title={isFollowing ? 'Takip Ediliyor' : 'Takip Et'}
                className={`gap-1.5 px-2 py-2.5 transition-all text-xs font-semibold justify-center cursor-pointer whitespace-nowrap ${
                  isFollowing
                    ? 'border-rose-500/40 text-rose-500 bg-rose-500/10'
                    : 'hover:border-rose-500/50 hover:text-rose-500'
                }`}
              >
                <Heart className={`w-3.5 h-3.5 shrink-0 ${isFollowing ? 'fill-current text-rose-500' : ''}`} />
                <span>{isFollowing ? t('content.following') : t('content.follow')}</span>
              </Button>

              <Button
                variant={isInLibrary ? 'secondary' : 'gold'}
                size="md"
                fullWidth
                onClick={onToggleLibrary}
                title={isInLibrary ? t('content.inLibrary') : t('content.addToLibrary')}
                className={`gap-1.5 px-2 py-2.5 text-xs font-semibold justify-center cursor-pointer whitespace-nowrap ${
                  isInLibrary
                    ? 'border-[var(--accent-color)] text-[var(--accent-color)]'
                    : 'bg-[var(--accent-color)] text-white hover:opacity-90'
                }`}
              >
                <Bookmark className={`w-3.5 h-3.5 shrink-0 ${isInLibrary ? 'fill-current' : ''}`} />
                <span>{isInLibrary ? t('content.inLibrary') : t('navigation.library')}</span>
              </Button>
            </div>

            {/* Okumaya Devam Et / İlk Bölümü Oku Button below Follow & Library */}
            {lastReadChapter ? (
              <Link to={`/${content.type}/${content.slug}/chapter/${lastReadNum}`} className="w-full">
                <Button
                  variant="gold"
                  size="md"
                  fullWidth
                  className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90 shadow-md cursor-pointer justify-center text-xs font-semibold py-2.5"
                >
                  <RefreshCw className="w-3.5 h-3.5 text-white shrink-0" />
                  <span className="truncate">{t('home.resumeReading')} ({t('chapters.chapterNumber', { number: lastReadNum })})</span>
                </Button>
              </Link>
            ) : firstChapter ? (
              <Link
                to={`/${content.type}/${content.slug}/chapter/${firstChapter.number || firstChapter.chapter_number}`}
                className="w-full"
              >
                <Button
                  variant="gold"
                  size="md"
                  fullWidth
                  className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90 shadow-md cursor-pointer justify-center text-xs font-semibold py-2.5"
                >
                  <BookOpen className="w-3.5 h-3.5 text-white shrink-0" />
                  <span className="truncate">{t('content.startReading')} ({t('chapters.chapterNumber', { number: firstChapter.number || firstChapter.chapter_number })})</span>
                </Button>
              </Link>
            ) : null}
          </div>

          {/* Hero Details Column */}
          <div className="flex-1 flex flex-col justify-between gap-4 sm:gap-5 min-w-0 w-full">
            {/* 1. Title & Alt Titles */}
            <div className="flex flex-col gap-1.5">
              <h1 className="font-serif text-2xl sm:text-3xl lg:text-4xl font-bold text-[var(--text-primary)] leading-tight break-words">
                {content.title}
              </h1>

              {altTitles.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5 pt-0.5">
                  {altTitles.map((t, i) => (
                    <span
                      key={i}
                      className="px-2 py-0.5 rounded-md text-[11px] font-mono bg-[var(--bg-tertiary)] text-[var(--text-muted)] border border-[var(--border-color)]"
                    >
                      {t}
                    </span>
                  ))}
                </div>
              )}
            </div>

            {/* 2. Description / Summary */}
            <div className="flex flex-col gap-2 bg-[var(--bg-tertiary)]/50 rounded-2xl p-4 sm:p-5 border border-[var(--border-color)]/70">
              <div className="flex items-center justify-between border-b border-[var(--border-color)]/50 pb-2">
                <span className="text-[11px] font-mono uppercase font-bold tracking-wider text-[var(--accent-color)]">
                  Özet & Açıklama
                </span>
              </div>
              <div className="relative">
                <p
                  className={`text-xs sm:text-sm text-[var(--text-secondary)] font-light leading-relaxed whitespace-pre-line break-words ${
                    !isDescriptionExpanded && isDescriptionLong ? 'line-clamp-3 sm:line-clamp-4' : ''
                  }`}
                >
                  {descriptionText}
                </p>
                {!isDescriptionExpanded && isDescriptionLong && (
                  <div className="absolute bottom-0 inset-x-0 h-6 bg-gradient-to-t from-[var(--bg-card)]/80 to-transparent pointer-events-none" />
                )}
              </div>
              {isDescriptionLong && (
                <button
                  type="button"
                  onClick={() => setIsDescriptionExpanded(!isDescriptionExpanded)}
                  className="self-start inline-flex items-center gap-1 text-[11px] font-mono font-semibold text-[var(--accent-color)] hover:underline pt-0.5 cursor-pointer"
                >
                  {isDescriptionExpanded ? (
                    <>
                      <span>Daha Az Göster</span>
                      <ChevronUp className="w-3 h-3" />
                    </>
                  ) : (
                    <>
                      <span>Daha Fazla Göster</span>
                      <ChevronDown className="w-3 h-3" />
                    </>
                  )}
                </button>
              )}
            </div>

            {/* 3. Genres & Tags: Separate dedicated rows for Türler and Etiketler */}
            {(genresList.length > 0 || tagsList.length > 0) && (
              <div className="flex flex-col gap-3 pt-3 border-t border-[var(--border-color)]">
                {/* Türler */}
                {genresList.length > 0 && (
                  <div className="flex flex-col gap-1.5">
                    <span className="text-[11px] font-mono uppercase tracking-wider text-[var(--accent-color)] font-bold flex items-center gap-1.5">
                      <Sparkles className="w-3.5 h-3.5" />
                      <span>Türler:</span>
                    </span>
                    <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                      {genresList.map((g) => (
                        <Link
                          key={`hero-genre-${g.id || g.slug}`}
                          to={`/genre/${g.slug}`}
                          className="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-semibold bg-[var(--accent-light)] text-[var(--accent-color)] border border-[var(--accent-border)] hover:opacity-90 transition-all shadow-2xs"
                        >
                          <span>{g.name}</span>
                          {g.content_count !== undefined && (
                            <span className="text-[10px] opacity-70 font-mono">({g.content_count})</span>
                          )}
                        </Link>
                      ))}
                    </div>
                  </div>
                )}

                {/* Etiketler */}
                {tagsList.length > 0 && (
                  <div className="flex flex-col gap-1.5">
                    <span className="text-[11px] font-mono uppercase tracking-wider text-[var(--text-muted)] font-bold flex items-center gap-1.5">
                      <Hash className="w-3.5 h-3.5" />
                      <span>Etiketler:</span>
                    </span>
                    <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                      {tagsList.map((t) => (
                        <Link
                          key={`hero-tag-${t.id || t.slug}`}
                          to={`/tag/${t.slug}`}
                          className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border border-[var(--border-color)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-all shadow-2xs"
                        >
                          <span>#{t.name}</span>
                          {t.content_count !== undefined && (
                            <span className="text-[10px] opacity-70 font-mono">({t.content_count})</span>
                          )}
                        </Link>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
