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
                    <div class="relative group cursor-pointer" title="<?= $__t('ui.change_profile_photo') ?>">
                        <div class="w-24 h-24 rounded-[32px] bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-3xl font-black text-white shadow-xl overflow-hidden">
                            <?php if (!empty($_SESSION['avatar'])): ?>
                                <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" class="w-full h-full object-cover" loading="lazy" decoding="async">
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
                            <label class="edit-label mb-0"><?= $__t('username') ?></label>
                            <i data-lucide="lock" class="w-3 h-3 text-gray-700"></i>
                        </div>
                        <input type="text" class="edit-input edit-input-locked" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" readonly />
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="edit-label mb-0"><?= $__t('email') ?></label>
                            <i data-lucide="lock" class="w-3 h-3 text-gray-700"></i>
                        </div>
                        <input type="email" class="edit-input edit-input-locked" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" readonly />
                    </div>
                    <div>
                        <label class="edit-label"><?= $__t('biography') ?></label>
                        <textarea name="bio" class="edit-input h-24 resize-none" placeholder="<?= $__t('tell_us_about_yourself') ?>"><?= htmlspecialchars($_SESSION['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="edit-label"><?= $__t('language') ?></label>
                            <select name="lang" class="edit-input py-3">
                                <option value="tr" <?= ($_SESSION['lang'] ?? 'tr') === 'tr' ? 'selected' : '' ?>>Türkçe</option>
                                <option value="en" <?= ($_SESSION['lang'] ?? 'tr') === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                        <div>
                            <label class="edit-label"><?= $__t('theme') ?></label>
                            <select name="theme" class="edit-input py-3">
                                <option value="default" <?= ($_SESSION['theme'] ?? 'default') === 'default' ? 'selected' : '' ?>>Default</option>
                                <option value="dark" <?= ($_SESSION['theme'] ?? 'default') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                <option value="glass" <?= ($_SESSION['theme'] ?? 'default') === 'glass' ? 'selected' : '' ?>>Glass</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex gap-4">
                    <button type="button" class="flex-1 btn btn-outline" onclick="closeModal()"><?= $__t('ui.close') ?></button>
                    <button type="submit" class="flex-[2] btn btn-primary"><?= $__t('ui.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modernized Notifications Modal -->
<div id="notifModal" class="modal-overlay">
    <div class="modal card">
        <div class="modal-header">
            <h3 class="flex items-center gap-2">🔔 <?= $__t('notifications') ?></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body p-0 max-h-[450px] overflow-y-auto" id="notifModalList">
            <div class="p-8 text-center text-gray-500 text-sm">
                <i data-lucide="bell-off" class="w-8 h-8 mx-auto mb-3 opacity-20"></i>
                <?= $__t('loading') ?>
            </div>
        </div>
        <div class="p-4 border-t border-white/5">
            <button class="w-full py-3 bg-white/5 hover:bg-white/10 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all" id="markAllReadBtn">
                <?= $__t('mark_all_read') ?>
            </button>
        </div>
    </div>
</div>

<!-- Modernized Reader Settings Modal -->
<div id="readerSettingsModal" class="modal-overlay">
    <div class="modal card max-w-xl">
        <div class="modal-header">
            <h3 class="flex items-center gap-2">⚙️ <?= $__t('reader_settings') ?></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body p-0 flex flex-col md:flex-row min-h-[400px]">
            <!-- Sidebar -->
            <div class="w-full md:w-48 bg-white/5 border-r border-white/5 p-4 space-y-2" id="readerTabSidebar">
                <button class="w-full text-left px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all active bg-blue-600 text-white shadow-lg shadow-blue-600/20" data-tab="layout">
                    <?= $__t('layout') ?>
                </button>
                <button class="w-full text-left px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-gray-500 hover:bg-white/5 hover:text-white" data-tab="typography">
                    <?= $__t('typography') ?>
                </button>
                <button class="w-full text-left px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-gray-500 hover:bg-white/5 hover:text-white" data-tab="appearance">
                    <?= $__t('appearance') ?>
                </button>
            </div>

            <!-- Content Area -->
            <div class="flex-grow p-8">
                <!-- Layout Tab -->
                <div id="tab-layout" class="settings-tab space-y-6">
                    <div>
                        <label class="edit-label"><?= $__t('page_layout') ?></label>
                        <select class="edit-input" name="reader_layout" id="readerLayoutSelect">
                            <option value="vertical"><?= $__t('vertical_scroll') ?></option>
                            <option value="single"><?= $__t('single_page') ?></option>
                            <option value="double"><?= $__t('double_page') ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="edit-label"><?= $__t('image_fit') ?></label>
                        <select class="edit-input" name="reader_image_fit">
                            <option value="width"><?= $__t('fit_width') ?></option>
                            <option value="height"><?= $__t('fit_height') ?></option>
                            <option value="original"><?= $__t('original_size') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Typography Tab -->
                <div id="tab-typography" class="settings-tab hidden space-y-6">
                    <div>
                        <label class="edit-label"><?= $__t('font_family') ?></label>
                        <select class="edit-input" name="reader_font_family">
                            <option value="var(--font-sans)">Inter (Sans)</option>
                            <option value="Lora, serif">Lora (Serif)</option>
                        </select>
                    </div>
                    <div>
                        <label class="edit-label flex justify-between">
                            <?= $__t('font_size') ?>
                            <span id="fontSizeVal" class="text-blue-500">18px</span>
                        </label>
                        <input type="range" name="reader_font_size" min="12" max="32" value="18" class="w-full h-1.5 bg-white/10 rounded-lg appearance-none cursor-pointer accent-blue-600 mt-4">
                    </div>
                </div>

                <!-- Appearance Tab -->
                <div id="tab-appearance" class="settings-tab hidden space-y-6">
                    <label class="edit-label">Renk Teması</label>
                    <div class="grid grid-cols-2 gap-3">
                        <?php 
                        $readerThemes = [
                            ['id' => 'default', 'label' => 'Aydınlık', 'icon' => '☀️'],
                            ['id' => 'dark', 'label' => 'Karanlık', 'icon' => '🌑'],
                            ['id' => 'sepia', 'label' => 'Sepia', 'icon' => '📖'],
                            ['id' => 'royal', 'label' => 'Kraliyet', 'icon' => '👑'],
                        ];
                        foreach ($readerThemes as $rt): 
                        ?>
                        <button class="theme-btn flex items-center justify-between px-4 py-3 rounded-xl border border-white/5 bg-white/5 hover:border-blue-500/50 transition-all text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white" data-theme="<?= $rt['id'] ?>">
                            <span><?= $rt['icon'] ?> <?= $rt['label'] ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-6 border-t border-white/5 flex gap-4">
            <button class="flex-1 btn btn-outline" onclick="closeModal()">İptal</button>
            <button class="flex-[2] btn btn-primary shadow-xl shadow-blue-600/20" id="saveAllSettingsBtn"><?= $__t('save_and_apply') ?></button>
        </div>
    </div>
</div>
