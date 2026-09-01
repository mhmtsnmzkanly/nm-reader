import React, { useState, useRef } from 'react';
import {
  Upload,
  Image as ImageIcon,
  X,
  Sparkles,
  Loader2,
  CheckCircle2,
} from 'lucide-react';
import { usePreferences } from '../../contexts/PreferencesContext';

interface BlogImageUploadProps {
  value?: string;
  onChange: (url: string) => void;
}

const SAMPLE_COVERS = [
  {
    name: 'Anime City Sunset',
    url: 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=800&auto=format&fit=crop&q=80',
  },
  {
    name: 'Manga Desk Study',
    url: 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?w=800&auto=format&fit=crop&q=80',
  },
  {
    name: 'Cyberpunk Neon Alley',
    url: 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=800&auto=format&fit=crop&q=80',
  },
  {
    name: 'Fantasy Magic Castle',
    url: 'https://images.unsplash.com/photo-1514539079130-25950c84af65?w=800&auto=format&fit=crop&q=80',
  },
  {
    name: 'Cherry Blossom Shrine',
    url: 'https://images.unsplash.com/photo-1528164344705-475426879c0d?w=800&auto=format&fit=crop&q=80',
  },
];

export const BlogImageUpload: React.FC<BlogImageUploadProps> = ({ value, onChange }) => {
  const { t } = usePreferences();
  const [isDragging, setIsDragging] = useState<boolean>(false);
  const [isUploading, setIsUploading] = useState<boolean>(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileProcess = (file: File) => {
    if (!file.type.startsWith('image/')) {
      alert(t('blogUpload.uploadError'));
      return;
    }

    setIsUploading(true);

    const reader = new FileReader();
    reader.onload = (e) => {
      setTimeout(() => {
        const resultUrl = (e.target?.result as string) || '';
        onChange(resultUrl);
        setIsUploading(false);
      }, 500); // Simulate upload latency
    };
    reader.onerror = () => {
      setIsUploading(false);
      alert(t('blogUpload.uploadError'));
    };
    reader.readAsDataURL(file);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileProcess(e.dataTransfer.files[0]);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      handleFileProcess(e.target.files[0]);
    }
  };

  return (
    <div className="flex flex-col gap-3">
      <label className="text-xs font-mono font-bold uppercase tracking-wider text-[var(--text-secondary)] flex items-center justify-between">
        <span>{t('blogUpload.coverUpload')}</span>
        <span className="text-[10px] text-[var(--text-muted)] font-normal">
          {t('blogUpload.supportedFormats')}
        </span>
      </label>

      {/* Hidden file input */}
      <input
        type="file"
        ref={fileInputRef}
        onChange={handleInputChange}
        accept="image/png,image/jpeg,image/webp,image/gif"
        className="hidden"
      />

      {/* Preview or Dropzone */}
      {value ? (
        <div className="relative rounded-2xl overflow-hidden border border-[var(--border-color)] bg-[var(--bg-card)] group aspect-[21/9] sm:aspect-[2.4/1] max-h-64 shadow-xs">
          <img
            src={value}
            alt="Blog Cover Preview"
            referrerPolicy="no-referrer"
            className="w-full h-full object-cover"
          />

          <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3 backdrop-blur-xs">
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              className="px-4 py-2 rounded-xl bg-white/90 text-zinc-900 text-xs font-semibold hover:bg-white transition-colors cursor-pointer flex items-center gap-1.5 shadow-md"
            >
              <Upload className="w-3.5 h-3.5" />
              <span>{t('blogUpload.changeImage')}</span>
            </button>

            <button
              type="button"
              onClick={() => onChange('')}
              className="px-4 py-2 rounded-xl bg-rose-500/90 text-white text-xs font-semibold hover:bg-rose-600 transition-colors cursor-pointer flex items-center gap-1.5 shadow-md"
            >
              <X className="w-3.5 h-3.5" />
              <span>{t('blogUpload.removeImage')}</span>
            </button>
          </div>

          <div className="absolute bottom-2 left-2 px-2.5 py-1 rounded-lg bg-black/60 text-white text-[10px] font-mono flex items-center gap-1 backdrop-blur-xs">
            <CheckCircle2 className="w-3 h-3 text-emerald-400" />
            <span>{t('blogUpload.uploadSuccess')}</span>
          </div>
        </div>
      ) : (
        <div
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          onClick={() => fileInputRef.current?.click()}
          className={`border-2 border-dashed rounded-2xl p-6 sm:p-8 flex flex-col items-center justify-center gap-3 text-center cursor-pointer transition-all ${
            isDragging
              ? 'border-[var(--accent-color)] bg-[var(--accent-light)]/40 scale-[0.99]'
              : 'border-[var(--border-color)] bg-[var(--bg-card)] hover:border-[var(--accent-color)]/70 hover:bg-[var(--bg-tertiary)]/50'
          }`}
        >
          {isUploading ? (
            <div className="flex flex-col items-center gap-2">
              <Loader2 className="w-8 h-8 text-[var(--accent-color)] animate-spin" />
              <span className="text-xs font-mono text-[var(--text-secondary)]">
                {t('blogUpload.uploading')}
              </span>
            </div>
          ) : (
            <>
              <div className="w-12 h-12 rounded-2xl bg-[var(--accent-light)] border border-[var(--accent-border)] text-[var(--accent-color)] flex items-center justify-center shadow-xs">
                <Upload className="w-6 h-6" />
              </div>

              <div className="flex flex-col gap-0.5">
                <span className="font-serif font-bold text-sm text-[var(--text-primary)]">
                  {t('blogUpload.dragDrop')}
                </span>
                <span className="text-xs text-[var(--text-muted)] font-mono">
                  {t('blogUpload.supportedFormats')}
                </span>
              </div>
            </>
          )}
        </div>
      )}

      {/* Sample Covers Carousel / Selector */}
      <div className="flex flex-col gap-2 mt-1">
        <div className="flex items-center gap-1.5 text-[11px] font-mono text-[var(--text-muted)]">
          <Sparkles className="w-3 h-3 text-[var(--accent-color)]" />
          <span>{t('blogUpload.sampleCovers')}</span>
        </div>

        <div className="flex items-center gap-2.5 overflow-x-auto pb-1 max-w-full">
          {SAMPLE_COVERS.map((sample, idx) => (
            <button
              type="button"
              key={idx}
              onClick={() => onChange(sample.url)}
              className="group relative rounded-xl overflow-hidden border border-[var(--border-color)] hover:border-[var(--accent-color)] shrink-0 w-24 h-14 transition-all cursor-pointer focus:outline-hidden"
              title={sample.name}
            >
              <img
                src={sample.url}
                alt={sample.name}
                referrerPolicy="no-referrer"
                className="w-full h-full object-cover group-hover:scale-105 transition-transform"
              />
              <div className="absolute inset-0 bg-black/40 group-hover:bg-black/10 transition-colors flex items-center justify-center p-1">
                <span className="text-[9px] font-mono text-white text-center line-clamp-1 font-semibold drop-shadow-xs">
                  {sample.name}
                </span>
              </div>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
};
