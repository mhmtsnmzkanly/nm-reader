import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import {
  CheckCircle2,
  AlertCircle,
  Loader2,
  Mail,
  Home,
  Compass,
  RefreshCw,
} from 'lucide-react';
import { usePreferences } from '../contexts/PreferencesContext';
import { useAuth } from '../contexts/AuthContext';
import { authService } from '../services';
import { Button } from '../components/ui/Button';

export const VerifyEmailPage: React.FC = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { t } = usePreferences();
  const { refreshProfile, isAuthenticated } = useAuth();

  const token = searchParams.get('token') || '';

  const [isLoading, setIsLoading] = useState(true);
  const [isSuccess, setIsSuccess] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const [isResending, setIsResending] = useState(false);
  const [resendSuccess, setResendSuccess] = useState<string | null>(null);
  const [resendError, setResendError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    const verify = async () => {
      if (!token) {
        if (isMounted) {
          setIsLoading(false);
          setIsSuccess(false);
          setErrorMessage(t('auth.emailVerificationFailed'));
        }
        return;
      }

      try {
        const res = await authService.verifyEmail(token);
        if (!isMounted) return;

        if (res.status === 'success') {
          setIsSuccess(true);
          await refreshProfile();
        } else {
          setIsSuccess(false);
          setErrorMessage(res.error?.message || t('auth.emailVerificationFailed'));
        }
      } catch {
        if (isMounted) {
          setIsSuccess(false);
          setErrorMessage(t('auth.unexpectedError'));
        }
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    verify();

    return () => {
      isMounted = false;
    };
  }, [token, refreshProfile, t]);

  const handleResend = async () => {
    setIsResending(true);
    setResendSuccess(null);
    setResendError(null);
    try {
      const res = await authService.resendVerificationEmail();
      if (res.status === 'success') {
        setResendSuccess(res.data.message || t('auth.resendSuccess'));
      } else {
        setResendError(res.error?.message || t('auth.generalError'));
      }
    } catch {
      setResendError(t('auth.generalError'));
    } finally {
      setIsResending(false);
    }
  };

  return (
    <div className="min-h-[75vh] flex items-center justify-center p-4">
      <div className="w-full max-w-md bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 text-center shadow-xl">
        {/* Loading State */}
        {isLoading && (
          <div className="flex flex-col items-center gap-4 py-8 animate-in fade-in duration-200">
            <div className="w-16 h-16 rounded-2xl bg-[var(--accent-color)]/10 text-[var(--accent-color)] flex items-center justify-center border border-[var(--accent-color)]/20">
              <Loader2 className="w-8 h-8 animate-spin" />
            </div>
            <h1 className="text-xl font-bold text-[var(--text-primary)]">
              {t('auth.verifyEmailTitle')}
            </h1>
            <p className="text-sm text-[var(--text-secondary)]">
              {t('auth.verifyingEmail')}
            </p>
          </div>
        )}

        {/* Success State */}
        {!isLoading && isSuccess && (
          <div className="flex flex-col items-center gap-4 py-4 animate-in zoom-in-95 duration-200">
            <div className="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center border border-emerald-500/20 shadow-sm">
              <CheckCircle2 className="w-8 h-8" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-[var(--text-primary)] mb-2">
                {t('auth.emailVerifiedSuccess')}
              </h1>
              <p className="text-sm text-[var(--text-secondary)]">
                {t('auth.emailVerifiedSuccessDesc')}
              </p>
            </div>
            <div className="w-full flex flex-col sm:flex-row gap-3 justify-center mt-4">
              <Button
                variant="gold"
                onClick={() => navigate('/')}
                className="w-full flex items-center justify-center gap-2 cursor-pointer bg-[var(--accent-color)] text-white"
              >
                <Compass className="w-4 h-4" />
                <span>{t('auth.exploreContent')}</span>
              </Button>
            </div>
          </div>
        )}

        {/* Error State */}
        {!isLoading && !isSuccess && (
          <div className="flex flex-col items-center gap-4 py-4 animate-in zoom-in-95 duration-200">
            <div className="w-16 h-16 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center border border-rose-500/20 shadow-sm">
              <AlertCircle className="w-8 h-8" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-[var(--text-primary)] mb-2">
                {t('auth.emailVerificationFailed')}
              </h1>
              <p className="text-sm text-[var(--text-secondary)]">
                {errorMessage || t('auth.emailVerificationFailedDesc')}
              </p>
            </div>

            {resendSuccess && (
              <div className="w-full p-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 rounded-xl text-xs font-medium">
                {resendSuccess}
              </div>
            )}

            {resendError && (
              <div className="w-full p-3 bg-rose-500/10 border border-rose-500/30 text-rose-500 rounded-xl text-xs font-medium">
                {resendError}
              </div>
            )}

            <div className="w-full flex flex-col gap-3 justify-center mt-4">
              {isAuthenticated && (
                <Button
                  variant="gold"
                  onClick={handleResend}
                  isLoading={isResending}
                  className="w-full flex items-center justify-center gap-2 cursor-pointer bg-[var(--accent-color)] text-white"
                >
                  <RefreshCw className="w-4 h-4" />
                  <span>{t('auth.resendVerificationEmail')}</span>
                </Button>
              )}

              <Button
                variant="outline"
                onClick={() => navigate('/')}
                className="w-full flex items-center justify-center gap-2 cursor-pointer"
              >
                <Home className="w-4 h-4" />
                <span>{t('auth.backToHome')}</span>
              </Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
