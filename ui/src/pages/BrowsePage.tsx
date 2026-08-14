import React, { useEffect, useState } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { contentService } from '../services';
import { ContentSummary, ContentType, PaginationMeta } from '../types/api';
import { ContentCard } from '../components/content/ContentCard';
import { Pagination } from '../components/feedback/Pagination';
import { BookOpen, Layers, Sparkles, Filter } from 'lucide-react';
import { usePreferences } from '../contexts/PreferencesContext';

type TypeOption = {
  key: ContentType;
  label: string;
  descriptionTr: string;
  descriptionEn: string;
};

const CONTENT_TYPES: TypeOption[] = [
  { key: 'manga', label: 'Manga', descriptionTr: 'Japon Çizgi Romanları', descriptionEn: 'Japanese Comics' },
  { key: 'manhwa', label: 'Manhwa', descriptionTr: 'Kore Çizgi Romanları', descriptionEn: 'Korean Comics' },
  { key: 'manhua', label: 'Manhua', descriptionTr: 'Çin Çizgi Romanları', descriptionEn: 'Chinese Comics' },
  { key: 'webtoon', label: 'Webtoon', descriptionTr: 'Renkli Dikey Web Çizimleri', descriptionEn: 'Color Web Comics' },
  { key: 'light-novel', label: 'Light Novel', descriptionTr: 'Japon Hafif Romanları', descriptionEn: 'Japanese Light Novels' },
  { key: 'web-novel', label: 'Web Novel', descriptionTr: 'İnternet Romanları', descriptionEn: 'Web Novels' },
  { key: 'novel', label: 'Novel', descriptionTr: 'Geleneksel ve Fantastik Romanlar', descriptionEn: 'Novels & Fiction' },
];

