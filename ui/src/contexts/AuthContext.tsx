import React, { createContext, useContext, useEffect, useState } from 'react';
import { UserProfile } from '../types/api';
import { ScenarioType } from '../types/domain';
import { authService, userService } from '../services';
import { scenarioManager } from '../mocks/scenarios';

type AuthContextType = {
  user: UserProfile | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  scenario: ScenarioType;
  setScenario: (sc: ScenarioType) => void;
  login: (email: string, pass: string, remember: boolean) => Promise<boolean>;
  register: (uname: string, email: string, pass: string) => Promise<boolean>;
  logout: () => Promise<void>;
  refreshProfile: () => Promise<void>;
  isAuthModalOpen: boolean;
  authModalTab: 'login' | 'register';
  openAuthModal: (tab?: 'login' | 'register') => void;
  closeAuthModal: () => void;
  isNotificationsModalOpen: boolean;
  openNotificationsModal: () => void;
  closeNotificationsModal: () => void;
};

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<UserProfile | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [scenario, setScenarioState] = useState<ScenarioType>(scenarioManager.getScenario());
  const [isAuthModalOpen, setIsAuthModalOpen] = useState<boolean>(false);
  const [authModalTab, setAuthModalTab] = useState<'login' | 'register'>('login');
  const [isNotificationsModalOpen, setIsNotificationsModalOpen] = useState<boolean>(false);

  const openAuthModal = (tab: 'login' | 'register' = 'login') => {
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
    setIsLoading(true);
    try {
      const res = await userService.getProfile();
      if (res.status === 'success') {
        setUser(res.data);
      } else {
        setUser(null);
      }
    } catch {
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchProfile();
    const unsub = scenarioManager.subscribe((newSc) => {
      setScenarioState(newSc);
      fetchProfile();
    });
    return unsub;
  }, []);

  const setScenario = (sc: ScenarioType) => {
    scenarioManager.setScenario(sc);
  };

  const login = async (email: string, pass: string, remember: boolean) => {
    const res = await authService.login(email, pass, remember);
    if (res.status === 'success') {
      await fetchProfile();
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
    await fetchProfile();
  };

  const isAuthenticated = !!user && !user.is_guest;

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated,
        isLoading,
        scenario,
        setScenario,
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
