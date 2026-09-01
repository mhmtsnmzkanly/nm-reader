import React from 'react';
import { useNavigate } from 'react-router-dom';
import { ShieldAlert, AlertTriangle, ArrowLeft, CheckCircle2 } from 'lucide-react';
import { Button } from '../ui/Button';
import { usePreferences } from '../../contexts/PreferencesContext';

export const ADULT_STORAGE_KEY = 'adult_confirmed';

export function isAdultConfirmed(): boolean {
  if (typeof window === 'undefined') return false;
  try {
    return localStorage.getItem(ADULT_STORAGE_KEY) === 'true';
  } catch {
    return false;
  }
}

export function setAdultConfirmed(confirmed: boolean = true): void {
  if (typeof window === 'undefined') return;
  try {
    if (confirmed) {
      localStorage.setItem(ADULT_STORAGE_KEY, 'true');
    } else {
      localStorage.removeItem(ADULT_STORAGE_KEY);
    }
  } catch {
    // Ignore storage quota or access errors
  }
}

type AdultGateModalProps = {
  isOpen: boolean;
  onConfirm: () => void;
  onCancel?: () => void;
  title?: string;
  description?: string;
};

export const AdultGateModal: React.FC<AdultGateModalProps> = ({
  isOpen,
  onConfirm,
  onCancel,
  title,
  description,
}) => {
  const navigate = useNavigate();
  const { t } = usePreferences();

  if (!isOpen) return null;

  const handleConfirm = () => {
    setAdultConfirmed(true);
    onConfirm();
  };

  const handleCancel = () => {
    if (onCancel) {
      onCancel();
    } else {
      if (window.history.length > 1) {
        navigate(-1);
      } else {
        navigate('/');
      }
    }
  };

  return (
    <div
      id="adult-gate-overlay"
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn"
      role="dialog"
      aria-modal="true"
      aria-labelledby="adult-gate-title"
    >
      <div className="relative w-full max-w-md bg-[var(--bg-card)] border-2 border-rose-500/40 rounded-3xl p-6 sm:p-8 shadow-2xl shadow-rose-950/40 text-center flex flex-col items-center gap-5 overflow-hidden">
        {/* Subtle Ambient Glow */}
        <div className="absolute -top-20 inset-x-0 h-40 bg-gradient-to-b from-rose-500/20 to-transparent pointer-events-none blur-2xl" />

        {/* Warning Icon with Badge */}
        <div className="relative flex items-center justify-center">
          <div className="w-20 h-20 rounded-2xl bg-rose-500/10 border-2 border-rose-500/30 flex items-center justify-center text-rose-500 shadow-lg shadow-rose-500/20">
            <ShieldAlert className="w-10 h-10" />
          </div>
          <span className="absolute -bottom-2 px-2.5 py-0.5 rounded-full bg-rose-600 text-white font-mono font-bold text-xs tracking-wider shadow-md">
            +18
          </span>
        </div>

        {/* Header & Warning Copy */}
        <div className="flex flex-col gap-2 relative z-10">
          <h2
            id="adult-gate-title"
            className="font-serif text-xl sm:text-2xl font-bold text-[var(--text-primary)]"
          >
            {title || t('adult.modalTitle')}
          </h2>
          <p className="text-xs sm:text-sm text-[var(--text-secondary)] font-light leading-relaxed">
            {description || t('adult.modalDescription')}
          </p>
        </div>

        {/* Highlight Notice */}
        <div className="w-full bg-rose-500/10 border border-rose-500/20 rounded-2xl p-3.5 flex items-center gap-3 text-left relative z-10">
          <AlertTriangle className="w-4 h-4 text-rose-500 shrink-0" />
          <span className="text-[11px] sm:text-xs text-rose-300 font-medium leading-snug">
            {t('adult.warningNotice')}
          </span>
        </div>

        {/* Action Buttons */}
        <div className="w-full flex flex-col gap-2.5 pt-2 relative z-10">
          <Button
            id="adult-confirm-btn"
            variant="primary"
            size="lg"
            fullWidth
            onClick={handleConfirm}
            className="bg-rose-600 hover:bg-rose-700 text-white font-bold gap-2 cursor-pointer shadow-lg shadow-rose-600/30 py-3 text-sm justify-center rounded-xl"
          >
            <CheckCircle2 className="w-4 h-4" />
            <span>{t('adult.confirmButton')}</span>
          </Button>

          <Button
            id="adult-cancel-btn"
            variant="outline"
            size="md"
            fullWidth
            onClick={handleCancel}
            className="text-[var(--text-secondary)] hover:text-[var(--text-primary)] border-[var(--border-color)] gap-2 cursor-pointer py-2.5 text-xs justify-center rounded-xl"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>{t('adult.cancelButton')}</span>
          </Button>
        </div>
      </div>
    </div>
  );
};
