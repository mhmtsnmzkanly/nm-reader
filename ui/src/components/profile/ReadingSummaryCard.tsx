import React from 'react';
import { BookOpen, CheckCircle2, Clock, Sparkles } from 'lucide-react';
import { ReadingSummary } from '../../types/api';

type ReadingSummaryCardProps = {
  reading?: ReadingSummary;
  className?: string;
};

export const ReadingSummaryCard: React.FC<ReadingSummaryCardProps> = ({
  reading = { chapters_read: 0, completed_series: 0, ongoing_series: 0 },
  className = '',
}) => {
  const totalSeries = (reading.completed_series || 0) + (reading.ongoing_series || 0);
  const completionRate =
    totalSeries > 0 ? Math.round(((reading.completed_series || 0) / totalSeries) * 100) : 0;

  return (
    <div
      className={`p-6 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl flex flex-col gap-6 shadow-sm ${className}`}
    >
      <div className="flex items-center justify-between border-b border-[var(--border-color)] pb-4">
        <div className="flex items-center gap-2.5">
          <div className="p-2 rounded-xl bg-[var(--accent-light)] text-[var(--accent-color)]">
            <Sparkles className="w-5 h-5" />
          </div>
          <div>
            <h3 className="font-serif text-base font-bold text-[var(--text-primary)]">
              Okuma Özeti
            </h3>
            <p className="text-xs text-[var(--text-muted)]">
              Okuma alışkanlıkları ve seri tamamlama durumu
            </p>
          </div>
        </div>

        {totalSeries > 0 && (
          <div className="hidden sm:flex items-center gap-2 px-3 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-full text-xs font-mono text-[var(--text-secondary)]">
            <span>Tamamlama Oranı:</span>
            <span className="font-bold text-[var(--accent-color)]">%{completionRate}</span>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="p-4 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)]/60 flex items-center gap-3">
          <div className="p-3 rounded-lg bg-[var(--accent-light)] text-[var(--accent-color)] shrink-0">
            <BookOpen className="w-5 h-5" />
          </div>
          <div className="flex flex-col">
            <span className="text-[11px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
              Okunan Bölümler
            </span>
            <span className="font-serif text-xl font-bold text-[var(--text-primary)]">
              {reading.chapters_read.toLocaleString('tr-TR')}
            </span>
          </div>
        </div>

        <div className="p-4 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)]/60 flex items-center gap-3">
          <div className="p-3 rounded-lg bg-emerald-500/10 text-emerald-500 shrink-0">
            <CheckCircle2 className="w-5 h-5" />
          </div>
          <div className="flex flex-col">
            <span className="text-[11px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
              Biten Seriler
            </span>
            <span className="font-serif text-xl font-bold text-[var(--text-primary)]">
              {reading.completed_series} Seri
            </span>
          </div>
        </div>

        <div className="p-4 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)]/60 flex items-center gap-3">
          <div className="p-3 rounded-lg bg-amber-500/10 text-amber-500 shrink-0">
            <Clock className="w-5 h-5" />
          </div>
          <div className="flex flex-col">
            <span className="text-[11px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
              Devam Edenler
            </span>
            <span className="font-serif text-xl font-bold text-[var(--text-primary)]">
              {reading.ongoing_series} Seri
            </span>
          </div>
        </div>
      </div>
    </div>
  );
};
