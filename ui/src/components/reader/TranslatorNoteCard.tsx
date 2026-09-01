import React, { useState } from 'react';
import { MessageSquareQuote, PenTool, ChevronDown, ChevronUp, Sparkles } from 'lucide-react';
import { usePreferences } from '../../contexts/PreferencesContext';

export interface TranslatorNoteCardProps {
  note: string;
  className?: string;
  isRtl?: boolean;
}

export const TranslatorNoteCard: React.FC<TranslatorNoteCardProps> = ({
  note,
  className = '',
  isRtl = false,
}) => {
  const { t } = usePreferences();
  const [isExpanded, setIsExpanded] = useState(true);

  if (!note || note.trim().length === 0) {
    return null;
  }

  const isLong = note.length > 280;

  return (
    <div
      id="reader-translator-note-card"
      dir={isRtl ? 'rtl' : 'ltr'}
      className={`w-full relative overflow-hidden rounded-2xl border border-[var(--border-color)] bg-[var(--bg-card)]/95 backdrop-blur-md shadow-lg transition-all ${className}`}
    >
      {/* Subtle top accent highlight stripe */}
      <div className="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-[var(--accent-color)] via-amber-500 to-[var(--accent-color)] opacity-80" />

      <div className="p-4 sm:p-5 flex flex-col gap-3">
        {/* Header with Title & Action */}
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-2.5 min-w-0">
            <div className="w-8 h-8 rounded-xl bg-[var(--accent-color)]/10 text-[var(--accent-color)] flex items-center justify-center shrink-0 shadow-inner">
              <MessageSquareQuote className="w-4 h-4" />
            </div>
            <div className="flex items-center gap-2 flex-wrap">
              <h3 className="text-xs sm:text-sm font-bold text-[var(--text-primary)] font-mono uppercase tracking-wider">
                {t('reader.translatorNote')}
              </h3>
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-medium bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border border-[var(--border-color)]">
                <PenTool className="w-2.5 h-2.5 text-[var(--accent-color)]" />
                <span>TN</span>
              </span>
            </div>
          </div>

          {isLong && (
            <button
              type="button"
              onClick={() => setIsExpanded((prev) => !prev)}
              className="p-1.5 rounded-lg bg-[var(--bg-tertiary)] hover:bg-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors cursor-pointer text-xs flex items-center gap-1 font-mono"
              aria-label={isExpanded ? 'Gizle' : 'Göster'}
            >
              {isExpanded ? (
                <>
                  <span className="hidden sm:inline text-[11px]">Gizle</span>
                  <ChevronUp className="w-3.5 h-3.5" />
                </>
              ) : (
                <>
                  <span className="hidden sm:inline text-[11px]">Genişlet</span>
                  <ChevronDown className="w-3.5 h-3.5" />
                </>
              )}
            </button>
          )}
        </div>

        {/* Note Content Body */}
        {(!isLong || isExpanded) && (
          <div
            className={`text-xs sm:text-sm text-[var(--text-primary)] leading-relaxed bg-[var(--bg-tertiary)]/50 rounded-xl p-3 sm:p-4 border border-[var(--border-color)]/60 whitespace-pre-line font-normal ${
              isRtl ? 'text-right' : 'text-left'
            }`}
          >
            {note}
          </div>
        )}
      </div>
    </div>
  );
};
