import React, { useState } from 'react';
import { User } from 'lucide-react';

type AvatarProps = {
  src?: string | null;
  alt?: string;
  name?: string;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  className?: string;
  ring?: boolean;
};

export const Avatar: React.FC<AvatarProps> = ({
  src,
  alt,
  name,
  size = 'md',
  className = '',
  ring = false,
}) => {
  const [hasError, setHasError] = useState(false);

  const sizeClasses = {
    sm: 'w-8 h-8 text-xs',
    md: 'w-12 h-12 text-sm',
    lg: 'w-20 h-20 text-xl',
    xl: 'w-28 h-28 sm:w-32 sm:h-32 text-2xl sm:text-3xl',
  };

  const getInitials = (text?: string): string => {
    if (!text) return '';
    const parts = text.trim().split(/\s+/);
    if (parts.length === 1) {
      return parts[0].substring(0, 2).toUpperCase();
    }
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  };

  const initials = getInitials(name || alt);
  const ringClass = ring
    ? 'ring-4 ring-[var(--bg-card)] shadow-xl'
    : 'border border-[var(--border-color)]';

  if (src && !hasError) {
    return (
      <div
        className={`relative inline-block rounded-full overflow-hidden shrink-0 bg-[var(--bg-tertiary)] select-none ${sizeClasses[size]} ${ringClass} ${className}`}
      >
        <img
          src={src}
          alt={alt || name || 'Avatar'}
          referrerPolicy="no-referrer"
          className="w-full h-full object-cover"
          onError={() => setHasError(true)}
        />
      </div>
    );
  }

  return (
    <div
      className={`relative inline-flex items-center justify-center rounded-full shrink-0 bg-[var(--accent-light)] text-[var(--accent-color)] font-serif font-bold uppercase select-none ${sizeClasses[size]} ${ringClass} ${className}`}
      title={name || alt}
    >
      {initials ? initials : <User className="w-1/2 h-1/2" />}
    </div>
  );
};
