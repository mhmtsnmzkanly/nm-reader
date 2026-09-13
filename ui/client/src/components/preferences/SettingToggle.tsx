import React from 'react';

type SettingToggleProps = {
  id: string;
  label: string;
  description?: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
  icon?: React.ReactNode;
};

export const SettingToggle: React.FC<SettingToggleProps> = ({
  id,
  label,
  description,
  checked,
  onChange,
  disabled = false,
  icon,
}) => {
  return (
    <div
      id={`setting-toggle-${id}`}
      className={`flex items-center justify-between gap-4 py-3.5 px-4 rounded-xl transition-all ${
        disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-[var(--bg-tertiary)]/50'
      }`}
    >
      <div className="flex items-start gap-3 min-w-0 flex-1">
        {icon && (
          <div className="mt-0.5 text-[var(--accent-color)] shrink-0">
            {icon}
          </div>
        )}
        <div className="flex flex-col min-w-0">
          <label
            htmlFor={`toggle-input-${id}`}
            className="text-xs sm:text-sm font-semibold text-[var(--text-primary)] cursor-pointer select-none"
          >
            {label}
          </label>
          {description && (
            <p className="text-[11px] sm:text-xs text-[var(--text-muted)] mt-0.5 leading-relaxed">
              {description}
            </p>
          )}
        </div>
      </div>

      <button
        type="button"
        id={`toggle-input-${id}`}
        role="switch"
        aria-checked={checked}
        aria-label={label}
        disabled={disabled}
        onClick={() => !disabled && onChange(!checked)}
        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[var(--accent-color)] focus:ring-offset-2 focus:ring-offset-[var(--bg-card)] ${
          checked ? 'bg-[var(--accent-color)]' : 'bg-[var(--border-color)]'
        }`}
      >
        <span
          aria-hidden="true"
          className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out ${
            checked ? 'translate-x-5' : 'translate-x-0'
          }`}
        />
      </button>
    </div>
  );
};
