import React from 'react';
import { Loader2 } from 'lucide-react';

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'outline' | 'danger' | 'ghost' | 'gold';
  size?: 'sm' | 'md' | 'lg';
  isLoading?: boolean;
  fullWidth?: boolean;
};

export const Button: React.FC<ButtonProps> = ({
  children,
  variant = 'primary',
  size = 'md',
  isLoading = false,
  fullWidth = false,
  className = '',
  disabled,
  ...props
}) => {
  const baseStyles =
    'inline-flex items-center justify-center font-bold uppercase tracking-[0.15em] transition-all duration-150 focus:outline-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed select-none';

  const sizeStyles = {
    sm: 'px-3 py-1.5 text-[10px] rounded-lg gap-1.5',
    md: 'px-5 py-2.5 text-xs rounded-lg gap-2',
    lg: 'px-8 py-3.5 text-xs rounded-xl gap-2.5',
  };

  const variantStyles = {
    primary:
      'bg-[var(--accent-color)] hover:opacity-90 text-white shadow-md shadow-[var(--accent-color)]/20 active:scale-[0.98]',
    gold:
      'bg-[var(--accent-color)] hover:opacity-90 text-white shadow-md shadow-[var(--accent-color)]/20 active:scale-[0.98]',
    secondary:
      'bg-[var(--bg-tertiary)] hover:bg-[var(--border-color)] text-[var(--text-primary)] border border-[var(--border-color)] active:scale-[0.98]',
    outline:
      'border border-[var(--border-color)] hover:border-[var(--accent-color)] text-[var(--text-primary)] hover:text-[var(--accent-color)] bg-transparent active:scale-[0.98]',
    danger:
      'bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 border border-rose-500/30 active:scale-[0.98]',
    ghost:
      'text-[var(--text-muted)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-tertiary)] bg-transparent',
  };

  const widthStyle = fullWidth ? 'w-full' : '';

  return (
    <button
      className={`${baseStyles} ${sizeStyles[size]} ${variantStyles[variant]} ${widthStyle} ${className}`}
      disabled={disabled || isLoading}
      {...props}
    >
      {isLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : children}
    </button>
  );
};
