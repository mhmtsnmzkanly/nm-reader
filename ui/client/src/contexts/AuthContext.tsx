import React, { createContext, useContext, useState } from 'react';
import { UserProfile } from '../types/api';
import { authService } from '../services';
import { useMe } from './MeContext';

type AuthModalTab = 'login' | 'register' | 'forgot-password';

type AuthContextType = {
  user: UserProfile | null;
  roles: string[];
  permissions: string[];
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (email: string, pass: string, remember: boolean) => Promise<boolean>;
  register: (uname: string, email: string, pass: string) => Promise<boolean>;
  logout: () => Promise<void>;
  refreshProfile: () => Promise<void>;
  isAuthModalOpen: boolean;
  authModalTab: AuthModalTab;
  openAuthModal: (tab?: AuthModalTab) => void;
  closeAuthModal: () => void;
  isNotificationsModalOpen: boolean;
  openNotificationsModal: () => void;
  closeNotificationsModal: () => void;
};

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { me, isLoading, refreshMe } = useMe();
  const user = me?.profile ?? null;
  const roles = me?.roles ?? [];
  const permissions = me?.permissions ?? [];
  const [isAuthModalOpen, setIsAuthModalOpen] = useState<boolean>(false);
  const [authModalTab, setAuthModalTab] = useState<AuthModalTab>('login');
  const [isNotificationsModalOpen, setIsNotificationsModalOpen] = useState<boolean>(false);

  const openAuthModal = (tab: AuthModalTab = 'login') => {
    setAuthModalTab(tab);
    setIsAuthModalOpen(true);
  };

  const closeAuthModal = () => {
    setIsAuthModalOpen(false);
  };

  const openNotificationsModal = () => {
    setIsNotificationsModalOpen(true);
  };

  const closeNotificationsModal = () => {
    setIsNotificationsModalOpen(false);
  };

  const fetchProfile = async () => {
    await refreshMe();
  };

  const login = async (email: string, pass: string, remember: boolean) => {
    const res = await authService.login(email, pass, remember);
    if (res.status === 'success') {
      await refreshMe();
      return true;
    }
    return false;
  };

  const register = async (uname: string, email: string, pass: string) => {
    const res = await authService.register(uname, email, pass);
    return res.status === 'success';
  };

  const logout = async () => {
    await authService.logout();
    await refreshMe();
  };

  const isAuthenticated = !!user && !user.is_guest;

  return (
    <AuthContext.Provider
      value={{
        user,
        roles,
        permissions,
        isAuthenticated,
        isLoading,
        login,
        register,
        logout,
        refreshProfile: fetchProfile,
        isAuthModalOpen,
        authModalTab,
        openAuthModal,
        closeAuthModal,
        isNotificationsModalOpen,
        openNotificationsModal,
        closeNotificationsModal,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
