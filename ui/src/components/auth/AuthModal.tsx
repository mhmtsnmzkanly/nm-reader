import React, { useState, useEffect } from 'react';
import { X, LogIn, UserPlus, AlertCircle, CheckCircle } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { Button } from '../ui/Button';

export const AuthModal: React.FC = () => {
  const { isAuthModalOpen, authModalTab, closeAuthModal, login, register } = useAuth();
  const [activeTab, setActiveTab] = useState<'login' | 'register'>('login');

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

  useEffect(() => {
    setActiveTab(authModalTab);
    setLoginError(null);
    setRegError(null);
    setRegSuccess(null);
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
        setLoginError('Giriş başarısız. Lütfen bilgilerinizi kontrol edin.');
      }
    } catch {
      setLoginError('Bir hata oluştu. Lütfen tekrar deneyin.');
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
        setRegSuccess('Hesabınız başarıyla oluşturuldu! Oturum açılıyor...');
        setTimeout(async () => {
          await login(regEmail, regPassword, true);
          closeAuthModal();
          setRegUsername('');
          setRegEmail('');
          setRegPassword('');
          setRegSuccess(null);
        }, 1200);
      } else {
        setRegError('Kayıt oluşturulamadı. Lütfen bilgilerinizi kontrol edin.');
      }
    } catch {
      setRegError('Bir hata oluştu. Lütfen tekrar deneyin.');
    } finally {
      setIsRegLoading(false);
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
          aria-label="Kapat"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Tab Headers */}
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
            <span>Giriş Yap</span>
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
            <span>Kayıt Ol</span>
          </button>
        </div>

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
              <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                E-Posta veya Kullanıcı Adı
              </label>
              <input
                type="text"
                autoComplete="username"
                value={loginIdentity}
                onChange={(e) => setLoginIdentity(e.target.value)}
                placeholder="kullanici@nmreader.com"
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                Şifre
              </label>
              <input
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
              Giriş Yap
            </Button>

            <div className="text-center text-xs text-[var(--text-secondary)] mt-2">
              Hesabın yok mu?{' '}
              <button
                type="button"
                onClick={() => setActiveTab('register')}
                className="text-[var(--accent-color)] font-bold hover:underline cursor-pointer"
              >
                Hemen Kaydol
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
              <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                Kullanıcı Adı
              </label>
              <input
                type="text"
                autoComplete="username"
                value={regUsername}
                onChange={(e) => setRegUsername(e.target.value)}
                placeholder="kullaniciadi"
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                E-Posta
              </label>
              <input
                type="email"
                autoComplete="email"
                value={regEmail}
                onChange={(e) => setRegEmail(e.target.value)}
                placeholder="kullanici@nmreader.com"
                required
                className="w-full bg-[var(--bg-tertiary)] text-[var(--text-primary)] placeholder-[var(--text-muted)] rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--accent-color)] border border-[var(--border-color)] transition-all"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                Şifre
              </label>
              <input
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
              Kayıt Ol
            </Button>

            <div className="text-center text-xs text-[var(--text-secondary)] mt-2">
              Zaten hesabın var mı?{' '}
              <button
                type="button"
                onClick={() => setActiveTab('login')}
                className="text-[var(--accent-color)] font-bold hover:underline cursor-pointer"
              >
                Giriş Yap
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};
