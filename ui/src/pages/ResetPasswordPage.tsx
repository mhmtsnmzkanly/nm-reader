import React, { useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import {
  KeyRound,
  Eye,
  EyeOff,
  CheckCircle2,
  AlertCircle,
  Lock,
  ArrowRight,
  Home,
  Check,
  X,
} from 'lucide-react';
import { usePreferences } from '../contexts/PreferencesContext';
import { useAuth } from '../contexts/AuthContext';
import { authService } from '../services';
import { Button } from '../components/ui/Button';

export const ResetPasswordPage: React.FC = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { t } = usePreferences();
  const { openAuthModal } = useAuth();

  const token = searchParams.get('token') || '';
  const email = searchParams.get('email') || '';

  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSuccess, setIsSuccess] = useState(false);

  // Validation rules
  const hasMinLength = password.length >= 8;
  const hasUpperCase = /[A-Z]/.test(password);
  const hasLowerCase = /[a-z]/.test(password);
  const hasNumber = /[0-9]/.test(password);
  const passwordsMatch = password.length > 0 && password === confirmPassword;
  const isFormValid = hasMinLength && hasUpperCase && hasLowerCase && hasNumber && passwordsMatch;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) {
      setError(t('auth.invalidResetLink'));
      return;
    }
    if (!passwordsMatch) {
      setError(t('auth.passwordsDoNotMatch'));
      return;
    }
    if (!hasMinLength || !hasUpperCase || !hasLowerCase || !hasNumber) {
      setError(t('auth.passwordRequirements'));
      return;
    }

    setError(null);
    setIsLoading(true);

    try {
      const res = await authService.resetPassword(token, password);
      if (res.status === 'success') {
        setIsSuccess(true);
      } else {
        setError(res.error?.message || t('auth.generalError'));
      }
    } catch {
      setError(t('auth.generalError'));
    } finally {
      setIsLoading(false);
    }
  };

  // If token is missing from URL
  if (!token) {
    return (
      <div className="min-h-[75vh] flex items-center justify-center p-4">
        <div className="w-full max-w-md bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 text-center shadow-xl">
          <div className="w-16 h-16 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center mx-auto mb-4 border border-rose-500/20">
            <AlertCircle className="w-8 h-8" />
          </div>
          <h1 className="text-xl font-bold text-[var(--text-primary)] mb-2">
            {t('auth.invalidResetLink')}
          </h1>
          <p className="text-sm text-[var(--text-secondary)] mb-6">
            {t('auth.invalidResetLinkDesc')}
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <Button
              variant="outline"
              onClick={() => navigate('/')}
              className="flex items-center justify-center gap-2 cursor-pointer"
            >
              <Home className="w-4 h-4" />
              <span>{t('auth.backToHome')}</span>
            </Button>
            <Button
              variant="gold"
              onClick={() => {
                navigate('/');
                setTimeout(() => openAuthModal('forgot-password'), 150);
              }}
              className="flex items-center justify-center gap-2 cursor-pointer bg-[var(--accent-color)] text-white"
            >
              <KeyRound className="w-4 h-4" />
              <span>{t('auth.sendResetLink')}</span>
            </Button>
          </div>
        </div>
      </div>
    );
  }

  // Success State
  if (isSuccess) {
    return (
      <div className="min-h-[75vh] flex items-center justify-center p-4">
        <div className="w-full max-w-md bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 text-center shadow-xl animate-in zoom-in-95 duration-200">
          <div className="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center mx-auto mb-4 border border-emerald-500/20">
            <CheckCircle2 className="w-8 h-8" />
          </div>
          <h1 className="text-xl font-bold text-[var(--text-primary)] mb-2">
            {t('auth.resetPasswordSuccess')}
          </h1>
          <p className="text-sm text-[var(--text-secondary)] mb-6">
            {t('auth.resetPasswordSuccessDesc')}
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <Button
              variant="gold"
              onClick={() => {
                navigate('/');
                setTimeout(() => openAuthModal('login'), 150);
              }}
              className="w-full flex items-center justify-center gap-2 cursor-pointer bg-[var(--accent-color)] text-white"
            >
              <span>{t('auth.goToLogin')}</span>
              <ArrowRight className="w-4 h-4" />
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-[75vh] flex items-center justify-center p-4">
      <div className="w-full max-w-md bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-xl">
        <div className="flex items-center gap-3 mb-6">
          <div className="p-3 rounded-2xl bg-[var(--accent-color)]/10 text-[var(--accent-color)] border border-[var(--accent-color)]/20">
            <KeyRound className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-xl font-bold text-[var(--text-primary)]">
              {t('auth.resetPasswordTitle')}
            </h1>
            <p className="text-xs text-[var(--text-secondary)]">
              {t('auth.resetPasswordSubtitle')}
            </p>
          </div>
        </div>

        {email && (
          <div className="mb-4 p-3 bg-[var(--bg-tertiary)] rounded-xl border border-[var(--border-color)] text-xs text-[var(--text-secondary)] flex items-center justify-between">
            <span>{t('auth.email')}:</span>
            <span className="font-semibold text-[var(--text-primary)]">{email}</span>
          </div>
        )}

        {error && (
          <div className="mb-4 flex items-center gap-2 p-3 bg-rose-500/10 border border-rose-500/30 text-rose-500 rounded-xl text-xs font-medium">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          {/* New Password */}
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {t('auth.newPassword')}
            </label>
            <div className="relative">
              <input
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 pr-10 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-3 text-[var(--text-muted)] hover:text-[var(--text-primary)] cursor-pointer"
              >
                {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

          {/* Confirm Password */}
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {t('auth.confirmPassword')}
            </label>
            <div className="relative">
              <input
                type={showConfirmPassword ? 'text' : 'password'}
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                placeholder="••••••••"
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 pr-10 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
              <button
                type="button"
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                className="absolute right-3 top-3 text-[var(--text-muted)] hover:text-[var(--text-primary)] cursor-pointer"
              >
                {showConfirmPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

          {/* Password Requirements List */}
          <div className="p-3 bg-[var(--bg-tertiary)]/50 rounded-xl border border-[var(--border-color)] flex flex-col gap-1.5 text-[11px]">
            <div className="font-semibold text-[var(--text-secondary)] mb-0.5">
              {t('auth.passwordRequirements')}
            </div>
            <div className={`flex items-center gap-1.5 ${hasMinLength ? 'text-emerald-500' : 'text-[var(--text-muted)]'}`}>
              {hasMinLength ? <Check className="w-3.5 h-3.5" /> : <X className="w-3.5 h-3.5" />}
              <span>En az 8 karakter</span>
            </div>
            <div className={`flex items-center gap-1.5 ${hasUpperCase ? 'text-emerald-500' : 'text-[var(--text-muted)]'}`}>
              {hasUpperCase ? <Check className="w-3.5 h-3.5" /> : <X className="w-3.5 h-3.5" />}
              <span>Büyük harf (A-Z)</span>
            </div>
            <div className={`flex items-center gap-1.5 ${hasLowerCase ? 'text-emerald-500' : 'text-[var(--text-muted)]'}`}>
              {hasLowerCase ? <Check className="w-3.5 h-3.5" /> : <X className="w-3.5 h-3.5" />}
              <span>Küçük harf (a-z)</span>
            </div>
            <div className={`flex items-center gap-1.5 ${hasNumber ? 'text-emerald-500' : 'text-[var(--text-muted)]'}`}>
              {hasNumber ? <Check className="w-3.5 h-3.5" /> : <X className="w-3.5 h-3.5" />}
              <span>Rakam (0-9)</span>
            </div>
            {password.length > 0 && confirmPassword.length > 0 && (
              <div className={`flex items-center gap-1.5 ${passwordsMatch ? 'text-emerald-500' : 'text-rose-500'}`}>
                {passwordsMatch ? <Check className="w-3.5 h-3.5" /> : <X className="w-3.5 h-3.5" />}
                <span>Şifreler eşleşiyor</span>
              </div>
            )}
          </div>

          <Button
            type="submit"
            variant="gold"
            size="lg"
            isLoading={isLoading}
            disabled={!isFormValid}
            fullWidth
            className="mt-2 bg-[var(--accent-color)] text-white hover:opacity-90 cursor-pointer"
          >
            <Lock className="w-4 h-4 mr-1.5" />
            <span>{t('auth.updatePassword')}</span>
          </Button>

          <div className="text-center text-xs text-[var(--text-secondary)] mt-2">
            <Link
              to="/"
              className="text-[var(--accent-color)] font-medium hover:underline inline-flex items-center gap-1"
            >
              ← {t('auth.backToHome')}
            </Link>
          </div>
        </form>
      </div>
    </div>
  );
};
