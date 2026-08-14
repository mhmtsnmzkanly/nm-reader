import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import {
  UserPreferences,
  AppearancePreferences,
  LanguagePreferences,
  ReaderPreferences,
  NotificationPreferences,
} from '../types/api';
import { AppLanguage, AppTheme } from '../types/domain';
import { userService } from '../services';
import { getTranslation } from '../i18n';
import { formatRelativeTime as utilFormatRelativeTime, formatDate as utilFormatDate } from '../utils/formatDate';

export const defaultUserPreferences: UserPreferences = {
  appearance: {
    theme: 'dark',
    accent: 'default',
  },
  language: {
    locale: 'tr',
  },
  reader: {
    layout: 'vertical',
    fit: 'width',
    direction: 'ltr',
    auto_hide_ui: true,
    show_progress: true,
    fontSize: '18',
    fontFamily: 'var(--font-sans)',
    lineHeight: '1.8',
    fontWeight: '400',
    imageFit: 'width',
    readingDirection: 'ltr',
  },
  notifications: {
    new_chapter: true,
    comments: true,
    replies: true,
    mentions: true,
    system: true,
  },
  account: {
    is_logged_in: true,
    email: 'deniz@example.test',
    last_sync: '2026-08-14T02:00:00Z',
  },
  lang: 'tr',
  theme: 'dark',
};

export type PresetType = 'default' | 'light_rtl' | 'minimal_notifications';

export const preferencePresets: Record<PresetType, Partial<UserPreferences>> = {
  default: {
    appearance: { theme: 'dark', accent: 'default' },
    language: { locale: 'tr' },
    reader: {
      layout: 'vertical',
      fit: 'width',
      direction: 'ltr',
      auto_hide_ui: true,
      show_progress: true,
      fontSize: '18',
      fontFamily: 'var(--font-sans)',
      lineHeight: '1.8',
      fontWeight: '400',
      imageFit: 'width',
      readingDirection: 'ltr',
    },
    notifications: {
      new_chapter: true,
      comments: true,
      replies: true,
      mentions: true,
      system: true,
    },
    lang: 'tr',
    theme: 'dark',
  },
  light_rtl: {
    appearance: { theme: 'light', accent: 'default' },
    language: { locale: 'tr' },
    reader: {
      layout: 'single',
      fit: 'height',
      direction: 'rtl',
      auto_hide_ui: false,
      show_progress: true,
      fontSize: '18',
      fontFamily: 'var(--font-sans)',
      lineHeight: '1.8',
      fontWeight: '400',
      imageFit: 'height',
      readingDirection: 'rtl',
    },
    notifications: {
      new_chapter: true,
      comments: true,
      replies: true,
      mentions: true,
      system: true,
    },
    lang: 'tr',
    theme: 'light',
  },
  minimal_notifications: {
    appearance: { theme: 'dark', accent: 'emerald' },
    language: { locale: 'tr' },
    reader: {
      layout: 'vertical',
      fit: 'width',
      direction: 'ltr',
      auto_hide_ui: true,
      show_progress: true,
      fontSize: '18',
      fontFamily: 'var(--font-sans)',
      lineHeight: '1.8',
      fontWeight: '400',
      imageFit: 'width',
      readingDirection: 'ltr',
    },
    notifications: {
      new_chapter: true,
      comments: false,
      replies: false,
      mentions: false,
      system: true,
    },
    lang: 'tr',
    theme: 'dark',
  },
};

