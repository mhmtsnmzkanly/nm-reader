import React, { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { contentService } from '../services';
import { ContentSummary, Genre, Tag, PaginationMeta } from '../types/api';
import { ContentCard } from '../components/content/ContentCard';
import { Pagination } from '../components/feedback/Pagination';
import { Filter, SlidersHorizontal, Search as SearchIcon } from 'lucide-react';
import { usePreferences } from '../contexts/PreferencesContext';

export const SearchPage: React.FC = () => {
  const { t } = usePreferences();
  const [searchParams, setSearchParams] = useSearchParams();

  const query = searchParams.get('q') || '';
  const selectedGenresStr = searchParams.get('genres') || '';
  const selectedTagsStr = searchParams.get('tags') || '';
  const statusParam = searchParams.get('status') || '';
  const sortParam = searchParams.get('sort') || 'EN YENİLER';
  const pageParam = parseInt(searchParams.get('page') || '1', 10);
  const perPageParam = parseInt(searchParams.get('per_page') || '10', 10);

  const [searchInput, setSearchInput] = useState(query);
  const [results, setResults] = useState<ContentSummary[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [availableGenres, setAvailableGenres] = useState<Genre[]>([]);
  const [availableTags, setAvailableTags] = useState<Tag[]>([]);
  const [showFilters, setShowFilters] = useState(false);

  const selectedGenres = selectedGenresStr ? selectedGenresStr.split(',').filter(Boolean) : [];
  const selectedTags = selectedTagsStr ? selectedTagsStr.split(',').filter(Boolean) : [];

  useEffect(() => {
    const fetchTaxonomies = async () => {
      const [gRes, tRes] = await Promise.all([
        contentService.getGenres(1, 50),
        contentService.getTags(1, 50),
      ]);
      if (gRes.status === 'success') setAvailableGenres(gRes.data);
      if (tRes.status === 'success') setAvailableTags(tRes.data);
    };
    fetchTaxonomies();
  }, []);

  useEffect(() => {
    const performSearch = async () => {
      setIsLoading(true);
      const res = await contentService.search(query, pageParam, perPageParam, {
        genres: selectedGenres,
        tags: selectedTags,
        status: statusParam,
        sort: sortParam,
      });

      if (res.status === 'success') {
        setResults(res.data);
        setMeta(res.meta as PaginationMeta);
      }
      setIsLoading(false);
    };

    performSearch();
  }, [query, selectedGenresStr, selectedTagsStr, statusParam, sortParam, pageParam, perPageParam]);

  const updateParam = (key: string, value: string) => {
    const newParams = new URLSearchParams(searchParams);
    if (value) {
      newParams.set(key, value);
    } else {
      newParams.delete(key);
    }
    // reset to page 1 on filter changes unless changing page
    if (key !== 'page') {
      newParams.set('page', '1');
    }
    setSearchParams(newParams);
  };

  const toggleGenre = (slug: string) => {
    const exists = selectedGenres.includes(slug);
    const updated = exists
      ? selectedGenres.filter((s) => s !== slug)
      : [...selectedGenres, slug];
    updateParam('genres', updated.join(','));
  };

  const toggleTag = (slug: string) => {
    const exists = selectedTags.includes(slug);
    const updated = exists
      ? selectedTags.filter((s) => s !== slug)
      : [...selectedTags, slug];
    updateParam('tags', updated.join(','));
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    updateParam('q', searchInput.trim());
  };

  const handlePageChange = (newPage: number) => {
    const newParams = new URLSearchParams(searchParams);
    newParams.set('page', newPage.toString());
    setSearchParams(newParams);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <div className="border-b border-[var(--border-color)] pb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
            {t('search.title')}
          </span>
          <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)]">
            {query ? t('search.resultsFor', { q: query }) : t('search.title')}
          </h1>
        </div>

        <button
          onClick={() => setShowFilters(!showFilters)}
          className="flex items-center gap-2 px-4 py-2 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] text-xs font-bold text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-colors self-start md:self-auto cursor-pointer"
        >
          <Filter className="w-4 h-4 text-[var(--accent-color)]" />
          {t('search.filterTitle')} {(selectedGenres.length > 0 || selectedTags.length > 0 || statusParam) && `(${selectedGenres.length + selectedTags.length + (statusParam ? 1 : 0)})`}
        </button>
      </div>

      {/* Filter Panel */}
      <div className={`flex flex-col gap-6 bg-[var(--bg-card)] p-6 rounded-2xl border border-[var(--border-color)] ${showFilters ? 'block' : 'hidden md:block'}`}>
        {/* Search input form */}
        <form onSubmit={handleSearchSubmit} className="flex gap-2">
          <div className="relative flex-1">
            <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder={t('search.placeholder')}
              className="w-full pl-9 pr-4 py-2.5 bg-[var(--bg-tertiary)] text-[var(--text-primary)] text-sm rounded-xl border border-[var(--border-color)] focus:outline-none focus:border-[var(--accent-color)] font-sans"
            />
          </div>
          <button
            type="submit"
            className="px-5 py-2.5 bg-[var(--accent-color)] text-white text-xs font-bold rounded-xl hover:opacity-90 transition-opacity cursor-pointer"
          >
            {t('common.search')}
          </button>
        </form>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {/* Status filter */}
          <div>
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)] mb-2 block">
              {t('common.status')}
            </label>
            <div className="flex flex-wrap gap-1.5">
              {[
                { label: t('browse.sortAll'), value: '' },
                { label: t('browse.statusOngoing'), value: 'ongoing' },
                { label: t('browse.statusCompleted'), value: 'completed' },
                { label: t('browse.statusHiatus'), value: 'hiatus' },
              ].map((st) => (
                <button
                  key={st.value}
                  type="button"
                  onClick={() => updateParam('status', st.value)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer ${
                    statusParam.toLowerCase() === st.value
                      ? 'bg-[var(--accent-color)] text-white'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  {st.label}
                </button>
              ))}
            </div>
          </div>

          {/* Sort filter */}
          <div>
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)] mb-2 block">
              {t('browse.sortBy')}
            </label>
            <div className="flex flex-wrap gap-1.5">
              {[
                { key: 'EN YENİLER', label: t('browse.sortNewest') },
                { key: 'EN ÇOK OKUNAN', label: t('browse.sortPopular') },
                { key: 'EN YÜKSEK PUAN', label: t('browse.sortRating') },
              ].map((s) => (
                <button
                  key={s.key}
                  type="button"
                  onClick={() => updateParam('sort', s.key)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer ${
                    sortParam === s.key
                      ? 'bg-[var(--accent-color)] text-white'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  {s.label}
                </button>
              ))}
            </div>
          </div>

          {/* Per Page filter */}
          <div>
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)] mb-2 block">
              {t('common.actions')}
            </label>
            <div className="flex flex-wrap gap-1.5">
              {[5, 10, 20, 50].map((num) => (
                <button
                  key={num}
                  type="button"
                  onClick={() => updateParam('per_page', num.toString())}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer ${
                    perPageParam === num
                      ? 'bg-[var(--accent-color)] text-white'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  {num}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Genres filter list */}
        {availableGenres.length > 0 && (
          <div>
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)] mb-2 block">
              {t('common.genres')}
            </label>
            <div className="flex flex-wrap gap-1.5">
              {availableGenres.map((g) => {
                const isSelected = selectedGenres.includes(g.slug);
                return (
                  <button
                    key={g.id}
                    type="button"
                    onClick={() => toggleGenre(g.slug)}
                    className={`px-2.5 py-1 rounded-md text-xs font-medium border transition-colors cursor-pointer ${
                      isSelected
                        ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)]'
                        : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:border-[var(--accent-color)]'
                    }`}
                  >
                    {g.name}
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {/* Tags filter list */}
        {availableTags.length > 0 && (
          <div>
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)] mb-2 block">
              {t('common.tags')}
            </label>
            <div className="flex flex-wrap gap-1.5">
              {availableTags.map((tag) => {
                const isSelected = selectedTags.includes(tag.slug);
                return (
                  <button
                    key={tag.id}
                    type="button"
                    onClick={() => toggleTag(tag.slug)}
                    className={`px-2.5 py-1 rounded-md text-xs font-medium border transition-colors cursor-pointer ${
                      isSelected
                        ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)]'
                        : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:border-[var(--accent-color)]'
                    }`}
                  >
                    #{tag.name}
                  </button>
                );
              })}
            </div>
          </div>
        )}
      </div>

      {/* Results grid */}
      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          {[...Array(perPageParam)].map((_, i) => (
            <div key={i} className="aspect-[3/4] bg-[var(--bg-tertiary)] rounded-xl animate-pulse" />
          ))}
        </div>
      ) : results.length === 0 ? (
        <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl">
          {t('search.noResults')}
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            {results.map((item) => (
              <ContentCard key={item.id} content={item} />
            ))}
          </div>

          {/* Pagination controls */}
          <Pagination
            currentPage={meta?.page || pageParam}
            totalPages={meta?.total_pages}
            total={meta?.total}
            perPage={meta?.per_page || perPageParam}
            onPageChange={handlePageChange}
          />
        </>
      )}
    </div>
  );
};

