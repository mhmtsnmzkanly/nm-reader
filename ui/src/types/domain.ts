// Domain and UI state types for nm-reader

import { ContentType, UserPreferences } from './api';

export type ThemeMode = 'dark' | 'light' | 'system';
export type AccentColor = 'default' | 'emerald' | 'amber' | 'rose' | 'cyan' | 'purple';

export type AppTheme =
  | 'default'
  | 'dark'
  | 'light'
  | 'system'
  | 'royal'
  | 'bootstrap'
  | 'material'
  | 'apple'
  | 'glass';

export type AppLanguage = 'tr' | 'en';

export type ScenarioType =
  | 'normal_authenticated'
  | 'normal_guest'
  | 'session_expired'
  | 'insufficient_coins'
  | 'network_error'
  | 'empty_data'
  | 'forbidden_commenting'
  | 'maintenance';

export type ReaderSettingsState = UserPreferences['reader'];

export type ProfileTab = 'overview' | 'library' | 'history' | 'following' | 'sessions' | 'activity';

export type FilterState = {
  type?: ContentType | 'all';
  status?: string;
  genre?: string;
  tag?: string;
  sort?: 'EN YENİLER' | 'EN ÇOK OKUNAN' | 'EN YÜKSEK PUAN' | 'TÜMÜ';
  q?: string;
  page?: number;
};
