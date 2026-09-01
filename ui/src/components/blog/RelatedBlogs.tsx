import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  BookOpen,
  Clock,
  ThumbsUp,
  Eye,
  ArrowRight,
  Sparkles,
} from 'lucide-react';
import { blogService } from '../../services';
import { BlogSummary } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

interface RelatedBlogsProps {
  currentBlogId?: string;
  slug?: string;
  tags?: string[];
  blogs?: BlogSummary[];
}

export const RelatedBlogs: React.FC<RelatedBlogsProps> = ({ currentBlogId, slug, tags = [], blogs }) => {
  const { t, formatRelativeTime } = usePreferences();
  const [relatedList, setRelatedList] = useState<BlogSummary[]>(blogs || []);
  const [isLoading, setIsLoading] = useState<boolean>(!blogs);

  const loadRelated = useCallback(async () => {
    if (blogs) {
      setRelatedList(blogs);
      setIsLoading(false);
      return;
    }
    setIsLoading(true);
    if (slug) {
      const res = await blogService.getRelatedBlogs(slug, 3);
      if (res.status === 'success' && res.data) {
        setRelatedList(res.data);
      }
    } else {
      const res = await blogService.getBlogs(1, 10);
      if (res.status === 'success' && res.data) {
        const otherBlogs = res.data.filter((b) => b.id !== currentBlogId);
        setRelatedList(otherBlogs.slice(0, 3));
      }
    }
    setIsLoading(false);
  }, [currentBlogId, slug, blogs]);

  useEffect(() => {
    if (blogs) {
      setRelatedList(blogs);
      setIsLoading(false);
    } else {
      loadRelated();
    }
  }, [blogs, loadRelated]);

  if (!isLoading && relatedList.length === 0) {
    return null;
  }

  return (
    <div className="flex flex-col gap-5 pt-8 border-t border-[var(--border-color)]">
      {/* Section Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="p-2 rounded-xl bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)]">
            <Sparkles className="w-4 h-4" />
          </div>
          <div>
            <h3 className="font-serif font-bold text-lg text-[var(--text-primary)]">
              {t('relatedBlogs.title')}
            </h3>
            <p className="text-xs text-[var(--text-secondary)]">
              {t('relatedBlogs.subtitle')}
            </p>
          </div>
        </div>

        <Link
          to="/blogs"
          className="text-xs font-mono text-[var(--text-secondary)] hover:text-[var(--accent-color)] flex items-center gap-1 transition-colors"
        >
          <span>{t('common.viewAll')}</span>
          <ArrowRight className="w-3.5 h-3.5" />
        </Link>
      </div>

      {/* Grid */}
      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {[1, 2, 3].map((i) => (
            <div
              key={i}
              className="h-44 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl animate-pulse"
            />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {relatedList.map((blog) => (
            <Link
              key={blog.id}
              to={`/blog/${blog.slug || blog.id}`}
              className="bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)]/60 rounded-2xl overflow-hidden shadow-xs hover:shadow-md transition-all flex flex-col justify-between group"
            >
              {/* Cover Image */}
              <div className="relative aspect-[16/9] w-full bg-[var(--bg-tertiary)] overflow-hidden">
                {blog.cover_image ? (
                  <img
                    src={blog.cover_image}
                    alt={blog.title}
                    referrerPolicy="no-referrer"
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                ) : (
                  <div className="w-full h-full bg-[var(--accent-light)] text-[var(--accent-color)] flex items-center justify-center font-serif font-bold text-lg">
                    {blog.title.substring(0, 2).toUpperCase()}
                  </div>
                )}

                {blog.read_time && (
                  <div className="absolute bottom-2 right-2 px-2 py-0.5 rounded-lg bg-black/60 text-white text-[10px] font-mono backdrop-blur-xs flex items-center gap-1">
                    <Clock className="w-3 h-3" />
                    <span>{t('blog.readingTime', { min: blog.read_time })}</span>
                  </div>
                )}
              </div>

              {/* Info Body */}
              <div className="p-4 flex flex-col gap-2.5 flex-1 justify-between">
                <div className="flex flex-col gap-1.5">
                  <h4 className="font-serif font-bold text-sm text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors line-clamp-2 leading-snug">
                    {blog.title}
                  </h4>

                  {blog.excerpt && (
                    <p className="text-xs text-[var(--text-secondary)] line-clamp-2 leading-relaxed font-light">
                      {blog.excerpt}
                    </p>
                  )}
                </div>

                {/* Author & Stats Meta */}
                <div className="flex items-center justify-between pt-3 border-t border-[var(--border-color)] text-[11px] font-mono text-[var(--text-muted)]">
                  <span className="truncate max-w-[120px]">
                    {blog.author?.display_name || blog.author?.username || blog.author_username || 'Yazar'}
                  </span>

                  <div className="flex items-center gap-3 shrink-0">
                    <span className="flex items-center gap-1">
                      <ThumbsUp className="w-3 h-3" />
                      {blog.upvote_count ?? blog.likes ?? blog.stats?.likes ?? 0}
                    </span>
                    <span className="flex items-center gap-1">
                      <Eye className="w-3 h-3" />
                      {blog.views ?? blog.stats?.views ?? 0}
                    </span>
                  </div>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
};
