import React from 'react';
import { Lock } from 'lucide-react';
import { Button } from '../ui/Button';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';

export type LoginPromptProps = {
  title?: string;
  description?: string;
  message?: string;
  actionText?: string;
  onAction?: () => void;
};

export const LoginPrompt: React.FC<LoginPromptProps> = ({
  title,
  description,
  message,
  actionText,
  onAction,
}) => {
  const { openAuthModal } = useAuth();
  const { t } = usePreferences();
  const displayTitle = title || t('feedback.loginRequiredTitle');
  const displayDesc = description || message || t('feedback.loginRequiredDesc');

  return (
    <div className="flex flex-col items-center justify-center p-8 text-center rounded-2xl border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/40 dark:bg-indigo-950/20 my-4">
      <div className="p-3.5 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 mb-3">
        <Lock className="w-8 h-8" />
      </div>
      <h4 className="text-base font-bold text-slate-900 dark:text-slate-100 mb-1">{displayTitle}</h4>
      <p className="text-sm text-slate-600 dark:text-slate-400 max-w-sm mb-5">{displayDesc}</p>
      <div className="flex items-center gap-3">
        {onAction ? (
          <Button
            variant="primary"
            size="sm"
            onClick={onAction}
            className="cursor-pointer"
          >
            {actionText || t('auth.login')}
          </Button>
        ) : (
          <>
            <Button variant="primary" size="sm" onClick={() => openAuthModal('login')} className="cursor-pointer">
              {t('auth.login')}
            </Button>
            <Button variant="outline" size="sm" onClick={() => openAuthModal('register')} className="cursor-pointer">
              {t('auth.register')}
            </Button>
          </>
        )}
      </div>
    </div>
  );
};
