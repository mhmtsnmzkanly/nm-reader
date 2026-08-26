<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">System & Site Settings</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
          <li class="breadcrumb-item active">Settings</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    
    <ul class="nav nav-tabs mb-4" id="configTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold" id="branding-tab" data-bs-toggle="tab" data-bs-target="#tab-branding" type="button" role="tab"><i class="bi bi-brush me-1 text-primary"></i> Site Kimliği & Marka</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#tab-maintenance" type="button" role="tab"><i class="bi bi-shield-shaded me-1 text-warning"></i> Bakım Modu & Güvenlik</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold" id="webhooks-tab" data-bs-toggle="tab" data-bs-target="#tab-webhooks" type="button" role="tab"><i class="bi bi-discord me-1 text-info"></i> Webhook Bildirimleri</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold" id="env-tab" data-bs-toggle="tab" data-bs-target="#tab-env" type="button" role="tab"><i class="bi bi-file-earmark-code me-1 text-secondary"></i> .env Değişkenleri</button>
      </li>
    </ul>

    <div class="tab-content" id="configTabContent">
      <!-- 1. Site Branding Tab -->
      <div class="tab-pane fade show active" id="tab-branding" role="tabpanel">
        <div class="card card-outline card-primary shadow-sm">
          <div class="card-header border-0">
            <h3 class="card-title"><i class="bi bi-globe me-2"></i>Görsel Site Kimliği ve Tema Ayarları</h3>
          </div>
          <div class="card-body">
            <form id="form-site-branding" class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-bold small">Site Adı</label>
                <input class="form-control" name="site_name" id="cfg-site-name" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small">Site Sloganı</label>
                <input class="form-control" name="site_slogan" id="cfg-site-slogan">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small">Logo URL</label>
                <input class="form-control" name="site_logo" id="cfg-site-logo">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small">Favicon URL</label>
                <input class="form-control" name="favicon_url" id="cfg-favicon-url">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small">Varsayılan Tema</label>
                <select class="form-select" name="default_theme" id="cfg-default-theme">
                  <option value="dark">Dark Theme</option>
                  <option value="royal">Royal Theme</option>
                  <option value="bootstrap">Bootstrap Theme</option>
                  <option value="material">Material Theme</option>
                  <option value="apple">Apple Theme</option>
                  <option value="glass">Glassmorphism Theme</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small">Varsayılan Dil</label>
                <select class="form-select" name="default_language" id="cfg-default-language">
                  <option value="tr">Türkçe (TR)</option>
                  <option value="en">English (EN)</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-bold small">Footer Metni</label>
                <input class="form-control" name="footer_text" id="cfg-footer-text">
              </div>
              <div class="col-12 text-end mt-3">
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save2 me-1"></i> Ayarları Kaydet</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- 2. Maintenance Mode Tab -->
      <div class="tab-pane fade" id="tab-maintenance" role="tabpanel">
        <div class="card card-outline card-warning shadow-sm">
          <div class="card-header border-0">
            <h3 class="card-title"><i class="bi bi-cone-striped me-2"></i>Bakım Modu ve IP Beyaz Listesi</h3>
          </div>
          <div class="card-body">
            <form id="form-maintenance-mode" class="row g-3">
              <div class="col-12">
                <div class="form-check form-switch p-3 bg-dark-subtle rounded border">
                  <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="cfg-maintenance-toggle" style="transform: scale(1.4);">
                  <label class="form-check-label fw-bold" for="cfg-maintenance-toggle">Bakım Modunu Aktif Et (503 Service Unavailable)</label>
                  <div class="text-muted small mt-1">Bakım modu açıldığında, sadece admin kullanıcılar ve aşağıda belirtilen IP adresleri siteye erişebilir. Diğer tüm kullanıcılara bakım sayfası gösterilir.</div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-bold small">Beyaz Listeye Alınan IP Adresleri (JSON array veya her satıra bir IP)</label>
                <textarea class="form-control" id="cfg-whitelist-ips" rows="4" placeholder='["127.0.0.1", "::1"]'></textarea>
                <small class="text-muted">Örnek: 127.0.0.1, 192.168.1.1</small>
              </div>
              <div class="col-12 text-end">
                <button type="submit" class="btn btn-warning px-4"><i class="bi bi-shield-check me-1"></i> Bakım Ayarlarını Uygula</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- 3. Webhooks Tab -->
      <div class="tab-pane fade" id="tab-webhooks" role="tabpanel">
        <div class="card card-outline card-info shadow-sm mb-4">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="bi bi-broadcast me-2"></i>Aktif Webhook Entegrasyonları</h3>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-create-webhook"><i class="bi bi-plus-lg me-1"></i> Yeni Webhook</button>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8">
              <thead class="table-dark">
                <tr><th>ID</th><th>Platform</th><th>Tetikleyici Olay</th><th>Hedef URL</th><th>Durum</th><th class="text-end">İşlemler</th></tr>
              </thead>
              <tbody id="webhooks-list-body">
                <tr><td colspan="6" class="text-center py-3 text-muted">Webhooklar yükleniyor...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 4. Environment Vars Tab -->
      <div class="tab-pane fade" id="tab-env" role="tabpanel">
        <div class="row">
          <div class="col-md-3">
            <div class="card card-outline card-secondary shadow-sm mb-4">
              <div class="card-header"><h3 class="card-title">Kategoriler</h3></div>
              <div class="card-body p-0">
                <ul class="nav nav-pills flex-column" id="env-category-nav">
                  <li class="nav-item"><a href="#cat-APP" class="nav-link active"><i class="bi bi-cpu me-2"></i> Application</a></li>
                  <li class="nav-item"><a href="#cat-DB" class="nav-link"><i class="bi bi-database me-2"></i> Database</a></li>
                  <li class="nav-item"><a href="#cat-SITE" class="nav-link"><i class="bi bi-globe me-2"></i> Site Identity</a></li>
                  <li class="nav-item"><a href="#cat-DEFAULT" class="nav-link"><i class="bi bi-palette me-2"></i> Defaults</a></li>
                  <li class="nav-item"><a href="#cat-SECURITY" class="nav-link"><i class="bi bi-shield-lock me-2"></i> Security</a></li>
                  <li class="nav-item"><a href="#cat-OTHER" class="nav-link"><i class="bi bi-three-dots me-2"></i> Other Vars</a></li>
                </ul>
              </div>
            </div>
            <div class="card shadow-sm">
              <div class="card-body text-center p-3">
                <button type="button" class="btn btn-primary btn-sm w-100 mb-2" id="btn-add-var"><i class="bi bi-plus-lg me-1"></i> Add Custom Var</button>
                <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-reload-env"><i class="bi bi-arrow-clockwise me-1"></i> Reload File</button>
              </div>
            </div>
          </div>
          <div class="col-md-9">
            <form id="form-env-config">
              <div id="env-sections-wrapper">
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Parsing configuration...</p></div>
              </div>
              <div class="card mt-4 shadow sticky-bottom bg-white" style="bottom: 1rem; z-index: 1020;">
                <div class="card-body d-flex justify-content-between align-items-center p-3">
                  <div><span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> Safety backup active</span></div>
                  <button type="submit" class="btn btn-danger btn-lg px-5 shadow" id="btn-save-env"><i class="bi bi-save2-fill me-2"></i> Save Changes</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal Create Webhook -->
