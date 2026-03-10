<div id="loginModal" class="modal-overlay">
  <div class="modal card">
    <div class="modal-header"><h3>🔐 <?= $__t('login') ?></h3><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      <form id="loginForm">
        <div class="form-group"><label class="form-label"><?= $__t('email') ?></label><input type="email" name="email" class="form-item" required></div>
        <div class="form-group"><label class="form-label"><?= $__t('password') ?></label><input type="password" name="password" class="form-item" required></div>
        <div class="form-group flex items-center gap-2 mb-2">
          <input type="checkbox" name="remember" id="loginRemember" style="width:auto;cursor:pointer">
          <label for="loginRemember" style="cursor:pointer;font-size:0.85rem;user-select:none"><?= $__t('remember_me') ?></label>
        </div>
        <?php if (!empty($siteConfig['integrations']['cloudflare_turnstile_site_key'] ?? '')): ?>
          <div class="cf-turnstile-wrapper mb-3">
            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($siteConfig['integrations']['cloudflare_turnstile_site_key']) ?>" data-theme="dark"></div>
          </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary w-100 mt-2"><?= $__t('login') ?></button>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-sm btn-outline" onclick="openModal('registerModal')"><?= $__t('create_account') ?></button></div>
  </div>
</div>

<div id="registerModal" class="modal-overlay">
  <div class="modal card">
    <div class="modal-header"><h3>✨ <?= $__t('signup') ?></h3><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      <form id="registerForm">
        <div class="form-group"><label class="form-label"><?= $__t('username') ?></label><input type="text" name="username" class="form-item" required></div>
        <div class="form-group"><label class="form-label"><?= $__t('email') ?></label><input type="email" name="email" class="form-item" required></div>
        <div class="form-group"><label class="form-label"><?= $__t('password') ?></label><input type="password" name="password" class="form-item" required></div>
        <?php if (!empty($siteConfig['integrations']['cloudflare_turnstile_site_key'] ?? '')): ?>
          <div class="cf-turnstile-wrapper mb-3">
            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($siteConfig['integrations']['cloudflare_turnstile_site_key']) ?>" data-theme="dark"></div>
          </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary w-100 mt-2"><?= $__t('signup') ?></button>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-sm btn-outline" onclick="openModal('loginModal')"><?= $__t('back_to_login') ?></button></div>
  </div>
</div>

