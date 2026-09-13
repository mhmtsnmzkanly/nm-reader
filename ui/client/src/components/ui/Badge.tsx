import React from 'react';

type BadgeProps = {
  children: React.ReactNode;
  variant?: 'primary' | 'secondary' | 'outline' | 'success' | 'warning' | 'gold';
  size?: 'sm' | 'md';
  className?: string;
};

export const Badge: React.FC<BadgeProps> = ({
  children,
  variant = 'secondary',
  size = 'md',
  className = '',
}) => {
  const sizeStyles = size === 'sm' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-xs';

  const variantStyles = {
    primary: 'bg-[var(--bg-tertiary)] text-[var(--text-primary)] border border-[var(--border-color)]',
    secondary: 'bg-[var(--bg-tertiary)] text-[var(--text-muted)] border border-[var(--border-color)]',
    outline: 'border border-[var(--border-color)] text-[var(--text-secondary)]',
    success: 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/30',
    warning: 'bg-amber-500/10 text-amber-500 border border-amber-500/30',
    gold: 'bg-[var(--accent-light)] text-[var(--accent-color)] border border-[var(--accent-border)]',
  };

  return (
    <span
      className={`inline-flex items-center font-semibold uppercase tracking-wider rounded-md whitespace-nowrap ${sizeStyles} ${variantStyles[variant]} ${className}`}
    >
      {children}
    </span>
  );
};