<div class="modal fade" id="modal-create-webhook" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="form-create-webhook">
        <div class="modal-header text-bg-info">
          <h5 class="modal-title"><i class="bi bi-broadcast me-2"></i>Yeni Webhook Ekle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Platform</label>
            <select class="form-select form-select-sm" name="platform" id="wh-platform" required>
              <option value="discord">Discord Webhook</option>
              <option value="telegram">Telegram Bot</option>
              <option value="custom">Özel HTTP JSON Webhook</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Tetikleyici Olay (Event)</label>
            <select class="form-select form-select-sm" name="event" id="wh-event" required>
              <option value="chapter_published">Yeni Bölüm Yayınlandı</option>
              <option value="blog_approved">Blog Yazısı Onaylandı</option>
              <option value="series_created">Yeni Seri Eklendi</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-bold">Webhook URL / Endpoint</label>
            <input class="form-control form-control-sm" name="webhook_url" id="wh-url" placeholder="https://discord.com/api/webhooks/..." required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Webhook Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .env-section { margin-bottom: 2rem; scroll-margin-top: 5rem; }
  .env-row { transition: all 0.2s; border-left: 3px solid transparent; }
  .env-row:hover { background: #f8f9fa; border-left-color: var(--bs-primary); }
  .env-key-label { font-size: 0.75rem; color: #6c757d; font-weight: 700; margin-bottom: 0.25rem; }
  .env-row .btn-remove { opacity: 0; }
  .env-row:hover .btn-remove { opacity: 1; }
  .sticky-bottom { border-top: 3px solid #dc3545; }
</style>
