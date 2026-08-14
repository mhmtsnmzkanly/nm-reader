import React from 'react';
import { Lock } from 'lucide-react';
import { Button } from '../ui/Button';
import { useAuth } from '../../contexts/AuthContext';

type LoginPromptProps = {
  message?: string;
};

export const LoginPrompt: React.FC<LoginPromptProps> = ({
  message = 'Bu özelliği kullanabilmek ve takibe alabilmek için giriş yapmalısınız.',
}) => {
  const { openAuthModal } = useAuth();

  return (
    <div className="flex flex-col items-center justify-center p-8 text-center rounded-2xl border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/40 dark:bg-indigo-950/20 my-4">
      <div className="p-3.5 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 mb-3">
        <Lock className="w-8 h-8" />
      </div>
      <h4 className="text-base font-bold text-slate-900 dark:text-slate-100 mb-1">Giriş Yapılması Gerekiyor</h4>
      <p className="text-sm text-slate-600 dark:text-slate-400 max-w-sm mb-5">{message}</p>
      <div className="flex items-center gap-3">
        <Button variant="primary" size="sm" onClick={() => openAuthModal('login')} className="cursor-pointer">
          Giriş Yap
        </Button>
        <Button variant="outline" size="sm" onClick={() => openAuthModal('register')} className="cursor-pointer">
          Kayıt Ol
        </Button>
      </div>
    </div>
  );
};
