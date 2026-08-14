import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { contentService } from '../services';
import { Genre } from '../types/api';

export const GenreDirectoryPage: React.FC = () => {
  const [genres, setGenres] = useState<Genre[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchGenres = async () => {
      setIsLoading(true);
      const res = await contentService.getGenres();
      if (res.status === 'success') {
        setGenres(res.data);
      }
      setIsLoading(false);
    };

    fetchGenres();
  }, []);

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <div className="border-b border-[var(--border-color)] pb-6">
        <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
          Kategoriler
        </span>
        <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)]">
          Tür <span className="italic text-[var(--accent-color)]">Dizini</span>
        </h1>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          {[...Array(8)].map((_, i) => (
            <div key={i} className="h-24 bg-[var(--bg-tertiary)] rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          {genres.map((genre) => (
            <Link
              key={genre.id}
              to={`/genre/${genre.slug}`}
              className="p-5 bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl transition-all hover:shadow-xl group flex items-center justify-between shadow-sm"
            >
              <div className="flex flex-col gap-1">
                <span className="font-serif text-lg font-semibold text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors">
                  {genre.name}
                </span>
                <span className="text-xs font-mono text-[var(--text-muted)]">
                  {genre.content_count || 0} İçerik
                </span>
              </div>
              <ChevronRight className="w-4 h-4 text-[var(--text-muted)] group-hover:text-[var(--accent-color)] transition-colors" />
            </Link>
          ))}
        </div>
      )}
    </div>
  );
};
