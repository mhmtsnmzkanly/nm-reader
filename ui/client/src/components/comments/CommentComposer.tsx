import React, { useState } from 'react';
import { Send, EyeOff } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';
import { Button } from '../ui/Button';

type CommentComposerProps = {
  onSubmit: (content: string, isSpoiler: boolean) => Promise<void>;
  placeholder?: string;
};

export const CommentComposer: React.FC<CommentComposerProps> = ({
  onSubmit,
  placeholder,
}) => {
  const { isAuthenticated } = useAuth();
  const { t } = usePreferences();
  const [content, setContent] = useState('');
  const [isSpoiler, setIsSpoiler] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const defaultPlaceholder = placeholder || t('comments.writePlaceholder');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!content.trim() || isSubmitting) return;

    setIsSubmitting(true);
    await onSubmit(content.trim(), isSpoiler);
    setContent('');
    setIsSpoiler(false);
    setIsSubmitting(false);
  };

  if (!isAuthenticated) {
    return (
      <div className="p-4 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-center text-xs text-[var(--text-muted)]">
        {t('comments.loginRequired')}
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-3 p-4 bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)] shadow-sm">
      <textarea
        value={content}
        onChange={(e) => setContent(e.target.value)}
        placeholder={defaultPlaceholder}
        rows={3}
        className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] resize-none font-light transition-all"
      />

      <div className="flex items-center justify-between">
        <label className="flex items-center gap-2 cursor-pointer text-xs text-[var(--text-secondary)] hover:text-[var(--text-primary)] select-none">
          <input
            type="checkbox"
            checked={isSpoiler}
            onChange={(e) => setIsSpoiler(e.target.checked)}
            className="rounded bg-[var(--bg-tertiary)] border-[var(--border-color)] text-[var(--accent-color)] focus:ring-[var(--accent-color)] cursor-pointer"
          />
          <EyeOff className="w-3.5 h-3.5 text-amber-500" />
          <span className="text-[11px] font-mono">{t('comments.containsSpoiler')}</span>
        </label>

        <Button
          type="submit"
          variant="gold"
          size="sm"
          isLoading={isSubmitting}
          disabled={!content.trim()}
          className="gap-1.5 bg-[var(--accent-color)] text-white hover:opacity-90"
        >
          <Send className="w-3.5 h-3.5" />
          <span>{t('comments.send')}</span>
        </Button>
      </div>
    </form>
  );
};
