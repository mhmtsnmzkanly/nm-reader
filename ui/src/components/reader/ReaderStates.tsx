import React from 'react';
import { AlertTriangle, RefreshCw } from 'lucide-react';

export const ReaderLoading: React.FC = () => {
  return (
    <div className="min-h-screen bg-[var(--bg-primary)] text-[var(--text-primary)] flex flex-col items-center justify-center p-6 animate-pulse transition-colors duration-300">
      <div className="max-w-md w-full flex flex-col items-center gap-6 text-center">
        <div className="w-12 h-12 rounded-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] flex items-center justify-center">
          <div className="w-6 h-6 border-2 border-[var(--accent-color)] border-t-transparent rounded-full animate-spin" />
        </div>
        <div className="flex flex-col gap-2 w-full items-center">
          <div className="h-4 w-32 bg-[var(--bg-tertiary)] rounded-full" />
          <div className="h-3 w-48 bg-[var(--bg-tertiary)] rounded-full" />
        </div>
        <div className="w-full max-w-lg flex flex-col gap-4 mt-8">
          <div className="h-6 bg-[var(--bg-tertiary)] rounded-md w-3/4 mx-auto" />
          <div className="h-4 bg-[var(--bg-tertiary)] rounded-md w-full" />
          <div className="h-4 bg-[var(--bg-tertiary)] rounded-md w-5/6 mx-auto" />
          <div className="h-4 bg-[var(--bg-tertiary)] rounded-md w-4/5 mx-auto" />
        </div>
        <span className="text-xs font-mono text-[var(--text-muted)] mt-4">Bölüm yükleniyor...</span>
      </div>
    </div>
  );
};

type ReaderErrorProps = {
  message?: string;
  onRetry: () => void;
};

export const ReaderError: React.FC<ReaderErrorProps> = ({
  message = 'Bölüm yüklenemedi.',
  onRetry,
}) => {
  return (
    <div className="min-h-screen bg-[var(--bg-primary)] text-[var(--text-primary)] flex items-center justify-center p-6 transition-colors duration-300">
      <div className="max-w-md w-full p-8 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl shadow-2xl flex flex-col items-center text-center gap-6">
        <div className="p-4 rounded-full bg-rose-500/10 text-rose-500 border border-rose-500/20">
          <AlertTriangle className="w-8 h-8" aria-hidden="true" />
        </div>

        <div className="flex flex-col gap-2">
          <h2 className="font-serif text-xl font-bold text-[var(--text-primary)]">
            {message}
          </h2>
          <p className="text-xs text-[var(--text-secondary)] font-light">
            Bölüm verileri alınırken bir hata oluştu. Lütfen bağlantınızı kontrol edip tekrar deneyin.
          </p>
        </div>

        <button
          type="button"
          onClick={onRetry}
          aria-label="Retry loading chapter"
          className="py-2.5 px-6 rounded-xl bg-[var(--accent-color)] text-white font-bold text-sm hover:opacity-90 transition-colors shadow-lg cursor-pointer flex items-center justify-center gap-2"
        >
          <RefreshCw className="w-4 h-4" />
          <span>Yeniden Dene</span>
        </button>
      </div>
    </div>
  );
};