type PreferencesContextType = {
  preferences: UserPreferences;
  appearance: AppearancePreferences;
  lang: AppLanguage;
  theme: AppTheme;
  accent: string;
  readerSettings: ReaderPreferences;
  notificationSettings: NotificationPreferences;
  isLoading: boolean;
  isSaving: boolean;
  isError: boolean;
  errorMessage: string | null;
  t: (keyPath: string, params?: Record<string, string | number>) => string;
  formatRelativeTime: (dateString: string) => string;
  formatDate: (dateString: string, options?: Intl.DateTimeFormatOptions) => string;
  setLanguage: (lang: AppLanguage) => Promise<void>;
  setTheme: (theme: AppTheme) => Promise<void>;
  setAccent: (accent: string) => Promise<void>;
  updateAppearance: (appearance: Partial<AppearancePreferences>) => Promise<void>;
  updateLanguage: (language: Partial<LanguagePreferences>) => Promise<void>;
  updateReaderSettings: (reader: Partial<ReaderPreferences>) => Promise<void>;
  updateNotificationSettings: (notifications: Partial<NotificationPreferences>) => Promise<void>;
  updatePreferences: (partial: Partial<UserPreferences>) => Promise<void>;
  resetToDefaults: () => Promise<void>;
  applyPreset: (preset: PresetType) => Promise<void>;
  reloadPreferences: () => Promise<void>;
};

const STORAGE_KEY = 'nm_user_preferences';

function mergePrefsWithDefaults(input: unknown): UserPreferences {
  if (!input || typeof input !== 'object') {
    return { ...defaultUserPreferences };
  }
  const obj = input as Record<string, unknown>;
  const parsedApp = (typeof obj.appearance === 'object' && obj.appearance !== null ? obj.appearance : {}) as Record<string, unknown>;
  const parsedLang = (typeof obj.language === 'object' && obj.language !== null ? obj.language : {}) as Record<string, unknown>;
  const parsedReader = (typeof obj.reader === 'object' && obj.reader !== null ? obj.reader : {}) as Record<string, unknown>;
  const parsedNotifs = (typeof obj.notifications === 'object' && obj.notifications !== null ? obj.notifications : {}) as Record<string, unknown>;
  const parsedAccount = (typeof obj.account === 'object' && obj.account !== null ? obj.account : {}) as Record<string, unknown>;

  const themeVal = (parsedApp.theme || obj.theme || defaultUserPreferences.appearance.theme) as AppearancePreferences['theme'];
  const accentVal = (parsedApp.accent || defaultUserPreferences.appearance.accent) as AppearancePreferences['accent'];
  const localeVal = (parsedLang.locale || obj.lang || defaultUserPreferences.language.locale) as LanguagePreferences['locale'];

  const fitVal = (parsedReader.fit || parsedReader.imageFit || defaultUserPreferences.reader.fit) as ReaderPreferences['fit'];
  const dirVal = (parsedReader.direction || parsedReader.readingDirection || defaultUserPreferences.reader.direction) as ReaderPreferences['direction'];
  const layoutVal = (parsedReader.layout || defaultUserPreferences.reader.layout) as ReaderPreferences['layout'];

  return {
    appearance: {
      theme: themeVal,
      accent: accentVal,
    },
    language: {
      locale: localeVal === 'en' ? 'en' : 'tr',
    },
    reader: {
      ...defaultUserPreferences.reader,
      ...parsedReader,
      layout: layoutVal,
      fit: fitVal,
      imageFit: fitVal,
      direction: dirVal,
      readingDirection: dirVal,
      auto_hide_ui: parsedReader.auto_hide_ui !== undefined ? Boolean(parsedReader.auto_hide_ui) : defaultUserPreferences.reader.auto_hide_ui,
      show_progress: parsedReader.show_progress !== undefined ? Boolean(parsedReader.show_progress) : defaultUserPreferences.reader.show_progress,
    },
    notifications: {
      new_chapter: parsedNotifs.new_chapter !== undefined ? Boolean(parsedNotifs.new_chapter) : defaultUserPreferences.notifications.new_chapter,
      comments: parsedNotifs.comments !== undefined ? Boolean(parsedNotifs.comments) : defaultUserPreferences.notifications.comments,
      replies: parsedNotifs.replies !== undefined ? Boolean(parsedNotifs.replies) : defaultUserPreferences.notifications.replies,
      mentions: parsedNotifs.mentions !== undefined ? Boolean(parsedNotifs.mentions) : defaultUserPreferences.notifications.mentions,
      system: parsedNotifs.system !== undefined ? Boolean(parsedNotifs.system) : defaultUserPreferences.notifications.system,
    },
    account: {
      is_logged_in: parsedAccount.is_logged_in !== undefined ? Boolean(parsedAccount.is_logged_in) : true,
      email: typeof parsedAccount.email === 'string' ? parsedAccount.email : 'deniz@example.test',
      last_sync: typeof parsedAccount.last_sync === 'string' ? parsedAccount.last_sync : new Date().toISOString(),
    },
    lang: localeVal === 'en' ? 'en' : 'tr',
    theme: themeVal,
  };
}

