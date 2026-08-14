import React, { useState } from 'react';
import { Image as ImageIcon, Sparkles, AlertCircle, Check } from 'lucide-react';
import { UserProfile } from '../../types/api';
import { Dialog } from '../ui/Dialog';
import { FormField } from '../ui/FormField';
import { Button } from '../ui/Button';
import { Avatar } from './Avatar';

type ProfileEditModalProps = {
  isOpen: boolean;
  onClose: () => void;
  user: UserProfile;
  onSave: (updated: Partial<UserProfile>) => Promise<void>;
};

const AVATAR_PRESETS = [
  'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=200&auto=format&fit=crop&q=80',
  'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
  'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&auto=format&fit=crop&q=80',
  'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&auto=format&fit=crop&q=80',
  'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=200&auto=format&fit=crop&q=80',
  'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=200&auto=format&fit=crop&q=80',
];

export const ProfileEditModal: React.FC<ProfileEditModalProps> = ({
  isOpen,
  onClose,
  user,
  onSave,
}) => {
  const [displayName, setDisplayName] = useState(user.display_name || user.username || '');
  const [bio, setBio] = useState(user.bio || '');
  const [avatarUrl, setAvatarUrl] = useState(user.avatar || user.profile_image || '');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleAvatarFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 2 * 1024 * 1024) {
        setError('Görsel boyutu 2MB üzerinde olamaz.');
        return;
      }
      setError(null);
      const reader = new FileReader();
      reader.onloadend = () => {
        if (typeof reader.result === 'string') {
          setAvatarUrl(reader.result);
        }
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsLoading(true);

    try {
      await onSave({
        display_name: displayName.trim(),
        bio: bio.trim(),
        avatar: avatarUrl.trim() || null,
        profile_image: avatarUrl.trim() || null,
      });
      onClose();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Profil güncellenirken bir hata oluştu.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="Profili Düzenle" maxWidth="md">
      <form onSubmit={handleSubmit} className="flex flex-col gap-5">
        {error && (
          <div className="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-500 text-xs flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* Avatar Section & Preview */}
        <div className="p-4 bg-[var(--bg-tertiary)] rounded-2xl border border-[var(--border-color)] flex flex-col gap-4">
          <span className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)]">
            Profil Resmi (Avatar)
          </span>

          <div className="flex items-center gap-4">
            <Avatar
              src={avatarUrl}
              name={displayName || user.username}
              size="lg"
              ring
            />
            <div className="flex flex-col gap-1.5 flex-1 min-w-0">
              <label
                htmlFor="avatar-upload"
                className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-[var(--bg-card)] border border-[var(--border-color)] hover:border-[var(--accent-color)] text-xs font-mono font-semibold text-[var(--text-primary)] hover:text-[var(--accent-color)] transition-all cursor-pointer shadow-xs active:scale-95 text-center"
              >
                <ImageIcon className="w-4 h-4 text-[var(--accent-color)]" />
                <span>Görsel Yükle</span>
              </label>
              <input
                id="avatar-upload"
                type="file"
                accept="image/*"
                onChange={handleAvatarFileChange}
                className="hidden"
              />
              <span className="text-[10px] text-[var(--text-muted)]">
                PNG, JPG veya WEBP (Maks. 2MB)
              </span>
            </div>
          </div>

          {/* Preset Avatar Selection */}
          <div className="flex flex-col gap-2 pt-2 border-t border-[var(--border-color)]/50">
            <span className="text-[10px] font-mono text-[var(--text-muted)]">
              veya hazır avatarlardan seçin:
            </span>
            <div className="flex items-center gap-2 overflow-x-auto py-1">
              {AVATAR_PRESETS.map((preset, idx) => (
                <button
                  key={idx}
                  type="button"
                  onClick={() => setAvatarUrl(preset)}
                  className={`relative w-10 h-10 rounded-full overflow-hidden shrink-0 border-2 transition-all cursor-pointer ${
                    avatarUrl === preset
                      ? 'border-[var(--accent-color)] scale-110 shadow-md'
                      : 'border-transparent opacity-70 hover:opacity-100'
                  }`}
                >
                  <img
                    src={preset}
                    alt={`Preset ${idx + 1}`}
                    className="w-full h-full object-cover"
                  />
                  {avatarUrl === preset && (
                    <div className="absolute inset-0 bg-[var(--accent-color)]/40 flex items-center justify-center text-white">
                      <Check className="w-3.5 h-3.5" />
                    </div>
                  )}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Display Name */}
        <FormField label="Görünen İsim" hint="Diğer kullanıcıların profilinizde göreceği isim">
          <input
            type="text"
            value={displayName}
            onChange={(e) => setDisplayName(e.target.value)}
            placeholder="Örn: Deniz Yılmaz"
            maxLength={50}
            required
            className="w-full px-4 py-2.5 bg-[var(--bg-tertiary)] border border-[var(--border-color)] focus:border-[var(--accent-color)] rounded-xl text-sm text-[var(--text-primary)] outline-none transition-colors"
          />
        </FormField>

        {/* Bio */}
        <FormField label="Hakkımda / Biyografi" hint="Kendinizi ve okuma zevklerinizi tanıtın">
          <textarea
            value={bio}
            onChange={(e) => setBio(e.target.value)}
            placeholder="Manga ve çizgi roman okumayı seven bir okuyucu..."
            rows={3}
            maxLength={300}
            className="w-full px-4 py-2.5 bg-[var(--bg-tertiary)] border border-[var(--border-color)] focus:border-[var(--accent-color)] rounded-xl text-sm text-[var(--text-primary)] outline-none transition-colors resize-none"
          />
        </FormField>

        {/* Action Buttons */}
        <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border-color)]">
          <Button
            type="button"
            variant="secondary"
            onClick={onClose}
            disabled={isLoading}
          >
            İptal
          </Button>
          <Button
            type="submit"
            variant="primary"
            isLoading={isLoading}
          >
            <Sparkles className="w-3.5 h-3.5" />
            <span>Kaydet</span>
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
