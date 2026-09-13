import React, { useState, useEffect } from 'react';
import { MailWarning, Send, CheckCircle2, X, AlertCircle } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';
import { authService } from '../../services';

const COOLDOWN_KEY = 'nm_verification_cooldown';

export const EmailVerificationBanner: React.FC = () => {
  const { user, isAuthenticated } = useAuth();
  const { t } = usePreferences();

  const [isDismissed, setIsDismissed] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [cooldown, setCooldown] = useState<number>(0);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Initialize and tick cooldown timer
  useEffect(() => {
    const savedCooldownExpiry = localStorage.getItem(COOLDOWN_KEY);
    if (savedCooldownExpiry) {
      const remaining = Math.ceil((parseInt(savedCooldownExpiry, 10) - Date.now()) / 1000);
      if (remaining > 0) {
        setCooldown(remaining);
      } else {
        localStorage.removeItem(COOLDOWN_KEY);
      }
    }
  }, []);

  useEffect(() => {
    if (cooldown <= 0) return;
    const interval = setInterval(() => {
      setCooldown((prev) => {
        if (prev <= 1) {
          localStorage.removeItem(COOLDOWN_KEY);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(interval);
  }, [cooldown]);

  // If user is guest, not logged in, or already email_verified, or dismissed
  if (!isAuthenticated || !user || user.email_verified || isDismissed) {
    return null;
  }

  const handleSendVerification = async () => {
    if (cooldown > 0 || isSending) return;

    setIsSending(true);
    setSuccessMessage(null);
    setErrorMessage(null);

    try {
      const res = await authService.resendVerificationEmail();
      if (res.status === 'success') {
        const expiry = Date.now() + 60 * 1000;
        localStorage.setItem(COOLDOWN_KEY, expiry.toString());
        setCooldown(60);
        setSuccessMessage(res.data.message || t('auth.resendSuccess'));
        setTimeout(() => setSuccessMessage(null), 5000);
      } else {
        setErrorMessage(res.error?.message || t('auth.generalError'));
        setTimeout(() => setErrorMessage(null), 5000);
      }
    } catch {
      setErrorMessage(t('auth.generalError'));
      setTimeout(() => setErrorMessage(null), 5000);
    } finally {
      setIsSending(false);
    }
  };

  return (
    <div className="w-full bg-amber-500/10 border-b border-amber-500/20 text-amber-900 dark:text-amber-200 px-4 py-2.5 transition-all text-xs">
      <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-4">
        {/* Left Side Message */}
        <div className="flex items-center gap-2.5 flex-1 min-w-0">
          <div className="p-1 rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400 shrink-0">
            <MailWarning className="w-4 h-4" />
          </div>
          <p className="font-medium truncate sm:whitespace-normal">
            {t('auth.emailUnverifiedNotice')}
          </p>
        </div>

        {/* Right Side Actions & Status */}
        <div className="flex items-center gap-2 shrink-0">
          {successMessage && (
            <div className="flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold animate-in fade-in">
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>{successMessage}</span>
            </div>
          )}

          {errorMessage && (
            <div className="flex items-center gap-1 text-rose-600 dark:text-rose-400 font-semibold animate-in fade-in">
              <AlertCircle className="w-3.5 h-3.5" />
              <span>{errorMessage}</span>
            </div>
          )}

          {!successMessage && !errorMessage && (
            <button
              type="button"
              onClick={handleSendVerification}
              disabled={cooldown > 0 || isSending}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            >
              <Send className="w-3 h-3" />
              <span>
                {cooldown > 0
                  ? t('auth.resendCountdown', { seconds: cooldown })
                  : t('auth.sendVerificationEmail')}
              </span>
            </button>
          )}

          {/* Dismiss button */}
          <button
            type="button"
            onClick={() => setIsDismissed(true)}
            className="p-1 text-amber-700 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-100 rounded-md hover:bg-amber-500/20 transition-colors cursor-pointer ml-1"
            aria-label={t('common.close')}
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
};
