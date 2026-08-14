import React, { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';

type ContentDescriptionProps = {
  description?: string | null;
  summary?: string | null;
};

export const ContentDescription: React.FC<ContentDescriptionProps> = ({
  description,
  summary,
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const text = description || summary || 'Bu içerik için henüz bir açıklama girilmemiştir.';

  const isLong = text.length > 280;

  return (
    <div className="bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-5 sm:p-6 flex flex-col gap-3 shadow-sm transition-colors duration-300">
      <h3 className="font-serif text-lg font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-3">
        Özet & <span className="italic text-[var(--accent-color)]">Açıklama</span>
      </h3>

      <div className="relative">
        <p
          className={`text-xs sm:text-sm text-[var(--text-secondary)] font-light leading-relaxed whitespace-pre-line break-words ${
            !isExpanded && isLong ? 'line-clamp-4' : ''
          }`}
        >
          {text}
        </p>

        {!isExpanded && isLong && (
          <div className="absolute bottom-0 inset-x-0 h-10 bg-gradient-to-t from-[var(--bg-card)] to-transparent pointer-events-none" />
        )}
      </div>

      {isLong && (
        <button
          type="button"
          onClick={() => setIsExpanded(!isExpanded)}
          className="self-start inline-flex items-center gap-1.5 text-xs font-mono font-semibold text-[var(--accent-color)] hover:underline pt-1 cursor-pointer"
        >
          {isExpanded ? (
            <>
              <span>Daha Az Göster</span>
              <ChevronUp className="w-3.5 h-3.5" />
            </>
          ) : (
            <>
              <span>Daha Fazla Göster</span>
              <ChevronDown className="w-3.5 h-3.5" />
            </>
          )}
        </button>
      )}
    </div>
  );
};
