import React, { useState } from 'react';
import {
  Palette,
  Moon,
  Sun,
  Laptop,
  Globe,
  BookOpen,
  Bell,
  Sliders,
  RotateCcw,
  Check,
  CheckCircle2,
  AlertCircle,
  ShieldCheck,
  Layers,
  MessageSquare,
  CornerDownRight,
  AtSign,
  Sparkles,
  RefreshCw,
} from 'lucide-react';
import { usePreferences, PresetType } from '../contexts/PreferencesContext';
import { SettingCard } from '../components/preferences/SettingCard';
import { SettingToggle } from '../components/preferences/SettingToggle';
import { Dialog } from '../components/ui/Dialog';
import { Button } from '../components/ui/Button';

export const PreferencesPage: React.FC = () => {
  const {
    preferences,
    appearance,
    lang,
    theme,
    accent,
    readerSettings,
    notificationSettings,
    isLoading,
    isSaving,
    isError,
    errorMessage,
    t,
    setLanguage,
    setTheme,
    setAccent,
    updateReaderSettings,
    updateNotificationSettings,
    resetToDefaults,
    applyPreset,
    reloadPreferences,
  } = usePreferences();

  const [isResetDialogOpen, setIsResetDialogOpen] = useState<boolean>(false);
  const [saveSuccessMessage, setSaveSuccessMessage] = useState<boolean>(false);

  const handleApplyPreset = async (presetKey: PresetType) => {
    await applyPreset(presetKey);
    setSaveSuccessMessage(true);
    setTimeout(() => setSaveSuccessMessage(false), 3000);
  };

  const handleConfirmReset = async () => {
    await resetToDefaults();
    setIsResetDialogOpen(false);
    setSaveSuccessMessage(true);
    setTimeout(() => setSaveSuccessMessage(false), 3000);
  };

  const accentOptions = [
    { key: 'default', label: t('preferences.accentDefault'), color: '#4F46E5' },
    { key: 'emerald', label: t('preferences.accentEmerald'), color: '#059669' },
    { key: 'amber', label: t('preferences.accentAmber'), color: '#D97706' },
    { key: 'rose', label: t('preferences.accentRose'), color: '#E11D48' },
    { key: 'cyan', label: t('preferences.accentCyan'), color: '#0284C7' },
    { key: 'purple', label: t('preferences.accentPurple'), color: '#9333EA' },
  ];

  if (isLoading) {
    return (
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-10 flex flex-col gap-6 animate-pulse">
        <div className="h-10 bg-[var(--bg-card)] rounded-xl w-1/3" />
        <div className="h-4 bg-[var(--bg-card)] rounded-xl w-1/2" />
        <div className="h-48 bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)] mt-4" />
        <div className="h-48 bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)]" />
        <div className="h-48 bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)]" />
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 flex flex-col gap-8 transition-colors duration-300">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border-color)] pb-6">
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <span className="text-[10px] font-mono font-bold uppercase tracking-[0.25em] text-[var(--accent-color)]">
              {t('navigation.preferences')}
            </span>
            <span className="w-1.5 h-1.5 rounded-full bg-[var(--accent-color)]" />
            <span className="text-[10px] font-mono text-[var(--text-muted)]">
              v1.7 Verified
            </span>
          </div>
          <h1 className="font-serif text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">
            {t('preferences.title')}
          </h1>
          <p className="text-xs sm:text-sm text-[var(--text-secondary)]">
            {t('preferences.subtitle')}
          </p>
        </div>

        {/* Header Actions */}
        <div className="flex items-center gap-2.5 shrink-0">
          <button
            type="button"
            id="btn-reset-preferences"
            onClick={() => setIsResetDialogOpen(true)}
            className="px-3.5 py-2 rounded-xl text-xs font-semibold bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)] transition-all cursor-pointer flex items-center gap-2 active:scale-95 shadow-sm"
          >
            <RotateCcw className="w-3.5 h-3.5" />
            <span>{t('preferences.resetDefaultsBtn')}</span>
          </button>
        </div>
      </div>

      {/* Error Alert Banner if any */}
      {isError && (
        <div className="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-500 flex items-center justify-between gap-3 text-xs sm:text-sm">
          <div className="flex items-center gap-2.5">
            <AlertCircle className="w-5 h-5 shrink-0" />
            <span>{errorMessage || 'Ayarlar yüklenirken bir hata oluştu.'}</span>
          </div>
          <button
            type="button"
            onClick={reloadPreferences}
            className="px-3 py-1.5 rounded-lg bg-rose-500 text-white font-semibold text-xs hover:bg-rose-600 transition-colors cursor-pointer flex items-center gap-1.5"
          >
            <RefreshCw className="w-3.5 h-3.5" />
            <span>{t('common.retry')}</span>
          </button>
        </div>
      )}

      {/* Save / Feedback Status Pill */}
      {saveSuccessMessage && (
        <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 flex items-center gap-2.5 text-xs sm:text-sm font-medium animate-fadeIn">
          <CheckCircle2 className="w-4 h-4 shrink-0" />
          <span>{t('preferences.savedSuccess')}</span>
        </div>
      )}

      {/* 1. SECTION: Appearance & Themes */}
      <SettingCard
        id="appearance"
        title={t('preferences.appearanceSection')}
        description={t('preferences.appearanceDesc')}
        icon={<Palette className="w-5 h-5" />}
        badge={appearance.theme.toUpperCase()}
      >
        {/* Theme Selector */}
        <div className="flex flex-col gap-2.5">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
            {t('preferences.theme')}
          </label>
          <div className="grid grid-cols-3 gap-2.5">
            {[
              { key: 'dark', label: t('preferences.themeDark'), icon: Moon },
              { key: 'light', label: t('preferences.themeLight'), icon: Sun },
              { key: 'system', label: t('preferences.themeSystem'), icon: Laptop },
            ].map((th) => {
              const Icon = th.icon;
              const isSelected = theme === th.key;
              return (
                <button
                  key={th.key}
                  type="button"
                  id={`theme-btn-${th.key}`}
                  onClick={() => setTheme(th.key as any)}
                  className={`px-3 py-3 rounded-xl border transition-all cursor-pointer flex flex-col items-center gap-2 text-center select-none active:scale-98 ${
                    isSelected
                      ? 'bg-[var(--accent-light)] border-[var(--accent-color)] text-[var(--accent-color)] shadow-sm font-bold ring-1 ring-[var(--accent-color)]'
                      : 'bg-[var(--bg-tertiary)] border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]/50'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  <span className="text-xs">{th.label}</span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Accent Color Selector */}
        <div className="flex flex-col gap-2.5 pt-4 border-t border-[var(--border-color)]/70">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
            {t('preferences.accentColor')}
          </label>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2">
            {accentOptions.map((opt) => {
              const isSelected = accent === opt.key;
              return (
                <button
                  key={opt.key}
                  type="button"
                  id={`accent-btn-${opt.key}`}
                  onClick={() => setAccent(opt.key)}
                  className={`p-2.5 rounded-xl border transition-all cursor-pointer flex items-center gap-2.5 select-none ${
                    isSelected
                      ? 'bg-[var(--bg-tertiary)] border-[var(--accent-color)] ring-2 ring-[var(--accent-color)]/40 shadow-sm'
                      : 'bg-[var(--bg-tertiary)]/70 border-[var(--border-color)] hover:border-[var(--text-muted)] text-[var(--text-secondary)]'
                  }`}
                >
                  <span
                    className="w-4 h-4 rounded-full shrink-0 shadow-xs flex items-center justify-center text-white"
                    style={{ backgroundColor: opt.color }}
                  >
                    {isSelected && <Check className="w-2.5 h-2.5 stroke-[3]" />}
                  </span>
                  <span className="text-[11px] font-medium truncate text-[var(--text-primary)]">
                    {opt.label}
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      </SettingCard>

      {/* 2. SECTION: Language & Region */}
      <SettingCard
        id="language"
        title={t('preferences.languageSection')}
        description={t('preferences.languageDesc')}
        icon={<Globe className="w-5 h-5" />}
        badge={lang.toUpperCase()}
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {[
            {
              key: 'tr',
              label: 'Türkçe',
              subtitle: 'Varsayılan uygulama dili',
              flag: '🇹🇷',
            },
            {
              key: 'en',
              label: 'English',
              subtitle: 'Application interface in English',
              flag: '🇺🇸',
            },
          ].map((item) => {
            const isSelected = lang === item.key;
            return (
              <button
                key={item.key}
                type="button"
                id={`lang-btn-${item.key}`}
                onClick={() => setLanguage(item.key as any)}
                className={`p-3.5 rounded-xl border text-left transition-all cursor-pointer flex items-center justify-between gap-3 select-none active:scale-98 ${
                  isSelected
                    ? 'bg-[var(--accent-light)] border-[var(--accent-color)] ring-1 ring-[var(--accent-color)] shadow-sm'
                    : 'bg-[var(--bg-tertiary)] border-[var(--border-color)] hover:border-[var(--accent-color)]/50'
                }`}
              >
                <div className="flex items-center gap-3">
                  <span className="text-xl">{item.flag}</span>
                  <div className="flex flex-col">
                    <span
                      className={`text-xs font-bold ${
                        isSelected ? 'text-[var(--accent-color)]' : 'text-[var(--text-primary)]'
                      }`}
                    >
                      {item.label}
                    </span>
                    <span className="text-[11px] text-[var(--text-muted)]">
                      {item.subtitle}
                    </span>
                  </div>
                </div>
                {isSelected && (
                  <CheckCircle2 className="w-4 h-4 text-[var(--accent-color)] shrink-0" />
                )}
              </button>
            );
          })}
        </div>
      </SettingCard>

      {/* 3. SECTION: Reader Defaults */}
      <SettingCard
        id="reader-defaults"
        title={t('preferences.readerDefaults')}
        description={t('preferences.readerDefaultsDesc')}
        icon={<BookOpen className="w-5 h-5" />}
        badge={`${readerSettings.layout} • ${readerSettings.fit}`}
      >
        {/* Layout */}
        <div className="flex flex-col gap-2">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
            {t('preferences.layout')}
          </label>
          <div className="grid grid-cols-3 gap-2">
            {[
              { key: 'vertical', label: t('reader.layoutVertical') },
              { key: 'single', label: t('reader.layoutSingle') },
              { key: 'double', label: t('reader.layoutDouble') },
            ].map((item) => {
              const isSelected = readerSettings.layout === item.key;
              return (
                <button
                  key={item.key}
                  type="button"
                  id={`reader-layout-btn-${item.key}`}
                  onClick={() => updateReaderSettings({ layout: item.key as any })}
                  className={`px-3 py-2.5 text-xs font-medium rounded-xl border transition-all cursor-pointer text-center select-none ${
                    isSelected
                      ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] shadow-sm font-semibold'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]'
                  }`}
                >
                  {item.label}
                </button>
              );
            })}
          </div>
        </div>

        {/* Fit */}
        <div className="flex flex-col gap-2 pt-3 border-t border-[var(--border-color)]/70">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
            {t('preferences.imageFit')}
          </label>
          <div className="grid grid-cols-3 gap-2">
            {[
              { key: 'width', label: t('reader.fitWidth') },
              { key: 'height', label: t('reader.fitHeight') },
              { key: 'original', label: t('reader.fitOriginal') },
            ].map((item) => {
              const isSelected = readerSettings.fit === item.key || readerSettings.imageFit === item.key;
              return (
                <button
                  key={item.key}
                  type="button"
                  id={`reader-fit-btn-${item.key}`}
                  onClick={() => updateReaderSettings({ fit: item.key as any, imageFit: item.key as any })}
                  className={`px-3 py-2.5 text-xs font-medium rounded-xl border transition-all cursor-pointer text-center select-none ${
                    isSelected
                      ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] shadow-sm font-semibold'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]'
                  }`}
                >
                  {item.label}
                </button>
              );
            })}
          </div>
        </div>

        {/* Direction */}
        <div className="flex flex-col gap-2 pt-3 border-t border-[var(--border-color)]/70">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
            {t('preferences.readingDirection')}
          </label>
          <div className="grid grid-cols-2 gap-2">
            {[
              { key: 'ltr', label: t('reader.dirLtr') },
              { key: 'rtl', label: t('reader.dirRtl') },
            ].map((d) => {
              const isSelected = readerSettings.direction === d.key || readerSettings.readingDirection === d.key;
              return (
                <button
                  key={d.key}
                  type="button"
                  id={`reader-dir-btn-${d.key}`}
                  onClick={() => updateReaderSettings({ direction: d.key as any, readingDirection: d.key as any })}
                  className={`px-3 py-2.5 text-xs font-medium rounded-xl border transition-all cursor-pointer text-center select-none ${
                    isSelected
                      ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] shadow-sm font-semibold'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]'
                  }`}
                >
                  {d.label}
                </button>
              );
            })}
          </div>
        </div>

        {/* Reader Feature Toggles */}
        <div className="flex flex-col gap-1 pt-3 border-t border-[var(--border-color)]/70">
          <SettingToggle
            id="reader-auto-hide-ui"
            label={t('preferences.autoHideUi')}
            description={t('preferences.autoHideUiDesc')}
            checked={Boolean(readerSettings.auto_hide_ui)}
            onChange={(checked) => updateReaderSettings({ auto_hide_ui: checked })}
            icon={<Layers className="w-4 h-4" />}
          />

          <SettingToggle
            id="reader-show-progress"
            label={t('preferences.showProgress')}
            description={t('preferences.showProgressDesc')}
            checked={Boolean(readerSettings.show_progress)}
            onChange={(checked) => updateReaderSettings({ show_progress: checked })}
            icon={<Sliders className="w-4 h-4" />}
          />
        </div>
      </SettingCard>

      {/* 4. SECTION: Notification Preferences */}
      <SettingCard
        id="notifications"
        title={t('preferences.notificationsSection')}
        description={t('preferences.notificationsDesc')}
        icon={<Bell className="w-5 h-5" />}
        badge={
          Object.values(notificationSettings).filter(Boolean).length === 5
            ? 'Tümü Aktif'
            : `${Object.values(notificationSettings).filter(Boolean).length}/5 Aktif`
        }
      >
        <div className="flex flex-col gap-1">
          <SettingToggle
            id="notif-new-chapter"
            label={t('preferences.notifNewChapter')}
            description={t('preferences.notifNewChapterDesc')}
            checked={Boolean(notificationSettings.new_chapter)}
            onChange={(val) => updateNotificationSettings({ new_chapter: val })}
            icon={<Bell className="w-4 h-4" />}
          />

          <SettingToggle
            id="notif-comments"
            label={t('preferences.notifComments')}
            description={t('preferences.notifCommentsDesc')}
            checked={Boolean(notificationSettings.comments)}
            onChange={(val) => updateNotificationSettings({ comments: val })}
            icon={<MessageSquare className="w-4 h-4" />}
          />

          <SettingToggle
            id="notif-replies"
            label={t('preferences.notifReplies')}
            description={t('preferences.notifRepliesDesc')}
            checked={Boolean(notificationSettings.replies)}
            onChange={(val) => updateNotificationSettings({ replies: val })}
            icon={<CornerDownRight className="w-4 h-4" />}
          />

          <SettingToggle
            id="notif-mentions"
            label={t('preferences.notifMentions')}
            description={t('preferences.notifMentionsDesc')}
            checked={Boolean(notificationSettings.mentions)}
            onChange={(val) => updateNotificationSettings({ mentions: val })}
            icon={<AtSign className="w-4 h-4" />}
          />

          <SettingToggle
            id="notif-system"
            label={t('preferences.notifSystem')}
            description={t('preferences.notifSystemDesc')}
            checked={Boolean(notificationSettings.system)}
            onChange={(val) => updateNotificationSettings({ system: val })}
            icon={<ShieldCheck className="w-4 h-4" />}
          />
        </div>
      </SettingCard>

      {/* 5. SECTION: Quick Test Presets (Section 17 requirement) */}
      <SettingCard
        id="test-presets"
        title={t('preferences.presetsTitle')}
        description="Prototip doğrulaması için tek tıkla test senaryosu yapılandırmaları uygulayın."
        icon={<Sparkles className="w-5 h-5" />}
        badge="PROTOTYPE PRESETS"
      >
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
          <button
            type="button"
            id="preset-btn-default"
            onClick={() => handleApplyPreset('default')}
            className="p-3 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-[var(--accent-color)] text-left flex flex-col gap-1 transition-all cursor-pointer active:scale-98"
          >
            <span className="text-xs font-bold text-[var(--text-primary)]">
              {t('preferences.presetDefault')}
            </span>
            <span className="text-[10px] text-[var(--text-muted)]">
              Dark • TR • Dikey • Tüm Bildirimler
            </span>
          </button>

          <button
            type="button"
            id="preset-btn-light-rtl"
            onClick={() => handleApplyPreset('light_rtl')}
            className="p-3 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-[var(--accent-color)] text-left flex flex-col gap-1 transition-all cursor-pointer active:scale-98"
          >
            <span className="text-xs font-bold text-[var(--text-primary)]">
              {t('preferences.presetLightRtl')}
            </span>
            <span className="text-[10px] text-[var(--text-muted)]">
              Light • TR • RTL Tek Sayfa • UI Sabit
            </span>
          </button>

          <button
            type="button"
            id="preset-btn-minimal-notifs"
            onClick={() => handleApplyPreset('minimal_notifications')}
            className="p-3 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-[var(--accent-color)] text-left flex flex-col gap-1 transition-all cursor-pointer active:scale-98"
          >
            <span className="text-xs font-bold text-[var(--text-primary)]">
              {t('preferences.presetMinimalNotifs')}
            </span>
            <span className="text-[10px] text-[var(--text-muted)]">
              Dark Emerald • Yalnızca Yeni Bölüm & Sistem
            </span>
          </button>
        </div>
      </SettingCard>

      {/* 6. SECTION: Active Sessions & Account Status */}
      <SettingCard
        id="sessions"
        title={t('preferences.sessionsSection')}
        description="Mevcut oturum durumu ve senkronizasyon zaman damgası."
        icon={<ShieldCheck className="w-5 h-5" />}
      >
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-xl bg-[var(--bg-tertiary)] border border-[var(--border-color)]">
          <div className="flex items-center gap-3">
            <div className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shrink-0" />
            <div className="flex flex-col">
              <span className="text-xs font-bold text-[var(--text-primary)]">
                {preferences.account?.email || 'deniz@example.test'}
              </span>
              <span className="text-[11px] font-mono text-[var(--text-muted)]">
                {t('preferences.lastSync', {
                  date: preferences.account?.last_sync
                    ? new Date(preferences.account.last_sync).toLocaleString()
                    : new Date().toLocaleTimeString(),
                })}
              </span>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span className="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-[10px] font-bold font-mono">
              {t('preferences.currentSession')} (Web App)
            </span>
          </div>
        </div>

        <p className="text-[11px] text-[var(--text-muted)] leading-relaxed mt-1">
          {t('preferences.testingNotice')}
        </p>
      </SettingCard>

      {/* Confirmation Dialog for Reset to Defaults */}
      <Dialog
        isOpen={isResetDialogOpen}
        onClose={() => setIsResetDialogOpen(false)}
        title={t('preferences.resetConfirmTitle')}
      >
        <div className="flex flex-col gap-4 py-2 text-sm text-[var(--text-primary)]">
          <p className="text-xs sm:text-sm text-[var(--text-secondary)] leading-relaxed">
            {t('preferences.resetConfirmDesc')}
          </p>

          <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-[var(--border-color)]">
            <button
              type="button"
              onClick={() => setIsResetDialogOpen(false)}
              className="px-4 py-2 rounded-xl text-xs font-semibold bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] border border-[var(--border-color)] cursor-pointer transition-colors"
            >
              {t('common.cancel')}
            </button>
            <button
              type="button"
              onClick={handleConfirmReset}
              className="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 cursor-pointer shadow-md shadow-rose-600/20 transition-all"
            >
              {t('preferences.resetDefaultsBtn')}
            </button>
          </div>
        </div>
      </Dialog>
    </div>
  );
};

export default PreferencesPage;
