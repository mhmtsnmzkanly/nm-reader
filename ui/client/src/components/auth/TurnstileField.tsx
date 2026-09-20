import React from 'react';
import { Turnstile } from '@marsidev/react-turnstile';

type TurnstileFieldProps = {
  action: 'login' | 'register' | 'forgot_password';
  resetKey: number;
  onToken: (token: string) => void;
  onError: () => void;
};

export const TurnstileField: React.FC<TurnstileFieldProps> = ({
  action,
  resetKey,
  onToken,
  onError,
}) => {
  const siteKey = window.__NMR_CONTEXT?.integrations?.cloudflare_turnstile_site_key?.trim();
  if (!siteKey) return null;

  return (
    <Turnstile
      key={`${action}-${resetKey}`}
      siteKey={siteKey}
      onSuccess={onToken}
      onExpire={() => onToken('')}
      onError={() => {
        onToken('');
        onError();
      }}
      options={{
        action,
        appearance: 'interaction-only',
        language: window.__NMR_CONTEXT?.lang_code || 'tr',
        refreshExpired: 'auto',
        size: 'flexible',
        theme: 'auto',
      }}
      className="w-full min-h-[65px]"
    />
  );
};
