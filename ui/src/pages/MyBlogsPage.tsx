import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { blogService } from '../services';
import { BlogSummary } from '../types/api';
import { BlogCard } from '../components/blogs/BlogCard';
import { Button } from '../components/ui/Button';

export const MyBlogsPage: React.FC = () => {
  const [blogs, setBlogs] = useState<BlogSummary[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchMyBlogs = async () => {
      setIsLoading(true);
      const res = await blogService.getUserBlogs();
      if (res.status === 'success') {
        setBlogs(res.data);
      }
      setIsLoading(false);
    };

    fetchMyBlogs();
  }, []);

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-6">
        <div>
          <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
            Yazar Paneli
          </span>
          <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)]">
            Bloglarım <span className="italic text-[var(--accent-color)]">({blogs.length})</span>
          </h1>
        </div>

        <Link to="/blogs/new">
          <Button variant="gold" size="md" className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90">
            <Plus className="w-4 h-4 text-white" />
            <span>Yeni Yazı Kaleme Al</span>
          </Button>
        </Link>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {[...Array(3)].map((_, i) => (
            <div key={i} className="h-64 bg-[var(--bg-tertiary)] rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : blogs.length === 0 ? (
        <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl">
          Henüz bir blog yazınız yok. İnceleme veya önerilerinizi paylaşmak için yeni bir yazı oluşturun!
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {blogs.map((b) => (
            <BlogCard key={b.id} blog={b} />
          ))}
        </div>
      )}
    </div>
  );
};
