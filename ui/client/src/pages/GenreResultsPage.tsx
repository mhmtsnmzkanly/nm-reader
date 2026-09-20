import React, { useEffect, useState } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import { contentService } from '../services';
import { ContentSummary, PaginationMeta } from '../types/api';
import { ContentCard } from '../components/content/ContentCard';
import { Pagination } from '../components/feedback/Pagination';
import { usePreferences } from '../contexts/PreferencesContext';
import { TaxonomyIcon, taxonomyColor } from '../components/taxonomy/TaxonomyIcon';

function getBootstrapGenreResults(slug: string, page: number): ContentSummary[] | null {
  if (page !== 1 || typeof window === 'undefined') return null;
  const current = window.__NMR_CONTEXT?.current_page;
  const items = current?.data?.items;
  return current?.route === 'genre' && current.data?.slug === slug && Array.isArray(items)
    ? items as ContentSummary[]
    : null;
}

export const GenreResultsPage: React.FC = () => {
  const { t } = usePreferences();
  const { slug = '' } = useParams<{ slug: string }>();
  const [searchParams, setSearchParams] = useSearchParams();

  const page = parseInt(searchParams.get('page') || '1', 10);
  const perPage = parseInt(searchParams.get('per_page') || '10', 10);

  const [bootstrapContents] = useState(() => getBootstrapGenreResults(slug, page));
  const taxonomy = window.__NMR_CONTEXT?.current_page?.data?.taxonomy;
  const taxonomyAccent = taxonomyColor(taxonomy?.ui_config?.color);
  const [contents, setContents] = useState<ContentSummary[]>(bootstrapContents || []);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [isLoading, setIsLoading] = useState(bootstrapContents === null);

  useEffect(() => {
    const fetchGenreContent = async () => {
      if (bootstrapContents !== null && page === 1) {
        setIsLoading(false);
        return;
      }
      setIsLoading(true);
      const res = await contentService.getGenreContents(slug, page, perPage);

      if (res.status === 'success') {
        setContents(res.data);
        setMeta(res.meta as PaginationMeta);
      }
      setIsLoading(false);
    };

    fetchGenreContent();
  }, [bootstrapContents, slug, page, perPage]);

  const handlePageChange = (newPage: number) => {
    const params = new URLSearchParams(searchParams);
    params.set('page', newPage.toString());
    setSearchParams(params);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <div
        className="border-b border-[var(--border-color)] pb-6"
        style={{ '--taxonomy-color': taxonomyAccent } as React.CSSProperties}
      >
        <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
          {t('genre.filterBadge')}
        </span>
        <div className="mt-2 flex items-center gap-3">
          <span className="flex h-12 w-12 items-center justify-center rounded-2xl" style={{ color: taxonomyAccent, backgroundColor: `${taxonomyAccent}18` }}>
            <TaxonomyIcon name={taxonomy?.ui_config?.icon} className="h-6 w-6" />
          </span>
          <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)] capitalize">
            {t('genre.resultsHeader', { slug: taxonomy?.name || slug })}
          </h1>
        </div>
        {taxonomy?.ui_config?.description && (
          <p className="mt-3 max-w-3xl text-sm leading-6 text-[var(--text-muted)]">
            {taxonomy.ui_config.description}
          </p>
        )}
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          {[...Array(perPage)].map((_, i) => (
            <div key={i} className="aspect-[3/4] bg-[var(--bg-tertiary)] rounded-xl animate-pulse" />
          ))}
        </div>
      ) : contents.length === 0 ? (
        <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl">
          {t('genre.noContentFound')}
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
