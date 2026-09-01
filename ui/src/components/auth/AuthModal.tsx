import React, { useState, useEffect } from 'react';
import { X, LogIn, UserPlus, AlertCircle, CheckCircle, KeyRound, ArrowLeft, Mail } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';
import { authService } from '../../services';
import { Button } from '../ui/Button';

export const AuthModal: React.FC = () => {
  const { t } = usePreferences();
  const { isAuthModalOpen, authModalTab, closeAuthModal, login, register } = useAuth();
  const [activeTab, setActiveTab] = useState<'login' | 'register' | 'forgot-password'>('login');

  // Login Form States
  const [loginIdentity, setLoginIdentity] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [loginError, setLoginError] = useState<string | null>(null);
  const [isLoginLoading, setIsLoginLoading] = useState(false);

  // Register Form States
  const [regUsername, setRegUsername] = useState('');
  const [regEmail, setRegEmail] = useState('');
  const [regPassword, setRegPassword] = useState('');
  const [regError, setRegError] = useState<string | null>(null);
  const [regSuccess, setRegSuccess] = useState<string | null>(null);
  const [isRegLoading, setIsRegLoading] = useState(false);

  // Forgot Password States
  const [forgotEmail, setForgotEmail] = useState('');
  const [forgotError, setForgotError] = useState<string | null>(null);
  const [forgotSuccess, setForgotSuccess] = useState<string | null>(null);
  const [isForgotLoading, setIsForgotLoading] = useState(false);

  useEffect(() => {
    setActiveTab(authModalTab);
    setLoginError(null);
    setRegError(null);
    setRegSuccess(null);
    setForgotError(null);
    setForgotSuccess(null);
  }, [authModalTab, isAuthModalOpen]);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isAuthModalOpen) {
        closeAuthModal();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isAuthModalOpen, closeAuthModal]);

  if (!isAuthModalOpen) return null;

  const handleLoginSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!loginIdentity || !loginPassword || isLoginLoading) return;

    setLoginError(null);
    setIsLoginLoading(true);
    try {
      const success = await login(loginIdentity, loginPassword, true);
      if (success) {
        closeAuthModal();
        setLoginIdentity('');
        setLoginPassword('');
      } else {
        setLoginError(t('auth.loginFailed'));
      }
    } catch {
      setLoginError(t('auth.generalError'));
    } finally {
      setIsLoginLoading(false);
    }
  };

  const handleRegisterSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!regUsername || !regEmail || !regPassword || isRegLoading) return;

    setRegError(null);
    setRegSuccess(null);
    setIsRegLoading(true);
    try {
      const success = await register(regUsername, regEmail, regPassword);
      if (success) {
        setRegSuccess(t('auth.registerSuccess'));
        setTimeout(async () => {
          await login(regEmail, regPassword, true);
          closeAuthModal();
          setRegUsername('');
          setRegEmail('');
          setRegPassword('');
          setRegSuccess(null);
        }, 1200);
      } else {
        setRegError(t('auth.registerFailed'));
      }
    } catch {
      setRegError(t('auth.generalError'));
    } finally {
      setIsRegLoading(false);
    }
  };

  const handleForgotSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!forgotEmail || isForgotLoading) return;

    setForgotError(null);
    setForgotSuccess(null);
    setIsForgotLoading(true);
    try {
      const res = await authService.forgotPassword(forgotEmail);
      if (res.status === 'success') {
        setForgotSuccess(res.data.message || t('auth.resetLinkSent'));
      } else {
        setForgotError(res.error?.message || t('auth.generalError'));
      }
    } catch {
      setForgotError(t('auth.generalError'));
    } finally {
      setIsForgotLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-200">
      {/* Backdrop overlay */}
      <div
        className="absolute inset-0"
        onClick={closeAuthModal}
        aria-hidden="true"
      />

      {/* Modal Container */}
      <div className="relative w-full max-w-md bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl shadow-2xl p-6 sm:p-8 z-10 overflow-hidden transition-colors duration-300">
        {/* Close Button */}
        <button
          onClick={closeAuthModal}
          className="absolute top-4 right-4 p-2 text-[var(--text-muted)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] rounded-full transition-colors cursor-pointer"
          aria-label={t('common.close')}
        >
          <X className="w-5 h-5" />
        </button>

        {/* Tab Headers or Forgot Password Header */}
        {activeTab !== 'forgot-password' ? (
          <div className="flex border-b border-[var(--border-color)] mb-6">
            <button
              type="button"
              onClick={() => {
                setActiveTab('login');
                setLoginError(null);
              }}
              className={`flex-1 flex items-center justify-center gap-2 pb-3 text-xs sm:text-sm font-bold uppercase tracking-wider transition-all cursor-pointer border-b-2 ${
                activeTab === 'login'
                  ? 'border-[var(--accent-color)] text-[var(--accent-color)]'
                  : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]'
              }`}
            >
              <LogIn className="w-4 h-4" />
              <span>{t('auth.login')}</span>
            </button>

            <button
              type="button"
              onClick={() => {
                setActiveTab('register');
                setRegError(null);
              }}
              className={`flex-1 flex items-center justify-center gap-2 pb-3 text-xs sm:text-sm font-bold uppercase tracking-wider transition-all cursor-pointer border-b-2 ${
                activeTab === 'register'
                  ? 'border-[var(--accent-color)] text-[var(--accent-color)]'
                  : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]'
              }`}
            >
              <UserPlus className="w-4 h-4" />
              <span>{t('auth.register')}</span>
            </button>
          </div>
        ) : (
          <div className="flex items-center gap-3 mb-6 pb-3 border-b border-[var(--border-color)]">
            <div className="p-2 rounded-xl bg-[var(--accent-color)]/10 text-[var(--accent-color)]">
              <KeyRound className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-[var(--text-primary)]">
                {t('auth.forgotPasswordTitle')}
              </h2>
              <p className="text-xs text-[var(--text-secondary)]">
                {t('auth.forgotPasswordSubtitle')}
              </p>
            </div>
          </div>
        )}

        {/* Login Tab Content */}
        {activeTab === 'login' && (
          <form onSubmit={handleLoginSubmit} className="flex flex-col gap-4">
            {loginError && (
              <div className="flex items-center gap-2 p-3 bg-rose-500/10 border border-rose-500/30 text-rose-500 rounded-xl text-xs font-medium">
                <AlertCircle className="w-4 h-4 shrink-0" />
                <span>{loginError}</span>
              </div>
            )}

            <div className="flex flex-col gap-1.5">
              <label htmlFor="login_identity" className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                {t('auth.emailOrUsername')}
              </label>
              <input
                id="login_identity"
                name="identity"
                type="text"
                autoComplete="username"
                value={loginIdentity}
                onChange={(e) => setLoginIdentity(e.target.value)}
                placeholder={t('auth.emailPlaceholder')}
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <div className="flex items-center justify-between">
                <label htmlFor="login_password" className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                  {t('auth.password')}
                </label>
                <button
                  type="button"
                  onClick={() => {
                    setActiveTab('forgot-password');
                    setForgotEmail(loginIdentity && loginIdentity.includes('@') ? loginIdentity : '');
                    setForgotError(null);
                    setForgotSuccess(null);
                  }}
                  className="text-[11px] text-[var(--accent-color)] hover:underline font-medium cursor-pointer"
                >
                  {t('auth.forgotPasswordPrompt')}
                </button>
              </div>
              <input
                id="login_password"
                name="password"
                type="password"
                autoComplete="current-password"
                value={loginPassword}
                onChange={(e) => setLoginPassword(e.target.value)}
                placeholder="••••••••"
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
            </div>

            <Button
              type="submit"
              variant="gold"
              size="lg"
              isLoading={isLoginLoading}
              disabled={!loginIdentity || !loginPassword}
              fullWidth
              className="mt-2 bg-[var(--accent-color)] text-white hover:opacity-90 cursor-pointer"
            >
              {t('auth.login')}
            </Button>

            <div className="text-center text-xs text-[var(--text-secondary)] mt-2">
              {t('auth.noAccountPrompt')}{' '}
              <button
                type="button"
                onClick={() => setActiveTab('register')}
                className="text-[var(--accent-color)] font-bold hover:underline cursor-pointer"
              >
                {t('auth.registerNow')}
              </button>
            </div>
          </form>
        )}

        {/* Register Tab Content */}
        {activeTab === 'register' && (
          <form onSubmit={handleRegisterSubmit} className="flex flex-col gap-4">
            {regError && (
              <div className="flex items-center gap-2 p-3 bg-rose-500/10 border border-rose-500/30 text-rose-500 rounded-xl text-xs font-medium">
                <AlertCircle className="w-4 h-4 shrink-0" />
                <span>{regError}</span>
              </div>
            )}

            {regSuccess && (
              <div className="flex items-center gap-2 p-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 rounded-xl text-xs font-medium">
                <CheckCircle className="w-4 h-4 shrink-0" />
                <span>{regSuccess}</span>
              </div>
            )}

            <div className="flex flex-col gap-1.5">
              <label htmlFor="reg_username" className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                {t('auth.username')}
              </label>
              <input
                id="reg_username"
                name="username"
                type="text"
                autoComplete="username"
                value={regUsername}
                onChange={(e) => setRegUsername(e.target.value)}
                placeholder={t('auth.usernamePlaceholder')}
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label htmlFor="reg_email" className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                {t('auth.email')}
              </label>
              <input
                id="reg_email"
                name="email"
                type="email"
                autoComplete="email"
                value={regEmail}
                onChange={(e) => setRegEmail(e.target.value)}
                placeholder={t('auth.emailPlaceholder')}
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label htmlFor="reg_password" className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                {t('auth.password')}
              </label>
              <input
                id="reg_password"
                name="password"
                type="password"
                autoComplete="new-password"
                value={regPassword}
                onChange={(e) => setRegPassword(e.target.value)}
                placeholder="••••••••"
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
            </div>

            <Button
              type="submit"
              variant="gold"
              size="lg"
              isLoading={isRegLoading}
              disabled={!regUsername || !regEmail || !regPassword}
              fullWidth
              className="mt-2 bg-[var(--accent-color)] text-white hover:opacity-90 cursor-pointer"
            >
              {t('auth.register')}
            </Button>

            <div className="text-center text-xs text-[var(--text-secondary)] mt-2">
              {t('auth.alreadyHaveAccountPrompt')}{' '}
              <button
                type="button"
                onClick={() => setActiveTab('login')}
                className="text-[var(--accent-color)] font-bold hover:underline cursor-pointer"
              >
                {t('auth.login')}
              </button>
            </div>
          </form>
        )}

        {/* Forgot Password Tab Content */}
        {activeTab === 'forgot-password' && (
          <div className="flex flex-col gap-4">
            {forgotSuccess ? (
              <div className="flex flex-col items-center gap-3 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 rounded-2xl text-center animate-in fade-in zoom-in-95 duration-200">
                <div className="w-12 h-12 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-500">
                  <CheckCircle className="w-6 h-6" />
                </div>
                <div className="text-sm font-semibold">
                  {forgotSuccess}
                </div>
              </div>
            ) : (
              <form onSubmit={handleForgotSubmit} className="flex flex-col gap-4">
                {forgotError && (
                  <div className="flex items-center gap-2 p-3 bg-rose-500/10 border border-rose-500/30 text-rose-500 rounded-xl text-xs font-medium">
                    <AlertCircle className="w-4 h-4 shrink-0" />
                    <span>{forgotError}</span>
                  </div>
                )}

                <div className="flex flex-col gap-1.5">
                  <label htmlFor="forgot_email" className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                    {t('auth.email')}
                  </label>
                  <div className="relative">
                    <input
                      id="forgot_email"
                      name="email"
                      type="email"
                      autoComplete="email"
                      value={forgotEmail}
                      onChange={(e) => setForgotEmail(e.target.value)}
                      placeholder={t('auth.emailPlaceholder')}
                      required
                      className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 pl-10 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
                    />
                    <Mail className="w-4 h-4 text-[var(--text-muted)] absolute left-3 top-3.5" />
                  </div>
                </div>

                <Button
                  type="submit"
                  variant="gold"
                  size="lg"
                  isLoading={isForgotLoading}
                  disabled={!forgotEmail}
                  fullWidth
                  className="mt-2 bg-[var(--accent-color)] text-white hover:opacity-90 cursor-pointer"
                >
                  {t('auth.sendResetLink')}
                </Button>
              </form>
            )}

            <div className="text-center text-xs text-[var(--text-secondary)] mt-2 pt-3 border-t border-[var(--border-color)]">
              <button
                type="button"
                onClick={() => {
                  setActiveTab('login');
                  setForgotError(null);
                  setForgotSuccess(null);
                }}
                className="text-[var(--accent-color)] font-bold hover:underline cursor-pointer inline-flex items-center gap-1.5"
              >
                <ArrowLeft className="w-3.5 h-3.5" />
                <span>{t('auth.backToLogin')}</span>
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

