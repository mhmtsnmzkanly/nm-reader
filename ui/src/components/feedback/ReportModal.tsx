import React, { useState, useEffect } from 'react';
import { X, Flag, AlertTriangle, CheckCircle2, ShieldAlert } from 'lucide-react';
import { ReportTargetType } from '../../services/contracts';
import { reportService } from '../../services';
import { usePreferences } from '../../contexts/PreferencesContext';
import { Button } from '../ui/Button';

export interface ReportModalProps {
  isOpen: boolean;
  onClose: () => void;
  targetType: ReportTargetType;
  targetId: string | number;
  targetTitle?: string;
}

const CHAPTER_SERIES_REASONS = [
  'broken_image',
  'missing_content',
  'wrong_chapter',
  'wrong_order',
  'copyright',
  'other',
] as const;

const BLOG_COMMENT_REASONS = [
  'spam',
  'harassment',
  'insult',
  'hate_speech',
  'sexual_content',
  'misinformation',
  'other',
] as const;

export const ReportModal: React.FC<ReportModalProps> = ({
  isOpen,
  onClose,
  targetType,
  targetId,
  targetTitle,
}) => {
  const { t } = usePreferences();
  const [selectedReason, setSelectedReason] = useState<string>('');
  const [description, setDescription] = useState<string>('');
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [isSuccess, setIsSuccess] = useState<boolean>(false);

  const reasons =
    targetType === 'chapter' || targetType === 'series'
      ? CHAPTER_SERIES_REASONS
      : BLOG_COMMENT_REASONS;

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      setSelectedReason('');
      setDescription('');
      setErrorMsg(null);
      setIsSuccess(false);
      setIsSubmitting(false);
    }
  }, [isOpen]);

  // Handle ESC key press
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen && !isSubmitting) {
        onClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose, isSubmitting]);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedReason || isSubmitting) return;

    setIsSubmitting(true);
    setErrorMsg(null);

    try {
      const res = await reportService.createReport({
        target_type: targetType,
        target_id: String(targetId),
        reason: selectedReason,
        description: description.trim() || undefined,
      });

      if (res.status === 'success') {
        setIsSuccess(true);
        setTimeout(() => {
          onClose();
        }, 1500);
      } else {
        setErrorMsg(res.error?.message || t('report.errorOccurred'));
      }
    } catch {
      setErrorMsg(t('report.errorOccurred'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const targetPrefix = t(`report.targetPrefix.${targetType}`);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/70 backdrop-blur-xs transition-opacity animate-in fade-in"
        onClick={() => !isSubmitting && onClose()}
      />

      {/* Modal Card */}
      <div className="relative w-full max-w-lg bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl sm:rounded-3xl shadow-2xl overflow-hidden z-10 animate-in zoom-in-95 duration-200">
        {/* Top Header */}
        <div className="flex items-center justify-between p-5 sm:p-6 border-b border-[var(--border-color)] bg-[var(--bg-card)]">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-500">
              <Flag className="w-5 h-5" />
            </div>
            <div>
              <span className="text-[10px] font-mono uppercase tracking-[0.2em] text-[var(--accent-color)] font-bold">
                {targetPrefix}
              </span>
              <h2 className="font-serif text-lg sm:text-xl font-bold text-[var(--text-primary)] leading-snug">
                {t('report.modalTitle')}
              </h2>
            </div>
          </div>
          <button
            onClick={onClose}
            disabled={isSubmitting}
            className="p-2 rounded-xl text-[var(--text-muted)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] transition-colors cursor-pointer disabled:opacity-50"
            title={t('report.cancel')}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content Body */}
        {isSuccess ? (
          <div className="p-8 sm:p-10 flex flex-col items-center justify-center text-center gap-4 animate-in zoom-in-95">
            <div className="w-14 h-14 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-500 flex items-center justify-center shadow-lg shadow-emerald-500/10">
              <CheckCircle2 className="w-8 h-8" />
            </div>
            <div className="flex flex-col gap-1.5 max-w-sm">
              <h3 className="font-serif text-xl font-bold text-[var(--text-primary)]">
                {t('report.successTitle')}
              </h3>
              <p className="text-xs sm:text-sm text-[var(--text-secondary)] font-light leading-relaxed">
                {t('report.successMessage')}
              </p>
            </div>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="p-5 sm:p-6 flex flex-col gap-5 max-h-[80vh] overflow-y-auto">
            {/* Target Title context if provided */}
            {targetTitle && (
              <div className="p-3 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-xs text-[var(--text-secondary)] font-mono flex items-center gap-2">
                <ShieldAlert className="w-4 h-4 text-[var(--accent-color)] shrink-0" />
                <span className="truncate">
                  <strong className="text-[var(--text-primary)]">{targetPrefix}:</strong> {targetTitle}
                </span>
              </div>
            )}

            {/* Error Message */}
            {errorMsg && (
              <div className="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-500 text-xs flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 shrink-0" />
                <span>{errorMsg}</span>
              </div>
            )}

            {/* Reasons (Radio options styled as cards) */}
            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] font-mono">
                {t('report.reasonLabel')} <span className="text-rose-500">*</span>
              </label>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {reasons.map((reasonKey) => {
                  const isChecked = selectedReason === reasonKey;
                  return (
                    <label
                      key={reasonKey}
                      className={`flex items-center gap-2.5 p-3 rounded-xl border text-xs cursor-pointer transition-all ${
                        isChecked
                          ? 'bg-[var(--accent-light)] border-[var(--accent-color)] text-[var(--text-primary)] shadow-2xs font-semibold'
                          : 'bg-[var(--bg-tertiary)] border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]/40'
                      }`}
                    >
                      <input
                        type="radio"
                        name="reportReason"
                        value={reasonKey}
                        checked={isChecked}
                        onChange={() => setSelectedReason(reasonKey)}
                        className="text-[var(--accent-color)] focus:ring-[var(--accent-color)] h-3.5 w-3.5 cursor-pointer"
                      />
                      <span className="truncate">{t(`report.reasons.${reasonKey}`)}</span>
                    </label>
                  );
                })}
              </div>
            </div>

            {/* Optional Description (textarea) */}
            <div className="flex flex-col gap-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] font-mono">
                  {t('report.descriptionLabel')}
                </label>
                <span className="text-[10px] font-mono text-[var(--text-muted)]">
                  {t('report.charsRemaining', { count: 1000 - description.length })}
                </span>
              </div>

              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value.slice(0, 1000))}
                placeholder={t('report.descriptionPlaceholder')}
                rows={3}
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3.5 text-xs sm:text-sm font-sans focus:outline-hidden focus:ring-2 focus:ring-[var(--accent-color)] border border-[var(--border-color)] resize-y leading-relaxed transition-all"
              />
            </div>

            {/* Actions */}
            <div className="flex items-center gap-3 pt-2 border-t border-[var(--border-color)]">
              <Button
                type="button"
                variant="outline"
                size="md"
                disabled={isSubmitting}
                onClick={onClose}
                className="w-1/2 border-[var(--border-color)] hover:border-[var(--accent-color)] text-[var(--text-secondary)]"
              >
                {t('report.cancel')}
              </Button>

              <Button
                type="submit"
                variant="gold"
                size="md"
                isLoading={isSubmitting}
                disabled={!selectedReason || isSubmitting}
                className="w-1/2 bg-[var(--accent-color)] text-white hover:opacity-90 font-serif"
              >
                {t('report.submit')}
              </Button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};
