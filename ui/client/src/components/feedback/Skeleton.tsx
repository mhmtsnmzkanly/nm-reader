import React from 'react';

type SkeletonProps = {
  className?: string;
  variant?: 'text' | 'card' | 'avatar' | 'rect';
};

export const Skeleton: React.FC<SkeletonProps> = ({
  className = '',
  variant = 'text',
}) => {
  const baseStyle = 'animate-pulse bg-slate-200 dark:bg-slate-800 rounded-lg';

  const variantStyles = {
    text: 'h-4 w-full',
    card: 'h-72 w-full',
    avatar: 'h-12 w-12 rounded-full',
    rect: 'h-32 w-full',
  };

  return <div className={`${baseStyle} ${variantStyles[variant]} ${className}`} />;
};

export const ContentCardSkeleton: React.FC = () => (
  <div className="flex flex-col gap-3 p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl">
    <Skeleton variant="card" className="aspect-[3/4] h-auto" />
    <Skeleton variant="text" className="h-5 w-3/4" />
    <Skeleton variant="text" className="h-3 w-1/2" />
  </div>
);

export const ContentGridSkeleton: React.FC<{ count?: number }> = ({ count = 8 }) => (
  <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
    {Array.from({ length: count }).map((_, i) => (
      <ContentCardSkeleton key={i} />
    ))}
  </div>
);
