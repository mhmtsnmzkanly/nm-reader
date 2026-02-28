<div id="loginModal" class="modal-overlay">
  <div class="modal card">
    <div class="modal-header"><h3>🔐 <?= $__t('login') ?></h3><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      <form id="loginForm">
        <div class="form-group"><label class="form-label"><?= $__t('email') ?></label><input type="email" class="form-item" required></div>
        <div class="form-group"><label class="form-label"><?= $__t('password') ?></label><input type="password" class="form-item" required></div>
        <div class="form-group flex items-center gap-2 mb-2">
          <input type="checkbox" id="loginRemember" style="width:auto;cursor:pointer">
          <label for="loginRemember" style="cursor:pointer;font-size:0.85rem;user-select:none"><?= $__t('remember_me') ?></label>
        </div>
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
        <div class="form-group"><label class="form-label"><?= $__t('username') ?></label><input type="text" class="form-item" required></div>
        <div class="form-group"><label class="form-label"><?= $__t('email') ?></label><input type="email" class="form-item" required></div>
        <div class="form-group"><label class="form-label"><?= $__t('password') ?></label><input type="password" class="form-item" required></div>
        <button type="submit" class="btn btn-primary w-100 mt-2"><?= $__t('signup') ?></button>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-sm btn-outline" onclick="openModal('loginModal')"><?= $__t('back_to_login') ?></button></div>
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
            <div class="btn-group w-100" id="layoutButtonGroup">
              <button class="btn btn-sm btn-outline" data-val="single"><?= $__t('single_page') ?></button>
              <button class="btn btn-sm btn-outline" data-val="double"><?= $__t('double_page') ?></button>
              <button class="btn btn-sm btn-outline" data-val="vertical"><?= $__t('vertical_scroll') ?></button>
            </div>
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

<div id="userSettingsModal" class="modal-overlay">
  <div class="modal card">
    <div class="modal-header"><h3>⚙️ <?= $__t('profile_settings') ?></h3><button class="modal-close" onclick="closeModal()">&times;</button></div>
    <div class="modal-body">
      <form id="userSettingsForm" enctype="multipart/form-data">
        <div class="form-group mb-3">
          <label class="form-label"><?= $__t('biography') ?></label>
          <textarea name="bio" class="form-item" rows="3" maxlength="1000" placeholder="<?= $__t('tell_us_about_yourself') ?>"></textarea>
        </div>
        <div class="form-group mb-3">
          <label class="form-label"><?= $__t('profile_image') ?></label>
          <input type="file" name="profile_image" class="form-item" accept="image/*">
        </div>
        <div class="form-group mb-3">
          <label class="form-label"><?= $__t('cover_image') ?></label>
          <input type="file" name="cover_image" class="form-item" accept="image/*">
        </div>
        <div class="grid grid-2 gap-2 mb-3">
          <div class="form-group">
            <label class="form-label"><?= $__t('theme') ?></label>
            <select name="theme" class="form-item">
              <option value="default">Default</option><option value="dark">Dark</option>
              <option value="royal">Royal</option><option value="bootstrap">Bootstrap</option>
              <option value="material">Material</option><option value="apple">Apple</option>
              <option value="glass">Glass</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label"><?= $__t('language') ?></label>
            <select name="lang" class="form-item">
              <option value="tr">Türkçe</option><option value="en">English</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-2"><?= $__t('update_profile') ?></button>
      </form>
    </div>
  </div>
</div>
