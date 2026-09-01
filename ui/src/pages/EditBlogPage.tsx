import React, { useEffect, useState } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import Markdown from 'react-markdown';
import { ArrowLeft, Eye, Edit3, Sparkles, FileText, Send } from 'lucide-react';
import { blogService } from '../services';
import { Button } from '../components/ui/Button';
import { BlogImageUpload } from '../components/blog/BlogImageUpload';
import { usePreferences } from '../contexts/PreferencesContext';

export const EditBlogPage: React.FC = () => {
  const { t } = usePreferences();
  const { id = '' } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const [title, setTitle] = useState('');
  const [excerpt, setExcerpt] = useState('');
  const [coverImage, setCoverImage] = useState('');
  const [tagsInput, setTagsInput] = useState('');
  const [content, setContent] = useState('');
  const [activeTab, setActiveTab] = useState<'write' | 'preview'>('write');
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitType, setSubmitType] = useState<'draft' | 'pending' | null>(null);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    const fetchBlog = async () => {
      setIsLoading(true);
      const res = await blogService.getMyBlog(id);
      if (res.status === 'success' && res.data) {
        const blog = res.data;
        setTitle(blog.title || '');
        setExcerpt(blog.excerpt || '');
        setCoverImage(blog.cover_image || '');
        setContent(blog.body || blog.content || '');
        const tagNames = blog.tags?.map((tg) => (typeof tg === 'string' ? tg : tg.name || tg.slug || tg.id)) || [];
        setTagsInput(tagNames.join(', '));
      } else {
        setNotFound(true);
      }
      setIsLoading(false);
    };

    fetchBlog();
  }, [id]);

  const handleUpdate = async (targetStatus: 'draft' | 'pending') => {
    if (!title.trim() || !content.trim() || isSubmitting) return;

    setIsSubmitting(true);
    setSubmitType(targetStatus);
    const tags = tagsInput
      .split(',')
      .map((t) => t.trim().replace(/^#/, ''))
      .filter((t) => t.length > 0);

    const res = await blogService.updateBlog(id, {
      title: title.trim(),
      body: content.trim(),
      tags: tags.length > 0 ? tags : undefined,
      excerpt: excerpt.trim() || undefined,
      cover_image: coverImage.trim() || undefined,
      status: targetStatus,
    });

    if (res.status === 'success') {
      navigate('/my-blogs');
    }
    setIsSubmitting(false);
    setSubmitType(null);
  };

  if (isLoading) {
    return (
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-12 flex flex-col gap-6 animate-pulse">
        <div className="h-6 w-32 bg-[var(--bg-tertiary)] rounded-lg" />
        <div className="h-10 w-3/4 bg-[var(--bg-tertiary)] rounded-xl" />
        <div className="h-4 w-1/4 bg-[var(--bg-tertiary)] rounded-lg" />
        <div className="h-48 w-full bg-[var(--bg-tertiary)] rounded-2xl mt-4" />
      </div>
    );
  }

  if (notFound) {
    return (
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-16 text-center">
        <p className="text-sm font-mono text-[var(--text-muted)]">{t('blog.notFound')}</p>
        <Link
          to="/my-blogs"
          className="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-[var(--bg-tertiary)] text-xs font-mono text-[var(--text-primary)] hover:text-[var(--accent-color)] border border-[var(--border-color)] transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>{t('navigation.myBlogs')}</span>
        </Link>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      {/* Header */}
      <div className="flex flex-col gap-2 border-b border-[var(--border-color)] pb-6">
        <Link
          to="/my-blogs"
          className="inline-flex items-center gap-1.5 text-xs font-mono text-[var(--text-muted)] hover:text-[var(--accent-color)] transition-colors mb-1"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          <span>{t('navigation.myBlogs')}</span>
        </Link>
        <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
          {t('blog.communityBadge')}
        </span>
        <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)]">
          {t('blog.editBlogHeader')}
        </h1>
        <p className="text-xs sm:text-sm text-[var(--text-secondary)] font-light">
          {t('blog.editBlogDesc')}
        </p>
      </div>

      <div className="flex flex-col gap-6 bg-[var(--bg-card)] p-6 sm:p-8 rounded-2xl border border-[var(--border-color)] shadow-sm">
        {/* Cover Image Upload */}
        <BlogImageUpload
          value={coverImage}
          onChange={setCoverImage}
        />

        {/* Title */}
        <div className="flex flex-col gap-2">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] font-mono">
            {t('blog.titleLabel')}
          </label>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder={t('blog.titlePlaceholder')}
            required
            className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3.5 text-sm sm:text-base font-serif focus:outline-hidden focus:ring-2 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
          />
        </div>

        {/* Excerpt */}
        <div className="flex flex-col gap-2">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] font-mono">
            {t('blog.excerptLabel')}
          </label>
          <input
            type="text"
            value={excerpt}
            onChange={(e) => setExcerpt(e.target.value)}
            placeholder={t('blog.excerptPlaceholder')}
            className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-sm focus:outline-hidden focus:ring-2 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
          />
        </div>

        {/* Tags */}
        <div className="flex flex-col gap-2">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] font-mono">
            {t('blog.tagsLabel')}
          </label>
          <input
            type="text"
            value={tagsInput}
            onChange={(e) => setTagsInput(e.target.value)}
            placeholder={t('blog.tagsPlaceholder')}
            className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-sm focus:outline-hidden focus:ring-2 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
          />
        </div>

        {/* Content & Markdown Tabs */}
        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] font-mono">
              {t('blog.contentLabel')}
            </label>

            <div className="flex items-center gap-1 p-1 bg-[var(--bg-tertiary)] rounded-lg border border-[var(--border-color)]">
              <button
                type="button"
                onClick={() => setActiveTab('write')}
                className={`flex items-center gap-1 px-3 py-1 rounded-md text-xs font-mono transition-all cursor-pointer ${
                  activeTab === 'write'
                    ? 'bg-[var(--bg-card)] text-[var(--accent-color)] font-bold shadow-xs'
                    : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'
                }`}
              >
                <Edit3 className="w-3 h-3" />
                <span>{t('blog.tabWrite')}</span>
              </button>
              <button
                type="button"
                onClick={() => setActiveTab('preview')}
                className={`flex items-center gap-1 px-3 py-1 rounded-md text-xs font-mono transition-all cursor-pointer ${
                  activeTab === 'preview'
                    ? 'bg-[var(--bg-card)] text-[var(--accent-color)] font-bold shadow-xs'
                    : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'
                }`}
              >
                <Eye className="w-3 h-3" />
                <span>{t('blog.tabPreview')}</span>
              </button>
            </div>
          </div>

          {activeTab === 'write' ? (
            <textarea
              value={content}
              onChange={(e) => setContent(e.target.value)}
              placeholder={t('blog.contentPlaceholder')}
              rows={14}
              required
              className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-4 text-sm font-mono focus:outline-hidden focus:ring-2 focus:ring-[var(--accent-color)] border border-[var(--border-color)] resize-y leading-relaxed transition-all"
            />
          ) : (
            <div className="w-full min-h-[300px] bg-[var(--bg-tertiary)]/50 rounded-xl p-5 border border-[var(--border-color)] text-[var(--text-primary)] overflow-y-auto">
              {content.trim() ? (
                <div className="prose prose-neutral max-w-none text-sm leading-relaxed">
                  <Markdown>{content}</Markdown>
                </div>
              ) : (
                <p className="text-xs font-mono text-[var(--text-muted)] italic">
                  {t('blog.previewEmpty')}
                </p>
              )}
            </div>
          )}
        </div>

        {/* Action Buttons */}
        <div className="flex flex-col sm:flex-row items-center gap-3 pt-2">
          <Button
            type="button"
            variant="outline"
            size="lg"
            isLoading={isSubmitting && submitType === 'draft'}
            disabled={isSubmitting || !title.trim() || !content.trim()}
            onClick={() => handleUpdate('draft')}
            className="w-full sm:w-1/2 gap-2 border-[var(--border-color)] hover:border-[var(--accent-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]"
          >
            <FileText className="w-4 h-4" />
            <span>{t('blog.updateDraft')}</span>
          </Button>

          <Button
            type="button"
            variant="gold"
            size="lg"
            isLoading={isSubmitting && submitType === 'pending'}
            disabled={isSubmitting || !title.trim() || !content.trim()}
            onClick={() => handleUpdate('pending')}
            className="w-full sm:w-1/2 gap-2 bg-[var(--accent-color)] text-white hover:opacity-90 font-serif"
          >
            <Send className="w-4 h-4 text-white" />
            <span>{t('blog.submitReview')}</span>
          </Button>
        </div>
      </div>
    </div>
  );
};
