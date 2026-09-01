import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
  X,
  ZoomIn,
  ZoomOut,
  RotateCcw,
  RotateCw,
  ExternalLink,
  Download,
  Maximize2,
  Minimize2,
  Move,
} from 'lucide-react';
import { usePreferences } from '../../contexts/PreferencesContext';

export interface ImageLightboxModalProps {
  isOpen: boolean;
  onClose: () => void;
  imageUrl: string;
  altText?: string;
  title?: string;
  badge?: string;
  meta?: string;
}

export const ImageLightboxModal: React.FC<ImageLightboxModalProps> = ({
  isOpen,
  onClose,
  imageUrl,
  altText = '',
  title,
  badge,
  meta,
}) => {
  const { t } = usePreferences();

  // Zoom, pan & rotation state
  const [scale, setScale] = useState(1);
  const [rotation, setRotation] = useState(0);
  const [position, setPosition] = useState({ x: 0, y: 0 });
  const [isDragging, setIsDragging] = useState(false);
  const [dragStart, setDragStart] = useState({ x: 0, y: 0 });
  const [hasLoaded, setHasLoaded] = useState(false);

  const containerRef = useRef<HTMLDivElement>(null);
  const imageRef = useRef<HTMLImageElement>(null);

  // Reset transform whenever modal opens or image changes
  useEffect(() => {
    if (isOpen) {
      setScale(1);
      setRotation(0);
      setPosition({ x: 0, y: 0 });
      setIsDragging(false);
      setHasLoaded(false);

      // Lock body scroll
      const originalOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      return () => {
        document.body.style.overflow = originalOverflow;
      };
    }
  }, [isOpen, imageUrl]);

  // Zoom in helper
  const handleZoomIn = useCallback(() => {
    setScale((prev) => Math.min(prev + 0.25, 4));
  }, []);

  // Zoom out helper
  const handleZoomOut = useCallback(() => {
    setScale((prev) => {
      const next = Math.max(prev - 0.25, 0.5);
      if (next <= 1) {
        setPosition({ x: 0, y: 0 });
      }
      return next;
    });
  }, []);

  // Reset zoom & pan
  const handleReset = useCallback(() => {
    setScale(1);
    setRotation(0);
    setPosition({ x: 0, y: 0 });
  }, []);

  // Rotate clockwise
  const handleRotate = useCallback(() => {
    setRotation((prev) => (prev + 90) % 360);
  }, []);

  // Double click to toggle zoom
  const handleDoubleClick = useCallback(
    (e: React.MouseEvent) => {
      e.stopPropagation();
      if (scale === 1) {
        setScale(2);
      } else {
        handleReset();
      }
    },
    [scale, handleReset]
  );

  // Keyboard shortcut listeners
  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      } else if (e.key === '+' || e.key === '=') {
        e.preventDefault();
        handleZoomIn();
      } else if (e.key === '-' || e.key === '_') {
        e.preventDefault();
        handleZoomOut();
      } else if (e.key === '0') {
        e.preventDefault();
        handleReset();
      } else if (e.key === 'r' || e.key === 'R') {
        e.preventDefault();
        handleRotate();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose, handleZoomIn, handleZoomOut, handleReset, handleRotate]);

  // Mouse wheel zoom
  const handleWheel = (e: React.WheelEvent) => {
    e.preventDefault();
    if (e.deltaY < 0) {
      handleZoomIn();
    } else {
      handleZoomOut();
    }
  };

  // Drag to pan handling
  const handlePointerDown = (e: React.PointerEvent) => {
    if (scale > 1) {
      setIsDragging(true);
      setDragStart({
        x: e.clientX - position.x,
        y: e.clientY - position.y,
      });
      (e.target as HTMLElement).setPointerCapture(e.pointerId);
    }
  };

  const handlePointerMove = (e: React.PointerEvent) => {
    if (isDragging && scale > 1) {
      setPosition({
        x: e.clientX - dragStart.x,
        y: e.clientY - dragStart.y,
      });
    }
  };

  const handlePointerUp = (e: React.PointerEvent) => {
    if (isDragging) {
      setIsDragging(false);
      try {
        (e.target as HTMLElement).releasePointerCapture(e.pointerId);
      } catch {
        // Ignore if pointer capture already released
      }
    }
  };

  if (!isOpen) return null;

  return (
    <div
      id="lightbox-modal"
      role="dialog"
      aria-modal="true"
      aria-label={title || altText || 'Görsel İnceleme'}
      className="fixed inset-0 z-50 flex flex-col items-center justify-between bg-black/90 backdrop-blur-md select-none animate-in fade-in duration-200"
      onClick={onClose}
    >
      {/* Top Bar / Header */}
      <div
        className="w-full flex items-center justify-between gap-4 p-3 sm:p-4 bg-gradient-to-b from-black/80 via-black/40 to-transparent z-20 text-white"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Title & Metadata */}
        <div className="flex items-center gap-2.5 min-w-0 flex-1">
          {badge && (
            <span className="px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-mono uppercase tracking-wider font-bold bg-[var(--accent-color)] text-white shadow-sm shrink-0">
              {badge}
            </span>
          )}
          <div className="flex flex-col min-w-0">
            <h2 className="text-sm sm:text-base font-bold text-white truncate max-w-md">
              {title || altText || 'Görsel'}
            </h2>
            {meta && (
              <span className="text-[11px] text-white/70 font-mono truncate">
                {meta}
              </span>
            )}
          </div>
        </div>

        {/* Toolbar Controls */}
        <div className="flex items-center gap-1 sm:gap-2 shrink-0">
          <button
            type="button"
            onClick={handleZoomIn}
            disabled={scale >= 4}
            className="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
            title="Yakınlaştır (+)"
            aria-label="Yakınlaştır"
          >
            <ZoomIn className="w-4 h-4 sm:w-5 sm:h-5" />
          </button>

          <button
            type="button"
            onClick={handleZoomOut}
            disabled={scale <= 0.5}
            className="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
            title="Uzaklaştır (-)"
            aria-label="Uzaklaştır"
          >
            <ZoomOut className="w-4 h-4 sm:w-5 sm:h-5" />
          </button>

          <button
            type="button"
            onClick={handleReset}
            className="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer"
            title="Sıfırla (0)"
            aria-label="Sıfırla"
          >
            <RotateCcw className="w-4 h-4 sm:w-5 sm:h-5" />
          </button>

          <button
            type="button"
            onClick={handleRotate}
            className="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer hidden sm:inline-flex"
            title="Döndür (R)"
            aria-label="Döndür"
          >
            <RotateCw className="w-4 h-4 sm:w-5 sm:h-5" />
          </button>

          <a
            href={imageUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer inline-flex items-center justify-center"
            title="Yeni Sekmede Aç"
            aria-label="Yeni Sekmede Aç"
          >
            <ExternalLink className="w-4 h-4 sm:w-5 sm:h-5" />
          </a>

          <div className="w-px h-6 bg-white/20 mx-1" />

          <button
            type="button"
            onClick={onClose}
            className="p-2 rounded-xl bg-rose-600/80 hover:bg-rose-600 text-white transition-colors cursor-pointer"
            title="Kapat (Esc)"
            aria-label="Kapat"
          >
            <X className="w-4 h-4 sm:w-5 sm:h-5" />
          </button>
        </div>
      </div>

      {/* Main Image Stage */}
      <div
        ref={containerRef}
        onWheel={handleWheel}
        onPointerDown={handlePointerDown}
        onPointerMove={handlePointerMove}
        onPointerUp={handlePointerUp}
        onDoubleClick={handleDoubleClick}
        className={`relative flex-1 w-full h-full flex items-center justify-center p-4 overflow-hidden ${
          scale > 1 ? (isDragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-zoom-in'
        }`}
      >
        <div
          className="relative max-w-full max-h-full flex items-center justify-center transition-transform duration-100 ease-out"
          style={{
            transform: `translate3d(${position.x}px, ${position.y}px, 0px) scale(${scale}) rotate(${rotation}deg)`,
            transformOrigin: 'center center',
          }}
          onClick={(e) => e.stopPropagation()}
        >
          <img
            ref={imageRef}
            src={imageUrl}
            alt={altText || title || 'Görsel'}
            referrerPolicy="no-referrer"
            onLoad={() => setHasLoaded(true)}
            draggable={false}
            className={`max-w-[85vw] sm:max-w-[75vw] max-h-[75vh] object-contain rounded-xl shadow-2xl transition-opacity duration-300 ${
              hasLoaded ? 'opacity-100' : 'opacity-0'
            }`}
          />

          {!hasLoaded && (
            <div className="absolute inset-0 flex items-center justify-center min-w-[240px] min-h-[320px] bg-white/5 rounded-xl border border-white/10 animate-pulse text-white/50 text-xs">
              Yükleniyor...
            </div>
          )}
        </div>
      </div>

      {/* Bottom Status / Instructions Pill */}
      <div
        className="w-full flex items-center justify-center p-3 sm:p-4 bg-gradient-to-t from-black/80 via-black/40 to-transparent z-20 text-white pointer-events-none"
      >
        <div className="inline-flex items-center gap-3 px-4 py-1.5 rounded-full bg-black/60 backdrop-blur-md border border-white/15 text-xs text-white/90 shadow-lg">
          <span className="font-mono font-bold text-[var(--accent-color)]">
            {Math.round(scale * 100)}%
          </span>
          <span className="hidden sm:inline text-white/40">•</span>
          <span className="hidden sm:inline text-white/70 text-[11px]">
            {scale > 1 ? 'Sürükleyerek kaydırın' : 'Çift tıklayarak veya tekerlek ile yakınlaştırın'}
          </span>
          <span className="hidden md:inline text-white/40">•</span>
          <span className="hidden md:inline text-white/50 text-[10px] font-mono">
            ESC: Kapat | +/-: Yakınlaştır | R: Döndür
          </span>
        </div>
      </div>
    </div>
  );
};
