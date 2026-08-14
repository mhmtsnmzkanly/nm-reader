import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { blogService } from '../services';
import { Button } from '../components/ui/Button';

export const NewBlogPage: React.FC = () => {
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || !content.trim() || isSubmitting) return;

    setIsSubmitting(true);
    const res = await blogService.createBlog(title.trim(), content.trim());

    if (res.status === 'success') {
      navigate(`/blog/${res.data.slug}`);
    }
    setIsSubmitting(false);
  };

  return (
    <div className="max-w-3xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8 transition-colors duration-300">
      <div className="border-b border-[var(--border-color)] pb-6">
        <span className="text-[10px] uppercase tracking-[0.3em] text-[var(--accent-color)] font-bold">
          İçerik Üretimi
        </span>
        <h1 className="font-serif text-3xl font-bold text-[var(--text-primary)]">
          Yeni Blog <span className="italic text-[var(--accent-color)]">Yazısı</span>
        </h1>
      </div>

      <form onSubmit={handleSubmit} className="flex flex-col gap-6 bg-[var(--bg-card)] p-6 sm:p-8 rounded-2xl border border-[var(--border-color)] shadow-sm">
        <div className="flex flex-col gap-2">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
            Başlık
          </label>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="Etkileyici bir başlık girin..."
            required
            className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-sm focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
          />
        </div>

        <div className="flex flex-col gap-2">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
            İçerik Metni
          </label>
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            placeholder="Yazınızı buraya detaylı olarak kaleme alın..."
            rows={10}
            required
            className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-sm focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] resize-y font-light leading-relaxed transition-all"
          />
        </div>

        <Button
          type="submit"
          variant="gold"
          size="lg"
          isLoading={isSubmitting}
          disabled={!title.trim() || !content.trim()}
          fullWidth
          className="bg-[var(--accent-color)] text-white hover:opacity-90"
        >
          Yayınla
        </Button>
      </form>
    </div>
  );
};
