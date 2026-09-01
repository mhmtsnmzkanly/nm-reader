import React, { useEffect, useState, useCallback } from 'react';
import {
  Laptop,
  Smartphone,
  Tablet,
  Globe,
  Clock,
  ShieldCheck,
  Trash2,
  AlertTriangle,
  Loader2,
  CheckCircle2,
} from 'lucide-react';
import { authService } from '../../services';
import { UserSession } from '../../types/api';
import { usePreferences } from '../../contexts/PreferencesContext';

export const SessionManager: React.FC = () => {
  const { t, formatRelativeTime } = usePreferences();
  const [sessions, setSessions] = useState<UserSession[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [revokingId, setRevokingId] = useState<string | null>(null);
  const [isRevokingOther, setIsRevokingOther] = useState<boolean>(false);
  const [feedbackMessage, setFeedbackMessage] = useState<{
    type: 'success' | 'error';
    text: string;
  } | null>(null);

  const loadSessions = useCallback(async () => {
    setIsLoading(true);
    const res = await authService.getSessions();
    if (res.status === 'success' && res.data) {
      setSessions(res.data);
    }
    setIsLoading(false);
  }, []);

  useEffect(() => {
    loadSessions();
  }, [loadSessions]);

  const showFeedback = (type: 'success' | 'error', text: string) => {
    setFeedbackMessage({ type, text });
    setTimeout(() => {
      setFeedbackMessage(null);
    }, 4000);
  };

  const handleRevokeSingle = async (sessionId: string) => {
    if (!window.confirm(t('sessions.confirmRevoke'))) return;

    setRevokingId(sessionId);
    const res = await authService.revokeSession(sessionId);
    if (res.status === 'success') {
      setSessions((prev) => prev.filter((s) => s.id !== sessionId));
      showFeedback('success', t('sessions.revokeSuccess'));
    } else {
      showFeedback('error', res.error.message || t('common.error'));
    }
    setRevokingId(null);
  };

  const handleRevokeOther = async () => {
    if (!window.confirm(t('sessions.confirmRevokeOther'))) return;

    setIsRevokingOther(true);
    const res = await authService.revokeOtherSessions();
    if (res.status === 'success') {
      setSessions((prev) => prev.filter((s) => s.is_current));
      showFeedback('success', t('sessions.revokeOtherSuccess'));
    } else {
      showFeedback('error', res.error.message || t('common.error'));
    }
    setIsRevokingOther(false);
  };

  const getDeviceIcon = (deviceInfo?: string, userAgent?: string) => {
    const combined = `${deviceInfo || ''} ${userAgent || ''}`.toLowerCase();
    if (combined.includes('iphone') || combined.includes('android') || combined.includes('mobile')) {
      return Smartphone;
    }
    if (combined.includes('ipad') || combined.includes('tablet')) {
      return Tablet;
    }
    return Laptop;
  };

  const otherSessionsCount = sessions.filter((s) => !s.is_current).length;

  return (
    <div className="flex flex-col gap-6">
      {/* Header & Actions */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border-color)] pb-4">
        <div>
          <h2 className="text-lg sm:text-xl font-bold font-serif text-[var(--text-primary)]">
            {t('sessions.title')}
          </h2>
          <p className="text-xs text-[var(--text-secondary)] mt-0.5">
            {t('sessions.subtitle')}
          </p>
        </div>

        {otherSessionsCount > 0 && (
          <button
            onClick={handleRevokeOther}
            disabled={isRevokingOther}
            className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 hover:bg-rose-500/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer self-start sm:self-auto"
          >
            {isRevokingOther ? (
              <Loader2 className="w-3.5 h-3.5 animate-spin" />
            ) : (
              <Trash2 className="w-3.5 h-3.5" />
            )}
            <span>{t('sessions.revokeOther')}</span>
          </button>
        )}
      </div>

      {/* Feedback Banner */}
      {feedbackMessage && (
        <div
          className={`p-3.5 rounded-xl text-xs font-medium flex items-center gap-2 transition-all ${
            feedbackMessage.type === 'success'
              ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400'
              : 'bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400'
          }`}
        >
          {feedbackMessage.type === 'success' ? (
            <CheckCircle2 className="w-4 h-4 shrink-0" />
          ) : (
            <AlertTriangle className="w-4 h-4 shrink-0" />
          )}
          <span>{feedbackMessage.text}</span>
        </div>
      )}

      {/* Loading state */}
      {isLoading ? (
        <div className="flex flex-col gap-3">
          {[1, 2, 3].map((i) => (
            <div
              key={i}
              className="h-24 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl animate-pulse"
            />
          ))}
        </div>
      ) : sessions.length === 0 ? (
        <div className="p-8 text-center border border-dashed border-[var(--border-color)] rounded-2xl text-[var(--text-muted)] text-xs font-mono">
          {t('sessions.empty')}
        </div>
      ) : (
        <div className="flex flex-col gap-3.5">
          {sessions.map((session) => {
            const Icon = getDeviceIcon(session.device_info, session.user_agent);
            const isCurrent = !!session.is_current;
            const isRevokingThis = revokingId === session.id;

            return (
              <div
                key={session.id}
                className={`p-4 sm:p-5 rounded-2xl border transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 ${
                  isCurrent
                    ? 'bg-[var(--accent-light)]/40 border-[var(--accent-color)] shadow-xs'
                    : 'bg-[var(--bg-card)] border-[var(--border-color)] hover:border-[var(--border-color-hover)]'
                }`}
              >
                {/* Left Side: Device & Meta */}
                <div className="flex items-start sm:items-center gap-3.5 min-w-0">
                  <div
                    className={`w-11 h-11 rounded-xl flex items-center justify-center shrink-0 border ${
                      isCurrent
                        ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] shadow-xs'
                        : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)]'
                    }`}
                  >
                    <Icon className="w-5 h-5" />
                  </div>

                  <div className="flex flex-col gap-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-semibold text-sm text-[var(--text-primary)] font-serif">
                        {session.device_info || 'Unknown Device'}
                      </span>
                      {isCurrent && (
                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-[var(--accent-color)] text-white">
                          <ShieldCheck className="w-3 h-3" />
                          {t('sessions.currentDevice')}
                        </span>
                      )}
                    </div>

                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[var(--text-muted)] font-mono">
                      <span className="flex items-center gap-1">
                        <Globe className="w-3 h-3 text-[var(--text-muted)]" />
                        {session.ip_address || '127.0.0.1'}
                        {session.location ? ` (${session.location})` : ''}
                      </span>

                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3 text-[var(--text-muted)]" />
                        {session.last_active_at
                          ? formatRelativeTime(session.last_active_at)
                          : formatRelativeTime(session.created_at)}
                      </span>
                    </div>

                    {session.user_agent && (
                      <span className="text-[11px] text-[var(--text-muted)] truncate max-w-md hidden sm:block">
                        {session.user_agent}
                      </span>
                    )}
                  </div>
                </div>

                {/* Right Side: Revoke Action */}
                {!isCurrent && (
                  <button
                    onClick={() => handleRevokeSingle(session.id)}
                    disabled={isRevokingThis}
                    className="self-end sm:self-center px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-500 hover:text-rose-600 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer flex items-center gap-1.5"
                  >
                    {isRevokingThis ? (
                      <Loader2 className="w-3.5 h-3.5 animate-spin" />
                    ) : (
                      <Trash2 className="w-3.5 h-3.5" />
                    )}
                    <span>{t('sessions.revoke')}</span>
                  </button>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};
