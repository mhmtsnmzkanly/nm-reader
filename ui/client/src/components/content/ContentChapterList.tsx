import React, { useState, useMemo } from 'react';
import { Search, ArrowUpDown, ChevronLeft, ChevronRight, BookOpen } from 'lucide-react';
import { ContentDetailChapter, ContentType } from '../../types/api';
import { ChapterRow } from './ChapterRow';
import { usePreferences } from '../../contexts/PreferencesContext';

type ContentChapterListProps = {
  chapters: ContentDetailChapter[];
  contentType: ContentType;
  contentSlug: string;
  lastReadChapterId?: string | null;
  onLockClick: (chapter: ContentDetailChapter) => void;
};

const CHAPTERS_PER_PAGE = 15;

export const ContentChapterList: React.FC<ContentChapterListProps> = ({
  chapters,
  contentType,
  contentSlug,
  lastReadChapterId,
  onLockClick,
}) => {
  const { t } = usePreferences();
  const [searchQuery, setSearchQuery] = useState('');
  const [sortOrder, setSortOrder] = useState<'desc' | 'asc'>('desc'); // desc = newest first
  const [currentPage, setCurrentPage] = useState(1);

  // Filter and sort chapters
  const filteredAndSorted = useMemo(() => {
    let result = [...chapters];

    // Search query
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase().trim();
      result = result.filter(
        (c) =>
          String(c.number || c.chapter_number || '').includes(q) ||
          (c.title && c.title.toLowerCase().includes(q))
      );
    }

    // Sort
    result.sort((a, b) => {
      const numA = Number(a.number || a.chapter_number || 0);
      const numB = Number(b.number || b.chapter_number || 0);
      return sortOrder === 'desc' ? numB - numA : numA - numB;
    });

    return result;
  }, [chapters, searchQuery, sortOrder]);

  const totalPages = Math.ceil(filteredAndSorted.length / CHAPTERS_PER_PAGE) || 1;
  const safePage = Math.min(currentPage, totalPages);

  const paginatedChapters = useMemo(() => {
    const start = (safePage - 1) * CHAPTERS_PER_PAGE;
    return filteredAndSorted.slice(start, start + CHAPTERS_PER_PAGE);
  }, [filteredAndSorted, safePage]);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  return (
    <div className="bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-5 sm:p-6 flex flex-col gap-5 shadow-sm transition-colors duration-300">
      {/* Header & Controls */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border-color)] pb-4">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-xl bg-[var(--accent-light)] text-[var(--accent-color)]">
            <BookOpen className="w-5 h-5" />
          </div>
          <div>
            <h3 className="font-serif text-xl font-bold text-[var(--text-primary)]">
              {t('chapters.title')}
            </h3>
            <span className="text-xs font-mono text-[var(--text-muted)]">
              {t('content.chaptersCount', { count: chapters.length })}
            </span>
          </div>
        </div>

        {/* Filter & Sort Controls */}
        <div className="flex items-center gap-2 flex-wrap">
          {/* Search Input */}
          <div className="relative flex-1 sm:w-48">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--text-muted)]" />
            <input
              type="text"
              placeholder={t('chapters.searchPlaceholder')}
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value);
                setCurrentPage(1);
              }}
              className="w-full pl-8 pr-3 py-1.5 rounded-xl text-xs font-mono bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)] placeholder-[var(--text-muted)] focus:outline-none focus:border-[var(--accent-color)] transition-colors"
            />
          </div>

          {/* Sort Direction Toggle */}
          <button
            type="button"
            onClick={() => setSortOrder((prev) => (prev === 'desc' ? 'asc' : 'desc'))}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-mono bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-colors cursor-pointer"
            title={sortOrder === 'desc' ? t('chapters.sortDesc') : t('chapters.sortAsc')}
          >
            <ArrowUpDown className="w-3.5 h-3.5" />
            <span>{sortOrder === 'desc' ? t('chapters.sortDesc') : t('chapters.sortAsc')}</span>
          </button>
        </div>
      </div>

      {/* Chapters Grid / List */}
      {paginatedChapters.length === 0 ? (
        <div className="p-8 text-center text-xs font-mono text-[var(--text-muted)] border border-dashed border-[var(--border-color)] rounded-xl">
          {t('chapters.noChapters')}
        </div>
      ) : (
        <div className="flex flex-col gap-2.5">
          {paginatedChapters.map((chap) => (
            <ChapterRow
              key={chap.id}
              chapter={chap}
              contentType={contentType}
              contentSlug={contentSlug}
              isLastRead={lastReadChapterId === chap.id}
              onLockClick={onLockClick}
            />
          ))}
        </div>
      )}

      {/* Pagination Controls */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between pt-4 border-t border-[var(--border-color)] text-xs font-mono">
          <span className="text-[var(--text-muted)] text-[11px]">
            {t('common.page')} {safePage} / {totalPages} ({t('common.resultsCountSimple', { count: filteredAndSorted.length })})
          </span>

          <div className="flex items-center gap-1.5">
            <button
              type="button"
              disabled={safePage <= 1}
              onClick={() => handlePageChange(safePage - 1)}
              aria-label="Previous Page"
              className="p-1.5 rounded-lg border border-[var(--border-color)] bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>

            {Array.from({ length: Math.min(5, totalPages) }).map((_, i) => {
              let pageNum: number;
              if (totalPages <= 5) {
                pageNum = i + 1;
              } else if (safePage <= 3) {
                pageNum = i + 1;
              } else if (safePage >= totalPages - 2) {
                pageNum = totalPages - 4 + i;
              } else {
                pageNum = safePage - 2 + i;
              }

              return (
                <button
                  key={pageNum}
                  type="button"
                  onClick={() => handlePageChange(pageNum)}
                  className={`w-7 h-7 rounded-lg text-xs font-mono transition-colors cursor-pointer ${
                    safePage === pageNum
                      ? 'bg-[var(--accent-color)] text-white font-bold'
                      : 'border border-[var(--border-color)] bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]'
                  }`}
                >
                  {pageNum}
                </button>
              );
            })}

            <button
              type="button"
              disabled={safePage >= totalPages}
              onClick={() => handlePageChange(safePage + 1)}
              aria-label="Next Page"
              className="p-1.5 rounded-lg border border-[var(--border-color)] bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