<!-- Modernized Profile Edit Modal -->
<div id="userSettingsModal" class="modal-overlay">
    <div class="modal card">
        <div class="modal-header">
            <h3>⚙️ <?= $__t('profile_settings') ?></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="userSettingsForm" enctype="multipart/form-data">
                <div class="flex justify-center mb-8">
                    <div class="relative group cursor-pointer" title="Profil Fotoğrafını Değiştir">
                        <div class="w-24 h-24 rounded-[32px] bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-3xl font-black italic text-white shadow-xl overflow-hidden">
                            <?php if (!empty($_SESSION['avatar'])): ?>
                                <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="absolute inset-0 bg-black/60 rounded-[32px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity border-2 border-blue-500/50">
                            <i data-lucide="camera" class="w-6 h-6 text-white"></i>
                            <input type="file" name="profile_image" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="edit-label mb-0">Kullanıcı Adı</label>
                            <i data-lucide="lock" class="w-3 h-3 text-gray-700"></i>
                        </div>
                        <input type="text" class="edit-input edit-input-locked" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" readonly />
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="edit-label mb-0">E-posta Adresi</label>
                            <i data-lucide="lock" class="w-3 h-3 text-gray-700"></i>
                        </div>
                        <input type="email" class="edit-input edit-input-locked" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" readonly />
                    </div>
                    <div>
                        <label class="edit-label">Biyografi (Düzenlenebilir)</label>
                        <textarea name="bio" class="edit-input h-24 resize-none" placeholder="Kendinizden bahsedin..."><?= htmlspecialchars($_SESSION['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="edit-label">Dil</label>
                            <select name="lang" class="edit-input py-3">
                                <option value="tr" <?= ($_SESSION['lang'] ?? 'tr') === 'tr' ? 'selected' : '' ?>>Türkçe</option>
                                <option value="en" <?= ($_SESSION['lang'] ?? 'tr') === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                        <div>
                            <label class="edit-label">Tema</label>
                            <select name="theme" class="edit-input py-3">
                                <option value="default" <?= ($_SESSION['theme'] ?? 'default') === 'default' ? 'selected' : '' ?>>Default</option>
                                <option value="dark" <?= ($_SESSION['theme'] ?? 'default') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                <option value="glass" <?= ($_SESSION['theme'] ?? 'default') === 'glass' ? 'selected' : '' ?>>Glass</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex gap-4">
                    <button type="button" class="flex-1 btn btn-outline" onclick="closeModal()">Kapat</button>
                    <button type="submit" class="flex-[2] btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="notifModal" class="modal-overlay">
  <div class="modal card">
    <div class="modal-header"><h3>🔔 <?= $__t('notifications') ?></h3><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body p-0" style="max-height: 400px; overflow-y: auto;" id="notifModalList">
      <div class="p-4 text-center text-muted"><?= $__t('loading') ?></div>
    </div>
    <div class="modal-footer"><button class="btn btn-sm btn-outline w-100" id="markAllReadBtn"><?= $__t('mark_all_read') ?></button></div>
  </div>
</div>

<div id="readerSettingsModal" class="modal-overlay">
  <div class="modal card modal-wide">
    <div class="modal-header"><h3>⚙️ <?= $__t('reader_settings') ?></h3><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body p-0"><div class="flex" style="min-height: 400px;">
      <div class="modal-sidebar border-r p-2" id="readerTabSidebar" style="width: 160px; background: var(--surface)">
        <button class="btn btn-sm btn-outline w-100 text-left mb-1 active" data-tab="layout"><?= $__t('layout') ?></button>
        <button class="btn btn-sm btn-outline w-100 text-left mb-1" data-tab="typography"><?= $__t('typography') ?></button>
        <button class="btn btn-sm btn-outline w-100 text-left mb-1" data-tab="appearance"><?= $__t('appearance') ?></button>
      </div>
      <div class="modal-content-area flex-grow p-4">
        <div id="tab-layout" class="settings-tab">
          <div class="form-group mb-3"><label class="text-xs"><?= $__t('page_layout') ?></label>
            <select class="form-item" name="reader_layout" id="readerLayoutSelect">
              <option value="vertical"><?= $__t('vertical_scroll') ?></option>
              <option value="single"><?= $__t('single_page') ?></option>
              <option value="double"><?= $__t('double_page') ?></option>
            </select>
          </div>
          <div class="form-group"><label class="text-xs"><?= $__t('image_fit') ?></label>
            <select class="form-item" name="reader_image_fit">
              <option value="width"><?= $__t('fit_width') ?></option>
              <option value="height"><?= $__t('fit_height') ?></option>
              <option value="original"><?= $__t('original_size') ?></option>
            </select>
          </div>
        </div>
        <div id="tab-typography" class="settings-tab hidden">
          <div class="form-group mb-3"><label class="text-xs"><?= $__t('font_family') ?></label>
            <select class="form-item" name="reader_font_family">
              <option value="var(--font-sans)">Sans</option>
              <option value="serif">Serif</option>
            </select>
          </div>
          <div class="form-group"><label class="text-xs"><?= $__t('font_size') ?> (<span id="fontSizeVal">18</span>px)</label>
            <input type="range" name="reader_font_size" min="12" max="32" value="18" class="w-100">
          </div>
        </div>
        <div id="tab-appearance" class="settings-tab hidden text-center">
          <div class="flex flex-wrap gap-2 justify-center">
            <button class="theme-btn btn btn-outline p-2" data-theme="default">☀️ Default</button>
            <button class="theme-btn btn btn-outline p-2" data-theme="dark">🌑 Dark</button>
            <button class="theme-btn btn btn-outline p-2" data-theme="royal">👑 Royal</button>
            <button class="theme-btn btn btn-outline p-2" data-theme="bootstrap">🅱️ Bootstrap</button>
            <button class="theme-btn btn btn-outline p-2" data-theme="material">Ⓜ️ Material</button>
            <button class="theme-btn btn btn-outline p-2" data-theme="apple">🍎 Apple</button>
            <button class="theme-btn btn btn-outline p-2" data-theme="glass">💎 Glass</button>
          </div>
        </div>
      </div>
    </div></div>
    <div class="modal-footer"><button class="btn btn-sm btn-primary px-5" id="saveAllSettingsBtn"><?= $__t('save_and_apply') ?></button></div>
  </div>
</div>
