import React from 'react';
import Markdown, { Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { Chapter } from '../../types/api';
import { ReaderSettingsState } from '../../types/domain';
import { usePreferences } from '../../contexts/PreferencesContext';
import { TranslatorNoteCard } from './TranslatorNoteCard';

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

function resolveFontFamily(fontKey?: string): string {
  if (!fontKey) return "'Playfair Display', Georgia, serif";
  if (fontKey === 'serif') return "'Playfair Display', Georgia, Cambria, 'Times New Roman', serif";
  if (fontKey === 'var(--font-mono)' || fontKey === 'monospace' || fontKey === 'mono') {
    return 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace';
  }
  if (fontKey === 'var(--font-sans)' || fontKey === 'sans' || fontKey === 'sans-serif') {
    return "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
  }
  return fontKey;
}

export const TextReader: React.FC<TextReaderProps> = ({ chapter, readerSettings, settings }) => {
  let t = (key: string) => {
    if (key === 'reader.noTextContent') return 'Bu bölüm için metin içeriği bulunamadı.';
    if (key === 'reader.endOfChapter') return 'Bölümün Sonu';
    return key;
  };

  try {
    const preferences = usePreferences();
    if (preferences && typeof preferences.t === 'function') {
      t = preferences.t;
    }
  } catch {
    // Fallback for standalone or test rendering
  }

  const bodyText = chapter.body;

  if (!bodyText || bodyText.trim().length === 0) {
    return (
      <div
        id="reader-no-text-content"
        className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl my-12 max-w-[760px] mx-auto"
      >
        {t('reader.noTextContent')}
      </div>
    );
  }

  const fontSize = Number(readerSettings?.fontSize || settings?.fontSize || 18);
  const lineHeight = Number(readerSettings?.lineHeight || settings?.lineHeight || 1.8);
  const maxWidth = settings?.readingWidth || 760;
  const rawFontFamily = readerSettings?.fontFamily || settings?.fontFamily || "'Playfair Display', Georgia, serif";
  const fontFamily = resolveFontFamily(rawFontFamily);
  const isRtl = readerSettings?.readingDirection === 'rtl' || readerSettings?.direction === 'rtl';

  const markdownComponents: Components = {
    h1: ({ children, ...props }) => (
      <h1
        className="font-serif text-2xl sm:text-3xl font-bold tracking-tight text-[var(--text-primary)] mt-8 mb-4 pt-2 border-b border-[var(--border-color)] pb-3"
        {...props}
      >
        {children}
      </h1>
    ),
    h2: ({ children, ...props }) => (
      <h2
        className="font-serif text-xl sm:text-2xl font-bold tracking-tight text-[var(--text-primary)] mt-7 mb-3"
        {...props}
      >
        {children}
      </h2>
    ),
    h3: ({ children, ...props }) => (
      <h3
        className="font-serif text-lg sm:text-xl font-semibold text-[var(--text-primary)] mt-6 mb-2.5"
        {...props}
      >
        {children}
      </h3>
    ),
    h4: ({ children, ...props }) => (
      <h4
        className="font-serif text-base sm:text-lg font-semibold text-[var(--text-primary)] mt-5 mb-2"
        {...props}
      >
        {children}
      </h4>
    ),
    p: ({ children, ...props }) => (
      <p
        className={`my-4 text-[var(--text-primary)] font-normal leading-relaxed ${
          isRtl ? 'text-right' : 'text-left'
        }`}
        {...props}
      >
        {children}
      </p>
    ),
    strong: ({ children, ...props }) => (
      <strong className="font-bold text-[var(--text-primary)]" {...props}>
        {children}
      </strong>
    ),
    em: ({ children, ...props }) => (
      <em className="italic text-[var(--text-primary)]" {...props}>
        {children}
      </em>
    ),
    del: ({ children, ...props }) => (
      <del className="line-through opacity-75 text-[var(--text-muted)]" {...props}>
        {children}
      </del>
    ),
    blockquote: ({ children, ...props }) => (
      <blockquote
        className={`my-6 py-3 px-4 sm:px-6 bg-[var(--bg-tertiary)]/60 text-[var(--text-secondary)] italic rounded-2xl transition-colors border-[var(--accent-color)] ${
          isRtl
            ? 'border-r-4 rounded-r-none text-right'
            : 'border-l-4 rounded-l-none text-left'
        } [&>blockquote]:my-3 [&>blockquote]:border-current [&>blockquote]:opacity-90 [&>blockquote]:bg-transparent [&>blockquote]:py-1`}
        {...props}
      >
        {children}
      </blockquote>
    ),
    ul: ({ children, ...props }) => (
      <ul
        className={`my-4 space-y-2 list-disc text-[var(--text-primary)] ${
          isRtl ? 'pr-6 pl-2 text-right' : 'pl-6 pr-2 text-left'
        } [&>li>ul]:my-1.5 [&>li>ul]:list-[circle] [&>li>ul]:pl-5 rtl:[&>li>ul]:pl-0 rtl:[&>li>ul]:pr-5 [&>li>ol]:my-1.5 [&>li>ol]:pl-5 rtl:[&>li>ol]:pl-0 rtl:[&>li>ol]:pr-5`}
        {...props}
      >
        {children}
      </ul>
    ),
    ol: ({ children, ...props }) => (
      <ol
        className={`my-4 space-y-2 list-decimal text-[var(--text-primary)] ${
          isRtl ? 'pr-6 pl-2 text-right' : 'pl-6 pr-2 text-left'
        } [&>li>ul]:my-1.5 [&>li>ul]:pl-5 rtl:[&>li>ul]:pl-0 rtl:[&>li>ul]:pr-5 [&>li>ol]:my-1.5 [&>li>ol]:pl-5 rtl:[&>li>ol]:pl-0 rtl:[&>li>ol]:pr-5`}
        {...props}
      >
        {children}
      </ol>
    ),
    li: ({ children, ...props }) => (
      <li className="leading-relaxed" {...props}>
        {children}
      </li>
    ),
    a: ({ href, children, ...props }) => {
      const isExternal = href?.startsWith('http://') || href?.startsWith('https://');
      return (
        <a
          href={href}
          target={isExternal ? '_blank' : undefined}
          rel={isExternal ? 'noopener noreferrer' : undefined}
          className="text-[var(--accent-color)] underline underline-offset-4 decoration-[var(--accent-color)]/50 hover:decoration-[var(--accent-color)] hover:opacity-80 transition-all font-medium"
          {...props}
        >
          {children}
        </a>
      );
    },
    hr: ({ ...props }) => (
      <hr className="my-8 border-t border-[var(--border-color)] w-full" {...props} />
    ),
    code: ({ className, children, ...props }) => {
      const isInline = !className && typeof children === 'string' && !children.includes('\n');
      if (isInline) {
        return (
          <code
            className="font-mono text-[0.88em] px-1.5 py-0.5 rounded-md bg-[var(--bg-tertiary)] text-[var(--accent-color)] border border-[var(--border-color)] break-words"
            {...props}
          >
            {children}
          </code>
        );
      }
      return (
        <code className={`font-mono text-xs sm:text-sm leading-relaxed block ${className || ''}`} {...props}>
          {children}
        </code>
      );
    },
    pre: ({ children, ...props }) => (
      <pre
        className="my-6 p-4 sm:p-5 rounded-2xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] overflow-x-auto text-[var(--text-primary)] font-mono text-xs sm:text-sm shadow-inner"
        {...props}
      >
        {children}
      </pre>
    ),
    table: ({ children, ...props }) => (
      <div className="my-6 w-full overflow-x-auto">
        <table className="w-full border-collapse border border-[var(--border-color)] rounded-xl text-sm text-[var(--text-primary)]" {...props}>
          {children}
        </table>
      </div>
    ),
    th: ({ children, ...props }) => (
      <th className="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] font-semibold text-left rtl:text-right" {...props}>
        {children}
      </th>
    ),
    td: ({ children, ...props }) => (
      <td className="p-3 border border-[var(--border-color)]" {...props}>
        {children}
      </td>
    ),
  };

  return (
    <article
      id="novel-text-reader"
      dir={isRtl ? 'rtl' : 'ltr'}
      className="w-full max-w-full overflow-hidden mx-auto px-4 sm:px-6 py-8 sm:py-12 text-[var(--text-primary)] flex flex-col gap-6 transition-all"
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

      {/* Translator Note */}
      {chapter.translator_note && (
        <TranslatorNoteCard
          note={chapter.translator_note}
          isRtl={isRtl}
          className="my-1"
        />
      )}

      {/* Markdown Content Container */}
      <div
        id="novel-markdown-body"
        className="novel-content-body flex flex-col font-normal leading-relaxed text-[var(--text-primary)] selection:bg-[var(--accent-color)] selection:text-white"
        style={{
          fontSize: `${fontSize}px`,
          lineHeight: lineHeight,
          fontFamily: fontFamily,
        }}
      >
        <Markdown remarkPlugins={[remarkGfm]} components={markdownComponents}>
          {bodyText}
        </Markdown>
      </div>

      <div
        id="reader-end-marker"
        className="pt-12 text-center text-xs font-mono text-[var(--text-muted)] tracking-widest uppercase bg-[var(--bg-card)] border-t border-[var(--border-color)] py-6 rounded-xl mt-6"
      >
        — {t('reader.endOfChapter')} —
      </div>
    </article>
  );
};

export const NovelReader = TextReader;
