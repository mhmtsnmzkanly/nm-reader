import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { MeData } from '../types/api';
import { userService } from '../services';

function getBootstrapMe(): MeData | null {
  if (typeof window === 'undefined') return null;
  const auth = window.__NMR_CONTEXT?.auth;
  if (!auth || auth.is_logged_in === false) return null;
  if (!auth.profile || !auth.preferences || !auth.wallet) return null;
  if (!auth.notifications || !Array.isArray(auth.notifications.items)) return null;
  return {
    is_logged_in: auth.is_logged_in,
    user_id: auth.user_id,
    username: auth.username,
    roles: auth.roles,
    permissions: auth.permissions,
    csrf_token: auth.csrf_token,
    profile: auth.profile,
    preferences: auth.preferences,
    wallet: auth.wallet,
    notifications: auth.notifications,
  };
}

function syncBootstrapAuth(snapshot: MeData | null): void {
  if (typeof window === 'undefined') return;
  const context = window.__NMR_CONTEXT ?? (window.__NMR_CONTEXT = {});
  const auth = context.auth ?? (context.auth = {});
  if (snapshot) {
    Object.assign(auth, snapshot, {
      is_logged_in: true,
    });
    return;
  }

  delete auth.profile;
  delete auth.preferences;
  delete auth.wallet;
  delete auth.notifications;
  auth.is_logged_in = false;
  auth.user_id = undefined;
  auth.username = undefined;
  auth.roles = [];
  auth.permissions = [];
}

function shouldAttemptFallback(): boolean {
  if (typeof window === 'undefined') return false;
  const auth = window.__NMR_CONTEXT?.auth;
  // An explicit logged-out context does not need a protected /me request.
  return !(auth && auth.is_logged_in === false);
}

type MeContextValue = {
  me: MeData | null;
  isLoading: boolean;
  refreshMe: () => Promise<MeData | null>;
};

const MeContext = createContext<MeContextValue | undefined>(undefined);

export const MeProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const bootstrap = getBootstrapMe();
  const [me, setMe] = useState<MeData | null>(bootstrap);
  const [isLoading, setIsLoading] = useState(bootstrap === null && shouldAttemptFallback());
  const requestRef = useRef<Promise<MeData | null> | null>(null);

  const refreshMe = useCallback(async (): Promise<MeData | null> => {
    if (requestRef.current) return requestRef.current;

    const request = (async () => {
      setIsLoading(true);
      try {
        const response = await userService.getMe();
        if (response.status === 'success' && response.data) {
          setMe(response.data);
          syncBootstrapAuth(response.data);
          return response.data;
        }
        setMe(null);
        syncBootstrapAuth(null);
        return null;
      } catch {
        setMe(null);
        syncBootstrapAuth(null);
        return null;
      } finally {
        setIsLoading(false);
      }
    })();

    requestRef.current = request;
    try {
      return await request;
    } finally {
      requestRef.current = null;
    }
  }, []);

  useEffect(() => {
    if (bootstrap !== null || !shouldAttemptFallback()) return;
    void refreshMe();
  }, [bootstrap, refreshMe]);

  return <MeContext.Provider value={{ me, isLoading, refreshMe }}>{children}</MeContext.Provider>;
};

export const useMe = (): MeContextValue => {
  const context = useContext(MeContext);
  if (!context) throw new Error('useMe must be used within a MeProvider');
  return context;
};
