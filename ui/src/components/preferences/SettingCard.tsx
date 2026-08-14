import React from 'react';

type SettingCardProps = {
  id: string;
  title: string;
  description?: string;
  icon?: React.ReactNode;
  badge?: string;
  children: React.ReactNode;
};

export const SettingCard: React.FC<SettingCardProps> = ({
  id,
  title,
  description,
  icon,
  badge,
  children,
}) => {
  return (
    <section
      id={`setting-card-${id}`}
      aria-labelledby={`setting-title-${id}`}
      className="bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)] p-5 sm:p-6 shadow-sm flex flex-col gap-5 transition-all duration-300"
    >
      <div className="flex items-center justify-between gap-4 border-b border-[var(--border-color)]/70 pb-4">
        <div className="flex items-center gap-3">
          {icon && (
            <div className="p-2 rounded-xl bg-[var(--accent-light)] text-[var(--accent-color)] border border-[var(--accent-border)] shrink-0">
              {icon}
            </div>
          )}
          <div className="flex flex-col">
            <div className="flex items-center gap-2">
              <h2
                id={`setting-title-${id}`}
                className="text-sm sm:text-base font-bold text-[var(--text-primary)] font-serif"
              >
                {title}
              </h2>
              {badge && (
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-[var(--accent-light)] text-[var(--accent-color)] border border-[var(--accent-border)]">
                  {badge}
                </span>
              )}
            </div>
            {description && (
              <p className="text-xs text-[var(--text-muted)] mt-0.5 leading-relaxed">
                {description}
              </p>
            )}
          </div>
        </div>
      </div>

      <div className="flex flex-col gap-3">{children}</div>
    </section>
  );
};
