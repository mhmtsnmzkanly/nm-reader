import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Calendar, Eye, ThumbsUp, MessageSquare, Edit2, Trash2, Clock, AlertCircle, CheckCircle2, FileEdit, XCircle } from 'lucide-react';
import { blogService } from '../services';
import { BlogSummary } from '../types/api';
import { Button } from '../components/ui/Button';
import { usePreferences } from '../contexts/PreferencesContext';

export const MyBlogsPage: React.FC = () => {
  const { t, formatDate } = usePreferences();
  const [blogs, setBlogs] = useState<BlogSummary[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [deletingId, setDeletingId] = useState<string | null>(null);

  const fetchMyBlogs = useCallback(async () => {
    setIsLoading(true);
    const res = await blogService.getUserBlogs();
    if (res.status === 'success' && res.data) {
      setBlogs(res.data);
    }
    setIsLoading(false);
  }, []);

  useEffect(() => {
    fetchMyBlogs();
  }, [fetchMyBlogs]);

  const handleDelete = async (id: string) => {
    if (!window.confirm(t('blog.deleteBlogConfirm'))) return;
    setDeletingId(id);
    const res = await blogService.deleteBlog(id);
    if (res.status === 'success') {
      await fetchMyBlogs();
    }
    setDeletingId(null);
  };

  const getStatusBadge = (blog: BlogSummary) => {
    const st = blog.status || (blog.approved === 1 ? 'published' : 'pending');

    switch (st) {
      case 'draft':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-mono font-medium bg-neutral-500/10 text-neutral-400 border border-neutral-500/20">
            <FileEdit className="w-3 h-3" />
            <span>{t('blog.statusDraft')}</span>
          </span>
        );
      case 'pending':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-mono font-medium bg-amber-500/10 text-amber-500 border border-amber-500/20">
            <Clock className="w-3 h-3" />
            <span>{t('blog.statusPending')}</span>
          </span>
        );
      case 'published':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-mono font-medium bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
            <CheckCircle2 className="w-3 h-3" />
            <span>{t('blog.statusPublished')}</span>
          </span>
        );
      case 'rejected':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-mono font-medium bg-rose-500/10 text-rose-500 border border-rose-500/20">
            <XCircle className="w-3 h-3" />
            <span>{t('blog.statusRejected')}</span>
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-mono font-medium bg-neutral-500/10 text-neutral-400 border border-neutral-500/20">
            <span>{st}</span>
          </span>
        );
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-6 flex-wrap gap-4">
        <div>
          <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
            {t('navigation.myBlogs')}
          </span>
          <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)]">
            {t('blog.myBlogsHeader', { count: blogs.length })}
          </h1>
        </div>

        <Link to="/blogs/new">
          <Button variant="gold" size="md" className="gap-2 bg-[var(--accent-color)] text-white hover:opacity-90">
            <Plus className="w-4 h-4 text-white" />
            <span>{t('blog.writeNewPost')}</span>
          </Button>
        </Link>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {[...Array(3)].map((_, i) => (
            <div key={i} className="h-64 bg-[var(--bg-tertiary)] rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : blogs.length === 0 ? (
        <div className="p-12 text-center text-[var(--text-muted)] font-mono text-xs border border-dashed border-[var(--border-color)] rounded-2xl">
          {t('blog.noBlogsYet')}
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {blogs.map((b) => {
            const isLive = b.status === 'published' || b.approved === 1;
            return (
              <div
                key={b.id}
                className="group bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)]/60 rounded-2xl overflow-hidden transition-all duration-300 flex flex-col justify-between h-full shadow-xs hover:shadow-md"
              >
                <div>
                  {b.cover_image ? (
                    <div className="relative aspect-[16/9] overflow-hidden bg-[var(--bg-tertiary)]">
                      <img
                        src={b.cover_image}
                        alt={b.title}
                        referrerPolicy="no-referrer"
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                        loading="lazy"
                      />
                      <div className="absolute top-3 right-3">{getStatusBadge(b)}</div>
                    </div>
                  ) : (
                    <div className="p-4 pb-0 flex justify-end">
                      {getStatusBadge(b)}
                    </div>
                  )}

                  <div className="p-5 flex flex-col gap-3">
                    <div className="flex items-center gap-2 text-[10px] font-mono text-[var(--text-muted)] uppercase tracking-wider">
                      <Calendar className="w-3 h-3" />
                      <span>{formatDate(b.created_at || b.published_at || '')}</span>
                      {b.read_time && (
                        <>
                          <span>•</span>
                          <span>{t('blog.readingTime', { min: b.read_time })}</span>
                        </>
                      )}
                    </div>

                    {isLive ? (
                      <Link to={`/blog/${b.slug}`}>
                        <h3 className="font-serif text-lg font-semibold text-[var(--text-primary)] hover:text-[var(--accent-color)] transition-colors leading-snug line-clamp-2">
                          {b.title}
                        </h3>
                      </Link>
                    ) : (
                      <h3 className="font-serif text-lg font-semibold text-[var(--text-primary)] leading-snug line-clamp-2">
                        {b.title}
                      </h3>
                    )}

                    {b.excerpt && (
                      <p className="text-xs text-[var(--text-secondary)] font-light line-clamp-2 leading-relaxed">
                        {b.excerpt}
                      </p>
                    )}
                  </div>
                </div>

                <div className="p-5 pt-0 flex flex-col gap-3">
                  <div className="flex items-center justify-between pt-3 border-t border-[var(--border-color)] text-[11px] text-[var(--text-muted)] font-mono">
                    <div className="flex items-center gap-3">
                      <span className="flex items-center gap-1" title="Görüntülenme">
                        <Eye className="w-3.5 h-3.5" />
                        {b.views ?? b.stats?.views ?? 0}
                      </span>
                      <span className="flex items-center gap-1" title="Beğeni">
                        <ThumbsUp className="w-3.5 h-3.5 text-[var(--accent-color)]" />
                        {b.likes ?? b.upvote_count ?? b.stats?.likes ?? 0}
                      </span>
                      <span className="flex items-center gap-1" title="Yorum">
                        <MessageSquare className="w-3.5 h-3.5" />
                        {b.comments_count ?? b.stats?.comments ?? 0}
                      </span>
                    </div>
                  </div>

                  {/* Management Actions */}
                  <div className="flex items-center gap-2 pt-2 border-t border-[var(--border-color)]">
                    <Link to={`/blogs/${b.id}/edit`} className="flex-1">
                      <Button
                        variant="outline"
                        size="sm"
                        fullWidth
                        className="gap-1.5 text-xs font-mono border-[var(--border-color)] hover:border-[var(--accent-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                        <span>{t('blog.actionEdit')}</span>
                      </Button>
                    </Link>

                    <Button
                      variant="outline"
                      size="sm"
                      isLoading={deletingId === b.id}
                      disabled={deletingId === b.id}
                      onClick={() => handleDelete(b.id)}
                      className="px-3 text-xs font-mono text-rose-500 hover:text-rose-600 hover:bg-rose-500/10 border-[var(--border-color)] hover:border-rose-500/30"
                      title={t('blog.actionDelete')}
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

