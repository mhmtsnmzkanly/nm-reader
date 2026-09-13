import React, { useState } from 'react';
import { Flag } from 'lucide-react';
import { ReportTargetType } from '../../services/contracts';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';
import { ReportModal } from './ReportModal';
import { Button } from '../ui/Button';

export interface ReportButtonProps {
  targetType: ReportTargetType;
  targetId: string | number;
  targetTitle?: string;
  variant?: 'icon' | 'text' | 'button';
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export const ReportButton: React.FC<ReportButtonProps> = ({
  targetType,
  targetId,
  targetTitle,
  variant = 'button',
  size = 'sm',
  className = '',
}) => {
  const { isAuthenticated, openAuthModal } = useAuth();
  const { t } = usePreferences();
  const [isModalOpen, setIsModalOpen] = useState(false);

  const handleClick = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    if (!isAuthenticated) {
      openAuthModal('login');
      return;
    }

    setIsModalOpen(true);
  };

  const buttonTitle = t('report.buttonTitle');
  const buttonLabel = t('report.buttonLabel');

  return (
    <>
      {variant === 'icon' ? (
        <button
          type="button"
          onClick={handleClick}
          title={buttonTitle}
          aria-label={buttonTitle}
          className={`p-1.5 sm:p-2 rounded-xl text-[var(--text-muted)] hover:text-amber-500 hover:bg-amber-500/10 border border-transparent hover:border-amber-500/20 transition-all cursor-pointer active:scale-95 shrink-0 ${className}`}
        >
          <Flag className="w-3.5 h-3.5 sm:w-4 sm:h-4" />
        </button>
      ) : variant === 'text' ? (
        <button
          type="button"
          onClick={handleClick}
          title={buttonTitle}
          className={`inline-flex items-center gap-1.5 text-xs font-mono text-[var(--text-muted)] hover:text-amber-500 transition-colors cursor-pointer active:scale-95 ${className}`}
        >
          <Flag className="w-3.5 h-3.5 text-amber-500" />
          <span>{buttonLabel}</span>
        </button>
      ) : (
        <Button
          type="button"
          variant="outline"
          size={size}
          onClick={handleClick}
          title={buttonTitle}
          className={`gap-1.5 font-mono text-xs border-[var(--border-color)] hover:border-amber-500/40 text-[var(--text-secondary)] hover:text-amber-500 hover:bg-amber-500/5 cursor-pointer ${className}`}
        >
          <Flag className="w-3.5 h-3.5 text-amber-500 shrink-0" />
          <span>{buttonLabel}</span>
        </Button>
      )}

      <ReportModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        targetType={targetType}
        targetId={targetId}
        targetTitle={targetTitle}
      />
    </>
  );
};
