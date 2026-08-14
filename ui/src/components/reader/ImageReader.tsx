import React, { useState, useEffect, useRef, useCallback } from 'react';
import { ChevronLeft, ChevronRight, AlertCircle, RefreshCw, Layers } from 'lucide-react';
import { Chapter } from '../../types/api';
import { ReaderSettingsState } from '../../types/domain';

type ImageReaderProps = {
  chapter: Chapter;
  readerSettings?: ReaderSettingsState;
  currentPageIndex: number;
  onPageChange: (index: number) => void;
  onNextChapter?: () => void;
  onPrevChapter?: () => void;
  isSettingsOpen?: boolean;
};

export const ImageReader: React.FC<ImageReaderProps> = ({
  chapter,
  readerSettings,
  currentPageIndex,
  onPageChange,
  onNextChapter,
  onPrevChapter,
  isSettingsOpen = false,
}) => {
  const pages = chapter.pages || [];
  const totalPages = pages.length;

  const layout = readerSettings?.layout || 'vertical';
  const imageFit = readerSettings?.imageFit || 'width';
  const readingDirection = readerSettings?.readingDirection || 'ltr';

  // Image load & error states per page index
  const [loadedPages, setLoadedPages] = useState<Record<number, boolean>>({});
  const [errorPages, setErrorPages] = useState<Record<number, boolean>>({});

  // Touch swipe handling
  const touchStartX = useRef<number | null>(null);
  const touchStartY = useRef<number | null>(null);

  const handleImageLoad = (index: number) => {
    setLoadedPages((prev) => ({ ...prev, [index]: true }));
    setErrorPages((prev) => ({ ...prev, [index]: false }));
  };

  const handleImageError = (index: number) => {
    setErrorPages((prev) => ({ ...prev, [index]: true }));
    setLoadedPages((prev) => ({ ...prev, [index]: true }));
  };

  const retryImage = (index: number) => {
    setErrorPages((prev) => ({ ...prev, [index]: false }));
    setLoadedPages((prev) => ({ ...prev, [index]: false }));
  };

  // Page navigation logic
  const goToNextPage = useCallback(() => {
    if (layout === 'double') {
      const step = 2;
      if (currentPageIndex + step < totalPages) {
        onPageChange(currentPageIndex + step);
      } else if (currentPageIndex < totalPages - 1) {
        onPageChange(totalPages - 1);
      } else if (onNextChapter) {
        onNextChapter();
      }
    } else {
      if (currentPageIndex < totalPages - 1) {
        onPageChange(currentPageIndex + 1);
      } else if (onNextChapter) {
        onNextChapter();
      }
    }
  }, [currentPageIndex, totalPages, layout, onPageChange, onNextChapter]);

  const goToPrevPage = useCallback(() => {
    if (layout === 'double') {
      const step = 2;
      if (currentPageIndex - step >= 0) {
        onPageChange(currentPageIndex - step);
      } else if (currentPageIndex > 0) {
        onPageChange(0);
      } else if (onPrevChapter) {
        onPrevChapter();
      }
    } else {
      if (currentPageIndex > 0) {
        onPageChange(currentPageIndex - 1);
      } else if (onPrevChapter) {
        onPrevChapter();
      }
    }
  }, [currentPageIndex, layout, onPageChange, onPrevChapter]);

  // Directional actions (considering LTR vs RTL)
  const handleLeftAction = useCallback(() => {
    if (readingDirection === 'rtl') {
      goToNextPage();
    } else {
      goToPrevPage();
    }
  }, [readingDirection, goToNextPage, goToPrevPage]);

  const handleRightAction = useCallback(() => {
    if (readingDirection === 'rtl') {
      goToPrevPage();
    } else {
      goToNextPage();
    }
  }, [readingDirection, goToPrevPage, goToNextPage]);

  // Keyboard navigation for paginated modes
  useEffect(() => {
    if (layout === 'vertical') return;

    const handleKeyDown = (e: KeyboardEvent) => {
      // Don't trigger if typing in an input or modal is open
      if (isSettingsOpen) return;
      const target = e.target as HTMLElement;
      if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
        return;
      }

      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        handleLeftAction();
      } else if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown') {
        e.preventDefault();
        handleRightAction();
      } else if (e.key === 'PageUp') {
        e.preventDefault();
        handleLeftAction();
      } else if (e.key === 'Home') {
        e.preventDefault();
        onPageChange(0);
      } else if (e.key === 'End') {
        e.preventDefault();
        onPageChange(totalPages - 1);
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [layout, handleLeftAction, handleRightAction, isSettingsOpen, onPageChange, totalPages]);

  // Touch Swipe Handlers for mobile
  const handleTouchStart = (e: React.TouchEvent) => {
    if (layout === 'vertical') return;
    touchStartX.current = e.touches[0].clientX;
    touchStartY.current = e.touches[0].clientY;
  };

  const handleTouchEnd = (e: React.TouchEvent) => {
    if (layout === 'vertical' || touchStartX.current === null || touchStartY.current === null) return;
    const deltaX = e.changedTouches[0].clientX - touchStartX.current;
    const deltaY = e.changedTouches[0].clientY - touchStartY.current;

    // Must be predominantly horizontal and over threshold
    if (Math.abs(deltaX) > 45 && Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
      if (deltaX > 0) {
        // Swiped Right -> trigger Left action
        handleLeftAction();
      } else {
        // Swiped Left -> trigger Right action
        handleRightAction();
      }
    }
    touchStartX.current = null;
    touchStartY.current = null;
  };

  if (totalPages === 0) {
    return (
      <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl my-12 max-w-2xl mx-auto">
        Bölüm sayfaları henüz yüklenmedi.
      </div>
    );
  }

  // Get image styling based on imageFit
  const getImageFitClass = () => {
    switch (imageFit) {
      case 'height':
        return 'max-h-[82vh] sm:max-h-[86vh] w-auto mx-auto object-contain';
      case 'original':
        return 'max-w-full w-auto mx-auto object-none sm:object-scale-down';
      case 'width':
      default:
        return 'w-full max-w-4xl mx-auto h-auto object-contain';
    }
  };

  // Render Page Component with Skeleton and Error Retry
  const renderSinglePageImage = (index: number, labelPrefix?: string) => {
    const page = pages[index];
    if (!page) return null;

    const isLoaded = loadedPages[index];
    const isError = errorPages[index];

    return (
      <div key={index} className="relative w-full flex flex-col items-center justify-center min-h-[300px] select-none">
        {/* Loading Skeleton */}
        {!isLoaded && !isError && (
          <div className="absolute inset-0 min-h-[400px] bg-[var(--bg-tertiary)]/60 animate-pulse rounded-lg flex items-center justify-center">
            <span className="text-xs font-mono text-[var(--text-muted)]">
              Sayfa {index + 1} yükleniyor...
            </span>
          </div>
        )}

        {/* Error Fallback */}
        {isError ? (
          <div className="p-8 text-center bg-[var(--bg-card)] border border-rose-500/20 rounded-2xl flex flex-col items-center gap-3 my-4 max-w-sm">
            <AlertCircle className="w-8 h-8 text-rose-500" />
            <p className="text-xs text-[var(--text-secondary)]">
              {labelPrefix || `Sayfa ${index + 1}`} yüklenirken hata oluştu.
            </p>
            <button
              type="button"
              onClick={() => retryImage(index)}
              className="py-1.5 px-4 rounded-lg bg-[var(--accent-color)] text-white text-xs font-semibold hover:opacity-90 transition-all flex items-center gap-1.5"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              <span>Yeniden Dene</span>
            </button>
          </div>
        ) : (
          <img
            src={page.image_path}
            loading={layout === 'vertical' ? 'lazy' : 'eager'}
            decoding="async"
            alt={`Sayfa ${index + 1}`}
            referrerPolicy="no-referrer"
            onLoad={() => handleImageLoad(index)}
            onError={() => handleImageError(index)}
            className={`block select-none transition-opacity duration-200 ${getImageFitClass()} ${
              isLoaded ? 'opacity-100' : 'opacity-0'
            }`}
          />
        )}
      </div>
    );
  };

  // 1. VERTICAL LAYOUT
  if (layout === 'vertical') {
    return (
      <div className="flex flex-col items-center justify-center gap-2 w-full max-w-5xl mx-auto py-2 overflow-hidden">
        {pages.map((_, index) => (
          <div key={index} className="w-full relative overflow-hidden flex justify-center">
            {renderSinglePageImage(index)}
          </div>
        ))}

        <div className="w-full py-10 mt-6 text-center text-xs font-mono text-[var(--text-muted)] tracking-widest uppercase bg-[var(--bg-card)] border-t border-[var(--border-color)] rounded-xl">
          — BÖLÜM SONU —
        </div>
      </div>
    );
  }

  // 2. SINGLE PAGE LAYOUT
  if (layout === 'single') {
    const isFirstPage = currentPageIndex === 0;
    const isLastPage = currentPageIndex === totalPages - 1;

    return (
      <div
        className="flex flex-col items-center justify-center w-full max-w-5xl mx-auto py-2 relative select-none"
        onTouchStart={handleTouchStart}
        onTouchEnd={handleTouchEnd}
      >
        {/* Interactive Click Zones for intuitive reading */}
        <div className="relative w-full flex items-center justify-center min-h-[60vh] sm:min-h-[75vh]">
          {/* Left Click Zone */}
          <div
            onClick={handleLeftAction}
            title={readingDirection === 'rtl' ? 'Sonraki Sayfa' : 'Önceki Sayfa'}
            className="absolute left-0 top-0 bottom-0 w-1/3 z-20 cursor-pointer group flex items-center justify-start pl-2 sm:pl-4 opacity-0 hover:opacity-100 transition-opacity"
          >
            <div className="p-2.5 rounded-full bg-[var(--bg-card)]/80 backdrop-blur-md border border-[var(--border-color)] text-[var(--text-primary)] shadow-lg group-hover:scale-110 transition-transform">
              <ChevronLeft className="w-5 h-5" />
            </div>
          </div>

          {/* Current Page Image */}
          <div className="w-full flex items-center justify-center">
            {renderSinglePageImage(currentPageIndex)}
          </div>

          {/* Right Click Zone */}
          <div
            onClick={handleRightAction}
            title={readingDirection === 'rtl' ? 'Önceki Sayfa' : 'Sonraki Sayfa'}
            className="absolute right-0 top-0 bottom-0 w-1/3 z-20 cursor-pointer group flex items-center justify-end pr-2 sm:pr-4 opacity-0 hover:opacity-100 transition-opacity"
          >
            <div className="p-2.5 rounded-full bg-[var(--bg-card)]/80 backdrop-blur-md border border-[var(--border-color)] text-[var(--text-primary)] shadow-lg group-hover:scale-110 transition-transform">
              <ChevronRight className="w-5 h-5" />
            </div>
          </div>
        </div>

        {/* Page Control Bar */}
        <div className="mt-4 flex flex-wrap items-center justify-center gap-3 p-2 px-4 rounded-2xl bg-[var(--bg-card)] border border-[var(--border-color)] shadow-md text-xs font-mono">
          <button
            type="button"
            disabled={isFirstPage && !onPrevChapter}
            onClick={goToPrevPage}
            className="py-1.5 px-3 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] disabled:opacity-30 disabled:cursor-not-allowed transition-all flex items-center gap-1 cursor-pointer"
          >
            <ChevronLeft className="w-4 h-4" />
            <span className="hidden sm:inline">Önceki Sayfa</span>
          </button>

          {/* Page Selector & Counter */}
          <div className="flex items-center gap-2 px-2">
            <span className="text-[var(--text-secondary)]">Sayfa</span>
            <select
              value={currentPageIndex}
              onChange={(e) => onPageChange(Number(e.target.value))}
              className="bg-[var(--bg-tertiary)] text-[var(--text-primary)] border border-[var(--border-color)] rounded-md px-2 py-1 font-bold text-xs cursor-pointer focus:outline-none focus:border-[var(--accent-color)]"
            >
              {pages.map((_, i) => (
                <option key={i} value={i}>
                  {i + 1}
                </option>
              ))}
            </select>
            <span className="text-[var(--text-muted)]">/ {totalPages}</span>
            <span className="text-[10px] text-[var(--accent-color)] font-semibold ml-1">
              ({Math.round(((currentPageIndex + 1) / totalPages) * 100)}%)
            </span>
          </div>

          <button
            type="button"
            disabled={isLastPage && !onNextChapter}
            onClick={goToNextPage}
            className="py-1.5 px-3 rounded-lg bg-[var(--accent-color)] text-white font-bold hover:opacity-90 disabled:opacity-30 disabled:cursor-not-allowed transition-all flex items-center gap-1 cursor-pointer"
          >
            <span className="hidden sm:inline">Sonraki Sayfa</span>
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      </div>
    );
  }

  // 3. DOUBLE PAGE LAYOUT
  const isRtl = readingDirection === 'rtl';
  const page1Index = currentPageIndex;
  const page2Index = currentPageIndex + 1 < totalPages ? currentPageIndex + 1 : null;

  // In RTL, right page is first (page1Index), left page is second (page2Index)
  // In LTR, left page is first (page1Index), right page is second (page2Index)
  const leftPageIndex = isRtl ? page2Index : page1Index;
  const rightPageIndex = isRtl ? page1Index : page2Index;

  const isFirstSpread = currentPageIndex === 0;
  const isLastSpread = currentPageIndex >= totalPages - 2;

  const spreadDisplay = page2Index !== null ? `${page1Index + 1}-${page2Index + 1}` : `${page1Index + 1}`;

  return (
    <div
      className="flex flex-col items-center justify-center w-full max-w-7xl mx-auto py-2 relative select-none"
      onTouchStart={handleTouchStart}
      onTouchEnd={handleTouchEnd}
    >
      {/* Interactive Click Zones for Double Spread */}
      <div className="relative w-full flex items-center justify-center min-h-[60vh] sm:min-h-[75vh]">
        {/* Left Click Zone */}
        <div
          onClick={handleLeftAction}
          title={isRtl ? 'Sonraki Sayfalar' : 'Önceki Sayfalar'}
          className="absolute left-0 top-0 bottom-0 w-1/4 z-20 cursor-pointer group flex items-center justify-start pl-2 sm:pl-4 opacity-0 hover:opacity-100 transition-opacity"
        >
          <div className="p-2.5 rounded-full bg-[var(--bg-card)]/80 backdrop-blur-md border border-[var(--border-color)] text-[var(--text-primary)] shadow-lg group-hover:scale-110 transition-transform">
            <ChevronLeft className="w-5 h-5" />
          </div>
        </div>

        {/* Double Page Spread Grid */}
        <div className="w-full grid grid-cols-1 md:grid-cols-2 gap-2 sm:gap-4 items-center justify-center">
          {/* Left Page Slot */}
          <div className="w-full flex items-center justify-center md:justify-end">
            {leftPageIndex !== null ? renderSinglePageImage(leftPageIndex) : <div className="hidden md:block w-full min-h-[300px]" />}
          </div>

          {/* Right Page Slot */}
          <div className="w-full flex items-center justify-center md:justify-start">
            {rightPageIndex !== null ? renderSinglePageImage(rightPageIndex) : <div className="hidden md:block w-full min-h-[300px]" />}
          </div>
        </div>

        {/* Right Click Zone */}
        <div
          onClick={handleRightAction}
          title={isRtl ? 'Önceki Sayfalar' : 'Sonraki Sayfalar'}
          className="absolute right-0 top-0 bottom-0 w-1/4 z-20 cursor-pointer group flex items-center justify-end pr-2 sm:pr-4 opacity-0 hover:opacity-100 transition-opacity"
        >
          <div className="p-2.5 rounded-full bg-[var(--bg-card)]/80 backdrop-blur-md border border-[var(--border-color)] text-[var(--text-primary)] shadow-lg group-hover:scale-110 transition-transform">
            <ChevronRight className="w-5 h-5" />
          </div>
        </div>
      </div>

      {/* Page Control Bar */}
      <div className="mt-4 flex flex-wrap items-center justify-center gap-3 p-2 px-4 rounded-2xl bg-[var(--bg-card)] border border-[var(--border-color)] shadow-md text-xs font-mono">
        <button
          type="button"
          disabled={isFirstSpread && !onPrevChapter}
          onClick={goToPrevPage}
          className="py-1.5 px-3 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] disabled:opacity-30 disabled:cursor-not-allowed transition-all flex items-center gap-1 cursor-pointer"
        >
          <ChevronLeft className="w-4 h-4" />
          <span className="hidden sm:inline">Önceki</span>
        </button>

        {/* Page Selector & Counter */}
        <div className="flex items-center gap-2 px-2">
          <Layers className="w-4 h-4 text-[var(--accent-color)]" />
          <span className="text-[var(--text-secondary)]">Sayfa {spreadDisplay} / {totalPages}</span>
          <span className="text-[10px] text-[var(--accent-color)] font-semibold ml-1">
            ({Math.round(((currentPageIndex + 1) / totalPages) * 100)}%)
          </span>
        </div>

        <button
          type="button"
          disabled={isLastSpread && !onNextChapter}
          onClick={goToNextPage}
          className="py-1.5 px-3 rounded-lg bg-[var(--accent-color)] text-white font-bold hover:opacity-90 disabled:opacity-30 disabled:cursor-not-allowed transition-all flex items-center gap-1 cursor-pointer"
        >
          <span className="hidden sm:inline">Sonraki</span>
          <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
};

export const MangaReader = ImageReader;
