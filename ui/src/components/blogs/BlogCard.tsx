import React from 'react';
import { Link } from 'react-router-dom';
import { Calendar, Eye, ThumbsUp, MessageSquare } from 'lucide-react';
import { BlogSummary } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

type BlogCardProps = {
  blog: BlogSummary;
};

export const BlogCard: React.FC<BlogCardProps> = ({ blog }) => {
  const { formatDate, t } = usePreferences();
  return (
    <div className="group bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] rounded-2xl overflow-hidden transition-all duration-300 flex flex-col justify-between h-full shadow-sm hover:shadow-lg">
      {blog.cover_image && (
        <Link to={`/blog/${blog.slug}`} className="relative aspect-[16/9] overflow-hidden bg-[var(--bg-tertiary)]">
          <img
            src={blog.cover_image}
            alt={blog.title}
            referrerPolicy="no-referrer"
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
            loading="lazy"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-[var(--bg-card)] via-transparent to-transparent opacity-60" />
        </Link>
      )}

      <div className="p-5 flex flex-col flex-grow justify-between gap-4">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-[10px] font-mono text-[var(--text-muted)] uppercase tracking-wider">
            <span className="text-[var(--accent-color)] font-bold">Blog</span>
            <span>•</span>
            <div className="flex items-center gap-1">
              <Calendar className="w-3 h-3" />
              <span>{formatDate(blog.created_at)}</span>
            </div>
          </div>

          <Link to={`/blog/${blog.slug}`}>
            <h3 className="font-serif text-lg font-semibold text-[var(--text-primary)] group-hover:text-[var(--accent-color)] transition-colors leading-snug line-clamp-2">
              {blog.title}
            </h3>
          </Link>

          {blog.excerpt && (
            <p className="text-xs text-[var(--text-secondary)] font-light line-clamp-3 leading-relaxed">
              {blog.excerpt}
            </p>
          )}
        </div>

        <div className="flex items-center justify-between pt-3 border-t border-[var(--border-color)] text-[11px] text-[var(--text-muted)] font-mono">
          <span className="font-medium text-[var(--text-primary)]">{blog.author?.username || blog.author_username || t('common.anonymous')}</span>

          <div className="flex items-center gap-3">
            <span className="flex items-center gap-1">
              <Eye className="w-3 h-3" />
              {blog.views}
            </span>
            <span className="flex items-center gap-1">
              <ThumbsUp className="w-3 h-3 text-[var(--accent-color)]" />
              {blog.likes}
            </span>
            <span className="flex items-center gap-1">
              <MessageSquare className="w-3 h-3" />
              {blog.comments_count}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
};