export const BrowsePage: React.FC = () => {
  const { t, lang } = usePreferences();
  const { type = 'manga' } = useParams<{ type: string }>();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();

  const page = parseInt(searchParams.get('page') || '1', 10);
  const perPage = parseInt(searchParams.get('per_page') || '10', 10);

  const [contents, setContents] = useState<ContentSummary[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [sortBy, setSortBy] = useState<'recent' | 'rating' | 'chapters'>('recent');

  const currentTypeConfig = CONTENT_TYPES.find((ct) => ct.key === type) || CONTENT_TYPES[0];
  const typeDescription = lang === 'en' ? currentTypeConfig.descriptionEn : currentTypeConfig.descriptionTr;

  useEffect(() => {
    const fetchBrowse = async () => {
      setIsLoading(true);
      const res = await contentService.getContentByType(type as ContentType, page, perPage);

      if (res.status === 'success') {
        let items = [...res.data];
        if (sortBy === 'rating') {
          items.sort((a, b) => (b.rating_avg || 0) - (a.rating_avg || 0));
        } else if (sortBy === 'chapters') {
          items.sort((a, b) => (b.chapter_count || 0) - (a.chapter_count || 0));
        }
        setContents(items);
        setMeta(res.meta as PaginationMeta);
      }
      setIsLoading(false);
    };

    fetchBrowse();
  }, [type, page, perPage, sortBy]);

  const handleTypeChange = (newType: ContentType) => {
    navigate(`/browse/${newType}?page=1`);
  };

  const handlePageChange = (newPage: number) => {
    const params = new URLSearchParams(searchParams);
    params.set('page', newPage.toString());
    setSearchParams(params);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-6 sm:gap-8 transition-colors duration-300">
      {/* Page Header */}
      <div className="flex flex-col gap-2 border-b border-[var(--border-color)] pb-6">
        <div className="flex items-center justify-between">
          <div>
            <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold flex items-center gap-1.5">
              <Sparkles className="w-3.5 h-3.5" />
              {t('browse.browseAndExplore')}
            </span>
            <h1 className="font-serif text-2xl sm:text-3xl font-bold text-[var(--text-primary)] capitalize mt-1">
              {currentTypeConfig.label}
            </h1>
          </div>

          <div className="hidden sm:flex items-center gap-2 bg-[var(--bg-tertiary)] px-3 py-1.5 rounded-xl border border-[var(--border-color)] text-xs text-[var(--text-secondary)]">
            <BookOpen className="w-4 h-4 text-[var(--accent-color)]" />
            <span>{t('common.totalItems', { count: meta?.total ?? contents.length })}</span>
          </div>
        </div>

        <p className="text-xs sm:text-sm text-[var(--text-muted)] font-normal">
          {typeDescription}
        </p>
      </div>

      {/* Content Type Selector Tabs */}
      <div className="flex flex-col gap-3">
        <div className="flex items-center justify-between">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] flex items-center gap-1.5">
            <Layers className="w-4 h-4 text-[var(--accent-color)]" />
            {t('browse.selectContentType')}
          </label>
        </div>

        {/* Scrollable Horizontal Tabs */}
        <div className="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none -mx-4 px-4 sm:mx-0 sm:px-0">
          {CONTENT_TYPES.map((ct) => {
            const isActive = ct.key === type;
            return (
              <button
                key={ct.key}
                type="button"
                onClick={() => handleTypeChange(ct.key)}
                className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold tracking-wide transition-all whitespace-nowrap cursor-pointer shrink-0 border ${
                  isActive
                    ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] shadow-md shadow-[var(--accent-color)]/25 scale-[1.02]'
                    : 'bg-[var(--bg-card)] text-[var(--text-secondary)] border-[var(--border-color)] hover:border-[var(--accent-color)]/50 hover:text-[var(--text-primary)]'
                }`}
              >
                <span>{ct.label}</span>
              </button>
            );
          })}
        </div>
      </div>

      {/* Sorting & Filter Toolbar */}
      <div className="flex items-center justify-between bg-[var(--bg-card)] p-3 rounded-2xl border border-[var(--border-color)]">
        <div className="flex items-center gap-2 text-xs text-[var(--text-secondary)] font-medium">
          <Filter className="w-4 h-4 text-[var(--accent-color)]" />
          <span className="hidden xs:inline">{t('browse.sortBy')}:</span>
        </div>

        <div className="flex items-center gap-1.5 bg-[var(--bg-tertiary)] p-1 rounded-xl">
          <button
            type="button"
            onClick={() => setSortBy('recent')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
              sortBy === 'recent'
                ? 'bg-[var(--bg-card)] text-[var(--text-primary)] shadow-sm'
                : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'
            }`}
          >
            {t('browse.sortNewest')}
          </button>
          <button
            type="button"
            onClick={() => setSortBy('rating')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
              sortBy === 'rating'
                ? 'bg-[var(--bg-card)] text-[var(--accent-color)] shadow-sm'
                : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'
            }`}
          >
            {t('browse.sortRating')}
          </button>
          <button
            type="button"
            onClick={() => setSortBy('chapters')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
              sortBy === 'chapters'
                ? 'bg-[var(--bg-card)] text-[var(--text-primary)] shadow-sm'
                : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'
            }`}
          >
            {t('browse.sortChapters')}
          </button>
        </div>
      </div>

      {/* Content Grid */}
      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          {[...Array(perPage)].map((_, i) => (
            <div key={i} className="aspect-[3/4] bg-[var(--bg-tertiary)] rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : contents.length === 0 ? (
        <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl bg-[var(--bg-card)]">
          {t('browse.noContentInType', { type: currentTypeConfig.label })}
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            {contents.map((item) => (
              <ContentCard key={item.id} content={item} />
            ))}
          </div>

          <Pagination
            currentPage={meta?.page || page}
            totalPages={meta?.total_pages}
            total={meta?.total}
            perPage={meta?.per_page || perPage}
            onPageChange={handlePageChange}
          />
        </>
      )}
    </div>
  );
};

