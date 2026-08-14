import React from 'react';

type StatCardProps = {
  label: string;
  value: number | string;
  icon?: React.ReactNode;
  hint?: string;
  className?: string;
  onClick?: () => void;
};

export const StatCard: React.FC<StatCardProps> = ({
  label,
  value,
  icon,
  hint,
  className = '',
  onClick,
}) => {
  return (
    <div
      onClick={onClick}
      className={`p-4 bg-[var(--bg-card)] border border-[var(--border-color)] rounded-2xl flex items-center justify-between transition-all ${
        onClick ? 'cursor-pointer hover:border-[var(--accent-color)] hover:shadow-md' : ''
      } ${className}`}
    >
      <div className="flex flex-col gap-0.5">
        <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
          {label}
        </span>
        <span className="font-serif text-2xl font-bold text-[var(--text-primary)]">
          {typeof value === 'number' ? value.toLocaleString('tr-TR') : value}
        </span>
        {hint && (
          <span className="text-[11px] text-[var(--text-secondary)] mt-0.5">
            {hint}
          </span>
        )}
      </div>
      {icon && (
        <div className="p-2.5 rounded-xl bg-[var(--accent-light)] text-[var(--accent-color)] shrink-0">
          {icon}
        </div>
      )}
    </div>
  );
};
