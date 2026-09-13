import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Lock, UserPlus, LogIn, Compass, Sparkles } from 'lucide-react';
import { Button } from '../ui/Button';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';

type MembersOnlyLockProps = {
  title?: string;
  description?: string;
  className?: string;
  compact?: boolean;
  onLogin?: () => void;
  onRegister?: () => void;
};

export const MembersOnlyLock: React.FC<MembersOnlyLockProps> = ({
  title,
  description,
  className = '',
  compact = false,
  onLogin,
  onRegister,
}) => {
  const navigate = useNavigate();
  const { openAuthModal } = useAuth();
  const { t } = usePreferences();

  const handleLoginClick = () => {
    if (onLogin) {
      onLogin();
    } else {
      openAuthModal('login');
    }
  };

  const handleRegisterClick = () => {
    if (onRegister) {
      onRegister();
    } else {
      openAuthModal('register');
    }
  };

  if (compact) {
    return (
      <div
        id="members-only-lock-card"
        className={`bg-purple-950/20 border border-purple-500/30 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left ${className}`}
      >
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center shrink-0 border border-purple-500/30 shadow-inner">
            <Lock className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2 justify-center sm:justify-start">
              <span className="text-xs font-mono uppercase font-bold tracking-wider text-purple-400">
                {t('membersOnly.badge')}
              </span>
            </div>
            <h4 className="text-sm font-semibold text-[var(--text-primary)]">
              {title || t('membersOnly.lockTitle')}
            </h4>
          </div>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <Button
            variant="gold"
            size="sm"
            onClick={handleLoginClick}
            className="gap-1.5 bg-purple-600 hover:bg-purple-700 text-white font-medium text-xs px-3 py-2 cursor-pointer shadow-md shadow-purple-600/20"
          >
            <LogIn className="w-3.5 h-3.5" />
            <span>{t('membersOnly.loginButton')}</span>
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={handleRegisterClick}
            className="gap-1.5 border-purple-500/40 text-purple-300 hover:bg-purple-500/10 text-xs px-3 py-2 cursor-pointer"
          >
            <UserPlus className="w-3.5 h-3.5" />
            <span>{t('membersOnly.registerButton')}</span>
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div
      id="members-only-lock-screen"
      className={`relative overflow-hidden bg-gradient-to-b from-[var(--bg-card)] via-purple-950/15 to-[var(--bg-card)] border-2 border-purple-500/30 rounded-3xl p-6 sm:p-10 shadow-2xl text-center flex flex-col items-center gap-6 max-w-xl mx-auto my-6 ${className}`}
    >
      {/* Decorative Aura */}
      <div className="absolute -top-24 inset-x-0 h-48 bg-gradient-to-b from-purple-500/20 to-transparent blur-3xl pointer-events-none" />

      {/* Lock Icon Emblem */}
      <div className="relative">
        <div className="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-purple-500/10 border-2 border-purple-500/40 flex items-center justify-center text-purple-400 shadow-xl shadow-purple-500/20">
          <Lock className="w-10 h-10 sm:w-12 sm:h-12" />
        </div>
        <div className="absolute -bottom-2.5 inset-x-0 flex justify-center">
          <span className="inline-flex items-center gap-1 px-3 py-0.5 rounded-full bg-purple-600 text-white font-mono font-bold text-[11px] tracking-wider uppercase shadow-md">
            <Sparkles className="w-3 h-3" />
            <span>{t('membersOnly.badge')}</span>
          </span>
        </div>
      </div>

      {/* Headings */}
      <div className="flex flex-col gap-2 relative z-10 max-w-md">
        <h3 className="font-serif text-xl sm:text-2xl font-bold text-[var(--text-primary)] leading-tight">
          {title || t('membersOnly.lockTitle')}
        </h3>
        <p className="text-xs sm:text-sm text-[var(--text-secondary)] font-light leading-relaxed">
          {description || t('membersOnly.lockDescription')}
        </p>
      </div>

      {/* Auth Actions */}
      <div className="w-full max-w-xs flex flex-col gap-2.5 relative z-10">
        <Button
          id="members-lock-login-btn"
          variant="gold"
          size="lg"
          fullWidth
          onClick={handleLoginClick}
          className="bg-purple-600 hover:bg-purple-700 text-white font-bold gap-2 cursor-pointer shadow-lg shadow-purple-600/30 py-3 text-sm justify-center rounded-xl"
        >
          <LogIn className="w-4 h-4" />
          <span>{t('membersOnly.loginButton')}</span>
        </Button>

        <Button
          id="members-lock-register-btn"
          variant="outline"
          size="md"
          fullWidth
          onClick={handleRegisterClick}
          className="border-purple-500/40 text-purple-300 hover:text-white hover:bg-purple-500/20 gap-2 cursor-pointer py-2.5 text-xs justify-center rounded-xl"
        >
          <UserPlus className="w-4 h-4" />
          <span>{t('membersOnly.registerButton')}</span>
        </Button>

        <button
          type="button"
          onClick={() => navigate('/browse')}
          className="mt-1 text-xs font-mono text-[var(--text-muted)] hover:text-[var(--text-primary)] hover:underline inline-flex items-center justify-center gap-1.5 cursor-pointer py-1"
        >
          <Compass className="w-3.5 h-3.5" />
          <span>{t('membersOnly.browseOther')}</span>
        </button>
      </div>
    </div>
  );
};
