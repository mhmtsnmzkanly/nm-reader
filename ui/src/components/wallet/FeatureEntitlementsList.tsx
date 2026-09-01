import React, { useEffect, useState, useCallback } from 'react';
import {
  ShieldCheck,
  Zap,
  Sparkles,
  Calendar,
  Clock,
  CheckCircle2,
  AlertCircle,
} from 'lucide-react';
import { walletService } from '../../services';
import { FeatureEntitlement } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

export const FeatureEntitlementsList: React.FC = () => {
  const { t, formatRelativeTime } = usePreferences();
  const [entitlements, setEntitlements] = useState<FeatureEntitlement[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    const res = await walletService.getFeatureEntitlements();
    if (res.status === 'success' && res.data) {
      setEntitlements(res.data);
    }
    setIsLoading(false);
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const getFeatureIcon = (key: string) => {
    if (key === 'ad_free') return ShieldCheck;
    if (key === 'early_access') return Zap;
    return Sparkles;
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-4">
        <div>
          <h2 className="text-lg sm:text-xl font-bold font-serif text-[var(--text-primary)]">
            {t('entitlements.title')}
          </h2>
          <p className="text-xs text-[var(--text-secondary)] mt-0.5">
            {t('entitlements.subtitle')}
          </p>
        </div>

        <span className="text-xs font-mono text-[var(--text-muted)] self-start sm:self-auto bg-[var(--bg-tertiary)] px-3 py-1.5 rounded-xl border border-[var(--border-color)]">
          {t('common.recordsCount', { count: entitlements.length })}
        </span>
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {[1, 2].map((i) => (
            <div
              key={i}
              className="h-32 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl animate-pulse"
            />
          ))}
        </div>
      ) : entitlements.length === 0 ? (
        <div className="p-10 text-center border border-dashed border-[var(--border-color)] rounded-2xl flex flex-col items-center gap-3">
          <div className="w-12 h-12 rounded-full bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] flex items-center justify-center">
            <Sparkles className="w-6 h-6" />
          </div>
          <h3 className="font-serif font-bold text-sm text-[var(--text-primary)]">
            {t('entitlements.empty')}
          </h3>
          <p className="text-xs text-[var(--text-secondary)] max-w-md">
            {t('entitlements.emptyDesc')}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {entitlements.map((item) => {
            const Icon = getFeatureIcon(item.feature_key);
            const isActive = item.is_active ?? (item.expires_at ? new Date(item.expires_at).getTime() > Date.now() : true);

            return (
              <div
                key={item.id}
                className={`p-5 rounded-2xl border transition-all flex flex-col justify-between gap-4 shadow-xs ${
                  isActive
                    ? 'bg-[var(--bg-card)] border-emerald-500/30 ring-1 ring-emerald-500/10'
                    : 'bg-[var(--bg-card)] border-[var(--border-color)] opacity-75'
                }`}
              >
                {/* Header */}
                <div className="flex items-start justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <div
                      className={`w-11 h-11 rounded-xl flex items-center justify-center shrink-0 border ${
                        isActive
                          ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                          : 'bg-[var(--bg-tertiary)] text-[var(--text-muted)] border-[var(--border-color)]'
                      }`}
                    >
                      <Icon className="w-5 h-5" />
                    </div>

                    <div className="flex flex-col">
                      <span className="font-serif font-bold text-sm text-[var(--text-primary)]">
                        {item.name || item.feature_key.toUpperCase()}
                      </span>
                      <span className="text-[11px] font-mono text-[var(--text-muted)]">
                        ID: #{item.id} • {item.source_type}
                      </span>
                    </div>
                  </div>

                  <span
                    className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider ${
                      isActive
                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                        : 'bg-zinc-500/10 text-zinc-500 border border-zinc-500/20'
                    }`}
                  >
                    {isActive ? (
                      <>
                        <CheckCircle2 className="w-3 h-3" />
                        {item.badge || t('entitlements.statusActive')}
                      </>
                    ) : (
                      <>
                        <AlertCircle className="w-3 h-3" />
                        {t('entitlements.statusExpired')}
                      </>
                    )}
                  </span>
                </div>

                {/* Description if present */}
                {item.description && (
                  <p className="text-xs text-[var(--text-secondary)] leading-relaxed">
                    {item.description}
                  </p>
                )}

                {/* Dates Meta */}
                <div className="flex items-center justify-between pt-3 border-t border-[var(--border-color)] text-xs text-[var(--text-muted)] font-mono">
                  {item.starts_at && (
                    <span className="flex items-center gap-1">
                      <Calendar className="w-3 h-3" />
                      {t('entitlements.startsAt')}: {formatRelativeTime(item.starts_at)}
                    </span>
                  )}

                  {item.expires_at ? (
                    <span className="flex items-center gap-1 text-[var(--text-primary)]">
                      <Clock className="w-3 h-3 text-[var(--accent-color)]" />
                      {t('entitlements.expiresAt')}: {new Date(item.expires_at).toLocaleDateString()}
                    </span>
                  ) : (
                    <span className="text-emerald-600 dark:text-emerald-400 font-semibold">
                      {t('entitlements.perpetual')}
                    </span>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};
