import React from 'react';
import { Wrench, RefreshCw } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { usePreferences } from '../../contexts/PreferencesContext';
import { Button } from '../ui/Button';

export const MaintenanceOverlay: React.FC = () => {
  const { scenario, setScenario } = useAuth();
  const { t } = usePreferences();

  if (scenario !== 'maintenance') {
    return null;
  }

  const handleRetry = () => {
    setScenario('normal_authenticated');
  };

  return (
    <div className="fixed inset-0 z-[100] bg-black/80 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-300">
      <div className="w-full max-w-md bg-[var(--bg-card)] border border-[var(--border-color)] rounded-3xl p-8 text-center shadow-2xl">
        <div className="w-16 h-16 rounded-2xl bg-amber-500/10 text-amber-500 border border-amber-500/20 flex items-center justify-center mx-auto mb-5 shadow-inner">
          <Wrench className="w-8 h-8 animate-pulse" />
        </div>

        <h2 className="text-2xl font-bold text-[var(--text-primary)] mb-2">
          {t('auth.maintenanceTitle')}
        </h2>

        <p className="text-sm text-[var(--text-secondary)] mb-6 leading-relaxed">
          {t('auth.maintenanceDesc')}
        </p>

        <Button
          variant="gold"
          size="lg"
          onClick={handleRetry}
          fullWidth
          className="flex items-center justify-center gap-2 cursor-pointer bg-[var(--accent-color)] text-white hover:opacity-90 font-bold"
        >
          <RefreshCw className="w-4 h-4" />
          <span>{t('auth.maintenanceRetry')}</span>
        </Button>
      </div>
    </div>
  );
};
