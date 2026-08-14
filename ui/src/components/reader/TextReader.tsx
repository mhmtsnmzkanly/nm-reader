import React from 'react';
import { Chapter } from '../../types/api';
import { ReaderSettingsState } from '../../types/domain';

type TextReaderProps = {
  chapter: Chapter;
  readerSettings?: ReaderSettingsState;
  settings?: {
    fontSize?: number;
    lineHeight?: number;
    readingWidth?: number;
    fontFamily?: string;
  };
};

export const TextReader: React.FC<TextReaderProps> = ({ chapter, readerSettings, settings }) => {
  const bodyText = chapter.body;

  if (!bodyText || bodyText.trim().length === 0) {
    return (
      <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl my-12 max-w-[760px] mx-auto">
        Bölüm içeriği henüz eklenmedi.
      </div>
    );
  }

  // Split body by "\n\n"
  const paragraphs = bodyText
    .split(/\n\s*\n/)
    .map((p) => p.trim())
    .filter((p) => p.length > 0);

  const fontSize = Number(readerSettings?.fontSize || settings?.fontSize || 18);
  const lineHeight = Number(readerSettings?.lineHeight || settings?.lineHeight || 1.8);
  const maxWidth = settings?.readingWidth || 760;
  const fontFamily = readerSettings?.fontFamily || settings?.fontFamily || "'Playfair Display', Georgia, serif";
  const isRtl = readerSettings?.readingDirection === 'rtl';

  return (
    <article
      dir={isRtl ? 'rtl' : 'ltr'}
      className="w-full max-w-full overflow-hidden mx-auto px-4 sm:px-6 py-8 sm:py-12 text-[var(--text-primary)] flex flex-col gap-8 transition-all"
      style={{ maxWidth: `${maxWidth}px` }}
    >
      {/* Optional Chapter Title inside reader area */}
      {chapter.title && (
        <header className="pb-6 border-b border-[var(--border-color)] text-center">
          <h1 className="font-serif text-2xl sm:text-3xl font-bold tracking-wide text-[var(--text-primary)]">
            {chapter.title}
          </h1>
        </header>
      )}

      {/* Paragraphs */}
      <div
        className="flex flex-col gap-6 font-normal leading-relaxed text-[var(--text-primary)] selection:bg-[var(--accent-color)] selection:text-white"
        style={{
          fontSize: `${fontSize}px`,
          lineHeight: lineHeight,
          fontFamily: fontFamily,
        }}
      >
        {paragraphs.map((paragraph, idx) => (
          <p key={idx} className={`whitespace-pre-wrap leading-relaxed ${isRtl ? 'text-right' : 'text-left'}`}>
            {paragraph}
          </p>
        ))}
      </div>

      <div className="pt-12 text-center text-xs font-mono text-[var(--text-muted)] tracking-widest uppercase bg-[var(--bg-card)] border-t border-[var(--border-color)] py-6 rounded-xl">
        — BÖLÜM SONU —
      </div>
    </article>
  );
};

export const NovelReader = TextReader;
