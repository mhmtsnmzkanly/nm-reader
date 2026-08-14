import React, { useEffect, useState } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import { contentService } from '../services';
import { ContentSummary, PaginationMeta } from '../types/api';
import { ContentCard } from '../components/content/ContentCard';
import { Pagination } from '../components/feedback/Pagination';

export const GenreResultsPage: React.FC = () => {
  const { slug = '' } = useParams<{ slug: string }>();
  const [searchParams, setSearchParams] = useSearchParams();

  const page = parseInt(searchParams.get('page') || '1', 10);
  const perPage = parseInt(searchParams.get('per_page') || '10', 10);

  const [contents, setContents] = useState<ContentSummary[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchGenreContent = async () => {
      setIsLoading(true);
      const res = await contentService.getGenreContents(slug, page, perPage);

      if (res.status === 'success') {
        setContents(res.data);
        setMeta(res.meta as PaginationMeta);
      }
      setIsLoading(false);
    };

    fetchGenreContent();
  }, [slug, page, perPage]);

  const handlePageChange = (newPage: number) => {
    const params = new URLSearchParams(searchParams);
    params.set('page', newPage.toString());
    setSearchParams(params);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <div className="border-b border-[var(--border-color)] pb-6">
        <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
          Tür Filtresi
        </span>
        <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)] capitalize">
          #{slug} <span className="italic text-[var(--accent-color)]">Koleksiyonu</span>
        </h1>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          {[...Array(perPage)].map((_, i) => (
            <div key={i} className="aspect-[3/4] bg-[var(--bg-tertiary)] rounded-xl animate-pulse" />
          ))}
        </div>
      ) : contents.length === 0 ? (
        <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl">
          Bu tür altında kayıtlı içerik bulunamadı.
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
