import React from 'react';
import { AlertTriangle } from 'lucide-react';
import { Button } from '../ui/Button';

type ErrorStateProps = {
  title?: string;
  message?: string;
  onRetry?: () => void;
};

export const ErrorState: React.FC<ErrorStateProps> = ({
  title = 'Bir Hata Oluştu',
  message = 'İstenen veriler yüklenirken bir iletişim hatası meydana geldi.',
  onRetry,
}) => {
  return (
    <div className="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/50 dark:bg-rose-950/20 my-4">
      <div className="p-4 rounded-2xl bg-rose-100 dark:bg-rose-900/40 mb-4 text-rose-600 dark:text-rose-400">
        <AlertTriangle className="w-12 h-12" />
      </div>
      <h3 className="text-lg font-bold text-slate-900 dark:text-slate-100 mb-1">{title}</h3>
      <p className="text-sm text-slate-600 dark:text-slate-400 max-w-md mb-6">{message}</p>
      {onRetry && (
        <Button variant="danger" onClick={onRetry}>
          Tekrar Dene
        </Button>
      )}
    </div>
  );
};