function getInitialPrefs(): UserPreferences {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      const parsed = JSON.parse(saved);
      return mergePrefsWithDefaults(parsed);
    }
  } catch (e) {
    // Ignore storage parse error and fallback safely
  }
  return { ...defaultUserPreferences };
}

const PreferencesContext = createContext<PreferencesContextType | undefined>(undefined);

export const PreferencesProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [preferences, setPreferences] = useState<UserPreferences>(getInitialPrefs);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [isSaving, setIsSaving] = useState<boolean>(false);
  const [isError, setIsError] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const fetchRemotePreferences = useCallback(async () => {
    setIsLoading(true);
    setIsError(false);
    setErrorMessage(null);
    try {
      const res = await userService.getPreferences();
      if (res.status === 'success' && res.data) {
        setPreferences((prev) => {
          const merged = mergePrefsWithDefaults({ ...prev, ...res.data });
          try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(merged));
          } catch (e) {
            // ignore localStorage quota error
          }
          return merged;
        });
      } else if (res.status === 'error') {
        setIsError(true);
        setErrorMessage(res.error?.message || 'Ayarlar yüklenemedi.');
      }
    } catch (err: any) {
      setIsError(true);
      setErrorMessage(err?.message || 'Bağlantı hatası oluştu.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchRemotePreferences();
  }, [fetchRemotePreferences]);

  // Sync theme & accent with HTML root attributes and system theme detection
  useEffect(() => {
    const root = document.documentElement;
    const currentTheme = preferences.appearance?.theme || preferences.theme || 'dark';
    const currentAccent = preferences.appearance?.accent || 'default';

    const applyThemeToDOM = (resolvedDark: boolean) => {
      root.setAttribute('data-theme', resolvedDark ? 'dark' : 'light');
      if (resolvedDark) {
        root.classList.add('dark');
        root.classList.remove('light');
      } else {
        root.classList.remove('dark');
        root.classList.add('light');
      }
    };

    if (currentTheme === 'system') {
      const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
      applyThemeToDOM(mediaQuery.matches);

      const handler = (e: MediaQueryListEvent) => {
        applyThemeToDOM(e.matches);
      };
      mediaQuery.addEventListener('change', handler);
      return () => mediaQuery.removeEventListener('change', handler);
    } else {
      const isDark = currentTheme === 'dark' || currentTheme === 'default' || currentTheme === 'royal';
      applyThemeToDOM(isDark);
    }

    // Set accent attribute
    root.setAttribute('data-accent', currentAccent);
  }, [preferences.appearance?.theme, preferences.appearance?.accent, preferences.theme]);

  // Base persistent updater
  const persistPreferences = async (updated: UserPreferences) => {
    setPreferences(updated);
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
    } catch (e) {
      // ignore
    }
    setIsSaving(true);
    try {
      await userService.updatePreferences(updated);
    } catch (e) {
      console.warn('Failed to sync preferences with server:', e);
    } finally {
      setIsSaving(false);
    }
  };

  const setLanguage = async (newLang: AppLanguage) => {
    const updated = mergePrefsWithDefaults({
      ...preferences,
      language: { locale: newLang },
      lang: newLang,
    });
    await persistPreferences(updated);
  };

  const setTheme = async (newTheme: AppTheme) => {
    const updated = mergePrefsWithDefaults({
      ...preferences,
      appearance: {
        ...preferences.appearance,
        theme: newTheme,
      },
      theme: newTheme,
    });
    await persistPreferences(updated);
  };

  const setAccent = async (newAccent: string) => {
    const updated = mergePrefsWithDefaults({
      ...preferences,
      appearance: {
        ...preferences.appearance,
        accent: newAccent,
      },
    });
    await persistPreferences(updated);
  };

  const updateAppearance = async (appearance: Partial<AppearancePreferences>) => {
    const updated = mergePrefsWithDefaults({
      ...preferences,
      appearance: {
        ...preferences.appearance,
        ...appearance,
      },
      theme: appearance.theme || preferences.appearance?.theme || preferences.theme,
    });
    await persistPreferences(updated);
  };

  const updateLanguage = async (language: Partial<LanguagePreferences>) => {
    const updated = mergePrefsWithDefaults({
      ...preferences,
      language: {
        ...preferences.language,
        ...language,
      },
      lang: language.locale || preferences.language?.locale || preferences.lang,
    });
    await persistPreferences(updated);
  };

  const updateReaderSettings = async (reader: Partial<ReaderPreferences>) => {
    const updated = mergePrefsWithDefaults({
      ...preferences,
      reader: {
        ...preferences.reader,
        ...reader,
      },
    });
    await persistPreferences(updated);
  };

  const updateNotificationSettings = async (notifications: Partial<NotificationPreferences>) => {
    const updated = mergePrefsWithDefaults({
      ...preferences,
      notifications: {
        ...preferences.notifications,
        ...notifications,
      },
    });
    await persistPreferences(updated);
  };

  const updatePreferences = async (partial: Partial<UserPreferences>) => {
    const updated = mergePrefsWithDefaults({
      ...preferences,
      ...partial,
      appearance: {
        ...preferences.appearance,
        ...(partial.appearance || {}),
      },
      language: {
        ...preferences.language,
        ...(partial.language || {}),
      },
      reader: {
        ...preferences.reader,
        ...(partial.reader || {}),
      },
      notifications: {
        ...preferences.notifications,
        ...(partial.notifications || {}),
      },
    });
    await persistPreferences(updated);
  };

  const resetToDefaults = async () => {
    const reset = { ...defaultUserPreferences, account: { ...preferences.account, last_sync: new Date().toISOString() } };
    await persistPreferences(reset);
  };

  const applyPreset = async (presetName: PresetType) => {
    const preset = preferencePresets[presetName];
    if (!preset) return;
    const updated = mergePrefsWithDefaults({
      ...preferences,
      ...preset,
    });
    await persistPreferences(updated);
  };

  const t = (keyPath: string, params?: Record<string, string | number>) => {
    const activeLocale = preferences.language?.locale || preferences.lang || 'tr';
    return getTranslation(activeLocale, keyPath, params);
  };

  const formatRelativeTime = useCallback(
    (dateString: string) => {
      const activeLocale = (preferences.language?.locale || preferences.lang || 'tr') as 'tr' | 'en';
      return utilFormatRelativeTime(dateString, activeLocale);
    },
    [preferences.language?.locale, preferences.lang]
  );

  const formatDate = useCallback(
    (dateString: string, options?: Intl.DateTimeFormatOptions) => {
      const activeLocale = (preferences.language?.locale || preferences.lang || 'tr') as 'tr' | 'en';
      return utilFormatDate(dateString, activeLocale, options);
    },
    [preferences.language?.locale, preferences.lang]
  );

  return (
    <PreferencesContext.Provider
      value={{
        preferences,
        appearance: preferences.appearance,
        lang: preferences.language?.locale || preferences.lang || 'tr',
        theme: preferences.appearance?.theme || preferences.theme || 'dark',
        accent: preferences.appearance?.accent || 'default',
        readerSettings: preferences.reader,
        notificationSettings: preferences.notifications,
        isLoading,
        isSaving,
        isError,
        errorMessage,
        t,
        formatRelativeTime,
        formatDate,
        setLanguage,
        setTheme,
        setAccent,
        updateAppearance,
        updateLanguage,
        updateReaderSettings,
        updateNotificationSettings,
        updatePreferences,
        resetToDefaults,
        applyPreset,
        reloadPreferences: fetchRemotePreferences,
      }}
    >
      {children}
    </PreferencesContext.Provider>
  );
};

export const usePreferences = () => {
  const context = useContext(PreferencesContext);
  if (!context) {
    throw new Error('usePreferences must be used within a PreferencesProvider');
  }
  return context;
};
