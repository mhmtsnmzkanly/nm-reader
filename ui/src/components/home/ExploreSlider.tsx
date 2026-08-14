import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { ChevronLeft, ChevronRight, Star, BookOpen, Play, Info } from 'lucide-react';
import { ExploreItem } from '../../types/api';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { usePreferences } from '../../contexts/PreferencesContext';

type ExploreSliderProps = {
  items: ExploreItem[];
};

export const ExploreSlider: React.FC<ExploreSliderProps> = ({ items }) => {
  const { t } = usePreferences();
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);


  const itemCount = items.length;

  const handleNext = useCallback(() => {
    if (itemCount === 0) return;
    setCurrentIndex((prev) => (prev + 1) % itemCount);
  }, [itemCount]);

  const handlePrev = useCallback(() => {
    if (itemCount === 0) return;
    setCurrentIndex((prev) => (prev - 1 + itemCount) % itemCount);
  }, [itemCount]);

  useEffect(() => {
    if (isPaused || itemCount <= 1) return;
    const timer = setInterval(() => {
      handleNext();
    }, 6000);
    return () => clearInterval(timer);
  }, [isPaused, itemCount, handleNext]);

  if (!items || items.length === 0) {
    return null;
  }

  const currentItem = items[currentIndex];
  const typeLabel = (currentItem.type || 'manga').toUpperCase();
  const coverImg = currentItem.cover || currentItem.cover_image || '';
  const bgImg = currentItem.background || coverImg;
  const ratingVal = currentItem.rating ?? currentItem.rating_avg ?? 0;
  const summaryText = currentItem.summary || currentItem.description || '';

  return (
    <div
      className="relative rounded-2xl overflow-hidden bg-slate-950 text-white border border-slate-800 shadow-2xl my-4 group"
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
    >
      {/* Dynamic Background with Overlay */}
      {bgImg && (
        <div
          className="absolute inset-0 bg-cover bg-center blur-2xl opacity-25 transform scale-110 transition-all duration-700 ease-out"
          style={{ backgroundImage: `url(${bgImg})` }}
        />
      )}
      <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent z-0" />

      {/* Main Slide Content */}
      <div className="relative z-10 p-6 md:p-10 flex flex-col md:flex-row gap-6 md:gap-8 items-center min-h-[380px]">
        {/* Cover Thumbnail */}
        <Link
          to={`/${currentItem.type}/${currentItem.slug}`}
          className="w-36 md:w-52 flex-shrink-0 aspect-[3/4] rounded-xl overflow-hidden shadow-2xl border border-white/10 bg-slate-900 group-hover:scale-102 transition-transform duration-300"
        >
          {coverImg ? (
            <img
              src={coverImg}
              alt={currentItem.title}
              referrerPolicy="no-referrer"
              className="w-full h-full object-cover"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center bg-indigo-900 text-3xl font-bold">
              {currentItem.title.substring(0, 2)}
            </div>
          )}
        </Link>

        {/* Info & Details */}
        <div className="flex flex-col gap-3 flex-grow text-center md:text-left w-full overflow-hidden">
          <div className="flex flex-wrap items-center justify-center md:justify-start gap-2">
            <Badge variant="gold">{typeLabel}</Badge>
            <Badge variant="secondary">
              {currentItem.status?.toLowerCase() === 'completed'
                ? t('browse.statusCompleted')
                : t('browse.statusOngoing')}
            </Badge>
            <div className="flex items-center gap-1 bg-amber-500/10 border border-amber-500/20 px-2.5 py-0.5 rounded-full text-amber-400 font-mono text-xs font-bold">
              <Star className="w-3.5 h-3.5 fill-amber-400" />
              <span>{ratingVal.toFixed(1)}</span>
            </div>
          </div>

          <Link to={`/${currentItem.type}/${currentItem.slug}`}>
            <h2 className="text-2xl md:text-4xl font-extrabold tracking-tight text-white leading-tight hover:text-[var(--accent-color)] transition-colors line-clamp-2">
              {currentItem.title}
            </h2>
          </Link>

          {summaryText && (
            <p className="text-sm text-slate-300 font-light line-clamp-3 md:line-clamp-4 leading-relaxed max-w-3xl">
              {summaryText}
            </p>
          )}

          {/* Action Buttons */}
          <div className="flex flex-wrap items-center justify-center md:justify-start gap-3 pt-3">
            <Link to={`/${currentItem.type}/${currentItem.slug}`}>
              <Button variant="primary" size="lg" className="gap-2 shadow-lg shadow-indigo-600/20">
                <Play className="w-4 h-4 fill-current" />
                {t('common.readNow')}
              </Button>
            </Link>

            <Link to={`/${currentItem.type}/${currentItem.slug}`}>
              <Button variant="secondary" size="lg" className="gap-2 bg-white/10 hover:bg-white/20 border-white/10 text-white">
                <Info className="w-4 h-4" />
                {t('common.details')}
              </Button>
            </Link>
          </div>
        </div>
      </div>

      {/* Navigation Arrows */}
      {itemCount > 1 && (
        <>
          <button
            onClick={handlePrev}
            aria-label={t('reader.prevChapter')}
            className="absolute left-3 top-1/2 -translate-y-1/2 z-20 p-2.5 rounded-full bg-slate-900/70 hover:bg-slate-800 text-white border border-white/10 backdrop-blur-md transition-all opacity-80 group-hover:opacity-100 hover:scale-110 active:scale-95"
          >
            <ChevronLeft className="w-5 h-5" />
          </button>
          <button
            onClick={handleNext}
            aria-label={t('reader.nextChapter')}
            className="absolute right-3 top-1/2 -translate-y-1/2 z-20 p-2.5 rounded-full bg-slate-900/70 hover:bg-slate-800 text-white border border-white/10 backdrop-blur-md transition-all opacity-80 group-hover:opacity-100 hover:scale-110 active:scale-95"
          >
            <ChevronRight className="w-5 h-5" />
          </button>
        </>
      )}


      {/* Slide Pagination Indicators */}
      {itemCount > 1 && (
        <div className="absolute bottom-3 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-900/60 backdrop-blur-md border border-white/10">
          {items.map((_, idx) => (
            <button
              key={idx}
              onClick={() => setCurrentIndex(idx)}
              aria-label={`Slide ${idx + 1}`}
              className={`h-2 rounded-full transition-all duration-300 ${
                idx === currentIndex
                  ? 'w-6 bg-[var(--accent-color)]'
                  : 'w-2 bg-slate-600 hover:bg-slate-400'
              }`}
            />
          ))}
        </div>
      )}
    </div>
  );
};
