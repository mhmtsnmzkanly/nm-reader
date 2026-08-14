import React from 'react';
import { Inbox } from 'lucide-react';
import { Button } from '../ui/Button';

type EmptyStateProps = {
  title?: string;
  description?: string;
  actionLabel?: string;
  onAction?: () => void;
  icon?: React.ReactNode;
};

export const EmptyState: React.FC<EmptyStateProps> = ({
  title = 'Gösterilecek İçerik Bulunamadı',
  description = 'Bu bölümde henüz herhangi bir kayıt ya da veri yer almamaktadır.',
  actionLabel,
  onAction,
  icon = <Inbox className="w-12 h-12 text-slate-400 dark:text-slate-600" />,
}) => {
  return (
    <div className="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 my-4">
      <div className="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800 mb-4">
        {icon}
      </div>
      <h3 className="text-lg font-bold text-slate-900 dark:text-slate-100 mb-1">{title}</h3>
      <p className="text-sm text-slate-500 dark:text-slate-400 max-w-sm mb-6">{description}</p>
      {actionLabel && onAction && (
        <Button variant="primary" onClick={onAction}>
          {actionLabel}
        </Button>
      )}
    </div>
  );
};
