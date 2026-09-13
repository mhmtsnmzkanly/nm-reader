import React from 'react';
import { Sliders, Check } from 'lucide-react';
import { usePreferences } from '../../contexts/PreferencesContext';
import { Dialog } from '../ui/Dialog';

type ReaderSettingsProps = {
  isOpen: boolean;
  onClose: () => void;
  mode?: 'image' | 'text';
};

export const ReaderSettings: React.FC<ReaderSettingsProps> = ({
  isOpen,
  onClose,
  mode = 'image',
}) => {
  const { readerSettings, updateReaderSettings, t } = usePreferences();

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title={t('reader.settingsTitle')}>
      <div className="flex flex-col gap-6 py-2 text-sm text-[var(--text-primary)] max-h-[75vh] overflow-y-auto pr-1">
        {/* Layout Mode */}
        {mode === 'image' && (
          <div className="flex flex-col gap-2">
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {t('reader.layout')}
            </label>
            <div className="grid grid-cols-3 gap-2">
              {[
                { key: 'vertical', label: t('reader.layoutVertical') },
                { key: 'single', label: t('reader.layoutSingle') },
                { key: 'double', label: t('reader.layoutDouble') },
              ].map((item) => (
                <button
                  key={item.key}
                  type="button"
                  onClick={() => updateReaderSettings({ layout: item.key as any })}
                  className={`px-3 py-2.5 text-xs font-medium rounded-xl border transition-all cursor-pointer text-center ${
                    readerSettings.layout === item.key
                      ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] shadow-sm'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]'
                  }`}
                >
                  {item.label}
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Image Fit */}
        {mode === 'image' && (
          <div className="flex flex-col gap-2">
            <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {t('reader.imageFit')}
            </label>
            <div className="grid grid-cols-3 gap-2">
              {[
                { key: 'width', label: t('reader.fitWidth') },
                { key: 'height', label: t('reader.fitHeight') },
                { key: 'original', label: t('reader.fitOriginal') },
              ].map((item) => (
                <button
                  key={item.key}
                  type="button"
                  onClick={() => updateReaderSettings({ fit: item.key as any, imageFit: item.key as any })}
                  className={`px-3 py-2.5 text-xs font-medium rounded-xl border transition-all cursor-pointer text-center ${
                    (readerSettings.fit || readerSettings.imageFit) === item.key
                      ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] shadow-sm'
                      : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]'
                  }`}
                >
                  {item.label}
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Text Reader Controls */}
        {mode === 'text' && (
          <>
            <div className="flex flex-col gap-2">
              <div className="flex justify-between items-center">
                <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                  {t('reader.fontSize')}
                </label>
                <span className="font-mono text-xs font-bold text-[var(--accent-color)]">
                  {readerSettings.fontSize}px
                </span>
              </div>
              <input
                type="range"
                min="14"
                max="32"
                value={readerSettings.fontSize}
                onChange={(e) => updateReaderSettings({ fontSize: e.target.value })}
                className="w-full accent-[var(--accent-color)] cursor-pointer"
              />
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                {t('reader.fontFamily')}
              </label>
              <div className="grid grid-cols-3 gap-2">
                {[
                  { key: 'var(--font-sans)', label: 'Sans-Serif' },
                  { key: 'serif', label: 'Serif' },
                  { key: 'var(--font-mono)', label: 'Monospace' },
                ].map((f) => (
                  <button
                    key={f.key}
                    type="button"
                    onClick={() => updateReaderSettings({ fontFamily: f.key })}
                    className={`px-3 py-2.5 text-xs font-medium rounded-xl border transition-all cursor-pointer text-center ${
                      readerSettings.fontFamily === f.key
                        ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] shadow-sm'
                        : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]'
                    }`}
                  >
                    {f.label}
                  </button>
                ))}
              </div>
            </div>

            <div className="flex flex-col gap-2">
              <div className="flex justify-between items-center">
                <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                  {t('reader.lineHeight')}
                </label>
                <span className="font-mono text-xs font-bold text-[var(--accent-color)]">
                  {readerSettings.lineHeight}
                </span>
              </div>
              <input
                type="range"
                min="1.2"
                max="2.6"
                step="0.1"
                value={readerSettings.lineHeight}
                onChange={(e) => updateReaderSettings({ lineHeight: e.target.value })}
                className="w-full accent-[var(--accent-color)] cursor-pointer"
              />
            </div>
          </>
        )}

        {/* Reading Direction */}
        <div className="flex flex-col gap-2">
          <label className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
            {t('reader.readingDirection')}
          </label>
          <div className="grid grid-cols-2 gap-2">
            {[
              { key: 'ltr', label: t('reader.dirLtr') },
              { key: 'rtl', label: t('reader.dirRtl') },
            ].map((d) => (
              <button
                key={d.key}
                type="button"
                onClick={() => updateReaderSettings({ direction: d.key as any, readingDirection: d.key as any })}
                className={`px-3 py-2.5 text-xs font-medium rounded-xl border transition-all cursor-pointer text-center ${
                  (readerSettings.direction || readerSettings.readingDirection) === d.key
                    ? 'bg-[var(--accent-color)] text-white border-[var(--accent-color)] shadow-sm'
                    : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] border-[var(--border-color)] hover:text-[var(--text-primary)] hover:border-[var(--accent-color)]'
                }`}
              >
                {d.label}
              </button>
            ))}
          </div>
        </div>
      </div>
    </Dialog>
  );
};
