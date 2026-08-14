import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { blogService } from '../services';
import { BlogSummary, PaginationMeta } from '../types/api';
import { BlogCard } from '../components/blogs/BlogCard';
import { Button } from '../components/ui/Button';
import { Pagination } from '../components/feedback/Pagination';

export const BlogListPage: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams();

  const page = parseInt(searchParams.get('page') || '1', 10);
  const perPage = parseInt(searchParams.get('per_page') || '6', 10);

  const [blogs, setBlogs] = useState<BlogSummary[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchBlogs = async () => {
      setIsLoading(true);
      const res = await blogService.getBlogs(page, perPage);
      if (res.status === 'success') {
        setBlogs(res.data);
        setMeta(res.meta as PaginationMeta);
      }
      setIsLoading(false);
    };

    fetchBlogs();
  }, [page, perPage]);

  const handlePageChange = (newPage: number) => {
    const params = new URLSearchParams(searchParams);
    params.set('page', newPage.toString());
    setSearchParams(params);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-6">
        <div>
          <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
            Topluluk & İnceleme
          </span>
          <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)]">
            Blog <span className="italic text-[var(--accent-color)]">Yazıları</span>
          </h1>
        </div>

        <Link to="/blogs/new">
          <Button variant="gold" size="md" className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90">
            <Plus className="w-4 h-4 text-white" />
            <span>Yeni Blog Yazısı</span>
          </Button>
        </Link>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {[...Array(perPage)].map((_, i) => (
            <div key={i} className="h-64 bg-[var(--bg-tertiary)] rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : blogs.length === 0 ? (
        <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl">
          Henüz hiç blog yazısı bulunmuyor.
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {blogs.map((b) => (
              <BlogCard key={b.id} blog={b} />
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
